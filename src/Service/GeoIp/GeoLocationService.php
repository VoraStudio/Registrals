<?php

declare(strict_types=1);

namespace App\Service\GeoIp;

use GeoIp2\Database\Reader;

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
        'CA' => 'Canadà',
        'MX' => 'Mèxic',
        'BR' => 'Brasil',
        'AR' => 'Argentina',
        'CL' => 'Xile',
        'CO' => 'Colòmbia',
        'SE' => 'Suècia',
        'NO' => 'Noruega',
        'DK' => 'Dinamarca',
        'AT' => 'Àustria',
        'PL' => 'Polònia',
        'FI' => 'Finlàndia',
        'GR' => 'Grècia',
        'CZ' => 'República Txeca',
        'RO' => 'Romania',
        'UA' => 'Ucraïna',
        'CN' => 'Xina',
        'JP' => 'Japó',
        'AU' => 'Austràlia',
    ];

    private ?Reader $reader = null;
    private bool $readerAttempted = false;

    public function __construct(
        private readonly ?GeoIpDatabaseManager $dbManager = null
    ) {
    }

    /**
     * Retorna l'estat actual de la base de dades local de GeoIP.
     *
     * @return array{
     *     exists: bool,
     *     path: string,
     *     updated_at: ?\DateTimeImmutable,
     *     age_days: ?int,
     *     is_stale: bool,
     *     size_formatted: ?string,
     *     has_license_key: bool
     * }
     */
    public function getDatabaseStatus(): array
    {
        if ($this->dbManager !== null) {
            return $this->dbManager->getDatabaseStatus();
        }

        return [
            'exists' => false,
            'path' => '',
            'updated_at' => null,
            'age_days' => null,
            'is_stale' => true,
            'size_formatted' => null,
            'has_license_key' => false,
        ];
    }

    private function getReader(): ?Reader
    {
        if ($this->readerAttempted) {
            return $this->reader;
        }

        $this->readerAttempted = true;
        $dbPath = $this->dbManager?->getDatabasePath();

        if ($dbPath !== null && file_exists($dbPath) && is_readable($dbPath)) {
            try {
                $this->reader = new Reader($dbPath);
            } catch (\Throwable) {
                $this->reader = null;
            }
        }

        return $this->reader;
    }

    /**
     * Resol una IP a un codi de país ISO-3166-1 alpha-2 i nom en català.
     * Ordre de resolució: capçalera de xarxa/CDN -> privada/loopback -> MaxMind GeoLite2 -> heurística local.
     *
     * @return array{code: string, name: string}
     */
    public function resolveCountry(?string $ip, ?string $headerCountry = null): array
    {
        $headerCode = $headerCountry !== null ? strtoupper(trim($headerCountry)) : '';
        if (strlen($headerCode) === 2 && ctype_alpha($headerCode) && !in_array($headerCode, ['XX', 'T1'], true)) {
            return [
                'code' => $headerCode,
                'name' => self::COUNTRY_NAMES[$headerCode] ?? $headerCode,
            ];
        }

        if (empty($ip) || $this->isPrivateOrLoopback($ip)) {
            return [
                'code' => 'ES',
                'name' => self::COUNTRY_NAMES['ES'],
            ];
        }

        // 1. Intent de resolució via base de dades oficial MaxMind (alta precisió IPv4 + IPv6)
        $reader = $this->getReader();
        if ($reader !== null) {
            try {
                $record = $reader->country($ip);
                $isoCode = $record->country->isoCode;
                if ($isoCode !== null && $isoCode !== '') {
                    $isoCode = strtoupper($isoCode);
                    $name = self::COUNTRY_NAMES[$isoCode]
                        ?? $record->country->names['es']
                        ?? $record->country->names['en']
                        ?? $record->country->name
                        ?? $isoCode;

                    return [
                        'code' => $isoCode,
                        'name' => $name,
                    ];
                }
            } catch (\Throwable) {
                // AddressNotFoundException o qualsevol incidència -> fallback local
            }
        }

        // 2. Resolució sobirana d'alta velocitat per rangs coneguts (Fallback)
        $code = $this->lookupCountryCode($ip);

        return [
            'code' => $code,
            'name' => self::COUNTRY_NAMES[$code] ?? $code,
        ];
    }

    /**
     * Agrega un conjunt de peticions (per IP o directament per country_code)
     * i retorna les dades preparades per a Google GeoChart.
     *
     * @param array<int, array{ip_address?: ?string, country_code?: ?string, cnt: int|string}> $rawRows
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
            $count = (int) ($row['cnt'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            if (!empty($row['country_code'])) {
                $code = strtoupper(trim((string) $row['country_code']));
                $name = self::COUNTRY_NAMES[$code] ?? $code;
            } else {
                $ip = $row['ip_address'] ?? null;
                $geo = $this->resolveCountry($ip);
                $code = $geo['code'];
                $name = $geo['name'];
            }

            if (!isset($countryCounts[$code])) {
                $countryCounts[$code] = [
                    'code' => $code,
                    'name' => $name,
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
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
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
