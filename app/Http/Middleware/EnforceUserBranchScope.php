<?php

namespace App\Http\Middleware;

use App\Support\UserBranchScope;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnforceUserBranchScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $scope = UserBranchScope::forUser($request->user());

        $request->attributes->set('user_branch_scope', $scope);
        View::share('userBranchScope', $scope);

        if ($scope !== null) {
            $scopedInput = [
                'cabang' => $scope['label'],
                'cabang1' => $scope['label'],
                'kanca' => $scope['label'],
                'mismatch_cabang1' => $scope['label'],
                'wilayah' => $scope['key'],
                'branch_office' => [$scope['upper_label']],
                'kantor_cabang' => [$scope['label']],
            ];

            $request->merge($scopedInput);
            $request->query->add($scopedInput);
            $request->request->add($scopedInput);
        }

        return $next($request);
    }
}
