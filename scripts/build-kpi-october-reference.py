"""Build and verify the read-only 31 October 2026 KPI source snapshots.

Usage: python scripts/build-kpi-october-reference.py <source-directory> [--check]
Inspect each supplied workbook with MarkItDown first. This script reads cached
Excel results and original formats/formulas; it never recalculates or saves a
source workbook. --check verifies existing snapshots without rewriting them.
"""
import argparse
import hashlib
import json
import subprocess
import xml.etree.ElementTree as ET
import zipfile
from collections import Counter
from pathlib import Path

from openpyxl import load_workbook
from openpyxl.utils import get_column_letter

ROOT = Path(__file__).resolve().parents[1]
BRANCHES = {"MADIUN", "MAGETAN", "NGAWI", "PONOROGO"}
SOURCES = {
    "mbm": ("KPI Mbm.xlsx", "KPI MBM", 3, 4, 0, 1, {"branch": "A", "name": "B"}),
    "rm-mikro": ("KPI RM Kur Kecil.xlsx", "KPI RM Mikro", 1, 2, 2, 0,
                 {"branch": "C", "name": "A", "pn": "B", "office": "D"}),
    "ka-unit": ("KPI Kaunit.xlsx", "KPI Kaunit", 3, 4, 0, 3,
                {"branch": "A", "mbm": "B", "office_code": "C", "office": "D"}),
    "mantri": ("KPI Mantri Tumbuh.xlsx", "KPI Mantri Tumbuh", 1, 2, 1, 7,
               {"branch": "B", "mbm": "C", "office": "D", "office_code": "F", "name": "H", "pn": "I"}),
}
FORMATTER = r'''
require 'vendor/autoload.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$rows = [];
foreach ($input['values'] as $r => $row) {
    $rows[] = array_map(static function ($value, $format) {
        if ($value === null) return '';
        if (!is_numeric($value) || is_string($value)) return trim((string) $value);
        return trim(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::toFormattedString($value, $format));
    }, $row, $input['formats'][$r]);
}
echo json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
'''


def sha256(path):
    with path.open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


def branch_key(value):
    """Derive filter metadata only; original cell values stay untouched."""
    branch = str(value or "").strip().upper()
    return branch[3:] if branch.startswith("KC ") else branch


def formatted_rows(values, formats):
    result = subprocess.run(
        ["php", "-r", FORMATTER], cwd=ROOT,
        input=json.dumps({"values": values, "formats": formats}, allow_nan=False),
        text=True, encoding="utf-8", capture_output=True, check=True,
    )
    return json.loads(result.stdout)


def cached_empty_formula_results(source, sheet_name):
    """Distinguish Excel's cached empty strings from absent formula caches.

    openpyxl data_only returns None for both. An explicit empty <v> with
    type="str" is a cached empty result, such as IF(..., value, "").
    """
    namespace = "http://schemas.openxmlformats.org/spreadsheetml/2006/main"
    relationship_namespace = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
    with zipfile.ZipFile(source) as archive:
        workbook = ET.fromstring(archive.read("xl/workbook.xml"))
        relationships = ET.fromstring(archive.read("xl/_rels/workbook.xml.rels"))
        targets = {item.attrib["Id"]: item.attrib["Target"] for item in relationships}
        sheet = next(item for item in workbook.find(f"{{{namespace}}}sheets") if item.attrib["name"] == sheet_name)
        target = targets[sheet.attrib[f"{{{relationship_namespace}}}id"]]
        path = target.lstrip("/") if target.startswith("/") else "xl/" + target
        empty_results = set()
        with archive.open(path) as stream:
            for _, cell in ET.iterparse(stream, events=("end",)):
                if cell.tag != f"{{{namespace}}}c":
                    continue
                value = cell.find(f"{{{namespace}}}v")
                formula = cell.find(f"{{{namespace}}}f")
                if formula is not None and cell.attrib.get("t") == "str" and value is not None and value.text in (None, ""):
                    empty_results.add(cell.attrib["r"])
                cell.clear()
        return empty_results


