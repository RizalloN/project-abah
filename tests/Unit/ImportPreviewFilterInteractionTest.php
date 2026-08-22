<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImportPreviewFilterInteractionTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function previewTemplates(): array
    {
        return [
            'CSV preview' => ['resources/views/import/preview.blade.php'],
            'Excel preview' => ['resources/views/import/preview_excel.blade.php'],
        ];
    }

    #[DataProvider('previewTemplates')]
    public function test_filter_controls_receive_clicks_before_dropdown_propagation_is_stopped(string $path): void
    {
        $source = file_get_contents(base_path($path));

        $this->assertStringContainsString(
            "document.querySelectorAll('.import-preview-table .dropdown-menu').forEach",
            $source
        );
        $this->assertStringContainsString("menu.addEventListener('click'", $source);
        $this->assertStringNotContainsString("document.addEventListener(eventType", $source);
        $this->assertStringNotContainsString('Use capture phase', $source);
    }

    public function test_csv_preview_uses_one_select_all_handler_and_applies_initial_area6_state(): void
    {
        $source = file_get_contents(base_path('resources/views/import/preview.blade.php'));

        $this->assertStringContainsString("window.importPreviewFilterBuild = 'merchant-filter-state-v2';", $source);
        $this->assertStringContainsString('const initialArea6Selections = @json($initialArea6Selections ?? []);', $source);
        $this->assertStringContainsString('const configuredInitialValues = normalizeFilterValues(initialArea6Selections[col] || []);', $source);
        $this->assertStringContainsString('? new Set(configuredInitialValues)', $source);
        $this->assertStringContainsString('initialSelectionPending: shouldApplyArea6Selection,', $source);
        $this->assertStringContainsString('!state.initialSelectionPending', $source);
        $this->assertStringNotContainsString('importPreviewToggleSelectAllDirect', $source);
        $this->assertStringNotContainsString('onclick="window.importPreviewToggleSelectAllDirect', $source);
    }

    public function test_csv_preview_applies_initial_filter_without_showing_stale_rows(): void
    {
        $source = file_get_contents(base_path('resources/views/import/preview.blade.php'));

        $this->assertStringContainsString('function updatePreviewTable(immediate = false)', $source);
        $this->assertStringContainsString('renderSamplePreviewTable(activeFilters);', $source);
        $this->assertStringContainsString('if (immediate) {', $source);
        $this->assertStringContainsString('updatePreviewTable(true);', $source);
        $this->assertStringContainsString("const activeFiltersInput = document.getElementById('active_filters_json');", $source);
        $this->assertStringContainsString('if (Object.keys(activeFilters).length > 0)', $source);
        $this->assertStringNotContainsString('if (prefetchFilterOptionsOnLoad && filePathValue && filterOptionsUrl)', $source);
    }

    #[DataProvider('previewTemplates')]
    public function test_preview_filters_current_sample_before_waiting_for_server_rows(string $path): void
    {
        $source = file_get_contents(base_path($path));

        $this->assertStringContainsString('renderSamplePreviewTable(activeFilters);', $source);
        $this->assertStringContainsString('}, 180);', $source);
        $this->assertStringContainsString("document.getElementById('active_filters_json')", $source);
        $this->assertStringContainsString('if (Object.keys(activeFilters).length > 0)', $source);
    }

    #[DataProvider('previewTemplates')]
    public function test_select_all_uses_complete_normalized_filter_state(string $path): void
    {
        $source = file_get_contents(base_path($path));

        $this->assertStringContainsString('function normalizeFilterValues(values)', $source);
        $this->assertStringContainsString('Array.from(new Set(', $source);
        $this->assertStringContainsString('const effectiveValues = state.allValues.slice();', $source);
        $this->assertStringContainsString('selectAll.disabled = Boolean(state.isLoading);', $source);
        $this->assertStringNotContainsString('collectPreviewValuesForColumn(', $source);
        $this->assertStringNotContainsString('rowMatchesActiveFilters(', $source);
    }

    #[DataProvider('previewTemplates')]
    public function test_cached_options_preserve_an_all_selected_state_without_debug_handlers(string $path): void
    {
        $source = file_get_contents(base_path($path));

        $this->assertStringContainsString('function replaceFilterOptions(state, values)', $source);
        $this->assertStringContainsString('previousSelection.size === previousValues.length', $source);
        $this->assertStringContainsString('replaceFilterOptions(state, cachedValues);', $source);
        $this->assertStringContainsString('replaceFilterOptions(state, normalizedValues);', $source);
        $this->assertStringNotContainsString(
            'previousSelection.has(value) || previousSelection.size === 0',
            $source
        );
        $this->assertStringNotContainsString('select-all-cb direct change event fired', $source);
        $this->assertStringNotContainsString('applySelectAllState starting', $source);
        $this->assertStringNotContainsString('applySelectAllState finished', $source);
    }

    #[DataProvider('previewTemplates')]
    public function test_wide_previews_prefetch_the_complete_branch_filter_instead_of_skipping_it(string $path): void
    {
        $source = file_get_contents(base_path($path));

        $this->assertStringContainsString('function priorityFilterHeaderScore(header)', $source);
        $this->assertStringContainsString("compactHeader === 'mbdesc'", $source);
        $this->assertStringContainsString("compactHeader.includes('namakci')", $source);
        $this->assertStringContainsString("compactHeader === 'kanca'", $source);
        $this->assertStringContainsString("compactHeader === 'cabang'", $source);
        $this->assertStringContainsString('function resolvePriorityFilterColumns()', $source);
        $this->assertStringContainsString('function prefetchPriorityFilterOptions()', $source);
        $this->assertStringContainsString('prefetchPriorityFilterOptions().catch(function (error)', $source);
        $this->assertStringNotContainsString('Prefetch skipped because table has too many columns:', $source);

        $priorityPrefetchPosition = strpos($source, 'prefetchPriorityFilterOptions().catch(function (error)');
        $initialRenderPosition = strrpos($source, 'renderFilterList(col);');
        $this->assertIsInt($priorityPrefetchPosition);
        $this->assertIsInt($initialRenderPosition);
        $this->assertLessThan(
            $initialRenderPosition,
            $priorityPrefetchPosition,
            'Priority branch hydration harus dimulai sebelum daftar sampel pertama dirender.'
        );
    }

    public function test_filter_option_cache_versions_invalidate_partial_branch_lists(): void
    {
        $csvPreview = file_get_contents(base_path('resources/views/import/preview.blade.php'));
        $excelPreview = file_get_contents(base_path('resources/views/import/preview_excel.blade.php'));

        $this->assertStringContainsString("const storageKeyPrefix = 'preview_filter_v9_'", $csvPreview);
        $this->assertStringContainsString("const storageKeyPrefix = 'preview_filter_excel_v6_'", $excelPreview);
        $this->assertStringNotContainsString("const storageKeyPrefix = 'preview_filter_v8_'", $csvPreview);
        $this->assertStringNotContainsString("const storageKeyPrefix = 'preview_filter_excel_v5_'", $excelPreview);
    }
}
