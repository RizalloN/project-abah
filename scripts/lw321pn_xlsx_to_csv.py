import argparse
import csv
import json
import os
import re
import sys
import zipfile
from datetime import datetime, timedelta
from datetime import date
from xml.etree import ElementTree as ET

DATE_HEADERS = {
    "PERIODE",
    "NEXT_PMT_DATE",
    "NEXT_INT_PMT_DATE",
    "TGL_MENUNGGAK",
    "TGL_REALISASI",
    "TGL JATUH TEMPO",
}

REQUIRED_HEADERS = {
    "PERIODE",
    "KODE_KANWIL",
    "KANWIL",
    "KODE_KANCA",
    "KANCA",
    "KODE_UKER",
    "UKER",
    "CURRENCY",
    "LN_TYPE",
    "NOMOR_REKENING",
    "NAMA_DEBITUR",
    "PLAFON",
    "NEXT_PMT_DATE",
    "NEXT_INT_PMT_DATE",
    "RATE",
    "TGL_MENUNGGAK",
    "TGL_REALISASI",
    "TGL_JATUH_TEMPO",
    "JANGKA_WAKTU",
    "FLAG_RESTRUK",
    "CIFNO",
    "KOLEKTIBILITAS_LANCAR",
    "KOLEKTIBILITAS_DPK",
    "KOLEKTIBILITAS_KURANG_LANCAR",
    "KOLEKTIBILITAS_DIRAGUKAN",
    "KOLEKTIBILITAS_MACET",
    "TUNGGAKAN_POKOK",
    "TUNGGAKAN_BUNGA",
    "TUNGGAKAN_PINALTI",
    "FREQ_PAYMENT",
    "FREQ_INT_PAYMENT",
    "CODE",
    "DESCRIPTION",
    "SEGMEN_LV1",
    "DESC_SEGMEN_LV1",
    "KOL_ADK",
    "PN_PENGELOLA_SINGLEPN",
    "PN_PENGELOLA_1",
    "PN_PEMRAKARSA",
    "PN_REFERRAL",
    "PN_RESTRUK",
    "PN_PENGELOLA_2",
    "PN_PEMUTUS",
    "PN_CRM",
    "PN_RM_REFERRAL_NAIK_SEGMENTASI",
    "PN_RM_CRR",
    "PLAFON_DALAM_IDR",
    "BALANCE_DALAM_IDR",
}

SHARED_PREFIX = "__SST__"


def emit(payload):
    print(json.dumps(payload, ensure_ascii=False), flush=True)


def normalize_header(value):
    return re.sub(r"[^A-Z0-9]+", "_", str(value or "").strip().upper()).strip("_")


def validate_headers(headers):
    normalized = {normalize_header(header) for header in headers if str(header or "").strip()}
    missing = sorted(REQUIRED_HEADERS - normalized)
    if missing:
        raise RuntimeError(
            "Schema sumber LW321PN tidak lengkap. Kolom yang hilang: " + ", ".join(missing)
        )


def worksheet_path(archive):
    preferred = "xl/worksheets/sheet1.xml"
    if preferred in archive.namelist():
        return preferred

    resolved = next(
        (name for name in archive.namelist() if name.startswith("xl/worksheets/sheet") and name.endswith(".xml")),
        None,
    )
    if resolved is None:
        raise RuntimeError("Worksheet XLSX tidak ditemukan.")

    return resolved


def load_shared_strings(archive):
    path = "xl/sharedStrings.xml"
    if path not in archive.namelist():
        return {}

    values = {}
    with archive.open(path) as handle:
        index = -1
        for event, elem in ET.iterparse(handle, events=("end",)):
            if not elem.tag.endswith("}si"):
                continue

            index += 1
            values[index] = "".join(
                text_node.text or "" for text_node in elem.iter() if text_node.tag.endswith("}t")
            )
            elem.clear()

    return values


def worksheet_max_row(archive, sheet_path):
    with archive.open(sheet_path) as handle:
        for event, elem in ET.iterparse(handle, events=("start",)):
            if not elem.tag.endswith("}dimension"):
                continue

            ref = elem.attrib.get("ref", "")
            match = re.search(r"[A-Z]+(\d+)$", ref.split(":")[-1].upper())
            return int(match.group(1)) if match else 0

    return 0


def row_values(row, shared_map):
    values = []
    for cell in row:
        if not cell.tag.endswith("}c"):
            continue

        ref = cell.attrib.get("r", "")
        index = column_index(ref)
        while len(values) <= index:
            values.append("")
        values[index] = read_cell_value(cell)

    return resolve_shared_values(values, shared_map)


def column_index(cell_ref):
    letters = re.sub(r"[^A-Z]", "", cell_ref.upper())
    index = 0
    for char in letters:
        index = index * 26 + (ord(char) - 64)
    return max(0, index - 1)


