<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased" style="font-family: 'Plus Jakarta Sans', sans-serif;">
        <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[radial-gradient(circle_at_top_left,_rgba(32,129,122,0.35),_transparent_32%),radial-gradient(circle_at_bottom_right,_rgba(245,158,11,0.18),_transparent_22%),linear-gradient(135deg,_#020617_0%,_#0f172a_55%,_#111827_100%)] px-4 py-10 sm:px-6 lg:px-8">
            <div class="absolute inset-0 opacity-30">
                <div class="absolute left-[-8rem] top-20 h-72 w-72 rounded-full bg-emerald-500/30 blur-3xl"></div>
                <div class="absolute bottom-10 right-[-5rem] h-64 w-64 rounded-full bg-amber-400/25 blur-3xl"></div>
            </div>

            <div class="relative z-10 w-full max-w-6xl overflow-hidden rounded-[32px] border border-white/10 bg-white/8 shadow-[0_30px_80px_-32px_rgba(15,23,42,0.35)] backdrop-blur-xl lg:grid lg:grid-cols-[1.05fr_0.95fr]">
                <section class="hidden border-b border-white/10 p-10 text-white lg:flex lg:flex-col lg:justify-between lg:border-b-0 lg:border-r lg:p-12">
                    <div>
                        <a href="/" class="inline-flex items-center gap-3 text-sm font-semibold uppercase tracking-[0.32em] text-white/75 transition hover:text-white">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15">DB</span>
                            DigiBranch - Area 6
                        </a>

                        <div class="mt-12">
                            <p class="text-sm font-medium uppercase tracking-[0.28em] text-emerald-100/80">Secure Access Portal</p>
                            <h1 class="mt-4 text-4xl font-extrabold leading-tight text-white xl:text-5xl">
                                Satu tampilan autentikasi yang konsisten untuk seluruh portal.
                            </h1>
                            <p class="mt-6 max-w-lg text-base leading-7 text-slate-300">
                                Halaman login, reset password, verifikasi email, dan form akun lain kini mengikuti gaya DigiBranch yang lebih bersih, modern, dan profesional.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                            <p class="text-sm font-semibold text-white">Akses Aman</p>
                            <p class="mt-2 text-sm leading-6 text-slate-300">Alur autentikasi tetap aman dan nyaman digunakan dari semua perangkat.</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                            <p class="text-sm font-semibold text-white">Visual Konsisten</p>
                            <p class="mt-2 text-sm leading-6 text-slate-300">Komponen form dan kartu mengikuti bahasa desain yang sama dengan dashboard.</p>
                        </div>
                    </div>
                </section>

                <section class="bg-white px-6 py-8 sm:px-10 sm:py-10 lg:px-12 lg:py-12">
                    <div class="mx-auto w-full max-w-md">
                        <a href="/" class="inline-flex items-center gap-3 text-sm font-semibold uppercase tracking-[0.28em] text-emerald-700 lg:hidden">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/25">DB</span>
                            DigiBranch - Area 6
                        </a>

                        <div class="mt-6 lg:mt-0">
                            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-emerald-700">Account Access</p>
                            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
                                Portal autentikasi DigiBranch
                            </h2>
                            <p class="mt-3 text-sm leading-6 text-slate-500">
                                Lanjutkan ke akun Anda melalui form yang tersedia di bawah ini.
                            </p>
                        </div>

                        <div class="mt-8">
                            {{ $slot }}
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </body>
</html>
