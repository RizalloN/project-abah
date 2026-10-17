<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;

final class UserBranchScope
{
    public const AREA_SCOPE = 'area6';

    /**
     * @var array<string, array{key: string, label: string, code4: string, code5: string}>
     */
    private const BRANCHES_BY_PN = [
        '0045' => ['key' => 'madiun', 'label' => 'KC Madiun', 'code4' => '0045', 'code5' => '00045'],
        '0049' => ['key' => 'magetan', 'label' => 'KC Magetan', 'code4' => '0049', 'code5' => '00049'],
        '0057' => ['key' => 'ngawi', 'label' => 'KC Ngawi', 'code4' => '0057', 'code5' => '00057'],
        '0070' => ['key' => 'ponorogo', 'label' => 'KC Ponorogo', 'code4' => '0070', 'code5' => '00070'],
    ];

    /** Scope pinned by runAs() for work without an authenticated user (cache warmers). */
    private static bool $pinned = false;

    private static ?array $pinnedScope = null;

    /**
     * @return array{key: string, slug: string, label: string, upper_label: string, plain_label: string, code4: string, code5: string, pn: string}|null
     */
    public static function forUser(?Authenticatable $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $pn = method_exists($user, 'getAttribute') ? $user->getAttribute('pn') : null;
        $pn ??= $user->getAuthIdentifier();

        $assignedScope = method_exists($user, 'getAttribute')
            ? trim((string) ($user->getAttribute('branch_scope') ?? ''))
            : '';
        if ($assignedScope !== '') {
            if (strtolower($assignedScope) === self::AREA_SCOPE) {
                return null;
            }

            $scope = self::forKey($assignedScope, $pn !== null ? (string) $pn : null);
            if ($scope !== null) {
                return $scope;
            }
        }

        return self::forPn($pn !== null ? (string) $pn : null);
    }

    /**
     * @return array{key: string, slug: string, label: string, upper_label: string, plain_label: string, code4: string, code5: string, pn: string}|null
     */
    public static function forPn(?string $pn): ?array
    {
        $normalizedPn = trim((string) $pn);
        $branch = self::BRANCHES_BY_PN[$normalizedPn] ?? null;

        if ($branch === null) {
            return null;
        }

        return self::buildScope($branch, $normalizedPn);
    }

    /**
     * @return array{key: string, slug: string, label: string, upper_label: string, plain_label: string, code4: string, code5: string, pn: string}|null
     */
    public static function forKey(?string $key, ?string $pn = null): ?array
    {
        $normalizedKey = strtolower(trim((string) $key));
        foreach (self::BRANCHES_BY_PN as $branch) {
            if ($branch['key'] === $normalizedKey) {
                return self::buildScope($branch, trim((string) $pn));
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [self::AREA_SCOPE => 'Area 6 (Semua Cabang)'];
        foreach (self::BRANCHES_BY_PN as $branch) {
            $options[$branch['key']] = $branch['label'];
        }

        return $options;
    }

    /**
     * @param  array{key: string, label: string, code4: string, code5: string}  $branch
     * @return array{key: string, slug: string, label: string, upper_label: string, plain_label: string, code4: string, code5: string, pn: string}
     */
    private static function buildScope(array $branch, string $pn): array
    {
        return [
            ...$branch,
            'slug' => 'kc-'.$branch['key'],
            'upper_label' => strtoupper($branch['label']),
            'plain_label' => substr($branch['label'], 3),
            'pn' => $pn,
        ];
    }

    /**
     * @return array{key: string, slug: string, label: string, upper_label: string, plain_label: string, code4: string, code5: string, pn: string}|null
     */
    public static function current(): ?array
    {
        if (self::$pinned) {
            return self::$pinnedScope;
        }

        return self::forUser(auth()->user());
    }

    /**
     * Run a callback as if the current user had the given scope (null = Area 6).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function runAs(?array $scope, callable $callback): mixed
    {
        [$wasPinned, $previous] = [self::$pinned, self::$pinnedScope];
        [self::$pinned, self::$pinnedScope] = [true, $scope];

        try {
            return $callback();
        } finally {
            [self::$pinned, self::$pinnedScope] = [$wasPinned, $previous];
        }
    }

    /** @return array<int, array{key: string, slug: string, label: string, upper_label: string, plain_label: string, code4: string, code5: string, pn: string}> */
    public static function branchScopes(): array
    {
        return array_map(
            static fn (array $branch): array => self::buildScope($branch, ''),
            array_values(self::BRANCHES_BY_PN)
        );
    }

    /**
     * Request values that pin every branch filter a report reads to the scope.
     *
     * @return array<string, string|array<int, string>>
     */
    public static function requestInput(array $scope): array
    {
        return [
            'cabang' => $scope['label'],
            'cabang1' => $scope['label'],
            'kanca' => $scope['label'],
            'mismatch_cabang1' => $scope['label'],
            'wilayah' => $scope['key'],
            'branch_office' => [$scope['upper_label']],
            'kantor_cabang' => [$scope['label']],
        ];
    }

    public static function cacheKey(): string
    {
        return self::current()['key'] ?? 'area6';
    }

    /**
     * Whether a stored branch value names the scope's branch. Source tables use
     * many formats ("KC MADIUN", "00045 -- KC Madiun (Konsolidasi-MB)",
     * "45 - KC Madiun", "kc-madiun", "0045"), while "KCP ... MADIUN" units and
     * "Area 6"/"all" aggregates never match.
     *
     * @param  array{key: string, label: string, code4: string, code5: string}  $scope
     */
    public static function matches(mixed $value, array $scope): bool
    {
        $token = self::token($value);
        if ($token === '') {
            return false;
        }

        if (ctype_digit($token)) {
            return ltrim($token, '0') === ltrim($scope['code4'], '0');
        }

        return $token === self::token($scope['key'])
            || str_contains($token, self::token($scope['label']));
    }

    /**
     * Keep only the branch options a restricted user may pick, so selectors never
     * offer "Area 6" or another branch. Options are strings or arrays carrying
     * `value`/`label`; unrestricted users get the options unchanged.
     *
     * @param  iterable<array-key, mixed>  $options
     * @return array<array-key, mixed>
     */
    public static function restrictOptions(iterable $options): array
    {
        $options = $options instanceof \Traversable ? iterator_to_array($options) : $options;
        $scope = self::current();
        if ($scope === null) {
            return $options;
        }

        $filtered = array_filter($options, static fn (mixed $option): bool => is_array($option)
            ? self::matches($option['value'] ?? null, $scope) || self::matches($option['label'] ?? null, $scope)
            : self::matches($option, $scope));

        return array_is_list($options) ? array_values($filtered) : $filtered;
    }

    private static function token(mixed $value): string
    {
        return is_scalar($value)
            ? (preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value)) ?? '')
            : '';
    }
}
