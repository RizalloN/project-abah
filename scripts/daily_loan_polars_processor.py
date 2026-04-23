#!/usr/bin/env python3
"""
Daily Loan CSV processor
=========================

Flow:
  1. Parse raw CSV records with the stdlib csv reader.
  2. Repair the wrapped Daily Loan rows into normal CSV records.
  3. Load the repaired file with Polars.
  4. Filter rows that do not have the required Daily Loan values.
  5. Write a clean CSV that Laravel can feed into LOAD DATA LOCAL INFILE.
"""

from __future__ import annotations

import argparse
import csv
import io
import json
import os
import re
import sys
import tempfile
from datetime import date, datetime
from pathlib import Path
from typing import Iterable

try:
    from dateutil import parser as dateutil_parser
    _DATEUTIL_AVAILABLE = True
except ImportError:
    _DATEUTIL_AVAILABLE = False

DATE_COLUMNS = {
    "PERIODE",
    "TGL_REALISASI",
    "TGL_JATUH_TEMPO",
    "TANGGAL_MENUNGGAK",
    "TGL_BAYAR_TERAKHIR",
    "TGL_TERMINATE",
    "LAST_DATE_MAINTENANCE_BILLING",
    "NEXT_PMT_DATE",
    "NEXT_PMT_INT_DATE",
    "TGL_AKAD_RESTRUK",
}


def normalize_integer_string(value: object) -> str:
    text = normalize_cell(value)
    if text == "":
        return ""

    decimal_text = normalize_decimal_value(text)
    if decimal_text is None:
        return ""

    try:
        return str(int(round(float(decimal_text))))
    except Exception:
        return ""


def send_event(event_type: str, data: dict) -> None:
    payload = dict(data)
    payload["type"] = event_type
    print(json.dumps(payload, ensure_ascii=False, default=str), flush=True)


def send_progress(percent: int, message: str, rows_done: int = 0, total: int = 0, speed: int = 0) -> None:
    send_event(
        "progress",
        {
            "percent": percent,
            "message": message,
            "rows_done": rows_done,
            "total": total,
            "speed": speed,
        },
    )


def send_error(message: str) -> None:
    send_event("error", {"message": message})


def load_config(config_path: str) -> dict:
    with open(config_path, "r", encoding="utf-8-sig") as handle:
        return json.load(handle)


def detect_delimiter(path: str, fallback: str = ",") -> str:
    try:
        with open(path, "r", encoding="utf-8-sig", errors="replace", newline="") as handle:
            samples: list[str] = []
            for line in handle:
                line = line.strip("\r\n")
                if line.strip() == "":
                    continue
                samples.append(line)
                if len(samples) >= 12:
                    break

        if not samples:
            return fallback

        best = fallback
        best_score = -10**9
        for delimiter in [",", ";", "\t", "|"]:
            counts = []
            for sample in samples:
                parsed = parse_csv_text(sample, delimiter)
                counts.append(len(parsed))
            max_count = max(counts)
            min_count = min(counts)
            avg_count = sum(counts) / max(len(counts), 1)
            stable_rows = sum(1 for count in counts if count == max_count)
            score = (max_count * 1000) + (stable_rows * 100) - ((max_count - min_count) * 20) + int(round(avg_count))
            if score > best_score:
                best_score = score
                best = delimiter
        return best
    except Exception:
        return fallback


def normalize_cell(value: object) -> str:
    text = "" if value is None else str(value)
    if text == "":
        return ""

    previous = None
    while text != previous:
        previous = text
        text = text.replace('""', '"')
        trimmed = text.strip()
        if len(trimmed) >= 2 and trimmed.startswith('"') and trimmed.endswith('"'):
            text = trimmed[1:-1]
            continue

    return text.strip()


def strip_wrapped_payload(text: str) -> str:
    value = normalize_cell(text)
    value = value.replace("\ufeff", "")
    value = re.sub(r";+\s*$", "", value)

    match = re.match(r'^"(.*)"\s*$', value, flags=re.S)
    if match:
        value = match.group(1)

    return value.strip()


def parse_csv_text(text: str, delimiter: str) -> list[str]:
    buffer = io.StringIO(text)
    reader = csv.reader(buffer, delimiter=delimiter, quotechar='"', escapechar="\\", strict=False)
    try:
        row = next(reader)
    except StopIteration:
        return []
    return [normalize_cell(cell) for cell in row]


