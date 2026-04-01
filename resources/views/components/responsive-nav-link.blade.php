@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-2xl bg-emerald-50 px-4 py-3 text-start text-base font-semibold text-emerald-700 ring-1 ring-emerald-100 transition duration-150 ease-in-out'
            : 'block w-full rounded-2xl px-4 py-3 text-start text-base font-semibold text-slate-600 transition duration-150 ease-in-out hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:bg-slate-100 focus:text-slate-800';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
