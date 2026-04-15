#!/usr/bin/env python3
"""
SSA Simpanan CSV processor
==========================

Flow:
  1. Parse CSV stage hasil konversi Excel dengan stdlib csv reader.
  2. Validasi struktur + kolom minimum yang wajib untuk SSA Simpanan.
  3. Baca ulang file bersih dengan Polars.
  4. Rapikan nilai string dan tulis CSV final untuk LOAD DATA LOCAL INFILE.
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
import time
from pathlib import Path


REQUIRED_HEADERS = {
    "posisi",
    "nama_cabang",
    "nama_uker",
    "produk",
    "saldo",
}


def send_event(event_type: str, data: dict) -> None:
    payload = dict(data)
    payload["type"] = event_type
    print(json.dumps(payload, ensure_ascii=False, default=str), flush=True)


def send_progress(
    percent: int,
    message: str,
    rows_done: int = 0,
    total: int = 0,
    speed: int = 0,
    speed_label: str = "",
    mode: str = "",
) -> None:
    send_event(
        "progress",
        {
            "percent": percent,
            "message": message,
            "rows_done": rows_done,
            "total": total,
            "speed": speed,
            "speed_label": speed_label,
            "mode": mode,
        },
    )


def send_error(message: str) -> None:
    send_event("error", {"message": message})


def load_config(config_path: str) -> dict:
    with open(config_path, "r", encoding="utf-8-sig") as handle:
        return json.load(handle)


def parse_csv_text(text: str, delimiter: str) -> list[str]:
    buffer = io.StringIO(text)
    reader = csv.reader(buffer, delimiter=delimiter, quotechar='"', escapechar="\\", strict=False)
    try:
        row = next(reader)
    except StopIteration:
        return []
    return [normalize_cell(cell) for cell in row]


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


def normalize_header_name(header_name: str) -> str:
    normalized = re.sub(r"[^A-Z0-9]+", "_", normalize_cell(header_name).upper()).strip("_")

    aliases = {
        "MONTH_DAY_YEAR_OF_POSISI": "posisi",
        "NAMA_CABANG": "nama_cabang",
        "NAMA_UKER": "nama_uker",
        "PRODUK": "produk",
        "SEGMENTASI": "segmentasi",
        "SEGMEN_KATEGORISASI_BISNIS": "segmen_kategorisasi_bisnis",
        "SALDO": "saldo",
        "TGL": "tgl",
        "BULAN": "bulan",
        "TAHUN": "tahun",
        "BULAN_TAHUN": "bulan_tahun",
    }

    return aliases.get(normalized, normalized.lower())


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


def normalize_integer_value(value: object) -> str | None:
    text = normalize_cell(value)
    if text == "":
        return None

    if re.fullmatch(r"-?\d+", text):
        return text

    normalized_decimal = normalize_decimal_value(text)
    if normalized_decimal is None:
        return None

    try:
        return str(int(round(float(normalized_decimal))))
    except Exception:
        return None


def is_valid_ssa_row_values(values_by_header: dict[str, object]) -> bool:
    posisi = normalize_cell(values_by_header.get("posisi"))
    nama_cabang = normalize_cell(values_by_header.get("nama_cabang"))
    nama_uker = normalize_cell(values_by_header.get("nama_uker"))
    produk = normalize_cell(values_by_header.get("produk"))
    saldo = normalize_decimal_value(values_by_header.get("saldo"))

    if posisi == "" or nama_cabang == "" or nama_uker == "" or produk == "" or saldo is None:
        return False

    return True


def sanitize_source(
    source_path: str,
    delimiter: str,
) -> tuple[str, list[str], int, int, int, bool, list[int], int]:
    temp_dir = Path(tempfile.gettempdir())
    fd, temp_path = tempfile.mkstemp(prefix="ssa_simpanan_sanitized_", suffix=".csv", dir=str(temp_dir))
    os.close(fd)

    total_records = 0
    structural_skipped = 0
    validation_skipped = 0
    rewrite_needed = False
    skipped_rows: list[int] = []
    headers: list[str] = []
    valid_rows = 0
    start_time = time.perf_counter()

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

            total_records += 1
            if row_number % 25000 == 0:
                elapsed = max(time.perf_counter() - start_time, 0.001)
                processed_rows = max(0, total_records - 1)
                speed = int(processed_rows / elapsed)
                send_progress(
                    min(50, 5 + int((row_number / 350000) * 45)),
                    "Menyiapkan sanitasi CSV SSA Simpanan...",
                    processed_rows,
                    0,
                    speed,
                    "baris/detik",
                    "polars",
                )

            if not headers:
                raw_headers = [normalize_cell(cell) for cell in row]
                headers = [normalize_header_name(header) or f"col_{index}" for index, header in enumerate(raw_headers)]
                missing = sorted(REQUIRED_HEADERS.difference(set(headers)))
                if missing:
                    raise RuntimeError("Kolom wajib SSA Simpanan tidak lengkap: " + ", ".join(missing))
                rewrite_needed = True
                writer.writerow(headers)
                continue

            if len(row) != len(headers):
                structural_skipped += 1
                skipped_rows.append(row_number)
                rewrite_needed = True
                continue

            values = [normalize_cell(cell) for cell in row]
            values_by_header: dict[str, object] = {}
            normalized_row: list[str] = []

            for index, header in enumerate(headers):
                raw_value = values[index]

                if header == "saldo":
                    normalized_value = normalize_decimal_value(raw_value)
                elif header in {"tgl", "tahun"}:
                    normalized_value = normalize_integer_value(raw_value)
                else:
                    normalized_value = raw_value if raw_value != "" else None

                values_by_header[header] = normalized_value
                normalized_row.append("" if normalized_value is None else str(normalized_value))

            if not is_valid_ssa_row_values(values_by_header):
                validation_skipped += 1
                skipped_rows.append(row_number)
                rewrite_needed = True
                continue

            writer.writerow(normalized_row)
            valid_rows += 1

    if not headers:
        raise RuntimeError("Header CSV SSA Simpanan tidak ditemukan.")

    return temp_path, headers, total_records, structural_skipped, validation_skipped, rewrite_needed, skipped_rows, valid_rows


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

    raise RuntimeError(f"Gagal membaca CSV SSA Simpanan dengan Polars: {last_error}")


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

    raise RuntimeError(f"Gagal menulis CSV hasil Polars SSA Simpanan: {last_error}")


def stage_ssa_simpanan(config: dict) -> None:
    import polars as pl

    source_path = config["file_path"]
    output_csv_path = config["output_csv_path"]
    delimiter = config.get("delimiter") or detect_delimiter(source_path, ",")

    send_progress(5, "Membaca dan menyiapkan CSV SSA Simpanan dengan Polars...", 0, 0, 0, "", "polars")
    temp_sanitized_path, headers, total_records, structural_skipped, validation_skipped, rewrite_needed, skipped_rows, valid_rows = sanitize_source(source_path, delimiter)
    total_data_rows = max(0, total_records - 1)

    try:
        send_progress(56, "Sanitasi selesai. Membaca file bersih SSA Simpanan dengan Polars...", total_data_rows, total_data_rows, 0, "", "polars")
        df = read_with_polars(temp_sanitized_path, headers, delimiter)

        if df.height == 0:
            raise RuntimeError("Polars tidak menemukan baris data SSA Simpanan yang valid.")

        df = df.with_columns([
            pl.col(column).cast(pl.Utf8).str.strip_chars().alias(column)
            for column in df.columns
        ])

        written_rows = int(df.height)
        skipped_total = int(structural_skipped + validation_skipped)

        send_progress(86, "Menulis CSV bersih SSA Simpanan untuk LOAD DATA...", written_rows, total_data_rows, 0, "", "polars")
        write_with_polars(df, output_csv_path, delimiter)

        send_event(
            "done",
            {
                "csv_path": output_csv_path,
                "total_rows": int(total_data_rows),
                "written_rows": written_rows,
                "skipped_count": skipped_total,
                "duplicate_count": 0,
                "skipped_rows": skipped_rows[:500],
                "rewritten": bool(rewrite_needed or structural_skipped > 0 or validation_skipped > 0),
                "backend": "polars",
            },
        )
    finally:
        try:
            os.unlink(temp_sanitized_path)
        except Exception:
            pass


def main() -> int:
    parser = argparse.ArgumentParser(description="SSA Simpanan CSV stage processor")
    parser.add_argument("--config", required=True, help="Path to JSON config file")
    parser.add_argument("--mode", default="stage", choices=["stage"], help="Processing mode")
    args = parser.parse_args()

    try:
        config = load_config(args.config)
        stage_ssa_simpanan(config)
        return 0
    except Exception as exc:
        send_error(str(exc))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
