# Codex Agent Instructions - Project ABAH

## Token Optimization (MarkItDown)
Whenever asked about documents (`.xlsx`, `.docx`, `.pptx`, `.pdf`, `.html`, `.csv`):
1. Do not inspect binary contents directly.
2. Run `python scripts/markitdown_token_optimizer.py <file>` or `php artisan doc:markdown <file>`.
3. Use the Markdown output for context and calculations.
