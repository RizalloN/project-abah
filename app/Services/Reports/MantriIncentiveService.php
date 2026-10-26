<?php

namespace App\Services\Reports;

use App\Support\MantriPerformanceBasis;
use App\Support\ReportCacheVersion;
use App\Support\UserBranchScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Insentif Mantri (Sosialisasi Insentif Mantri 2026, Micro Business Development Group).
 *
 * Syarat utama (keduanya wajib):
 *  - Pertumbuhan: rata-rata Net Disbursement 3 bulan periode penilaian >= Rp800 juta.
 *  - Kualitas: vintage MOB 6 (plafond pemburukan / plafond realisasi) <= 1%, posisi bulan
 *    terakhir periode insentif, atas booking 12 s.d. 7 bulan sebelum akhir periode.
 *
 * Net Disbursement dihitung per bulan, per CIF debitur, berdasarkan PN prakarsa:
 *  - Baru / lunas putus / musiman (pelunasan beda bulan): seluruh plafond.
 *  - Suplesi / pelunasan di bulan yang sama: plafond - sisa OS saat pelunasan, minimal 0.
 *
 * Sumber: daily_loan_dinamis. Periode LW321PN yang lebih baru dari Daily Loan Dinamis
 * dimaterialisasi otomatis ke tabel yang sama (uniqueid_namareport berawalan "LW321PN:").
 */
final class MantriIncentiveService
{
    public const SOURCE_TABLE = 'daily_loan_dinamis';

    public const LW321_TABLE = 'lw321pn';

    public const LW321_ID_PREFIX = 'LW321PN:';

