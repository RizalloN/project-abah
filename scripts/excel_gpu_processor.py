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
import csv
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
DECIMAL_COLUMNS = {'BAKI_DEBET'}
NULL_STRS    = {'', 'nan', 'none', 'nat', 'null', 'n/a', 'na'}


def canonicalize_header(header_name):
    import re
    return re.sub(r'[^A-Z0-9]+', '_', str(header_name).upper().strip()).strip('_')


def normalize_decimal_value(value):
    if value is None:
        return None

    value_str = str(value).strip()
    if value_str.lower() in NULL_STRS:
        return None

    value_str = ''.join(value_str.split())
    filtered = ''.join(ch for ch in value_str if ch.isdigit() or ch in ',.-')

    if filtered in ('', '-', None):
        return None

    has_comma = ',' in filtered
    has_dot = '.' in filtered

    if has_comma and has_dot:
        if filtered.rfind(',') > filtered.rfind('.'):
            filtered = filtered.replace('.', '')
            filtered = filtered.replace(',', '.')
        else:
            filtered = filtered.replace(',', '')
    elif has_comma:
        parts = filtered.split(',')
        last_part = parts[-1]
        if len(parts) > 2 or len(last_part) == 3:
            filtered = filtered.replace(',', '')
        else:
            filtered = filtered.replace(',', '.')
    elif has_dot:
        parts = filtered.split('.')
        last_part = parts[-1]
        if len(parts) > 2 or len(last_part) == 3:
            filtered = filtered.replace('.', '')

    try:
        return '{:.2f}'.format(float(filtered))
    except (ValueError, TypeError):
        return None


def normalize_value(header_name, value):
    import math
    header = canonicalize_header(header_name)

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

    if header in DECIMAL_COLUMNS:
        return normalize_decimal_value(value)

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
    table_columns_map = {str(col).lower(): str(col) for col in table_columns}
    unique_id_col = table_columns_map.get(unique_id_col.lower(), unique_id_col)
    skip_cols     = set(['id', unique_id_col.lower()])

    # Build valid headers list: [(original_col_index, header_name), ...]
    valid_headers = []
    for idx_str in sorted(normalized_headers.keys(), key=lambda x: int(x)):
        h = normalized_headers[idx_str]
        if not h.startswith('COL_'):
            valid_headers.append((int(idx_str), h))

    output_csv_path = config.get('output_csv_path')
    load_columns = [str(col).strip() for col in config.get('load_columns', []) if str(col).strip() != '']

    if output_csv_path and not load_columns:
        send_error('Kolom bulk load tidak tersedia untuk export CSV.')
        sys.exit(1)

    send_progress(25, 'Mapping kolom selesai. Menyiapkan output import...', 0, total_rows)

    now_str    = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    batch      = []
    batch_size = 200    # 200 baris per batch JSON line (aman untuk max_allowed_packet)
    rows_done  = 0
    start_time = time.time()

    row_values = df.values.tolist()

    csv_file = None
    csv_writer = None
    if output_csv_path:
        os.makedirs(os.path.dirname(output_csv_path), exist_ok=True)
        csv_file = open(output_csv_path, 'w', encoding='utf-8', newline='')
        csv_writer = csv.writer(
            csv_file,
            delimiter=',',
            quotechar='"',
            quoting=csv.QUOTE_MINIMAL,
            lineterminator='\n',
            doublequote=True,
        )

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
            if table_columns and db_col not in table_columns_map:
                continue
            final_row[table_columns_map.get(db_col, db_col)] = val

        if len(final_row) > 3:
            rows_done += 1

            if csv_writer is not None:
                csv_writer.writerow([
                    r'\N' if final_row.get(column) is None else final_row.get(column)
                    for column in load_columns
                ])
            else:
                batch.append(final_row)

        if csv_writer is None and len(batch) >= batch_size:
            print(json.dumps({'type': 'batch', 'rows': batch}, ensure_ascii=False, default=str), flush=True)
            batch = []

        # Kirim progress setiap 5000 baris
        if rows_done > 0 and rows_done % 5000 == 0:
            elapsed = max(time.time() - start_time, 0.001)
            speed   = int(rows_done / elapsed)
            pct     = min(90, 25 + int((rows_done / total_rows) * 65)) if total_rows > 0 else 50
            send_progress(pct, 'Memproses... (' + str(speed) + ' baris/detik)', rows_done, total_rows, speed)

    if csv_file is not None:
        csv_file.close()
        send_progress(95, 'CSV sementara selesai dibuat. Menunggu MySQL bulk load...', rows_done, total_rows)
        send_event('done', {'total_rows': rows_done, 'csv_path': output_csv_path})
        return

    if batch:
        print(json.dumps({'type': 'batch', 'rows': batch}, ensure_ascii=False, default=str), flush=True)

    send_progress(95, 'File selesai diproses. Menunggu PHP selesai insert ke database...', rows_done, total_rows)
    send_event('done', {'total_rows': rows_done})