def excel_serial_to_date(value):
    try:
        serial = float(value)
    except (TypeError, ValueError):
        return value

    if serial < 20000 or serial > 80000:
        return value

    # Excel's Windows date system includes the 1900 leap-year bug.
    date_value = datetime(1899, 12, 30) + timedelta(days=serial)
    return date_value.strftime("%d/%m/%Y")


def shared_string_at(shared_strings_path, wanted_indexes):
    if not wanted_indexes:
        return {}

    wanted = set(wanted_indexes)
    max_wanted = max(wanted)
    resolved = {}

    with zipfile.ZipFile(shared_strings_path.filename) as archive:
        with archive.open("xl/sharedStrings.xml") as handle:
            index = -1
            for event, elem in ET.iterparse(handle, events=("end",)):
                if elem.tag.endswith("}si"):
                    index += 1
                    if index in wanted:
                        texts = [text_node.text or "" for text_node in elem.iter() if text_node.tag.endswith("}t")]
                        resolved[index] = "".join(texts)
                    elem.clear()
                    if index >= max_wanted and wanted.issubset(resolved.keys()):
                        break

    return resolved


def read_cell_value(cell):
    cell_type = cell.attrib.get("t", "")

    if cell_type == "inlineStr":
        texts = [text_node.text or "" for text_node in cell.iter() if text_node.tag.endswith("}t")]
        return "".join(texts)

    value_node = None
    for child in cell:
        if child.tag.endswith("}v"):
            value_node = child
            break

    raw = "" if value_node is None or value_node.text is None else value_node.text
    if cell_type == "s" and raw != "":
        return f"{SHARED_PREFIX}{raw}"

    return raw


def resolve_shared_values(values, shared_map):
    resolved = []
    for value in values:
        if isinstance(value, str) and value.startswith(SHARED_PREFIX):
            try:
                resolved.append(shared_map.get(int(value[len(SHARED_PREFIX):]), ""))
            except ValueError:
                resolved.append("")
        else:
            resolved.append(value)
    return resolved


def fast_preview_xlsx(args):
    try:
        fast_preview_xlsx_fastexcel(args)
        return
    except ImportError:
        pass

    header_row = None
    headers = []
    preview_rows = []
    unique_values = {}
    shared_indexes = set()
    pending_rows = []

    with zipfile.ZipFile(args.input) as archive:
        sheet_name = worksheet_path(archive)

        with archive.open(sheet_name) as handle:
            for event, row in ET.iterparse(handle, events=("end",)):
                if not row.tag.endswith("}row"):
                    continue

                row_number = int(row.attrib.get("r", "0") or 0)
                values = []
                for cell in row:
                    if not cell.tag.endswith("}c"):
                        continue
                    ref = cell.attrib.get("r", "")
                    index = column_index(ref)
                    while len(values) <= index:
                        values.append("")
                    value = read_cell_value(cell)
                    if isinstance(value, str) and value.startswith(SHARED_PREFIX):
                        try:
                            shared_indexes.add(int(value[len(SHARED_PREFIX):]))
                        except ValueError:
                            pass
                    values[index] = value

                pending_rows.append((row_number, values))
                row.clear()

                if header_row is not None and len(preview_rows) >= args.preview_limit:
                    break

                if header_row is None:
                    continue

                if len(preview_rows) < args.preview_limit and any(str(value).strip() for value in values):
                    preview_rows.append(values)

        shared_map = {}
        if "xl/sharedStrings.xml" in archive.namelist() and shared_indexes:
            with archive.open("xl/sharedStrings.xml") as handle:
                index = -1
                max_index = max(shared_indexes)
                for event, elem in ET.iterparse(handle, events=("end",)):
                    if elem.tag.endswith("}si"):
                        index += 1
                        if index in shared_indexes:
                            texts = [text_node.text or "" for text_node in elem.iter() if text_node.tag.endswith("}t")]
                            shared_map[index] = "".join(texts)
                        elem.clear()
                        if index >= max_index and shared_indexes.issubset(shared_map.keys()):
                            break

    resolved_rows = [(row_number, resolve_shared_values(values, shared_map)) for row_number, values in pending_rows]
    header_row = None
    preview_rows = []
    for row_number, values in resolved_rows:
        upper = [str(value).strip().upper() for value in values]
        if header_row is None:
            if "PERIODE" in upper and "NOMOR_REKENING" in upper:
                header_row = row_number
                headers = [
                    str(value).strip() if value and str(value).strip() else f"COL_{index}"
                    for index, value in enumerate(values)
                ]
                validate_headers(headers)
                emit({
                    "type": "progress",
                    "percent": 65,
                    "message": "Header LW321PN ditemukan. Menyiapkan sampel preview...",
                    "header_row": header_row,
                    "headers": len(headers),
                })
            continue

        if len(preview_rows) >= args.preview_limit:
            break
        if any(str(value).strip() for value in values):
            normalized = (values + [""] * len(headers))[:len(headers)]
            for index, header in enumerate(headers):
                if str(header).strip().upper() in DATE_HEADERS:
                    normalized[index] = excel_serial_to_date(normalized[index])
            preview_rows.append(normalized)

    if header_row is None:
        raise RuntimeError("Header LW321PN tidak ditemukan. Pastikan file memuat PERIODE dan NOMOR_REKENING.")

    for normalized in preview_rows:
        for index, value in enumerate(normalized):
            value = str(value).strip()
            if value == "":
                value = "(Blank)"
            bucket = unique_values.setdefault(index, {})
            if len(bucket) < 75:
                bucket[value] = True

    emit({
        "type": "done",
        "output": None,
        "header_index": 0,
        "source_header_row": header_row,
        "headers": headers,
        "preview_rows": preview_rows,
        "unique_values": {str(index): list(values.keys()) for index, values in unique_values.items()},
        "total_rows": len(preview_rows),
    })


