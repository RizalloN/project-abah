# Domain: bank-pipeline

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=bank-pipeline --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 16 |
| method | 284 |
| unresolved_symbol | 21 |
| route | 24 |
| class | 16 |
| table | 2 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `DriveAsixFile` | class | 80 | `app/Models/DriveAsixFile.php:9` |
| `SpreadsheetWorkbookService` | class | 70 | `app/Services/DriveAsix/SpreadsheetWorkbookService.php:30` |
| `DriveAsixOfficeException` | class | 52 | `app/Exceptions/DriveAsixOfficeException.php:7` |
| `OnlyOfficeEditorService` | class | 49 | `app/Services/DriveAsix/OnlyOfficeEditorService.php:15` |
| `DriveAsixController` | class | 48 | `app/Http/Controllers/DriveAsixController.php:26` |
| `DriveAsixWorkbookException` | class | 40 | `app/Exceptions/DriveAsixWorkbookException.php:7` |
| `PipelineWorkbookSummaryService` | class | 33 | `app/Services/DriveAsix/PipelineWorkbookSummaryService.php:15` |
| `OnlyOfficeDocumentStorageService` | class | 31 | `app/Services/DriveAsix/OnlyOfficeDocumentStorageService.php:19` |
| `OfficeDocumentPreviewService` | class | 28 | `app/Services/DriveAsix/OfficeDocumentPreviewService.php:13` |
| `callback` | method | 28 | `app/Http/Controllers/DriveAsixOfficeController.php:181` |
| `OnlyOfficeJwtService` | class | 24 | `app/Services/DriveAsix/OnlyOfficeJwtService.php:7` |
| `editor` | method | 24 | `app/Http/Controllers/DriveAsixOfficeController.php:39` |
| `save` | method | 23 | `app/Services/DriveAsix/SpreadsheetWorkbookService.php:448` |
| `DriveAsixFolder` | class | 22 | `app/Models/DriveAsixFolder.php:9` |
| `applyOperation` | method | 21 | `app/Services/DriveAsix/SpreadsheetWorkbookService.php:1226` |
| `source` | method | 21 | `app/Http/Controllers/DriveAsixOfficeController.php:123` |
| `buildEditorConfig` | method | 19 | `app/Services/DriveAsix/OnlyOfficeEditorService.php:379` |
| `drive.index` | route | 18 | - |
| `previewPresentation` | method | 18 | `app/Services/DriveAsix/OfficeDocumentPreviewService.php:272` |
| `previewWordDocument` | method | 18 | `app/Services/DriveAsix/OfficeDocumentPreviewService.php:158` |
| `serializeSheet` | method | 18 | `app/Services/DriveAsix/SpreadsheetWorkbookService.php:901` |
| `upload` | method | 18 | `app/Http/Controllers/DriveAsixController.php:219` |
| `DriveAsixOfficeController` | class | 17 | `app/Http/Controllers/DriveAsixOfficeController.php:18` |
| `openOrReuseSession` | method | 17 | `app/Services/DriveAsix/OnlyOfficeEditorService.php:209` |
| `replaceUnderLock` | method | 17 | `app/Services/DriveAsix/OnlyOfficeDocumentStorageService.php:671` |
| `PublicWorkbookController` | class | 16 | `app/Http/Controllers/PublicWorkbookController.php:14` |
| `extension` | method | 16 | `app/Models/DriveAsixFile.php:70` |
| `deleteStoredArtifacts` | method | 15 | `app/Http/Controllers/DriveAsixController.php:1066` |
| `requireAdmin` | method | 15 | `app/Http/Controllers/DriveAsixController.php:627` |
| `serializeWorkbook` | method | 15 | `app/Services/DriveAsix/SpreadsheetWorkbookService.php:819` |

## Route Nodes

