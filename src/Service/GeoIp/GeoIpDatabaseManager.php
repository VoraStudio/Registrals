<?php

declare(strict_types=1);

namespace App\Service\GeoIp;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeoIpDatabaseManager
{
    public const DB_FILENAME = 'GeoLite2-Country.mmdb';
    public const STALE_DAYS_THRESHOLD = 30;
    public const PUBLIC_MMDB_MIRROR_URL = 'https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-Country.mmdb';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%env(default::string:MAXMIND_LICENSE_KEY)%')]
        private readonly ?string $licenseKey = null,
    ) {
    }

    public function getDatabasePath(): string
    {
        return $this->projectDir . \DIRECTORY_SEPARATOR . 'var' . \DIRECTORY_SEPARATOR . 'geoip' . \DIRECTORY_SEPARATOR . self::DB_FILENAME;
    }

    /**
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
        $path = $this->getDatabasePath();
        $exists = is_file($path) && ($fh = @fopen($path, 'rb')) !== false;
        if ($exists && is_resource($fh)) {
            fclose($fh);
        }

        $updatedAt = null;
        $ageDays = null;
        $isStale = true;
        $sizeFormatted = null;

        if ($exists) {
            $mtime = filemtime($path);
            if ($mtime !== false) {
                $updatedAt = (new \DateTimeImmutable())->setTimestamp($mtime);
                $diff = (new \DateTimeImmutable())->diff($updatedAt);
                $ageDays = (int) $diff->days;
                $isStale = $ageDays >= self::STALE_DAYS_THRESHOLD;
            }
            $bytes = filesize($path);
            if ($bytes !== false) {
                $sizeFormatted = round($bytes / 1024 / 1024, 2) . ' MB';
            }
        }

        return [
            'exists' => $exists,
            'path' => $path,
            'updated_at' => $updatedAt,
            'age_days' => $ageDays,
            'is_stale' => $isStale,
            'size_formatted' => $sizeFormatted,
            'has_license_key' => !empty($this->licenseKey),
        ];
    }

    /**
     * Descarrega i actualitza la base de dades des de MaxMind (o mirall GeoLite2 si s'autoritza sense clau) amb reanomenat atòmic.
     *
     * @return array{success: bool, message: string}
     */
    public function updateDatabase(?string $licenseKeyOverride = null, bool $force = false, bool $allowPublicMirrorFallback = false): array
    {
        $key = trim($licenseKeyOverride ?? $this->licenseKey ?? '');

        $status = $this->getDatabaseStatus();
        if (!$force && $status['exists'] && !$status['is_stale']) {
            return [
                'success' => true,
                'message' => "La base de dades està actualitzada (té {$status['age_days']} dies, límit " . self::STALE_DAYS_THRESHOLD . ' dies). Fes servir --force per forçar la descàrrega.',
            ];
        }

        if ($key === '') {
            if ($allowPublicMirrorFallback) {
                return $this->downloadFromPublicMirror();
            }

            return [
                'success' => false,
                'message' => 'Cal una clau de llicència de MaxMind (MAXMIND_LICENSE_KEY al fitxer .env) o passar-la com a paràmetre. Es pot obtenir gratuïtament a maxmind.com.',
            ];
        }

        $downloadUrl = sprintf(
            'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=%s&suffix=tar.gz',
            urlencode($key)
        );

        $tempDir = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'geoip_' . bin2hex(random_bytes(6));
        $tempTarGz = $tempDir . '.tar.gz';
        $tempTar = $tempDir . '.tar';

        try {
            $this->logger->info('Iniciant descàrrega de MaxMind GeoLite2-Country...');
            $response = $this->httpClient->request('GET', $downloadUrl, [
                'timeout' => 90.0,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode === 401 || $statusCode === 403) {
                return [
                    'success' => false,
                    'message' => 'La clau de llicència de MaxMind no és vàlida o no té permís per descarregar GeoLite2-Country (HTTP ' . $statusCode . ').',
                ];
            }

            if ($statusCode !== 200) {
                return [
                    'success' => false,
                    'message' => 'Error en descarregar des de MaxMind (HTTP ' . $statusCode . ').',
                ];
            }

            $content = $response->getContent();
            file_put_contents($tempTarGz, $content);

            // Descomprimir .tar.gz a .tar amb PharData
            $p = new \PharData($tempTarGz);
            $p->decompress(); // Crea fitxer .tar

            $tar = new \PharData($tempTar);
            $extractedMmdb = null;

            @mkdir($tempDir, 0755, true);
            $tar->extractTo($tempDir);

            // Cercar el fitxer GeoLite2-Country.mmdb dins de la carpeta extreta
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tempDir));
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), self::DB_FILENAME)) {
                    $extractedMmdb = $file->getPathname();
                    break;
                }
            }

            if (!$extractedMmdb || !file_exists($extractedMmdb)) {
                return [
                    'success' => false,
                    'message' => "No s'ha trobat el fitxer " . self::DB_FILENAME . " dins de l'arxiu descarregat de MaxMind.",
                ];
            }

            $targetPath = $this->replaceDatabaseAtomically($extractedMmdb);

            $this->logger->info('Base de dades GeoIP actualitzada amb èxit a ' . $targetPath);

            return [
                'success' => true,
                'message' => 'Base de dades GeoIP (MaxMind GeoLite2-Country) descarregada i actualitzada correctament.',
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Error actualitzant base de dades GeoIP: ' . $e->getMessage(), ['exception' => $e]);

            return [
                'success' => false,
                'message' => 'Error tècnic durant la descàrrega/extracció: ' . $e->getMessage(),
            ];
        } finally {
            // Neteja de temporals
            if (file_exists($tempTarGz)) {
                @unlink($tempTarGz);
            }
            if (file_exists($tempTar)) {
                @unlink($tempTar);
            }
            if (is_dir($tempDir)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    @$todo($fileinfo->getRealPath());
                }
                @rmdir($tempDir);
            }
        }
    }

    /**
     * Importa directament un fitxer .mmdb existent.
     *
     * @return array{success: bool, message: string}
     */
    public function importDatabaseFile(string $sourcePath): array
    {
        if (!is_file($sourcePath) || !($fh = @fopen($sourcePath, 'rb'))) {
            return [
                'success' => false,
                'message' => "El fitxer origen '{$sourcePath}' no existeix o no es pot llegir.",
            ];
        }
        fclose($fh);

        $targetPath = $this->replaceDatabaseAtomically($sourcePath);

        return [
            'success' => true,
            'message' => "Fitxer GeoIP importat amb èxit a {$targetPath}.",
        ];
    }

    /**
     * Descàrrega directa des del mirall públic de GeoLite2 (mateixa estratègia que VoraData quan no hi ha clau pròpia).
     *
     * @return array{success: bool, message: string}
     */
    private function downloadFromPublicMirror(): array
    {
        $tempFile = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'geoip_mirror_' . bin2hex(random_bytes(6)) . '.mmdb';

        try {
            $this->logger->info('Descarregant GeoLite2-Country.mmdb des del mirall públic...');
            $response = $this->httpClient->request('GET', self::PUBLIC_MMDB_MIRROR_URL, [
                'timeout' => 60.0,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                return [
                    'success' => false,
                    'message' => sprintf('El mirall GeoLite2 ha retornat HTTP %d.', $statusCode),
                ];
            }

            $content = $response->getContent();
            if (strlen($content) < 100_000) {
                return [
                    'success' => false,
                    'message' => 'El fitxer GeoLite2 descarregat sembla incomplet o invàlid.',
                ];
            }

            file_put_contents($tempFile, $content);
            $this->replaceDatabaseAtomically($tempFile);
            $sizeMb = round(strlen($content) / (1024 * 1024), 2);

            return [
                'success' => true,
                'message' => sprintf('Base de dades GeoLite2 actualitzada correctament des del mirall públic (%.2f MB).', $sizeMb),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Error descarregant GeoLite2 des del mirall públic: ' . $e->getMessage(), ['exception' => $e]);

            return [
                'success' => false,
                'message' => 'No s\'ha pogut actualitzar GeoLite2 des del mirall públic: ' . $e->getMessage(),
            ];
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    private function replaceDatabaseAtomically(string $sourcePath): string
    {
        $targetPath = $this->getDatabasePath();
        $targetDir = \dirname($targetPath);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $tempFinal = $targetPath . '.tmp.' . bin2hex(random_bytes(4));
        copy($sourcePath, $tempFinal);
        rename($tempFinal, $targetPath);

        return $targetPath;
    }
}
