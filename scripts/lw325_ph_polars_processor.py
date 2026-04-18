#!/usr/bin/env python3
"""
LW325_PH Polars Processor
=========================
High-speed processing for Report Nominatif Rekening Pinjaman PH.
"""

import argparse
import csv
import json
import os
import re
import sys
import time
from pathlib import Path
import polars as pl

REQUIRED_HEADERS = {
    "acctno",
    "kanca",
    "nama_debitur",
    "periode",
}

def send_event(event_type: str, **data) -> None:
    payload = dict(data)
    payload["type"] = event_type
    print(json.dumps(payload, ensure_ascii=False, default=str), flush=True)

def send_progress(percent: int, message: str, rows_done: int = 0, total: int = 0) -> None:
    send_event("progress", percent=percent, message=message, rows_done=rows_done, total=total, mode="polars")

def normalize_header(h: str) -> str:
    h = re.sub(r'^\xEF\xBB\xBF', '', str(h))
    return re.sub(r'[^a-z0-9]+', '_', h.strip().lower()).strip('_')

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--config", required=True)
    parser.add_argument("--mode", default="stage")
    args = parser.parse_args()

    with open(args.config, "r", encoding="utf-8-sig") as f:
        config = json.load(f)

    source_path = config["file_path"]
    output_path = config["output_csv_path"]
    delimiter = config.get("delimiter", ",")

    send_progress(10, "Membaca data LW325_PH dengan Polars...")

    # Load data
    try:
        is_excel = source_path.lower().endswith(('.xlsx', '.xls'))
        if is_excel:
            send_progress(15, "Membaca file Excel LW325_PH dengan engine fastexcel...")
            df = pl.read_excel(source_path, engine="fastexcel")
        else:
            send_progress(15, "Membaca file CSV/TXT LW325_PH...")
            df = pl.read_csv(
                source_path,
                separator=delimiter,
                infer_schema_length=10000,
                ignore_errors=True,
                truncate_ragged_lines=True
            )
    except Exception as e:
        if not is_excel:
            # Fallback to more relaxed reading for CSV
            df = pl.read_csv(source_path, separator=delimiter, ignore_errors=True, infer_schema_length=0)
        else:
            raise e

    # Normalize headers
    original_columns = df.columns
    rename_map = {col: normalize_header(col) for col in original_columns}
    df = df.rename(rename_map)

    # Check required headers
    current_headers = set(df.columns)
    missing = REQUIRED_HEADERS - current_headers
    if missing:
        # Try finding case-insensitive or similar
        for m in list(missing):
            for h in current_headers:
                if h.lower() == m.lower() or h.replace('_','').lower() == m.replace('_','').lower():
                    df = df.rename({h: m})
                    missing.remove(m)
                    break
        
        if missing:
            print(json.dumps({"type":"error", "message": f"Kolom wajib tidak ditemukan: {', '.join(missing)}"}), flush=True)
            sys.exit(1)

    send_progress(30, "Mencuci data (Date/Decimal normalization)...")

    # Clean data logic
    # 1. Strip strings
    # 2. Date normalization (YYYY-MM-DD or null)
    # 3. Decimal normalization (0.00 or null)
    
    date_cols = ['periode', 'tgl_ph', 'tgl_realisasi', 'wpstdt', 'wpstdt6']
    decimal_cols = [
        'saldo_pertama_ph_pokok', 'saldo_pertama_ph_bunga', 'besar_realisasi', 'plafon',
        'pokok', 'bunga', 'angpok', 'angbung', 'sisapok', 'sisabun', 'clmamt1', 'clmapr1',
        'os_penuh_berjalan1', 'saldo_pertama_kali_charge_off', 'deffered_bunga',
        'sai_deffered', 'sai_tunggakan', 'deffered_bunga_ph', 'sai_tunggakan_ph',
        'sai_deffered_ph', 'wcbal', 'waccint', 'wadvpmt', 'wpenint', 'wmisc', 'wothchg',
        'wpmtamt', 'wamount', 'clmamt', 'clmapr'
    ]
    int_cols = ['jw', 'at', 'jumlah_pn', 'jumlah_pn_all']

    def clean_decimal(s):
        if s is None: return None
        s = str(s).strip()
        if not s or s == '-' or s == 'nan': return None
        # Handle formats like (1.234,56) or 1,234.56
        s = s.replace('(', '-').replace(')', '')
        if ',' in s and '.' in s:
            if s.rfind(',') > s.rfind('.'): # European style: 1.234,56
                s = s.replace('.', '').replace(',', '.')
            else: # US style: 1,234.56
                s = s.replace(',', '')
        elif ',' in s: # European or just comma decimal: 1234,56
            # If comma is followed by 2 digits, assume it's decimal
            parts = s.split(',')
            if len(parts) == 2 and len(parts[1]) <= 2:
                s = s.replace(',', '.')
            else:
                s = s.replace(',', '')
        
        try:
            return float(s)
        except:
            return None

    def clean_date(s):
        if s is None: return None
        s = str(s).strip()
        if not s or s == 'nan': return None
        # Quick extract digit-based date
        match = re.search(r'(\d{1,4})[/-](\d{1,2})[/-](\d{1,4})', s)
        if match:
            g1, g2, g3 = match.groups()
            if len(g1) == 4: # YYYY-MM-DD
                return f"{g1}-{g2.zfill(2)}-{g3.zfill(2)}"
            elif len(g3) == 4: # DD-MM-YYYY or MM-DD-YYYY
                # Logic from StrictDateParser: we usually assume DD-MM-YYYY unless it looks like US
                return f"{g3}-{g2.zfill(2)}-{g1.zfill(2)}"
        return None

    # Apply cleaning
    for col in date_cols:
        if col in df.columns:
            df = df.with_columns(pl.col(col).map_elements(clean_date, return_dtype=pl.Utf8))
    
    for col in decimal_cols:
        if col in df.columns:
            df = df.with_columns(pl.col(col).map_elements(clean_decimal, return_dtype=pl.Float64))

    for col in int_cols:
        if col in df.columns:
            df = df.with_columns(pl.col(col).map_elements(lambda x: int(float(x)) if x is not None and str(x).strip() not in ('','nan') else None, return_dtype=pl.Int64))

    # Filter out empty acctno
    df = df.filter(pl.col("acctno").is_not_null() & (pl.col("acctno").cast(pl.Utf8).str.strip_chars() != ""))

    # Generate uniqueid_namareport if not exists
    if "uniqueid_namareport" not in df.columns:
        # Format: PERIODE_ACCTNO_RPH
        df = df.with_columns(
            (pl.col("periode").cast(pl.Utf8) + "_" + pl.col("acctno").cast(pl.Utf8) + "_RPH").alias("uniqueid_namareport")
        )

    # Ensure all target columns exist (fill null if not in report)
    # The list is based on TARGET_COLUMNS
    target_cols = [
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
        'wamount', 'flag_klaim', 'clmamt', 'clmapr'
    ]

    for col in target_cols:
        if col not in df.columns:
            df = df.with_columns(pl.lit(None).cast(pl.Utf8).alias(col))

    # Select and reorder to match TARGET_COLUMNS
    df = df.select(target_cols)

    send_progress(80, "Menulis CSV akhir untuk LOAD DATA INFILE...")
    
    df.write_csv(output_path, separator=",", include_header=True)

    send_event("done", 
               written_rows=df.height, 
               total_rows=df.height, 
               dates=df.select("periode").unique().to_series().to_list())

if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(json.dumps({"type":"error", "message": str(e)}), flush=True)
        sys.exit(1)
