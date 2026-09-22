# Codex Guidelines for Project ABAH

See `AGENTS.md` for core repository guidelines and safety rules.

## Mandatory Token Optimization Rule (MarkItDown)
When analyzing spreadsheets, presentations, word files, or PDF documents in this repository:
- **Target Extensions**: `.xlsx`, `.xls`, `.docx`, `.pptx`, `.pdf`, `.html`, `.csv`
- **Rule**: NEVER parse raw binary/large XML structures into the Codex conversation context. Doing so wastes thousands of tokens.
- **Action**: Always run the token optimizer first:
  ```bash
  python scripts/markitdown_token_optimizer.py <path_to_file>
  ```
  Or via Laravel Artisan:
  ```bash
  php artisan doc:markdown <path_to_file>
  ```
- If the user requests clipboard copy: pass `--clipboard` (`-c`).
- If the user requests file output: pass `-o <path.md>`.
- Use the resulting Markdown as your context for analysis and code generation.
