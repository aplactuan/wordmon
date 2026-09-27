<?php

namespace App\Livewire;

use App\Models\Website;
use App\Services\WebsiteCsvImporter;
use App\Services\WebsiteInspector;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Websites')]
class Dashboard extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public string $domain = '';

    public string $username = '';

    public string $applicationPassword = '';

    public bool $showAddForm = false;

    public bool $showImportForm = false;

    public ?TemporaryUploadedFile $csvFile = null;

    /** @var array{imported: int, duplicates: int, invalid: int, issues: list<string>}|null */
    public ?array $importSummary = null;

    public bool $showIntegration = false;

    public string $integrationDomain = '';

    public string $integrationToken = '';

    public string $checksUrl = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function addWebsite(): void
    {
        $this->domain = Website::normalizeDomain($this->domain);

        $this->validate([
            'domain' => [
                'required',
                'max:253',
                'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
                Rule::unique('websites', 'domain')->where('user_id', Auth::id()),
            ],
            'username' => ['required', 'string', 'max:255'],
            'applicationPassword' => ['required', 'string', 'max:255'],
        ], [
            'domain.regex' => 'Enter a domain such as example.com.',
        ]);

        $website = Website::create([
            'user_id' => Auth::id(),
            'domain' => $this->domain,
            'username' => $this->username,
            'application_password' => $this->applicationPassword,
        ]);
        $website->ensureWebhookToken();

        $this->reset('domain', 'username', 'applicationPassword', 'showAddForm');
        $this->resetPage();
        session()->flash('status', 'Website added. Select Check now to run its first check.');
    }

    public function openImport(): void
    {
        $this->reset('csvFile', 'importSummary');
        $this->resetValidation('csvFile');
        $this->showImportForm = true;
    }

    public function importCsv(WebsiteCsvImporter $importer): void
    {
        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path = $this->csvFile?->getRealPath();

        if ($path === null || $path === '') {
            $this->addError('csvFile', 'The CSV file could not be read.');

            return;
        }

        $this->importSummary = $importer->import((int) Auth::id(), $path);
        $this->reset('csvFile');
        $this->resetPage();
    }

    public function checkWebsite(int $websiteId, WebsiteInspector $inspector): void
    {
        $website = Website::query()->where('user_id', Auth::id())->findOrFail($websiteId);
        $result = $inspector->inspect($website);

        $website->recordCheck($result);

        session()->flash('status', $result['check_error'] ?? 'Check completed for '.$website->domain.'.');
    }

    public function showIntegration(int $websiteId): void
    {
        $website = Website::query()->where('user_id', Auth::id())->findOrFail($websiteId);

        $this->integrationDomain = $website->domain;
        $this->integrationToken = $website->ensureWebhookToken();
        $this->checksUrl = route('api.monitoring.websites.checks.store', $website);
        $this->showIntegration = true;
    }

    public function render(): View
    {
        $query = Website::query()->where('user_id', Auth::id());

        $totals = (clone $query)->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status_code = 200 THEN 1 ELSE 0 END) as healthy')
            ->selectRaw('SUM(CASE WHEN (status_code IS NOT NULL AND status_code <> 200) OR (checked_at IS NOT NULL AND status_code IS NULL) OR check_error IS NOT NULL OR ssl_expires_at <= ? THEN 1 ELSE 0 END) as attention', [now()->addDays(30)])
            ->first();

        $websites = (clone $query)
            ->when($this->search !== '', fn ($query) => $query->where('domain', 'like', '%'.$this->search.'%'))
            ->orderBy('domain')
            ->paginate(10);

        return view('livewire.dashboard', [
            'websites' => $websites,
            'totals' => $totals,
        ]);
    }
}
