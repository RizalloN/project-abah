#!/usr/bin/env python3
"""
Excel CPU Processor untuk Laravel Import
=========================================
Arsitektur:
  - Python  : baca Excel dengan pandas (CPU), normalisasi, output batch JSON ke stdout
  - PHP     : terima batch JSON dari stdout, insert ke database (tidak perlu pymysql)

Dependencies:
  pip install pandas openpyxl python-dateutil
"""

import sys
import json
import os
import argparse
import time
import uuid
from datetime import datetime, date, timedelta

# ── Force CPU: matikan semua GPU device sebelum import apapun ────────────────
os.environ['CUDA_VISIBLE_DEVICES']   = ''
os.environ['ROCR_VISIBLE_DEVICES']   = ''
os.environ['MLU_VISIBLE_DEVICES']    = ''
os.environ['ASCEND_VISIBLE_DEVICES'] = ''
os.environ['HIP_VISIBLE_DEVICES']    = ''


# ─────────────────────────────────────────────────────────────────────────────
# Helper: kirim event ke PHP (stdout)
# ─────────────────────────────────────────────────────────────────────────────

def send_event(event_type, data):
    data['type'] = event_type
    print(json.dumps(data, ensure_ascii=False, default=str), flush=True)


def send_progress(percent, message, rows_done=0, total=0, speed=0):
    send_event('progress', {
        'percent':   percent,
        'message':   message,
        'rows_done': rows_done,
        'total':     total,
        'speed':     speed,
    })


def send_error(message):
    send_event('error', {'message': message})


# ─────────────────────────────────────────────────────────────────────────────
# Normalisasi nilai sel Excel
# ─────────────────────────────────────────────────────────────────────────────

EXCEL_EPOCH  = date(1899, 12, 30)
DATE_COLUMNS = {'PERIODE', 'POSISI', 'TGL_REALISASI', 'TGL_JATUH_TEMPO', 'TANGGAL'}
NULL_STRS    = {'', 'nan', 'none', 'nat', 'null', 'n/a', 'na'}


def normalize_value(header_name, value):
    import math
    header = header_name.upper().strip()

    if value is None:
        return None

    if isinstance(value, float) and math.isnan(value):
        return None

    if isinstance(value, datetime):
        return value.strftime('%Y-%m-%d') if header in DATE_COLUMNS else value.strftime('%Y-%m-%d %H:%M:%S')

    if isinstance(value, date):
        return value.strftime('%Y-%m-%d')

    value_str = str(value).strip()
    if value_str.lower() in NULL_STRS:
        return None

    if header in DATE_COLUMNS:
        try:
            try:
                num = float(value_str)
                d = EXCEL_EPOCH + timedelta(days=int(num))
                return d.strftime('%Y-%m-%d')
            except (ValueError, OverflowError):
                pass
            from dateutil import parser as dateutil_parser
            return dateutil_parser.parse(value_str.replace('/', '-')).strftime('%Y-%m-%d')
        except Exception:
            return None

    try:
        num = float(value_str)
        formatted = '{:.2f}'.format(num).rstrip('0').rstrip('.')
        return formatted if formatted != '' else '0'
    except (ValueError, TypeError):
        pass

    return value_str


# ─────────────────────────────────────────────────────────────────────────────
# MODE: init — Scan cepat header & total baris
# ─────────────────────────────────────────────────────────────────────────────

def run_init(config):
    file_path = config['file_path']

    try:
        import pandas as pd
        df_scan = pd.read_excel(file_path, header=None, nrows=200, engine='openpyxl')
    except Exception as e:
        print(json.dumps({'status': 'error', 'message': 'Gagal membuka file: ' + str(e)}), flush=True)
        sys.exit(1)

    header_index  = None
    header_values = []

    for i in range(len(df_scan)):
        row       = df_scan.iloc[i]
        row_upper = [str(v).upper().strip() if str(v).lower() not in ('nan', 'none', '') else '' for v in row]
        if 'PERIODE' in row_upper or 'POSISI' in row_upper:
            header_index  = i
            header_values = [str(v).strip() if str(v).lower() not in ('nan', 'none') else '' for v in row]
            break

    if header_index is None:
        print(json.dumps({
            'status':  'error',
            'message': 'Header utama (PERIODE / POSISI) tidak ditemukan dalam 200 baris pertama.',
        }), flush=True)
        sys.exit(1)

    # Total baris via openpyxl read-only (cepat, dari metadata XML)
    total_rows = 0
    try:
        from openpyxl import load_workbook
        wb         = load_workbook(file_path, read_only=True, data_only=True)
        total_rows = wb.active.max_row or 0
        wb.close()
    except Exception:
        total_rows = 0

    print(json.dumps({
        'status':        'ok',
        'header_index':  header_index,
        'total_rows':    total_rows,
        'header_values': header_values,
    }), flush=True)
    sys.exit(0)


# ─────────────────────────────────────────────────────────────────────────────
# MODE: process — Baca Excel dengan pandas, output batch JSON ke stdout
#                 PHP yang akan insert ke database (tidak perlu pymysql)
# ─────────────────────────────────────────────────────────────────────────────

def run_process(config):
    try:
        _run_process_inner(config)
    except Exception as e:
        # Tangkap semua exception yang tidak tertangani dan kirim sebagai error event
        # agar PHP bisa fallback ke chunked reading
        import traceback
        send_error('Python error: ' + str(e) + ' | ' + traceback.format_exc(limit=3))
        sys.exit(1)


