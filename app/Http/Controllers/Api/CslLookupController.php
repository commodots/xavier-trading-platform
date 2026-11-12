<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CSL\CslStockClient;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class CslLookupController extends Controller
{
    public function __construct(
        protected CslStockClient $client
    ) {}

    public function countries(): array
    {
        return [
            'countries' => $this->cachedLookup(
                'csl.customer.countries.v3',
                fn (): array => $this->client->countries(),
                'GetCRCSCountries',
                'country_code',
                'country_description',
                isCountry: true
            ),
        ];
    }

    public function banks(): array
    {
        return [
            'banks' => $this->cachedLookup(
                'csl.customer.banks.v3',
                fn (): array => $this->client->banks(),
                'GetCRCSBanks',
                'bank_code',
                'bank_description',
                'bank_type_description',
                'nibss_id'
            ),
        ];
    }

    protected function cachedLookup(
        string $cacheKey,
        callable $fetch,
        string $responseKey,
        string $codeField,
        string $descriptionField,
        ?string $typeField = null,
        ?string $nibssField = null,
        bool $isCountry = false
    ): array {
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $rows = $this->extractRows($fetch(), $responseKey, $codeField);
        $normalized = collect($rows)
            ->filter(fn ($row): bool => is_array($row) && filled($this->field($row, $codeField)))
            ->map(function (array $row) use ($codeField, $descriptionField, $typeField, $nibssField, $isCountry): array {
                $item = [
                    'code' => (string) $this->field($row, $codeField),
                    'description' => (string) ($this->field($row, $descriptionField) ?? ''),
                ];

                if ($typeField !== null) {
                    $item['type'] = (string) ($this->field($row, $typeField) ?? '');
                }

                if ($nibssField !== null) {
                    $item['nibss_id'] = (string) ($this->field($row, $nibssField) ?? '');
                }

                if ($isCountry) {
                    $home = $this->field($row, 'home_yesno');
                    $item['is_home'] = in_array(strtoupper((string) $home), ['Y', '1', 'TRUE'], true);
                }

                return $item;
            })
            ->values()
            ->all();

        if ($normalized === []) {
            throw new RuntimeException("CSL returned no records for {$responseKey}.");
        }

        Cache::put($cacheKey, $normalized, now()->addHours(12));

        return $normalized;
    }

    protected function extractRows(array $response, string $responseKey, string $codeField): array
    {
        $containers = [$response];

        while ($containers !== []) {
            $container = array_shift($containers);

            if (array_is_list($container)) {
                foreach ($container as $item) {
                    if (is_array($item)) {
                        $containers[] = $item;
                    }
                }

                continue;
            }

            foreach ([$responseKey, 'output'] as $key) {
                if (isset($container[$key]) && is_array($container[$key])) {
                    $rows = $container[$key];

                    return array_is_list($rows) ? $rows : [$rows];
                }
            }

            foreach (['data', 'result'] as $key) {
                if (isset($container[$key]) && is_array($container[$key])) {
                    $nested = $container[$key];
                    if ($this->field($nested, $codeField) !== null) {
                        return [$nested];
                    }
                    $containers[] = $nested;
                }
            }
        }

        throw new RuntimeException("CSL response did not contain {$responseKey} lookup rows.");
    }

    protected function field(array $row, string $field): mixed
    {
        $lookup = strtolower($field);

        foreach ($row as $key => $value) {
            if (strtolower((string) $key) === $lookup) {
                return $value;
            }
        }

        return null;
    }
}