def normalize_header_name(value: object) -> str:
    text = normalize_cell(value).upper()
    text = re.sub(r"[^A-Z0-9]+", "_", text)
    return text.strip("_")


def is_daily_loan_header_row(row: list[str]) -> bool:
    normalized_cells = [normalize_header_name(cell) for cell in row]
    if not normalized_cells or normalized_cells[0] != "PERIODE":
        return False

    header_groups = [
        {"KODE_KANWIL1", "KODE_KANWIL"},
        {"CIFNO"},
        {"NOMOR_REKENING1", "NOMOR_REKENING"},
        {"BAKI_DEBET1", "BAKI_DEBET"},
    ]
    matched_headers = sum(1 for variants in header_groups if any(header in normalized_cells for header in variants))
    return matched_headers >= 3


def parse_logical_row(row: list[str], delimiter: str, expected_columns: int | None) -> tuple[list[str] | None, bool]:
    cells = [normalize_cell(cell) for cell in row]
    changed = False

    while expected_columns is not None and len(cells) > expected_columns and cells[-1] == "":
        cells.pop()
        changed = True

    if cells:
        trimmed_last = re.sub(r";+\s*$", "", cells[-1])
        if trimmed_last != cells[-1]:
            cells[-1] = trimmed_last
            changed = True

    if len(cells) == 1:
        payload = strip_wrapped_payload(cells[0])
        if delimiter in payload:
            parsed = parse_csv_text(payload, delimiter)
            if parsed:
                cells = parsed
                changed = True

    if expected_columns is not None:
        if len(cells) == expected_columns - 1:
            # Daily Loan exports sometimes omit the final empty trailing column.
            # Pad it back so valid rows are not dropped by a strict field-count check.
            cells = cells + [""]
            changed = True
        elif len(cells) != expected_columns:
            return None, changed

    return cells, changed


def normalize_date_value(value: object) -> str | None:
    text = normalize_cell(value)
    if text == "":
        return None

    if not _DATEUTIL_AVAILABLE:
        return None

    try:
        parsed = dateutil_parser.parse(text.replace("/", "-"), dayfirst=True, yearfirst=False)
        return parsed.strftime("%Y-%m-%d")
    except Exception:
        return None


def normalize_date_string(value: object) -> str:
    parsed = normalize_date_value(value)
    if parsed is not None:
        return parsed
    return normalize_cell(value)


def is_non_date_like_value(value: object) -> bool:
    text = normalize_cell(value)
    if text == "":
        return False

    compact = text.strip()
    if re.fullmatch(r"\d{8}", compact):
        return False

    if re.fullmatch(r"\d{4}[-/]\d{2}[-/]\d{2}", compact):
        return False

    if re.fullmatch(r"\d{2}[-/]\d{2}[-/]\d{4}", compact):
        return False

    if re.fullmatch(r"\d{2}[-/]\d{2}[-/]\d{2}", compact):
        return False

    if re.fullmatch(r"\d{4}[-/]\d{2}[-/]\d{2}\s+\d{2}:\d{2}(:\d{2})?", compact):
        return False

    if re.fullmatch(r"\d{2}[-/]\d{2}[-/]\d{4}\s+\d{2}:\d{2}(:\d{2})?", compact):
        return False

    return True


def build_non_date_like_expr(expr):
    compact = expr.fill_null("").str.strip_chars()
    patterns = [
        r"^\d{8}$",
        r"^\d{4}[-/]\d{2}[-/]\d{2}$",
        r"^\d{2}[-/]\d{2}[-/]\d{4}$",
        r"^\d{2}[-/]\d{2}[-/]\d{2}$",
        r"^\d{4}[-/]\d{2}[-/]\d{2}\s+\d{2}:\d{2}(:\d{2})?$",
        r"^\d{2}[-/]\d{2}[-/]\d{4}\s+\d{2}:\d{2}(:\d{2})?$",
    ]

    invalid = None
    for pattern in patterns:
        matched = compact.str.contains(pattern)
        invalid = matched if invalid is None else (invalid | matched)

    return compact.ne("") & (~invalid if invalid is not None else True)