def _run_process_inner(config):
    file_path          = config['file_path']
    header_index       = int(config['header_index'])
    table_name         = config['table_name']
    active_filters     = config.get('active_filters', {})
    normalized_headers = config['normalized_headers']
    table_columns      = set(c.lower() for c in config.get('table_columns', []))

    # ── CRITICAL FIX: PHP json_encode mengubah array integer-key menjadi JSON array ──
    # Contoh: [0=>'PERIODE', 1=>'POSISI'] → ["PERIODE","POSISI"] (bukan {"0":"PERIODE",...})
    # Python menerima list, bukan dict → normalized_headers.keys() crash!
    # Fix: konversi list ke dict dengan index sebagai key string
    if isinstance(normalized_headers, list):
        normalized_headers = {str(i): v for i, v in enumerate(normalized_headers)}

    # ── Baca seluruh file dengan pandas (CPU, satu kali load) ────────────────
    send_progress(5, 'Membaca file Excel dengan pandas CPU...')

    try:
        import pandas as pd
        df = pd.read_excel(
            file_path,
            header=header_index,
            engine='openpyxl',
            dtype=object,
        )
        df = df.dropna(how='all').reset_index(drop=True)
        total_rows = len(df)
        send_progress(20, 'File dibaca: ' + str(total_rows) + ' baris. Memproses kolom...')
    except Exception as e:
        send_error('Gagal membaca file Excel: ' + str(e))
        sys.exit(1)

    unique_id_col = 'uniqueid_SimoPN' if 'simpanan' in table_name else 'uniqueid_namareport'
    suffix        = '_SimoPN'         if 'simpanan' in table_name else '_DLD'
    skip_cols     = set(['id', unique_id_col.lower()])

    # Build valid headers list: [(original_col_index, header_name), ...]
    valid_headers = []
    for idx_str in sorted(normalized_headers.keys(), key=lambda x: int(x)):
        h = normalized_headers[idx_str]
        if not h.startswith('COL_'):
            valid_headers.append((int(idx_str), h))

    send_progress(25, 'Mapping kolom selesai. Mulai kirim batch ke PHP untuk insert...', 0, total_rows)

    now_str    = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    batch      = []
    batch_size = 200    # 200 baris per batch JSON line (aman untuk max_allowed_packet)
    rows_done  = 0
    start_time = time.time()

    row_values = df.values.tolist()

    for row_list in row_values:
        mapped_data = {}
        pass_filter = True

        for filter_idx, (original_index, h_name) in enumerate(valid_headers):
            val = row_list[original_index] if original_index < len(row_list) else None
            val = normalize_value(h_name, val)

            filter_key = str(filter_idx)
            if active_filters and filter_key in active_filters:
                f_val = '(Blank)' if val is None else str(val)
                if f_val not in active_filters[filter_key]:
                    pass_filter = False
                    break

            mapped_data[h_name.upper().replace(' ', '_')] = val

        if not pass_filter:
            continue

        # Bangun baris final dengan unique ID dan timestamps
        final_row = {
            unique_id_col: str(uuid.uuid4()) + suffix,
            'created_at':  now_str,
            'updated_at':  now_str,
        }
        for excel_key, val in mapped_data.items():
            db_col = excel_key.lower()
            if db_col in skip_cols:
                continue
            if table_columns and db_col not in table_columns:
                continue
            final_row[db_col] = val

        if len(final_row) > 3:
            batch.append(final_row)
            rows_done += 1

        # Kirim batch ke PHP via stdout
        if len(batch) >= batch_size:
            print(json.dumps({'type': 'batch', 'rows': batch}, ensure_ascii=False, default=str), flush=True)
            batch = []

        # Kirim progress setiap 5000 baris
        if rows_done > 0 and rows_done % 5000 == 0:
            elapsed = max(time.time() - start_time, 0.001)
            speed   = int(rows_done / elapsed)
            pct     = min(90, 25 + int((rows_done / total_rows) * 65)) if total_rows > 0 else 50
            send_progress(pct, 'Memproses... (' + str(speed) + ' baris/detik)', rows_done, total_rows, speed)

    # Flush sisa batch terakhir
    if batch:
        print(json.dumps({'type': 'batch', 'rows': batch}, ensure_ascii=False, default=str), flush=True)

    send_progress(95, 'File selesai diproses. Menunggu PHP selesai insert ke database...', rows_done, total_rows)

    # Kirim event 'done' — PHP akan finalisasi job status dan kirim 'complete' ke browser
    send_event('done', {'total_rows': rows_done})


# ─────────────────────────────────────────────────────────────────────────────
# Entry point
# ─────────────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='Excel CPU Processor untuk Laravel Import')
    parser.add_argument('--config', required=True, help='Path ke file config JSON')
    parser.add_argument('--mode',   default='process', choices=['init', 'process'],
                        help='Mode: init (deteksi header) | process (output batch ke PHP)')
    args = parser.parse_args()

    try:
        with open(args.config, 'r', encoding='utf-8') as f:
            config = json.load(f)
    except Exception as e:
        if args.mode == 'init':
            print(json.dumps({'status': 'error', 'message': 'Gagal membaca config: ' + str(e)}), flush=True)
        else:
            send_error('Gagal membaca config: ' + str(e))
        sys.exit(1)

    if args.mode == 'init':
        run_init(config)
    else:
        run_process(config)


if __name__ == '__main__':
    main()