def build_snapshot(directory, key, spec):
    filename, sheet_name, header_row, sub_row, bo_index, identity_index, identity_columns = spec
    source = directory / filename
    source_hash = sha256(source)
    cached_book = load_workbook(source, read_only=True, data_only=True)
    formula_book = load_workbook(source, read_only=True, data_only=False)
    cached_empty_cells = cached_empty_formula_results(source, sheet_name)
    try:
        cached_sheet = cached_book[sheet_name]
        formula_sheet = formula_book[sheet_name]
        width = max(cached_sheet.max_column, formula_sheet.max_column)
        cached_rows = list(cached_sheet.iter_rows(max_col=width))
        formula_rows = list(formula_sheet.iter_rows(max_col=width))
        raw_headers = [[cell.value for cell in cached_rows[n - 1]] for n in (header_row, sub_row)]
        headers = [["" if value is None else str(value).strip() for value in row] for row in raw_headers]
        if key in ("mbm", "ka-unit"):
            for index, weight in enumerate(headers[1]):
                if weight.startswith("BOBOT "):
                    headers[0][index] += " " + weight
                    headers[1][index] = "Pencapaian"
                    headers[1][index + 1] = "Score"

        row_numbers, values, formats, errors = [], [], [], []
        branches, row_branches, formulas, missing_caches, blank_results, excluded = Counter(), [], {}, [], [], []
        for number in range(sub_row + 1, len(cached_rows) + 1):
            cells = cached_rows[number - 1]
            source_cells = formula_rows[number - 1]
            branch_value = cells[bo_index].value
            identity = cells[identity_index].value
            if not branch_value and not identity:
                if any(cell.value is not None for cell in source_cells):
                    excluded.append(number)
                continue
            branch = branch_key(branch_value)
            if branch not in BRANCHES or not identity:
                raise ValueError(f"Unrecognised personnel row {sheet_name}!{number}: {branch!r}")
            row_numbers.append(number)
            values.append([cell.value for cell in cells])
            formats.append([cell.number_format for cell in cells])
            branches[branch] += 1
            row_branches.append(branch)
            for index, cell in enumerate(cells):
                coordinate = f"{get_column_letter(index + 1)}{number}"
                if cell.data_type == "e":
                    errors.append({"cell": coordinate, "value": cell.value})
                formula_cell = source_cells[index]
                if formula_cell.data_type == "f":
                    formulas[coordinate] = formula_cell.value
                    if coordinate in cached_empty_cells:
                        blank_results.append(coordinate)
                    elif cell.value is None:
                        missing_caches.append(coordinate)

        format_list = list(dict.fromkeys(value for row in formats for value in row))
        format_indexes = {value: index for index, value in enumerate(format_list)}
        snapshot = {
            "version": 1, "period": "2026-10", "as_of": "2026-10-31",
            "source": {"file": filename, "sha256": source_hash, "sheet": sheet_name},
            "header_rows": headers, "raw_header_rows": raw_headers,
            "source_header_rows": [header_row, sub_row],
            "source_columns": [get_column_letter(index + 1) for index in range(width)],
            "source_rows": row_numbers, "rows": formatted_rows(values, formats), "raw_rows": values,
            "number_formats": format_list,
            "format_ids": [[format_indexes[value] for value in row] for row in formats],
            "branches": dict(branches), "row_branches": row_branches,
            "bo_index": bo_index, "identity_index": identity_index, "identity_columns": identity_columns,
            "source_errors": errors, "source_formulas": formulas,
            "source_missing_formula_caches": missing_caches,
            "source_blank_formula_results": blank_results,
            "excluded_control_rows": excluded,
        }
    finally:
        cached_book.close()
        formula_book.close()
    if sha256(source) != source_hash:
        raise ValueError(f"Source changed while reading: {filename}")
    return snapshot


