# Domain: presentation

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=presentation --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 10 |
| method | 225 |
| unresolved_symbol | 1 |
| class | 7 |
| trait | 1 |
| view | 2 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `NativeOpenXmlPowerPointRenderer` | class | 71 | `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php:9` |
| `PresentationScopeDataService` | class | 50 | `app/Services/Presentation/PresentationScopeDataService.php:14` |
| `PresentationFundingStrategyService` | class | 41 | `app/Services/Presentation/PresentationFundingStrategyService.php:16` |
| `PresentationDeckDataService` | class | 35 | `app/Services/Presentation/PresentationDeckDataService.php:10` |
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
| `build` | method | 14 | `app/Services/Presentation/PresentationFundingStrategyService.php:35` |

## Class Nodes

- `NativeOpenXmlPowerPointRenderer` - `app/Services/Presentation/NativeOpenXmlPowerPointRenderer.php`
- `PowerPointExportService` - `app/Services/Presentation/PowerPointExportService.php`
- `PresentationDeckDataService` - `app/Services/Presentation/PresentationDeckDataService.php`
- `PresentationExportManager` - `app/Services/Presentation/PresentationExportManager.php`
- `PresentationFundingStrategyService` - `app/Services/Presentation/PresentationFundingStrategyService.php`
- `PresentationNarrativeService` - `app/Services/Presentation/PresentationNarrativeService.php`
- `PresentationScopeDataService` - `app/Services/Presentation/PresentationScopeDataService.php`

## Trait Nodes

- `IdReusable` - `app/Traits/IdReusable.php`

## View Nodes

- `presentation` - `resources/views/presentation.blade.php`
- `presentation._executive-slides` - `resources/views/presentation/_executive-slides.blade.php`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| presentation -> core (calls) | 60 |
| presentation -> core (accepts) | 24 |
| presentation -> core (instantiates) | 16 |
| jobs-snapshots -> presentation (calls) | 9 |
| presentation -> dashboard-simpanan (references_route) | 8 |
| dashboard-simpanan -> presentation (accepts) | 4 |
| access-control -> presentation (calls) | 4 |
| presentation -> import (reads_table) | 4 |
| presentation -> core (checks_table) | 3 |
| jobs-snapshots -> presentation (uses_trait) | 3 |
| jobs-snapshots -> presentation (accepts) | 2 |
| presentation -> dashboard-simpanan (reads_table) | 2 |
| input-management -> presentation (uses_trait) | 2 |
| presentation -> access-control (contains) | 2 |
| dashboard-simpanan -> presentation (calls) | 1 |
| dashboard-simpanan -> presentation (renders) | 1 |
| presentation -> input-management (injects) | 1 |
| presentation -> dashboard-pinjaman (injects) | 1 |
| presentation -> core (injects) | 1 |
| presentation -> input-management (calls) | 1 |
| presentation -> dashboard-simpanan (checks_table) | 1 |
| presentation -> dashboard-pinjaman (calls) | 1 |
| presentation -> core (reads_table) | 1 |
| import -> presentation (uses_trait) | 1 |
| access-control -> presentation (uses_trait) | 1 |
| presentation -> jobs-snapshots (contains) | 1 |
