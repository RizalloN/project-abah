#!/usr/bin/env python
import argparse
import json
import os
import sys

import polars as pl


REQUIRED_HEADERS = [
    "Periode",
    "KODE",
    "Pendapatan Koreksi PPAP-dr Angsuran PH",
]


def send(event_type, **payload):
    payload["type"] = event_type
    print(json.dumps(payload, ensure_ascii=False), flush=True)


def normalize_header(value):
    return " ".join(str(value or "").replace("\n", " ").split()).strip()


def load_config(path):
    with open(path, "r", encoding="utf-8") as handle:
        return json.load(handle)


def build_alias_map(headers):
    alias_map = {}
    for header in headers:
        normalized = normalize_header(header)
        alias_map[normalized.lower()] = header
    return alias_map


def find_header(alias_map, expected):
    return alias_map.get(normalize_header(expected).lower())


def main():
    parser = argparse.ArgumentParser(description="GI405 - Rec. DH CSV stage processor")
    parser.add_argument("--config", required=True)
    parser.add_argument("--mode", default="stage")
    args = parser.parse_args()

    config = load_config(args.config)
    file_path = config["file_path"]
    delimiter = config.get("delimiter", ",")
    output_csv_path = config["output_csv_path"]

    send("progress", percent=5, message="Membaca dan memvalidasi CSV GI405 - Rec. DH dengan Polars...", rows_done=0, total=0, speed=0, phase="polars", mode="polars")

    df = pl.read_csv(
        file_path,
        separator=delimiter,
        infer_schema_length=5000,
        ignore_errors=False,
        try_parse_dates=False,
    )

    header_map = build_alias_map(df.columns)
    resolved_headers = {}
    missing = []
    for expected in REQUIRED_HEADERS:
        resolved = find_header(header_map, expected)
        if not resolved:
            missing.append(expected)
        else:
            resolved_headers[expected] = resolved

    if missing:
        raise RuntimeError("Kolom wajib GI405 - Rec. DH tidak lengkap: " + ", ".join(missing))

    rename_map = {resolved_headers[key]: key for key in REQUIRED_HEADERS}
    df = df.rename(rename_map)

    df = df.with_columns([
        pl.col("KODE").cast(pl.Utf8).str.strip_chars().alias("KODE"),
        pl.col("Periode").cast(pl.Utf8).str.strip_chars().alias("Periode"),
        pl.col("Pendapatan Koreksi PPAP-dr Angsuran PH")
            .cast(pl.Utf8)
            .str.replace_all(r"[^0-9,\.\-\(\)]", "")
            .alias("Pendapatan Koreksi PPAP-dr Angsuran PH"),
    ])

    df = df.filter(
        pl.col("KODE").is_not_null()
        & (pl.col("KODE") != "")
        & pl.col("Periode").is_not_null()
        & (pl.col("Periode") != "")
    )

    if df.height == 0:
        raise RuntimeError("Polars tidak menemukan baris data GI405 - Rec. DH yang valid.")

    duplicate_pairs = (
        df.group_by(["Periode", "KODE"])
        .len()
        .filter(pl.col("len") > 1)
        .height
    )

    send("progress", percent=56, message="Sanitasi selesai. Menulis CSV bersih GI405 - Rec. DH...", rows_done=df.height, total=df.height, speed=0, phase="polars", mode="polars")

    os.makedirs(os.path.dirname(output_csv_path), exist_ok=True)
    df.write_csv(output_csv_path, separator=",")

    send(
        "done",
        written_rows=df.height,
        total_rows=df.height,
        skipped_rows=[],
        skipped_count=0,
        duplicate_count=duplicate_pairs,
        dates=df.select("Periode").unique().to_series().to_list(),
    )


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        send("error", message=str(exc))
        sys.exit(1)
