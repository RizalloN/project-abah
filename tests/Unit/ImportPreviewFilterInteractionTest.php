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
        $this->assertStringNotContainsString('importPreviewToggleSelectAllDirect', $source);
        $this->assertStringNotContainsString('onclick="window.importPreviewToggleSelectAllDirect', $source);
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

        $this->assertStringContainsString(
            'const hadAllSelected = previousValues.length === 0 || previousSelection.size === previousValues.length;',
            $source
        );
        $this->assertStringNotContainsString(
            'previousSelection.has(value) || previousSelection.size === 0',
            $source
        );
        $this->assertStringNotContainsString('select-all-cb direct change event fired', $source);
        $this->assertStringNotContainsString('applySelectAllState starting', $source);
        $this->assertStringNotContainsString('applySelectAllState finished', $source);
    }
}
