<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            main {
                transition: opacity 180ms ease, transform 220ms ease;
                will-change: opacity, transform;
            }

            body.page-transition-leaving main {
                opacity: 0;
                transform: translateY(4px);
                pointer-events: none;
            }

            .page-transition-bar {
                position: fixed;
                top: 0;
                left: 0;
                z-index: 2000;
                height: 3px;
                width: 100%;
                transform-origin: left;
                transform: scaleX(0);
                background: linear-gradient(90deg, #0f766e 0%, #14b8a6 100%);
                box-shadow: 0 2px 10px rgba(15, 118, 110, 0.35);
                opacity: 0;
                transition: transform 320ms ease-out, opacity 180ms ease;
                pointer-events: none;
            }

            body.page-transition-active .page-transition-bar {
                opacity: 1;
                transform: scaleX(0.82);
            }

            body.page-transition-finishing .page-transition-bar {
                opacity: 0;
                transform: scaleX(1);
            }

            @media (prefers-reduced-motion: reduce) {
                main,
                .page-transition-bar {
                    transition: none !important;
                }
            }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="page-transition-bar" aria-hidden="true"></div>
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
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const body = document.body;
                const prefetchCache = new Set();
                const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                const isInternalNavigableLink = function (link) {
                    if (!link || link.target === '_blank' || link.hasAttribute('download')) {
                        return false;
                    }

                    const href = link.getAttribute('href');
                    if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
                        return false;
                    }

                    const url = new URL(link.href, window.location.origin);
                    if (url.origin !== window.location.origin) {
                        return false;
                    }

                    return url.pathname + url.search !== window.location.pathname + window.location.search;
                };

                const prefetchLink = function (link) {
                    if (!isInternalNavigableLink(link)) {
                        return;
                    }

                    const url = new URL(link.href, window.location.origin);
                    const key = url.pathname + url.search;
                    if (prefetchCache.has(key)) {
                        return;
                    }

                    prefetchCache.add(key);
                    const hint = document.createElement('link');
                    hint.rel = 'prefetch';
                    hint.href = url.href;
                    hint.as = 'document';
                    document.head.appendChild(hint);
                };

                document.addEventListener('mouseover', function (event) {
                    const link = event.target.closest('a[href]');
                    prefetchLink(link);
                });

                document.addEventListener('touchstart', function (event) {
                    const link = event.target.closest('a[href]');
                    prefetchLink(link);
                }, { passive: true });

                document.addEventListener('click', function (event) {
                    const link = event.target.closest('a[href]');
                    if (!isInternalNavigableLink(link)) {
                        return;
                    }

                    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
                        return;
                    }

                    if (event.defaultPrevented) {
                        return;
                    }

                    if (!reducedMotion) {
                        body.classList.add('page-transition-active', 'page-transition-leaving');
                        window.setTimeout(function () {
                            body.classList.add('page-transition-finishing');
                        }, 140);
                    }
                }, true);

                window.addEventListener('pageshow', function () {
                    body.classList.remove('page-transition-active', 'page-transition-leaving', 'page-transition-finishing');
                });
            });
        </script>
    </body>
</html>
