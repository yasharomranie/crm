<?php

namespace App\Domain\Crm;

use App\Models\Guest;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Bulk-creates guests from an Excel/CSV file. The first row must name the
 * columns (Persian or English); only "name" is required. Everything is
 * checked before anything is written: rows with a bad mobile/email are
 * reported and skipped, rows that match an existing guest (same mobile,
 * email or national code - mobile compared in any common format) or an
 * earlier row of the same file are skipped as duplicates, and the rest are
 * created in one transaction. Nothing existing is ever modified.
 */
class GuestImporter
{
    public const MAX_ROWS = 5000;

    /** field => accepted header names (lower-cased) */
    private const HEADERS = [
        'name' => ['نام', 'نام و نام خانوادگی', 'name', 'full name'],
        'mobile' => ['موبایل', 'شماره موبایل', 'تلفن همراه', 'mobile', 'phone'],
        'email' => ['ایمیل', 'پست الکترونیک', 'email'],
        'national_code' => ['کد ملی', 'national_code', 'national code'],
        'notes' => ['یادداشت', 'توضیحات', 'notes'],
    ];

    /**
     * @return array{created: int, duplicates: int, invalid: int, errors: list<string>}
     */
    public function import(string $path, string $extension): array
    {
        $rows = $this->readRows($path, $extension);

        if ($rows === []) {
            throw new InvalidArgumentException('فایل خالی است.');
        }

        $columns = $this->mapHeader(array_shift($rows));

        if (! isset($columns['name'])) {
            throw new InvalidArgumentException('ستونی با عنوان «نام» در ردیف اول فایل پیدا نشد.');
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new InvalidArgumentException('حداکثر '.self::MAX_ROWS.' ردیف در هر بار قابل درون‌ریزی است.');
        }

        $existing = $this->existingKeys();
        $seen = ['mobile' => [], 'email' => [], 'national_code' => []];
        $toCreate = [];
        $duplicates = 0;
        $invalid = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $record = [];

            foreach ($columns as $field => $position) {
                $record[$field] = trim((string) ($row[$position] ?? ''));
            }

            if (implode('', $record) === '') {
                continue;
            }

            if ($record['name'] === '' || mb_strlen($record['name']) > 255) {
                $invalid++;
                $errors[] = "ردیف {$line}: نام خالی یا بیش از حد طولانی است.";

                continue;
            }

            $mobile = null;

            if (($record['mobile'] ?? '') !== '') {
                $mobile = GuestDuplicateDetector::normalizeMobile($record['mobile']);

                if ($mobile === null) {
                    $invalid++;
                    $errors[] = "ردیف {$line}: شماره‌ی موبایل «{$record['mobile']}» معتبر نیست.";

                    continue;
                }
            }

            $email = GuestDuplicateDetector::normalizeEmail($record['email'] ?? null);

            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid++;
                $errors[] = "ردیف {$line}: ایمیل «{$record['email']}» معتبر نیست.";

                continue;
            }

            $nationalCode = ($record['national_code'] ?? '') !== '' ? preg_replace('/\D+/', '', (string) $record['national_code']) : null;

            if ($nationalCode !== null && ($nationalCode === '' || strlen($nationalCode) > 20)) {
                $invalid++;
                $errors[] = "ردیف {$line}: کد ملی معتبر نیست.";

                continue;
            }

            $keys = ['mobile' => $mobile, 'email' => $email, 'national_code' => $nationalCode];

            if ($this->isDuplicate($keys, $existing, $seen)) {
                $duplicates++;

                continue;
            }

            foreach ($keys as $field => $value) {
                if ($value !== null) {
                    $seen[$field][$value] = true;
                }
            }

            $toCreate[] = [
                'name' => $record['name'],
                'mobile' => $mobile,
                'email' => $email,
                'national_code' => $nationalCode,
                'notes' => ($record['notes'] ?? '') !== '' ? $record['notes'] : null,
            ];
        }

        DB::transaction(function () use ($toCreate) {
            foreach ($toCreate as $attributes) {
                Guest::create($attributes);
            }
        });

        return [
            'created' => count($toCreate),
            'duplicates' => $duplicates,
            'invalid' => $invalid,
            'errors' => array_slice($errors, 0, 20),
        ];
    }

    /**
     * @return list<list<mixed>>
     */
    private function readRows(string $path, string $extension): array
    {
        /** @var ReaderInterface $reader */
        $reader = match (strtolower($extension)) {
            'xlsx' => new XlsxReader,
            'csv' => new CsvReader,
            default => throw new InvalidArgumentException('فقط فایل xlsx یا csv پذیرفته می‌شود.'),
        };

        $rows = [];

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * @param  list<mixed>  $header
     * @return array<string, int> field => column position
     */
    private function mapHeader(array $header): array
    {
        $columns = [];

        foreach ($header as $position => $title) {
            // A UTF-8 byte-order mark (Excel's CSV) would otherwise glue itself to the first title.
            $normalized = mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $title)));

            foreach (self::HEADERS as $field => $aliases) {
                if (in_array($normalized, $aliases, true) && ! isset($columns[$field])) {
                    $columns[$field] = $position;
                }
            }
        }

        return $columns;
    }

    /**
     * @return array{mobile: array<string, true>, email: array<string, true>, national_code: array<string, true>}
     */
    private function existingKeys(): array
    {
        $keys = ['mobile' => [], 'email' => [], 'national_code' => []];

        Guest::query()->select(['mobile', 'email', 'national_code'])->chunk(1000, function ($guests) use (&$keys) {
            foreach ($guests as $guest) {
                if ($mobile = GuestDuplicateDetector::normalizeMobile($guest->mobile)) {
                    $keys['mobile'][$mobile] = true;
                }

                if ($email = GuestDuplicateDetector::normalizeEmail($guest->email)) {
                    $keys['email'][$email] = true;
                }

                if (filled($guest->national_code)) {
                    $keys['national_code'][preg_replace('/\D+/', '', $guest->national_code)] = true;
                }
            }
        });

        return $keys;
    }

    /**
     * @param  array<string, ?string>  $keys
     * @param  array<string, array<string, true>>  $existing
     * @param  array<string, array<string, true>>  $seen
     */
    private function isDuplicate(array $keys, array $existing, array $seen): bool
    {
        foreach ($keys as $field => $value) {
            if ($value !== null && (isset($existing[$field][$value]) || isset($seen[$field][$value]))) {
                return true;
            }
        }

        return false;
    }
}
