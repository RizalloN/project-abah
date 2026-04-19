#!/usr/bin/env python3
"""
LW325_PH Polars Processor — Optimized
======================================
Optimisasi vs versi lama:
1. Direct Polars CSV load — eliminasi sanitize_csv_source Python loop (2 full-pass → 0)
2. Normalisasi date/decimal/int di SEMUA mode — menghilangkan kebutuhan PHP fallback
3. Semua normalisasi dibatch dalam 3 with_columns() — bukan 39 call terpisah
4. Format tanggal dikurangi ke 6 format paling umum (dari 10)
"""

import argparse
import csv
import json
import os
import re
import sys
from datetime import datetime
import polars as pl

REQUIRED_HEADERS = {"acctno", "kanca", "nama_debitur", "periode"}

TARGET_COLS = [
    'uniqueid_namareport', 'periode', 'acctno', 'kanwil', 'kanca', 'unit', 'nama_debitur', 'cif1',
    'fksegmen', 'segmen_dashboard', 'description', 'produk_dashboard', 'tgl_ph', 'tgl_realisasi',
    'curtyp', 'saldo_pertama_ph_pokok', 'saldo_pertama_ph_bunga', 'besar_realisasi', 'plafon',
    'jw', 'at', 'cif', 'pokok', 'bunga', 'angpok', 'angbung', 'sisapok', 'sisabun', 'clmamt1',
    'clmapr1', 'os_penuh_berjalan1', 'kecamatan_t_tinggal', 'kelurahan_t_tinggal',
    'kodepos_t_tinggal', 'kecamatan_t_usaha', 'kelurahan_t_usaha', 'kodepos_t_usaha',
    'pn_pengelola', 'pn_pemrakarsa', 'pn_referral', 'pn_restruk', 'pn_pengelola2',
    'pn_pemutus', 'pn_crm', 'pn_crr1', 'pn_referral_naik_kelas', 'jumlah_pn',
    'jumlah_pn_all', 'saldo_pertama_kali_charge_off', 'deffered_bunga', 'sai_deffered',
    'sai_tunggakan', 'deffered_bunga_ph', 'sai_tunggakan_ph', 'sai_deffered_ph', 'wcbal',
    'waccint', 'wadvpmt', 'wpenint', 'wmisc', 'wothchg', 'wpmtamt', 'wpstdt', 'wpstdt6',
    'wamount', 'flag_klaim', 'clmamt', 'clmapr',
]

DATE_COLS = ['periode', 'tgl_ph', 'tgl_realisasi', 'wpstdt', 'wpstdt6']
PREFER_MONTH_FIRST_COLS = {'periode'}

DECIMAL_COLS = [
    'saldo_pertama_ph_pokok', 'saldo_pertama_ph_bunga', 'besar_realisasi', 'plafon',
    'pokok', 'bunga', 'angpok', 'angbung', 'sisapok', 'sisabun', 'clmamt1', 'clmapr1',
    'os_penuh_berjalan1', 'saldo_pertama_kali_charge_off', 'deffered_bunga',
    'sai_deffered', 'sai_tunggakan', 'deffered_bunga_ph', 'sai_tunggakan_ph',
    'sai_deffered_ph', 'wcbal', 'waccint', 'wadvpmt', 'wpenint', 'wmisc', 'wothchg',
    'wpmtamt', 'wamount', 'clmamt', 'clmapr',
]
INT_COLS = ['jw', 'at', 'jumlah_pn', 'jumlah_pn_all']

# Format tanggal prioritas tinggi untuk lw325_ph (US banking format)
DATE_FMTS_MONTH_FIRST = [
    "%m/%d/%Y %I:%M:%S %p",
    "%m/%d/%Y %H:%M:%S",
    "%m/%d/%Y",
    "%Y-%m-%d",
    "%d/%m/%Y",
    "%d-%m-%Y",
]
DATE_FMTS_DAY_FIRST = [
    "%d/%m/%Y %I:%M:%S %p",
    "%d/%m/%Y %H:%M:%S",
    "%d/%m/%Y",
    "%Y-%m-%d",
    "%m/%d/%Y",
    "%d-%m-%Y",
]


