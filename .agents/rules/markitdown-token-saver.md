# Rule: Mandatory Document Conversion via MarkItDown (Antigravity & AI Agents)

## Scope
Applies to all tasks involving reading, inspecting, analyzing, summarizing, or extracting data from document files in `project-ABAH`.

## Background & Rationale
Raw Office documents (`.xlsx`, `.xls`, `.docx`, `.pptx`), PDF files, and HTML dumps contain massive formatting overhead, XML trees, or binary streams that rapidly deplete the LLM token budget.

## Execution Rules
1. **Never read raw binary documents directly into the prompt context.**
2. **Always convert using MarkItDown first:**
   - Execute:
     ```bash
     python scripts/markitdown_token_optimizer.py <path_to_file>
     ```
     Or via Laravel Artisan:
     ```bash
     php artisan doc:markdown <path_to_file>
     ```
3. **Use the optimized Markdown output** as the data source for reasoning, calculations, and answering user queries.
4. If the user wants the result in their clipboard to paste into another AI or chat window, use the `-c` / `--clipboard` flag.
5. If the document is an exceptionally large spreadsheet (>10,000 rows), use `--preview-lines 100` or use the dedicated Polars processor scripts in `scripts/`.
