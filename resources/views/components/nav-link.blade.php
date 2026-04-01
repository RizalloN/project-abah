@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-2xl bg-emerald-50 px-4 py-2 text-sm font-semibold leading-5 text-emerald-700 shadow-sm ring-1 ring-emerald-100 transition duration-150 ease-in-out'
            : 'inline-flex items-center rounded-2xl px-4 py-2 text-sm font-semibold leading-5 text-slate-500 transition duration-150 ease-in-out hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:bg-slate-100 focus:text-slate-800';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