# ---------------------------------------------------------------------------
# Output helpers
# ---------------------------------------------------------------------------

def send_event(event_type: str, **data) -> None:
    payload = dict(data)
    payload["type"] = event_type
    print(json.dumps(payload, ensure_ascii=False, default=str), flush=True)


def send_progress(percent: int, message: str, rows_done: int = 0, total: int = 0) -> None:
    send_event("progress", percent=percent, message=message, rows_done=rows_done, total=total, mode="polars")


# ---------------------------------------------------------------------------
# Header / delimiter utilities
# ---------------------------------------------------------------------------

def normalize_header(h: str) -> str:
    h = re.sub(r'^\xEF\xBB\xBF|\ufeff', '', str(h))
    return re.sub(r'[^a-z0-9]+', '_', h.strip().lower()).strip('_')


def extract_metadata_period(text: str):
    m = re.search(r'periode\s*data\s*:\s*([^,\r\n]+)', text, re.IGNORECASE)
    if not m:
        return None
    return clean_date(m.group(1).strip(), prefer_month_first=True)


def detect_header_row(source_path: str, delimiter: str):
    """Scan baris awal untuk menemukan header dan periode metadata (cepat — max 100 baris)."""
    metadata_period = None
    is_excel = source_path.lower().endswith(('.xlsx', '.xls'))

    if is_excel:
        try:
            # Gunakan Polars probing untuk Excel — jauh lebih cepat daripada scan binary sebagai teks
            probe_df = pl.read_excel(source_path, n_rows=100, engine="fastexcel")
            for idx, row_tuple in enumerate(probe_df.iter_rows()):
                # Cek metadata periode di awal-awal baris
                if idx < 20 and metadata_period is None:
                    joined = ",".join(str(v).strip() for v in row_tuple if v is not None)
                    metadata_period = extract_metadata_period(joined) or metadata_period
                
                normalized = [normalize_header(v) for v in row_tuple if v is not None]
                if normalized and REQUIRED_HEADERS.issubset(set(normalized)):
                    # Simpan raw headers untuk mapping kolom asli
                    return idx + 1, metadata_period, [str(v) for v in row_tuple]
            
            # Jika tidak ketemu di data (mungkin ada di header fastexcel itu sendiri)
            normalized_h = [normalize_header(c) for c in probe_df.columns]
            if REQUIRED_HEADERS.issubset(set(normalized_h)):
                return 0, metadata_period, probe_df.columns
                
        except Exception as e:
            send_event("debug", message=f"Excel probe failed: {str(e)}")

    # Fallback/Default untuk CSV
    with open(source_path, "r", encoding="utf-8-sig", errors="replace", newline="") as fh:
        reader = csv.reader(fh, delimiter=delimiter)
        for idx, row in enumerate(reader):
            if idx < 20 and metadata_period is None:
                joined = ",".join(str(v).strip() for v in row if str(v).strip())
                metadata_period = extract_metadata_period(joined) or metadata_period
            
            normalized = [normalize_header(v) for v in row if str(v).strip()]
            if normalized and REQUIRED_HEADERS.issubset(set(normalized)):
                return idx, metadata_period, row
            if idx > 200:
                break

    raise RuntimeError(
        "Header LW325_PH tidak ditemukan. "
        "Pastikan file memiliki baris header dengan PERIODE, ACCTNO, KANCA, NAMA_DEBITUR."
    )


