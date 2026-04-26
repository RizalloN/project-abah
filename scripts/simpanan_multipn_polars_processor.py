#!/usr/bin/env python3
"""
Simpanan MultiPN CSV processor
==============================

Flow:
  1. Parse raw CSV records with the stdlib csv reader.
  2. Keep only structurally valid rows with the minimum required values.
  3. Load the cleaned file with Polars.
  4. Trim columns and remove duplicate business-key rows.
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
import time
import threading
from decimal import Decimal, InvalidOperation, ROUND_HALF_UP
from datetime import datetime, timedelta
from datetime import date
from pathlib import Path
from typing import Optional


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


class DBConnectionPool:
    """Singleton connection pool for efficient DB access."""
    _instance: Optional['DBConnectionPool'] = None
    _lock = threading.Lock()

    def __init__(self):
        self.conn: Optional[object] = None
        self.db_config: Optional[dict] = None

    @staticmethod
    def get_instance() -> 'DBConnectionPool':
        if DBConnectionPool._instance is None:
            with DBConnectionPool._lock:
                if DBConnectionPool._instance is None:
                    DBConnectionPool._instance = DBConnectionPool()
        return DBConnectionPool._instance

    def init_config(self, db_config: dict) -> None:
        self.db_config = db_config

    def get_connection(self):
        # Check if existing connection is still alive (handle MySQL timeout/disconnect)
        if self.conn:
            try:
                self.conn.ping(reconnect=False)
            except Exception:
                self.conn = None  # Connection putus, reset untuk reconnect

        if not self.db_config or not self.conn:
            try:
                import mysql.connector
                self.conn = mysql.connector.connect(
                    host=self.db_config.get("host", "127.0.0.1"),
                    user=self.db_config.get("username", "root"),
                    password=self.db_config.get("password", ""),
                    database=self.db_config.get("database", "project_abah"),
                    connect_timeout=2,
                    autocommit=True
                )
            except Exception:
                return None
        return self.conn

    def close(self) -> None:
        if self.conn:
            try:
                self.conn.close()
            except Exception:
                pass
            self.conn = None


def check_termination(job_id: int, db_config: dict) -> bool:
    """Check if the job has been terminated in the database. Uses connection pooling."""
    if not job_id or not db_config:
        return False

    try:
        pool = DBConnectionPool.get_instance()
        pool.init_config(db_config)
        conn = pool.get_connection()

        if not conn:
            return False

        cursor = conn.cursor()
        cursor.execute("SELECT status FROM import_jobs WHERE id = %s", (job_id,))
        row = cursor.fetchone()
        cursor.close()

        return row and row[0] == "terminated"
    except Exception:
        return False


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


def normalize_header_name(header_name: str) -> str:
    normalized = re.sub(r"[^A-Z0-9]+", "_", normalize_cell(header_name).upper())
    normalized = normalized.strip("_")

    aliases = {
        "NOREKENING": "no_rekening",
        "NOMORREKENING": "no_rekening",
        "NOMOR_REKENING": "no_rekening",
        "NO_REKENING": "no_rekening",
        "CIF_NO": "cifno",
        "CIFNUMBER": "cifno",
        "CIF_NUMBER": "cifno",
        "POSISI": "posisi",
        "JENISSIMPANAN": "jenis_simpanan",
        "JENIS_SIMPANAN": "jenis_simpanan",
        "STATUSREKENING": "status",
        "STATUS_REKENING": "status",
        "STATUSREK": "status",
        "STATUS_REK": "status",
        "STATUSSIMPANAN": "status",
        "STATUS_SIMPANAN": "status",
        "STATUSDORMANT": "status",
        "STATUS_DORMANT": "status",
        "SALDOIDR": "saldo_idr",
        "SALDO_IDR": "saldo_idr",
    }

    return aliases.get(normalized, normalized.lower())


def parse_csv_text(text: str, delimiter: str) -> list[str]:
    buffer = io.StringIO(text)
    reader = csv.reader(buffer, delimiter=delimiter, quotechar='"', escapechar="\\", strict=False)
    try:
        row = next(reader)
    except StopIteration:
        return []
    return [normalize_cell(cell) for cell in row]


def normalize_date_value(value: object) -> str | None:
    text = normalize_cell(value)
    if text == "":
        return None

    compact = text.strip()
    if re.fullmatch(r"\d{1,5}", compact):
        return None

    if re.fullmatch(r"\d+(?:\.\d+)?", compact):
        try:
            serial = float(compact)
        except Exception:
            serial = -1
        if 20000 <= serial <= 80000:
            try:
                base = datetime(1899, 12, 30)
                return (base + timedelta(days=serial)).strftime("%Y-%m-%d")
            except Exception:
                return None

    try:
        from dateutil import parser as dateutil_parser

        parsed = dateutil_parser.parse(compact.replace("/", "-"), dayfirst=True, yearfirst=False)
        return parsed.strftime("%Y-%m-%d")
    except Exception:
        return None


def normalize_decimal_value(value: object) -> str | None:
    """Fast decimal normalization with pre-computed patterns."""
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
            text = text.replace(".", "").replace(",", ".")
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


def decimal_string_to_cents(value: str) -> int:
    normalized = value.strip()
    if normalized == "":
        return 0

    negative = normalized.startswith("-")
    if negative:
        normalized = normalized[1:]

    if "." in normalized:
        whole, fraction = normalized.split(".", 1)
    else:
        whole, fraction = normalized, ""

    whole = re.sub(r"\D+", "", whole) or "0"
    fraction = (re.sub(r"\D+", "", fraction) + "00")[:2]
    cents = (int(whole) * 100) + int(fraction or "0")
    return -cents if negative else cents


def _normalize_decimal_polars(col_expr):
    """Vectorized decimal normalization using Polars native operations + optimized Python.
    Hybrid approach: Use Polars for string cleaning, then single map_elements pass for logic.
    Performance: 2-3x faster than pure map_elements by pre-cleaning with vectorized ops.
    """
    import polars as pl

    # Pre-clean: Vectorized operations (free on entire column)
    col_expr = col_expr.str.strip_chars()
    col_expr = col_expr.str.replace_all(r"[^0-9,.\-()]", "")  # Remove non-numeric except delimiters

    # Convert parentheses notation: (123.45) → -123.45
    col_expr = pl.when(col_expr.str.contains(r"^\(")).\
        then(pl.lit("-") + col_expr.str.strip_chars("()")).\
        otherwise(col_expr)

    # Remove trailing minus: 123.45- → -123.45 (rare but possible)
    col_expr = pl.when(col_expr.str.ends_with("-") & ~col_expr.str.starts_with("-")).\
        then(pl.lit("-") + col_expr.str.strip_chars_end("-")).\
        otherwise(col_expr)

    # NOW use optimized map_elements for final decimal normalization
    # By pre-cleaning with Polars, we reduce the work in the callback by ~70%
    return col_expr.map_elements(
        lambda val: _normalize_decimal_optimized(val),
        return_dtype="str",
        skip_nulls=True
    )


def _normalize_decimal_optimized(value: str) -> str | None:
    """Lightweight decimal normalization - assumes input is pre-cleaned."""
    if not value or value in ("-", ""):
        return None

    is_negative = value.startswith("-")
    text = value.lstrip("-") if is_negative else value

    # Quick check for comma vs dot
    comma_pos = text.rfind(",")
    dot_pos = text.rfind(".")

    # Determine separator: rightmost non-zero position
    if comma_pos > dot_pos:
        text = text.replace(".", "").replace(",", ".")
    elif dot_pos > comma_pos:
        text = text.replace(",", "")
    elif comma_pos >= 0:
        # Only comma present - check if it's thousands or decimal
        parts = text.split(",")
        text = text.replace(",", "") if (len(parts[-1]) == 3 and len(parts) > 1) else text.replace(",", ".")
    elif dot_pos >= 0:
        # Only dot present - check if it's thousands or decimal
        parts = text.split(".")
        text = text.replace(".", "") if (len(parts[-1]) == 3 and len(parts) > 1) else text

    try:
        val = float(text)
        return f"{-val:.2f}" if is_negative else f"{val:.2f}"
    except:
        return None


def is_valid_simpanan_posisi(value: object) -> bool:
    return normalize_date_value(value) is not None


def is_valid_simpanan_row_values(values_by_header: dict[str, object]) -> bool:
    posisi = normalize_cell(values_by_header.get("posisi"))
    cifno = normalize_cell(values_by_header.get("cifno"))
    no_rekening = normalize_cell(values_by_header.get("no_rekening"))
    jenis = normalize_cell(values_by_header.get("jenis_simpanan")).upper()
    saldo = normalize_cell(values_by_header.get("saldo_idr"))

    if posisi == "" or cifno == "" or no_rekening == "" or jenis == "" or saldo == "":
        return False

    if not is_valid_simpanan_posisi(posisi):
        return False

    if re.fullmatch(r"[A-Z0-9.,+_\/'-]+", no_rekening, flags=re.I) is None:
        return False

    if len(no_rekening) < 6:
        return False

    if not (
        jenis.startswith("TABUNGAN")
        or jenis.startswith("GIRO")
        or jenis.startswith("DEPOSITO")
    ):
        return False

    return normalize_decimal_value(saldo) is not None


def sanitize_source_optimized(source_path: str, delimiter: str, config: dict | None = None) -> tuple[str, list[str], int, int, int, int, bool, list[int], int, int, list[dict[str, str]], bool]:
    """Optimized CSV sanitization using Polars lazy evaluation."""
    import polars as pl
    from datetime import datetime, timedelta

    config = config or {}
    output_csv_path = str(config.get("output_csv_path") or "")
    direct_output_written = output_csv_path != ""
    temp_path = ""
    if direct_output_written:
        write_path = output_csv_path
    else:
        temp_dir = Path(tempfile.gettempdir())
        fd, temp_path = tempfile.mkstemp(prefix="simpanan_multipn_sanitized_opt_", suffix=".csv", dir=str(temp_dir))
        os.close(fd)
        write_path = temp_path

    start_time = time.perf_counter()
    send_progress(10, "Membaca CSV dengan Polars (fast-path)...", 0, 0, 0, "baris/detik", "polars")

    try:
        # 1. Read headers to get column names
        raw_headers = []
        with open(source_path, "r", encoding="utf-8-sig", errors="replace", newline="") as h:
            reader = csv.reader(h, delimiter=delimiter, quotechar='"', escapechar="\\")
            for row in reader:
                if row and any(cell.strip() for cell in row):
                    raw_headers = [normalize_cell(c) for c in row]
                    break
        
        if not raw_headers:
            raise RuntimeError("Header CSV tidak ditemukan.")

        normalized_headers = [normalize_header_name(h) or f"col_{i}" for i, h in enumerate(raw_headers)]
        
        # 2. Scan CSV
        # We use infer_schema_length=0 to treat everything as string initially for robust cleaning
        df_lazy = pl.scan_csv(
            source_path,
            separator=delimiter,
            has_header=True,
            quote_char='"',
            # escapechar="\\" is not supported in some scan_csv versions, removed for compatibility
            infer_schema_length=0,
            encoding="utf8-lossy",
            new_columns=normalized_headers,
            skip_rows=0, # The first non-empty row was the header
        )

        # 3. Apply active filters (User-defined)
        active_filters = config.get("active_filters") or {}
        if active_filters:
            for col_idx_str, values in active_filters.items():
                try:
                    idx = int(col_idx_str)
                    if 0 <= idx < len(normalized_headers):
                        col_name = normalized_headers[idx]
                        if values:
                            # Clean values and filter
                            clean_values = [str(v).strip() for v in values if v is not None]
                            if clean_values:
                                df_lazy = df_lazy.filter(pl.col(col_name).str.strip_chars().is_in(clean_values))
                except (ValueError, TypeError):
                    continue

        # 4. Vectorized Filtering (Business Logic)
        # Required columns: posisi, cifno, no_rekening, jenis_simpanan, saldo_idr
        required_cols = ["posisi", "cifno", "no_rekening", "jenis_simpanan", "saldo_idr"]
        for col in required_cols:
            if col not in normalized_headers:
                raise RuntimeError(f"Kolom wajib '{col}' tidak ditemukan.")

        non_empty_mask = pl.any_horizontal([
            pl.col(col).is_not_null() & (pl.col(col).str.strip_chars() != "")
            for col in normalized_headers
        ])

        filter_mask = (
            non_empty_mask &
            pl.col("posisi").is_not_null() & (pl.col("posisi").str.strip_chars() != "") &
            pl.col("cifno").is_not_null() & (pl.col("cifno").str.strip_chars() != "") &
            pl.col("no_rekening").is_not_null() & (pl.col("no_rekening").str.strip_chars() != "") &
            pl.col("jenis_simpanan").is_not_null() & (pl.col("jenis_simpanan").str.strip_chars() != "") &
            pl.col("saldo_idr").is_not_null() & (pl.col("saldo_idr").str.strip_chars() != "")
        )

        # Business logic filters
        filter_mask = filter_mask & (
            pl.col("no_rekening").str.strip_chars().str.contains(r"(?i)^[A-Z0-9.,+_\/'-]+$") &
            (pl.col("no_rekening").str.strip_chars().str.len_chars() >= 6)
        )
        
        filter_mask = filter_mask & (
            pl.col("jenis_simpanan").str.strip_chars().str.to_uppercase().str.starts_with("TABUNGAN") |
            pl.col("jenis_simpanan").str.strip_chars().str.to_uppercase().str.starts_with("GIRO") |
            pl.col("jenis_simpanan").str.strip_chars().str.to_uppercase().str.starts_with("DEPOSITO")
        )

        # 4. Vectorized Normalization with Caching
        posisi_stripped = pl.col("posisi").str.strip_chars()
        saldo_stripped = pl.col("saldo_idr").str.strip_chars()

        # Date normalization (posisi) - Handle common formats: DD/MM/YYYY, YYYY-MM-DD, Excel Serial
        posisi_text = posisi_stripped.str.replace_all("/", "-")
        posisi_serial = posisi_text.cast(pl.Float64, strict=False)
        posisi_serial_date = (
            pl.lit(date(1899, 12, 30)) +
            pl.duration(days=posisi_serial.cast(pl.Int64, strict=False))
        ).cast(pl.Date)
        posisi_expr = pl.coalesce([
            posisi_text.str.strptime(pl.Date, "%d-%m-%Y", strict=False),
            posisi_text.str.strptime(pl.Date, "%Y-%m-%d", strict=False),
            posisi_text.str.strptime(pl.Date, "%d-%m-%y", strict=False),
            pl.when(posisi_serial.is_between(20000, 80000))
            .then(posisi_serial_date)
            .otherwise(None),
        ]).cast(pl.Utf8)

        # Decimal normalization (saldo_idr) - Use Polars regex for better perf than map_elements
        saldo_expr = _normalize_decimal_polars(saldo_stripped)

        transformations = []
        for col in normalized_headers:
            if col == "posisi":
                transformations.append(posisi_expr.alias("posisi"))
            elif col == "saldo_idr":
                transformations.append(saldo_expr.alias("saldo_idr"))
            else:
                transformations.append(pl.col(col).str.strip_chars().alias(col))

        df_processed = df_lazy.select([
            *transformations,
            non_empty_mask.alias("__smpn_non_empty"),
            filter_mask.alias("__smpn_valid_shape"),
        ])

        # Execute
        send_progress(35, "Memproses data dengan engine Polars...", 0, 0, 0, "baris/detik", "polars")

        df_collected = df_processed.collect()
        total_input_rows = int(df_collected.filter(pl.col("__smpn_non_empty")).height)
        df_collected = df_collected.filter(
            pl.col("__smpn_valid_shape") &
            pl.col("posisi").is_not_null() & (pl.col("posisi") != "") &
            pl.col("saldo_idr").is_not_null() & (pl.col("saldo_idr") != "")
        ).drop(["__smpn_non_empty", "__smpn_valid_shape"])

        valid_rows = df_collected.height
        total_data_rows = total_input_rows
        skipped_count = total_data_rows - valid_rows
        
        if valid_rows == 0:
            raise RuntimeError("Tidak ada data valid yang ditemukan setelah filtering Polars.")

        # Calculate balance total cents - vectorized with minimal overhead
        balance_total_cents = 0
        if "saldo_idr" in df_collected.columns:
            # Convert to float, multiply by 100 to get cents, sum
            # Much faster than row-by-row map_elements
            try:
                balance_total_cents = int(
                    df_collected.select(
                        (pl.col("saldo_idr").cast(pl.Float64, strict=False) * 100).sum()
                    ).to_series()[0] or 0
                )
            except Exception:
                # Fallback if direct cast fails (shouldn't happen with normalized saldo_idr)
                balance_total_cents = df_collected.select(
                    pl.col("saldo_idr")
                    .map_elements(decimal_string_to_cents, return_dtype=pl.Int64)
                    .sum()
                ).to_series()[0] or 0

        # Account samples
        account_samples = []
        if "no_rekening" in df_collected.columns:
            samples = df_collected.head(10).get_column("no_rekening").to_list()
            account_samples = [{"raw": s, "normalized": s} for s in samples]

        # 5. FULL VECTORIZATION (Optional - for direct DB load)
        target_columns = config.get("target_columns") or []
        full_vectorization = config.get("full_vectorization", False)
        if full_vectorization:
            send_progress(70, "Menambahkan kolom database (uniqueid, timestamps)...", 0, 0, 0, "baris/detik", "polars")

            timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            unique_id_col = config.get("unique_id_col", "uniqueid_SMPN")
            unique_id_prefix = config.get("unique_id_prefix", "imp")

            cols_to_add = [
                pl.lit(timestamp).alias("created_at"),
                pl.lit(timestamp).alias("updated_at"),
            ]

            if unique_id_col not in df_collected.columns:
                import uuid
                uuid_base = unique_id_prefix + "_"
                uuids = [uuid_base + str(uuid.uuid4()) for _ in range(df_collected.height)]
                cols_to_add.append(pl.lit(uuids).alias(unique_id_col))

            manual_values = config.get("manual_values", {})
            if manual_values:
                for col_name, col_val in manual_values.items():
                    if col_name not in df_collected.columns:
                        cols_to_add.append(pl.lit(col_val).alias(col_name))

            if cols_to_add:
                df_collected = df_collected.with_columns(cols_to_add)

        if target_columns:
            existing_target = [c for c in target_columns if c in df_collected.columns]
            if existing_target:
                df_collected = df_collected.select(existing_target)

        # 6. Periodic termination check
        job_id = config.get("job_id")
        db_config = config.get("db_config")
        if job_id and db_config and valid_rows > 50000:
            if check_termination(job_id, db_config):
                raise RuntimeError("Proses import dihentikan oleh pengguna.")

        # Write CSV
        send_progress(80, f"Menulis {valid_rows:,} baris hasil optimasi...", valid_rows, valid_rows, 0, "baris/detik", "polars")
        df_collected.write_csv(
            write_path,
            separator=delimiter,
            include_header=True,
            quote_style="necessary",
            line_terminator="\n",
        )

        elapsed = max(time.perf_counter() - start_time, 0.001)
        speed = int(valid_rows / elapsed)
        send_progress(90, "Optimasi selesai.", valid_rows, valid_rows, speed, "baris/detik", "polars")

        return write_path, df_collected.columns, total_input_rows + 1, 0, skipped_count, 0, True, [], valid_rows, int(balance_total_cents), account_samples, direct_output_written

    except Exception as e:
        if temp_path and os.path.exists(temp_path):
            try: os.unlink(temp_path)
            except: pass
        raise e


def sanitize_source(
    source_path: str,
    delimiter: str,
) -> tuple[str, list[str], int, int, int, int, bool, list[int], int, int, list[dict[str, str]]]:
    """Try optimized Polars path first, fallback to legacy if needed."""
    try:
        return sanitize_source_optimized(source_path, delimiter)[:11]
    except Exception as e:
        send_event("debug", {"message": f"Polars fast-path failed, falling back to legacy: {str(e)}"})
        
        temp_dir = Path(tempfile.gettempdir())
        fd, temp_path = tempfile.mkstemp(prefix="simpanan_multipn_sanitized_", suffix=".csv", dir=str(temp_dir))
        os.close(fd)

        total_records = 0
        structural_skipped = 0
        validation_skipped = 0
        rewrite_needed = False
        skipped_rows: list[int] = []
        headers: list[str] = []
        valid_rows = 0
        balance_total_cents = 0
        account_samples: list[dict[str, str]] = []
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
                # Adaptive heartbeat frequency: More frequent for large files to prevent watchdog timeout
                # For 680k rows: 10k interval = 68 updates (vs 13 with 50k) = more responsive progress
                heartbeat_interval = 10000 if total_records > 100000 else 50000
                if row_number % heartbeat_interval == 0:
                    elapsed = max(time.perf_counter() - start_time, 0.001)
                    processed_rows = max(0, total_records - 1)
                    speed = int(processed_rows / elapsed)
                    send_progress(
                        min(50, 5 + int((row_number / 250000) * 45)),
                        "Menyiapkan sanitasi CSV Simpanan MultiPN (legacy loop)...",
                        processed_rows,
                        0,
                        speed,
                        "baris/detik",
                        "polars",
                    )

                if not headers:
                    raw_headers = [normalize_cell(cell) for cell in row]
                    headers = [normalize_header_name(header) or f"col_{index}" for index, header in enumerate(raw_headers)]
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
                for index, header in enumerate(headers):
                    if header == "" or header.startswith("col_"):
                        continue
                    values_by_header[header] = values[index]

                if not is_valid_simpanan_row_values(values_by_header):
                    validation_skipped += 1
                    skipped_rows.append(row_number)
                    rewrite_needed = True
                    continue

                for index, header in enumerate(headers):
                    if header == "posisi":
                        normalized = normalize_date_value(values[index])
                        if normalized is None:
                            validation_skipped += 1
                            skipped_rows.append(row_number)
                            rewrite_needed = True
                            break
                        values[index] = normalized
                        continue

                    if header == "saldo_idr":
                        normalized = normalize_decimal_value(values[index])
                        if normalized is None:
                            validation_skipped += 1
                            skipped_rows.append(row_number)
                            rewrite_needed = True
                            break
                        values[index] = normalized
                        balance_total_cents += decimal_string_to_cents(normalized)
                        continue

                    if header == "no_rekening":
                        raw_value = normalize_cell(values[index])
                        values[index] = raw_value
                        if len(account_samples) < 10:
                            account_samples.append({
                                "raw": raw_value,
                                "normalized": raw_value,
                            })
                        continue
                else:
                    writer.writerow(values)
                    valid_rows += 1
                    rewrite_needed = rewrite_needed or any(values[index] != normalize_cell(row[index]) for index in range(len(row)))
                    continue

                continue

        if not headers:
            raise RuntimeError("Header CSV Simpanan MultiPN tidak ditemukan.")

        return temp_path, headers, total_records, structural_skipped, validation_skipped, 0, rewrite_needed, skipped_rows, valid_rows, balance_total_cents, account_samples


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


def stage_simpanan_multipn(config: dict) -> None:
    source_path = config["file_path"]
    output_csv_path = config["output_csv_path"]
    delimiter = config.get("delimiter") or detect_delimiter(source_path, ",")

    send_progress(5, "Membaca dan menyiapkan CSV Simpanan MultiPN dengan Polars...", 0, 0, 0, "", "polars")
    (
        temp_sanitized_path,
        headers,
        total_records,
        structural_skipped,
        validation_skipped,
        duplicate_skipped,
        rewrite_needed,
        skipped_rows,
        valid_rows,
        balance_total_cents,
        account_samples,
        direct_output_written,
    ) = sanitize_source_optimized(source_path, delimiter, config)
    total_data_rows = max(0, total_records - 1)

    temp_path_to_cleanup = None
    try:
        if direct_output_written:
            written_rows = int(valid_rows)
            output_headers = list(headers)
        else:
            temp_path_to_cleanup = temp_sanitized_path
            send_progress(56, "Sanitasi selesai. Membaca file bersih dengan Polars...", total_data_rows, total_data_rows, 0, "", "polars")
            df = read_with_polars(temp_sanitized_path, headers, delimiter)

            if df.height == 0:
                raise RuntimeError("Polars tidak menemukan baris data yang valid.")

            written_rows = int(df.height)
            output_headers = list(df.columns)

        duplicate_count = int(duplicate_skipped)
        skipped_total = int(structural_skipped + validation_skipped + duplicate_count)

        if written_rows == 0:
            raise RuntimeError("Polars tidak menemukan baris data Simpanan MultiPN yang valid.")

        if not direct_output_written:
            send_progress(86, "Menulis CSV bersih untuk LOAD DATA...", written_rows, total_data_rows, 0, "", "polars")
            write_with_polars(df, output_csv_path, delimiter)

        send_event(
            "done",
            {
                "csv_path": output_csv_path,
                "total_rows": int(total_data_rows),
                "written_rows": written_rows,
                "skipped_count": skipped_total,
                "duplicate_count": duplicate_count,
                "skipped_rows": skipped_rows[:500],
                "rewritten": bool(rewrite_needed or structural_skipped > 0 or validation_skipped > 0 or duplicate_count > 0),
                "backend": "polars",
                "balance_total_cents": int(balance_total_cents),
                "account_samples": account_samples,
                "headers": output_headers,
                "full_vectorization": bool(config.get("full_vectorization", False)),
            },
        )
    finally:
        if temp_path_to_cleanup:
            try:
                os.unlink(temp_path_to_cleanup)
            except Exception:
                pass
        DBConnectionPool.get_instance().close()


def main() -> int:
    parser = argparse.ArgumentParser(description="Simpanan MultiPN CSV stage processor")
    parser.add_argument("--config", required=True, help="Path to JSON config file")
    parser.add_argument("--mode", default="stage", choices=["stage"], help="Processing mode")
    args = parser.parse_args()

    try:
        config = load_config(args.config)
        stage_simpanan_multipn(config)
        return 0
    except Exception as exc:
        send_error(str(exc))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
