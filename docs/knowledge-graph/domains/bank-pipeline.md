# Domain: bank-pipeline

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=bank-pipeline --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 20 |
| function | 14 |
| method | 304 |
| unresolved_symbol | 23 |
| route | 24 |
| class | 17 |
| table | 2 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `DriveAsixFile` | class | 80 | `app/Models/DriveAsixFile.php:9` |
| `SpreadsheetWorkbookService` | class | 76 | `app/Services/DriveAsix/SpreadsheetWorkbookService.php:30` |
| `DriveAsixOfficeException` | class | 52 | `app/Exceptions/DriveAsixOfficeException.php:7` |
| `OnlyOfficeEditorService` | class | 49 | `app/Services/DriveAsix/OnlyOfficeEditorService.php:15` |
| `DriveAsixController` | class | 48 | `app/Http/Controllers/DriveAsixController.php:26` |
| `DriveAsixWorkbookException` | class | 40 | `app/Exceptions/DriveAsixWorkbookException.php:7` |
| `OfficeDocumentPreviewService` | class | 32 | `app/Services/DriveAsix/OfficeDocumentPreviewService.php:13` |
| `PipelineWorkbookSummaryService` | class | 32 | `app/Services/DriveAsix/PipelineWorkbookSummaryService.php:15` |
| `OnlyOfficeDocumentStorageService` | class | 31 | `app/Services/DriveAsix/OnlyOfficeDocumentStorageService.php:19` |
| `callback` | method | 28 | `app/Http/Controllers/DriveAsixOfficeController.php:181` |
| `OnlyOfficeJwtService` | class | 24 | `app/Services/DriveAsix/OnlyOfficeJwtService.php:7` |
| `editor` | method | 24 | `app/Http/Controllers/DriveAsixOfficeController.php:39` |
| `save` | method | 23 | `app/Services/DriveAsix/SpreadsheetWorkbookService.php:434` |
| `DriveAsixFolder` | class | 22 | `app/Models/DriveAsixFolder.php:9` |
| `applyOperation` | method | 21 | `app/Services/DriveAsix/SpreadsheetWorkbookService.php:1212` |
| `source` | method | 21 | `app/Http/Controllers/DriveAsixOfficeController.php:123` |
| `buildEditorConfig` | method | 19 | `app/Services/DriveAsix/OnlyOfficeEditorService.php:379` |
| `drive.index` | route | 18 | - |
| `previewPresentation` | method | 18 | `app/Services/DriveAsix/OfficeDocumentPreviewService.php:272` |
| `previewWordDocument` | method | 18 | `app/Services/DriveAsix/OfficeDocumentPreviewService.php:158` |
| `serializeSheet` | method | 18 | `app/Services/DriveAsix/SpreadsheetWorkbookService.php:887` |
| `upload` | method | 18 | `app/Http/Controllers/DriveAsixController.php:212` |
| `DriveAsixOfficeController` | class | 17 | `app/Http/Controllers/DriveAsixOfficeController.php:18` |
| `openOrReuseSession` | method | 17 | `app/Services/DriveAsix/OnlyOfficeEditorService.php:209` |
| `replaceUnderLock` | method | 17 | `app/Services/DriveAsix/OnlyOfficeDocumentStorageService.php:671` |
| `PublicWorkbookController` | class | 16 | `app/Http/Controllers/PublicWorkbookController.php:14` |
| `extension` | method | 16 | `app/Models/DriveAsixFile.php:70` |
| `deleteStoredArtifacts` | method | 15 | `app/Http/Controllers/DriveAsixController.php:1059` |
| `requireAdmin` | method | 15 | `app/Http/Controllers/DriveAsixController.php:620` |
| `serializeWorkbook` | method | 15 | `app/Services/DriveAsix/SpreadsheetWorkbookService.php:805` |

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
- `DriveAsixDocumentPreviewViewTest` - `tests/Unit/DriveAsixDocumentPreviewViewTest.php`
- `DriveAsixFile` - `app/Models/DriveAsixFile.php`
- `DriveAsixFolder` - `app/Models/DriveAsixFolder.php`
- `DriveAsixOfficeController` - `app/Http/Controllers/DriveAsixOfficeController.php`
- `DriveAsixOfficeException` - `app/Exceptions/DriveAsixOfficeException.php`
- `DriveAsixStreamingWorkbookValidationTest` - `tests/Unit/DriveAsixStreamingWorkbookValidationTest.php`
- `DriveAsixUploadUiContractTest` - `tests/Unit/DriveAsixUploadUiContractTest.php`
- `DriveAsixVersionConflictException` - `app/Exceptions/DriveAsixVersionConflictException.php`
- `DriveAsixWorkbookException` - `app/Exceptions/DriveAsixWorkbookException.php`
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
| bank-pipeline -> core (calls) | 171 |
| bank-pipeline -> access-control (protected_by) | 114 |
| bank-pipeline -> core (instantiates) | 67 |
| bank-pipeline -> core (accepts) | 66 |
| core -> bank-pipeline (references_route) | 29 |
| bank-pipeline -> access-control (calls) | 8 |
| bank-pipeline -> core (extends) | 8 |
| bank-pipeline -> core (renders) | 6 |
| marketshare -> bank-pipeline (dispatches_to) | 4 |
| tests -> bank-pipeline (instantiates) | 3 |
| tests -> bank-pipeline (contains) | 3 |
| bank-pipeline -> tests (extends) | 3 |
| bank-pipeline -> core (defines_table) | 2 |
| database -> bank-pipeline (defines_table) | 2 |
| database -> bank-pipeline (alters_table) | 2 |
| core -> bank-pipeline (accepts) | 2 |
| core -> bank-pipeline (calls) | 2 |
| bank-pipeline -> core (references_route) | 1 |
| bank-pipeline -> core (dispatches) | 1 |
| bank-pipeline -> tests (instantiates) | 1 |
| bank-pipeline -> tests (calls) | 1 |
| presentation -> bank-pipeline (instantiates) | 1 |
| bank-pipeline -> core (uses_trait) | 1 |
