# Domain: access-control

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=access-control --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 33 |
| function | 4 |
| method | 71 |
| unresolved_symbol | 17 |
| middleware | 8 |
| route | 18 |
| class | 15 |
| table | 1 |
| view | 8 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `current` | method | 46 | `app/Support/UserBranchScope.php:111` |
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
| `login` | route | 10 | - |
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
| `forUser` | method | 9 | `app/Support/UserBranchScope.php:24` |
| `index` | method | 9 | `app/Http/Controllers/Admin/UserManagementController.php:56` |
| `throttleKey` | method | 9 | `app/Http/Requests/Auth/LoginRequest.php:123` |
| `throwRateLimitedValidationException` | method | 9 | `app/Http/Requests/Auth/LoginRequest.php:106` |
| `user-management.destroy` | route | 9 | - |
| `user-management.index` | route | 9 | - |
| `startFreshAuthenticatedSession` | method | 8 | `app/Http/Controllers/Auth/AuthenticatedSessionController.php:51` |

## Route Nodes

- `generated::En5dqAes9CPCurNw` - `login`
- `generated::zordguGW9En5qNg3` - `confirm-password`
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
- `ConfirmablePasswordController` - `app/Http/Controllers/Auth/ConfirmablePasswordController.php`
- `EmailVerificationNotificationController` - `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`
- `EmailVerificationPromptController` - `app/Http/Controllers/Auth/EmailVerificationPromptController.php`
- `EnforceUserBranchScope` - `app/Http/Middleware/EnforceUserBranchScope.php`
- `LoginHistory` - `app/Models/LoginHistory.php`
- `LoginRequest` - `app/Http/Requests/Auth/LoginRequest.php`
- `NewPasswordController` - `app/Http/Controllers/Auth/NewPasswordController.php`
- `PasswordController` - `app/Http/Controllers/Auth/PasswordController.php`
- `PasswordResetLinkController` - `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- `RoleMiddleware` - `app/Http/Middleware/RoleMiddleware.php`
- `SecurityHeadersMiddleware` - `app/Http/Middleware/SecurityHeadersMiddleware.php`
- `UserBranchScope` - `app/Support/UserBranchScope.php`
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

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| import -> access-control (protected_by) | 776 |
| dashboard-pinjaman -> access-control (protected_by) | 165 |
| dashboard-simpanan -> access-control (protected_by) | 145 |
| bank-pipeline -> access-control (protected_by) | 114 |
| core -> access-control (protected_by) | 103 |
| access-control -> core (calls) | 66 |
| dashboard-harian -> access-control (protected_by) | 45 |
| input-management -> access-control (protected_by) | 35 |
| access-control -> core (accepts) | 30 |
| almafacts -> access-control (protected_by) | 25 |
| core -> access-control (calls) | 18 |
| dashboard-simpanan -> access-control (calls) | 17 |
| access-control -> core (uses_component) | 17 |
| tests -> access-control (calls) | 14 |
| tests -> access-control (contains) | 12 |
| database -> access-control (protected_by) | 11 |
| access-control -> core (extends) | 11 |
| dashboard-pinjaman -> access-control (calls) | 9 |
| access-control -> tests (calls) | 9 |
| bank-pipeline -> access-control (calls) | 8 |
| marketshare -> access-control (protected_by) | 8 |
| access-control -> core (defines_table) | 7 |
| access-control -> dashboard-simpanan (references_route) | 7 |
| access-control -> core (writes_table) | 6 |
| jobs-snapshots -> access-control (protected_by) | 6 |
| prognosa -> access-control (protected_by) | 5 |
| access-control -> core (checks_table) | 4 |
| access-control -> marketshare (references_route) | 4 |
| access-control -> core (instantiates) | 4 |
| core -> access-control (references_route) | 4 |
