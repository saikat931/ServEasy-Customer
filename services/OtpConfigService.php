<?php

namespace App\Services;

if (!class_exists('\\Database') && !class_exists('\\App\\Helpers\\DB')) {
    $dbConn = dirname(__DIR__) . '/common/db_connection.php';
    if (file_exists($dbConn)) require_once $dbConn;
}

use PDO;
use Exception;

/**
 * ServEasy - Global OTP Login Configuration Service
 * Controls whether Email OTP, Phone OTP, or both are active platform-wide,
 * and manages the global input field labels and placeholders across login systems.
 */
class OtpConfigService {

    /**
     * Retrieve active OTP login configuration with UI labels and placeholders
     *
     * @return array
     */
    public static function getConfig(): array {
        $emailEnabled = true;
        $phoneEnabled = true;
        $customLabel = null;
        $customPlaceholder = null;
        $updatedBy = 'System';
        $updatedAt = date('Y-m-d H:i:s');

        try {
            $pdo = null;
            if (class_exists('\\App\\Helpers\\DB')) {
                $pdo = \App\Helpers\DB::getConnection();
            } elseif (class_exists('\\Database')) {
                $pdo = \Database::connect();
            } else {
                $pdo = new PDO("mysql:host=127.0.0.1;dbname=serveasy_v2;charset=utf8mb4", "root", "", [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
            }

            $stmt = $pdo->query("SELECT email_otp_enabled, phone_otp_enabled, custom_label, custom_placeholder, updated_by, updated_at FROM `otp_login_config` ORDER BY id ASC LIMIT 1");
            $row = $stmt ? $stmt->fetch() : null;

            if ($row) {
                $emailEnabled = (bool)(int)$row['email_otp_enabled'];
                $phoneEnabled = (bool)(int)$row['phone_otp_enabled'];
                $customLabel = !empty($row['custom_label']) ? trim($row['custom_label']) : null;
                $customPlaceholder = !empty($row['custom_placeholder']) ? trim($row['custom_placeholder']) : null;
                $updatedBy = $row['updated_by'] ?? 'Super Admin';
                $updatedAt = $row['updated_at'] ?? $updatedAt;
            }
        } catch (\Throwable $e) {
            error_log("OtpConfigService::getConfig failed: " . $e->getMessage());
        }

        // Failsafe: if both are 0, fallback to both enabled
        if (!$emailEnabled && !$phoneEnabled) {
            $emailEnabled = true;
            $phoneEnabled = true;
        }

        $mode = 'both';
        $defaultLabel = 'Email or Phone Number';
        $defaultPlaceholder = 'Please enter your phone number or email';
        $validationMsg = 'Please enter a valid email or 10-digit mobile number.';
        $inputType = 'text';

        if ($emailEnabled && !$phoneEnabled) {
            $mode = 'email';
            $defaultLabel = 'Email Address';
            $defaultPlaceholder = 'Please enter your email';
            $validationMsg = 'Phone number login is closed. Please enter your email.';
            $inputType = 'email';
        } elseif (!$emailEnabled && $phoneEnabled) {
            $mode = 'phone';
            $defaultLabel = 'Phone Number';
            $defaultPlaceholder = 'Please enter your phone number';
            $validationMsg = 'Email login is closed. Please enter your phone number.';
            $inputType = 'tel';
        }

        $label = $customLabel ?: $defaultLabel;
        $placeholder = $customPlaceholder ?: $defaultPlaceholder;

        return [
            'email_otp_enabled' => $emailEnabled,
            'phone_otp_enabled' => $phoneEnabled,
            'mode' => $mode,
            'label' => $label,
            'placeholder' => $placeholder,
            'default_label' => $defaultLabel,
            'default_placeholder' => $defaultPlaceholder,
            'custom_label' => $customLabel,
            'custom_placeholder' => $customPlaceholder,
            'validation_message' => $validationMsg,
            'input_type' => $inputType,
            'updated_by' => $updatedBy,
            'updated_at' => $updatedAt
        ];
    }

    /**
     * Update active OTP login configuration and input field text
     *
     * @param bool $emailEnabled
     * @param bool $phoneEnabled
     * @param string|null $customLabel
     * @param string|null $customPlaceholder
     * @param string $updatedBy
     * @return array
     * @throws Exception
     */
    public static function updateConfig(bool $emailEnabled, bool $phoneEnabled, ?string $customLabel = null, ?string $customPlaceholder = null, string $updatedBy = 'Super Admin'): array {
        if (!$emailEnabled && !$phoneEnabled) {
            throw new Exception("At least one OTP login method (Email or Phone Number) must remain enabled.");
        }

        try {
            $pdo = null;
            if (class_exists('\\Database')) {
                $pdo = \Database::connect();
            } else {
                $pdo = new PDO("mysql:host=127.0.0.1;dbname=serveasy_v2;charset=utf8mb4", "root", "", [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
            }

            $stmt = $pdo->prepare("
                UPDATE `otp_login_config` 
                SET email_otp_enabled = :email_otp,
                    phone_otp_enabled = :phone_otp,
                    custom_label = :custom_label,
                    custom_placeholder = :custom_placeholder,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = 1
            ");

            $stmt->execute([
                ':email_otp' => $emailEnabled ? 1 : 0,
                ':phone_otp' => $phoneEnabled ? 1 : 0,
                ':custom_label' => $customLabel !== null ? trim($customLabel) : null,
                ':custom_placeholder' => $customPlaceholder !== null ? trim($customPlaceholder) : null,
                ':updated_by' => $updatedBy
            ]);

            return self::getConfig();
        } catch (\Throwable $e) {
            throw new Exception("Failed to update OTP configuration: " . $e->getMessage());
        }
    }

    /**
     * Retrieve SMTP configuration for Email OTP dispatch
     *
     * @return array
     */
    public static function getSmtpConfig(): array {
        $host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $port = (int)($_ENV['SMTP_PORT'] ?? 587);
        $user = $_ENV['SMTP_USER'] ?? '';
        $pass = $_ENV['SMTP_PASS'] ?? '';
        $fromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? ($user ?: '');
        $fromName = $_ENV['SMTP_FROM_NAME'] ?? 'ServEasy';
        $encryption = 'tls';

        try {
            $pdo = null;
            if (class_exists('\\App\\Helpers\\DB')) {
                $pdo = \App\Helpers\DB::getConnection();
            } elseif (class_exists('\\Database')) {
                $pdo = \Database::connect();
            } else {
                $pdo = new PDO("mysql:host=127.0.0.1;dbname=serveasy_v2;charset=utf8mb4", "root", "", [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
            }

            $stmt = $pdo->query("SELECT smtp_host, smtp_port, smtp_user, smtp_pass, smtp_from_email, smtp_from_name, smtp_encryption FROM `otp_login_config` WHERE id = 1 LIMIT 1");
            $row = $stmt ? $stmt->fetch() : null;

            if ($row) {
                if (!empty($row['smtp_host'])) $host = trim($row['smtp_host']);
                if (!empty($row['smtp_port'])) $port = (int)$row['smtp_port'];
                if (!empty($row['smtp_user'])) $user = trim($row['smtp_user']);
                if (!empty($row['smtp_pass'])) $pass = trim($row['smtp_pass']);
                if (!empty($row['smtp_from_email'])) $fromEmail = trim($row['smtp_from_email']);
                if (!empty($row['smtp_from_name'])) $fromName = trim($row['smtp_from_name']);
                if (!empty($row['smtp_encryption'])) $encryption = trim($row['smtp_encryption']);
            }
        } catch (\Throwable $e) {
            error_log("OtpConfigService::getSmtpConfig failed: " . $e->getMessage());
        }

        $isConfigured = !empty($user) && !empty($pass);

        return [
            'smtp_host' => $host,
            'smtp_port' => $port,
            'smtp_user' => $user,
            'smtp_pass' => $pass,
            'smtp_from_email' => $fromEmail ?: $user,
            'smtp_from_name' => $fromName,
            'smtp_encryption' => $encryption,
            'is_configured' => $isConfigured
        ];
    }

    /**
     * Update SMTP configuration and synchronize with backend/.env
     *
     * @param array $data
     * @param string $updatedBy
     * @return array
     * @throws Exception
     */
    public static function updateSmtpConfig(array $data, string $updatedBy = 'Super Admin'): array {
        $host = trim($data['smtp_host'] ?? 'smtp.gmail.com');
        $port = (int)($data['smtp_port'] ?? 587);
        $user = trim($data['smtp_user'] ?? '');
        $pass = trim($data['smtp_pass'] ?? '');
        $fromEmail = trim($data['smtp_from_email'] ?? ($user ?: ''));
        $fromName = trim($data['smtp_from_name'] ?? 'ServEasy');
        $encryption = trim($data['smtp_encryption'] ?? 'tls');

        if (empty($user)) {
            throw new Exception("Gmail / SMTP Username (Email) cannot be empty.");
        }
        if (empty($pass)) {
            throw new Exception("Gmail 16-character App Password cannot be empty.");
        }

        try {
            $pdo = null;
            if (class_exists('\\Database')) {
                $pdo = \Database::connect();
            } else {
                $pdo = new PDO("mysql:host=127.0.0.1;dbname=serveasy_v2;charset=utf8mb4", "root", "", [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
            }

            $stmt = $pdo->prepare("
                UPDATE `otp_login_config`
                SET smtp_host = :host,
                    smtp_port = :port,
                    smtp_user = :user,
                    smtp_pass = :pass,
                    smtp_from_email = :from_email,
                    smtp_from_name = :from_name,
                    smtp_encryption = :encryption,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = 1
            ");

            $stmt->execute([
                ':host' => $host,
                ':port' => $port,
                ':user' => $user,
                ':pass' => $pass,
                ':from_email' => $fromEmail,
                ':from_name' => $fromName,
                ':encryption' => $encryption,
                ':updated_by' => $updatedBy
            ]);

            // Sync with backend/.env file
            self::syncEnv([
                'SMTP_HOST' => $host,
                'SMTP_PORT' => (string)$port,
                'SMTP_USER' => $user,
                'SMTP_PASS' => $pass,
                'SMTP_FROM_EMAIL' => $fromEmail,
                'SMTP_FROM_NAME' => $fromName
            ]);

            return self::getSmtpConfig();
        } catch (\Throwable $e) {
            throw new Exception("Failed to update SMTP configuration: " . $e->getMessage());
        }
    }

    /**
     * Synchronize key-value pairs into backend/.env file
     */
    public static function syncEnv(array $updates): void {
        $envPath = dirname(__DIR__, 2) . '/.env';
        if (!file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);
        foreach ($updates as $k => $v) {
            if (preg_match("/^{$k}=.*$/m", $content)) {
                $content = preg_replace("/^{$k}=.*$/m", "{$k}={$v}", $content);
            } else {
                $content .= "\n{$k}={$v}";
            }
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
            putenv("{$k}={$v}");
        }
        file_put_contents($envPath, $content);
    }

    /**
     * Record an OTP dispatch or block event in otp_dispatch_logs
     */
    public static function logOtpDispatch(
        string $recipient,
        string $userType = 'customer',
        string $otpCode = '',
        string $deliveryMethod = 'email',
        string $status = 'sent',
        int $attemptNumber = 1,
        ?string $errorMessage = null,
        ?string $ipAddress = null
    ): void {
        try {
            $pdo = null;
            if (class_exists('\\Database')) {
                $pdo = \Database::connect();
            } else {
                $pdo = new PDO("mysql:host=127.0.0.1;dbname=serveasy_v2;charset=utf8mb4", "root", "", [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
            }

            $ip = $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

            $stmt = $pdo->prepare("
                INSERT INTO `otp_dispatch_logs`
                (`recipient`, `user_type`, `otp_code`, `delivery_method`, `status`, `attempt_number`, `error_message`, `ip_address`, `created_at`)
                VALUES (:recipient, :user_type, :otp_code, :delivery_method, :status, :attempt_number, :error_message, :ip, NOW())
            ");

            $stmt->execute([
                ':recipient' => $recipient,
                ':user_type' => $userType,
                ':otp_code' => $otpCode,
                ':delivery_method' => $deliveryMethod,
                ':status' => $status,
                ':attempt_number' => $attemptNumber,
                ':error_message' => $errorMessage,
                ':ip' => $ip
            ]);
        } catch (\Throwable $e) {
            error_log("OtpConfigService::logOtpDispatch error: " . $e->getMessage());
        }
    }

    /**
     * Retrieve recent OTP dispatch activity logs
     */
    public static function getOtpLogs(int $limit = 50): array {
        try {
            $pdo = null;
            if (class_exists('\\App\\Helpers\\DB')) {
                $pdo = \App\Helpers\DB::getConnection();
            } elseif (class_exists('\\Database')) {
                $pdo = \Database::connect();
            } else {
                $pdo = new PDO("mysql:host=127.0.0.1;dbname=serveasy_v2;charset=utf8mb4", "root", "", [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
            }

            $stmt = $pdo->prepare("
                SELECT id, recipient, user_type, otp_code, delivery_method, status, attempt_number, error_message, ip_address, created_at
                FROM `otp_dispatch_logs`
                ORDER BY id DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            error_log("OtpConfigService::getOtpLogs error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear all OTP dispatch activity logs
     */
    public static function clearOtpLogs(): bool {
        try {
            $pdo = null;
            if (class_exists('\\Database')) {
                $pdo = \Database::connect();
            } else {
                $pdo = new PDO("mysql:host=127.0.0.1;dbname=serveasy_v2;charset=utf8mb4", "root", "", [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
            }

            $pdo->exec("TRUNCATE TABLE `otp_dispatch_logs`");
            return true;
        } catch (\Throwable $e) {
            error_log("OtpConfigService::clearOtpLogs error: " . $e->getMessage());
            return false;
        }
    }
}

