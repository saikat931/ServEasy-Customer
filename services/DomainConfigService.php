<?php

namespace App\Services;

if (!class_exists('\\Database') && !class_exists('\\App\\Helpers\\DB')) {
    $dbConn = dirname(__DIR__) . '/common/db_connection.php';
    if (file_exists($dbConn)) require_once $dbConn;
}

use PDO;
use Exception;

/**
 * ServEasy - Dynamic Live Domain & Base URL Configuration Service
 * Allows Super Admin to dynamically configure the live domain/host and paths,
 * seamlessly synchronizing all APIs, Web frontends, Mobile endpoints, and webhooks.
 */
class DomainConfigService {

    private static ?array $cachedConfig = null;

    /**
     * Get the active domain configuration
     */
    public static function getConfig(): array {
        if (self::$cachedConfig !== null) {
            return self::$cachedConfig;
        }

        self::ensureTableExists();

        $defaultDomain = self::detectCurrentDomain();
        $defaultConfig = [
            'domain_url' => $defaultDomain,
            'api_path' => '/api',
            'web_path' => '/Frontend/Web',
            'admin_path' => '/Frontend/Admin',
            'superadmin_path' => '/Frontend/Super Admin',
            'provider_path' => '/Frontend/Service Provider',
            'environment' => 'production',
            'is_active' => 1,
            'updated_by' => 'System',
            'updated_at' => date('Y-m-d H:i:s')
        ];

        try {
            $pdo = self::getPdoConnection();
            if ($pdo) {
                $stmt = $pdo->query("SELECT * FROM `domain_config` WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
                $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                if ($row) {
                    $defaultConfig = array_merge($defaultConfig, $row);
                }
            }
        } catch (\Throwable $e) {
            error_log("DomainConfigService::getConfig error: " . $e->getMessage());
        }

        // Clean trailing slashes
        $defaultConfig['domain_url'] = rtrim(trim($defaultConfig['domain_url']), '/');
        $defaultConfig['api_url'] = $defaultConfig['domain_url'] . '/' . ltrim(trim($defaultConfig['api_path']), '/');
        $defaultConfig['api_url'] = rtrim($defaultConfig['api_url'], '/');

        self::$cachedConfig = $defaultConfig;
        return $defaultConfig;
    }

    /**
     * Get the active base domain URL (e.g. http://localhost/ServEasy)
     */
    public static function getBaseUrl(): string {
        $cfg = self::getConfig();
        return $cfg['domain_url'];
    }

    /**
     * Get the full API Base URL (e.g. http://localhost/ServEasy/backend/api)
     */
    public static function getApiUrl(): string {
        $cfg = self::getConfig();
        return $cfg['api_url'];
    }

    /**
     * Generate complete directory of all calculated endpoints
     */
    public static function getAllEndpoints(): array {
        $cfg = self::getConfig();
        $base = $cfg['domain_url'];
        $api = $cfg['api_url'];

        return [
            'base_domain' => $base,
            'api_base' => $api,
            'environment' => $cfg['environment'],
            'web_portals' => [
                'customer_web' => $base . '/' . ltrim($cfg['web_path'], '/') . '/index.php',
                'admin_dashboard' => $base . '/' . ltrim($cfg['admin_path'], '/') . '/admin-dashboard.php',
                'superadmin_dashboard' => $base . '/' . ltrim($cfg['superadmin_path'], '/') . '/superadmin-dashboard.php',
                'provider_portal' => $base . '/' . ltrim($cfg['provider_path'], '/') . '/index.php',
            ],
            'api_services' => [
                'categories' => $api . '/categories.php',
                'services' => $api . '/services.php',
                'bookings' => $api . '/bookings.php',
                'sse_events' => $api . '/sse-events.php',
                'customer_auth' => $api . '/auth-api.php',
                'dealer_auth' => $api . '/dealer-auth-api.php',
                'profile' => $api . '/profile.php',
                'user_details' => $api . '/user-details-api.php',
                'addresses' => $api . '/addresses.php',
                'create_payment' => $api . '/create-payment-order.php',
                'verify_payment' => $api . '/verify-payment.php',
                'payment_webhook' => $api . '/payment-webhook.php',
                'wallet' => $api . '/wallet.php',
                'platform_settings' => $api . '/platform-settings.php',
                'notifications' => $api . '/notifications.php',
                'reviews' => $api . '/reviews.php',
                'chats' => $api . '/chats.php',
                'provider_location' => $api . '/provider-location.php',
                'provider_availability' => $api . '/provider-availability.php',
                'admin_api' => $api . '/admin.php'
            ]
        ];
    }

    /**
     * Update the domain configuration from Super Admin
     */
    public static function updateConfig(array $params, string $updatedBy = 'Super Admin'): array {
        $domainUrl = trim($params['domain_url'] ?? '');
        $apiPath = trim($params['api_path'] ?? '/api');
        $environment = trim($params['environment'] ?? 'production');
        $syncEnv = filter_var($params['sync_env'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if (empty($domainUrl)) {
            throw new Exception("Live Domain / Base URL cannot be empty.");
        }

        // Validate URL syntax
        if (!filter_var($domainUrl, FILTER_VALIDATE_URL)) {
            // Allow without protocol by prepending https://
            if (preg_match('/^[a-zA-Z0-9\.\-]+\.[a-zA-Z]{2,}(:\d+)?(\/.*)?$/', $domainUrl)) {
                $domainUrl = 'https://' . $domainUrl;
            } else {
                throw new Exception("Invalid domain URL format. Expected e.g. https://yourdomain.com");
            }
        }

        $domainUrl = rtrim($domainUrl, '/');
        $apiPath = '/' . ltrim(rtrim($apiPath, '/'), '/');

        self::ensureTableExists();
        $pdo = self::getPdoConnection();

        if ($pdo) {
            // Deactivate previous active configs
            $pdo->exec("UPDATE `domain_config` SET is_active = 0");

            // Insert new active config
            $stmt = $pdo->prepare("
                INSERT INTO `domain_config` 
                (domain_url, api_path, environment, is_active, updated_by, updated_at)
                VALUES (:domain_url, :api_path, :environment, 1, :updated_by, NOW())
            ");
            $stmt->execute([
                ':domain_url' => $domainUrl,
                ':api_path' => $apiPath,
                ':environment' => $environment,
                ':updated_by' => $updatedBy
            ]);
        }

        // Reset cache
        self::$cachedConfig = null;

        // Optionally sync backend/.env if writable
        $envSynced = false;
        if ($syncEnv) {
            $envSynced = self::syncEnvFile($domainUrl);
        }

        $freshConfig = self::getConfig();
        $freshConfig['env_synced'] = $envSynced;
        return $freshConfig;
    }

    /**
     * Safely updates APP_URL in backend/.env file if exists & writable
     */
    public static function syncEnvFile(string $newAppUrl): bool {
        try {
            $envPath = dirname(__DIR__, 2) . '/.env';
            if (!file_exists($envPath) || !is_writable($envPath)) {
                return false;
            }

            $content = file_get_contents($envPath);
            if ($content === false) return false;

            if (preg_match('/^APP_URL=.*$/m', $content)) {
                $newContent = preg_replace('/^APP_URL=.*$/m', 'APP_URL=' . $newAppUrl, $content);
            } else {
                $newContent = $content . "\nAPP_URL=" . $newAppUrl . "\n";
            }

            return file_put_contents($envPath, $newContent) !== false;
        } catch (\Throwable $t) {
            error_log("DomainConfigService::syncEnvFile error: " . $t->getMessage());
            return false;
        }
    }

    /**
     * Auto-detect host from $_SERVER if running under web context
     */
    private static function detectCurrentDomain(): string {
        if (!empty($_ENV['APP_URL']) && $_ENV['APP_URL'] !== 'http://localhost:8000') {
            return rtrim($_ENV['APP_URL'], '/');
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return rtrim($protocol . $host, '/');
    }

    /**
     * Helper to obtain PDO connection to serveasy-admin
     */
    private static function getPdoConnection(): ?PDO {
        if (class_exists('\\App\\Helpers\\DB')) {
            return \App\Helpers\DB::getConnection();
        }
        if (class_exists('\\Database')) {
            return \Database::connect();
        }
        return null;
    }

    /**
     * Ensure domain_config table exists in serveasy-admin
     */
    private static function ensureTableExists(): void {
        try {
            $pdo = self::getPdoConnection();
            if ($pdo) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `domain_config` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `domain_url` VARCHAR(255) NOT NULL DEFAULT 'http://localhost/ServEasy',
                    `api_path` VARCHAR(100) NOT NULL DEFAULT '/api',
                    `web_path` VARCHAR(100) NOT NULL DEFAULT '/Frontend/Web',
                    `admin_path` VARCHAR(100) NOT NULL DEFAULT '/Frontend/Admin',
                    `superadmin_path` VARCHAR(100) NOT NULL DEFAULT '/Frontend/Super Admin',
                    `provider_path` VARCHAR(100) NOT NULL DEFAULT '/Frontend/Service Provider',
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `environment` VARCHAR(50) NOT NULL DEFAULT 'production',
                    `updated_by` VARCHAR(100) NULL,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
        } catch (\Throwable $e) {
            error_log("DomainConfigService::ensureTableExists error: " . $e->getMessage());
        }
    }
}

