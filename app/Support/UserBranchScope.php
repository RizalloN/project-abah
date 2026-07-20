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
        return self::forUser(auth()->user());
    }

    public static function cacheKey(): string
    {
        return self::current()['key'] ?? 'area6';
    }
}
