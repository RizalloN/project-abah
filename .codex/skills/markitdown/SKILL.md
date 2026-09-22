---
name: markitdown
description: Convert office documents (Excel .xlsx, Word .docx, PowerPoint .pptx), PDF, HTML, and CSV to token-minified Markdown. Use whenever inspecting or reading document files in project-ABAH to save 60-90% context window tokens in Codex.
---

# MarkItDown Token Optimizer for Codex (Project ABAH)

Converts heavy document formats into clean, structured Markdown optimized specifically for Codex context windows.

## Execution
```bash
# Python
python scripts/markitdown_token_optimizer.py <path_to_file>

# Laravel Artisan
php artisan doc:markdown <path_to_file>
```

Add `--clipboard` (`-c`) to copy directly to Windows clipboard.
Add `-o <path.md>` to save to a file.