- `drive.file.copy` - `drive/files/{file}/copy`
- `drive.file.delete` - `drive/files/{file}`
- `drive.file.document-preview` - `drive/files/{file}/document-preview`
- `drive.file.download` - `drive/files/{file}/download`
- `drive.file.editor` - `drive/files/{file}/editor`
- `drive.file.move` - `drive/files/{file}/move`
- `drive.file.office-editor` - `drive/files/{file}/office-editor`
- `drive.file.preview` - `drive/files/{file}/preview`
- `drive.file.purge` - `drive/trash/files/{id}/purge`
- `drive.file.purge-all` - `drive/trash/purge-all`
- `drive.file.rename` - `drive/files/{file}/rename`
- `drive.file.restore` - `drive/trash/files/{id}/restore`
- `drive.file.workbook` - `drive/files/{file}/workbook`
- `drive.file.workbook.cells` - `drive/files/{file}/workbook/cells`
- `drive.file.workbook.save` - `drive/files/{file}/workbook`
- `drive.folder.delete` - `drive/folders/{folder}`
- `drive.folder.move` - `drive/folders/{folder}/move`
- `drive.folder.rename` - `drive/folders/{folder}/rename`
- `drive.folder.store` - `drive/folders`
- `drive.index` - `drive/{folderId?}`
- `drive.office.callback` - `drive/office/files/{file}/{documentKey}/callback`
- `drive.office.source` - `drive/office/files/{file}/{documentKey}/source`
- `drive.pipeline-summary` - `drive/pipeline-summary`
- `drive.upload` - `drive/upload`

## Class Nodes

- `DriveAsixController` - `app/Http/Controllers/DriveAsixController.php`
- `DriveAsixFile` - `app/Models/DriveAsixFile.php`
- `DriveAsixFolder` - `app/Models/DriveAsixFolder.php`
- `DriveAsixOfficeController` - `app/Http/Controllers/DriveAsixOfficeController.php`
- `DriveAsixOfficeException` - `app/Exceptions/DriveAsixOfficeException.php`
- `DriveAsixVersionConflictException` - `app/Exceptions/DriveAsixVersionConflictException.php`
- `DriveAsixWorkbookException` - `app/Exceptions/DriveAsixWorkbookException.php`
- `MicroNettWorkbook` - `app/Support/MicroNettWorkbook.php`
- `MicroRmNettWorkbook` - `app/Support/MicroRmNettWorkbook.php`
- `OfficeDocumentPreviewService` - `app/Services/DriveAsix/OfficeDocumentPreviewService.php`
- `OnlyOfficeDocumentStorageService` - `app/Services/DriveAsix/OnlyOfficeDocumentStorageService.php`
- `OnlyOfficeEditorService` - `app/Services/DriveAsix/OnlyOfficeEditorService.php`
- `OnlyOfficeJwtService` - `app/Services/DriveAsix/OnlyOfficeJwtService.php`
- `PipelineWorkbookSummaryService` - `app/Services/DriveAsix/PipelineWorkbookSummaryService.php`
- `PublicWorkbookController` - `app/Http/Controllers/PublicWorkbookController.php`
- `SpreadsheetWorkbookService` - `app/Services/DriveAsix/SpreadsheetWorkbookService.php`

## Table Nodes

- `drive_asix_files`
- `drive_asix_folders`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| bank-pipeline -> core (calls) | 167 |
| bank-pipeline -> access-control (protected_by) | 136 |
| bank-pipeline -> core (accepts) | 63 |
| bank-pipeline -> core (instantiates) | 37 |
| core -> bank-pipeline (references_route) | 28 |
| bank-pipeline -> access-control (calls) | 8 |
| bank-pipeline -> core (extends) | 8 |
| bank-pipeline -> core (renders) | 4 |
| database -> bank-pipeline (defines_table) | 2 |
| database -> bank-pipeline (alters_table) | 2 |
| marketshare -> bank-pipeline (calls) | 2 |
| bank-pipeline -> marketshare (contains) | 2 |
| core -> bank-pipeline (accepts) | 1 |
| core -> bank-pipeline (calls) | 1 |
| bank-pipeline -> core (references_route) | 1 |
| bank-pipeline -> jobs-snapshots (dispatches) | 1 |
| jobs-snapshots -> bank-pipeline (accepts) | 1 |
| jobs-snapshots -> bank-pipeline (calls) | 1 |
| dashboard-pinjaman -> bank-pipeline (calls) | 1 |
| bank-pipeline -> core (uses_trait) | 1 |
| bank-pipeline -> dashboard-pinjaman (contains) | 1 |
| platform -> bank-pipeline (references_route) | 1 |