def run_stage(config):
    try:
        import pandas as pd
    except Exception as e:
        send_error('Pandas tidak tersedia: ' + str(e))
        sys.exit(1)

    file_path = config['file_path']
    header_index = int(config['header_index'])
    normalized_headers = config['normalized_headers']
    output_csv_path = config['output_csv_path']

    if isinstance(normalized_headers, list):
        normalized_headers = {str(i): v for i, v in enumerate(normalized_headers)}

    try:
        df = pd.read_excel(
            file_path,
            header=header_index,
            engine='openpyxl',
            dtype=object,
        )
        df = df.dropna(how='all').reset_index(drop=True)
    except Exception as e:
        send_error('Gagal membaca file Excel untuk staging: ' + str(e))
        sys.exit(1)

    valid_headers = []
    for idx_str in sorted(normalized_headers.keys(), key=lambda x: int(x)):
        h = normalized_headers[idx_str]
        if not str(h).startswith('COL_'):
            valid_headers.append((int(idx_str), str(h)))

    total_rows = len(df)
    send_progress(10, 'Memulai konversi Excel ke CSV stage...', 0, total_rows, 0)

    os.makedirs(os.path.dirname(output_csv_path), exist_ok=True)

    rows_done = 0
    start_time = time.time()

    with open(output_csv_path, 'w', encoding='utf-8', newline='') as csv_file:
        writer = csv.writer(
            csv_file,
            delimiter=',',
            quotechar='"',
            quoting=csv.QUOTE_MINIMAL,
            lineterminator='\n',
            doublequote=True,
        )

        writer.writerow([header_name for _, header_name in valid_headers])

        row_values = df.values.tolist()
        for row_list in row_values:
            output_row = []
            has_value = False

            for original_index, header_name in valid_headers:
                val = row_list[original_index] if original_index < len(row_list) else None
                val = normalize_value(header_name, val)
                if val is not None and str(val).strip() != '':
                    has_value = True
                output_row.append(r'\N' if val is None else val)

            if not has_value:
                continue

            writer.writerow(output_row)
            rows_done += 1

            if rows_done > 0 and rows_done % 5000 == 0:
                elapsed = max(time.time() - start_time, 0.001)
                speed = int(rows_done / elapsed)
                pct = min(95, 10 + int((rows_done / max(total_rows, 1)) * 80))
                send_progress(pct, 'Mengonversi ke CSV stage... (' + str(speed) + ' baris/detik)', rows_done, total_rows, speed)

    send_progress(98, 'CSV stage selesai dibuat.', rows_done, total_rows, 0)
    send_event('done', {'total_rows': rows_done, 'csv_path': output_csv_path})


# ─────────────────────────────────────────────────────────────────────────────
# Entry point
# ─────────────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='Excel CPU Processor untuk Laravel Import')
    parser.add_argument('--config', required=True, help='Path ke file config JSON')
    parser.add_argument('--mode',   default='process', choices=['init', 'process', 'stage'],
                        help='Mode: init | process | stage')
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
    elif args.mode == 'stage':
        run_stage(config)
    else:
        run_process(config)


if __name__ == '__main__':
    main()
