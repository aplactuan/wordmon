<div class="mx-auto flex w-full max-w-7xl flex-col gap-8 px-4 py-8 sm:px-6 lg:px-10 lg:py-10">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">Overview</p>
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Your websites</h1>
            <p class="text-sm text-slate-500">A clear view of your WordPress sites and their latest checks.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button wire:click="openImport">Import CSV</flux:button>
            <flux:button variant="primary" icon="plus" wire:click="$set('showAddForm', true)">Add website</flux:button>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800" role="status">{{ session('status') }}</div>
    @endif

    <section class="grid gap-4 sm:grid-cols-3" aria-label="Website summary">
        <div class="rounded-xl border border-slate-200 bg-white px-5 py-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Total websites</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">{{ $totals->total ?? 0 }}</p>
            <p class="mt-1 text-xs text-slate-500">Sites in your workspace</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-5 py-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Responding normally</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-emerald-700">{{ $totals->healthy ?? 0 }}</p>
            <p class="mt-1 text-xs text-slate-500">Latest HTTP check returned 2xx or 3xx</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-5 py-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Needs attention</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-amber-700">{{ $totals->attention ?? 0 }}</p>
            <p class="mt-1 text-xs text-slate-500">Check errors or certificates expiring soon</p>
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="site-list-heading">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="site-list-heading" class="text-base font-semibold text-slate-900">Monitored websites</h2>
                <p class="mt-1 text-sm text-slate-500">Status from the most recent completed check.</p>
            </div>
            <div class="w-full sm:w-72">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by domain" aria-label="Search by domain" />
            </div>
        </div>

        @if ($websites->isEmpty())
            <div class="flex flex-col items-center px-6 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                    <flux:icon.globe-alt class="size-6" />
                </div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">{{ $search === '' ? 'No websites yet' : 'No matching websites' }}</h3>
                <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $search === '' ? 'Add your first WordPress website to start tracking its status, version, and certificate.' : 'Try another domain name or clear your search.' }}</p>
                @if ($search === '')
                    <flux:button class="mt-5" wire:click="$set('showAddForm', true)">Add your first website</flux:button>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3.5">Website</th>
                            <th class="px-5 py-3.5">Latest status</th>
                            <th class="px-5 py-3.5">WordPress</th>
                            <th class="px-5 py-3.5">SSL expires</th>
                            <th class="px-5 py-3.5">Last checked</th>
                            <th class="px-5 py-3.5 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($websites as $website)
                            <tr wire:key="website-{{ $website->id }}" class="hover:bg-slate-50/70">
                                <td class="px-5 py-4">
                                    <span class="block font-semibold text-slate-900">{{ $website->domain }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">{{ $website->username }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    @if ($website->status_code !== null)
                                        <span @class([
                                            'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold',
                                            'bg-emerald-50 text-emerald-700' => $website->status_code < 400,
                                            'bg-rose-50 text-rose-700' => $website->status_code >= 400,
                                        ])>
                                            <span @class(['size-1.5 rounded-full', 'bg-emerald-500' => $website->status_code < 400, 'bg-rose-500' => $website->status_code >= 400])></span>
                                            HTTP {{ $website->status_code }}
                                        </span>
                                    @else
                                        <span class="text-slate-500">{{ $website->checked_at ? 'Check failed' : 'Not yet checked' }}</span>
                                    @endif
                                    @if ($website->check_error)
                                        <span class="mt-1 block max-w-48 text-xs text-amber-700">{{ $website->check_error }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-slate-700">{{ $website->wordpress_version ? 'v'.$website->wordpress_version : 'Unavailable' }}</td>
                                <td class="px-5 py-4">
                                    @if ($website->ssl_expires_at)
                                        <span @class(['font-medium', 'text-amber-700' => $website->ssl_expires_at->lte(now()->addDays(30)), 'text-slate-700' => $website->ssl_expires_at->gt(now()->addDays(30))])>{{ $website->ssl_expires_at->format('M j, Y') }}</span>
                                        @if ($website->ssl_expires_at->lte(now()->addDays(30)))
                                            <span class="mt-1 block text-xs text-amber-700">{{ $website->ssl_expires_at->isPast() ? 'Expired' : 'Expiring soon' }}</span>
                                        @endif
                                    @else
                                        <span class="text-slate-500">Unavailable</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-slate-600">{{ $website->checked_at?->format('M j, Y · g:i A') ?? 'Not yet checked' }}</td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="checkWebsite({{ $website->id }})" wire:loading.attr="disabled" wire:target="checkWebsite({{ $website->id }})">Check now</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="showIntegration({{ $website->id }})">n8n setup</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4">{{ $websites->links() }}</div>
        @endif
    </section>

    <flux:modal wire:model="showAddForm" class="w-full max-w-lg">
        <form wire:submit="addWebsite" class="space-y-5">
            <div>
                <flux:heading size="lg">Add a WordPress website</flux:heading>
                <flux:subheading>Connect a site to start monitoring its latest status.</flux:subheading>
            </div>
            <flux:input wire:model="domain" label="Domain name" placeholder="example.com" required />
            <flux:input wire:model="username" label="WordPress username" autocomplete="username" required />
            <flux:input wire:model="applicationPassword" type="password" label="Application password" description="Create this in WordPress under Users → Profile → Application Passwords." required />
            <p class="text-xs leading-relaxed text-slate-500">Your application password is encrypted when stored and used for manual checks. Configure n8n with its own WordPress credential for hourly checks.</p>
            <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                <flux:button type="button" variant="ghost" wire:click="$set('showAddForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="addWebsite">Add website</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showImportForm" class="w-full max-w-lg">
        <form wire:submit="importCsv" class="space-y-5">
            <div>
                <flux:heading size="lg">Import websites from CSV</flux:heading>
                <flux:subheading>Add domains and WordPress credentials in one file.</flux:subheading>
            </div>
            <div class="space-y-2">
                <p class="text-sm text-slate-600">Use these exact column names in the first row:</p>
                <pre class="overflow-x-auto rounded-lg bg-slate-100 p-3 text-xs leading-relaxed text-slate-700">domain,username,application_password
example.com,admin,"abcd efgh ijkl mnop"</pre>
                <p class="text-xs text-slate-500">Up to 500 websites per file. Existing domains are skipped; invalid rows are reported. Application passwords are encrypted when stored.</p>
            </div>
            <flux:input type="file" wire:model="csvFile" accept=".csv,text/csv" label="CSV file" />
            <p wire:loading wire:target="csvFile" class="text-sm text-slate-500">Uploading CSV…</p>

            @if ($importSummary)
                <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900" role="status">
                    <p class="font-semibold">{{ $importSummary['imported'] }} imported · {{ $importSummary['duplicates'] }} duplicates skipped · {{ $importSummary['invalid'] }} invalid rows</p>
                    @if ($importSummary['issues'])
                        <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
                            @foreach ($importSummary['issues'] as $issue)
                                <li wire:key="import-issue-{{ $loop->index }}">{{ $issue }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                <flux:button type="button" variant="ghost" wire:click="$set('showImportForm', false)">Done</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="csvFile,importCsv">Import websites</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showIntegration" class="w-full max-w-xl">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Connect {{ $integrationDomain }} to n8n</flux:heading>
                <flux:subheading>Use this site's token when n8n sends its hourly result to Wordmon.</flux:subheading>
            </div>
            <div class="space-y-2">
                <label for="integration-token" class="block text-sm font-medium text-slate-800">Bearer token</label>
                <input id="integration-token" type="text" readonly onclick="this.select()" value="{{ $integrationToken }}" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-800" />
                <p class="text-xs text-slate-500">Keep this token private. Click the field to select it.</p>
            </div>
            <div class="space-y-2">
                <label for="integration-post-url" class="block text-sm font-medium text-slate-800">POST check result</label>
                <input id="integration-post-url" type="text" readonly onclick="this.select()" value="{{ $checksUrl }}" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-800" />
                <pre class="overflow-x-auto rounded-lg bg-slate-100 p-3 text-xs leading-relaxed text-slate-700">{{ json_encode(['domain' => $integrationDomain, 'status_code' => 200, 'wordpress_version' => '6.6.2', 'ssl_expires_at' => now()->addDays(90)->toIso8601String(), 'checked_at' => now()->toIso8601String(), 'check_error' => null], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
            <p class="text-xs text-slate-500">Send <code>Authorization: Bearer &lt;token&gt;</code> and <code>Content-Type: application/json</code>. Send a newer check time with each result. Use HTTPS outside local development.</p>
            <div class="flex justify-end border-t border-slate-100 pt-4">
                <flux:button wire:click="$set('showIntegration', false)">Done</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
