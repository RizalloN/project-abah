---
description: Convert office documents (Excel, Word, PPTX), PDF, or HTML to token-optimized Markdown to save LLM context window tokens.
argument-hint: "<file_path> [--clipboard] [--output <path>]"
allowed-tools: Bash(python scripts/markitdown_token_optimizer.py*), Bash(php artisan doc:markdown*)
---

You are converting an Office document, PDF, or HTML file into token-optimized Markdown for AI models (Claude, Gemini, Codex).

### Flow:
1. When asked to inspect, analyze, summarize, or extract data from a document (`.xlsx`, `.docx`, `.pptx`, `.pdf`, `.html`, `.csv`), do NOT read raw binary files.
2. Run the optimizer:
   ```bash
   python scripts/markitdown_token_optimizer.py "$ARGUMENTS"
   ```
   Or via Artisan:
   ```bash
   php artisan doc:markdown "$ARGUMENTS"
   ```
3. Use the resulting Markdown in your context.
4. If the user wants it copied to their clipboard, pass `-c` or `--clipboard`.
5. If the user wants it saved to a file, pass `-o <path.md>`.