    public const BRANCHES = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];

    /** Kupedes, KUR Mikro, dan KPP (produk KPR pada segmen mikro). */
    public const PRODUCTS = ['KUPEDES', 'KURMIKRO', 'KPR'];

    public const MIN_AVG_NET_DISBURSEMENT = 800000000.0;

    public const MAX_MOB6_RATIO = 1.0;

    /** Kolektibilitas minimum yang dihitung sebagai pemburukan pada MOB 6 (DPK ke atas). */
    public const PEMBURUKAN_MIN_KOLEK = 2;

    public const MIN_TENURE_DAYS = 30;

    /** Diurutkan dari tier tertinggi; batas bawah eksklusif kecuali tier pertama (>= 800 jt). */
    public const TIERS = [
        ['key' => 'tier_3', 'label' => '> 1 M', 'above' => 1000000000.0, 'rate' => 0.005],
        ['key' => 'tier_2', 'label' => '> 900 Jt - 1 M', 'above' => 900000000.0, 'rate' => 0.004],
        ['key' => 'tier_1', 'label' => '800 - 900 Jt', 'above' => null, 'rate' => 0.003],
    ];

    private const KUR_RITEL_DESCRIPTION = 'KREDITMIKROKURRITEL2015';

    private const CACHE_SECONDS = 1800;

    private const CHUNK = 900;

    public function __construct(private readonly MantriPerformanceBasis $basis)
    {
    }

    /** @return array<int, string> */
    public function availablePeriods(): array
    {
        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            return [];
        }

        return Cache::remember($this->cacheKey('periods'), 600, function (): array {
            return DB::table(self::SOURCE_TABLE)
                ->where('periode', '>=', now()->subMonthsNoOverflow(15)->startOfMonth()->toDateString())
                ->distinct()
                ->orderByDesc('periode')
                ->pluck('periode')
                ->map(fn ($value): string => substr((string) $value, 0, 10))
                ->filter()
                ->values()
                ->all();
        });
    }

    /** @return array{latest_daily:?string, latest_lw321:?string, pending_lw321:bool} */
    public function sourceFreshness(): array
    {
        $latestDaily = $this->availablePeriods()[0] ?? null;
        $latestLw = null;
        if (Schema::hasTable(self::LW321_TABLE)) {
            $value = DB::table(self::LW321_TABLE)->max('periode');
            $latestLw = $value !== null ? substr((string) $value, 0, 10) : null;
        }

        return [
            'latest_daily' => $latestDaily,
            'latest_lw321' => $latestLw,
            'pending_lw321' => $latestLw !== null && ($latestDaily === null || $latestLw > $latestDaily),
        ];
    }

    public function positionSource(string $position): string
    {
        return Cache::remember($this->cacheKey('source', $position), 600, function () use ($position): string {
            $fromLw = DB::table(self::SOURCE_TABLE)
                ->where('periode', $position)
                ->where('uniqueid_namareport', 'like', self::LW321_ID_PREFIX.'%')
                ->exists();

            return $fromLw ? 'LW321PN' : 'Daily Loan Dinamis';
        });
    }

    /**
     * Seluruh perhitungan area (sebelum filter cabang/unit) untuk satu posisi.
     *
     * @return array<string, mixed>
     */
    public function build(string $position): array
    {
        $position = Carbon::parse($position)->toDateString();
        $rosterFingerprint = app(\App\Support\MantriProductivityReference::class)->fingerprint($position);

        return $this->rememberResult(
            $this->cacheKey('payload', $position, UserBranchScope::cacheKey(), $rosterFingerprint),
            function () use ($position): array {
                return $this->buildUncached($position);
            },
            $this->cacheKey('build', $position, $rosterFingerprint)
        );
    }

    private function buildUncached(string $position): array
    {
        $positions = $this->availablePeriods();
        $window = $this->window($position, $positions);

        $monthly = [];
        foreach ($window['months'] as $month) {
            $monthly[$month['key']] = $month['position'] !== null
                ? $this->monthNetDisbursement($month['start'], $month['end'], $month['position'], $positions)
                : [];
        }

        $mob = $this->mobBookingsForMonths($window['mob_months']);

        return $this->assemble($position, $window, $monthly, $mob);
    }

    /**
     * Nominatif per rekening untuk verifikasi (Net Disbursement atau MOB 6).
     *
     * @return array<int, array<string, mixed>>
     */
    public function nominatif(string $position, string $type): array
    {
        $inactivePns = array_fill_keys($this->basis->inactivePns(), true);
        $positions = $this->availablePeriods();
        $window = $this->window(Carbon::parse($position)->toDateString(), $positions);
        $rows = [];

        if ($type === 'mob6') {
            $bookingsByMonth = $this->mobBookingsForMonths($window['mob_months']);
            foreach ($window['mob_months'] as $mobMonth) {
                if ($mobMonth['booking_position'] === null && $mobMonth['measure_position'] === null) {
                    continue;
                }
                foreach ($bookingsByMonth[$mobMonth['key']] as $account) {
                    if (isset($inactivePns[$this->basis->pnKey($account['pn'] ?? '')])) {
                        continue;
                    }
                    $rows[] = $account + [
                        'bulan_booking' => $mobMonth['key'],
                        'posisi_mob' => $mobMonth['measure_position'],
                        'mob' => $mobMonth['mob'],
                    ];
                }
            }

            return $rows;
        }

        foreach ($window['months'] as $month) {
            if ($month['position'] === null) {
                continue;
            }
            foreach ($this->monthNetDisbursement($month['start'], $month['end'], $month['position'], $positions) as $booking) {
                if (isset($inactivePns[$this->basis->pnKey($booking['pn'] ?? '')])) {
                    continue;
                }
                $rows[] = $booking + ['bulan' => $month['key'], 'posisi' => $month['position']];
            }
        }

        return $rows;
    }

    /**
     * Periode penilaian = kuartal dari posisi terpilih (Q4 2026 = Okt-Des 2026).
     *
     * @param  array<int, string>  $positions
     * @return array<string, mixed>
     */
    public function window(string $position, array $positions): array
    {
        $selected = Carbon::parse($position)->startOfDay();
        $quarterStart = $selected->copy()->firstOfQuarter();
        $quarterEnd = $selected->copy()->lastOfQuarter();

        $months = [];
        for ($i = 0; $i < 3; $i++) {
            $start = $quarterStart->copy()->addMonthsNoOverflow($i)->startOfMonth();
            $end = $start->copy()->endOfMonth()->startOfDay();
            $cap = $end->lt($selected) ? $end : $selected;
            $monthPosition = $start->gt($selected) ? null : $this->latestPositionBetween($positions, $start->toDateString(), $cap->toDateString());
            $months[] = [
                'key' => $start->format('Y-m'),
                'label' => $start->locale('id')->translatedFormat('M Y'),
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'position' => $monthPosition,
                'status' => match (true) {
                    $start->gt($selected) => 'belum',
                    $end->lte($selected) => 'final',
                    default => 'berjalan',
                },
            ];
        }

        // Kualitas MOB 6: booking bulan (akhir periode - 11) s.d. (akhir periode - 6), diukur pada MOB 6.
        $mobMonths = [];
        for ($i = 11; $i >= 6; $i--) {
            $bookingStart = $quarterEnd->copy()->startOfMonth()->subMonthsNoOverflow($i);
            $bookingEnd = $bookingStart->copy()->endOfMonth()->startOfDay();
            $measureMonthEnd = $bookingStart->copy()->addMonthsNoOverflow(6)->endOfMonth()->startOfDay();
            $measureCap = $measureMonthEnd->lt($selected) ? $measureMonthEnd : $selected;
            $measurePosition = $this->latestPositionBetween($positions, $bookingStart->toDateString(), $measureCap->toDateString());
            $bookingPosition = $this->latestPositionBetween(
                $positions,
                $bookingStart->toDateString(),
                ($bookingEnd->lt($selected) ? $bookingEnd : $selected)->toDateString()
            );
            $mobReached = $measurePosition !== null
                ? max(0, (int) $bookingStart->diffInMonths(Carbon::parse($measurePosition)->startOfMonth()))
                : 0;

            $mobMonths[] = [
                'key' => $bookingStart->format('Y-m'),
                'label' => $bookingStart->locale('id')->translatedFormat('M Y'),
                'start' => $bookingStart->toDateString(),
                'end' => $bookingEnd->toDateString(),
                'booking_position' => $bookingPosition,
                'measure_position' => $measurePosition,
                'measure_label' => $measureMonthEnd->locale('id')->translatedFormat('M Y'),
                'mob' => min(6, $mobReached),
                'final' => $measureMonthEnd->lte($selected),
            ];
        }

        $elapsed = count(array_filter($months, fn (array $month): bool => $month['status'] !== 'belum'));

        return [
            'position' => $selected->toDateString(),
            'quarter' => (int) $selected->quarter,
            'year' => (int) $selected->year,
            'label' => 'Q'.$selected->quarter.' '.$selected->year,
            'start' => $quarterStart->toDateString(),
            'end' => $quarterEnd->toDateString(),
            'months' => $months,
            'elapsed_months' => max(1, $elapsed),
            'mob_months' => $mobMonths,
        ];
    }

    public function tierFor(float $averageNet): ?array
    {
        if ($averageNet < self::MIN_AVG_NET_DISBURSEMENT) {
            return null;
        }

        foreach (self::TIERS as $tier) {
            if ($tier['above'] === null || $averageNet > $tier['above']) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * Net Disbursement satu bulan per booking (rekening) dengan pengurang OS per CIF.
     *
     * @param  array<int, string>  $positions
     * @return array<int, array<string, mixed>>
     */
    public function monthNetDisbursement(string $monthStart, string $monthEnd, string $position, array $positions): array
    {
        return $this->rememberResult($this->cacheKey('nd', $monthStart, $position), function () use ($monthStart, $monthEnd, $position, $positions): array {
            $bookings = $this->monthBookings($monthStart, $monthEnd, $position);
            if ($bookings === []) {
                return [];
            }

            $earlierPositions = array_values(array_filter(
                $positions,
                fn (string $value): bool => $value < $position && $value >= Carbon::parse($monthStart)->subMonthNoOverflow()->startOfMonth()->toDateString()
            ));
            $previousMonthEnd = $this->latestPositionBetween(
                $positions,
                Carbon::parse($monthStart)->subMonthNoOverflow()->startOfMonth()->toDateString(),
                Carbon::parse($monthStart)->subDay()->toDateString()
            );
            $earlierPositions = array_values(array_filter(
                $earlierPositions,
                fn (string $value): bool => $value >= $monthStart || $value === $previousMonthEnd
            ));

            $this->resolveMissingPn($bookings, $earlierPositions);
            $closedByCif = $this->closedExposureByCif($bookings, $monthStart, $position, $earlierPositions);

            // Per CIF: ND = max(0, total plafond baru - sisa OS pelunasan), dialokasikan proporsional plafond.
            $byCif = [];
            foreach ($bookings as $account => $booking) {
                $byCif[$booking['cif'] !== '' ? $booking['cif'] : 'ACC:'.$account][] = $account;
            }

            foreach ($byCif as $cif => $accounts) {
                $plafond = array_sum(array_map(fn (string $account): float => $bookings[$account]['plafon'], $accounts));
                $closed = (float) ($closedByCif[$cif] ?? 0.0);
                $net = max(0.0, $plafond - $closed);
                foreach ($accounts as $account) {
                    $share = $plafond > 0 ? $bookings[$account]['plafon'] / $plafond : 0.0;
                    $bookings[$account]['os_pelunasan'] = round($closed * $share, 2);
                    $bookings[$account]['net'] = round($net * $share, 2);
                    $bookings[$account]['kategori'] = $closed > 0 ? 'Suplesi / Pelunasan Bulan Sama' : 'New Booking';
                }
            }

            return array_values($bookings);
        });
    }

    /** @return array<string, array<string, mixed>> */
    private function monthBookings(string $monthStart, string $monthEnd, string $position): array
    {
        $rows = DB::table(self::SOURCE_TABLE)
            ->where('periode', $position)
            ->where('segmen_kinerja', 'MICRO')
            ->whereIn('produk_kinerja', self::PRODUCTS)
            ->whereBetween('tgl_realisasi', [$monthStart, $monthEnd])
            ->get([
                'nomor_rekening1', 'cifno', 'plafon', 'tgl_realisasi', 'produk_kinerja', 'description',
                'pn_pemrakarsa1', 'pn_pengelola1', 'cabang1', 'unit1', 'nama_debitur1',
            ]);

        $bookings = [];
        foreach ($rows as $row) {
            if ($this->isKurRitel($row)) {
                continue;
            }
            $account = $this->canonicalAccount($row->nomor_rekening1 ?? null);
            if ($account === '') {
                continue;
            }
            $plafond = (float) ($row->plafon ?? 0);
            if (isset($bookings[$account]) && $bookings[$account]['plafon'] >= $plafond) {
                continue;
            }
            $bookings[$account] = [
                'account' => $account,
                'raw_account' => trim((string) $row->nomor_rekening1),
                'cif' => $this->canonicalCif($row->cifno ?? null),
                'raw_cif' => trim((string) ($row->cifno ?? '')),
                'nama_debitur' => trim((string) ($row->nama_debitur1 ?? '')),
                'produk' => (string) ($row->produk_kinerja ?? ''),
                'tgl_realisasi' => substr((string) $row->tgl_realisasi, 0, 10),
                'plafon' => $plafond,
                'pn' => $this->basis->pnKey($row->pn_pemrakarsa1 ?? ''),
                'pn_label' => trim((string) ($row->pn_pemrakarsa1 ?? '')),
                'pn_source' => 'prakarsa',
                'fallback_pn' => $this->basis->pnKey($row->pn_pengelola1 ?? ''),
                'fallback_label' => trim((string) ($row->pn_pengelola1 ?? '')),
                'cabang' => $this->normalize($row->cabang1 ?? ''),
                'unit' => $this->normalize($row->unit1 ?? ''),
                'os_pelunasan' => 0.0,
                'net' => $plafond,
                'kategori' => 'New Booking',
            ];
        }

        return $bookings;
    }

    /**
     * PN prakarsa pada posisi terbaru bisa masih kosong (data PN menyusul). Ambil dari
     * posisi sebelumnya; bila tetap kosong gunakan PN pengelola.
     *
     * @param  array<string, array<string, mixed>>  $bookings
     * @param  array<int, string>  $earlierPositions
     */
    private function resolveMissingPn(array &$bookings, array $earlierPositions): void
    {
        $missing = array_keys(array_filter($bookings, fn (array $booking): bool => $booking['pn'] === ''));
        foreach (array_reverse($earlierPositions) as $earlier) {
            if ($missing === []) {
                break;
            }
            foreach (array_chunk($missing, self::CHUNK) as $chunk) {
                $rawAccounts = array_values(array_unique(array_map(fn (string $account): string => $bookings[$account]['raw_account'], $chunk)));
                DB::table(self::SOURCE_TABLE)
                    ->where('periode', $earlier)
                    ->whereIn('nomor_rekening1', $rawAccounts)
                    ->get(['nomor_rekening1', 'pn_pemrakarsa1'])
                    ->each(function (object $row) use (&$bookings): void {
                        $account = $this->canonicalAccount($row->nomor_rekening1 ?? null);
                        $pn = $this->basis->pnKey($row->pn_pemrakarsa1 ?? '');
                        if ($pn !== '' && isset($bookings[$account]) && $bookings[$account]['pn'] === '') {
                            $bookings[$account]['pn'] = $pn;
                            $bookings[$account]['pn_label'] = trim((string) $row->pn_pemrakarsa1);
                        }
                    });
            }
            $missing = array_values(array_filter($missing, fn (string $account): bool => $bookings[$account]['pn'] === ''));
        }

        foreach ($missing as $account) {
            if ($bookings[$account]['fallback_pn'] !== '') {
                $bookings[$account]['pn'] = $bookings[$account]['fallback_pn'];
                $bookings[$account]['pn_label'] = $bookings[$account]['fallback_label'];
                $bookings[$account]['pn_source'] = 'pengelola';
            } else {
                $bookings[$account]['pn_source'] = 'kosong';
            }
        }

        foreach ($bookings as &$booking) {
            unset($booking['fallback_pn'], $booking['fallback_label']);
        }
        unset($booking);
    }

    /**
     * Sisa OS saat pelunasan per CIF: rekening mikro milik CIF yang sama yang terlihat pada
     * posisi akhir bulan sebelumnya / posisi di bulan berjalan, tetapi sudah lunas (tidak ada
     * atau OS 0) pada posisi bulan ini. Nilai yang dipakai = OS pada posisi terakhir sebelum
     * lunas. Rekening yang direalisasi ulang (top up di nomor yang sama) dikurangi OS lamanya.
     *
     * @param  array<string, array<string, mixed>>  $bookings
     * @param  array<int, string>  $earlierPositions
     * @return array<string, float>
     */
    private function closedExposureByCif(array $bookings, string $monthStart, string $position, array $earlierPositions): array
    {
        if ($earlierPositions === []) {
            return [];
        }

        $rawCifs = [];
        foreach ($bookings as $booking) {
            if ($booking['cif'] === '') {
                continue;
            }
            $rawCifs[$booking['raw_cif']] = true;
            $rawCifs[$booking['cif']] = true;
        }
        $rawCifs = array_keys($rawCifs);
        if ($rawCifs === []) {
            return [];
        }

        // Posisi terakhir tiap rekening lama (realisasi sebelum bulan ini) sebelum posisi bulan ini.
        // Rekening yang direalisasi dan lunas di bulan yang sama tidak menjadi pengurang.
        $lastSeen = [];
        foreach (array_chunk($rawCifs, self::CHUNK) as $chunk) {
            DB::table(self::SOURCE_TABLE)
                ->whereIn('periode', $earlierPositions)
                ->whereIn('cifno', $chunk)
                ->where('segmen_kinerja', 'MICRO')
                ->where('produk_kinerja', '<>', 'BRIGUNAMIKRO')
                ->where('tgl_realisasi', '<', $monthStart)
                ->get(['periode', 'cifno', 'nomor_rekening1', 'baki_debet1'])
                ->each(function (object $row) use (&$lastSeen): void {
                    $account = $this->canonicalAccount($row->nomor_rekening1 ?? null);
                    if ($account === '') {
                        return;
                    }
                    $periode = substr((string) $row->periode, 0, 10);
                    if (isset($lastSeen[$account]) && $lastSeen[$account]['periode'] >= $periode) {
                        return;
                    }
                    $lastSeen[$account] = [
                        'periode' => $periode,
                        'cif' => $this->canonicalCif($row->cifno ?? null),
                        'os' => (float) ($row->baki_debet1 ?? 0),
                    ];
                });
        }
        if ($lastSeen === []) {
            return [];
        }

        // Kehadiran rekening pada posisi bulan ini (seluruh segmen: pindah produk/uker tetap dianggap hidup).
        $current = [];
        foreach (array_chunk($rawCifs, self::CHUNK) as $chunk) {
            DB::table(self::SOURCE_TABLE)
                ->where('periode', $position)
                ->whereIn('cifno', $chunk)
                ->get(['nomor_rekening1', 'baki_debet1'])
                ->each(function (object $row) use (&$current): void {
                    $account = $this->canonicalAccount($row->nomor_rekening1 ?? null);
                    if ($account !== '') {
                        $current[$account] = max($current[$account] ?? 0.0, (float) ($row->baki_debet1 ?? 0));
                    }
                });
        }

        $closedByCif = [];
        foreach ($lastSeen as $account => $seen) {
            if ($seen['cif'] === '' || $seen['os'] <= 0) {
                continue;
            }
            // Top up pada nomor rekening yang sama: OS lama tetap menjadi pengurang.
            $isRebooked = isset($bookings[$account]);
            $isClosed = ! isset($bookings[$account]) && (! isset($current[$account]) || $current[$account] <= 0);
            if ($isRebooked || $isClosed) {
                $closedByCif[$seen['cif']] = ($closedByCif[$seen['cif']] ?? 0.0) + $seen['os'];
            }
        }

        return $closedByCif;
    }

    /**
     * Booking satu bulan beserta kolektibilitas pada posisi MOB 6 (point-in-time).
     *
     * @param  array<string, mixed>  $mobMonth
     * @return array<int, array<string, mixed>>
     */
    public function mobBookings(array $mobMonth): array
    {
        $mobMonth['key'] ??= substr($mobMonth['start'], 0, 7);

        return $this->mobBookingsForMonths([$mobMonth])[$mobMonth['key']];
    }

    /**
     * Read each source position once, including cohorts that share a running MOB position.
     * Both booking-only (closed) and measure-only (late imported) accounts must be retained.
     *
     * @param  array<int, array<string, mixed>>  $months
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function mobBookingsForMonths(array $months): array
    {
        $results = [];
        $missing = [];
        $keys = [];
        foreach ($months as $month) {
            $key = $keys[$month['key']] = $this->mobCacheKey($month);
            $cached = Cache::get($key);
            if (is_array($cached)) {
                $results[$month['key']] = $cached;
            } elseif ($month['booking_position'] === null && $month['measure_position'] === null) {
                $results[$month['key']] = [];
            } else {
                $missing[$month['key']] = $month;
            }
        }
        if ($missing === []) {
            return $results;
        }

        $lockKey = 'insentif_mantri:mob-build:'.sha1(json_encode($keys));

        return Cache::lock($lockKey, 300)->block(120, function () use ($results, $missing, $keys): array {
            $positions = [];
            $accountsByMonth = [];
            foreach ($missing as $monthKey => $month) {
                $cached = Cache::get($keys[$monthKey]);
                if (is_array($cached)) {
                    $results[$monthKey] = $cached;
                    continue;
                }
                $accountsByMonth[$monthKey] = [];
                foreach (array_unique(array_filter([$month['booking_position'], $month['measure_position']])) as $position) {
                    $positions[$position][$monthKey] = $month;
                }
            }
            // Visit booking before measurement, as in the original date-sorted reducer.
            ksort($positions);
            foreach ($positions as $position => $cohorts) {
                $rows = DB::table(self::SOURCE_TABLE)
                    ->where('periode', $position)
                    ->where('segmen_kinerja', 'MICRO')
                    ->whereIn('produk_kinerja', self::PRODUCTS)
                    ->where(function ($query) use ($cohorts): void {
                        foreach ($cohorts as $cohort) {
                            $query->orWhereBetween('tgl_realisasi', [$cohort['start'], $cohort['end']]);
                        }
                    })
                    ->get([
                        'periode', 'nomor_rekening1', 'cifno', 'plafon', 'tgl_realisasi', 'produk_kinerja', 'description',
                        'pn_pemrakarsa1', 'pn_pengelola1', 'kolek', 'baki_debet1', 'nama_debitur1',
                    ]);

                foreach ($rows as $row) {
                    $monthKey = substr((string) $row->tgl_realisasi, 0, 7);
                    if (isset($cohorts[$monthKey])) {
                        $this->addMobAccount($accountsByMonth[$monthKey], $row, $cohorts[$monthKey]);
                    }
                }
                unset($rows);
            }

            foreach ($accountsByMonth as $monthKey => $accounts) {
                foreach ($accounts as &$entry) {
                    $entry['pemburukan'] = $entry['kolek_mob'] !== null && $entry['kolek_mob'] >= self::PEMBURUKAN_MIN_KOLEK
                        ? $entry['plafon'] : 0.0;
                }
                unset($entry);
                $results[$monthKey] = array_values($accounts);
                Cache::put($keys[$monthKey], $results[$monthKey], self::CACHE_SECONDS);
            }

            return $results;
        });
    }

    /** @param array<string, array<string, mixed>> $accounts */
    private function addMobAccount(array &$accounts, object $row, array $mobMonth): void
    {
        if ($this->isKurRitel($row)) {
            return;
        }
        $account = $this->canonicalAccount($row->nomor_rekening1 ?? null);
        if ($account === '') {
            return;
        }
        $periode = substr((string) $row->periode, 0, 10);
        $entry = $accounts[$account] ?? [
            'account' => $account,
            'cif' => $this->canonicalCif($row->cifno ?? null),
            'nama_debitur' => trim((string) ($row->nama_debitur1 ?? '')),
            'produk' => (string) ($row->produk_kinerja ?? ''),
            'tgl_realisasi' => substr((string) $row->tgl_realisasi, 0, 10),
            'plafon' => 0.0,
            'pn' => '',
            'pn_label' => '',
            'kolek_mob' => null,
            'os_mob' => null,
        ];
        $entry['plafon'] = max($entry['plafon'], (float) ($row->plafon ?? 0));
        $prakarsa = $this->basis->pnKey($row->pn_pemrakarsa1 ?? '');
        if ($prakarsa !== '') {
            $entry['pn'] = $prakarsa;
            $entry['pn_label'] = trim((string) $row->pn_pemrakarsa1);
        } elseif ($entry['pn'] === '') {
            $entry['pn'] = $this->basis->pnKey($row->pn_pengelola1 ?? '');
            $entry['pn_label'] = trim((string) ($row->pn_pengelola1 ?? ''));
        }
        if ($periode === $mobMonth['measure_position']) {
            $entry['kolek_mob'] = (int) ($row->kolek ?? 1);
            $entry['os_mob'] = (float) ($row->baki_debet1 ?? 0);
        }
        $accounts[$account] = $entry;
    }

    private function mobCacheKey(array $month): string
    {
        return $this->cacheKey('mob', $month['start'], $month['booking_position'] ?? '-', $month['measure_position'] ?? '-');
    }

    /**
     * @param  array<string, mixed>  $window
     * @param  array<string, array<int, array<string, mixed>>>  $monthly
     * @param  array<string, array<int, array<string, mixed>>>  $mob
     * @return array<string, mixed>
     */
    private function assemble(string $position, array $window, array $monthly, array $mob): array
    {
        $roster = $this->roster($position);
        $inactivePns = array_fill_keys($this->basis->inactivePns(), true);
        $branches = $this->scopedBranches();
        $monthKeys = array_column($window['months'], 'key');
        $mobKeys = array_column($window['mob_months'], 'key');

        $blank = fn (): array => [
            'nd' => array_fill_keys($monthKeys, 0.0),
            'nd_deb' => array_fill_keys($monthKeys, 0),
            'mob_plafon' => array_fill_keys($mobKeys, 0.0),
            'mob_pemburukan' => array_fill_keys($mobKeys, 0.0),
        ];

        $metrics = [];
        $outside = ['nd' => 0.0, 'deb' => 0, 'pn' => [], 'no_pn_nd' => 0.0, 'no_pn_deb' => 0];
        foreach ($monthly as $monthKey => $bookings) {
            foreach ($bookings as $booking) {
                $pn = (string) $booking['pn'];
                if (isset($inactivePns[$this->basis->pnKey($pn)])) {
                    continue;
                }
                if ($pn === '' || ! isset($roster[$pn])) {
                    if (in_array($booking['cabang'], $branches, true)) {
                        if ($pn === '') {
                            $outside['no_pn_nd'] += (float) $booking['net'];
                            $outside['no_pn_deb']++;
                        } else {
                            $outside['nd'] += (float) $booking['net'];
                            $outside['deb']++;
                            $outside['pn'][$pn] = true;
                        }
                    }
                    continue;
                }
                $metrics[$pn] ??= $blank();
                $metrics[$pn]['nd'][$monthKey] += (float) $booking['net'];
                $metrics[$pn]['nd_deb'][$monthKey]++;
            }
        }
        foreach ($mob as $mobKey => $accounts) {
            foreach ($accounts as $account) {
                $pn = (string) $account['pn'];
                if (! isset($roster[$pn])) {
                    continue;
                }
                $metrics[$pn] ??= $blank();
                $metrics[$pn]['mob_plafon'][$mobKey] += (float) $account['plafon'];
                $metrics[$pn]['mob_pemburukan'][$mobKey] += (float) $account['pemburukan'];
            }
        }

        $quarterDays = Carbon::parse($window['start'])->diffInDays(Carbon::parse($window['end'])) + 1;
        $rows = [];
        foreach ($roster as $person) {
            $pn = (string) $person['pn'];
            $metric = $metrics[$pn] ?? $blank();
            $totalNd = array_sum($metric['nd']);
            $average = $totalNd / 3;
            $runRate = $totalNd / $window['elapsed_months'];
            $mobPlafon = array_sum($metric['mob_plafon']);
            $mobBad = array_sum($metric['mob_pemburukan']);
            $mobRatio = $mobPlafon > 0 ? ($mobBad / $mobPlafon) * 100 : 0.0;

            $tenure = $this->tenure($person['tmt_jabatan'], $window['start'], $window['end'], $quarterDays);
            $ndOk = $average >= self::MIN_AVG_NET_DISBURSEMENT;
            $mobOk = $mobRatio <= self::MAX_MOB6_RATIO;
            $isPermanent = $person['status_key'] === 'tetap';
            $eligible = $ndOk && $mobOk && $isPermanent && $tenure['eligible'];
            $tier = $eligible ? $this->tierFor($average) : null;
            $nominal = $tier !== null ? $average * $tier['rate'] * $tenure['factor'] : 0.0;
            $runRateTier = $this->tierFor($runRate);

            $reasons = [];
            if (! $isPermanent) {
                $reasons[] = 'Bukan pekerja tetap';
            }
            if (! $tenure['eligible']) {
                $reasons[] = 'Masa kerja < 30 hari';
            }
            if (! $ndOk) {
                $reasons[] = 'Avg ND < 800 Jt';
            }
            if (! $mobOk) {
                $reasons[] = 'MOB 6 > 1%';
            }

            $rows[] = [
                'pn' => $pn,
                'nama' => $person['name'],
                'cabang' => $person['branch'],
                'unit' => $person['unit'],
                'bc' => $person['bc'],
                'status_pekerja' => $person['status'],
                'status_key' => $person['status_key'],
                'tmt_jabatan' => $person['tmt_jabatan'],
                'nd' => $metric['nd'],
                'nd_deb' => $metric['nd_deb'],
                'nd_total' => $totalNd,
                'nd_avg' => $average,
                'nd_run_rate' => $runRate,
                'nd_gap_total' => max(0.0, self::MIN_AVG_NET_DISBURSEMENT * 3 - $totalNd),
                'nd_achievement' => ($average / self::MIN_AVG_NET_DISBURSEMENT) * 100,
                'mob_plafon_months' => $metric['mob_plafon'],
                'mob_pemburukan_months' => $metric['mob_pemburukan'],
                'mob_plafon' => $mobPlafon,
                'mob_pemburukan' => $mobBad,
                'mob_ratio' => $mobRatio,
                'nd_ok' => $ndOk,
                'mob_ok' => $mobOk,
                'tenure_factor' => $tenure['factor'],
                'tenure_note' => $tenure['note'],
                'eligible' => $eligible,
                'reasons' => $reasons,
                'tier_key' => $tier['key'] ?? null,
                'tier_label' => $tier['label'] ?? null,
                'rate' => $tier['rate'] ?? null,
                'nominal' => round($nominal),
                'run_rate_rate' => $runRateTier['rate'] ?? null,
                'run_rate_nominal' => $runRateTier !== null && $mobOk && $isPermanent && $tenure['eligible']
                    ? round($runRate * $runRateTier['rate'] * $tenure['factor']) : 0.0,
            ];
        }

        usort($rows, fn (array $left, array $right): int => [$left['cabang'], $left['unit'], -$left['nd_avg'], $left['nama']]
            <=> [$right['cabang'], $right['unit'], -$right['nd_avg'], $right['nama']]);

        return [
            'position' => $position,
            'window' => $window,
            'rows' => $rows,
            'outside_roster' => [
                'nd' => $outside['nd'],
                'deb' => $outside['deb'],
                'pn_count' => count($outside['pn']),
                'no_pn_nd' => $outside['no_pn_nd'],
                'no_pn_deb' => $outside['no_pn_deb'],
            ],
        ];
    }

    /**
     * Mantri mikro (bukan Mantri Briguna) di 4 cabang area dari referensi BRIHC pemasar.
     *
     * @return array<string, array<string, string>>
     */
    public function roster(?string $period = null): array
    {
        if ($period !== null && app(\App\Support\MantriProductivityReference::class)->dataForPeriod($period) !== null) {
            $branches = $this->scopedBranches();

            return collect($this->basis->rosterState($period)['people'])
                ->filter(fn (array $person): bool => in_array($person['branch'], $branches, true))
                ->map(fn (array $person): array => array_merge($person, [
                    'status_key' => $person['category'] === 'contract' ? 'kontrak' : 'tetap',
                    'unit' => $this->normalize($person['unit']),
                    'tmt_jabatan' => $this->parseDate($person['history_start']),
                ]))->all();
        }
        if (! Schema::hasTable('brihc_pemasar')) {
            return [];
        }

        $columns = array_values(array_intersect(
            ['pn_mantri', 'pernr', 'nip', 'completename', 'positiondesc', 'psadesc', 'orgdesc', 'bc', 'esgdesc', 'tmt_jabatan', 'keterangan'],
            Schema::getColumnListing('brihc_pemasar')
        ));
        if (! in_array('positiondesc', $columns, true) || ! in_array('psadesc', $columns, true)) {
            return [];
        }

        $people = [];
        $branches = $this->scopedBranches();
        foreach (DB::table('brihc_pemasar')->select($columns)->get() as $row) {
            if ($this->normalize($row->positiondesc ?? '') !== 'MANTRI'
                || $this->basis->isInactiveForProductivity($row->keterangan ?? null)) {
                continue;
            }
            $branch = $this->normalize($row->psadesc ?? '');
            if (! in_array($branch, $branches, true)) {
                continue;
            }
            $pn = '';
            foreach (['pn_mantri', 'pernr', 'nip'] as $column) {
                $pn = $this->basis->pnKey($row->{$column} ?? '');
                if ($pn !== '') {
                    break;
                }
            }
            if ($pn === '') {
                continue;
            }
            $status = trim((string) ($row->esgdesc ?? ''));
            $normalizedStatus = $this->normalize($status);
            $people[$pn] = [
                'pn' => $pn,
                'name' => trim((string) ($row->completename ?? '')),
                'branch' => $branch,
                'unit' => $this->normalize($row->orgdesc ?? ''),
                'bc' => trim((string) ($row->bc ?? '')),
                'status' => $status !== '' ? $status : '-',
                'status_key' => match (true) {
                    str_contains($normalizedStatus, 'KONTRAK') => 'kontrak',
                    str_starts_with($normalizedStatus, 'PT') || str_contains($normalizedStatus, 'TETAP') => 'tetap',
                    default => 'lainnya',
                },
                'tmt_jabatan' => $this->parseDate($row->tmt_jabatan ?? null),
            ];
        }

        return $people;
    }

    /** @return array{factor: float, eligible: bool, note: ?string} */
    private function tenure(?string $tmtJabatan, string $windowStart, string $windowEnd, int $quarterDays): array
    {
        if ($tmtJabatan === null || $tmtJabatan <= $windowStart) {
            return ['factor' => 1.0, 'eligible' => true, 'note' => $tmtJabatan === null ? null : 'TMT '.$tmtJabatan];
        }
        if ($tmtJabatan > $windowEnd) {
            return ['factor' => 0.0, 'eligible' => false, 'note' => 'TMT '.$tmtJabatan];
        }

        $days = Carbon::parse($tmtJabatan)->diffInDays(Carbon::parse($windowEnd)) + 1;

        return [
            'factor' => round($days / $quarterDays, 4),
            'eligible' => $days >= self::MIN_TENURE_DAYS,
            'note' => 'TMT '.$tmtJabatan.' ('.$days.' hari, proporsional)',
        ];
    }

    /** @return array<int, string> */
    public function scopedBranches(): array
    {
        $scope = UserBranchScope::current();
        if ($scope === null) {
            return self::BRANCHES;
        }

        return array_values(array_filter(self::BRANCHES, fn (string $branch): bool => str_contains($branch, $scope['upper_label'])
            || str_contains($scope['upper_label'], $branch)));
    }

    /** @param array<int, string> $positions */
    private function latestPositionBetween(array $positions, string $start, string $end): ?string
    {
        $match = null;
        foreach ($positions as $position) {
            if ($position >= $start && $position <= $end && ($match === null || $position > $match)) {
                $match = $position;
            }
        }

        return $match;
    }

    private function isKurRitel(object $row): bool
    {
        return strtoupper((string) ($row->produk_kinerja ?? '')) === 'KURMIKRO'
            && strtoupper((string) preg_replace('/[\s\-_\x{00A0}]+/u', '', trim((string) ($row->description ?? '')))) === self::KUR_RITEL_DESCRIPTION;
    }

    private function canonicalAccount(mixed $account): string
    {
        $account = strtoupper(trim((string) $account));
        if ($account === '') {
            return '';
        }

        return ltrim($account, '0') !== '' ? ltrim($account, '0') : '0';
    }

    private function canonicalCif(mixed $cif): string
    {
        return strtoupper(trim((string) $cif));
    }

    private function parseDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalize(mixed $value): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim((string) $value))) ?? '';
    }

    private function cacheKey(string ...$parts): string
    {
        return implode(':', array_merge(
            ['insentif_mantri', 'v2-batched-mob', (string) ReportCacheVersion::get('pinjaman')],
            $parts
        ));
    }

    /** @param callable(): array $compute */
    private function rememberResult(string $key, callable $compute, ?string $lockKey = null): array
    {
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        return Cache::lock(($lockKey ?? $key).':lock', 300)->block(120, function () use ($key, $compute): array {
            $cached = Cache::get($key);
            if (is_array($cached)) {
                return $cached;
            }
            $result = $compute();
            Cache::put($key, $result, self::CACHE_SECONDS);

            return $result;
        });
    }
}
