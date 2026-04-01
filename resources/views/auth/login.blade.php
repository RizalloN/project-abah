<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - DigiBranch Area 6</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f3f8f7',
                            100: '#dcecea',
                            500: '#1f6f68',
                            600: '#195952',
                            700: '#12463f',
                            900: '#0d2b2a',
                        },
                    },
                    boxShadow: {
                        soft: '0 30px 80px -32px rgba(15, 23, 42, 0.35)',
                    },
                },
            },
        };
    </script>
</head>
<body class="min-h-screen bg-slate-950 font-sans text-slate-900 antialiased">
    <div class="relative isolate min-h-screen overflow-hidden bg-[radial-gradient(circle_at_top_left,_rgba(32,129,122,0.35),_transparent_32%),radial-gradient(circle_at_bottom_right,_rgba(245,158,11,0.18),_transparent_22%),linear-gradient(135deg,_#020617_0%,_#0f172a_55%,_#111827_100%)]">
        <div class="absolute inset-0 opacity-30">
            <div class="absolute left-[-8rem] top-20 h-72 w-72 rounded-full bg-brand-500 blur-3xl"></div>
            <div class="absolute bottom-10 right-[-5rem] h-64 w-64 rounded-full bg-amber-400/30 blur-3xl"></div>
        </div>

        <div class="relative mx-auto flex min-h-screen max-w-7xl items-center px-4 py-10 sm:px-6 lg:px-8">
            <div class="grid w-full overflow-hidden rounded-[32px] border border-white/10 bg-white/8 shadow-soft backdrop-blur-xl lg:grid-cols-[1.1fr_0.9fr]">
                <section class="hidden flex-col justify-between border-b border-white/10 p-8 text-white lg:flex lg:border-b-0 lg:border-r lg:p-12">
                    <div class="max-w-xl">
                        <a href="/" class="inline-flex items-center gap-3 text-sm font-semibold uppercase tracking-[0.32em] text-white/75 transition hover:text-white">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15">DB</span>
                            DigiBranch - Area 6
                        </a>

                        <div class="mt-12">
                            <p class="text-sm font-medium uppercase tracking-[0.28em] text-brand-100/80">Secure Access Portal</p>
                            <h1 class="mt-4 text-4xl font-extrabold leading-tight text-white xl:text-5xl">
                                Monitoring kinerja cabang dengan tampilan yang lebih rapi dan fokus.
                            </h1>
                            <p class="mt-6 max-w-lg text-base leading-7 text-slate-300">
                                Masuk ke dashboard DigiBranch untuk mengakses data performa, pelaporan, dan insight operasional Area 6 secara lebih cepat dan terstruktur.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                            <p class="text-sm font-semibold text-white">Dashboard Terintegrasi</p>
                            <p class="mt-2 text-sm leading-6 text-slate-300">Akses performa channel dan laporan penting dari satu tempat.</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                            <p class="text-sm font-semibold text-white">Akses Aman</p>
                            <p class="mt-2 text-sm leading-6 text-slate-300">Masuk menggunakan personal number untuk menjaga kontrol akses internal.</p>
                        </div>
                    </div>
                </section>

                <section class="bg-white px-6 py-8 sm:px-10 sm:py-10 lg:px-12 lg:py-12">
                    <div class="mx-auto w-full max-w-md">
                        <a href="/" class="inline-flex items-center gap-3 text-sm font-semibold uppercase tracking-[0.28em] text-brand-600 lg:hidden">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-brand-600 text-white shadow-lg shadow-brand-600/25">DB</span>
                            DigiBranch - Area 6
                        </a>

                        <div class="mt-6 lg:mt-0">
                            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-brand-600">Welcome Back</p>
                            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
                                Masuk ke akun Anda
                            </h2>
                            <p class="mt-3 text-sm leading-6 text-slate-500">
                                Gunakan personal number dan password untuk mengakses portal DigiBranch - Area 6.
                            </p>
                        </div>

                        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                            @csrf

                            <div>
                                <label for="pn" class="block text-sm font-semibold text-slate-700">
                                    Personal Number
                                </label>
                                <input
                                    id="pn"
                                    class="mt-2 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-100"
                                    type="text"
                                    name="pn"
                                    value="{{ old('pn') }}"
                                    placeholder="Masukkan personal number"
                                    required
                                    autofocus
                                />

                                @error('pn')
                                    <p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>
                                @enderror
                                @error('personal_number')
                                    <p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>
                                @enderror
                                @error('email')
                                    <p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password" class="block text-sm font-semibold text-slate-700">
                                    Password
                                </label>
                                <input
                                    id="password"
                                    class="mt-2 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-100"
                                    type="password"
                                    name="password"
                                    placeholder="Masukkan password"
                                    required
                                />

                                @error('password')
                                    <p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-center justify-between gap-4 pt-1">
                                <label for="remember_me" class="inline-flex items-center gap-3 text-sm text-slate-600">
                                    <input
                                        id="remember_me"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                        name="remember"
                                    >
                                    <span>Remember me</span>
                                </label>
                                <span class="text-xs font-medium uppercase tracking-[0.22em] text-slate-400">Internal Access</span>
                            </div>

                            <div class="pt-3">
                                <button
                                    type="submit"
                                    class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-600 px-4 py-3.5 text-sm font-bold uppercase tracking-[0.22em] text-white transition hover:bg-brand-700 focus:outline-none focus:ring-4 focus:ring-brand-200"
                                >
                                    Log In
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
</body>
</html>