def verify_snapshot(directory, key, spec, snapshot):
    """Reopen the workbook and compare every exported cell and original format."""
    source = directory / spec[0]
    if snapshot["source"]["sha256"] != sha256(source):
        raise ValueError(f"Source hash mismatch: {source.name}")
    cached_book = load_workbook(source, read_only=True, data_only=True)
    formula_book = load_workbook(source, read_only=True, data_only=False)
    cached_empty_cells = cached_empty_formula_results(source, spec[1])
    try:
        width = len(snapshot["source_columns"])
        cached_rows = list(cached_book[spec[1]].iter_rows(max_col=width))
        formula_rows = list(formula_book[spec[1]].iter_rows(max_col=width))
        errors, formulas, missing, blank_results, branches, formats = [], {}, [], [], Counter(), []
        checks = 0
        for offset, number in enumerate(snapshot["source_rows"]):
            source_row = cached_rows[number - 1]
            branch = branch_key(source_row[spec[4]].value)
            if branch != snapshot["row_branches"][offset]:
                raise ValueError(f"Branch mismatch at source row {number}")
            branches[branch] += 1
            row_formats = []
            for column, cell in enumerate(source_row):
                coordinate = f"{get_column_letter(column + 1)}{number}"
                raw = snapshot["raw_rows"][offset][column]
                if type(raw) is not type(cell.value) or raw != cell.value:
                    raise ValueError(f"Raw source mismatch at {spec[1]}!{coordinate}")
                original_format = snapshot["number_formats"][snapshot["format_ids"][offset][column]]
                if original_format != cell.number_format:
                    raise ValueError(f"Format mismatch at {spec[1]}!{coordinate}")
                row_formats.append(cell.number_format)
                if cell.data_type == "e":
                    errors.append({"cell": coordinate, "value": cell.value})
                formula_cell = formula_rows[number - 1][column]
                if formula_cell.data_type == "f":
                    formulas[coordinate] = formula_cell.value
                    if coordinate in cached_empty_cells:
                        blank_results.append(coordinate)
                    elif cell.value is None:
                        missing.append(coordinate)
                checks += 1
            formats.append(row_formats)
        expected_personnel = [
            number for number, row in enumerate(cached_rows, 1)
            if number > spec[3] and row[spec[4]].value and row[spec[5]].value
        ]
        if expected_personnel != snapshot["source_rows"]:
            raise ValueError(f"Personnel coverage mismatch: {key}")
        for index, number in enumerate(snapshot["source_header_rows"]):
            if snapshot["raw_header_rows"][index] != [cell.value for cell in cached_rows[number - 1]]:
                raise ValueError(f"Header mismatch at {spec[1]}!{number}")
        if (dict(branches), errors, formulas, missing, blank_results) != (
            snapshot["branches"], snapshot["source_errors"], snapshot["source_formulas"],
            snapshot["source_missing_formula_caches"], snapshot["source_blank_formula_results"],
        ):
            raise ValueError(f"Source quality metadata mismatch: {key}")
        if formatted_rows(snapshot["raw_rows"], formats) != snapshot["rows"]:
            raise ValueError(f"Formatted display mismatch: {key}")
    finally:
        cached_book.close()
        formula_book.close()
    return {"key": key, "rows": len(snapshot["rows"]), "columns": width,
            "cell_checks": checks, "differences": 0, "branches": dict(branches),
            "formula_cells": len(formulas), "errors": len(errors),
            "missing_formula_caches": len(missing), "blank_formula_results": len(blank_results),
            "identity_columns": snapshot["identity_columns"]}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("source_directory", type=Path)
    parser.add_argument("--output-dir", type=Path, default=ROOT / "resources/data")
    parser.add_argument("--check", action="store_true", help="Verify existing snapshots without rewriting them")
    args = parser.parse_args()
    reports = []
    for key, spec in SOURCES.items():
        destination = args.output_dir / f"kpi-{key}-2026-10.json"
        if args.check:
            snapshot = json.loads(destination.read_text(encoding="utf-8"))
        else:
            snapshot = build_snapshot(args.source_directory, key, spec)
            destination.write_text(json.dumps(snapshot, ensure_ascii=False, separators=(",", ":"), allow_nan=False) + "\n", encoding="utf-8")
            snapshot = json.loads(destination.read_text(encoding="utf-8"))
        reports.append(verify_snapshot(args.source_directory, key, spec, snapshot))
    print(json.dumps(reports, ensure_ascii=False))


if __name__ == "__main__":
    main()
