<?php

namespace App\Helpers;

$phpMailerDir = file_exists(__DIR__ . '/PHPMailer-master/src/PHPMailer.php')
    ? __DIR__ . '/PHPMailer-master/src'
    : (file_exists(dirname(__DIR__, 2) . '/backend/app/PHPMailer-master/src/PHPMailer.php') ? dirname(__DIR__, 2) . '/backend/app/PHPMailer-master/src' : null);
if ($phpMailerDir) {
    require_once $phpMailerDir . '/Exception.php';
    require_once $phpMailerDir . '/PHPMailer.php';
    require_once $phpMailerDir . '/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * ServEasy MailHelper
 * Handles automated transactional email dispatch via free SMTP (e.g. Gmail SMTP)
 * using the bundled PHPMailer library.
 */
class MailHelper {

    /**
     * Send a 6-digit login verification OTP to a recipient email.
     *
     * @param string $toEmail Recipient email address
     * @param string $otp 6-digit numeric verification code
     * @param string $recipientName Optional recipient display name
     * @return array Result array ['success' => bool, 'message' => string, 'unconfigured' => bool]
     */
    public static function sendOtp(string $toEmail, string $otp, string $recipientName = 'User', string $userType = 'customer'): array {
        // Load configuration
        $configPath = dirname(__DIR__, 2) . '/config/app.php';
        $config = file_exists($configPath) ? require $configPath : [];
        $mailConfig = $config['mail'] ?? [];
        $isDebug = (bool)($config['debug'] ?? true);

        // Check database-stored SMTP settings from Super Admin first, then fallback to .env
        $dbSmtp = [];
        if (class_exists('\\App\\Services\\OtpConfigService')) {
            $dbSmtp = \App\Services\OtpConfigService::getSmtpConfig();
        }

        $host = !empty($dbSmtp['smtp_host']) ? $dbSmtp['smtp_host'] : ($mailConfig['host'] ?? ($_ENV['SMTP_HOST'] ?? 'smtp.gmail.com'));
        $port = !empty($dbSmtp['smtp_port']) ? (int)$dbSmtp['smtp_port'] : ((int)($mailConfig['port'] ?? ($_ENV['SMTP_PORT'] ?? 587)));
        $username = !empty($dbSmtp['smtp_user']) ? $dbSmtp['smtp_user'] : trim($mailConfig['username'] ?? ($_ENV['SMTP_USER'] ?? ''));
        $password = !empty($dbSmtp['smtp_pass']) ? $dbSmtp['smtp_pass'] : trim($mailConfig['password'] ?? ($_ENV['SMTP_PASS'] ?? ''));
        $fromEmail = !empty($dbSmtp['smtp_from_email']) ? $dbSmtp['smtp_from_email'] : trim($mailConfig['from_address'] ?? ($_ENV['SMTP_FROM_EMAIL'] ?? ''));
        $fromName = !empty($dbSmtp['smtp_from_name']) ? $dbSmtp['smtp_from_name'] : trim($mailConfig['from_name'] ?? ($_ENV['SMTP_FROM_NAME'] ?? 'ServEasy'));

        if (empty($fromEmail)) {
            $fromEmail = $username;
        }

        // If SMTP credentials have not been configured in Super Admin or backend/.env yet
        if (empty($username) || empty($password)) {
            error_log("[ServEasy MailHelper] SMTP credentials not configured in Super Admin or backend/.env (SMTP_USER / SMTP_PASS).");
            if (class_exists('\\App\\Services\\OtpConfigService')) {
                \App\Services\OtpConfigService::logOtpDispatch($toEmail, $userType, $otp, 'email', 'failed', 1, 'SMTP credentials not configured in Super Admin / backend/.env');
            }
            return [
                'success' => false,
                'unconfigured' => true,
                'message' => 'Email service is not yet configured in Super Admin dashboard or backend/.env.',
                'otp' => $isDebug ? $otp : null
            ];
        }

        $mail = new PHPMailer(true);

        try {
            // Server configuration
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $username;
            $mail->Password   = $password;
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 10;
            $mail->XMailer    = ' ';

            // Prevent local Windows computer hostname (e.g. HACKER) from leaking into Message-ID or HELO
            $msgDomain = (stripos($host, 'gmail') !== false) ? 'mail.gmail.com' : (stripos($host, 'brevo') !== false ? 'smtp-relay.brevo.com' : 'com');
            $randomId  = bin2hex(random_bytes(16));
            $mail->MessageID = "<{$randomId}@{$msgDomain}>";
            $mail->Helo = $host;

            if ($port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->Port       = $port;

            // Personalize greeting for new users so spam filters don't flag generic 'Hello User'
            if (empty($recipientName) || strtolower(trim($recipientName)) === 'user') {
                $emailPrefix = explode('@', $toEmail)[0];
                $cleanName = preg_replace('/[^a-zA-Z0-9]/', ' ', $emailPrefix);
                $recipientName = ucwords(trim($cleanName)) ?: 'Customer';
            }

            // Sender and Recipient
            $mail->setFrom($fromEmail, $fromName);
            $mail->Sender = $fromEmail;
            $mail->addReplyTo($fromEmail, $fromName);
            $mail->addAddress($toEmail, $recipientName);

            // Dynamic subject with verification code
            $safeName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
            $safeOtp  = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

            $mail->isHTML(true);
            $mail->Subject = "{$safeOtp} is your ServEasy verification code";

            // Clean, high-deliverability email template with balanced text-to-HTML ratio and 0 spam keywords
            $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>{$safeOtp} is your ServEasy verification code</title>
            </head>
            <body style='margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color: #1e293b;'>
                <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f8fafc; padding: 32px 16px;'>
                    <tr>
                        <td align='center'>
                            <table width='100%' max-width='500px' cellpadding='0' cellspacing='0' style='max-width: 500px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;'>
                                <!-- Header -->
                                <tr>
                                    <td style='background-color: #0f172a; padding: 22px 24px; text-align: left;'>
                                        <span style='font-size: 20px; color: #ffffff; font-weight: 700; letter-spacing: 0.5px;'>ServEasy</span>
                                    </td>
                                </tr>
                                <!-- Content Body -->
                                <tr>
                                    <td style='padding: 28px 24px; text-align: left;'>
                                        <p style='margin: 0 0 16px; font-size: 15px; line-height: 1.5; color: #334155;'>
                                            Hello {$safeName},
                                        </p>
                                        <p style='margin: 0 0 20px; font-size: 14px; line-height: 1.5; color: #475569;'>
                                            Use this verification code to complete your login:
                                        </p>
                                        
                                        <!-- OTP Display Box -->
                                        <div style='background-color: #f1f5f9; border-radius: 8px; padding: 14px 20px; margin: 0 0 20px 0; text-align: center; border: 1px solid #cbd5e1;'>
                                            <span style='font-size: 30px; font-weight: 700; letter-spacing: 6px; color: #0f172a; font-family: monospace;'>{$safeOtp}</span>
                                        </div>

                                        <p style='margin: 0 0 10px; font-size: 13px; color: #64748b; line-height: 1.4;'>
                                            This code will expire in <strong>3 minutes</strong>.
                                        </p>
                                        <p style='margin: 0; font-size: 12px; color: #94a3b8; line-height: 1.4;'>
                                            If you did not request this login code, you can safely ignore this email.
                                        </p>
                                    </td>
                                </tr>
                                <!-- Footer -->
                                <tr>
                                    <td style='background-color: #f8fafc; padding: 16px 24px; text-align: left; border-top: 1px solid #f1f5f9; font-size: 11px; color: #94a3b8;'>
                                        &copy; " . date('Y') . "  All rights reserved.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            ";

            $mail->AltBody = "Hello {$recipientName},\n\nYour ServEasy verification code is: {$otp}\n\nThis code will expire in 3 minutes.\n\nIf you did not request this code, you can safely ignore this email.\n\nRegards,\nServEasy Team";

            $mail->send();

            if (class_exists('\\App\\Services\\OtpConfigService')) {
                \App\Services\OtpConfigService::logOtpDispatch($toEmail, $userType, $otp, 'email', 'sent', 1);
            }

            return [
                'success' => true,
                'unconfigured' => false,
                'message' => 'Verification code sent to ' . $toEmail
            ];
        } catch (Exception $e) {
            $err = $mail->ErrorInfo ?: $e->getMessage();
            error_log("[ServEasy MailHelper] Email dispatch failed to {$toEmail}: " . $err);
            if (class_exists('\\App\\Services\\OtpConfigService')) {
                \App\Services\OtpConfigService::logOtpDispatch($toEmail, $userType, $otp, 'email', 'failed', 1, $err);
            }
            return [
                'success' => false,
                'unconfigured' => false,
                'message' => 'Failed to send verification email: ' . $err
            ];
        }
    }
}
