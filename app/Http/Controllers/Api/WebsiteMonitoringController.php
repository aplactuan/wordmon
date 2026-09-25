<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WebsiteMonitoringController extends Controller
{
    public function store(Request $request, Website $website): JsonResponse
    {
        $this->authorizeToken($request, $website);

        $validated = $request->validate([
            'domain' => ['required', 'string', Rule::in([$website->domain])],
            'status_code' => ['nullable', 'required_without:check_error', 'integer', 'between:100,599'],
            'wordpress_version' => ['nullable', 'string', 'max:50'],
            'ssl_expires_at' => ['nullable', 'date'],
            'checked_at' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(5)->toDateTimeString()],
            'check_error' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = DB::transaction(function () use ($website, $validated): bool {
            $current = Website::query()->lockForUpdate()->findOrFail($website->id);
            $checkedAt = Carbon::parse($validated['checked_at']);

            if ($current->checked_at?->gte($checkedAt)) {
                return false;
            }

            $current->update([
                'status_code' => $validated['status_code'] ?? null,
                'wordpress_version' => $validated['wordpress_version'] ?? null,
                'ssl_expires_at' => $validated['ssl_expires_at'] ?? null,
                'checked_at' => $checkedAt,
                'check_error' => $validated['check_error'] ?? null,
            ]);

            return true;
        });

        return response()->json(['updated' => $updated]);
    }

    private function authorizeToken(Request $request, Website $website): void
    {
        $submittedToken = $request->bearerToken();
        $storedToken = $website->webhook_token;

        abort_unless($submittedToken !== null && $storedToken !== null && hash_equals($storedToken, $submittedToken), 404);
    }
}