def normalize_decimal_value(value: object) -> str | None:
    text = normalize_cell(value)
    if text == "":
        return None

    is_negative = False
    match = re.match(r"^\((.*)\)$", text)
    if match:
        text = str(match.group(1) or "").strip()
        is_negative = True

    if text.endswith("-"):
        text = text[:-1].strip()
        is_negative = True

    text = re.sub(r"\s+", "", text)
    text = re.sub(r"[^0-9,\.\-]", "", text)

    if text in ("", "-"):
        return None

    has_comma = "," in text
    has_dot = "." in text

    if has_comma and has_dot:
        if text.rfind(",") > text.rfind("."):
            text = text.replace(".", "")
            text = text.replace(",", ".")
        else:
            text = text.replace(",", "")
    elif has_comma:
        parts = text.split(",")
        last_part = parts[-1]
        if len(parts) > 2 or len(last_part) == 3:
            text = text.replace(",", "")
        else:
            text = text.replace(",", ".")
    elif has_dot:
        parts = text.split(".")
        last_part = parts[-1]
        if len(parts) > 2 or len(last_part) == 3:
            text = text.replace(".", "")

    try:
        value_float = float(text)
        if is_negative:
            value_float *= -1
        return f"{value_float:.2f}"
    except Exception:
        return None


def sanitize_source(
    source_path: str,
    delimiter: str,
    expected_columns: int | None = None,
) -> tuple[str, list[str], int, int, bool, list[int]]:
    import time

    temp_dir = Path(tempfile.gettempdir())
    fd, temp_path = tempfile.mkstemp(prefix="daily_loan_polars_sanitized_", suffix=".csv", dir=str(temp_dir))
    os.close(fd)

    total_records = 0
    skipped_count = 0
    rewrite_needed = False
    skipped_rows: list[int] = []
    headers: list[str] = []
    last_progress_time = time.monotonic()

    with open(source_path, "r", encoding="utf-8-sig", errors="replace", newline="") as raw_handle, open(
        temp_path,
        "w",
        encoding="utf-8",
        newline="",
    ) as out_handle:
        reader = csv.reader(raw_handle, delimiter=delimiter, quotechar='"', escapechar="\\", strict=False)
        writer = csv.writer(out_handle, delimiter=delimiter, quotechar='"', escapechar="\\", lineterminator="\n")

        for row_number, row in enumerate(reader, start=1):
            if not row or all(normalize_cell(cell) == "" for cell in row):
                continue

            now = time.monotonic()
            if row_number % 10000 == 0 or (now - last_progress_time) >= 3.0:
                last_progress_time = now
                send_progress(min(50, 5 + int((row_number / 250000) * 45)), f"Menyiapkan sanitasi CSV Daily Loan... ({row_number} record)")

            if not headers:
                parsed_header, changed = parse_logical_row(row, delimiter, None)
                if parsed_header is None or not parsed_header:
                    continue

                parsed_header = [re.sub(r";+\s*$", "", normalize_cell(cell)) for cell in parsed_header]
                if not is_daily_loan_header_row(parsed_header):
                    continue

                headers = parsed_header
                expected_columns = len(headers)
                rewrite_needed = rewrite_needed or changed or any(cell != normalize_cell(cell) for cell in row)
                writer.writerow(headers)
                continue

            total_records += 1
            parsed_row, changed = parse_logical_row(row, delimiter, expected_columns)
            if parsed_row is None:
                skipped_count += 1
                skipped_rows.append(row_number)
                continue

            if len(parsed_row) != expected_columns:
                skipped_count += 1
                skipped_rows.append(row_number)
                continue

            rewrite_needed = rewrite_needed or changed or (len(row) != expected_columns)
            writer.writerow(parsed_row)

    if not headers:
        raise RuntimeError("Header CSV Daily Loan tidak ditemukan.")

    return temp_path, headers, total_records, skipped_count, rewrite_needed, skipped_rows


def read_with_polars(path: str, headers: list[str], delimiter: str):
    import polars as pl

    schema_overrides = {header: pl.Utf8 for header in headers}

    read_attempts = [
        lambda: pl.read_csv(
            path,
            separator=delimiter,
            has_header=True,
            quote_char='"',
            escapechar="\\",
            schema_overrides=schema_overrides,
            infer_schema_length=0,
            ignore_errors=False,
            truncate_ragged_lines=False,
            encoding="utf8-lossy",
        ),
        lambda: pl.read_csv(
            path,
            separator=delimiter,
            has_header=True,
            quote_char='"',
            schema_overrides=schema_overrides,
            infer_schema_length=0,
            ignore_errors=False,
            truncate_ragged_lines=False,
            encoding="utf8-lossy",
        ),
        lambda: pl.read_csv(
            path,
            separator=delimiter,
            has_header=True,
            schema_overrides=schema_overrides,
            ignore_errors=False,
            encoding="utf8-lossy",
        ),
    ]

    last_error: Exception | None = None
    for attempt in read_attempts:
        try:
            return attempt()
        except Exception as exc:
            last_error = exc

    raise RuntimeError(f"Gagal membaca CSV dengan Polars: {last_error}")


