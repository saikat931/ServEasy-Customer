<?php

namespace App\Repositories;

if (!class_exists('\\App\\Helpers\\DB')) {
    $dbConn = dirname(__DIR__) . '/common/db_connection.php';
    if (file_exists($dbConn)) require_once $dbConn;
}

use App\Helpers\DB;
use PDO;

class SettingRepository {
    public function __construct() {
        $this->ensureTablesExist();
    }

    private function ensureTablesExist(): void {
        try {
            $db = DB::getConnection();
            $db->exec("CREATE TABLE IF NOT EXISTS `platform_settings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `setting_key` VARCHAR(100) NOT NULL,
                `country_code` VARCHAR(10) NOT NULL DEFAULT 'IN',
                `country_name` VARCHAR(100) NOT NULL DEFAULT 'India',
                `currency_symbol` VARCHAR(10) NOT NULL DEFAULT '₹',
                `currency_code` VARCHAR(10) NOT NULL DEFAULT 'INR',
                `fee_amount` DECIMAL(10,2) NOT NULL DEFAULT 20.00,
                `fee_type` ENUM('fixed', 'percentage') NOT NULL DEFAULT 'fixed',
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `service_scope` ENUM('all', 'specific') NOT NULL DEFAULT 'all',
                `applicable_services` TEXT NULL,
                `description` VARCHAR(255) NULL,
                `updated_by` VARCHAR(100) NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_key_country` (`setting_key`, `country_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $db->exec("CREATE TABLE IF NOT EXISTS `fee_settings_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `setting_key` VARCHAR(100) NOT NULL,
                `country_code` VARCHAR(10) NOT NULL,
                `fee_type` ENUM('fixed', 'percentage') NOT NULL DEFAULT 'fixed',
                `service_scope` VARCHAR(50) NOT NULL DEFAULT 'all',
                `applicable_services` TEXT NULL,
                `old_amount` DECIMAL(10,2) NULL,
                `new_amount` DECIMAL(10,2) NOT NULL,
                `notes` TEXT NULL,
                `updated_by` VARCHAR(100) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Seed default row if not exists
            $db->exec("INSERT INTO `platform_settings` 
                (`setting_key`, `country_code`, `country_name`, `currency_symbol`, `currency_code`, `fee_amount`, `fee_type`, `is_active`, `service_scope`, `applicable_services`, `description`, `updated_by`)
                VALUES 
                ('convenience_fee', 'IN', 'India', '₹', 'INR', 20.00, 'fixed', 1, 'all', NULL, 'Standard platform convenience fee for India bookings', 'System')
                ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`");
        } catch (\Throwable $t) {
            error_log("SettingRepository init error: " . $t->getMessage());
        }
    }

    public function getSetting(string $key, string $countryCode = 'IN'): ?array {
        try {
            $stmt = DB::getConnection()->prepare("
                SELECT * FROM `platform_settings`
                WHERE `setting_key` = :key AND `country_code` = :country
                LIMIT 1
            ");
            $stmt->execute([
                'key' => $key,
                'country' => strtoupper($countryCode)
            ]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\Throwable $t) {
            error_log("Failed to get platform setting: " . $t->getMessage());
            return null;
        }
    }

    public function getConvenienceFee(string $countryCode = 'IN'): array {
        $setting = $this->getSetting('convenience_fee', $countryCode);
        if (!$setting) {
            return [
                'amount' => 20.00,
                'type' => 'fixed',
                'is_active' => true,
                'service_scope' => 'all',
                'applicable_services' => [],
                'currency_symbol' => '₹',
                'currency_code' => 'INR',
                'country_code' => 'IN',
                'country_name' => 'India'
            ];
        }

        $applicable = [];
        if (!empty($setting['applicable_services'])) {
            $decoded = json_decode($setting['applicable_services'], true);
            $applicable = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $setting['applicable_services'])));
        }

        return [
            'amount' => (float)$setting['fee_amount'],
            'type' => $setting['fee_type'] ?? 'fixed',
            'is_active' => (bool)$setting['is_active'],
            'service_scope' => $setting['service_scope'] ?? 'all',
            'applicable_services' => $applicable,
            'currency_symbol' => $setting['currency_symbol'] ?? '₹',
            'currency_code' => $setting['currency_code'] ?? 'INR',
            'country_code' => $setting['country_code'] ?? 'IN',
            'country_name' => $setting['country_name'] ?? 'India',
            'description' => $setting['description'] ?? '',
            'updated_by' => $setting['updated_by'] ?? '',
            'updated_at' => $setting['updated_at'] ?? ''
        ];
    }

    public function updateConvenienceFee(
        float $amount,
        string $feeType = 'fixed',
        int $isActive = 1,
        string $countryCode = 'IN',
        string $countryName = 'India',
        ?string $description = null,
        ?string $updatedBy = 'Admin',
        string $serviceScope = 'all',
        ?string $applicableServices = null,
        ?string $notes = null
    ): bool {
        try {
            $db = DB::getConnection();
            
            // Get old amount for audit log
            $oldSetting = $this->getSetting('convenience_fee', $countryCode);
            $oldAmount = $oldSetting ? (float)$oldSetting['fee_amount'] : null;

            $stmt = $db->prepare("
                INSERT INTO `platform_settings`
                (`setting_key`, `country_code`, `country_name`, `currency_symbol`, `currency_code`, `fee_amount`, `fee_type`, `is_active`, `service_scope`, `applicable_services`, `description`, `updated_by`)
                VALUES
                ('convenience_fee', :country_code, :country_name, '₹', 'INR', :fee_amount, :fee_type, :is_active, :service_scope, :applicable_services, :description, :updated_by)
                ON DUPLICATE KEY UPDATE
                `fee_amount` = VALUES(`fee_amount`),
                `fee_type` = VALUES(`fee_type`),
                `is_active` = VALUES(`is_active`),
                `service_scope` = VALUES(`service_scope`),
                `applicable_services` = VALUES(`applicable_services`),
                `description` = VALUES(`description`),
                `updated_by` = VALUES(`updated_by`),
                `updated_at` = CURRENT_TIMESTAMP
            ");

            $res = $stmt->execute([
                'country_code' => strtoupper($countryCode),
                'country_name' => $countryName,
                'fee_amount' => $amount,
                'fee_type' => $feeType,
                'is_active' => $isActive,
                'service_scope' => in_array($serviceScope, ['all', 'specific']) ? $serviceScope : 'all',
                'applicable_services' => $applicableServices,
                'description' => $description ?? 'Platform convenience fee for customer bookings',
                'updated_by' => $updatedBy ?: 'Admin'
            ]);

            // Insert into logs
            if ($res) {
                $logStmt = $db->prepare("
                    INSERT INTO `fee_settings_logs`
                    (`setting_key`, `country_code`, `fee_type`, `service_scope`, `applicable_services`, `old_amount`, `new_amount`, `notes`, `updated_by`)
                    VALUES
                    ('convenience_fee', :country_code, :fee_type, :service_scope, :applicable_services, :old_amount, :new_amount, :notes, :updated_by)
                ");
                $logStmt->execute([
                    'country_code' => strtoupper($countryCode),
                    'fee_type' => $feeType,
                    'service_scope' => $serviceScope,
                    'applicable_services' => $applicableServices,
                    'old_amount' => $oldAmount,
                    'new_amount' => $amount,
                    'notes' => $notes,
                    'updated_by' => $updatedBy ?: 'Admin'
                ]);
            }

            return $res;
        } catch (\Throwable $t) {
            error_log("Failed to update convenience fee: " . $t->getMessage());
            return false;
        }
    }

    public function getFeeLogs(int $limit = 50): array {
        try {
            $stmt = DB::getConnection()->prepare("
                SELECT * FROM `fee_settings_logs`
                ORDER BY `id` DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $t) {
            error_log("Failed to fetch fee logs: " . $t->getMessage());
            return [];
        }
    }

    public function getFeeLogById(int $id): ?array {
        try {
            $stmt = DB::getConnection()->prepare("
                SELECT * FROM `fee_settings_logs`
                WHERE `id` = :id
                LIMIT 1
            ");
            $stmt->execute(['id' => $id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\Throwable $t) {
            error_log("Failed to fetch fee log by id: " . $t->getMessage());
            return null;
        }
    }

    public function createFeeLog(array $data): ?int {
        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("
                INSERT INTO `fee_settings_logs`
                (`setting_key`, `country_code`, `fee_type`, `service_scope`, `applicable_services`, `old_amount`, `new_amount`, `notes`, `updated_by`)
                VALUES
                (:setting_key, :country_code, :fee_type, :service_scope, :applicable_services, :old_amount, :new_amount, :notes, :updated_by)
            ");
            $res = $stmt->execute([
                'setting_key' => $data['setting_key'] ?? 'convenience_fee',
                'country_code' => strtoupper($data['country_code'] ?? 'IN'),
                'fee_type' => in_array($data['fee_type'] ?? '', ['fixed', 'percentage']) ? $data['fee_type'] : 'fixed',
                'service_scope' => $data['service_scope'] ?? 'all',
                'applicable_services' => $data['applicable_services'] ?? null,
                'old_amount' => isset($data['old_amount']) && $data['old_amount'] !== '' ? (float)$data['old_amount'] : null,
                'new_amount' => (float)($data['new_amount'] ?? 0),
                'notes' => $data['notes'] ?? null,
                'updated_by' => $data['updated_by'] ?? 'Admin'
            ]);
            return $res ? (int)$db->lastInsertId() : null;
        } catch (\Throwable $t) {
            error_log("Failed to create fee log: " . $t->getMessage());
            return null;
        }
    }

    public function updateFeeLog(int $id, array $data): bool {
        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("
                UPDATE `fee_settings_logs`
                SET 
                    `fee_type` = :fee_type,
                    `service_scope` = :service_scope,
                    `applicable_services` = :applicable_services,
                    `old_amount` = :old_amount,
                    `new_amount` = :new_amount,
                    `notes` = :notes,
                    `updated_by` = :updated_by
                WHERE `id` = :id
            ");
            return $stmt->execute([
                'id' => $id,
                'fee_type' => in_array($data['fee_type'] ?? '', ['fixed', 'percentage']) ? $data['fee_type'] : 'fixed',
                'service_scope' => $data['service_scope'] ?? 'all',
                'applicable_services' => $data['applicable_services'] ?? null,
                'old_amount' => isset($data['old_amount']) && $data['old_amount'] !== '' ? (float)$data['old_amount'] : null,
                'new_amount' => (float)($data['new_amount'] ?? 0),
                'notes' => $data['notes'] ?? null,
                'updated_by' => $data['updated_by'] ?? 'Admin'
            ]);
        } catch (\Throwable $t) {
            error_log("Failed to update fee log: " . $t->getMessage());
            return false;
        }
    }

    public function deleteFeeLog(int $id): bool {
        try {
            $stmt = DB::getConnection()->prepare("
                DELETE FROM `fee_settings_logs`
                WHERE `id` = :id
            ");
            return $stmt->execute(['id' => $id]);
        } catch (\Throwable $t) {
            error_log("Failed to delete fee log: " . $t->getMessage());
            return false;
        }
    }
}
