<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MapboxService
{
    private readonly ?string $token;

    public function __construct(?string $token = null)
    {
        $this->token = $token ?? config('services.mapbox.token');
    }

    public function isConfigured(): bool
    {
        return filled($this->token);
    }

    /**
     * Geocode a free-text address into [latitude, longitude].
     *
     * @return array{0: float, 1: float}|null
     */
    public function geocode(string $address): ?array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('MAPBOX_TOKEN no está configurado.');
        }

        $response = Http::get('https://api.mapbox.com/geocoding/v5/mapbox.places/'.rawurlencode($address).'.json', [
            'access_token' => $this->token,
            'limit' => 1,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $coordinates = $response->json('features.0.center');

        if (! $coordinates) {
            return null;
        }

        return [(float) $coordinates[1], (float) $coordinates[0]];
    }

    /**
     * Optimize the visiting order of a set of coordinates using the Mapbox Optimization API.
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates  Ordered [lat, lng] pairs, first one is the depot/origin.
     * @return array<int, array{index: int, duration: int, distance: int}>|null Original indexes in optimized order with leg metrics.
     */
    public function optimizeOrder(array $coordinates): ?array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('MAPBOX_TOKEN no está configurado.');
        }

        $coordinatesParam = collect($coordinates)
            ->map(fn (array $pair): string => $pair[1].','.$pair[0])
            ->implode(';');

        $response = Http::get('https://api.mapbox.com/optimized-trips/v1/mapbox/driving/'.$coordinatesParam, [
            'access_token' => $this->token,
            'source' => 'first',
            'roundtrip' => 'false',
        ]);

        if (! $response->successful() || $response->json('code') !== 'Ok') {
            return null;
        }

        $optimizedOrder = collect($response->json('waypoints'))
            ->map(fn (array $waypoint, int $originalIndex): array => [
                'original_index' => $originalIndex,
                'waypoint_index' => $waypoint['waypoint_index'],
            ])
            ->sortBy('waypoint_index')
            ->values();

        $legs = collect($response->json('trips.0.legs'));

        return $optimizedOrder->map(function (array $entry, int $position) use ($legs): array {
            $leg = $legs->get($position);

            return [
                'index' => $entry['original_index'],
                'duration' => (int) round($leg['duration'] ?? 0),
                'distance' => (int) round($leg['distance'] ?? 0),
            ];
        })->all();
    }
}
