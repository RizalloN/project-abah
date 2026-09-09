<?php

namespace App\Support;

use App\Services\Presentation\PresentationPrognosaWeeklyService;

final class LandingPrognosaCardService
{
    public function __construct(
        private readonly PresentationPrognosaWeeklyService $prognosaWeeklyService
    ) {
    }

    /** @param array<string, mixed> $dashboard */
    public function decorateDashboard(array $dashboard, ?string $sourceScope = null): array
    {
        $portfolio = data_get($dashboard, 'area6_portfolio');
        if (! is_array($portfolio) || $portfolio === []) {
            return $dashboard;
        }

        if ($sourceScope === null) {
            $scope = UserBranchScope::current();
            $sourceScope = $scope['upper_label'] ?? UserBranchScope::AREA_SCOPE;
        }
        $payload = $this->prognosaWeeklyService->payload();

        data_set(
            $dashboard,
            'area6_portfolio',
            $this->decoratePortfolio($portfolio, $payload, $sourceScope)
        );

        return $dashboard;
    }

    /**
     * @param  array<string, mixed>  $portfolio
     * @param  array<string, mixed>  $prognosaPayload
     * @return array<string, mixed>
     */
    public function decoratePortfolio(
        array $portfolio,
        array $prognosaPayload,
        string $sourceScope = 'area6'
    ): array {
        $meta = (array) data_get($prognosaPayload, 'meta', []);
        $forecastScope = data_get($prognosaPayload, ['scopes', $sourceScope], []);
        $metrics = data_get($forecastScope, 'metrics', []);

        if (! is_array($forecastScope) || ! is_array($metrics) || ! (bool) ($meta['available'] ?? false)) {
            $portfolio['prognosa'] = [
                'available' => false,
                'scope' => $sourceScope,
                'error' => (string) ($meta['error'] ?? 'Data prognosa belum tersedia.'),
            ];

            return $portfolio;
        }

        $weekLabel = $this->normalizeWeekLabel((string) ($meta['week_label'] ?? ''));
        $availableWeeks = $this->resolveAvailableWeeks($meta, $forecastScope, $weekLabel);
        $forecastLabel = trim((string) ($meta['label'] ?? 'Prognosa '.$weekLabel));

        $portfolio['prognosa'] = [
            'available' => true,
            'scope' => $sourceScope,
            'week' => (int) ($meta['week_number'] ?? 1),
            'week_label' => $weekLabel,
            'label' => $forecastLabel,
            'stale' => (bool) ($meta['stale'] ?? false),
            'source' => (string) ($meta['source'] ?? 'Prognosa Weekly'),
            'available_weeks' => $availableWeeks,
        ];

        $scopes = (array) ($portfolio['scopes'] ?? []);
        foreach ($scopes as $scopeKey => $scopePayload) {
            if (! is_array($scopePayload)) {
                continue;
            }

            $scopePayload['prognosa'] = $this->buildScopePrognosa(
                $scopePayload,
                $forecastScope,
                $availableWeeks,
                $weekLabel,
                $meta
            );
            $scopePayload['cards'] = array_map(
                fn (mixed $card): mixed => is_array($card)
                    ? $this->decorateCard(
                        $card,
                        (string) $scopeKey,
                        $scopePayload,
                        $forecastScope,
                        $availableWeeks,
                        $weekLabel,
                        $meta
                    )
                    : $card,
                (array) ($scopePayload['cards'] ?? [])
            );
            $scopes[$scopeKey] = $scopePayload;
        }

        $portfolio['scopes'] = $scopes;
        $defaultScope = (string) ($portfolio['default_scope'] ?? 'area6');
        if (isset($scopes[$defaultScope]['cards'])) {
            $portfolio['cards'] = $scopes[$defaultScope]['cards'];
        }

        return $portfolio;
    }