def preview_string(value):
    if value is None:
        return ""
    if isinstance(value, datetime):
        return value.strftime("%d/%m/%Y")
    if isinstance(value, date):
        return value.strftime("%d/%m/%Y")
    return str(value)


def fast_preview_xlsx_fastexcel(args):
    import fastexcel

    scan_rows = max(200, args.preview_limit + 20)
    reader = fastexcel.read_excel(args.input)
    sheet = reader.load_sheet_by_idx(
        0,
        header_row=None,
        n_rows=scan_rows,
        schema_sample_rows=min(scan_rows, 100),
        dtype_coercion="coerce",
    )
    frame = sheet.to_polars()
    rows = [list(row) for row in frame.iter_rows()]

    header_offset = None
    headers = []
    for index, row in enumerate(rows):
        values = [preview_string(value) for value in row]
        upper = [value.strip().upper() for value in values]
        if "PERIODE" in upper and "NOMOR_REKENING" in upper:
            header_offset = index
            headers = [
                value.strip() if value and value.strip() else f"COL_{column_index}"
                for column_index, value in enumerate(values)
            ]
            validate_headers(headers)
            break

    if header_offset is None:
        raise RuntimeError("Header LW321PN tidak ditemukan. Pastikan file memuat PERIODE dan NOMOR_REKENING.")

    emit({
        "type": "progress",
        "percent": 65,
        "message": "Header LW321PN ditemukan. Menyiapkan sampel preview...",
        "header_row": header_offset + 1,
        "headers": len(headers),
    })

    preview_rows = []
    unique_values = {}
    for row in rows[header_offset + 1:]:
        if len(preview_rows) >= args.preview_limit:
            break

        normalized = [preview_string(value) for value in row]
        normalized = (normalized + [""] * len(headers))[:len(headers)]
        if not any(str(value).strip() for value in normalized):
            continue

        for index, header in enumerate(headers):
            if str(header).strip().upper() in DATE_HEADERS:
                normalized[index] = excel_serial_to_date(normalized[index])

        preview_rows.append(normalized)

        for index, value in enumerate(normalized):
            value = str(value).strip()
            if value == "":
                value = "(Blank)"
            bucket = unique_values.setdefault(index, {})
            if len(bucket) < 75:
                bucket[value] = True

    emit({
        "type": "done",
        "output": None,
        "header_index": 0,
        "source_header_row": header_offset + 1,
        "headers": headers,
        "preview_rows": preview_rows,
        "unique_values": {str(index): list(values.keys()) for index, values in unique_values.items()},
        "total_rows": max(0, int(sheet.total_height or 0) - (header_offset + 1)),
    })


def stream_xlsx_to_csv_fastexcel(args):
    import fastexcel
    import polars as pl

    temporary_output = args.output + ".fastexcel"

    try:
        reader = fastexcel.read_excel(args.input)
        sheet = reader.load_sheet_by_idx(
            0,
            header_row=None,
            schema_sample_rows=1000,
            dtype_coercion="coerce",
        )
        frame = sheet.to_polars()

        header_index = None
        headers = []
        for row_index, row in enumerate(frame.head(64).iter_rows()):
            upper = [str(value or "").strip().upper() for value in row]
            if "PERIODE" not in upper or "NOMOR_REKENING" not in upper:
                continue

            header_index = row_index
            headers = [
                str(value).strip() if str(value or "").strip() else f"COL_{index}"
                for index, value in enumerate(row)
            ]
            validate_headers(headers)
            break

        if header_index is None:
            raise RuntimeError("Header LW321PN tidak ditemukan. Pastikan file memuat PERIODE dan NOMOR_REKENING.")

        emit({
            "type": "progress",
            "percent": 45,
            "message": "Header LW321PN ditemukan. Menulis CSV staging cepat...",
            "header_row": header_index + 1,
            "headers": len(headers),
        })

        data = frame.slice(header_index + 1)
        data.columns = headers
        if data.height > 0:
            non_blank_row = pl.any_horizontal(*[
                pl.col(column).cast(pl.String).fill_null("").str.strip_chars().ne("")
                for column in headers
            ])
            data = data.filter(non_blank_row)

        emit({
            "type": "progress",
            "percent": 75,
            "message": "Menulis CSV staging LW321PN dengan fast reader...",
            "rows": data.height,
        })
        data.write_csv(temporary_output, include_header=True)
        os.replace(temporary_output, args.output)
    finally:
        if os.path.exists(temporary_output):
            os.unlink(temporary_output)

    emit({
        "type": "done",
        "output": args.output,
        "header_index": 0,
        "source_header_row": header_index + 1,
        "headers": headers,
        "preview_rows": [],
        "unique_values": {},
        "total_rows": data.height,
    })


