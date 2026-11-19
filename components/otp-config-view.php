<?php
/**
 * ServEasy - Super Admin Global OTP Login Configuration Component
 * Restricted strictly to Super Administrator
 * Controls Email OTP vs Phone OTP availability and dynamic input field text/placeholders site-wide
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo)) {
    $dbConfigPath = file_exists(__DIR__ . '/../common/db_connection.php')
        ? __DIR__ . '/../common/db_connection.php'
        : (file_exists(__DIR__ . '/../../../backend/config/db_connection.php') ? __DIR__ . '/../../../backend/config/db_connection.php' : null);
    if ($dbConfigPath) {
        require_once $dbConfigPath;
        $pdo = Database::connect();
    }
}

$is_super = false;
if (isset($is_super_admin) && $is_super_admin) {
    $is_super = true;
} elseif (isset($_SESSION['super_admin']) || isset($_SESSION['superadmin'])) {
    $is_super = true;
} elseif (isset($_SESSION['admin_role']) && in_array(strtolower($_SESSION['admin_role']), ['super_admin', 'superadmin', 'master_admin'])) {
    $is_super = true;
} elseif (isset($_SESSION['standard_admin']['role']) && in_array(strtolower($_SESSION['standard_admin']['role']), ['super_admin', 'superadmin'])) {
    $is_super = true;
}

if (!$is_super) {
    if (isset($_POST['otp_action'])) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Access Denied: Global OTP Login Configuration is strictly restricted to Super Administrators.']);
        exit();
    }
    echo '<div class="card shadow-sm border-0 m-4"><div class="card-body text-center py-5 text-danger font-weight-bold"><i class="fas fa-lock fa-3x mb-3 d-block"></i>Access Denied: Global OTP Login Configuration is strictly restricted to Super Administrators.</div></div>';
    exit();
}

require_once __DIR__ . '/../../../backend/app/services/OtpConfigService.php';
use App\Services\OtpConfigService;

// Handle AJAX toggle/save action
if (isset($_POST['otp_action'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['otp_action'];

    if ($action === 'update_otp_settings' || $action === 'save_input_text') {
        $emailEnabled = filter_var($_POST['email_otp_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $phoneEnabled = filter_var($_POST['phone_otp_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $customLabel = isset($_POST['custom_label']) ? trim($_POST['custom_label']) : null;
        $customPlaceholder = isset($_POST['custom_placeholder']) ? trim($_POST['custom_placeholder']) : null;
        $adminName = $_SESSION['admin_username'] ?? $_SESSION['admin_name'] ?? 'Super Admin';

        if (!$emailEnabled && !$phoneEnabled) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Safety Rule: At least one OTP login method (Email or Phone Number) must remain enabled to prevent locking out all users.'
            ]);
            exit();
        }

        try {
            $updatedConfig = OtpConfigService::updateConfig($emailEnabled, $phoneEnabled, $customLabel, $customPlaceholder, $adminName);
            echo json_encode([
                'status' => 'success',
                'message' => 'Login input field text and OTP configuration saved successfully!',
                'data' => $updatedConfig
            ]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit();
        }
    }

    if ($action === 'save_smtp_settings') {
        $host = trim($_POST['smtp_host'] ?? 'smtp.gmail.com');
        $port = (int)($_POST['smtp_port'] ?? 587);
        $user = trim($_POST['smtp_user'] ?? '');
        $pass = trim($_POST['smtp_pass'] ?? '');
        $fromEmail = trim($_POST['smtp_from_email'] ?? $user);
        $fromName = trim($_POST['smtp_from_name'] ?? 'ServEasy');
        $encryption = trim($_POST['smtp_encryption'] ?? 'tls');
        $adminName = $_SESSION['admin_username'] ?? $_SESSION['admin_name'] ?? 'Super Admin';

        try {
            $updatedSmtp = OtpConfigService::updateSmtpConfig([
                'smtp_host' => $host,
                'smtp_port' => $port,
                'smtp_user' => $user,
                'smtp_pass' => $pass,
                'smtp_from_email' => $fromEmail,
                'smtp_from_name' => $fromName,
                'smtp_encryption' => $encryption
            ], $adminName);

            echo json_encode([
                'status' => 'success',
                'message' => 'Gmail SMTP settings saved successfully and synchronized to backend/.env!',
                'data' => $updatedSmtp
            ]);
            exit();
        } catch (\Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit();
        }
    }

    if ($action === 'send_test_email') {
        $testEmail = trim($_POST['test_email'] ?? '');
        if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Please provide a valid recipient email address for testing.']);
            exit();
        }

        require_once __DIR__ . '/../../../backend/app/helpers/MailHelper.php';
        $testOtp = (string)rand(100000, 999999);
        $res = \App\Helpers\MailHelper::sendOtp($testEmail, $testOtp, 'Super Admin Tester', 'test');

        if ($res['success']) {
            echo json_encode([
                'status' => 'success',
                'message' => "Live test email sent successfully to {$testEmail}! Check your inbox for OTP: {$testOtp}",
                'otp' => $testOtp
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Email test failed: ' . ($res['message'] ?? 'Could not deliver email.')
            ]);
        }
        exit();
    }

    if ($action === 'fetch_otp_logs') {
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
        $logs = OtpConfigService::getOtpLogs($limit);
        echo json_encode([
            'status' => 'success',
            'logs' => $logs
        ]);
        exit();
    }

    if ($action === 'clear_otp_logs') {
        $ok = OtpConfigService::clearOtpLogs();
        echo json_encode([
            'status' => $ok ? 'success' : 'error',
            'message' => $ok ? 'OTP dispatch logs cleared successfully.' : 'Failed to clear logs.'
        ]);
        exit();
    }
}

$currentConfig = OtpConfigService::getConfig();
$smtpConfig = OtpConfigService::getSmtpConfig();
$recentLogs = OtpConfigService::getOtpLogs(30);
$emailOn = !empty($currentConfig['email_otp_enabled']);
$phoneOn = !empty($currentConfig['phone_otp_enabled']);
?>

<div class="container-fluid py-3" id="otpConfigContainer">
    <!-- Breadcrumb & Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="font-weight-bold text-gray-800 mb-1">
                <i class="fas fa-shield-alt text-success mr-2"></i>Global OTP Login &amp; Input Text Configuration
            </h4>
            <p class="text-muted small mb-0">Decide which OTP login method works site-wide and configure the exact input field text and placeholder shown on all login forms.</p>
        </div>
        <div>
            <span class="badge badge-success px-3 py-2 font-weight-bold" style="font-size: 0.82rem;">
                <i class="fas fa-crown mr-1"></i> Super Admin Exclusive
            </span>
        </div>
    </div>

    <!-- Alert Banner -->
    <div id="otpStatusAlert" style="display: none;" class="alert alert-dismissible fade show mb-4" role="alert">
        <span id="otpStatusAlertMsg"></span>
        <button type="button" class="close" onclick="$('#otpStatusAlert').fadeOut();">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <!-- Main Grid: Toggles & Input Field Text Set -->
    <div class="row">
        <!-- Left Column: Two Main Control Buttons -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between border-bottom">
                    <h6 class="m-0 font-weight-bold text-gray-800">
                        <i class="fas fa-sliders-h mr-2 text-primary"></i>1. Select Active OTP Channels
                    </h6>
                    <span class="badge <?= ($emailOn && $phoneOn) ? 'badge-info' : 'badge-warning' ?> font-weight-bold" id="currentModeBadge">
                        Mode: <?= strtoupper($currentConfig['mode']) ?>
                    </span>
                </div>
                <div class="card-body p-4">
                    <p class="text-secondary small mb-4">
                        Click either button below to enable or disable that channel. When you close a channel, all references to it are <strong>automatically removed</strong> from the input field placeholder on the right and on the live site.
                    </p>

                    <div class="row">
                        <!-- Button / Card 1: Phone Number OTP -->
                        <div class="col-md-6 mb-3">
                            <div class="card border <?= $phoneOn ? 'border-success' : 'border-secondary' ?> h-100 shadow-sm" id="cardPhoneOtp" style="border-width: 2px !important; border-radius: 12px; transition: all 0.25s ease;">
                                <div class="card-body text-center p-3 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 55px; height: 55px; background: <?= $phoneOn ? '#e8f5e9' : '#f5f5f5' ?>; color: <?= $phoneOn ? '#2e7d32' : '#757575' ?>;" id="iconBoxPhone">
                                            <i class="fas fa-mobile-alt fa-2x"></i>
                                        </div>
                                        <h6 class="font-weight-bold text-gray-900 mb-1">Phone Number OTP</h6>
                                        <div class="mb-2">
                                            <span class="badge <?= $phoneOn ? 'badge-success' : 'badge-secondary' ?> px-2 py-1" id="badgePhoneStatus">
                                                <?= $phoneOn ? '● Active' : '○ Closed' ?>
                                            </span>
                                        </div>
                                        <p class="small text-muted mb-3" style="font-size: 0.8rem; min-height: 40px;">
                                            Allows 10-digit phone number SMS OTP login.
                                        </p>
                                    </div>
                                    <button type="button" class="btn <?= $phoneOn ? 'btn-outline-danger' : 'btn-success' ?> btn-block font-weight-bold py-2 shadow-sm" id="btnTogglePhone" onclick="toggleOtpMethod('phone')">
                                        <i class="fas <?= $phoneOn ? 'fa-ban' : 'fa-check' ?> mr-1"></i>
                                        <span><?= $phoneOn ? 'Close Phone OTP' : 'Open Phone OTP' ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Button / Card 2: Email Address OTP -->
                        <div class="col-md-6 mb-3">
                            <div class="card border <?= $emailOn ? 'border-success' : 'border-secondary' ?> h-100 shadow-sm" id="cardEmailOtp" style="border-width: 2px !important; border-radius: 12px; transition: all 0.25s ease;">
                                <div class="card-body text-center p-3 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 55px; height: 55px; background: <?= $emailOn ? '#e8f5e9' : '#f5f5f5' ?>; color: <?= $emailOn ? '#2e7d32' : '#757575' ?>;" id="iconBoxEmail">
                                            <i class="fas fa-envelope fa-2x"></i>
                                        </div>
                                        <h6 class="font-weight-bold text-gray-900 mb-1">Email Address OTP</h6>
                                        <div class="mb-2">
                                            <span class="badge <?= $emailOn ? 'badge-success' : 'badge-secondary' ?> px-2 py-1" id="badgeEmailStatus">
                                                <?= $emailOn ? '● Active' : '○ Closed' ?>
                                            </span>
                                        </div>
                                        <p class="small text-muted mb-3" style="font-size: 0.8rem; min-height: 40px;">
                                            Allows email verification code login.
                                        </p>
                                    </div>
                                    <button type="button" class="btn <?= $emailOn ? 'btn-outline-danger' : 'btn-success' ?> btn-block font-weight-bold py-2 shadow-sm" id="btnToggleEmail" onclick="toggleOtpMethod('email')">
                                        <i class="fas <?= $emailOn ? 'fa-ban' : 'fa-check' ?> mr-1"></i>
                                        <span><?= $emailOn ? 'Close Email OTP' : 'Open Email OTP' ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Failsafe Note -->
                    <div class="alert alert-light border small text-muted mb-0 mt-2">
                        <i class="fas fa-info-circle text-primary mr-1"></i>
                        <strong>Rule:</strong> At least one method (Email or Phone) must remain active so users can always log in.
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Input Field Text Set -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-gray-800">
                        <i class="fas fa-edit mr-2 text-success"></i>2. Input Field Text &amp; Placeholder Set
                    </h6>
                    <span class="badge badge-success px-2 py-1 font-weight-bold" style="font-size: 0.72rem;">Live Input Config</span>
                </div>
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <p class="small text-muted mb-3">
                            Set the exact text for the input field and placeholder across the whole site. Toggling the buttons on the left auto-updates these fields, or you can edit them directly and click <strong>Save &amp; Apply Input Field Text</strong>.
                        </p>

                        <!-- Input Field Text Form -->
                        <div class="p-3 bg-light rounded border mb-3">
                            <!-- Field Label Setting -->
                            <div class="form-group mb-3">
                                <label class="font-weight-bold small text-gray-800 mb-1" for="inputFieldLabel">
                                    <i class="fas fa-heading text-secondary mr-1"></i>Input Field Label
                                </label>
                                <input type="text" class="form-control" id="inputFieldLabel" value="<?= htmlspecialchars($currentConfig['label']) ?>" placeholder="e.g. Email or Phone Number" oninput="updateLivePreview()">
                            </div>

                            <!-- Field Placeholder Setting -->
                            <div class="form-group mb-3">
                                <label class="font-weight-bold small text-gray-800 mb-1" for="inputFieldPlaceholder">
                                    <i class="fas fa-i-cursor text-secondary mr-1"></i>Input Field Placeholder Text
                                </label>
                                <input type="text" class="form-control" id="inputFieldPlaceholder" value="<?= htmlspecialchars($currentConfig['placeholder']) ?>" placeholder="e.g. Please enter your email" oninput="updateLivePreview()">
                                <small class="text-muted">
                                    <i class="fas fa-magic text-info mr-1"></i>Automatically changes when you toggle Phone / Email on the left.
                                </small>
                            </div>

                            <!-- Live Visual Preview of the Input Box -->
                            <div class="border rounded p-3 bg-white mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="font-weight-bold text-dark small" id="previewLabelText"><?= htmlspecialchars($currentConfig['label']) ?></span>
                                    <span class="badge badge-light border text-muted" style="font-size: 0.7rem;">Live Preview</span>
                                </div>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light text-muted border-right-0" id="previewIcon">
                                            <i class="fas <?= $phoneOn && !$emailOn ? 'fa-phone' : ($emailOn && !$phoneOn ? 'fa-envelope' : 'fa-user') ?>"></i>
                                        </span>
                                    </div>
                                    <input type="text" class="form-control border-left-0" id="previewPlaceholderInput" placeholder="<?= htmlspecialchars($currentConfig['placeholder']) ?>" readonly style="background-color: #fafafa; cursor: default;">
                                </div>
                                <small class="text-success d-block mt-1 font-weight-bold" style="font-size: 0.75rem;">
                                    <i class="fas fa-check-circle mr-1"></i>Active on Customer Login &amp; Provider Login
                                </small>
                            </div>
                        </div>

                        <!-- Action Button: Set / Save Input Field Text -->
                        <button type="button" class="btn btn-success btn-block font-weight-bold py-2 shadow-sm" id="btnSaveFieldText" onclick="saveInputTextConfig()">
                            <i class="fas fa-save mr-2"></i>Save &amp; Apply Input Field Text
                        </button>
                    </div>

                    <!-- Metadata -->
                    <div class="border-top pt-3 mt-3">
                        <div class="d-flex justify-content-between small text-muted">
                            <span>Last Updated By:</span>
                            <span class="font-weight-bold text-dark" id="txtUpdatedBy"><?= htmlspecialchars($currentConfig['updated_by']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mt-1">
                            <span>Last Changed:</span>
                            <span class="font-weight-bold text-dark" id="txtUpdatedAt"><?= htmlspecialchars($currentConfig['updated_at']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Gmail SMTP Credentials Setup & Live Test -->
    <div class="row mt-2">
        <!-- SMTP Form -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap">
                    <h6 class="m-0 font-weight-bold text-gray-800">
                        <i class="fab fa-google text-danger mr-2"></i>3. Gmail SMTP Setup for Email OTP
                    </h6>
                    <div class="mt-2 mt-md-0 d-flex align-items-center">
                        <span id="smtpStatusBadge" class="badge <?= $smtpConfig['is_configured'] ? 'badge-success' : 'badge-warning' ?> px-3 py-2 font-weight-bold mr-2">
                            <?= $smtpConfig['is_configured'] ? '<i class="fas fa-check-circle mr-1"></i>Active &amp; Configured' : '<i class="fas fa-exclamation-triangle mr-1"></i>Credentials Needed' ?>
                        </span>
                        <a href="#" onclick="loadSection('smtp_settings');return false;" class="btn btn-sm btn-outline-primary font-weight-bold">
                            <i class="fas fa-external-link-alt mr-1"></i>Dedicated SMTP Page
                        </a>
                    </div>
                </div>
                <div class="card-body p-4">
                    <p class="small text-muted mb-4">
                        Configure the exact Gmail SMTP credentials used to deliver one-time verification passwords (OTPs) across the entire platform. Saving here automatically updates both the database and the system <code>backend/.env</code> configuration.
                    </p>

                    <form id="smtpConfigForm" onsubmit="event.preventDefault(); saveSmtpConfig();">
                        <div class="row">
                            <!-- SMTP Host -->
                            <div class="col-md-8 mb-3">
                                <label class="font-weight-bold small text-gray-800" for="smtpHost">
                                    <i class="fas fa-server text-secondary mr-1"></i>SMTP Host (SMTP_HOST)
                                </label>
                                <input type="text" class="form-control" id="smtpHost" name="smtp_host" value="<?= htmlspecialchars($smtpConfig['smtp_host']) ?>" placeholder="smtp.gmail.com" required>
                                <small class="text-muted">Default for Gmail: <code>smtp.gmail.com</code></small>
                            </div>
                            <!-- SMTP Port -->
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold small text-gray-800" for="smtpPort">
                                    <i class="fas fa-network-wired text-secondary mr-1"></i>SMTP Port (SMTP_PORT)
                                </label>
                                <input type="number" class="form-control" id="smtpPort" name="smtp_port" value="<?= htmlspecialchars((string)$smtpConfig['smtp_port']) ?>" placeholder="587" required>
                                <small class="text-muted">Port <code>587</code> (STARTTLS) or <code>465</code> (SSL)</small>
                            </div>
                        </div>

                        <div class="row">
                            <!-- SMTP User / Gmail Address -->
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-gray-800" for="smtpUser">
                                    <i class="fas fa-envelope text-primary mr-1"></i>Gmail Address (SMTP_USER)
                                </label>
                                <input type="email" class="form-control" id="smtpUser" name="smtp_user" value="<?= htmlspecialchars($smtpConfig['smtp_user']) ?>" placeholder="yourname@gmail.com" required>
                                <small class="text-muted">Your Google account email address.</small>
                            </div>

                            <!-- SMTP Pass / 16-Letter App Password -->
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-gray-800" for="smtpPass">
                                    <i class="fas fa-key text-warning mr-1"></i>16-Character App Password (SMTP_PASS)
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="smtpPass" name="smtp_pass" value="<?= htmlspecialchars($smtpConfig['smtp_pass']) ?>" placeholder="xxxx xxxx xxxx xxxx" required>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" onclick="toggleSmtpPasswordVisibility()" title="Show/Hide Password">
                                            <i class="fas fa-eye" id="togglePassIcon"></i>
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted">Generated from Google App Passwords.</small>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Sender From Email -->
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-gray-800" for="smtpFromEmail">
                                    <i class="fas fa-at text-secondary mr-1"></i>From Email Address (SMTP_FROM_EMAIL)
                                </label>
                                <input type="email" class="form-control" id="smtpFromEmail" name="smtp_from_email" value="<?= htmlspecialchars($smtpConfig['smtp_from_email']) ?>" placeholder="yourname@gmail.com">
                                <small class="text-muted">Leave empty to use Gmail Address above.</small>
                            </div>

                            <!-- Sender From Name -->
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-gray-800" for="smtpFromName">
                                    <i class="fas fa-signature text-secondary mr-1"></i>From Sender Name (SMTP_FROM_NAME)
                                </label>
                                <input type="text" class="form-control" id="smtpFromName" name="smtp_from_name" value="<?= htmlspecialchars($smtpConfig['smtp_from_name']) ?>" placeholder="ServEasy">
                                <small class="text-muted">Display name seen by recipient (e.g. <code>ServEasy</code>).</small>
                            </div>
                        </div>

                        <!-- Guide Alert Box -->
                        <div class="alert alert-light border border-info rounded p-3 mb-4">
                            <h6 class="font-weight-bold text-info mb-2">
                                <i class="fas fa-question-circle mr-1"></i>How to get your free 16-character Google App Password:
                            </h6>
                            <ol class="small text-muted mb-0 pl-3">
                                <li>Make sure <strong>2-Step Verification</strong> is ON at <a href="https://myaccount.google.com/security" target="_blank" class="font-weight-bold">myaccount.google.com/security <i class="fas fa-external-link-alt fa-xs"></i></a>.</li>
                                <li>Visit <a href="https://myaccount.google.com/apppasswords" target="_blank" class="font-weight-bold text-primary">myaccount.google.com/apppasswords <i class="fas fa-external-link-alt fa-xs"></i></a>.</li>
                                <li>Name the app <code>ServEasy</code>, copy the 16-letter password Google gives you, and paste it in the <strong>SMTP_PASS</strong> box above.</li>
                            </ol>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary font-weight-bold py-2 px-4 shadow-sm" id="btnSaveSmtp">
                            <i class="fas fa-save mr-2"></i>Save &amp; Apply SMTP Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Live SMTP Connection Test Box -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-gray-800">
                        <i class="fas fa-paper-plane text-success mr-2"></i>Test Live Email Dispatch
                    </h6>
                    <span class="badge badge-light border text-muted">Diagnostic</span>
                </div>
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <p class="small text-muted mb-3">
                            Send a real live verification OTP to your personal inbox right now to confirm the server connection works.
                        </p>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small text-gray-800 mb-1" for="testEmailInput">
                                <i class="fas fa-user-check text-success mr-1"></i>Recipient Test Email
                            </label>
                            <input type="email" class="form-control" id="testEmailInput" placeholder="Enter your email to receive test OTP" value="<?= htmlspecialchars($smtpConfig['smtp_user'] ?: '') ?>">
                        </div>

                        <button type="button" class="btn btn-success btn-block font-weight-bold py-2 shadow-sm" id="btnSendTestEmail" onclick="sendTestEmail()">
                            <i class="fas fa-paper-plane mr-2"></i>Send Test OTP Email
                        </button>

                        <div id="testEmailAlert" style="display: none;" class="alert mt-3 small mb-0" role="alert">
                            <span id="testEmailAlertMsg"></span>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <small class="text-muted d-block">
                            <i class="fas fa-shield-alt text-success mr-1"></i>
                            Emails are transmitted securely over TLS/SSL port 587 via PHPMailer.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 3: Real-Time OTP Dispatch Logs Table -->
    <div class="row mt-2">
        <div class="col-12 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap">
                    <h6 class="m-0 font-weight-bold text-gray-800">
                        <i class="fas fa-history text-info mr-2"></i>4. Real-Time OTP Dispatch &amp; Activity Logs
                    </h6>
                    <div class="mt-2 mt-md-0">
                        <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold" onclick="fetchOtpLogs()">
                            <i class="fas fa-sync-alt mr-1"></i> Refresh Logs
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold ml-2" onclick="clearOtpLogs()">
                            <i class="fas fa-trash-alt mr-1"></i> Clear Logs
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 small" id="otpLogsTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Date &amp; Time</th>
                                    <th>Recipient</th>
                                    <th>User Type</th>
                                    <th>Channel</th>
                                    <th>OTP Code</th>
                                    <th>Attempt</th>
                                    <th>Status</th>
                                    <th>Details / Error</th>
                                </tr>
                            </thead>
                            <tbody id="otpLogsTbody">
                                <?php if (!empty($recentLogs)): ?>
                                    <?php foreach ($recentLogs as $log): ?>
                                        <tr>
                                            <td><?= (int)$log['id'] ?></td>
                                            <td class="text-nowrap"><?= htmlspecialchars($log['created_at']) ?></td>
                                            <td class="font-weight-bold text-dark"><?= htmlspecialchars($log['recipient']) ?></td>
                                            <td>
                                                <span class="badge badge-light border text-uppercase">
                                                    <?= htmlspecialchars($log['user_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <i class="fas <?= $log['delivery_method'] === 'email' ? 'fa-envelope text-primary' : 'fa-mobile-alt text-success' ?> mr-1"></i>
                                                <?= ucfirst($log['delivery_method']) ?>
                                            </td>
                                            <td>
                                                <code class="font-weight-bold text-primary" style="font-size: 0.95rem;"><?= htmlspecialchars($log['otp_code']) ?></code>
                                            </td>
                                            <td>
                                                <span class="badge badge-secondary"><?= (int)$log['attempt_number'] ?> of 4</span>
                                            </td>
                                            <td>
                                                <?php if ($log['status'] === 'sent'): ?>
                                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Sent</span>
                                                <?php elseif ($log['status'] === 'blocked'): ?>
                                                    <span class="badge badge-warning px-2 py-1"><i class="fas fa-ban mr-1"></i>Blocked</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i>Failed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted"><?= htmlspecialchars($log['error_message'] ?: 'OK - Delivered successfully') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr id="noLogsRow">
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                                            No OTP requests logged yet. When customers or providers attempt login, activity will appear here in real time.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// State Management for OTP Configuration
var otpConfigState = {
    emailOn: <?= $emailOn ? 'true' : 'false' ?>,
    phoneOn: <?= $phoneOn ? 'true' : 'false' ?>
};

// Target endpoint for AJAX calls
var OTP_CONFIG_AJAX_URL = 'superadmin-dashboard.php?ajax_action=fetch_list&type=otp_config';

// Update visual preview when user types in the label or placeholder fields
function updateLivePreview() {
    var labelVal = $('#inputFieldLabel').val().trim();
    var placeholderVal = $('#inputFieldPlaceholder').val().trim();

    $('#previewLabelText').text(labelVal || 'Email or Phone Number');
    $('#previewPlaceholderInput').attr('placeholder', placeholderVal || 'Please enter your input');
}

// When user toggles Phone or Email, compute text automatically and save
function toggleOtpMethod(method) {
    var newEmail = otpConfigState.emailOn;
    var newPhone = otpConfigState.phoneOn;

    if (method === 'phone') {
        newPhone = !newPhone;
    } else if (method === 'email') {
        newEmail = !newEmail;
    }

    // Safety guard: at least one must remain enabled
    if (!newEmail && !newPhone) {
        showOtpAlert('danger', '<i class="fas fa-exclamation-triangle mr-1"></i> <strong>Safety Rule:</strong> At least one OTP login method (Email or Phone Number) must remain enabled.');
        return;
    }

    // Automatically determine text based on selection
    var autoLabel = 'Email or Phone Number';
    var autoPlaceholder = 'Please enter your phone number or email';

    if (newEmail && !newPhone) {
        autoLabel = 'Email Address';
        autoPlaceholder = 'Please enter your email';
    } else if (!newEmail && newPhone) {
        autoLabel = 'Phone Number';
        autoPlaceholder = 'Please enter your phone number';
    }

    // Update text fields immediately
    $('#inputFieldLabel').val(autoLabel);
    $('#inputFieldPlaceholder').val(autoPlaceholder);
    updateLivePreview();

    // Disable buttons during AJAX
    $('#btnTogglePhone, #btnToggleEmail, #btnSaveFieldText').prop('disabled', true);

    $.ajax({
        url: OTP_CONFIG_AJAX_URL,
        type: 'POST',
        dataType: 'json',
        data: {
            otp_action: 'update_otp_settings',
            email_otp_enabled: newEmail ? 1 : 0,
            phone_otp_enabled: newPhone ? 1 : 0,
            custom_label: autoLabel,
            custom_placeholder: autoPlaceholder
        },
        success: function(resp) {
            $('#btnTogglePhone, #btnToggleEmail, #btnSaveFieldText').prop('disabled', false);
            if (resp && resp.status === 'success') {
                otpConfigState.emailOn = newEmail;
                otpConfigState.phoneOn = newPhone;
                applyOtpUiState(resp.data);
                showOtpAlert('success', '<i class="fas fa-check-circle mr-1"></i> ' + resp.message);
            } else {
                showOtpAlert('danger', '<i class="fas fa-times-circle mr-1"></i> ' + (resp.message || 'Failed to update configuration.'));
            }
        },
        error: function(xhr, status, err) {
            $('#btnTogglePhone, #btnToggleEmail, #btnSaveFieldText').prop('disabled', false);
            var errDetail = err;
            try {
                if (xhr.responseText) {
                    var parsed = JSON.parse(xhr.responseText);
                    if (parsed.message) errDetail = parsed.message;
                }
            } catch(e) {}
            showOtpAlert('danger', '<i class="fas fa-exclamation-circle mr-1"></i> Error: ' + errDetail);
        }
    });
}

// Save input field text directly
function saveInputTextConfig() {
    var labelVal = $('#inputFieldLabel').val().trim();
    var placeholderVal = $('#inputFieldPlaceholder').val().trim();

    if (!placeholderVal) {
        showOtpAlert('warning', '<i class="fas fa-exclamation-circle mr-1"></i> Please provide a placeholder text for the input field.');
        return;
    }

    $('#btnSaveFieldText').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

    $.ajax({
        url: OTP_CONFIG_AJAX_URL,
        type: 'POST',
        dataType: 'json',
        data: {
            otp_action: 'save_input_text',
            email_otp_enabled: otpConfigState.emailOn ? 1 : 0,
            phone_otp_enabled: otpConfigState.phoneOn ? 1 : 0,
            custom_label: labelVal,
            custom_placeholder: placeholderVal
        },
        success: function(resp) {
            $('#btnSaveFieldText').prop('disabled', false).html('<i class="fas fa-save mr-2"></i>Save &amp; Apply Input Field Text');
            if (resp && resp.status === 'success') {
                applyOtpUiState(resp.data);
                showOtpAlert('success', '<i class="fas fa-check-circle mr-1"></i> ' + resp.message);
            } else {
                showOtpAlert('danger', '<i class="fas fa-times-circle mr-1"></i> ' + (resp.message || 'Failed to save field text.'));
            }
        },
        error: function(xhr, status, err) {
            $('#btnSaveFieldText').prop('disabled', false).html('<i class="fas fa-save mr-2"></i>Save &amp; Apply Input Field Text');
            var errDetail = err;
            try {
                if (xhr.responseText) {
                    var parsed = JSON.parse(xhr.responseText);
                    if (parsed.message) errDetail = parsed.message;
                }
            } catch(e) {}
            showOtpAlert('danger', '<i class="fas fa-exclamation-circle mr-1"></i> Error: ' + errDetail);
        }
    });
}

function applyOtpUiState(data) {
    if (!data) return;

    var email = data.email_otp_enabled;
    var phone = data.phone_otp_enabled;

    // Update Phone Card
    if (phone) {
        $('#cardPhoneOtp').removeClass('border-secondary').addClass('border-success');
        $('#iconBoxPhone').css({'background': '#e8f5e9', 'color': '#2e7d32'});
        $('#badgePhoneStatus').removeClass('badge-secondary').addClass('badge-success').text('● Active');
        $('#btnTogglePhone').removeClass('btn-success').addClass('btn-outline-danger').html('<i class="fas fa-ban mr-1"></i><span>Close Phone OTP</span>');
    } else {
        $('#cardPhoneOtp').removeClass('border-success').addClass('border-secondary');
        $('#iconBoxPhone').css({'background': '#f5f5f5', 'color': '#757575'});
        $('#badgePhoneStatus').removeClass('badge-success').addClass('badge-secondary').text('○ Closed');
        $('#btnTogglePhone').removeClass('btn-outline-danger').addClass('btn-success').html('<i class="fas fa-check mr-1"></i><span>Open Phone OTP</span>');
    }

    // Update Email Card
    if (email) {
        $('#cardEmailOtp').removeClass('border-secondary').addClass('border-success');
        $('#iconBoxEmail').css({'background': '#e8f5e9', 'color': '#2e7d32'});
        $('#badgeEmailStatus').removeClass('badge-secondary').addClass('badge-success').text('● Active');
        $('#btnToggleEmail').removeClass('btn-success').addClass('btn-outline-danger').html('<i class="fas fa-ban mr-1"></i><span>Close Email OTP</span>');
    } else {
        $('#cardEmailOtp').removeClass('border-success').addClass('border-secondary');
        $('#iconBoxEmail').css({'background': '#f5f5f5', 'color': '#757575'});
        $('#badgeEmailStatus').removeClass('badge-success').addClass('badge-secondary').text('○ Closed');
        $('#btnToggleEmail').removeClass('btn-outline-danger').addClass('btn-success').html('<i class="fas fa-check mr-1"></i><span>Open Email OTP</span>');
    }

    // Update Mode Badge
    $('#currentModeBadge')
        .removeClass('badge-info badge-warning')
        .addClass((email && phone) ? 'badge-info' : 'badge-warning')
        .text('Mode: ' + data.mode.toUpperCase());

    // Update Form Inputs if not currently focused
    if (!$('#inputFieldLabel').is(':focus')) {
        $('#inputFieldLabel').val(data.label);
    }
    if (!$('#inputFieldPlaceholder').is(':focus')) {
        $('#inputFieldPlaceholder').val(data.placeholder);
    }

    // Update Preview
    $('#previewLabelText').text(data.label);
    $('#previewPlaceholderInput').attr('placeholder', data.placeholder);

    var iconHtml = '<i class="fas fa-user"></i>';
    if (phone && !email) {
        iconHtml = '<i class="fas fa-phone"></i>';
    } else if (email && !phone) {
        iconHtml = '<i class="fas fa-envelope"></i>';
    }
    $('#previewIcon').html(iconHtml);

    // Update Metadata
    if (data.updated_by) $('#txtUpdatedBy').text(data.updated_by);
    if (data.updated_at) $('#txtUpdatedAt').text(data.updated_at);
}

function showOtpAlert(type, htmlMsg) {
    var $al = $('#otpStatusAlert');
    $al.removeClass('alert-success alert-danger alert-warning alert-info')
       .addClass('alert-' + type);
    $('#otpStatusAlertMsg').html(htmlMsg);
    $al.fadeIn();
    setTimeout(function() {
        $al.fadeOut();
    }, 6000);
}

// Toggle Password Visibility in SMTP Form
function toggleSmtpPasswordVisibility() {
    var passInput = $('#smtpPass');
    var icon = $('#togglePassIcon');
    if (passInput.attr('type') === 'password') {
        passInput.attr('type', 'text');
        icon.removeClass('fa-eye').addClass('fa-eye-slash');
    } else {
        passInput.attr('type', 'password');
        icon.removeClass('fa-eye-slash').addClass('fa-eye');
    }
}

// Save SMTP Configuration from Super Admin UI
function saveSmtpConfig() {
    var host = $('#smtpHost').val().trim();
    var port = $('#smtpPort').val().trim();
    var user = $('#smtpUser').val().trim();
    var pass = $('#smtpPass').val().trim();
    var fromEmail = $('#smtpFromEmail').val().trim();
    var fromName = $('#smtpFromName').val().trim();

    if (!user) {
        showOtpAlert('warning', '<i class="fas fa-exclamation-triangle mr-1"></i> Please enter your Gmail Address.');
        $('#smtpUser').focus();
        return;
    }
    if (!pass) {
        showOtpAlert('warning', '<i class="fas fa-exclamation-triangle mr-1"></i> Please enter your 16-character Google App Password.');
        $('#smtpPass').focus();
        return;
    }

    var $btn = $('#btnSaveSmtp');
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Saving &amp; Syncing...');

    $.ajax({
        url: OTP_CONFIG_AJAX_URL,
        type: 'POST',
        dataType: 'json',
        data: {
            otp_action: 'save_smtp_settings',
            smtp_host: host,
            smtp_port: port,
            smtp_user: user,
            smtp_pass: pass,
            smtp_from_email: fromEmail,
            smtp_from_name: fromName,
            smtp_encryption: 'tls'
        },
        success: function(resp) {
            $btn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i>Save &amp; Apply SMTP Settings');
            if (resp && resp.status === 'success') {
                $('#smtpStatusBadge')
                    .removeClass('badge-warning')
                    .addClass('badge-success')
                    .html('<i class="fas fa-check-circle mr-1"></i>Active &amp; Configured');
                if (!$('#testEmailInput').val()) {
                    $('#testEmailInput').val(user);
                }
                showOtpAlert('success', '<i class="fas fa-check-circle mr-1"></i> ' + resp.message);
            } else {
                showOtpAlert('danger', '<i class="fas fa-times-circle mr-1"></i> ' + (resp.message || 'Failed to save SMTP settings.'));
            }
        },
        error: function(xhr, status, err) {
            $btn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i>Save &amp; Apply SMTP Settings');
            var msg = err;
            try {
                var p = JSON.parse(xhr.responseText);
                if (p.message) msg = p.message;
            } catch(e) {}
            showOtpAlert('danger', '<i class="fas fa-exclamation-circle mr-1"></i> Error: ' + msg);
        }
    });
}

// Send Test Email from Super Admin UI
function sendTestEmail() {
    var testEmail = $('#testEmailInput').val().trim();
    if (!testEmail) {
        $('#testEmailAlert')
            .removeClass('alert-success alert-danger')
            .addClass('alert-warning')
            .html('<i class="fas fa-exclamation-circle mr-1"></i> Please enter an email address to receive the test OTP.')
            .fadeIn();
        return;
    }

    var $btn = $('#btnSendTestEmail');
    var $alert = $('#testEmailAlert');
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Sending...');
    $alert.hide();

    $.ajax({
        url: OTP_CONFIG_AJAX_URL,
        type: 'POST',
        dataType: 'json',
        data: {
            otp_action: 'send_test_email',
            test_email: testEmail
        },
        success: function(resp) {
            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-2"></i>Send Test OTP Email');
            if (resp && resp.status === 'success') {
                $alert
                    .removeClass('alert-warning alert-danger')
                    .addClass('alert-success')
                    .html('<i class="fas fa-check-circle mr-1"></i> ' + resp.message)
                    .fadeIn();
                fetchOtpLogs();
            } else {
                $alert
                    .removeClass('alert-warning alert-success')
                    .addClass('alert-danger')
                    .html('<i class="fas fa-times-circle mr-1"></i> ' + (resp.message || 'Test email failed.'))
                    .fadeIn();
            }
        },
        error: function(xhr, status, err) {
            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-2"></i>Send Test OTP Email');
            var msg = err;
            try {
                var p = JSON.parse(xhr.responseText);
                if (p.message) msg = p.message;
            } catch(e) {}
            $alert
                .removeClass('alert-warning alert-success')
                .addClass('alert-danger')
                .html('<i class="fas fa-times-circle mr-1"></i> Error: ' + msg)
                .fadeIn();
        }
    });
}

// Refresh live OTP dispatch logs
function fetchOtpLogs() {
    $.ajax({
        url: OTP_CONFIG_AJAX_URL,
        type: 'POST',
        dataType: 'json',
        data: {
            otp_action: 'fetch_otp_logs',
            limit: 50
        },
        success: function(resp) {
            if (resp && resp.status === 'success' && resp.logs) {
                renderOtpLogsTable(resp.logs);
            }
        }
    });
}

function renderOtpLogsTable(logs) {
    var tbody = $('#otpLogsTbody');
    if (!logs || logs.length === 0) {
        tbody.html('<tr id="noLogsRow"><td colspan="9" class="text-center py-4 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>No OTP requests logged yet.</td></tr>');
        return;
    }

    var html = '';
    for (var i = 0; i < logs.length; i++) {
        var log = logs[i];
        var statusBadge = '<span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Sent</span>';
        if (log.status === 'blocked') {
            statusBadge = '<span class="badge badge-warning px-2 py-1"><i class="fas fa-ban mr-1"></i>Blocked</span>';
        } else if (log.status === 'failed') {
            statusBadge = '<span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i>Failed</span>';
        }

        var channelIcon = log.delivery_method === 'email' ? '<i class="fas fa-envelope text-primary mr-1"></i>Email' : '<i class="fas fa-mobile-alt text-success mr-1"></i>Phone';

        html += '<tr>' +
            '<td>' + log.id + '</td>' +
            '<td class="text-nowrap">' + escapeHtml(log.created_at) + '</td>' +
            '<td class="font-weight-bold text-dark">' + escapeHtml(log.recipient) + '</td>' +
            '<td><span class="badge badge-light border text-uppercase">' + escapeHtml(log.user_type) + '</span></td>' +
            '<td>' + channelIcon + '</td>' +
            '<td><code class="font-weight-bold text-primary" style="font-size: 0.95rem;">' + escapeHtml(log.otp_code) + '</code></td>' +
            '<td><span class="badge badge-secondary">' + (log.attempt_number || 1) + ' of 4</span></td>' +
            '<td>' + statusBadge + '</td>' +
            '<td class="text-muted">' + escapeHtml(log.error_message || 'OK - Delivered successfully') + '</td>' +
            '</tr>';
    }
    tbody.html(html);
}

function clearOtpLogs() {
    if (!confirm('Are you sure you want to clear all OTP dispatch logs?')) {
        return;
    }
    $.ajax({
        url: OTP_CONFIG_AJAX_URL,
        type: 'POST',
        dataType: 'json',
        data: { otp_action: 'clear_otp_logs' },
        success: function(resp) {
            if (resp && resp.status === 'success') {
                renderOtpLogsTable([]);
                showOtpAlert('success', '<i class="fas fa-check-circle mr-1"></i> ' + resp.message);
            }
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return $('<div>').text(text).html();
}
</script>
