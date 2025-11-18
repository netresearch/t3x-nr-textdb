<?php

declare(strict_types=1);

namespace Netresearch\NrTextdb\Service;

use FFI;
use RuntimeException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * High-performance database import using Rust
 *
 * Provides 10-40x speedup over PHP ORM by using:
 * - Batch lookups instead of per-translation queries
 * - Bulk INSERT (500 rows at a time)
 * - Single transaction instead of 1000s
 * - Connection pooling
 */
class RustDbImporter
{
    private static ?FFI $ffi = null;
    private static bool $initialized = false;

    /**
     * Check if Rust DB importer is available
     */
    public static function isAvailable(): bool
    {
        if (self::$initialized) {
            return self::$ffi !== null;
        }

        self::$initialized = true;

        if (!extension_loaded('ffi')) {
            return false;
        }

        $libraryPath = self::getLibraryPath();
        if ($libraryPath === null || !file_exists($libraryPath)) {
            return false;
        }

        try {
            self::$ffi = self::createFFI($libraryPath);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Import translations using Rust bulk import
     *
     * @param array<string, array<string, string>> $translations Language => [key => value]
     * @param string $environment Environment name (e.g., 'default')
     * @param int $languageUid sys_language_uid (0 for default language)
     * @return array{total: int, inserted: int, updated: int, skipped: int, errors: int, duration_ms: int}
     */
    public function importTranslations(
        array $translations,
        string $environment = 'default',
        int $languageUid = 0
    ): array {
        $ffi = self::getFFI();
        if ($ffi === null) {
            throw new RuntimeException('Rust DB importer not available');
        }

        // Get TYPO3 database configuration
        $dbConfig = $this->getDatabaseConfig();
        error_log('[Rust] DB Config: host=' . $dbConfig['host'] . ', port=' . $dbConfig['port'] . ', database=' . $dbConfig['database'] . ', username=' . $dbConfig['username'] . ', password_length=' . strlen($dbConfig['password']));

        // Flatten translations to format: "env|component|type|placeholder|translation|lang_uid"
        $entries = [];
        foreach ($translations as $language => $items) {
            foreach ($items as $key => $value) {
                // Parse key format: "component|type|placeholder"
                $parts = explode('|', $key);
                if (count($parts) === 3) {
                    $entry = sprintf(
                        '%s|%s|%s|%s|%s|%d',
                        $environment,
                        $parts[0], // component
                        $parts[1], // type
                        $parts[2], // placeholder
                        $value,
                        $languageUid
                    );
                    $entries[] = $entry;
                    // Debug: Log first few entries
                    if (count($entries) <= 3) {
                        error_log('[Rust] Entry format: ' . $entry);
                    }
                } else {
                    error_log('[Rust] Invalid key format (expected 3 parts): ' . $key);
                }
            }
        }

        if (empty($entries)) {
            return [
                'total' => 0,
                'inserted' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
                'duration_ms' => 0,
            ];
        }

        // Convert PHP array to C array with null termination
        error_log('[Rust] Preparing ' . count($entries) . ' entries for FFI');
        $cEntries = [];
        foreach ($entries as $entry) {
            $len = strlen($entry);
            $cEntry = FFI::new("char[" . ($len + 1) . "]");
            FFI::memcpy($cEntry, $entry, $len);
            $cEntry[$len] = "\0";  // Explicit null terminator
            $cEntries[] = $cEntry;
        }

        error_log('[Rust] Allocating char*[' . count($cEntries) . '] array');
        $entriesArray = $ffi->new('char*[' . count($cEntries) . ']');
        error_log('[Rust] Populating array with ' . count($cEntries) . ' pointers');
        for ($i = 0; $i < count($cEntries); $i++) {
            // Cast char[N] to char* by getting pointer to first element
            $entriesArray[$i] = FFI::cast('char*', $cEntries[$i]);
        }
        error_log('[Rust] Calling Rust FFI with ' . count($entries) . ' entries');

        // Prepare database config (convert PHP strings to C strings with null termination)
        $config = $ffi->new('DbConfig');

        // Helper to create null-terminated C string
        $makeCString = function($str) use ($ffi) {
            $len = strlen($str);
            $cstr = FFI::new("char[" . ($len + 1) . "]", false);  // +1 for null terminator
            FFI::memcpy($cstr, $str, $len);
            $cstr[$len] = "\0";  // Explicit null terminator
            return FFI::cast('char*', $cstr);
        };

        $config->host = $makeCString($dbConfig['host']);
        $config->port = $dbConfig['port'];
        $config->database = $makeCString($dbConfig['database']);
        $config->username = $makeCString($dbConfig['username']);
        $config->password = $makeCString($dbConfig['password']);

        // Call Rust import
        $stats = $ffi->new('ImportStats');
        // Initialize stats to prevent segfault if Rust returns early
        $stats->total_processed = 0;
        $stats->inserted = 0;
        $stats->updated = 0;
        $stats->skipped = 0;
        $stats->errors = 0;
        $stats->duration_ms = 0;

        $result = $ffi->xliff_db_import(
            FFI::addr($config),
            $entriesArray,  // Already char*[], no addr() needed
            count($entries),
            FFI::addr($stats)
        );

        if ($result !== 0) {
            $errorMessages = [
                -1 => 'File not found or null pointer',
                -2 => 'Database connection error',
                -3 => 'Invalid UTF-8',
                -4 => 'Memory error',
                -5 => 'Panic in Rust code',
            ];
            $errorMsg = $errorMessages[$result] ?? 'Unknown error';
            throw new RuntimeException('Database import failed with error code: ' . $result . ' (' . $errorMsg . ')');
        }

        return [
            'total' => $stats->total_processed,
            'inserted' => $stats->inserted,
            'updated' => $stats->updated,
            'skipped' => $stats->skipped,
            'errors' => $stats->errors,
            'duration_ms' => $stats->duration_ms,
        ];
    }

    /**
     * Get TYPO3 database configuration
     */
    private function getDatabaseConfig(): array
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);

        $params = $connection->getParams();

        return [
            'host' => $params['host'] ?? 'localhost',
            'port' => $params['port'] ?? 3306,
            'database' => $params['dbname'] ?? '',
            'username' => $params['user'] ?? '',
            'password' => $params['password'] ?? '',
        ];
    }

