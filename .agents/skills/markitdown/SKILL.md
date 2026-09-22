---
name: markitdown
description: "Convert office documents (Excel .xlsx, Word .docx, PowerPoint .pptx), PDF, HTML, and CSV to ultra-compact Markdown. Use whenever inspecting or reading document files in project-ABAH to save 60-90% LLM tokens for Claude, Gemini, Codex, and Antigravity."
argument-hint: "[file_path]"
license: MIT
metadata:
  author: project-abah
  version: "1.0.0"
---

# MarkItDown Token Optimizer for Project ABAH

Converts heavy document formats into clean, structured Markdown optimized specifically for LLM context windows.

## When to Use
- User asks to inspect, summarize, analyze, or extract information from:
  - Excel files (`.xlsx`, `.xls`)
  - Word documents (`.docx`)
  - PowerPoint presentations (`.pptx`)
  - PDF files (`.pdf`)
  - HTML or web exports (`.html`)
  - CSV / TSV files
- User wants to save tokens when chatting with AI models (Gemini, Claude, Codex).

## How to Run

### Via Python Script:
```bash
# Print to stdout
python scripts/markitdown_token_optimizer.py path/to/document.xlsx

# Copy directly to Windows clipboard (for pasting into AI chat)
python scripts/markitdown_token_optimizer.py path/to/document.pdf --clipboard

# Save to a markdown file
python scripts/markitdown_token_optimizer.py path/to/document.docx -o output.md

# Get JSON output with token statistics
python scripts/markitdown_token_optimizer.py path/to/document.xlsx --json
```

### Via Laravel Artisan:
```bash
# Basic conversion
php artisan doc:markdown storage/app/laporan.xlsx

# With clipboard copy and token statistics
php artisan doc:markdown storage/app/laporan.pdf --clipboard --stats-only
```

### Programmatic Usage in Laravel (PHP):
```php
use App\Services\DocumentMarkdownService;

$service = app(DocumentMarkdownService::class);
$result = $service->convert('path/to/file.xlsx', [
    'clipboard' => true,
    'output' => 'path/to/output.md',
]);

if ($result['success']) {
    $markdown = $result['markdown'];
    $stats = $result['stats'];
    // $stats['optimized_estimated_tokens'], $stats['savings_percentage']
}
```
