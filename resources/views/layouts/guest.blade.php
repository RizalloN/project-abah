<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('images/a-six-logo.svg') }}">

        <title>{{ config('app.name', 'Dashboard A-Six') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .guest-auth-form input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]),
            .guest-auth-form select,
            .guest-auth-form textarea {
                min-height: 46px;
                border-color: #b9cee9;
                background-color: #fff;
                color: #102d52;
            }

            .guest-auth-form input:not([type="hidden"]):focus-visible,
            .guest-auth-form select:focus-visible,
            .guest-auth-form textarea:focus-visible {
                border-color: #0857c3;
                outline: none;
                box-shadow: 0 0 0 4px rgba(8, 87, 195, 0.14);
            }

            .guest-auth-form button[type="submit"] {
                min-height: 46px;
                border-radius: 12px;
                background: linear-gradient(135deg, #053b82, #0857c3);
                box-shadow: 0 10px 24px -10px rgba(8, 87, 195, 0.65);
            }

            .guest-auth-form button[type="submit"]:hover {
                background: linear-gradient(135deg, #042a5f, #06489f);
            }

            .guest-auth-form button[type="submit"]:focus-visible,
            .guest-auth-form a:focus-visible {
                outline: 3px solid rgba(8, 87, 195, 0.24);
                outline-offset: 3px;
            }

            @media (prefers-reduced-motion: reduce) {
                .guest-auth-form *,
                .guest-auth-form *::before,
                .guest-auth-form *::after {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                }
            }

            @media (orientation: landscape) and (max-height: 640px) {
                .guest-auth-panel {
                    min-height: 0 !important;
                    align-items: flex-start !important;
                    padding-top: 1.25rem !important;
                    padding-bottom: 1.25rem !important;
                }

                .guest-auth-heading {
                    margin-top: 1.25rem !important;
                }

                .guest-auth-form {
                    margin-top: 1.25rem !important;
                }
            }
        </style>
    </head>
    <body class="font-sans text-slate-950 antialiased">
        <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#f4f8ff] px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="absolute -left-24 top-[-6rem] h-72 w-72 rounded-full bg-blue-300/25 blur-3xl"></div>
                <div class="absolute -bottom-24 right-[-5rem] h-72 w-72 rounded-full bg-sky-300/20 blur-3xl"></div>
            </div>

            <div class="relative z-10 w-full max-w-[1120px] overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-[0_28px_80px_-38px_rgba(5,59,130,0.38)] lg:grid lg:grid-cols-[1.03fr_0.97fr]">
                <section class="relative hidden min-h-[660px] overflow-hidden border-r border-blue-800 bg-[linear-gradient(145deg,#042a5f_0%,#0857c3_58%,#307fe2_100%)] p-10 text-white lg:flex lg:flex-col lg:justify-between lg:p-12">
                    <div class="pointer-events-none absolute inset-0 opacity-30" aria-hidden="true">
                        <div class="absolute -right-20 -top-24 h-72 w-72 rounded-full border border-white/30"></div>
                        <div class="absolute -right-4 -top-8 h-72 w-72 rounded-full border border-white/20"></div>
                        <div class="absolute bottom-0 left-0 h-32 w-full bg-gradient-to-t from-blue-950/30 to-transparent"></div>
                    </div>
                    <div>
                        <a href="/" class="relative inline-flex min-h-11 items-center gap-3 rounded-xl text-sm font-semibold uppercase tracking-[0.22em] text-white/80 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-4 focus-visible:ring-offset-blue-800 motion-reduce:transition-none">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10 p-2 ring-1 ring-white/20">
                                <img src="{{ asset('images/a-six-logo.svg') }}" alt="Logo A-Six" class="h-full w-full object-contain">
                            </span>
                            Dashboard A-Six
                        </a>

                        <div class="relative mt-16">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-100">Secure Access Portal</p>
                            <h1 class="mt-5 max-w-xl text-4xl font-extrabold leading-[1.08] tracking-[-0.035em] text-white xl:text-[3.25rem]">
                                Satu tampilan autentikasi yang konsisten untuk seluruh portal.
                            </h1>
                            <p class="mt-6 max-w-lg text-base leading-7 text-blue-50/85">
                                Halaman login, reset password, verifikasi email, dan form akun lain kini mengikuti identitas Dashboard A-Six yang lebih bersih, modern, dan profesional.
                            </p>
                        </div>
                    </div>

                    <div class="relative grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-white/15 bg-white/10 p-5">
                            <p class="text-sm font-semibold text-white">Akses Aman</p>
                            <p class="mt-2 text-sm leading-6 text-blue-50/80">Alur autentikasi tetap aman dan nyaman digunakan dari semua perangkat.</p>
                        </div>
                        <div class="rounded-xl border border-white/15 bg-white/10 p-5">
                            <p class="text-sm font-semibold text-white">Visual Konsisten</p>
                            <p class="mt-2 text-sm leading-6 text-blue-50/80">Komponen form dan kartu mengikuti bahasa desain yang sama dengan dashboard.</p>
                        </div>
                    </div>
                </section>

                <section class="guest-auth-panel flex min-h-[calc(100vh-2rem)] items-center bg-white px-5 py-8 min-[360px]:px-6 sm:min-h-0 sm:px-10 sm:py-12 lg:px-12 lg:py-14">
                    <div class="mx-auto min-w-0 w-full max-w-md">
                        <a href="/" class="inline-flex min-h-11 items-center gap-3 rounded-xl text-xs font-semibold uppercase tracking-[0.18em] text-[#053b82] transition-colors hover:text-[#0857c3] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0857c3] focus-visible:ring-offset-4 lg:hidden motion-reduce:transition-none">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#0857c3] p-2 text-white shadow-[0_8px_20px_-8px_rgba(8,87,195,0.7)]">
                                <img src="{{ asset('images/a-six-logo.svg') }}" alt="Logo A-Six" class="h-full w-full object-contain">
                            </span>
                            Dashboard A-Six
                        </a>

                        <div class="guest-auth-heading mt-8 lg:mt-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#0857c3]">Account Access</p>
                            <h2 class="mt-3 text-[1.75rem] font-extrabold leading-tight tracking-[-0.03em] text-[#082b59] min-[360px]:text-3xl">
                                Portal autentikasi Dashboard A-Six
                            </h2>
                            <p class="mt-3 text-sm leading-6 text-slate-600">
                                Lanjutkan ke akun Anda melalui form yang tersedia di bawah ini.
                            </p>
                        </div>

                        <div class="guest-auth-form mt-8">
                            {{ $slot }}
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </body>
</html>
