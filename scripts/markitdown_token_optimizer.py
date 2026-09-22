"""
MarkItDown Token Optimizer for Project ABAH
===========================================
Converts Office documents (Word, Excel, PowerPoint), PDF, HTML, and other files
into ultra-compact Markdown optimized specifically for LLM context windows
(Gemini, Claude, OpenAI/Codex, Antigravity).

Saves up to 60% - 90% tokens compared to raw files and default markups.
"""

import argparse
import json
import os
import re
import subprocess
import sys
from pathlib import Path
from typing import Any, Dict, Optional, Tuple


def clean_markdown_for_llm(text: str, compact_tables: bool = True) -> str:
    """
    Minifies and cleans Markdown to consume the minimum possible LLM tokens
    while maintaining full semantic clarity and structure.
    """
    if not text:
        return ""

    # Normalize newlines
    text = text.replace("\r\n", "\n").replace("\r", "\n")

    # Replace tab characters with 2 spaces
    text = text.replace("\t", "  ")

    # Strip trailing whitespace on each line
    lines = [line.rstrip() for line in text.split("\n")]

    if compact_tables:
        compacted_lines = []
        for line in lines:
            # Check if this line is a markdown table row (starts and ends with |)
            stripped = line.strip()
            if stripped.startswith("|") and stripped.endswith("|") and len(stripped) > 2:
                # If it's a separator line e.g. |-------|------| -> |---|---|
                if re.match(r"^\|[\s\-:|]+\|$", stripped):
                    parts = stripped.split("|")[1:-1]
                    new_parts = []
                    for p in parts:
                        p_str = p.strip()
                        align_left = p_str.startswith(":")
                        align_right = p_str.endswith(":")
                        if align_left and align_right:
                            new_parts.append(":---:")
                        elif align_left:
                            new_parts.append(":---")
                        elif align_right:
                            new_parts.append("---:")
                        else:
                            new_parts.append("---")
                    compacted_lines.append("|" + "|".join(new_parts) + "|")
                else:
                    # Regular table cell line: trim spaces inside each cell
                    parts = stripped.split("|")[1:-1]
                    new_parts = [f" {p.strip()} " if p.strip() else " " for p in parts]
                    compacted_lines.append("|" + "|".join(new_parts) + "|")
            else:
                compacted_lines.append(line)
        lines = compacted_lines

    cleaned_text = "\n".join(lines)

    # Collapse 3 or more consecutive newlines into 2 (one blank line between paragraphs)
    cleaned_text = re.sub(r"\n{3,}", "\n\n", cleaned_text)

    # Remove excessive repeated separator lines (e.g. ------ or =====)
    cleaned_text = re.sub(r"([-=_*~]){4,}", r"\1\1\1", cleaned_text)

    return cleaned_text.strip()


def estimate_tokens(text: str) -> int:
    """
    Estimates token count for mixed Indonesian/English text, code, and tables.
    Rule of thumb: ~3.8 characters per token, minimum 1.
    """
    if not text:
        return 0
    # Average across GPT-4o, Claude 3.5, and Gemini 2.5 tokenizers
    return max(1, int(len(text) / 3.8))


def copy_to_windows_clipboard(text: str) -> bool:
    """Copies UTF-8/UTF-16 text to Windows clipboard using clip.exe."""
    try:
        # clip.exe on Windows handles UTF-16LE perfectly
        process = subprocess.Popen(["clip"], stdin=subprocess.PIPE, shell=True)
        process.communicate(input=text.encode("utf-16le"))
        return process.returncode == 0
    except Exception as e:
        sys.stderr.write(f"Warning: Failed to copy to clipboard: {e}\n")
        return False