def stream_xlsx_to_csv_xml(args):
    header_row = None
    headers = []
    rows_written = 0

    with zipfile.ZipFile(args.input) as archive:
        sheet_name = worksheet_path(archive)
        max_row = worksheet_max_row(archive, sheet_name)
        shared_map = load_shared_strings(archive)

        with archive.open(sheet_name) as sheet_handle, open(
            args.output,
            "w",
            newline="",
            encoding="utf-8",
        ) as output_handle:
            writer = csv.writer(output_handle)

            for event, row in ET.iterparse(sheet_handle, events=("end",)):
                if not row.tag.endswith("}row"):
                    continue

                row_number = int(row.attrib.get("r", "0") or 0)
                values = row_values(row, shared_map)
                upper = [str(value).strip().upper() for value in values]

                if header_row is None:
                    if "PERIODE" in upper and "NOMOR_REKENING" in upper:
                        header_row = row_number
                        headers = [
                            str(value).strip() if value and str(value).strip() else f"COL_{index}"
                            for index, value in enumerate(values)
                        ]
                        validate_headers(headers)
                        writer.writerow(headers)
                        emit({
                            "type": "progress",
                            "percent": 45,
                            "message": "Header LW321PN ditemukan. Menulis CSV staging streaming...",
                            "header_row": header_row,
                            "headers": len(headers),
                        })
                    row.clear()
                    continue

                normalized = (values + [""] * len(headers))[:len(headers)]
                if not any(str(value).strip() for value in normalized):
                    row.clear()
                    continue

                for index, header in enumerate(headers):
                    if str(header).strip().upper() in DATE_HEADERS:
                        normalized[index] = excel_serial_to_date(normalized[index])

                writer.writerow(normalized)
                rows_written += 1

                if rows_written % max(1, args.progress_every) == 0:
                    if max_row > header_row:
                        denominator = max(max_row - header_row, 1)
                        percent = min(82, 45 + int(rows_written / denominator * 37))
                    else:
                        completed_batches = rows_written // max(1, args.progress_every)
                        percent = min(80, 45 + (completed_batches * 4))

                    emit({
                        "type": "progress",
                        "percent": percent,
                        "message": f"Menulis CSV staging LW321PN... {rows_written} baris",
                        "rows": rows_written,
                    })

                row.clear()

    if header_row is None:
        raise RuntimeError("Header LW321PN tidak ditemukan. Pastikan file memuat PERIODE dan NOMOR_REKENING.")

    emit({
        "type": "done",
        "output": args.output,
        "header_index": 0,
        "source_header_row": header_row,
        "headers": headers,
        "preview_rows": [],
        "unique_values": {},
        "total_rows": rows_written,
    })


def stream_xlsx_to_csv(args):
    try:
        stream_xlsx_to_csv_fastexcel(args)
        return
    except ImportError:
        pass
    except Exception:
        # Parser XML lama tetap menjadi fallback untuk workbook yang tidak
        # didukung penuh oleh fast reader, tanpa mengurangi kontrak data.
        pass

    stream_xlsx_to_csv_xml(args)


def main():
    parser = argparse.ArgumentParser(description="Stream LW321PN XLSX to CSV staging.")
    parser.add_argument("--input", required=True)
    parser.add_argument("--output")
    parser.add_argument("--preview-only", action="store_true")
    parser.add_argument("--preview-limit", type=int, default=75)
    parser.add_argument("--progress-every", type=int, default=25000)
    args = parser.parse_args()

    if not args.preview_only:
        if not args.output:
            raise RuntimeError("--output wajib diisi untuk mode staging penuh.")
        os.makedirs(os.path.dirname(args.output), exist_ok=True)
    else:
        fast_preview_xlsx(args)
        return

    stream_xlsx_to_csv(args)


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        emit({"type": "error", "message": str(exc)})
        sys.exit(1)
