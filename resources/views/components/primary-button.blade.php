<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-bold uppercase tracking-[0.18em] text-white shadow-lg shadow-emerald-700/20 transition hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-800 focus:outline-none focus:ring-4 focus:ring-emerald-100']) }}>
    {{ $slot }}
</button>