def detect_delimiter(path: str, fallback: str = ",") -> str:
    if path.lower().endswith(('.xlsx', '.xls')):
        return ","  # Excel tidak butuh delimiter csv

    try:
        with open(path, "r", encoding="utf-8-sig", errors="replace", newline="") as fh:
            samples = []
            for line in fh:
                s = line.rstrip("\r\n")
                if s.strip():
                    samples.append(s)
                if len(samples) >= 12:
                    break
        if not samples:
            return fallback
        best, best_score = fallback, -(10 ** 9)
        for cand in [",", ";", "\t", "|"]:
            counts = []
            for s in samples:
                row = list(csv.reader([s], delimiter=cand, quotechar='"'))[0]
                while row and not row[-1].strip():
                    row.pop()
                counts.append(len(row))
            mx, mn = max(counts), min(counts)
            stable = sum(1 for c in counts if c == mx)
            score = mx * 1000 + stable * 100 - (mx - mn) * 20
            if score > best_score:
                best_score, best = score, cand
        return best
    except Exception:
        return fallback


def clean_date(value, prefer_month_first=False):
    """Konversi string tanggal → YYYY-MM-DD (dipakai hanya untuk metadata)."""
    if value is None:
        return None
    text = re.sub(r"\s+", " ", str(value).strip())
    if not text or text.lower() == "nan":
        return None
    fmts = DATE_FMTS_MONTH_FIRST if prefer_month_first else DATE_FMTS_DAY_FIRST
    for fmt in fmts:
        try:
            return datetime.strptime(text, fmt).strftime("%Y-%m-%d")
        except ValueError:
            continue
    m = re.search(r"(\d{1,4})[/\-](\d{1,2})[/\-](\d{1,4})", text)
    if not m:
        return None
    a, b, c = m.groups()
    try:
        if len(a) == 4:
            return datetime(int(a), int(b), int(c)).strftime("%Y-%m-%d")
        if len(c) == 4:
            p, q, yr = int(a), int(b), int(c)
            mo, dy = (p, q) if (prefer_month_first and p <= 12) else (q, p)
            return datetime(yr, mo, dy).strftime("%Y-%m-%d")
    except ValueError:
        pass
    return None


# ---------------------------------------------------------------------------
# Direct CSV loader — tanpa Python sanitize loop
# ---------------------------------------------------------------------------

def load_csv_polars(source_path: str, delimiter: str, skip_rows: int, raw_headers) -> pl.DataFrame:
    """
    Load CSV langsung dengan Polars (semua kolom sebagai Utf8).
    Eliminasi sanitize_csv_source yang sebelumnya butuh 2 full Python loop.
    """
    schema_overrides = {str(h).strip(): pl.Utf8 for h in raw_headers if str(h).strip()}
    base_kwargs = {
        "separator": delimiter,
        "skip_rows": skip_rows,
        "has_header": True,
        "infer_schema_length": 0,
        "ignore_errors": True,
        "truncate_ragged_lines": True,
    }

    # Coba schema_overrides (Polars modern)
    for enc in ["utf8-lossy", None]:
        try:
            kw = {**base_kwargs}
            if enc:
                kw["encoding"] = enc
            try:
                return pl.read_csv(source_path, schema_overrides=schema_overrides, **kw)
            except TypeError:
                try:
                    return pl.read_csv(source_path, dtypes=schema_overrides, **kw)
                except TypeError:
                    return pl.read_csv(source_path, **kw)
        except Exception:
            if enc is None:
                raise


# ---------------------------------------------------------------------------
# Ekspresi normalisasi (dibangun sekali, dieksekusi dalam 1 with_columns batch)
# ---------------------------------------------------------------------------

def _strptime_safe(base: pl.Expr, fmt: str) -> pl.Expr:
    try:
        return base.str.strptime(pl.Date, fmt, strict=False, exact=True)
    except TypeError:
        return base.str.strptime(pl.Date, fmt, strict=False)


def build_date_exprs(columns: list, prefer_month_first_set: set) -> list:
    """Bangun list ekspresi normalisasi tanggal untuk 1 with_columns call."""
    exprs = []
    for col in columns:
        base = pl.col(col).cast(pl.Utf8).str.strip_chars()
        fmts = DATE_FMTS_MONTH_FIRST if col in prefer_month_first_set else DATE_FMTS_DAY_FIRST
        candidates = [_strptime_safe(base, fmt) for fmt in fmts]
        exprs.append(pl.coalesce(candidates).dt.strftime("%Y-%m-%d").alias(col))
    return exprs