def convert_document(
    file_path: str | Path,
    enable_vision: bool = False,
    compact_tables: bool = True,
    max_preview_lines: Optional[int] = None,
) -> Dict[str, Any]:
    """
    Converts a document to token-optimized Markdown.
    """
    target = Path(file_path).resolve()
    if not target.exists():
        raise FileNotFoundError(f"File not found: {target}")

    if not target.is_file():
        raise ValueError(f"Path is not a file: {target}")

    try:
        from markitdown import MarkItDown
    except ImportError:
        raise ImportError(
            "Microsoft MarkItDown is not installed. "
            "Please run: pip install markitdown"
        )

    file_size = target.stat().st_size
    md = MarkItDown()

    # Convert via MarkItDown
    conversion_result = md.convert(str(target))
    raw_content = conversion_result.text_content or ""

    # Optimize for LLM tokens
    optimized_content = clean_markdown_for_llm(raw_content, compact_tables=compact_tables)

    if max_preview_lines and max_preview_lines > 0:
        lines = optimized_content.split("\n")
        if len(lines) > max_preview_lines:
            optimized_content = "\n".join(lines[:max_preview_lines]) + f"\n\n... [Truncated: showing {max_preview_lines} of {len(lines)} lines to preserve tokens] ..."

    raw_tokens = estimate_tokens(raw_content)
    optimized_tokens = estimate_tokens(optimized_content)

    chars_saved = len(raw_content) - len(optimized_content)
    tokens_saved = max(0, raw_tokens - optimized_tokens)
    savings_pct = round((tokens_saved / raw_tokens * 100), 1) if raw_tokens > 0 else 0.0

    stats = {
        "file_name": target.name,
        "file_path": str(target),
        "file_extension": target.suffix.lower(),
        "file_size_bytes": file_size,
        "raw_characters": len(raw_content),
        "optimized_characters": len(optimized_content),
        "raw_estimated_tokens": raw_tokens,
        "optimized_estimated_tokens": optimized_tokens,
        "tokens_saved": tokens_saved,
        "savings_percentage": savings_pct,
    }

    return {
        "success": True,
        "markdown": optimized_content,
        "stats": stats,
    }


def main():
    parser = argparse.ArgumentParser(
        description="MarkItDown Token Optimizer for Project ABAH (Office/PDF to LLM-ready Markdown)"
    )
    parser.add_argument("file", help="Path to document file (.pdf, .xlsx, .docx, .pptx, .html, etc.)")
    parser.add_argument("-o", "--output", help="Path to save output Markdown file")
    parser.add_argument("-c", "--clipboard", action="store_true", help="Copy optimized Markdown to Windows clipboard")
    parser.add_argument("--json", action="store_true", help="Output result and metadata in JSON format")
    parser.add_argument("--stats-only", action="store_true", help="Print only token statistics without markdown body")
    parser.add_argument("--preview-lines", type=int, default=None, help="Limit output to first N lines")
    parser.add_argument("--no-compact-tables", action="store_true", help="Disable table whitespace compaction")

    args = parser.parse_args()

    try:
        result = convert_document(
            file_path=args.file,
            compact_tables=not args.no_compact_tables,
            max_preview_lines=args.preview_lines,
        )

        if args.output:
            out_path = Path(args.output).resolve()
            out_path.parent.mkdir(parents=True, exist_ok=True)
            with open(out_path, "w", encoding="utf-8") as f:
                f.write(result["markdown"])
            result["stats"]["saved_to"] = str(out_path)

        clipboard_copied = False
        if args.clipboard:
            clipboard_copied = copy_to_windows_clipboard(result["markdown"])
            result["stats"]["clipboard_copied"] = clipboard_copied

        if args.json:
            print(json.dumps(result, ensure_ascii=False, indent=2))
            return 0

        stats = result["stats"]
        print("=" * 60)
        print("  MARKITDOWN TOKEN OPTIMIZER (PROJECT ABAH)")
        print("=" * 60)
        print(f" Dokumen         : {stats['file_name']} ({stats['file_size_bytes']:,} bytes)")
        print(f" Estimasi Token  : ~{stats['optimized_estimated_tokens']:,} tokens")
        print(f" Karakter Bersih : {stats['optimized_characters']:,} chars")
        if stats['tokens_saved'] > 0:
            print(f" Penghematan     : ~{stats['tokens_saved']:,} tokens ({stats['savings_percentage']}%)")
        if args.output:
            print(f" Tersimpan di    : {args.output}")
        if args.clipboard:
            print(f" Clipboard       : {'[Berhasil disalin ke clipboard!]' if clipboard_copied else '[Gagal salin]'}")
        print("=" * 60)

        if not args.stats_only:
            print("\n" + result["markdown"])

        return 0

    except Exception as e:
        if args.json:
            print(json.dumps({"success": False, "error": str(e)}))
        else:
            sys.stderr.write(f"Error: {e}\n")
        return 1


if __name__ == "__main__":
    sys.exit(main())