    /**
     * @param  array<string, mixed>  $card
     * @param  array<string, mixed>  $scopePayload
     * @param  array<string, mixed>  $forecastScope
     * @param  array<int, string>  $availableWeeks
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function decorateCard(
        array $card,
        string $scopeKey,
        array $scopePayload,
        array $forecastScope,
        array $availableWeeks,
        string $weekLabel,
        array $meta
    ): array {
        $cardKey = strtolower((string) ($card['key'] ?? ''));
        $metricKey = $scopeKey === 'area6' ? $cardKey : $scopeKey.'_'.$cardKey;
        $weeklyCards = [];

        foreach ($availableWeeks as $candidateWeek) {
            $metrics = $this->forecastMetricsForWeek($forecastScope, $candidateWeek, $weekLabel);
            $forecast = data_get($metrics, $metricKey.'.value');
            $forecastAvailable = (bool) data_get($metrics, $metricKey.'.available', false)
                && is_numeric($forecast);
            $actualPayload = $this->actualPayloadForWeek($scopePayload, $candidateWeek, $weekLabel);
            $actual = data_get($actualPayload, ['metrics', $cardKey]);

            if (! is_numeric($actual) && $candidateWeek === $weekLabel) {
                $actual = is_numeric($card['realization_raw'] ?? null)
                    ? (float) $card['realization_raw']
                    : $this->parseDisplayedMillions((string) ($card['realization_value'] ?? '')) * 1_000_000;
            }

            $actualAvailable = is_numeric($actual);
            $forecastValue = $forecastAvailable ? (float) $forecast : null;
            $actualValue = $actualAvailable ? (float) $actual : null;
            $lowerIsBetter = in_array($cardKey, ['sml', 'npl'], true);
            $achievement = $forecastValue !== null && $actualValue !== null
                ? $this->achievement($actualValue, $forecastValue, $lowerIsBetter)
                : null;
            $isAchieved = $this->isTargetAchieved($actualValue, $forecastValue, $lowerIsBetter);

            $weeklyCards[$candidateWeek] = [
                'available' => $forecastAvailable,
                'week_label' => $candidateWeek,
                'label' => 'Prognosa '.$candidateWeek,
                'value_raw' => $forecastValue,
                'value' => $forecastValue === null ? '-' : $this->formatMillions($forecastValue),
                'actual_available' => $actualAvailable,
                'actual_raw' => $actualValue,
                'actual_value' => $actualValue === null ? '-' : $this->formatMillions($actualValue),
                'actual_label' => (string) data_get($actualPayload, 'position_label', 'Posisi terbaru'),
                'cutoff_label' => (string) data_get($actualPayload, 'cutoff_label', ''),
                'state_label' => (string) data_get($actualPayload, 'state_label', ''),
                'is_elapsed' => (bool) data_get($actualPayload, 'is_elapsed', false),
                'achievement_label' => '% Penc. Prognosa',
                'achievement_raw' => $achievement,
                'achievement' => $this->formatAchievement($achievement, $isAchieved),
                'achievement_color' => $this->achievementColor($achievement, $isAchieved),
                'stale' => (bool) ($meta['stale'] ?? false),
            ];
        }

        $selected = $weeklyCards[$weekLabel]
            ?? collect($weeklyCards)->first(static fn (array $week): bool => (bool) ($week['available'] ?? false));

        if (! is_array($selected)) {
            $card['prognosa'] = [
                'available' => false,
                'week_label' => $weekLabel,
                'weeks' => $weeklyCards,
            ];

            return $card;
        }

        $card['prognosa'] = array_merge($selected, [
            'active_week' => $weekLabel,
            'weeks' => $weeklyCards,
        ]);

        return $card;
    }

    /**
     * @param  array<string, mixed>  $scopePayload
     * @param  array<string, mixed>  $forecastScope
     * @param  array<int, string>  $availableWeeks
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function buildScopePrognosa(
        array $scopePayload,
        array $forecastScope,
        array $availableWeeks,
        string $activeWeek,
        array $meta
    ): array {
        $weeks = [];
        foreach ($availableWeeks as $weekLabel) {
            $actual = $this->actualPayloadForWeek($scopePayload, $weekLabel, $activeWeek);
            $forecast = data_get($forecastScope, ['weeks', $weekLabel], []);
            if (! is_array($forecast) || $forecast === []) {
                $forecast = $weekLabel === $activeWeek ? $forecastScope : [];
            }

            $weeks[$weekLabel] = [
                'week' => (int) substr($weekLabel, 1),
                'week_label' => $weekLabel,
                'label' => 'Week '.substr($weekLabel, 1),
                'available' => (bool) data_get($forecast, 'available', false),
                'active' => $weekLabel === $activeWeek,
                'position_label' => (string) data_get($actual, 'position_label', 'Posisi terbaru'),
                'cutoff_label' => (string) data_get($actual, 'cutoff_label', ''),
                'state_label' => (string) data_get($actual, 'state_label', ''),
                'is_elapsed' => (bool) data_get($actual, 'is_elapsed', false),
            ];
        }

        return [
            'available' => collect($weeks)->contains(
                static fn (array $week): bool => (bool) ($week['available'] ?? false)
            ),
            'active_week' => $activeWeek,
            'weeks' => $weeks,
            'stale' => (bool) ($meta['stale'] ?? false),
        ];
    }

    /**
     * The selected week is the most recent available forecast. Its achievement
     * must be measured against the latest actual position, not kept at an old
     * Saturday cutoff when the workbook has no following week yet.
     *
     * @param  array<string, mixed>  $scopePayload
     * @return array<string, mixed>
     */
    private function actualPayloadForWeek(array $scopePayload, string $weekLabel, string $activeWeek): array
    {
        $cutoffActual = data_get($scopePayload, ['prognosa_actuals', $weekLabel], []);
        if (! is_array($cutoffActual) || $weekLabel !== $activeWeek) {
            return is_array($cutoffActual) ? $cutoffActual : [];
        }

        $latestActual = data_get($scopePayload, ['prognosa_actuals', 'latest'], []);
        if (! is_array($latestActual) || ! $this->isNewerActual($latestActual, $cutoffActual)) {
            return $cutoffActual;
        }

        return array_merge($latestActual, [
            'cutoff_label' => (string) data_get($cutoffActual, 'cutoff_label', data_get($latestActual, 'cutoff_label', '')),
            'forecast_cutoff_label' => (string) data_get($cutoffActual, 'cutoff_label', ''),
            'comparison_mode' => 'running_position',
        ]);
    }

