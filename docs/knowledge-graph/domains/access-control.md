# Domain: access-control

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=access-control --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 29 |
| method | 70 |
| unresolved_symbol | 33 |
| middleware | 9 |
| route | 18 |
| class | 19 |
| table | 5 |
| view | 8 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `current` | method | 54 | `app/Support/UserBranchScope.php:111` |
| `User` | class | 27 | `app/Models/User.php:10` |
| `update` | method | 21 | `app/Http/Controllers/Admin/UserManagementController.php:173` |
| `LoginRequest` | class | 20 | `app/Http/Requests/Auth/LoginRequest.php:15` |
| `store` | method | 14 | `app/Http/Controllers/Admin/UserManagementController.php:101` |
| `SecurityHeadersMiddleware` | class | 13 | `app/Http/Middleware/SecurityHeadersMiddleware.php:9` |
| `withSecurityHeaders` | method | 13 | `app/Http/Middleware/SecurityHeadersMiddleware.php:23` |
| `forUser` | method | 12 | `app/Support/UserBranchScope.php:24` |
| `store` | method | 12 | `app/Http/Controllers/Auth/NewPasswordController.php:32` |
| `UserManagementController` | class | 11 | `app/Http/Controllers/Admin/UserManagementController.php:21` |
| `auth.forgot-password` | view | 10 | `resources/views/auth/forgot-password.blade.php:1` |
| `auth.reset-password` | view | 10 | `resources/views/auth/reset-password.blade.php:1` |
| `contentSecurityPolicy` | method | 10 | `app/Http/Middleware/SecurityHeadersMiddleware.php:85` |
| `login` | route | 10 | - |
| `store` | method | 10 | `app/Http/Controllers/Auth/AuthenticatedSessionController.php:30` |
| `store` | method | 10 | `app/Http/Controllers/Auth/ConfirmablePasswordController.php:25` |
| `update` | method | 10 | `app/Http/Controllers/Auth/PasswordController.php:17` |
| `user-management.destroy` | route | 10 | - |
| `user-management.store` | route | 10 | - |
| `user-management.update` | route | 10 | - |
| `users` | table | 10 | - |
| `AuthenticatedSessionController` | class | 9 | `app/Http/Controllers/Auth/AuthenticatedSessionController.php:17` |
| `UserBranchScope` | class | 9 | `app/Support/UserBranchScope.php:7` |
| `auth.confirm-password` | view | 9 | `resources/views/auth/confirm-password.blade.php:1` |
| `authenticate` | method | 9 | `app/Http/Requests/Auth/LoginRequest.php:48` |
| `destroy` | method | 9 | `app/Http/Controllers/Admin/UserManagementController.php:275` |
| `index` | method | 9 | `app/Http/Controllers/Admin/UserManagementController.php:56` |
| `startFreshAuthenticatedSession` | method | 9 | `app/Http/Controllers/Auth/AuthenticatedSessionController.php:52` |
| `store` | method | 9 | `app/Support/AuthenticatedSessionContext.php:24` |
| `throttleKey` | method | 9 | `app/Http/Requests/Auth/LoginRequest.php:123` |

## Route Nodes

- `generated::ExqIPAhwDvzTzTur` - `login`
- `generated::uH9UL7XKsBg7PlyS` - `confirm-password`
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

- `AuthenticateSession` - `app/Http/Middleware/AuthenticateSession.php`
- `AuthenticatedSessionContext` - `app/Support/AuthenticatedSessionContext.php`
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
- `ReleaseSessionLockMiddleware` - `app/Http/Middleware/ReleaseSessionLockMiddleware.php`
- `RoleMiddleware` - `app/Http/Middleware/RoleMiddleware.php`
- `SecurityHeadersMiddleware` - `app/Http/Middleware/SecurityHeadersMiddleware.php`
- `User` - `app/Models/User.php`
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
- `password_reset_tokens`
- `sessions`
- `user_audit_log`
- `users`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| import -> access-control (protected_by) | 973 |
| dashboard-simpanan -> access-control (protected_by) | 222 |
| dashboard-pinjaman -> access-control (protected_by) | 222 |
| bank-pipeline -> access-control (protected_by) | 136 |
| core -> access-control (protected_by) | 69 |
| access-control -> core (calls) | 61 |
| dashboard-harian -> access-control (protected_by) | 60 |
| input-management -> access-control (protected_by) | 42 |
| almafacts -> access-control (protected_by) | 30 |
| access-control -> core (accepts) | 28 |
| dashboard-simpanan -> access-control (calls) | 28 |
| dashboard-pinjaman -> access-control (calls) | 17 |
| database -> access-control (protected_by) | 13 |
| access-control -> core (uses_component) | 13 |
| access-control -> core (extends) | 11 |
| bank-pipeline -> access-control (calls) | 8 |
| marketshare -> access-control (protected_by) | 8 |
| core -> access-control (calls) | 7 |
| jobs-snapshots -> access-control (protected_by) | 7 |
| prognosa -> access-control (protected_by) | 6 |
| database -> access-control (defines_table) | 5 |
| access-control -> dashboard-simpanan (references_route) | 5 |
| database -> access-control (checks_table) | 4 |
| dashboard-harian -> access-control (calls) | 4 |
| access-control -> presentation (calls) | 4 |
| access-control -> platform (uses_component) | 4 |
| platform -> access-control (references_route) | 3 |
| database -> access-control (uses_table) | 3 |
| database -> access-control (writes_table) | 2 |
| database -> access-control (alters_table) | 2 |
