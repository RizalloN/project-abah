#!/usr/bin/env python3
"""
SSA Pinjaman CSV processor
==========================

Flow:
  1. Parse CSV stage hasil konversi Excel dengan stdlib csv reader.
  2. Validasi struktur + kolom minimum yang wajib untuk SSA Pinjaman.
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
import tempfile
import time
import subprocess
import shutil
from datetime import datetime, timedelta
from pathlib import Path


REQUIRED_HEADERS = {
    "month_day_year_of_periode",
    "nama_cabang",
    "nama_uker",
    "produk",
    "baki_debet",
}

INDONESIAN_MONTHS = {
    "januari": "january",
    "februari": "february",
    "maret": "march",
    "april": "april",
    "mei": "may",
    "juni": "june",
    "juli": "july",
    "agustus": "august",
    "september": "september",
    "oktober": "october",
    "november": "november",
    "desember": "december",
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

    if text.strip() == r"\N":
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
        "MONTH_DAY_YEAR_OF_PERIODE": "month_day_year_of_periode",
        "NAMA_CABANG": "nama_cabang",
        "NAMA_UKER": "nama_uker",
        "PRODUK": "produk",
        "PRODUK_DASHBOARD": "produk_dashboard",
        "SEGMEN": "segmen",
        "SEGMEN_LAMA": "segmen_lama",
        "SEGMEN_2025": "segmen_2025",
        "SEGMEN_DASHBOARD": "segmen_dashboard",
        "KOLEKTABILITAS_ONE_OBLIGOR": "kolektabilitas_one_obligor",
        "FLAG_RESTRUK": "flag_restruk",
        "BAKI_DEBET": "baki_debet",
        "JUMLAH_DEBITUR_AKTIF": "jumlah_debitur_aktif",
        "JUMLAH_REKENING_AKTIF": "jumlah_rekening_aktif",
        "KETERANGAN_UKER": "keterangan_uker",
        "KUALITAS": "kualitas",
        "TGL": "tgl",
        "BULAN": "bulan",
        "TAHUN": "tahun",
        "BULAN_TAHUN": "bulan_tahun",
    }

    return aliases.get(normalized, normalized.lower())


def normalize_locale_date_text(value: str) -> str:
    normalized = value.strip()
    for source, target in INDONESIAN_MONTHS.items():
        normalized = re.sub(rf"\b{source}\b", target, normalized, flags=re.IGNORECASE)
    return normalized


def normalize_date_value(value: object) -> str | None:
    text = normalize_cell(value)
    if text == "":
        return None

    if re.fullmatch(r"\d+(?:\.\d+)?", text):
        try:
            serial = float(text)
        except Exception:
            serial = -1
        if 20000 <= serial <= 80000:
            try:
                return (datetime(1899, 12, 30) + timedelta(days=serial)).strftime("%Y-%m-%d")
            except Exception:
                return None

    normalized = normalize_locale_date_text(text).replace("/", "-")

    for date_format in ("%Y-%m-%d", "%d %B %Y", "%d %b %Y", "%d-%m-%Y", "%d-%m-%y"):
        try:
            return datetime.strptime(normalized, date_format).strftime("%Y-%m-%d")
        except Exception:
            continue

    try:
        from dateutil import parser as dateutil_parser

        return dateutil_parser.parse(normalized, dayfirst=True, yearfirst=False).strftime("%Y-%m-%d")
    except Exception:
        pass

    return None


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


def is_valid_ssa_pinjaman_row_values(values_by_header: dict[str, object]) -> bool:
    periode = normalize_cell(values_by_header.get("month_day_year_of_periode"))
    nama_cabang = normalize_cell(values_by_header.get("nama_cabang"))
    nama_uker = normalize_cell(values_by_header.get("nama_uker"))
    produk = normalize_cell(values_by_header.get("produk"))

    if periode == "" or nama_cabang == "" or nama_uker == "" or produk == "":
        return False

    return True


def read_normalized_headers(source_path: str, delimiter: str) -> list[str]:
    with open(source_path, "r", encoding="utf-8-sig", errors="replace", newline="") as raw_handle:
        reader = csv.reader(raw_handle, delimiter=delimiter, quotechar='"', escapechar="\\", strict=False)
        for row in reader:
            if not row or all(normalize_cell(cell) == "" for cell in row):
                continue
            return [normalize_header_name(cell) or f"col_{index}" for index, cell in enumerate(row)]

    raise RuntimeError("Header CSV SSA Pinjaman tidak ditemukan.")


def trusted_stage_is_eligible(source_path: str, headers: list[str], delimiter: str) -> bool:
    if not headers:
        return False

    if sorted(REQUIRED_HEADERS.difference(set(headers))):
        return False

    with open(source_path, "r", encoding="utf-8-sig", errors="replace", newline="") as raw_handle:
        reader = csv.reader(raw_handle, delimiter=delimiter, quotechar='"', escapechar="\\", strict=False)
        try:
            first_row = next(reader)
        except StopIteration:
            return False

    normalized_first_row = [normalize_header_name(cell) or f"col_{index}" for index, cell in enumerate(first_row)]
    return normalized_first_row == headers


def sanitize_source_optimized(source_path: str, delimiter: str, max_rows: int | None = None) -> tuple[str, list[str], int, int, int, bool, list[int], int]:
    """Optimized CSV sanitization using Polars lazy evaluation instead of row-by-row Python processing."""
    import polars as pl

    temp_dir = Path(tempfile.gettempdir())
    fd, temp_path = tempfile.mkstemp(prefix="ssa_pinjaman_sanitized_", suffix=".csv", dir=str(temp_dir))
    os.close(fd)

    start_time = time.perf_counter()

    try:
        send_progress(
            10,
            "Membaca CSV dengan Polars (lazy mode)...",
            0,
            0,
            0,
            "baris/detik",
            "polars",
        )

        schema_overrides = {header: pl.Utf8 for header in REQUIRED_HEADERS}

        df_lazy = pl.scan_csv(
            source_path,
            separator=delimiter,
            has_header=True,
            quote_char='"',
            escapechar="\\",
            infer_schema_length=0,
            encoding="utf8-lossy",
        )

        all_cols = df_lazy.select(pl.all()).collect_schema().names()
        total_records = len(all_cols)

        normalized_headers = [normalize_header_name(h) for h in all_cols]

        missing = sorted(REQUIRED_HEADERS.difference(set(normalized_headers)))
        if missing:
            raise RuntimeError("Kolom wajib SSA Pinjaman tidak lengkap: " + ", ".join(missing))

        send_progress(
            25,
            "Validasi header selesai. Filtering baris invalid...",
            0,
            0,
            0,
            "baris/detik",
            "polars",
        )

        required_mask = (
            pl.col("month_day_year_of_periode").is_not_null() & (pl.col("month_day_year_of_periode").str.strip_chars() != "")
            & pl.col("nama_cabang").is_not_null() & (pl.col("nama_cabang").str.strip_chars() != "")
            & pl.col("nama_uker").is_not_null() & (pl.col("nama_uker").str.strip_chars() != "")
            & pl.col("produk").is_not_null() & (pl.col("produk").str.strip_chars() != "")
        )

        filtered_lazy = df_lazy.filter(required_mask)

        if max_rows is not None:
            filtered_lazy = filtered_lazy.limit(max_rows)

        send_progress(
            40,
            "Normalisasi nilai dan whitespace...",
            0,
            0,
            0,
            "baris/detik",
            "polars",
        )

        transformations = []
        for col in normalized_headers:
            if col == "baki_debet":
                expr = pl.col(col).str.strip_chars().alias(col)
            elif col == "month_day_year_of_periode":
                expr = pl.col(col).map_elements(normalize_date_value, return_dtype=pl.Utf8).alias(col)
            elif col in {"tgl", "tahun", "jumlah_debitur_aktif", "jumlah_rekening_aktif"}:
                expr = pl.col(col).str.strip_chars().alias(col)
            else:
                expr = pl.col(col).str.strip_chars().alias(col)
            transformations.append(expr)

        df_normalized = filtered_lazy.select(transformations)

        df_collected = df_normalized.collect()

        send_progress(
            45,
            "Menulis file temp dengan Polars...",
            int(df_collected.height),
            0,
            0,
            "baris/detik",
            "polars",
        )

        df_collected.write_csv(
            temp_path,
            separator=delimiter,
            include_header=True,
            quote_style="necessary",
            line_terminator="\n",
        )

        elapsed = max(time.perf_counter() - start_time, 0.001)
        speed = int(df_collected.height / elapsed)

        structural_skipped = 0
        validation_skipped = 0
        valid_rows = int(df_collected.height)

        return temp_path, normalized_headers, total_records, structural_skipped, validation_skipped, True, [], valid_rows

    except Exception as e:
        try:
            os.unlink(temp_path)
        except:
            pass
        raise


def sanitize_source(source_path: str, delimiter: str, max_rows: int | None = None) -> tuple[str, list[str], int, int, int, bool, list[int], int]:
    """Fallback to optimized Polars-based sanitization."""
    try:
        return sanitize_source_optimized(source_path, delimiter, max_rows)
    except Exception:
        import polars as pl

        temp_dir = Path(tempfile.gettempdir())
        fd, temp_path = tempfile.mkstemp(prefix="ssa_pinjaman_sanitized_", suffix=".csv", dir=str(temp_dir))
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
                if row_number % 50000 == 0:
                    elapsed = max(time.perf_counter() - start_time, 0.001)
                    processed_rows = max(0, total_records - 1)
                    speed = int(processed_rows / elapsed)
                    send_progress(
                        min(50, 5 + int((row_number / 350000) * 45)),
                        "Menyiapkan sanitasi CSV SSA Pinjaman...",
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
                        raise RuntimeError("Kolom wajib SSA Pinjaman tidak lengkap: " + ", ".join(missing))
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

                    if header == "baki_debet":
                        normalized_value = normalize_decimal_value(raw_value)
                    elif header == "month_day_year_of_periode":
                        normalized_value = normalize_date_value(raw_value)
                    elif header in {"tgl", "tahun", "jumlah_debitur_aktif", "jumlah_rekening_aktif"}:
                        normalized_value = normalize_integer_value(raw_value)
                    else:
                        normalized_value = raw_value if raw_value != "" else None

                    values_by_header[header] = normalized_value
                    normalized_row.append("" if normalized_value is None else str(normalized_value))

                if not is_valid_ssa_pinjaman_row_values(values_by_header):
                    validation_skipped += 1
                    skipped_rows.append(row_number)
                    rewrite_needed = True
                    continue

                writer.writerow(normalized_row)
                valid_rows += 1

                if max_rows is not None and valid_rows >= max_rows:
                    break

        if not headers:
            raise RuntimeError("Header CSV SSA Pinjaman tidak ditemukan.")

        return temp_path, headers, total_records, structural_skipped, validation_skipped, rewrite_needed, skipped_rows, valid_rows


def stage_trusted_csv(config: dict, source_path: str, output_csv_path: str, delimiter: str, mode: str, preview_max_rows: int | None = None) -> bool:
    import polars as pl

    headers = read_normalized_headers(source_path, delimiter)
    if not trusted_stage_is_eligible(source_path, headers, delimiter):
        return False

    send_progress(12, "CSV staging internal terdeteksi. Membaca langsung dengan Polars...", 0, 0, 0, "", "polars")

    try:
        df = read_trusted_with_polars(source_path, headers, delimiter)
    except Exception:
        return False

    if df.height == 0:
        raise RuntimeError("Polars tidak menemukan baris data SSA Pinjaman yang valid.")

    string_columns = [column for column, dtype in df.schema.items() if dtype == pl.Utf8]
    if string_columns:
        df = df.with_columns([
            pl.col(column).str.strip_chars().alias(column)
            for column in string_columns
        ])

    if "month_day_year_of_periode" in df.columns:
        df = df.with_columns(
            pl.col("month_day_year_of_periode")
            .map_elements(normalize_date_value, return_dtype=pl.Utf8)
            .alias("month_day_year_of_periode")
        )

    required_mask = (
        pl.col("month_day_year_of_periode").is_not_null() & (pl.col("month_day_year_of_periode") != "")
        & pl.col("nama_cabang").is_not_null() & (pl.col("nama_cabang") != "")
        & pl.col("nama_uker").is_not_null() & (pl.col("nama_uker") != "")
        & pl.col("produk").is_not_null() & (pl.col("produk") != "")
    )

    total_data_rows = int(df.height)
    filtered_df = df.filter(required_mask)
    skipped_total = max(0, total_data_rows - int(filtered_df.height))

    if filtered_df.height == 0:
        raise RuntimeError("Semua baris SSA Pinjaman terfilter sebagai tidak valid.")

    if preview_max_rows is not None:
        filtered_df = filtered_df.head(preview_max_rows)

    send_progress(72, "Fast-path staging aktif. Menulis CSV hasil Polars...", int(filtered_df.height), total_data_rows, 0, "", "polars")

    if mode == "preview":
        write_with_polars(filtered_df, output_csv_path, delimiter)
    elif mode in ("bulk_load", "import"):
        load_cols = [c for c in (config.get("load_columns") or list(filtered_df.columns))]
        write_with_polars_bulk(filtered_df, output_csv_path, delimiter, load_columns=load_cols)

        if mode == "import":
            db_cfg = config.get("db") or {}
            table = config.get("table") or config.get("target_table") or ""
            if not db_cfg or not table:
                send_event("error", {"message": "DB config atau nama tabel tidak disediakan untuk mode import"})
            else:
                execute_mysql_load(output_csv_path, db_cfg, table, load_cols, delimiter)
    else:
        write_with_polars(filtered_df, output_csv_path, delimiter)

    periods: list[str] = []
    if "month_day_year_of_periode" in filtered_df.columns:
        try:
            periods = filtered_df["month_day_year_of_periode"].unique().to_list()
            periods = [str(p) for p in periods if p is not None and str(p).strip() != ""]
        except Exception:
            periods = []

    send_event(
        "done",
        {
            "csv_path": output_csv_path,
            "total_rows": total_data_rows,
            "written_rows": int(filtered_df.height),
            "skipped_count": skipped_total,
            "duplicate_count": 0,
            "skipped_rows": [],
            "rewritten": False,
            "backend": "polars_fast_path",
            "periods": periods,
            "headers": list(filtered_df.columns),
        },
    )

    return True


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

    raise RuntimeError(f"Gagal membaca CSV SSA Pinjaman dengan Polars: {last_error}")


def read_trusted_with_polars(path: str, headers: list[str], delimiter: str):
    import polars as pl

    schema_overrides = {header: pl.Utf8 for header in headers}

    read_attempts = [
        lambda: pl.read_csv(
            path,
            separator=delimiter,
            has_header=True,
            new_columns=headers,
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
            new_columns=headers,
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
            new_columns=headers,
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

    raise RuntimeError(f"Gagal membaca trusted CSV SSA Pinjaman dengan Polars: {last_error}")


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

    raise RuntimeError(f"Gagal menulis CSV hasil Polars SSA Pinjaman: {last_error}")


def write_with_polars_bulk(df, path: str, delimiter: str, load_columns: list[str] | None = None) -> None:
    import polars as pl

    cols = list(load_columns) if load_columns else list(df.columns)
    for c in cols:
        if c not in df.columns:
            df = df.with_columns(pl.lit(None).cast(pl.Utf8).alias(c))

    final_select = []
    for c in cols:
        if df.schema.get(c) == pl.Utf8:
            expr = (
                pl.when(pl.col(c).is_null() | (pl.col(c).cast(pl.Utf8).str.strip_chars() == ""))
                .then(pl.lit(None))
                .otherwise(pl.col(c).cast(pl.Utf8).str.replace_all(r"\\", r"\\\\"))
                .alias(c)
            )
        else:
            expr = pl.col(c)
        final_select.append(expr)

    final_df = df.select(final_select)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    try:
        final_df.write_csv(
            path,
            separator=delimiter,
            include_header=False,
            null_value=r"\N",
            quote_char='"',
            line_terminator="\n",
        )
    except Exception as exc:
        raise RuntimeError(f"Gagal menulis CSV bulk SSA Pinjaman: {exc}")


def execute_mysql_load(file_path: str, db_config: dict, table: str, columns: list[str], delimiter: str = ',') -> bool:
    send_progress(92, "Memulai LOAD DATA ke database...", 0, 0, 0, "", "db")

    cols_sql = ", ".join([f"`{c}`" for c in columns])
    safe_path = file_path.replace("'", "\\'")
    sql = (
        "SET SESSION local_infile=1; "
        f"LOAD DATA LOCAL INFILE '{safe_path}' INTO TABLE `{table}` "
        f"FIELDS TERMINATED BY '{delimiter}' OPTIONALLY ENCLOSED BY '\"' ESCAPED BY '\\\\' "
        "LINES TERMINATED BY '\n' "
        f"({cols_sql});"
    )

    try:
        import pymysql

        conn = pymysql.connect(
            host=db_config.get("host", "127.0.0.1"),
            port=int(db_config.get("port", 3306)),
            user=db_config.get("user"),
            password=db_config.get("password", ""),
            database=db_config.get("database"),
            local_infile=1,
            charset="utf8mb4",
        )
        with conn.cursor() as cur:
            cur.execute("SET SESSION local_infile=1;")
            cur.execute(sql)
        conn.commit()
        conn.close()
        return True
    except Exception as exc_pymysql:
        mysql_cmd = shutil.which("mysql")
        if not mysql_cmd:
            send_event("error", {"message": "pymysql import failed and mysql client not found: " + str(exc_pymysql)})
            return False

        env = os.environ.copy()
        if "password" in db_config:
            env["MYSQL_PWD"] = str(db_config.get("password", ""))

        cmd = [
            mysql_cmd,
            "-h",
            db_config.get("host", "127.0.0.1"),
            "-P",
            str(db_config.get("port", 3306)),
            "-u",
            db_config.get("user"),
            db_config.get("database"),
            "-e",
            sql,
        ]

        try:
            subprocess.run(cmd, check=True, env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            return True
        except subprocess.CalledProcessError as cpe:
            stderr = cpe.stderr.decode(errors="ignore") if cpe.stderr else str(cpe)
            send_event("error", {"message": f"mysql client import failed: {stderr}"})
            return False


def stage_ssa_pinjaman(config: dict) -> None:
    source_path = config["file_path"]
    output_csv_path = config["output_csv_path"]
    delimiter = config.get("delimiter") or detect_delimiter(source_path, ",")
    mode = config.get("mode") or config.get("_cli_mode") or "stage"
    preview_max_rows = int(config.get("preview_max_rows", 1000)) if mode == "preview" else None

    send_progress(5, "Membaca dan menyiapkan CSV SSA Pinjaman dengan Polars...", 0, 0, 0, "", "polars")

    if bool(config.get("trusted_staged_csv")):
        if stage_trusted_csv(config, source_path, output_csv_path, delimiter, mode, preview_max_rows):
            return

    import polars as pl
    temp_sanitized_path, headers, total_records, structural_skipped, validation_skipped, rewrite_needed, skipped_rows, valid_rows = sanitize_source(source_path, delimiter, max_rows=preview_max_rows)
    total_data_rows = max(0, total_records - 1)

    try:
        send_progress(60, "Normalisasi dan filtering dengan Polars...", total_data_rows, total_data_rows, 0, "", "polars")
        df = read_with_polars(temp_sanitized_path, headers, delimiter)

        if df.height == 0:
            raise RuntimeError("Polars tidak menemukan baris data SSA Pinjaman yang valid.")

        written_rows = int(df.height)
        skipped_total = int(structural_skipped + validation_skipped)

        if mode == "preview":
            send_progress(80, "Menulis preview CSV SSA Pinjaman...", written_rows, total_data_rows, 0, "", "polars")
            write_with_polars(df, output_csv_path, delimiter)
        elif mode in ("bulk_load", "import"):
            load_cols = [c for c in (config.get("load_columns") or list(df.columns))]
            send_progress(80, "Menulis CSV bulk load SSA Pinjaman...", written_rows, total_data_rows, 0, "", "polars")
            write_with_polars_bulk(df, output_csv_path, delimiter, load_columns=load_cols)

            if mode == "import":
                db_cfg = config.get("db") or {}
                table = config.get("table") or config.get("target_table") or ""
                if not db_cfg or not table:
                    send_event("error", {"message": "DB config atau nama tabel tidak disediakan untuk mode import"})
                else:
                    execute_mysql_load(output_csv_path, db_cfg, table, load_cols, delimiter)
        else:
            send_progress(80, "Menulis CSV bersih SSA Pinjaman untuk LOAD DATA...", written_rows, total_data_rows, 0, "", "polars")
            write_with_polars(df, output_csv_path, delimiter)

        periods = []
        if "month_day_year_of_periode" in df.columns:
            try:
                periods = df["month_day_year_of_periode"].unique().to_list()
                periods = [str(p) for p in periods if p is not None and str(p).strip() != ""]
            except Exception:
                pass

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
                "periods": periods,
                "headers": list(df.columns),
            },
        )
    finally:
        try:
            os.unlink(temp_sanitized_path)
        except Exception:
            pass


def main() -> int:
    parser = argparse.ArgumentParser(description="SSA Pinjaman CSV stage processor")
    parser.add_argument("--config", required=True, help="Path to JSON config file")
    parser.add_argument("--mode", default="stage", choices=["stage", "preview", "bulk_load", "import"], help="Processing mode")
    args = parser.parse_args()

    try:
        config = load_config(args.config)
        if "mode" not in config:
            config["_cli_mode"] = args.mode
        stage_ssa_pinjaman(config)
        return 0
    except Exception as exc:
        send_error(str(exc))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