    /** @param array<string, mixed> $latestActual @param array<string, mixed> $cutoffActual */
    private function isNewerActual(array $latestActual, array $cutoffActual): bool
    {
        $latestPeriod = (string) data_get($latestActual, 'actual_period', '');
        $cutoffPeriod = (string) data_get($cutoffActual, 'actual_period', '');

        return $latestPeriod !== '' && ($cutoffPeriod === '' || $latestPeriod > $cutoffPeriod);
    }

    /** @return array<string, mixed> */
    private function forecastMetricsForWeek(array $forecastScope, string $weekLabel, string $activeWeek): array
    {
        $metrics = data_get($forecastScope, ['weeks', $weekLabel, 'metrics']);
        if (is_array($metrics)) {
            return $metrics;
        }

        return $weekLabel === $activeWeek && is_array($forecastScope['metrics'] ?? null)
            ? $forecastScope['metrics']
            : [];
    }

    /** @return array<int, string> */
    private function resolveAvailableWeeks(array $meta, array $forecastScope, string $activeWeek): array
    {
        $weeks = collect((array) ($meta['available_weeks'] ?? []))
            ->map(function (mixed $week): ?string {
                $label = is_numeric($week) ? 'W'.(int) $week : strtoupper(trim((string) $week));

                return preg_match('/^W[1-5]$/', $label) === 1 ? $label : null;
            })
            ->filter()
            ->values();

        if ($weeks->isEmpty()) {
            $weeks = collect(array_keys((array) ($forecastScope['weeks'] ?? [])))
                ->map(fn (mixed $week): string => strtoupper(trim((string) $week)))
                ->filter(static fn (string $week): bool => preg_match('/^W[1-5]$/', $week) === 1)
                ->values();
        }

        if ($weeks->isEmpty()) {
            $weeks->push($activeWeek);
        } elseif (! $weeks->contains($activeWeek)) {
            $weeks->push($activeWeek);
        }

        return $weeks
            ->unique()
            ->sortBy(static fn (string $week): int => (int) substr($week, 1))
            ->values()
            ->all();
    }

    private function normalizeWeekLabel(string $week): string
    {
        $week = strtoupper(trim($week));

        return preg_match('/^W[1-5]$/', $week) === 1 ? $week : 'W1';
    }

    private function formatMillions(float $value): string
    {
        return number_format(round($value / 1_000_000), 0, ',', '.');
    }

    private function achievement(float $realization, float $forecast, bool $lowerIsBetter): ?float
    {
        if (abs($forecast) < 0.000001) {
            return null;
        }

        if ($lowerIsBetter) {
            return $realization <= 0.000001 ? 100.0 : ($forecast / $realization) * 100;
        }

        return ($realization / $forecast) * 100;
    }

    private function parseDisplayedMillions(string $value): float
    {
        $value = trim(str_replace(['(', ')', ' '], ['', '', ''], $value));
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function isTargetAchieved(?float $actual, ?float $forecast, bool $lowerIsBetter): ?bool
    {
        if ($actual === null || $forecast === null) {
            return null;
        }

        if (abs($actual - $forecast) < 0.000001) {
            return true;
        }

        if (abs($actual) >= 1_000_000 || abs($forecast) >= 1_000_000) {
            $displayedActual = (int) round($actual / 1_000_000);
            $displayedForecast = (int) round($forecast / 1_000_000);

            if ($displayedActual === $displayedForecast) {
                return true;
            }

            if ($lowerIsBetter) {
                return $displayedActual < $displayedForecast || $actual <= $forecast;
            }

            return $displayedActual > $displayedForecast || $actual >= $forecast;
        }

        return $lowerIsBetter ? ($actual <= $forecast) : ($actual >= $forecast);
    }

    private function formatAchievement(?float $achievement, ?bool $isAchieved): string
    {
        if ($achievement === null || $isAchieved === null) {
            return '-';
        }

        if ($isAchieved) {
            return number_format(max(100.0, $achievement), 2, ',', '.').'%';
        }

        $formatted = number_format($achievement, 2, ',', '.');
        if ($formatted === '100,00' || $achievement >= 100.0) {
            $formatted = '99,99';
        }

        return $formatted.'%';
    }

    private function achievementColor(?float $achievement, ?bool $isAchieved): string
    {
        if ($achievement === null || $isAchieved === null) {
            return 'muted';
        }

        return $isAchieved ? 'green' : 'red';
    }
}
