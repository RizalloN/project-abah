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
    <body class="font-sans antialiased" style="font-family: 'Plus Jakarta Sans', sans-serif;">
        <div class="min-h-screen bg-[linear-gradient(180deg,_#f8fafc_0%,_#edf4f6_100%)]">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="border-b border-slate-200/70 bg-white/75 shadow-sm backdrop-blur">
                    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    <div class="space-y-6 [&_.bg-white]:rounded-[1.5rem] [&_.bg-white]:border [&_.bg-white]:border-slate-200/80 [&_.bg-white]:shadow-[0_20px_45px_-30px_rgba(15,23,42,0.24)] [&_input]:rounded-2xl [&_input]:border-slate-200 [&_input]:bg-slate-50 [&_input]:px-4 [&_input]:py-3 [&_input]:text-slate-900 [&_input]:shadow-none [&_input:focus]:border-emerald-500 [&_input:focus]:ring-emerald-100 [&_textarea]:rounded-2xl [&_textarea]:border-slate-200 [&_textarea]:bg-slate-50 [&_textarea]:shadow-none [&_textarea:focus]:border-emerald-500 [&_textarea:focus]:ring-emerald-100 [&_button]:rounded-2xl">
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
