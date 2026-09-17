<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReverseGeocoder
{
    /**
     * Resolve coordinates into a readable address.
     *
     * This never throws: attendance must still be recorded when the
     * geocoding service is unavailable, so failures return empty values.
     *
     * @return array{address: ?string, city: ?string, state: ?string, country: ?string}
     */
    public function lookup(float|int|string|null $latitude, float|int|string|null $longitude): array
    {
        $empty = $this->emptyResult();

        if (! $this->isUsable($latitude) || ! $this->isUsable($longitude)) {
            return $empty;
        }

        $latitude = round((float) $latitude, 5);
        $longitude = round((float) $longitude, 5);

        $key = sprintf('reverse-geocode:%s,%s', $latitude, $longitude);

        return Cache::remember($key, now()->addDays(30), function () use ($latitude, $longitude, $empty) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => (string) config('services.nominatim.user_agent'),
                    'Accept' => 'application/json',
                ])
                    ->timeout(6)
                    ->connectTimeout(4)
                    ->retry(1, 250, throw: false)
                    ->get(rtrim((string) config('services.nominatim.endpoint'), '/').'/reverse', array_filter([
                        'format' => 'jsonv2',
                        'lat' => $latitude,
                        'lon' => $longitude,
                        'zoom' => 18,
                        'addressdetails' => 1,
                        'email' => config('services.nominatim.email'),
                    ]));

                if (! $response->successful()) {
                    return $empty;
                }

                return $this->parse((array) $response->json());
            } catch (\Throwable $e) {
                Log::warning('Reverse geocoding failed.', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'message' => $e->getMessage(),
                ]);

                return $empty;
            }
        });
    }

    /**
     * "Ikeja, Lagos" style label for the given coordinates.
     */
    public function label(float|int|string|null $latitude, float|int|string|null $longitude): ?string
    {
        $location = $this->lookup($latitude, $longitude);

        $parts = array_filter([$location['city'], $location['state']]);

        return $parts !== [] ? implode(', ', $parts) : $location['address'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{address: ?string, city: ?string, state: ?string, country: ?string}
     */
    protected function parse(array $payload): array
    {
        $parts = (array) ($payload['address'] ?? []);

        $city = $parts['city'] ?? $parts['town'] ?? $parts['village'] ?? $parts['municipality']
            ?? $parts['suburb'] ?? $parts['city_district'] ?? $parts['county'] ?? null;

        $state = $parts['state'] ?? $parts['region'] ?? $parts['state_district'] ?? null;

        return [
            'address' => $this->limit($this->compactAddress($parts) ?? ($payload['display_name'] ?? null), 250),
            'city' => $this->limit($city, 110),
            'state' => $this->limit($state, 110),
            'country' => $this->limit($parts['country'] ?? null, 110),
        ];
    }

    /**
     * Build a short address such as "Example Road, Ikeja, Lagos".
     *
     * @param  array<string, mixed>  $parts
     */
    protected function compactAddress(array $parts): ?string
    {
        $lines = [];

        $street = trim(implode(' ', array_filter([
            $parts['house_number'] ?? null,
            $parts['road'] ?? null,
        ])));

        if ($street !== '') {
            $lines[] = $street;
        }

        foreach (['neighbourhood', 'suburb', 'city_district', 'city', 'town', 'village', 'municipality', 'county', 'state', 'country'] as $key) {
            if (! empty($parts[$key])) {
                $lines[] = $parts[$key];
            }
        }

        $lines = array_values(array_filter(array_unique($lines)));

        return $lines !== [] ? implode(', ', array_slice($lines, 0, 5)) : null;
    }

    protected function isUsable(float|int|string|null $value): bool
    {
        return $value !== null && $value !== '' && is_numeric($value);
    }

    protected function limit(?string $value, int $length): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, $length);
    }

    /**
     * @return array{address: null, city: null, state: null, country: null}
     */
    protected function emptyResult(): array
    {
        return ['address' => null, 'city' => null, 'state' => null, 'country' => null];
    }
}
