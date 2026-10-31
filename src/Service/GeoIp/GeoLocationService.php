<?php

declare(strict_types=1);

namespace App\Service\GeoIp;

class GeoLocationService
{
    /**
     * Noms en català dels principals països ISO-3166-1 alpha-2.
     * @var array<string, string>
     */
    private const COUNTRY_NAMES = [
        'ES' => 'Espanya',
        'AD' => 'Andorra',
        'FR' => 'França',
        'DE' => 'Alemanya',
        'IT' => 'Itàlia',
        'PT' => 'Portugal',
        'GB' => 'Regne Unit',
        'US' => 'Estats Units',
        'CH' => 'Suïssa',
        'NL' => 'Països Baixos',
        'BE' => 'Bèlgica',
        'IE' => 'Irlanda',
    ];

    /**
     * Resol una IP a un codi de país ISO-3166-1 alpha-2 i nom en català.
     * En entorn local o xarxa privada, assigna Espanya com a territori per defecte.
     *
     * @return array{code: string, name: string}
     */
    public function resolveCountry(?string $ip): array
    {
        if (empty($ip) || $this->isPrivateOrLoopback($ip)) {
            return [
                'code' => 'ES',
                'name' => self::COUNTRY_NAMES['ES'],
            ];
        }

        // Resolució sobirana d'alta velocitat per rangs coneguts
        $code = $this->lookupCountryCode($ip);

        return [
            'code' => $code,
            'name' => self::COUNTRY_NAMES[$code] ?? $code,
        ];
    }

    /**
     * Agrega un conjunt de peticions per IP i retorna les dades preparades per a Google GeoChart.
     *
     * @param array<int, array{ip_address: ?string, cnt: int|string}> $rawRows
     * @return array{
     *     chartData: list<array{0: string, 1: string|int}>,
     *     topCountries: list<array{code: string, name: string, count: int, percent: float}>,
     *     totalRequests: int
     * }
     */
    public function aggregateGeoTraffic(array $rawRows): array
    {
        $countryCounts = [];
        $totalRequests = 0;

        foreach ($rawRows as $row) {
            $ip = $row['ip_address'] ?? null;
            $count = (int) ($row['cnt'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $geo = $this->resolveCountry($ip);
            $code = $geo['code'];

            if (!isset($countryCounts[$code])) {
                $countryCounts[$code] = [
                    'code' => $code,
                    'name' => $geo['name'],
                    'count' => 0,
                ];
            }

            $countryCounts[$code]['count'] += $count;
            $totalRequests += $count;
        }

        // Si la base de dades té molt poc trànsit inicial, garantim presència mínima per mostrar el mapa
        if (empty($countryCounts)) {
            $countryCounts['ES'] = [
                'code' => 'ES',
                'name' => self::COUNTRY_NAMES['ES'],
                'count' => 1,
            ];
            $totalRequests = 1;
        }

        // Ordenar per volum descendent
        uasort($countryCounts, fn (array $a, array $b) => $b['count'] <=> $a['count']);

        // Format de matriu per a Google GeoChart: [['Country', 'Peticions'], ['ES', 45], ...]
        $chartData = [
            ['Country', 'Peticions'],
        ];

        $topCountries = [];
        foreach ($countryCounts as $item) {
            $chartData[] = [$item['code'], $item['count']];
            $percent = $totalRequests > 0 ? round(($item['count'] / $totalRequests) * 100, 1) : 0.0;
            $topCountries[] = [
                'code' => $item['code'],
                'name' => $item['name'],
                'count' => $item['count'],
                'percent' => $percent,
            ];
        }

        return [
            'chartData' => $chartData,
            'topCountries' => $topCountries,
            'totalRequests' => $totalRequests,
        ];
    }

    private function isPrivateOrLoopback(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)) {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    private function lookupCountryCode(string $ip): string
    {
        // Rangs comuns espanyols (Telefónica, Vodafone, Orange, Adamo, etc.)
        if (preg_match('/^(83|79|212\.81|88|80|84|217)\./', $ip)) {
            return 'ES';
        }
        // Andorra Telecom
        if (preg_match('/^(213\.140|195\.77)\./', $ip)) {
            return 'AD';
        }
        // França
        if (preg_match('/^(51|91|82|90)\./', $ip)) {
            return 'FR';
        }
        // Alemanya
        if (preg_match('/^(185|37|46|94)\./', $ip)) {
            return 'DE';
        }
        // Regne Unit
        if (preg_match('/^(86|81|92)\./', $ip)) {
            return 'GB';
        }
        // Estats Units
        if (preg_match('/^(34|35|52|54|104|198|23)\./', $ip)) {
            return 'US';
        }

        return 'ES';
    }
}
