# Domain: access-control

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=access-control --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 38 |
| function | 4 |
| method | 85 |
| unresolved_symbol | 26 |
| middleware | 8 |
| route | 18 |
| class | 19 |
| table | 5 |
| view | 8 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `current` | method | 50 | `app/Support/UserBranchScope.php:111` |
| `User` | class | 47 | `app/Models/User.php:10` |
| `users` | table | 29 | - |
| `forKey` | method | 28 | `app/Support/UserBranchScope.php:68` |
| `update` | method | 22 | `app/Http/Controllers/Admin/UserManagementController.php:159` |
| `LoginRequest` | class | 20 | `app/Http/Requests/Auth/LoginRequest.php:15` |
| `store` | method | 14 | `app/Http/Controllers/Admin/UserManagementController.php:101` |
| `SecurityHeadersMiddleware` | class | 13 | `app/Http/Middleware/SecurityHeadersMiddleware.php:9` |
| `store` | method | 12 | `app/Http/Controllers/Auth/NewPasswordController.php:32` |
| `UserManagementController` | class | 11 | `app/Http/Controllers/Admin/UserManagementController.php:21` |
| `login_histories` | table | 11 | - |
| `auth.forgot-password` | view | 10 | `resources/views/auth/forgot-password.blade.php:1` |
| `auth.reset-password` | view | 10 | `resources/views/auth/reset-password.blade.php:1` |
| `contentSecurityPolicy` | method | 10 | `app/Http/Middleware/SecurityHeadersMiddleware.php:80` |
| `forUser` | method | 10 | `app/Support/UserBranchScope.php:24` |
| `store` | method | 10 | `app/Http/Controllers/Auth/AuthenticatedSessionController.php:29` |
| `store` | method | 10 | `app/Http/Controllers/Auth/ConfirmablePasswordController.php:25` |
| `user-management.store` | route | 10 | - |
| `user-management.update` | route | 10 | - |
| `withSecurityHeaders` | method | 10 | `app/Http/Middleware/SecurityHeadersMiddleware.php:23` |
| `AuthenticatedSessionController` | class | 9 | `app/Http/Controllers/Auth/AuthenticatedSessionController.php:16` |
| `UserBranchScope` | class | 9 | `app/Support/UserBranchScope.php:7` |
| `auth.confirm-password` | view | 9 | `resources/views/auth/confirm-password.blade.php:1` |
| `authenticate` | method | 9 | `app/Http/Requests/Auth/LoginRequest.php:48` |
| `destroy` | method | 9 | `app/Http/Controllers/Admin/UserManagementController.php:253` |
| `index` | method | 9 | `app/Http/Controllers/Admin/UserManagementController.php:56` |
| `login` | route | 9 | - |
| `sessions` | table | 9 | - |
| `throttleKey` | method | 9 | `app/Http/Requests/Auth/LoginRequest.php:123` |
| `throwRateLimitedValidationException` | method | 9 | `app/Http/Requests/Auth/LoginRequest.php:106` |

## Route Nodes

- `confirm-password` - `confirm-password`
- `login` - `login`
- `login` - `login`
- `logout` - `logout`
- `password.confirm` - `confirm-password`
- `password.email` - `forgot-password`
- `password.request` - `forgot-password`
- `password.reset` - `reset-password/{token}`
- `password.store` - `reset-password`
- `password.update` - `password`
- `user-management.destroy` - `user-management/{user}`
- `user-management.index` - `user-management`
- `user-management.login-history` - `user-management/{user}/login-history`
- `user-management.store` - `user-management`
- `user-management.update` - `user-management/{user}`
- `verification.notice` - `verify-email`
- `verification.send` - `email/verification-notification`
- `verification.verify` - `verify-email/{id}/{hash}`

## Class Nodes

- `AuthenticatedSessionController` - `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `BranchScopeReconciliationTest` - `tests/Unit/BranchScopeReconciliationTest.php`
- `ConfirmablePasswordController` - `app/Http/Controllers/Auth/ConfirmablePasswordController.php`
- `EmailVerificationNotificationController` - `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`
- `EmailVerificationPromptController` - `app/Http/Controllers/Auth/EmailVerificationPromptController.php`
- `EnforceUserBranchScope` - `app/Http/Middleware/EnforceUserBranchScope.php`
- `LoginHistory` - `app/Models/LoginHistory.php`
- `LoginRequest` - `app/Http/Requests/Auth/LoginRequest.php`
- `NewPasswordController` - `app/Http/Controllers/Auth/NewPasswordController.php`
- `PasswordController` - `app/Http/Controllers/Auth/PasswordController.php`
- `PasswordResetLinkController` - `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- `ReleaseSessionLockMiddleware` - `app/Http/Middleware/ReleaseSessionLockMiddleware.php`
- `RoleMiddleware` - `app/Http/Middleware/RoleMiddleware.php`
- `SecurityHeadersMiddleware` - `app/Http/Middleware/SecurityHeadersMiddleware.php`
- `User` - `app/Models/User.php`
- `UserBranchScope` - `app/Support/UserBranchScope.php`
- `UserIdReuseTest` - `tests/Unit/UserIdReuseTest.php`
- `UserManagementController` - `app/Http/Controllers/Admin/UserManagementController.php`
- `VerifyEmailController` - `app/Http/Controllers/Auth/VerifyEmailController.php`

## View Nodes

- `admin.user-management` - `resources/views/admin/user-management.blade.php`
- `auth.confirm-password` - `resources/views/auth/confirm-password.blade.php`
- `auth.forgot-password` - `resources/views/auth/forgot-password.blade.php`
- `auth.login` - `resources/views/auth/login.blade.php`
- `auth.reset-password` - `resources/views/auth/reset-password.blade.php`
- `auth.verify-email` - `resources/views/auth/verify-email.blade.php`
- `components.auth-session-status` - `resources/views/components/auth-session-status.blade.php`
- `components.auth.session.status`

## Table Nodes

- `login_histories`
- `password_reset_tokens`
- `sessions`
- `user_audit_log`
- `users`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| import -> access-control (protected_by) | 803 |
| dashboard-simpanan -> access-control (protected_by) | 185 |
| dashboard-pinjaman -> access-control (protected_by) | 175 |
| bank-pipeline -> access-control (protected_by) | 114 |
| access-control -> core (calls) | 60 |
| core -> access-control (protected_by) | 58 |
| dashboard-harian -> access-control (protected_by) | 45 |
| input-management -> access-control (protected_by) | 35 |
| dashboard-simpanan -> access-control (calls) | 28 |
| access-control -> core (accepts) | 27 |
| dashboard-pinjaman -> access-control (calls) | 25 |
| almafacts -> access-control (protected_by) | 25 |
| access-control -> core (uses_component) | 13 |
| bank-pipeline -> access-control (calls) | 11 |
| database -> access-control (protected_by) | 11 |
| access-control -> core (extends) | 11 |
| access-control -> tests (calls) | 10 |
| prognosa -> access-control (instantiates) | 10 |
| tests -> access-control (contains) | 10 |
| tests -> access-control (calls) | 8 |
| marketshare -> access-control (protected_by) | 8 |
| access-control -> dashboard-simpanan (references_route) | 7 |
| core -> access-control (calls) | 7 |
| almafacts -> access-control (instantiates) | 7 |
| jobs-snapshots -> access-control (protected_by) | 6 |
| database -> access-control (defines_table) | 5 |
| prognosa -> access-control (protected_by) | 5 |
| access-control -> marketshare (references_route) | 4 |
| database -> access-control (checks_table) | 4 |
| dashboard-harian -> access-control (calls) | 4 |