def build_decimal_exprs(columns: list) -> list:
    """
    Robust decimal normalization for Polars.
    Handles: "219,000.00", "219.000,00", "(219,000.00)", "219000", etc.
    """
    exprs = []
    for col in columns:
        # 1. Pre-cleaning (strip, parents to negative, remove non-numeric junk)
        base = (
            pl.col(col).cast(pl.Utf8).str.strip_chars()
            .str.replace_all(r"^\((.+)\)$", r"-$1")
            .str.replace_all(r"\s+", "")
        )

        # 2. Logic: Jika ada titik DAN koma, atau jika ada titik/koma berulang, kita harus normalisasi.
        # Strategi: Hapus SEMUA pemisah ribuan, sisakan hanya 1 titik desimal di akhir.
        
        # Step A: Jika ada format ribuan titik (1.234,56), ubah jadi format standar (1234.56)
        # Kita deteksi jika ada titik diikuti oleh 3 angka DAN ada koma di belakangnya.
        is_id_format = base.str.contains(r"\d\.\d{3}.*,")
        
        cleaned = (
            pl.when(is_id_format)
            .then(base.str.replace_all(r"\.", "").str.replace(r",", "."))
            .otherwise(base.str.replace_all(r",", "")) # Assume US format or simple number
            .str.replace_all(r"[^0-9.\-]", "") # Final safety sweep
        )

        expr = (
            pl.when(cleaned.is_null() | (cleaned == "") | (cleaned == "-") | (cleaned.str.to_lowercase() == "nan"))
            .then(pl.lit(None))
            .when(cleaned.str.contains(r"^-?\d+$"))
            .then(cleaned + ".00")
            .when(cleaned.str.contains(r"^-?\d+\.\d$"))
            .then(cleaned + "0")
            .when(cleaned.str.contains(r"^-?\d+\.\d+$"))
            .then(cleaned)
            .otherwise(pl.lit(None))
            .alias(col)
        )
        exprs.append(expr)
    return exprs


