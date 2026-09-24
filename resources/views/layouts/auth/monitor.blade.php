<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark monitor">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-svh bg-[#05080d] text-zinc-100 antialiased">
        <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(16,185,129,0.16),transparent_42%),radial-gradient(ellipse_at_bottom_right,rgba(6,182,212,0.12),transparent_38%)]"></div>
            <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(255,255,255,0.045)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.045)_1px,transparent_1px)] bg-[size:56px_56px] mask-[radial-gradient(ellipse_at_center,black_30%,transparent_78%)]"></div>
            <div class="monitor-scan absolute inset-x-0 top-0 h-40 bg-linear-to-b from-transparent via-emerald-400/8 to-transparent"></div>
        </div>

        <div class="relative grid min-h-svh lg:grid-cols-[1.15fr_0.85fr]">
            <section class="hidden flex-col justify-between border-e border-white/8 px-10 py-10 lg:flex xl:px-16">
                <a href="{{ route('home') }}" class="flex items-center gap-3" wire:navigate>
                    <span class="relative flex size-10 items-center justify-center rounded-lg border border-emerald-400/30 bg-emerald-400/10 text-emerald-300 shadow-[0_0_24px_rgba(52,211,153,0.25)]">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" d="M12 12h.01M7.5 12a4.5 4.5 0 0 1 9 0M4 12a8 8 0 0 1 16 0" />
                            <path stroke-linecap="round" d="M12 12v6" />
                        </svg>
                        <span class="absolute -end-0.5 -top-0.5 size-2 rounded-full bg-emerald-400 shadow-[0_0_10px_#34d399]"></span>
                    </span>
                    <span class="flex flex-col">
                        <span class="text-sm font-semibold tracking-wide">Wordmon</span>
                        <span class="font-mono text-[10px] tracking-[0.18em] text-zinc-500 uppercase">WordPress watch</span>
                    </span>
                </a>

                <div class="flex max-w-xl flex-col gap-8">
                    <div class="flex flex-col gap-4">
                        <p class="font-mono text-[11px] tracking-[0.24em] text-emerald-400/90 uppercase">Domain monitor</p>
                        <h1 class="text-4xl leading-tight font-semibold tracking-tight text-white xl:text-5xl">
                            Know the second a WordPress site goes quiet.
                        </h1>
                        <p class="max-w-md text-base leading-relaxed text-zinc-400">
                            Uptime, response time, SSL, and core updates on one dark console. Probes run on a steady interval so a downed domain never sits unnoticed.
                        </p>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-white/10 bg-black/40 shadow-[0_0_0_1px_rgba(255,255,255,0.03),0_24px_80px_rgba(0,0,0,0.45)] backdrop-blur-md">
                        <div class="flex items-center justify-between border-b border-white/8 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="relative flex size-2">
                                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-70"></span>
                                    <span class="relative inline-flex size-2 rounded-full bg-emerald-400"></span>
                                </span>
                                <span class="font-mono text-[11px] tracking-wider text-zinc-400 uppercase">Live probe</span>
                            </div>
                            <span class="font-mono text-[11px] text-zinc-500">interval 60s</span>
                        </div>

                        <ul class="divide-y divide-white/6 font-mono text-xs">
                            @foreach ([
                                ['host' => 'northshore.example', 'detail' => '200 · 118ms · WP 6.8', 'state' => 'Up', 'tone' => 'emerald'],
                                ['host' => 'atlas-studio.example', 'detail' => 'SSL certificate · 12 days', 'state' => 'Watch', 'tone' => 'amber'],
                                ['host' => 'harbor-press.example', 'detail' => 'Plugin update available', 'state' => 'Update', 'tone' => 'cyan'],
                                ['host' => 'kiln-and-co.example', 'detail' => 'Timeout · 0 bytes', 'state' => 'Down', 'tone' => 'rose'],
                            ] as $site)
                                <li class="flex items-center justify-between gap-4 px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-zinc-100">{{ $site['host'] }}</p>
                                        <p class="mt-0.5 truncate text-zinc-500">{{ $site['detail'] }}</p>
                                    </div>
                                    <span @class([
                                        'shrink-0 rounded-full border px-2 py-0.5 text-[10px] tracking-wider uppercase',
                                        'border-emerald-400/30 bg-emerald-400/10 text-emerald-300' => $site['tone'] === 'emerald',
                                        'border-amber-400/30 bg-amber-400/10 text-amber-300' => $site['tone'] === 'amber',
                                        'border-cyan-400/30 bg-cyan-400/10 text-cyan-300' => $site['tone'] === 'cyan',
                                        'border-rose-400/30 bg-rose-400/10 text-rose-300' => $site['tone'] === 'rose',
                                    ])>{{ $site['state'] }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="border-t border-white/8 px-4 py-3">
                            <svg viewBox="0 0 320 48" class="h-12 w-full text-emerald-400" fill="none" aria-hidden="true">
                                <path d="M0 32 H28 L36 32 L44 18 L52 32 H88 L96 32 L104 8 L112 36 L120 32 H168 L176 32 L184 22 L192 32 H240 L248 32 L256 14 L264 32 H320" stroke="currentColor" stroke-width="1.6" />
                                <path d="M0 32 H28 L36 32 L44 18 L52 32 H88 L96 32 L104 8 L112 36 L120 32 H168 L176 32 L184 22 L192 32 H240 L248 32 L256 14 L264 32 H320 V48 H0 Z" fill="currentColor" opacity="0.12" />
                            </svg>
                        </div>
                    </div>
                </div>

                <dl class="grid grid-cols-3 gap-6 text-sm">
                    <div>
                        <dt class="font-mono text-[10px] tracking-[0.16em] text-zinc-500 uppercase">Checks</dt>
                        <dd class="mt-1 text-zinc-200">HTTP, SSL, WP</dd>
                    </div>
                    <div>
                        <dt class="font-mono text-[10px] tracking-[0.16em] text-zinc-500 uppercase">Signal</dt>
                        <dd class="mt-1 text-zinc-200">Status &amp; latency</dd>
                    </div>
                    <div>
                        <dt class="font-mono text-[10px] tracking-[0.16em] text-zinc-500 uppercase">Alerts</dt>
                        <dd class="mt-1 text-zinc-200">Down &amp; expiring</dd>
                    </div>
                </dl>
            </section>

            <section class="flex items-center justify-center px-6 py-10">
                <div class="flex w-full max-w-md flex-col gap-8">
                    <a href="{{ route('home') }}" class="flex items-center gap-3 lg:hidden" wire:navigate>
                        <span class="flex size-9 items-center justify-center rounded-lg border border-emerald-400/30 bg-emerald-400/10 text-emerald-300">
                            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" d="M12 12h.01M7.5 12a4.5 4.5 0 0 1 9 0M4 12a8 8 0 0 1 16 0" />
                                <path stroke-linecap="round" d="M12 12v6" />
                            </svg>
                        </span>
                        <span class="text-sm font-semibold">Wordmon</span>
                    </a>

                    <div class="flex gap-2 lg:hidden">
                        <span class="rounded-full border border-emerald-400/25 bg-emerald-400/10 px-2.5 py-1 font-mono text-[10px] tracking-wider text-emerald-300 uppercase">3 up</span>
                        <span class="rounded-full border border-amber-400/25 bg-amber-400/10 px-2.5 py-1 font-mono text-[10px] tracking-wider text-amber-300 uppercase">1 watch</span>
                        <span class="rounded-full border border-rose-400/25 bg-rose-400/10 px-2.5 py-1 font-mono text-[10px] tracking-wider text-rose-300 uppercase">1 down</span>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-zinc-950/70 p-6 shadow-[0_0_0_1px_rgba(255,255,255,0.03),0_20px_60px_rgba(0,0,0,0.4)] backdrop-blur-xl sm:p-8">
                        {{ $slot }}
                    </div>
                </div>
            </section>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
