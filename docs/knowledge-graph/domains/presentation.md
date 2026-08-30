# Domain: presentation

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=presentation --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 15 |
| method | 256 |
| unresolved_symbol | 1 |
| class | 11 |
| view | 2 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `NativeOpenXmlPowerPointRenderer` | class | 72 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:9` |
| `PresentationScopeDataService` | class | 53 | `app/Services/Presentation/PresentationScopeDataService.php:14` |
| `PresentationFundingStrategyService` | class | 39 | `app/Services/Presentation/PresentationFundingStrategyService.php:16` |
| `PresentationDeckDataService` | class | 38 | `app/Services/Presentation/PresentationDeckDataService.php:10` |
| `PresentationPowerPointExportTest` | class | 33 | `tests/Unit/PresentationPowerPointExportTest.php:11` |
| `PresentationExportManager` | class | 23 | `app/Services/Presentation/PresentationExportManager.php:11` |
| `footer` | method | 23 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:2316` |
| `header` | method | 22 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:2300` |
| `text` | method | 22 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:2689` |
| `cell` | method | 21 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:2591` |
| `shape` | method | 21 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:2664` |
| `slide` | method | 21 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:2648` |
| `callout` | method | 20 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:2635` |
| `PresentationNarrativeService` | class | 19 | `app/Services/Presentation/PresentationNarrativeService.php:5` |
| `table` | method | 19 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:2454` |
| `buildSlides` | method | 18 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:81` |
| `formatAmount` | method | 18 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:3061` |
| `sectionOverviewSlide` | method | 18 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:576` |
| `structuredCreditSegmentSlide` | method | 18 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:1102` |
| `build` | method | 17 | `app/Services/Presentation/PresentationNarrativeService.php:11` |
| `formatPercent` | method | 17 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:3091` |
| `structuredFundingOverviewSlide` | method | 17 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:768` |
| `marketShareAreaSlide` | method | 16 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:201` |
| `marketShareMappingSlide` | method | 16 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:421` |
| `marketShareSectorSlide` | method | 16 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:327` |
| `metricCard` | method | 16 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:2624` |
| `sectionProductSlide` | method | 16 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:644` |
| `build` | method | 15 | `app/Services/Presentation/PresentationDeckDataService.php:21` |
| `formatCurrency` | method | 15 | `app/Services/Presentation/PresentationScopeDataService.php:2160` |
| `structuredCreditOverviewSlide` | method | 15 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:983` |

## Class Nodes

- `FinancialHighlightPresentationTest` - `tests/Unit/FinancialHighlightPresentationTest.php`
- `GeneratePresentationPowerPointJob` - `app/Jobs/GeneratePresentationPowerPointJob.php`
- `NativeOpenXmlPowerPointRenderer` - `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php`
- `NativeOpenXmlPowerPointRendererTest` - `tests/Unit/NativeOpenXmlPowerPointRendererTest.php`
- `PowerPointExportService` - `app/Services/Presentation/PowerPointExportService.php`
- `PresentationDeckDataService` - `app/Services/Presentation/PresentationDeckDataService.php`
- `PresentationExportManager` - `app/Services/Presentation/PresentationExportManager.php`
- `PresentationFundingStrategyService` - `app/Services/Presentation/PresentationFundingStrategyService.php`
- `PresentationNarrativeService` - `app/Services/Presentation/PresentationNarrativeService.php`
- `PresentationPowerPointExportTest` - `tests/Unit/PresentationPowerPointExportTest.php`
- `PresentationScopeDataService` - `app/Services/Presentation/PresentationScopeDataService.php`

## View Nodes

- `presentation` - `resources/views/presentation.blade.php`
- `presentation._executive-slides` - `resources/views/presentation/_executive-slides.blade.php`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| presentation -> core (calls) | 61 |
| presentation -> core (instantiates) | 27 |
| presentation -> core (accepts) | 25 |
| presentation -> dashboard-simpanan (references_route) | 8 |
| jobs-snapshots -> presentation (calls) | 6 |
| dashboard-simpanan -> presentation (accepts) | 4 |
| dashboard-simpanan -> presentation (calls) | 3 |
| presentation -> import (reads_table) | 3 |
| presentation -> core (checks_table) | 3 |
| presentation -> almafacts (instantiates) | 3 |
| presentation -> jobs-snapshots (uses_trait) | 3 |
| presentation -> tests (extends) | 3 |
| presentation -> marketshare (contains) | 3 |
| presentation -> core (injects) | 2 |
| presentation -> jobs-snapshots (reads_table) | 2 |
| presentation -> core (reads_table) | 2 |
| marketshare -> presentation (instantiates) | 2 |
| presentation -> jobs-snapshots (contains) | 2 |
| presentation -> almafacts (contains) | 2 |
| presentation -> prognosa (contains) | 2 |
| dashboard-simpanan -> presentation (renders) | 1 |
| presentation -> jobs-snapshots (uses_queue) | 1 |
| jobs-snapshots -> presentation (dispatches) | 1 |
| presentation -> input-management (injects) | 1 |
| presentation -> input-management (calls) | 1 |
| presentation -> jobs-snapshots (checks_table) | 1 |
| presentation -> almafacts (reads_table) | 1 |
| prognosa -> presentation (calls) | 1 |
| presentation -> tests (calls) | 1 |
| presentation -> bank-pipeline (instantiates) | 1 |
