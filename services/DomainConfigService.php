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
     * Get the complete live ecosystem directory
     */
    public static function getEcosystemDirectory(): array {
        $httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $isLocal = in_array($httpHost, ['localhost', '127.0.0.1', '::1']) || str_starts_with($httpHost, 'localhost:');

        if ($isLocal) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $localRoot = $protocol . $httpHost . '/ServEasy-v2';
            return [
                'is_local' => true,
                'environment' => 'development',
                'portals' => [
                    'customer' => [
                        'id' => 'customer',
                        'name' => 'Customer Web Portal',
                        'tag' => 'Customer App',
                        'icon' => 'fa-shopping-bag',
                        'badge_color' => '#38bdf8',
                        'domain' => $localRoot . '/frontend-web',
                        'role' => 'Public Customer Marketplace, Browse Services & Bookings',
                        'repo' => 'https://github.com/saikat931/ServEasy.git',
                        'repo_name' => 'saikat931/ServEasy',
                        'urls' => [
                            ['label' => 'Home Page', 'url' => $localRoot . '/frontend-web/index.php', 'desc' => 'Customer storefront'],
                            ['label' => 'Browse Services', 'url' => $localRoot . '/frontend-web/pages/services/index.php', 'desc' => 'Service catalog listings'],
                            ['label' => 'Customer Login', 'url' => $localRoot . '/frontend-web/login.php', 'desc' => 'Customer sign in'],
                            ['label' => 'Track Booking', 'url' => $localRoot . '/frontend-web/track-booking.php', 'desc' => 'Order status tracking'],
                        ]
                    ],
                    'provider' => [
                        'id' => 'provider',
                        'name' => 'Service Provider Portal',
                        'tag' => 'Partner App',
                        'icon' => 'fa-handshake',
                        'badge_color' => '#10b981',
                        'domain' => $localRoot . '/frontend-provider',
                        'role' => 'Dealer & Partner Order Management, Fulfillment & Earnings',
                        'repo' => 'https://github.com/saikat931/ServEasy-Provider.git',
                        'repo_name' => 'saikat931/ServEasy-Provider',
                        'urls' => [
                            ['label' => 'Provider Dashboard', 'url' => $localRoot . '/frontend-provider/index.php?tab=dashboard', 'desc' => 'Order overview & metrics'],
                            ['label' => 'My Services', 'url' => $localRoot . '/frontend-provider/index.php?tab=services', 'desc' => 'Service list & pricing'],
                            ['label' => 'My Bookings', 'url' => $localRoot . '/frontend-provider/index.php?tab=bookings', 'desc' => 'Live booking management'],
                            ['label' => 'Wallet & Payouts', 'url' => $localRoot . '/frontend-provider/index.php?tab=wallet', 'desc' => 'Earnings & withdrawal requests'],
                            ['label' => 'Provider Login', 'url' => $localRoot . '/frontend-provider/login.php', 'desc' => 'Partner authentication'],
                        ]
                    ],
                    'superadmin' => [
                        'id' => 'superadmin',
                        'name' => 'Super Admin Portal',
                        'tag' => 'Governance',
                        'icon' => 'fa-user-shield',
                        'badge_color' => '#8b5cf6',
                        'domain' => $localRoot . '/frontend-superadmin',
                        'role' => 'Master Platform Governance, Convenience Fees, Categories & Settings',
                        'repo' => 'https://github.com/saikat931/ServEasy-Superadmin.git',
                        'repo_name' => 'saikat931/ServEasy-Superadmin',
                        'urls' => [
                            ['label' => 'Superadmin Dashboard', 'url' => $localRoot . '/frontend-superadmin/superadmin-dashboard.php', 'desc' => 'Executive control center'],
                            ['label' => 'Superadmin Login', 'url' => $localRoot . '/frontend-superadmin/superadmin-login.php', 'desc' => 'Admin credential verification'],
                            ['label' => 'Category Management', 'url' => $localRoot . '/frontend-superadmin/superadmin-dashboard.php?tab=categories', 'desc' => 'Manage categories & subcategories'],
                            ['label' => 'Convenience Fee Settings', 'url' => $localRoot . '/frontend-superadmin/superadmin-dashboard.php?tab=convenience_fee', 'desc' => 'Platform charges & commissions'],
                            ['label' => 'OTP Login Settings', 'url' => $localRoot . '/frontend-superadmin/superadmin-dashboard.php?tab=otp_config', 'desc' => 'Global OTP delivery configuration'],
                            ['label' => 'SMTP Email Settings', 'url' => $localRoot . '/frontend-superadmin/superadmin-dashboard.php?tab=smtp_settings', 'desc' => 'Gmail SMTP credentials'],
                            ['label' => 'Payment Gateway Keys', 'url' => $localRoot . '/frontend-superadmin/superadmin-dashboard.php?tab=payment_gateway', 'desc' => 'Razorpay API credentials'],
                            ['label' => 'Admin Security Setup', 'url' => $localRoot . '/frontend-superadmin/admin-setup.php', 'desc' => 'Security recovery setup'],
                        ]
                    ],
                    'backend' => [
                        'id' => 'backend',
                        'name' => 'Central Backend & REST APIs',
                        'tag' => 'Central API Hub',
                        'icon' => 'fa-server',
                        'badge_color' => '#f59e0b',
                        'domain' => $localRoot . '/backend',
                        'role' => 'Central REST API Hub, Business Logic, Database Layer & Webhook Listeners',
                        'repo' => 'https://github.com/saikat931/ServEasy-Backend.git',
                        'repo_name' => 'saikat931/ServEasy-Backend',
                        'api_base' => $localRoot . '/backend/api',
                        'urls' => [
                            ['label' => 'API Root Base', 'url' => $localRoot . '/backend/api/', 'desc' => 'REST API base path'],
                            ['label' => 'Categories API', 'url' => $localRoot . '/backend/api/categories.php', 'desc' => 'Categories & service catalog'],
                            ['label' => 'Bookings API', 'url' => $localRoot . '/backend/api/bookings.php', 'desc' => 'Order placement & status updates'],
                            ['label' => 'Services API', 'url' => $localRoot . '/backend/api/services.php', 'desc' => 'Provider offerings & rates'],
                            ['label' => 'Customer Auth API', 'url' => $localRoot . '/backend/api/auth-api.php', 'desc' => 'Customer authentication'],
                            ['label' => 'Provider Auth API', 'url' => $localRoot . '/backend/api/dealer-auth-api.php', 'desc' => 'Partner authentication'],
                            ['label' => 'Wallet & Payouts API', 'url' => $localRoot . '/backend/api/wallet.php', 'desc' => 'Partner balance ledger'],
                            ['label' => 'Platform Settings API', 'url' => $localRoot . '/backend/api/platform-settings.php', 'desc' => 'Fee & site metadata'],
                            ['label' => 'Razorpay Order Creator', 'url' => $localRoot . '/backend/api/create-payment-order.php', 'desc' => 'Razorpay order generation'],
                            ['label' => 'Razorpay Webhook', 'url' => $localRoot . '/backend/api/payment-webhook.php', 'desc' => 'Instant payment capture listener'],
                            ['label' => 'OTP Dispatch Service', 'url' => $localRoot . '/backend/api/otp-request.php', 'desc' => '6-digit OTP engine'],
                            ['label' => 'SSE Real-time Events', 'url' => $localRoot . '/backend/api/sse-events.php', 'desc' => 'Server-sent live order stream'],
                        ]
                    ]
                ],
                'database' => [
                    'host' => '127.0.0.1:3306',
                    'name' => 'serveasy_v2',
                    'user' => 'root',
                    'charset' => 'utf8mb4_unicode_ci',
                    'engine' => 'InnoDB (Local MySQL)'
                ]
            ];
        }

        // Live Production Hostinger Multi-Domain Architecture
        return [
            'is_local' => false,
            'environment' => 'production',
            'portals' => [
                'customer' => [
                    'id' => 'customer',
                    'name' => 'Customer Web Portal',
                    'tag' => 'Customer App',
                    'icon' => 'fa-shopping-bag',
                    'badge_color' => '#38bdf8',
                    'domain' => 'https://silver-penguin-156353.hostingersite.com',
                    'role' => 'Public Customer Marketplace, Browse Services & Instant Bookings',
                    'repo' => 'https://github.com/saikat931/ServEasy-Customer.git',
                    'repo_name' => 'saikat931/ServEasy-Customer',
                    'urls' => [
                        ['label' => 'Home Page', 'url' => 'https://silver-penguin-156353.hostingersite.com/', 'desc' => 'Customer storefront'],
                        ['label' => 'Browse Services', 'url' => 'https://silver-penguin-156353.hostingersite.com/pages/services/', 'desc' => 'Service catalog listings'],
                        ['label' => 'Customer Login', 'url' => 'https://silver-penguin-156353.hostingersite.com/login.php', 'desc' => 'Customer sign in'],
                        ['label' => 'Track Booking', 'url' => 'https://silver-penguin-156353.hostingersite.com/track-booking.php', 'desc' => 'Order status tracking'],
                        ['label' => 'Customer Profile', 'url' => 'https://silver-penguin-156353.hostingersite.com/profile.php', 'desc' => 'Account settings & history'],
                    ]
                ],
                'provider' => [
                    'id' => 'provider',
                    'name' => 'Service Provider Portal',
                    'tag' => 'Partner App',
                    'icon' => 'fa-handshake',
                    'badge_color' => '#10b981',
                    'domain' => 'https://darkcyan-cassowary-121832.hostingersite.com',
                    'role' => 'Partner & Provider Operations, Order Fulfillment, Earnings & ID Verification',
                    'repo' => 'https://github.com/saikat931/ServEasy-Provider.git',
                    'repo_name' => 'saikat931/ServEasy-Provider',
                    'urls' => [
                        ['label' => 'Provider Dashboard', 'url' => 'https://darkcyan-cassowary-121832.hostingersite.com/index.php?tab=dashboard', 'desc' => 'Order overview & metrics'],
                        ['label' => 'My Services', 'url' => 'https://darkcyan-cassowary-121832.hostingersite.com/index.php?tab=services', 'desc' => 'Service catalog & pricing'],
                        ['label' => 'My Bookings', 'url' => 'https://darkcyan-cassowary-121832.hostingersite.com/index.php?tab=bookings', 'desc' => 'Live booking management'],
                        ['label' => 'Wallet & Payouts', 'url' => 'https://darkcyan-cassowary-121832.hostingersite.com/index.php?tab=wallet', 'desc' => 'Earnings & withdrawal requests'],
                        ['label' => 'ID Verification', 'url' => 'https://darkcyan-cassowary-121832.hostingersite.com/index.php?tab=verification', 'desc' => 'Aadhaar / ID proof upload'],
                        ['label' => 'Provider Login', 'url' => 'https://darkcyan-cassowary-121832.hostingersite.com/login.php', 'desc' => 'Partner authentication'],
                    ]
                ],
                'superadmin' => [
                    'id' => 'superadmin',
                    'name' => 'Super Admin Portal',
                    'tag' => 'Governance',
                    'icon' => 'fa-user-shield',
                    'badge_color' => '#8b5cf6',
                    'domain' => 'https://inviting-royalblue-gerbil-79.hostingersite.com',
                    'role' => 'Master Platform Governance, Convenience Fees, Categories, Admins & Global Configurations',
                    'repo' => 'https://github.com/saikat931/ServEasy-Superadmin.git',
                    'repo_name' => 'saikat931/ServEasy-Superadmin',
                    'urls' => [
                        ['label' => 'Superadmin Dashboard', 'url' => 'https://inviting-royalblue-gerbil-79.hostingersite.com/superadmin-dashboard.php', 'desc' => 'Executive control center'],
                        ['label' => 'Superadmin Login', 'url' => 'https://inviting-royalblue-gerbil-79.hostingersite.com/superadmin-login.php', 'desc' => 'Admin credential verification'],
                        ['label' => 'Category Management', 'url' => 'https://inviting-royalblue-gerbil-79.hostingersite.com/superadmin-dashboard.php?tab=categories', 'desc' => 'Manage categories & subcategories'],
                        ['label' => 'Convenience Fee Settings', 'url' => 'https://inviting-royalblue-gerbil-79.hostingersite.com/superadmin-dashboard.php?tab=convenience_fee', 'desc' => 'Platform charges & commissions'],
                        ['label' => 'OTP Login Settings', 'url' => 'https://inviting-royalblue-gerbil-79.hostingersite.com/superadmin-dashboard.php?tab=otp_config', 'desc' => 'Global OTP delivery configuration'],
                        ['label' => 'SMTP Email Settings', 'url' => 'https://inviting-royalblue-gerbil-79.hostingersite.com/superadmin-dashboard.php?tab=smtp_settings', 'desc' => 'Gmail SMTP credentials'],
                        ['label' => 'Payment Gateway Keys', 'url' => 'https://inviting-royalblue-gerbil-79.hostingersite.com/superadmin-dashboard.php?tab=payment_gateway', 'desc' => 'Razorpay API credentials'],
                        ['label' => 'Admin Security Setup', 'url' => 'https://inviting-royalblue-gerbil-79.hostingersite.com/admin-setup.php', 'desc' => 'Security recovery setup'],
                    ]
                ],
                'backend' => [
                    'id' => 'backend',
                    'name' => 'Central Backend & REST APIs',
                    'tag' => 'Central API Hub',
                    'icon' => 'fa-server',
                    'badge_color' => '#f59e0b',
                    'domain' => 'https://beige-curlew-610555.hostingersite.com',
                    'role' => 'Central High-Throughput REST API Hub, Order Engine, Push Notifications & Webhook Listeners',
                    'repo' => 'https://github.com/saikat931/ServEasy-Backend.git',
                    'repo_name' => 'saikat931/ServEasy-Backend',
                    'api_base' => 'https://beige-curlew-610555.hostingersite.com/api',
                    'urls' => [
                        ['label' => 'API Root Base', 'url' => 'https://beige-curlew-610555.hostingersite.com/api/', 'desc' => 'REST API base path'],
                        ['label' => 'Categories API', 'url' => 'https://beige-curlew-610555.hostingersite.com/api/categories.php', 'desc' => 'Categories & service catalog'],
                        ['label' => 'Bookings API', 'url' => 'https://beige-curlew-610555.hostingersite.com/api/bookings.php', 'desc' => 'Order placement & status updates'],
                        ['label' => 'Services API', 'url' => 'https://beige-curlew-610555.hostingersite.com/api/services.php', 'desc' => 'Provider offerings & rates'],
                        ['label' => 'Customer Auth API', 'url' => 'https://beige-curlew-610555.hostingersite.com/api/auth-api.php', 'desc' => 'Customer authentication'],
                        ['label' => 'Provider Auth API', 'url' => 'https://beige-curlew-610555.hostingersite.com/api/dealer-auth-api.php', 'desc' => 'Partner authentication'],
                        ['label' => 'Wallet & Payouts API', 'url' => 'https://beige-curlew-610555.hostingersite.com/api/wallet.php', 'desc' => 'Partner balance ledger'],
                        ['label' => 'Platform Settings API', 'url' => 'https://beige-curlew-610555.hostingersite.com/api/platform-settings.php', 'desc' => 'Fee & site metadata'],
                        ['label' => 'Razorpay Order Creator', 'url' => 'https://beige-curlew-610555.hostingersite.com/api/create-payment-order.php', 'desc' => 'Razorpay order generation'],
                        ['label' => 'Razorpay Webhook', 'url' => 'https://beige-curlew-610555.hostingersite.com/api/payment-webhook.php', 'desc' => 'Instant payment capture listener'],
                        ['label' => 'OTP Dispatch Service', 'url' => 'https://beige-curlew-610555.hostingersite.com/api/otp-request.php', 'desc' => '6-digit OTP engine'],
                        ['label' => 'SSE Real-time Events', 'url' => 'https://beige-curlew-610555.hostingersite.com/api/sse-events.php', 'desc' => 'Server-sent live order stream'],
                    ]
                ]
            ],
            'database' => [
                'host' => 'localhost:3306 (Internal Hostinger Cloud)',
                'name' => 'u923365340_serveasy',
                'user' => 'u923365340_serveasy',
                'charset' => 'utf8mb4_unicode_ci',
                'engine' => 'InnoDB (MySQL on Hostinger)'
            ]
        ];
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