def build_integer_exprs(columns: list) -> list:
    """Bangun list ekspresi normalisasi integer untuk 1 with_columns call."""
    exprs = []
    for col in columns:
        base = (
            pl.col(col).cast(pl.Utf8).str.strip_chars()
            .str.replace_all(r"^\((.+)\)$", r"-$1")
            .str.replace_all(r"\s+", "")
            .str.replace_all(r"[^0-9.\-]", "")
        )
        expr = (
            pl.when(base.is_null() | (base == "") | (base == "-"))
            .then(pl.lit(None))
            .when(base.str.contains(r"^-?\d+$"))
            .then(base)
            .when(base.str.contains(r"^-?\d+\.\d+$"))
            .then(base.str.replace(r"\.\d+$", ""))
            .otherwise(pl.lit(None))
            .alias(col)
        )
        exprs.append(expr)
    return exprs


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--config", required=True)
    parser.add_argument("--mode", default="stage")
    args = parser.parse_args()

    with open(args.config, "r", encoding="utf-8-sig") as f:
        config = json.load(f)

    source_path   = config["file_path"]
    output_path   = config["output_csv_path"]
    delimiter     = config.get("delimiter") or ""
    active_filters = config.get("active_filters") or {}
    output_mode   = str(config.get("output_mode") or "preview").strip().lower()
    load_columns  = [str(c).strip() for c in (config.get("load_columns") or []) if str(c).strip()]
    timestamp     = str(config.get("timestamp") or datetime.now().strftime("%Y-%m-%d %H:%M:%S"))
    unique_suffix = str(config.get("unique_suffix") or "_RPH")

    if not delimiter:
        delimiter = detect_delimiter(source_path, ",")

    # ------------------------------------------------------------------
    # 1. Deteksi header (cepat — hanya scan ~20 baris)
    # ------------------------------------------------------------------
    send_progress(10, "Membaca data LW325_PH dengan Polars...")
    header_row_index, metadata_period, raw_headers = detect_header_row(source_path, delimiter)

    is_excel = source_path.lower().endswith(('.xlsx', '.xls'))

    # ------------------------------------------------------------------
    # 2. Load data
    # ------------------------------------------------------------------
    if is_excel:
        send_progress(15, "Membaca file Excel LW325_PH dengan fastexcel...")
        # Gunakan read_excel dengan parameter header_row jika ditemukan
        df = pl.read_excel(source_path, engine="fastexcel", read_options={"header_row": header_row_index} if header_row_index > 0 else None)
    else:
        send_progress(15, "Memuat CSV LW325_PH langsung dengan Polars (direct load)...")
        df = load_csv_polars(source_path, delimiter, header_row_index, raw_headers)

    data_rows_estimate = df.height

    # ------------------------------------------------------------------
    # 3. Normalisasi header kolom (1 operasi)
    # ------------------------------------------------------------------
    rename_map = {col: normalize_header(col) for col in df.columns}
    df = df.rename(rename_map)

    # ------------------------------------------------------------------
    # 4. Filter baris tanpa acctno (DILAKUKAN AWAL UNTUK SPEED)
    # ------------------------------------------------------------------
    df = df.filter(
        pl.col("acctno").is_not_null()
    )

    # ------------------------------------------------------------------
    # 5. Strip whitespace HANYA untuk kolom String (Pl.Utf8)
    #    Ini jauh lebih cepat daripada strip semua kolom.
    # ------------------------------------------------------------------
    string_cols = [c for c in df.columns if df.schema[c] == pl.Utf8]
    if string_cols:
        df = df.with_columns([
            pl.col(c).str.strip_chars().alias(c)
            for c in string_cols
        ])

    # ------------------------------------------------------------------
    # 5. Validasi required headers
    # ------------------------------------------------------------------
    current = set(df.columns)
    missing = REQUIRED_HEADERS - current
    if missing:
        for m in list(missing):
            for h in current:
                if h.replace('_', '').lower() == m.replace('_', '').lower():
                    df = df.rename({h: m})
                    current.add(m)
                    missing.discard(m)
                    break
        if missing:
            send_event("error", message=f"Kolom wajib tidak ditemukan: {', '.join(missing)}")
            sys.exit(1)

    # ------------------------------------------------------------------
    # 7. Terapkan active_filters (vectorized)
    # ------------------------------------------------------------------

    # ------------------------------------------------------------------
    # 7. Terapkan active_filters (vectorized)
    # ------------------------------------------------------------------
    if active_filters:
        send_progress(30, "Menerapkan filter cepat dengan Polars...", 0, data_rows_estimate)
        for col, raw_values in active_filters.items():
            if col not in df.columns:
                continue
            values = [str(v).strip() for v in (raw_values or []) if str(v).strip()]
            if values:
                df = df.filter(pl.col(col).str.strip_chars().is_in(values))

    # ------------------------------------------------------------------
    # 8. Tambah kolom target yang belum ada sebagai null
    # ------------------------------------------------------------------
    existing = set(df.columns)
    missing_cols = [c for c in TARGET_COLS if c != 'uniqueid_namareport' and c not in existing]
    if missing_cols:
        df = df.with_columns([pl.lit(None).cast(pl.Utf8).alias(c) for c in missing_cols])

    # ------------------------------------------------------------------
    # 9. Isi periode dari metadata jika kosong
    # ------------------------------------------------------------------
    if metadata_period and "periode" in df.columns:
        df = df.with_columns(
            pl.when(pl.col("periode").is_null() | (pl.col("periode").str.strip_chars() == ""))
            .then(pl.lit(metadata_period))
            .otherwise(pl.col("periode"))
            .alias("periode")
        )

    send_progress(45, "Normalisasi tanggal, desimal, integer LW325_PH (3 batch pass)...", 0, data_rows_estimate)

    # ------------------------------------------------------------------
    # 10a. Normalisasi tanggal — 1 with_columns call (bukan 5 call terpisah)
    # ------------------------------------------------------------------
    date_cols_active = [c for c in DATE_COLS if c in df.columns]
    if date_cols_active:
        df = df.with_columns(build_date_exprs(date_cols_active, PREFER_MONTH_FIRST_COLS))

    # ------------------------------------------------------------------
    # 10b. Normalisasi desimal — 1 with_columns call (bukan 30 call terpisah)
    # ------------------------------------------------------------------
    dec_active = [c for c in DECIMAL_COLS if c in df.columns]
    if dec_active:
        df = df.with_columns(build_decimal_exprs(dec_active))

    # ------------------------------------------------------------------
    # 10c. Normalisasi integer — 1 with_columns call (bukan 4 call terpisah)
    # ------------------------------------------------------------------
    int_active = [c for c in INT_COLS if c in df.columns]
    if int_active:
        df = df.with_columns(build_integer_exprs(int_active))

    # ------------------------------------------------------------------
    # 11. Generate uniqueid + timestamps (dilakukan di SEMUA mode)
    #     Ini memperbaiki kegagalan validateLw325NormalizedPeriods di queue path
    # ------------------------------------------------------------------
    total_rows = df.height
    try:
        df = df.with_row_index("_ridx")
    except AttributeError:
        df = df.with_row_count("_ridx")
    periode_val = pl.coalesce([pl.col("periode").cast(pl.Utf8), pl.lit(metadata_period or "unknown")])
    acctno_val  = pl.coalesce([pl.col("acctno").cast(pl.Utf8), pl.lit("missing")])

    df = df.with_columns([
        (periode_val + "_" + acctno_val + "_" + pl.col("_ridx").cast(pl.Utf8) + unique_suffix).alias("uniqueid_namareport"),
        pl.lit(timestamp).alias("created_at"),
        pl.lit(timestamp).alias("updated_at"),
    ])

    send_progress(82, "Menulis CSV final LW325_PH...", total_rows, max(total_rows, data_rows_estimate))

    # ------------------------------------------------------------------
    # 12. Tulis output
    # ------------------------------------------------------------------
    if output_mode == "bulk_load":
        if not load_columns:
            raise RuntimeError("Kolom LOAD DATA untuk LW325_PH kosong.")

        for c in load_columns:
            if c not in df.columns:
                df = df.with_columns(pl.lit(None).cast(pl.Utf8).alias(c))

        send_progress(90, "Menulis CSV bulk load final dengan Polars...", total_rows, total_rows)

        # Select + escape backslash dalam 1 operasi
        final_df = df.select([
            pl.when(pl.col(c).is_null() | (pl.col(c).cast(pl.Utf8).str.strip_chars() == ""))
            .then(pl.lit(None))
            .otherwise(pl.col(c).cast(pl.Utf8).str.replace_all(r"\\", r"\\\\"))
            .alias(c)
            for c in load_columns
        ])

        os.makedirs(os.path.dirname(output_path), exist_ok=True)
        final_df.write_csv(
            output_path,
            separator=",",
            include_header=False,
            null_value=r"\N",
            quote_char='"',
            line_terminator="\n",
        )

    else:
        # Preview mode: tulis dengan header, urutan TARGET_COLS
        # uniqueid sekarang sudah di-set → validateLw325NormalizedPeriods akan lolos
        preview_cols = [c for c in TARGET_COLS if c in df.columns]
        df.select(preview_cols).write_csv(output_path, separator=",", include_header=True)

    dates = []
    if "periode" in df.columns:
        try:
            dates = df.select("periode").unique().drop_nulls().to_series().to_list()
        except Exception:
            dates = []

    send_event(
        "done",
        written_rows=total_rows,
        total_rows=total_rows,
        csv_path=output_path,
        dates=dates,
    )


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(json.dumps({"type": "error", "message": str(exc)}), flush=True)
        sys.exit(1)
