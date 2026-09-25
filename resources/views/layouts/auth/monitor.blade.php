<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-svh bg-[#f7f9fc] text-slate-900 antialiased">
        <div class="min-h-svh lg:grid lg:grid-cols-2">
            <section class="flex min-h-svh flex-col bg-white px-6 py-7 sm:px-12 lg:px-16 xl:px-24">
                <a href="{{ route('home') }}" class="inline-flex w-fit items-center gap-2.5" wire:navigate>
                    <span class="flex size-9 items-center justify-center rounded-lg bg-blue-700 text-white"><x-app-logo-icon class="size-5" /></span>
                    <span class="text-lg font-semibold tracking-tight text-slate-900">Wordmon</span>
                </a>

                <div class="flex flex-1 items-center justify-center py-12">
                    <div class="w-full max-w-[420px]">{{ $slot }}</div>
                </div>

                <p class="text-center text-xs text-slate-400">Wordmon · WordPress monitoring, made clear.</p>
            </section>

            <aside class="hidden border-l border-slate-200 bg-[#f7f9fc] px-12 py-12 lg:flex lg:flex-col lg:justify-center xl:px-20" aria-label="Product preview">
                <div class="mx-auto w-full max-w-xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">WordPress monitoring</p>
                    <h1 class="mt-4 max-w-lg text-4xl font-semibold leading-tight tracking-tight text-slate-900">Every site, one clear view.</h1>
                    <p class="mt-4 max-w-lg text-base leading-relaxed text-slate-600">Keep track of response status, WordPress versions, and SSL certificates from a focused workspace.</p>

                    <div class="mt-10 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_16px_48px_rgba(15,23,42,0.06)]">
                        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                            <span class="text-sm font-semibold text-slate-900">Website overview</span>
                            <span class="text-xs text-slate-400">Example view</span>
                        </div>
                        <div class="grid grid-cols-3 divide-x divide-slate-100 border-b border-slate-200">
                            <div class="px-6 py-5"><span class="block text-xs text-slate-500">Websites</span><span class="mt-2 block text-2xl font-semibold text-slate-900">12</span></div>
                            <div class="px-6 py-5"><span class="block text-xs text-slate-500">Responding</span><span class="mt-2 block text-2xl font-semibold text-emerald-700">11</span></div>
                            <div class="px-6 py-5"><span class="block text-xs text-slate-500">Attention</span><span class="mt-2 block text-2xl font-semibold text-amber-700">1</span></div>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div class="flex items-center justify-between gap-4 px-6 py-4"><div><span class="block text-sm font-medium text-slate-900">studio.example</span><span class="block text-xs text-slate-500">WordPress 6.6</span></div><span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">HTTP 200</span></div>
                            <div class="flex items-center justify-between gap-4 px-6 py-4"><div><span class="block text-sm font-medium text-slate-900">journal.example</span><span class="block text-xs text-slate-500">WordPress 6.5</span></div><span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">HTTP 200</span></div>
                            <div class="flex items-center justify-between gap-4 px-6 py-4"><div><span class="block text-sm font-medium text-slate-900">shop.example</span><span class="block text-xs text-slate-500">SSL expires soon</span></div><span class="rounded-md bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Review SSL</span></div>
                        </div>
                    </div>
                    <p class="mt-5 text-xs text-slate-400">Illustrative data shown above.</p>
                </div>
            </aside>
        </div>

        @persist('toast')
            <flux:toast.group><flux:toast /></flux:toast.group>
        @endpersist
        @fluxScripts
    </body>
</html>
