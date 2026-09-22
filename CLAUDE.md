# Claude Code Guidelines for Project ABAH

See `AGENTS.md` for full repository rules and coding principles.

## Document Handling & Token Optimization Rule
When asked to inspect, read, review, or analyze Office documents (`.xlsx`, `.xls`, `.docx`, `.pptx`), PDF files, or HTML dumps:
- **DO NOT** attempt to read or dump raw files into context.
- **ALWAYS** convert to token-optimized Markdown first:
  ```bash
  python scripts/markitdown_token_optimizer.py <file_path>
  ```
  Or:
  ```bash
  php artisan doc:markdown <file_path>
  ```
- Or use the slash command: `/convert-doc <file_path>`
- Use the converted Markdown content as your context to save up to 60-90% tokens.
