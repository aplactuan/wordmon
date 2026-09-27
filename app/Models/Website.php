<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\WebsiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $domain
 * @property string $username
 * @property string $application_password
 * @property string|null $webhook_token
 * @property int|null $status_code
 * @property string|null $wordpress_version
 * @property CarbonInterface|null $ssl_expires_at
 * @property CarbonInterface|null $checked_at
 * @property string|null $check_error
 */
#[Fillable(['user_id', 'domain', 'username', 'application_password', 'status_code', 'wordpress_version', 'ssl_expires_at', 'checked_at', 'check_error'])]
#[Hidden(['application_password', 'webhook_token'])]
class Website extends Model
{
    /** @use HasFactory<WebsiteFactory> */
    use HasFactory;

    public static function normalizeDomain(string $domain): string
    {
        return strtolower(trim(preg_replace('#^https?://#i', '', $domain) ?? $domain, '/ '));
    }

    protected function casts(): array
    {
        return [
            'application_password' => 'encrypted',
            'webhook_token' => 'encrypted',
            'ssl_expires_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ensureWebhookToken(): string
    {
        if ($this->webhook_token === null) {
            $this->webhook_token = Str::random(64);
            $this->save();
        }

        return $this->webhook_token;
    }

    /**
     * @param  array{status_code: int|null, wordpress_version?: mixed, ssl_expires_at?: mixed, checked_at?: mixed, check_error?: string|null}  $result
     */
    public function recordCheck(array $result): void
    {
        $this->update($result['status_code'] === 200 && ($result['check_error'] ?? null) === null
            ? $result
            : ['status_code' => $result['status_code']]);
    }
}