def write_with_polars(df, path: str, delimiter: str) -> None:
    attempts = [
        lambda: df.write_csv(path, separator=delimiter, quote_style="necessary", include_header=True, line_terminator="\n"),
        lambda: df.write_csv(path, separator=delimiter, quote_style="necessary", include_header=True),
        lambda: df.write_csv(path, separator=delimiter, include_header=True),
    ]

    last_error: Exception | None = None
    for attempt in attempts:
        try:
            attempt()
            return
        except Exception as exc:
            last_error = exc

    raise RuntimeError(f"Gagal menulis CSV hasil Polars: {last_error}")


def stage_daily_loan(config: dict) -> None:
    import polars as pl

    source_path = config["file_path"]
    output_csv_path = config["output_csv_path"]
    delimiter = config.get("delimiter") or detect_delimiter(source_path, ",")
    required_headers = [str(value) for value in config.get("required_headers", ["PERIODE", "NOMOR_REKENING1", "BAKI_DEBET1"])]
    strict_non_date_headers = [str(value).upper() for value in config.get("strict_non_date_headers", ["KODE_KANWIL1"])]

    send_progress(5, "Membaca dan menyiapkan CSV Daily Loan dengan Polars...", 0, 0, 0)
    temp_sanitized_path, headers, total_records, structural_skipped, rewrite_needed, skipped_rows = sanitize_source(source_path, delimiter)
    total_data_rows = max(0, total_records - 1)

    try:
        send_progress(56, "Sanitasi selesai. Membaca file bersih dengan Polars dan audit anchor kode kanwil...", total_data_rows, total_data_rows, 0)
        df = read_with_polars(temp_sanitized_path, headers, delimiter)

        if df.height == 0:
            raise RuntimeError("Polars tidak menemukan baris data yang valid.")

        # Keep Polars work limited to structural cleanup and row validation.
        # Type normalization is deferred to MySQL expressions during LOAD DATA.
        df = df.with_columns([
            pl.col(column).cast(pl.Utf8).str.strip_chars().alias(column)
            for column in df.columns
        ])

        valid_expr = None
        for required in required_headers:
            if required not in df.columns:
                continue

            expr = pl.col(required).str.strip_chars().ne("")
            valid_expr = expr if valid_expr is None else (valid_expr & expr)

        if valid_expr is not None:
            df = df.filter(valid_expr)

        audit_expr = None
        for strict_header in strict_non_date_headers:
            if strict_header not in df.columns:
                continue

            expr = build_non_date_like_expr(pl.col(strict_header))
            audit_expr = expr if audit_expr is None else (audit_expr & expr)

        if audit_expr is not None:
            df = df.filter(audit_expr)

        written_rows = int(df.height)
        skipped_total = int(structural_skipped + max(0, total_data_rows - structural_skipped - written_rows))
        if skipped_total < 0:
            skipped_total = 0

        send_progress(86, "Menulis CSV bersih untuk LOAD DATA...", written_rows, total_data_rows, 0)
        write_with_polars(df, output_csv_path, delimiter)

        send_event(
            "done",
            {
                "csv_path": output_csv_path,
                "total_rows": int(total_data_rows),
                "written_rows": written_rows,
                "skipped_count": skipped_total,
                "skipped_rows": skipped_rows[:500],
                "rewritten": bool(rewrite_needed or structural_skipped > 0),
                "backend": "polars",
            },
        )
    finally:
        try:
            os.unlink(temp_sanitized_path)
        except Exception:
            pass


def main() -> int:
    parser = argparse.ArgumentParser(description="Daily Loan CSV stage processor")
    parser.add_argument("--config", required=True, help="Path to JSON config file")
    parser.add_argument("--mode", default="stage", choices=["stage"], help="Processing mode")
    args = parser.parse_args()

    try:
        config = load_config(args.config)
        stage_daily_loan(config)
        return 0
    except Exception as exc:
        send_error(str(exc))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
