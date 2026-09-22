# GitHub Copilot Instructions for Project ABAH

## Role & Mission
You are an expert AI assistant specialized in Project ABAH (Laravel & Python data platform).

## Critical Token Optimization Guideline (MarkItDown)
When the user asks you to read, analyze, summarize, or extract data from any document or spreadsheet:
- **Files**: `.xlsx`, `.xls`, `.docx`, `.pptx`, `.pdf`, `.html`, `.csv`
- **DO NOT** attempt to parse raw XML/binary structures or tell the user you cannot read binary files.
- **Instruct or Execute MarkItDown Conversion**:
  - Recommend or run:
    ```bash
    python scripts/markitdown_token_optimizer.py <path-to-file>
    ```
    or
    ```bash
    php artisan doc:markdown <path-to-file>
    ```
  - Use the flag `--clipboard` (`-c`) if the user wants to copy the result to clipboard.
  - Use `-o <path.md>` to save to a file.
  - Base all subsequent analysis, calculations, and answers on the generated token-optimized Markdown.
