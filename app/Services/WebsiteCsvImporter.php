<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WebsiteCsvImporter
{
    /**
     * @return array{imported: int, duplicates: int, invalid: int, issues: list<string>}
     */
    public function import(int $userId, string $path): array
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw ValidationException::withMessages(['csvFile' => 'The CSV file could not be read.']);
        }

        try {
            $header = fgetcsv($handle, 0, ',', '"', '');

            if ($header === false) {
                throw ValidationException::withMessages(['csvFile' => 'The CSV file is empty.']);
            }

            $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
            $columns = array_map(fn ($column): string => strtolower(trim((string) $column)), $header);

            if ($columns !== ['domain', 'username', 'application_password']) {
                throw ValidationException::withMessages(['csvFile' => 'The first row must be: domain,username,application_password.']);
            }

            $rows = [];
            $issues = [];
            $invalid = 0;
            $rowNumber = 1;
            $dataRows = 0;

            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $rowNumber++;

                if ($row === [null]) {
                    continue;
                }

                $dataRows++;

                if ($dataRows > 500) {
                    throw ValidationException::withMessages(['csvFile' => 'Import up to 500 websites at a time.']);
                }

                if (count($row) !== 3) {
                    $invalid++;
                    $this->addIssue($issues, "Row {$rowNumber}: expected three columns.");

                    continue;
                }

                $values = [
                    'domain' => Website::normalizeDomain((string) $row[0]),
                    'username' => trim((string) $row[1]),
                    'application_password' => trim((string) $row[2]),
                ];

                $validator = Validator::make($values, [
                    'domain' => ['required', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/'],
                    'username' => ['required', 'string', 'max:255'],
                    'application_password' => ['required', 'string', 'max:255'],
                ]);

                if ($validator->fails()) {
                    $invalid++;
                    $this->addIssue($issues, "Row {$rowNumber}: invalid ".implode(', ', $validator->errors()->keys()).'.');

                    continue;
                }

                $rows[] = $values;
            }
        } finally {
            fclose($handle);
        }

        if ($dataRows === 0) {
            throw ValidationException::withMessages(['csvFile' => 'The CSV file has no website rows.']);
        }

        [$imported, $duplicates] = DB::transaction(function () use ($rows, $userId): array {
            $imported = 0;
            $duplicates = 0;

            foreach ($rows as $row) {
                $website = Website::firstOrCreate(
                    ['user_id' => $userId, 'domain' => $row['domain']],
                    ['username' => $row['username'], 'application_password' => $row['application_password']],
                );

                if ($website->wasRecentlyCreated) {
                    $website->ensureWebhookToken();
                    $imported++;
                } else {
                    $duplicates++;
                }
            }

            return [$imported, $duplicates];
        });

        return compact('imported', 'duplicates', 'invalid', 'issues');
    }

    /** @param list<string> $issues */
    private function addIssue(array &$issues, string $issue): void
    {
        if (count($issues) < 8) {
            $issues[] = $issue;
        }
    }
}
