<?php

namespace App\Services\Presentation;

class PresentationNarrativeService
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function build(array $payload): array
    {
        $rows = (array) data_get($payload, 'performance_overview.matrix.rows.area6', []);
        $period = (string) data_get($payload, 'meta.period_label', data_get($payload, 'meta.period', '-'));
        $simpanan = $this->aggregateMetric($rows, 'simpanan');
        $os = $this->aggregateMetric($rows, 'os');
        $sml = $this->aggregateMetric($rows, 'sml');
        $npl = $this->aggregateMetric($rows, 'npl');
        $smlRatio = $this->ratio($sml['latest'], $os['latest']);
        $nplRatio = $this->ratio($npl['latest'], $os['latest']);
        $loanLeader = $this->leader($rows, 'os');
        $fundingLeader = $this->leader($rows, 'simpanan');
        $qualityWatch = $this->qualityWatch($rows);
        $financial = $this->financialFacts($payload);
        $product = $this->productFacts($payload);
        $productivity = $this->productivityFacts($payload);
        $digital = $this->digitalFacts($payload);
        $anomalies = array_values(array_merge(
            $this->metricAnomalies($rows, 'simpanan', false),
            $this->metricAnomalies($rows, 'os', false),
            $this->metricAnomalies($rows, 'sml', true),
            $this->metricAnomalies($rows, 'npl', true),
        ));

        $slides = [
            0 => $this->slide(
                'Executive pulse',
                "{$period}: OS {$this->rupiah($os['latest'])} dan simpanan {$this->rupiah($simpanan['latest'])}. "
                    ."Rasio SML {$this->percent($smlRatio)} serta NPL {$this->percent($nplRatio)}; "
                    .($qualityWatch['text'] ?: 'tidak ada outlier kualitas material pada scope Area 6.'),
                $qualityWatch['severity'],
                [$this->fact('OS', $this->rupiah($os['latest'])), $this->fact('Simpanan', $this->rupiah($simpanan['latest']))],
            ),
            1 => $this->slide(
                'Loan performance',
                "OS Area 6 berada di {$this->rupiah($os['latest'])} dengan MtD {$this->signedRupiah($os['mtd'])} "
                    ."dan pencapaian RKA {$this->percent($os['achievement'])}. {$loanLeader['label']} menjadi kontributor terbesar "
                    ."sebesar {$this->rupiah($loanLeader['value'])}.",
                $os['mtd'] < 0 ? 'watch' : 'positive',
                [$this->fact('Penc. RKA', $this->percent($os['achievement'])), $this->fact('Leader', $loanLeader['label'])],
            ),
            2 => $this->slide(
                'Funding performance',
                "Simpanan Area 6 mencapai {$this->rupiah($simpanan['latest'])}; momentum MtD {$this->signedRupiah($simpanan['mtd'])} "
                    ."dengan pencapaian RKA {$this->percent($simpanan['achievement'])}. Kontributor terbesar adalah {$fundingLeader['label']}.",
                $simpanan['mtd'] < 0 ? 'watch' : 'positive',
                [$this->fact('Penc. RKA', $this->percent($simpanan['achievement'])), $this->fact('Leader', $fundingLeader['label'])],
            ),
            3 => $this->slide(
                'Product concentration',
                $product['text'],
                $product['severity'],
                $product['facts'],
            ),
            4 => $this->slide(
                'Portfolio quality',
                "Portofolio lancar diperkirakan {$this->percent(max(0.0, 100.0 - $smlRatio - $nplRatio))}; "
                    ."SML {$this->rupiah($sml['latest'])} ({$this->percent($smlRatio)}) dan NPL {$this->rupiah($npl['latest'])} "
                    ."({$this->percent($nplRatio)}). {$qualityWatch['text']}",
                $qualityWatch['severity'],
                [$this->fact('SML', $this->percent($smlRatio)), $this->fact('NPL', $this->percent($nplRatio))],
            ),
            5 => $this->slide(
                'Branch quadrant',
                "{$loanLeader['label']} memimpin skala OS, sementara watchlist kualitas difokuskan pada {$qualityWatch['entity']}. "
                    .'Kuadran menggunakan OS sebagai sumbu X dan rasio kualitas sebagai sumbu Y.',
                $qualityWatch['severity'],
                [$this->fact('OS leader', $loanLeader['label']), $this->fact('Quality watch', $qualityWatch['entity'])],
            ),
            6 => $this->slide(
                'Funding mix',
                $this->fundingMixNarrative($payload),
                'neutral',
            ),
            7 => $this->slide(
                'Branch action room',
                "Gunakan selector cabang untuk membandingkan posisi, RKA, kualitas, dan momentum. Prioritas awal Area 6: "
                    ."pertahankan kontribusi {$loanLeader['label']} dan tindak lanjuti {$qualityWatch['entity']}.",
                $qualityWatch['severity'],
            ),
            8 => $this->slide(
                'Profitability',
                $financial['text'],
                $financial['severity'],
                $financial['facts'],
            ),
            9 => $this->slide(
                'Performance versus RKA',
                "OS {$this->rupiah($os['latest'])} ({$this->percent($os['achievement'])} RKA) dan simpanan "
                    ."{$this->rupiah($simpanan['latest'])} ({$this->percent($simpanan['achievement'])} RKA). "
                    ."Gunakan toggle untuk melihat perubahan YtD, MtM, dan MtD per cabang.",
                min($os['achievement'], $simpanan['achievement']) < 100 ? 'watch' : 'positive',
            ),
            10 => $this->slide(
                'Segment execution',
                "Kontribusi dan gap setiap segmen mengikuti scope aktif. OS total {$this->rupiah($os['latest'])}; "
                    ."momentum MtD {$this->signedRupiah($os['mtd'])}.",
                $os['mtd'] < 0 ? 'watch' : 'positive',
            ),
            11 => $this->slide(
                'Risk watch',
                "{$qualityWatch['text']} Secara Area 6, SML {$this->percent($smlRatio)} dan NPL {$this->percent($nplRatio)} "
                    ."terhadap total OS.",
                $qualityWatch['severity'],
            ),
            12 => $this->slide(
                'SML early warning',
                "Nominal SML Area 6 {$this->rupiah($sml['latest'])}, bergerak {$this->signedRupiah($sml['mtd'])} MtD. "
                    ."Kenaikan perlu dibaca sebagai tekanan kualitas; penurunan sebagai curing.",
                $sml['mtd'] > 0 ? 'risk' : 'positive',
            ),
            13 => $this->slide(
                'NPL recovery',
                "Nominal NPL Area 6 {$this->rupiah($npl['latest'])}, bergerak {$this->signedRupiah($npl['mtd'])} MtD. "
                    ."Fokus recovery diarahkan pada cabang dengan rasio dan nominal tertinggi.",
                $npl['mtd'] > 0 ? 'risk' : 'positive',
            ),
            14 => $this->slide(
                'KTS decision support',
                'KTS dimuat on-demand berdasarkan kategori membaik/memburuk dan scope ritel/mikro agar deck awal tetap ringan.',
                'neutral',
            ),
            15 => $this->slide(
                'RM productivity',
                $productivity['text'],
                $productivity['severity'],
                $productivity['facts'],
            ),
            16 => $this->slide(
                'Integrated trend',
                'Timeseries menampilkan tiga belas titik bulanan per scope. Perubahan awal-ke-akhir, momentum terakhir, dan puncak seri menjadi dasar tindakan.',
                'neutral',
            ),
            17 => $this->slide(
                'Digital execution',
                $digital['text'],
                $digital['severity'],
                $digital['facts'],
            ),
        ];

        return [
            'engine' => 'deterministic-rule-v1',
            'generated_at' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->toIso8601String(),
            'slides' => $slides,
            'anomalies' => $anomalies,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{latest: float, mtd: float, rka: float, achievement: float}
     */
    private function aggregateMetric(array $rows, string $metric): array
    {
        $latest = 0.0;
        $mtd = 0.0;
        $rka = 0.0;

        foreach ($rows as $row) {
            $latest += (float) data_get($row, "metrics.{$metric}.latest_raw", 0);
            $mtd += (float) data_get($row, "metrics.{$metric}.mtd_raw", 0);
            $rka += (float) data_get($row, "metrics.{$metric}.rka_raw", 0);
        }

        return [
            'latest' => $latest,
            'mtd' => $mtd,
            'rka' => $rka,
            'achievement' => $rka > 0 ? ($latest / $rka) * 100 : 0.0,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{label: string, value: float}
     */
    private function leader(array $rows, string $metric): array
    {
        $leader = collect($rows)->sortByDesc(
            fn (array $row): float => (float) data_get($row, "metrics.{$metric}.latest_raw", 0)
        )->first();

        return [
            'label' => (string) data_get($leader, 'label', '-'),
            'value' => (float) data_get($leader, "metrics.{$metric}.latest_raw", 0),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function metricAnomalies(array $rows, string $metric, bool $inverse): array
    {
        $values = collect($rows)
            ->map(fn (array $row): float => (float) data_get($row, "metrics.{$metric}.latest_raw", 0))
            ->filter(fn (float $value): bool => $value > 0)
            ->values();

        if ($values->count() < 3) {
            return [];
        }

        $mean = (float) $values->avg();
        $variance = (float) $values->map(fn (float $value): float => ($value - $mean) ** 2)->avg();
        $threshold = $mean + (sqrt($variance) * 1.5);

        return collect($rows)
            ->filter(fn (array $row): bool => (float) data_get($row, "metrics.{$metric}.latest_raw", 0) > $threshold)
            ->map(fn (array $row): array => [
                'metric' => $metric,
                'entity' => (string) data_get($row, 'label', '-'),
                'severity' => $inverse ? 'risk' : 'positive',
                'value' => (float) data_get($row, "metrics.{$metric}.latest_raw", 0),
                'threshold' => $threshold,
                'message' => (string) data_get($row, 'label', '-').' berada di atas batas outlier '.$metric.'.',
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{entity: string, text: string, severity: string}
     */
    private function qualityWatch(array $rows): array
    {
        $watch = collect($rows)->map(function (array $row): array {
            $os = (float) data_get($row, 'metrics.os.latest_raw', 0);
            $sml = (float) data_get($row, 'metrics.sml.latest_raw', 0);
            $npl = (float) data_get($row, 'metrics.npl.latest_raw', 0);

            return [
                'label' => (string) data_get($row, 'label', '-'),
                'ratio' => $this->ratio($sml + $npl, $os),
            ];
        })->sortByDesc('ratio')->first();

        $entity = (string) data_get($watch, 'label', '-');
        $ratio = (float) data_get($watch, 'ratio', 0);

        return [
            'entity' => $entity,
            'text' => $ratio > 0
                ? "{$entity} mencatat rasio SML+NPL tertinggi {$this->percent($ratio)} dan menjadi watchlist kualitas."
                : 'Belum ada rasio kualitas yang dapat dihitung.',
            'severity' => $ratio >= 10 ? 'risk' : ($ratio > 0 ? 'watch' : 'neutral'),
        ];
    }

    /**
     * @return array{text: string, severity: string, facts: array<int, array<string, string>>}
     */
    private function financialFacts(array $payload): array
    {
        $cards = collect((array) data_get($payload, 'financial_highlights.cards', []));
        $profit = $cards->firstWhere('key', 'laba_setelah_pajak') ?? $cards->first();
        $nim = $cards->firstWhere('key', 'nim');
        $bopo = $cards->firstWhere('key', 'bopo');

        return [
            'text' => 'Laba setelah pajak '.data_get($profit, 'value', '-')
                .', NIM '.data_get($nim, 'value', '-')
                .', dan BOPO '.data_get($bopo, 'value', '-')
                .'. Pembacaan profitabilitas mengikuti scope dan periode aktif.',
            'severity' => 'neutral',
            'facts' => [
                $this->fact('Laba', (string) data_get($profit, 'value', '-')),
                $this->fact('NIM', (string) data_get($nim, 'value', '-')),
                $this->fact('BOPO', (string) data_get($bopo, 'value', '-')),
            ],
        ];
    }

    /**
     * @return array{text: string, severity: string, facts: array<int, array<string, string>>}
     */
    private function productFacts(array $payload): array
    {
        $products = collect((array) data_get($payload, 'loan_products.cards', []));
        $leader = $products->sortByDesc(fn (array $row): float => (float) ($row['os_raw'] ?? $row['value_raw'] ?? 0))->first();
        $risk = $products->sortByDesc(fn (array $row): float => (float) ($row['npl_pct_raw'] ?? 0))->first();

        return [
            'text' => 'Produk terbesar '.data_get($leader, 'label', '-').' dengan OS '.data_get($leader, 'os', data_get($leader, 'value', '-'))
                .'. Rasio NPL tertinggi berada pada '.data_get($risk, 'label', '-').' sebesar '.data_get($risk, 'npl_pct', '-').'.',
            'severity' => (float) data_get($risk, 'npl_pct_raw', 0) >= 5 ? 'risk' : 'neutral',
            'facts' => [
                $this->fact('OS leader', (string) data_get($leader, 'label', '-')),
                $this->fact('NPL watch', (string) data_get($risk, 'label', '-')),
            ],
        ];
    }

    /**
     * @return array{text: string, severity: string, facts: array<int, array<string, string>>}
     */
    private function productivityFacts(array $payload): array
    {
        $scopes = (array) data_get($payload, 'productivity.scopes.area6.categories', []);
        $items = collect($scopes)->map(function (array $category, string $key): array {
            return [
                'key' => $key,
                'label' => (string) ($category['label'] ?? $key),
                'rm' => (float) data_get($category, 'total.rm_count', 0),
                'os' => (float) data_get($category, 'total.realisasi_os', 0),
                'os_fmt' => (string) data_get($category, 'total.realisasi_os_fmt', '-'),
            ];
        });
        $leader = $items->sortByDesc('os')->first();

        return [
            'text' => $leader
                ? "{$leader['label']} mencatat realisasi terbesar {$leader['os_fmt']} dari {$this->integer($leader['rm'])} RM."
                : 'Data produktivitas RM sedang menunggu payload detail.',
            'severity' => 'neutral',
            'facts' => $leader
                ? [$this->fact('Kategori leader', $leader['label']), $this->fact('Jumlah RM', $this->integer($leader['rm']))]
                : [],
        ];
    }

    /**
     * @return array{text: string, severity: string, facts: array<int, array<string, string>>}
     */
    private function digitalFacts(array $payload): array
    {
        $cards = collect((array) data_get($payload, 'digital_strategy.cards', []))
            ->filter(fn (array $card): bool => ($card['available'] ?? true) !== false);
        $ranked = $cards->map(function (array $card): array {
            preg_match('/-?[\d.,]+/', (string) ($card['trend'] ?? ''), $matches);
            $number = isset($matches[0])
                ? (float) str_replace(['.', ','], ['', '.'], $matches[0])
                : 0.0;
            $card['trend_raw'] = $number;

            return $card;
        });
        $strongest = $ranked->sortByDesc('trend_raw')->first();
        $weakest = $ranked->sortBy('trend_raw')->first();

        return [
            'text' => "{$cards->count()} strategi memiliki sumber aktif. Momentum tertinggi "
                .data_get($strongest, 'title', '-').' '.data_get($strongest, 'trend', '-')
                .'; prioritas intervensi '.data_get($weakest, 'title', '-').' '.data_get($weakest, 'trend', '-').'.',
            'severity' => (float) data_get($weakest, 'trend_raw', 0) < 0 ? 'watch' : 'positive',
            'facts' => [
                $this->fact('Momentum', (string) data_get($strongest, 'title', '-')),
                $this->fact('Intervensi', (string) data_get($weakest, 'title', '-')),
            ],
        ];
    }

    private function fundingMixNarrative(array $payload): string
    {
        $cards = collect((array) data_get($payload, 'savings_breakdown.cards', []));
        $casa = $cards->firstWhere('key', 'casa');
        $tabungan = $cards->firstWhere('key', 'tabungan');
        $deposito = $cards->firstWhere('key', 'deposito');

        return 'CASA '.data_get($casa, 'pct', '-').' dengan tabungan '.data_get($tabungan, 'pct', '-')
            .' dan deposito '.data_get($deposito, 'pct', '-').'. Funding mix yang sehat menjaga biaya dana tetap terkendali.';
    }

    /**
     * @param array<int, array<string, string>> $facts
     * @return array<string, mixed>
     */
    private function slide(string $headline, string $body, string $severity = 'neutral', array $facts = []): array
    {
        return compact('headline', 'body', 'severity', 'facts');
    }

    /**
     * @return array{label: string, value: string}
     */
    private function fact(string $label, string $value): array
    {
        return compact('label', 'value');
    }

    private function ratio(float $numerator, float $denominator): float
    {
        return $denominator > 0 ? ($numerator / $denominator) * 100 : 0.0;
    }

    private function rupiah(float $million): string
    {
        $value = $million * 1_000_000;
        $absolute = abs($value);
        if ($absolute >= 1_000_000_000_000) {
            return 'Rp'.number_format($value / 1_000_000_000_000, 2, ',', '.').' T';
        }
        if ($absolute >= 1_000_000_000) {
            return 'Rp'.number_format($value / 1_000_000_000, 2, ',', '.').' M';
        }

        return 'Rp'.number_format($value / 1_000_000, 2, ',', '.').' Jt';
    }

    private function signedRupiah(float $million): string
    {
        if ($million === 0.0) {
            return 'Rp0';
        }

        return ($million > 0 ? '+' : '-').$this->rupiah(abs($million));
    }

    private function percent(float $value): string
    {
        return number_format($value, 2, ',', '.').'%';
    }

    private function integer(float $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}