    /**
     * Get FFI instance
     */
    private static function getFFI(): ?FFI
    {
        if (!self::isAvailable()) {
            return null;
        }
        return self::$ffi;
    }

    /**
     * Get library path based on platform
     */
    private static function getLibraryPath(): ?string
    {
        $os = PHP_OS_FAMILY;
        $arch = php_uname('m');

        $libraryMap = [
            'Linux' => [
                'x86_64' => 'linux64/libxliff_parser.so',
                'aarch64' => 'linux-arm64/libxliff_parser.so',
            ],
            'Darwin' => [
                'x86_64' => 'darwin64/libxliff_parser.dylib',
                'arm64' => 'darwin-arm64/libxliff_parser.dylib',
            ],
            'Windows' => [
                'AMD64' => 'win64/xliff_parser.dll',
                'x86_64' => 'win64/xliff_parser.dll',
            ],
        ];

        if (!isset($libraryMap[$os][$arch])) {
            return null;
        }

        $basePath = dirname(__DIR__, 2) . '/Resources/Private/Bin';
        return $basePath . '/' . $libraryMap[$os][$arch];
    }

    /**
     * Import XLIFF file directly to database (optimal pipeline)
     *
     * @param string $filePath Absolute path to XLIFF file
     * @param string $environment Environment name (e.g., 'default')
     * @param int $languageUid sys_language_uid (0 for default language)
     * @return array{total: int, inserted: int, updated: int, skipped: int, errors: int, duration_ms: int}
     */
    public function importFile(
        string $filePath,
        string $environment = 'default',
        int $languageUid = 0
    ): array {
        $ffi = self::getFFI();
        if ($ffi === null) {
            throw new RuntimeException('Rust DB importer not available');
        }

        // Get TYPO3 database configuration
        $dbConfig = $this->getDatabaseConfig();

        // Prepare database config
        $config = $ffi->new('DbConfig');

        // Helper to create null-terminated C string
        $makeCString = function($str) use ($ffi) {
            $len = strlen($str);
            $cstr = FFI::new("char[" . ($len + 1) . "]", false);
            FFI::memcpy($cstr, $str, $len);
            $cstr[$len] = "\0";
            return FFI::cast('char*', $cstr);
        };

        $config->host = $makeCString($dbConfig['host']);
        $config->port = $dbConfig['port'];
        $config->database = $makeCString($dbConfig['database']);
        $config->username = $makeCString($dbConfig['username']);
        $config->password = $makeCString($dbConfig['password']);

        $filePathCStr = $makeCString($filePath);
        $environmentCStr = $makeCString($environment);

        // Call Rust import
        $stats = $ffi->new('ImportStats');
        $stats->total_processed = 0;
        $stats->inserted = 0;
        $stats->updated = 0;
        $stats->skipped = 0;
        $stats->errors = 0;
        $stats->duration_ms = 0;

        $result = $ffi->xliff_import_file_to_db(
            $filePathCStr,
            FFI::addr($config),
            $environmentCStr,
            $languageUid,
            FFI::addr($stats)
        );

        if ($result !== 0) {
            $errorMessages = [
                -1 => 'Null pointer or panic',
                -2 => 'Database connection error or parse error',
                -3 => 'Import error or invalid UTF-8',
            ];
            $errorMsg = $errorMessages[$result] ?? 'Unknown error';
            throw new RuntimeException('XLIFF import failed with error code: ' . $result . ' (' . $errorMsg . ')');
        }

        return [
            'total' => $stats->total_processed,
            'inserted' => $stats->inserted,
            'updated' => $stats->updated,
            'skipped' => $stats->skipped,
            'errors' => $stats->errors,
            'duration_ms' => $stats->duration_ms,
        ];
    }

    /**
     * Create FFI instance
     */
    private static function createFFI(string $libraryPath): FFI
    {
        return FFI::cdef(
            '
            typedef struct {
                const char* host;
                uint16_t port;
                const char* database;
                const char* username;
                const char* password;
            } DbConfig;

            typedef struct {
                size_t total_processed;
                size_t inserted;
                size_t updated;
                size_t skipped;
                size_t errors;
                uint64_t duration_ms;
            } ImportStats;

            int xliff_db_import(
                const DbConfig* config,
                const char** translations,
                size_t count,
                ImportStats* out_stats
            );

            int xliff_import_file_to_db(
                const char* file_path,
                const DbConfig* config,
                const char* environment,
                int language_uid,
                ImportStats* out_stats
            );

            const char* xliff_db_version(void);
            ',
            $libraryPath
        );
    }
}
