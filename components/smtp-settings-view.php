<?php
/**
 * ServEasy - Super Admin Gmail SMTP Configuration Component
 * Restricted strictly to Super Administrator
 * Enables live configuration of Gmail SMTP credentials for 100% Free Email OTP delivery
 * Automatically synchronizes with MySQL database and backend/.env file
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo)) {
    require_once __DIR__ . '/../../../backend/config/db_connection.php';
    $pdo = Database::connect();
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
    if (isset($_POST['smtp_action'])) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Access Denied: SMTP Configuration is strictly restricted to Super Administrators.']);
        exit();
    }
    echo '<div class="card shadow-sm border-0 m-4"><div class="card-body text-center py-5 text-danger font-weight-bold"><i class="fas fa-lock fa-3x mb-3 d-block"></i>Access Denied: SMTP Configuration is strictly restricted to Super Administrators.</div></div>';
    exit();
}

require_once __DIR__ . '/../../../backend/app/Services/OtpConfigService.php';
use App\Services\OtpConfigService;

// ── Handle AJAX actions for SMTP Setup ─────────────────────────────
if (isset($_POST['smtp_action'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['smtp_action'];

    if ($action === 'save_smtp_settings') {
        $host = trim($_POST['smtp_host'] ?? 'smtp.gmail.com');
        $port = (int)($_POST['smtp_port'] ?? 587);
        $user = trim($_POST['smtp_user'] ?? '');
        $pass = trim($_POST['smtp_pass'] ?? '');
        $fromEmail = trim($_POST['smtp_from_email'] ?? $user);
        $fromName = trim($_POST['smtp_from_name'] ?? 'ServEasy');
        $encryption = trim($_POST['smtp_encryption'] ?? 'tls');
        $adminName = $_SESSION['admin_username'] ?? $_SESSION['admin_name'] ?? 'Super Admin';

        if (empty($user)) {
            echo json_encode(['status' => 'error', 'message' => 'Gmail Address (SMTP_USER) cannot be empty.']);
            exit();
        }
        if (empty($pass)) {
            echo json_encode(['status' => 'error', 'message' => '16-Character App Password (SMTP_PASS) cannot be empty.']);
            exit();
        }

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
                'message' => 'Gmail SMTP settings saved successfully and synchronized with backend/.env!',
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
                'message' => 'Email test failed: ' . ($res['message'] ?? 'Could not deliver email. Please check your credentials and App Password.')
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

$smtpConfig = OtpConfigService::getSmtpConfig();
$recentLogs = OtpConfigService::getOtpLogs(30);
$isConfigured = !empty($smtpConfig['is_configured']);
?>

<div class="container-fluid py-3" id="smtpSettingsContainer">
    <!-- Header & Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap">
        <div>
            <h4 class="font-weight-bold text-gray-800 mb-1">
                <i class="fas fa-envelope-open-text text-primary mr-2"></i>Gmail SMTP Setup (Free Email OTP)
            </h4>
            <p class="text-muted small mb-0">
                Configure your Gmail SMTP credentials here to send 100% free email OTP verification codes site-wide. No manual <code>.env</code> file editing required.
            </p>
        </div>
        <div class="mt-2 mt-md-0 d-flex align-items-center">
            <span class="badge <?= $isConfigured ? 'badge-success' : 'badge-warning' ?> px-3 py-2 mr-2 font-weight-bold" id="topSmtpStatusBadge" style="font-size: 0.82rem;">
                <i class="fas <?= $isConfigured ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> mr-1"></i>
                <?= $isConfigured ? 'SMTP Configured &amp; Active' : 'SMTP Credentials Needed' ?>
            </span>
            <span class="badge badge-success px-3 py-2 font-weight-bold" style="font-size: 0.82rem;">
                <i class="fas fa-crown mr-1"></i> Super Admin Exclusive
            </span>
        </div>
    </div>

    <!-- Alert Box -->
    <div id="smtpStatusAlert" style="display: none;" class="alert alert-dismissible fade show mb-4" role="alert">
        <span id="smtpStatusAlertMsg"></span>
        <button type="button" class="close" onclick="$('#smtpStatusAlert').fadeOut();">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <!-- Main Row: Configuration Form & Diagnostic Test -->
    <div class="row">
        <!-- Left: SMTP Credentials Form -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-gray-800">
                        <i class="fab fa-google text-danger mr-2"></i>Gmail SMTP Server Credentials
                    </h6>
                    <span class="badge badge-light border text-secondary font-weight-bold" style="font-size: 0.75rem;">
                        <i class="fas fa-shield-alt text-success mr-1"></i>Secured TLS / Port 587
                    </span>
                </div>
                <div class="card-body p-4">
                    <p class="small text-muted mb-4">
                        Enter your Gmail address and 16-character Google App Password below. When you click <strong>Save &amp; Apply SMTP Settings</strong>, these values are instantly saved to the database and automatically written to <code>backend/.env</code>.
                    </p>

                    <form id="dedicatedSmtpForm" onsubmit="event.preventDefault(); submitDedicatedSmtp();">
                        <div class="row">
                            <!-- SMTP Host -->
                            <div class="col-md-8 mb-3">
                                <label class="font-weight-bold small text-gray-800" for="smtpHostInput">
                                    <i class="fas fa-server text-secondary mr-1"></i>SMTP Host (<code>SMTP_HOST</code>)
                                </label>
                                <input type="text" class="form-control" id="smtpHostInput" name="smtp_host" value="<?= htmlspecialchars($smtpConfig['smtp_host'] ?: 'smtp.gmail.com') ?>" placeholder="smtp.gmail.com" required>
                                <small class="text-muted">Standard Gmail host: <code>smtp.gmail.com</code></small>
                            </div>
                            <!-- SMTP Port -->
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold small text-gray-800" for="smtpPortInput">
                                    <i class="fas fa-network-wired text-secondary mr-1"></i>SMTP Port (<code>SMTP_PORT</code>)
                                </label>
                                <input type="number" class="form-control" id="smtpPortInput" name="smtp_port" value="<?= htmlspecialchars((string)($smtpConfig['smtp_port'] ?: 587)) ?>" placeholder="587" required>
                                <small class="text-muted">Port <code>587</code> (STARTTLS) recommended</small>
                            </div>
                        </div>

                        <div class="row">
                            <!-- SMTP User / Gmail Address -->
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-gray-800" for="smtpUserInput">
                                    <i class="fas fa-envelope text-primary mr-1"></i>Gmail Address (<code>SMTP_USER</code>)
                                </label>
                                <input type="email" class="form-control" id="smtpUserInput" name="smtp_user" value="<?= htmlspecialchars($smtpConfig['smtp_user']) ?>" placeholder="yourname@gmail.com" required>
                                <small class="text-muted">Your personal or company Gmail account</small>
                            </div>

                            <!-- SMTP Pass / 16-Character App Password -->
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-gray-800" for="smtpPassInput">
                                    <i class="fas fa-key text-warning mr-1"></i>16-Character App Password (<code>SMTP_PASS</code>)
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="smtpPassInput" name="smtp_pass" value="<?= htmlspecialchars($smtpConfig['smtp_pass']) ?>" placeholder="xxxx xxxx xxxx xxxx" required>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" onclick="toggleDedicatedPassVisibility()" title="Show/Hide Password">
                                            <i class="fas fa-eye" id="togglePassEyeIcon"></i>
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted">Generated at <code>myaccount.google.com/apppasswords</code></small>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Sender From Email -->
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-gray-800" for="smtpFromEmailInput">
                                    <i class="fas fa-at text-secondary mr-1"></i>From Email Address (<code>SMTP_FROM_EMAIL</code>)
                                </label>
                                <input type="email" class="form-control" id="smtpFromEmailInput" name="smtp_from_email" value="<?= htmlspecialchars($smtpConfig['smtp_from_email']) ?>" placeholder="yourname@gmail.com">
                                <small class="text-muted">Leave empty to use Gmail Address above</small>
                            </div>

                            <!-- Sender From Name -->
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-gray-800" for="smtpFromNameInput">
                                    <i class="fas fa-signature text-secondary mr-1"></i>From Sender Name (<code>SMTP_FROM_NAME</code>)
                                </label>
                                <input type="text" class="form-control" id="smtpFromNameInput" name="smtp_from_name" value="<?= htmlspecialchars($smtpConfig['smtp_from_name'] ?: 'ServEasy') ?>" placeholder="ServEasy">
                                <small class="text-muted">Display name shown in user's inbox (e.g. <code>ServEasy</code>)</small>
                            </div>
                        </div>

                        <!-- Step-by-Step Gmail App Password Guide -->
                        <div class="alert alert-light border border-info rounded p-3 mb-4" style="background-color: #f7fbfe;">
                            <h6 class="font-weight-bold text-info mb-2">
                                <i class="fas fa-info-circle mr-1"></i>How to get your free 16-character Google App Password in 60 seconds:
                            </h6>
                            <ol class="small text-muted mb-0 pl-3">
                                <li class="mb-1">Make sure <strong>2-Step Verification</strong> is ON at <a href="https://myaccount.google.com/security" target="_blank" class="font-weight-bold text-dark">myaccount.google.com/security <i class="fas fa-external-link-alt fa-xs text-primary"></i></a>.</li>
                                <li class="mb-1">Go to <a href="https://myaccount.google.com/apppasswords" target="_blank" class="font-weight-bold text-primary">myaccount.google.com/apppasswords <i class="fas fa-external-link-alt fa-xs"></i></a>.</li>
                                <li class="mb-1">Enter App name: <code>ServEasy</code> and click <strong>Create</strong>.</li>
                                <li>Google will display a 16-character code (e.g. <code>abcd efgh ijkl mnop</code>). Copy and paste it into the <strong>SMTP_PASS</strong> box above and click Save.</li>
                            </ol>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex align-items-center flex-wrap">
                            <button type="submit" class="btn btn-primary font-weight-bold py-2 px-4 shadow-sm mr-3 mb-2" id="btnSaveDedicatedSmtp">
                                <i class="fas fa-save mr-2"></i>Save &amp; Apply SMTP Settings
                            </button>
                            <button type="button" class="btn btn-outline-secondary font-weight-bold py-2 px-3 mb-2" onclick="loadSection('otp_config');return false;">
                                <i class="fas fa-sliders-h mr-1"></i>Open OTP Login Channels
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Test Live Email Dispatch -->
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
                            Send a real live verification OTP code to your personal email inbox right now to verify that Gmail SMTP is delivering emails properly.
                        </p>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small text-gray-800 mb-1" for="dedicatedTestEmailInput">
                                <i class="fas fa-user-check text-success mr-1"></i>Recipient Email Address
                            </label>
                            <input type="email" class="form-control" id="dedicatedTestEmailInput" placeholder="Enter recipient email..." value="<?= htmlspecialchars($smtpConfig['smtp_user'] ?: '') ?>">
                            <small class="text-muted">Enter any email address to test live delivery</small>
                        </div>

                        <button type="button" class="btn btn-success btn-block font-weight-bold py-2 shadow-sm" id="btnDedicatedSendTest" onclick="submitDedicatedTestEmail()">
                            <i class="fas fa-paper-plane mr-2"></i>Send Test OTP Email
                        </button>

                        <div id="dedicatedTestEmailAlert" style="display: none;" class="alert mt-3 small mb-0" role="alert">
                            <span id="dedicatedTestEmailAlertMsg"></span>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <div class="small text-muted">
                            <div class="d-flex align-items-center mb-1">
                                <i class="fas fa-check-circle text-success mr-2"></i>
                                <span><strong>Daily Quota:</strong> 500 emails/day free</span>
                            </div>
                            <div class="d-flex align-items-center mb-1">
                                <i class="fas fa-lock text-primary mr-2"></i>
                                <span><strong>Encryption:</strong> TLS 587 / SSL 465</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-bolt text-warning mr-2"></i>
                                <span><strong>Cost:</strong> 100% Free Forever</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Real-Time OTP Dispatch Logs Table -->
    <div class="row mt-2">
        <div class="col-12 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap">
                    <h6 class="m-0 font-weight-bold text-gray-800">
                        <i class="fas fa-history text-info mr-2"></i>Real-Time OTP Dispatch &amp; Delivery Logs
                    </h6>
                    <div class="mt-2 mt-md-0">
                        <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold" onclick="fetchDedicatedOtpLogs()">
                            <i class="fas fa-sync-alt mr-1"></i> Refresh Logs
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold ml-2" onclick="clearDedicatedOtpLogs()">
                            <i class="fas fa-trash-alt mr-1"></i> Clear Logs
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 small" id="dedicatedOtpLogsTable">
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
                            <tbody id="dedicatedOtpLogsTbody">
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
                                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check mr-1"></i>Sent</span>
                                                <?php elseif ($log['status'] === 'blocked'): ?>
                                                    <span class="badge badge-danger px-2 py-1"><i class="fas fa-ban mr-1"></i>Rate Limited</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning px-2 py-1"><i class="fas fa-exclamation mr-1"></i>Failed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted" style="max-width: 250px;">
                                                <?= htmlspecialchars($log['error_message'] ?? 'Delivered via SMTP') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                                            No OTP dispatches recorded yet. Use the <strong>Test Live Email Dispatch</strong> button above to test.
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
var SMTP_SETTINGS_AJAX_URL = 'superadmin-dashboard.php?ajax_action=fetch_list&type=smtp_settings';

// Toggle password visibility
function toggleDedicatedPassVisibility() {
    var passInput = document.getElementById('smtpPassInput');
    var icon = document.getElementById('togglePassEyeIcon');
    if (!passInput || !icon) return;
    if (passInput.type === 'password') {
        passInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Show alert banner
function showSmtpStatusAlert(msg, type) {
    var alertBox = $('#smtpStatusAlert');
    var msgSpan = $('#smtpStatusAlertMsg');
    alertBox.removeClass('alert-success alert-danger alert-warning alert-info')
            .addClass('alert-' + type);
    msgSpan.html(msg);
    alertBox.fadeIn();
    $('html, body').animate({ scrollTop: alertBox.offset().top - 80 }, 250);
}

// Submit SMTP Settings Form
function submitDedicatedSmtp() {
    var btn = $('#btnSaveDedicatedSmtp');
    var originalHtml = btn.html();

    var host = $('#smtpHostInput').val().trim();
    var port = $('#smtpPortInput').val().trim();
    var user = $('#smtpUserInput').val().trim();
    var pass = $('#smtpPassInput').val().trim();
    var fromEmail = $('#smtpFromEmailInput').val().trim();
    var fromName = $('#smtpFromNameInput').val().trim();

    if (!user) {
        showSmtpStatusAlert('<i class="fas fa-exclamation-triangle mr-2"></i>Please enter your Gmail address (SMTP_USER).', 'warning');
        return;
    }
    if (!pass) {
        showSmtpStatusAlert('<i class="fas fa-exclamation-triangle mr-2"></i>Please enter your 16-character Google App Password (SMTP_PASS).', 'warning');
        return;
    }

    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Saving to DB &amp; .env...');

    $.ajax({
        url: SMTP_SETTINGS_AJAX_URL,
        type: 'POST',
        data: {
            smtp_action: 'save_smtp_settings',
            smtp_host: host,
            smtp_port: port,
            smtp_user: user,
            smtp_pass: pass,
            smtp_from_email: fromEmail,
            smtp_from_name: fromName,
            smtp_encryption: 'tls'
        },
        dataType: 'json',
        success: function (res) {
            btn.prop('disabled', false).html(originalHtml);
            if (res && res.status === 'success') {
                showSmtpStatusAlert('<i class="fas fa-check-circle mr-2"></i>' + res.message, 'success');
                $('#topSmtpStatusBadge').removeClass('badge-warning').addClass('badge-success')
                    .html('<i class="fas fa-check-circle mr-1"></i>SMTP Configured &amp; Active');
                
                // Pre-fill test recipient if blank
                if (!$('#dedicatedTestEmailInput').val()) {
                    $('#dedicatedTestEmailInput').val(user);
                }

                if (typeof Notify !== 'undefined' && Notify.success) {
                    Notify.success('Gmail SMTP settings saved and synchronized with .env!');
                }
            } else {
                showSmtpStatusAlert('<i class="fas fa-times-circle mr-2"></i>' + (res.message || 'Failed to save SMTP settings.'), 'danger');
            }
        },
        error: function (xhr) {
            btn.prop('disabled', false).html(originalHtml);
            var err = 'Server error saving SMTP settings.';
            try {
                var json = JSON.parse(xhr.responseText);
                if (json.message) err = json.message;
            } catch (e) {}
            showSmtpStatusAlert('<i class="fas fa-times-circle mr-2"></i>' + err, 'danger');
        }
    });
}

// Send Live Test Email
function submitDedicatedTestEmail() {
    var emailInput = $('#dedicatedTestEmailInput');
    var testEmail = emailInput.val().trim();
    var btn = $('#btnDedicatedSendTest');
    var alertBox = $('#dedicatedTestEmailAlert');
    var msgSpan = $('#dedicatedTestEmailAlertMsg');
    var originalHtml = btn.html();

    if (!testEmail) {
        alertBox.removeClass('alert-success alert-warning alert-info').addClass('alert-danger').fadeIn();
        msgSpan.html('<i class="fas fa-exclamation-circle mr-1"></i>Please enter a valid recipient email address.');
        return;
    }

    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Connecting &amp; Sending...');
    alertBox.removeClass('alert-danger alert-success alert-warning').addClass('alert-info').fadeIn();
    msgSpan.html('<i class="fas fa-circle-notch fa-spin mr-1"></i>Sending live test OTP via Gmail SMTP port 587...');

    $.ajax({
        url: SMTP_SETTINGS_AJAX_URL,
        type: 'POST',
        data: {
            smtp_action: 'send_test_email',
            test_email: testEmail
        },
        dataType: 'json',
        success: function (res) {
            btn.prop('disabled', false).html(originalHtml);
            if (res && res.status === 'success') {
                alertBox.removeClass('alert-info alert-danger alert-warning').addClass('alert-success').fadeIn();
                msgSpan.html('<i class="fas fa-check-circle mr-1"></i>' + res.message);
                if (typeof Notify !== 'undefined' && Notify.success) {
                    Notify.success('Live test OTP email delivered!');
                }
                fetchDedicatedOtpLogs();
            } else {
                alertBox.removeClass('alert-info alert-success').addClass('alert-danger').fadeIn();
                msgSpan.html('<i class="fas fa-times-circle mr-1"></i>' + (res.message || 'Email delivery failed.'));
            }
        },
        error: function (xhr) {
            btn.prop('disabled', false).html(originalHtml);
            var err = 'Server error during live email test.';
            try {
                var json = JSON.parse(xhr.responseText);
                if (json.message) err = json.message;
            } catch (e) {}
            alertBox.removeClass('alert-info alert-success').addClass('alert-danger').fadeIn();
            msgSpan.html('<i class="fas fa-times-circle mr-1"></i>' + err);
        }
    });
}

// Fetch OTP dispatch logs
function fetchDedicatedOtpLogs() {
    $.ajax({
        url: SMTP_SETTINGS_AJAX_URL,
        type: 'POST',
        data: { smtp_action: 'fetch_otp_logs', limit: 30 },
        dataType: 'json',
        success: function (res) {
            if (res && res.status === 'success' && Array.isArray(res.logs)) {
                renderDedicatedLogsTable(res.logs);
            }
        }
    });
}

function renderDedicatedLogsTable(logs) {
    var tbody = $('#dedicatedOtpLogsTbody');
    if (!logs || logs.length === 0) {
        tbody.html('<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>No OTP dispatches recorded yet.</td></tr>');
        return;
    }

    var html = '';
    logs.forEach(function (log) {
        var statusBadge = '';
        if (log.status === 'sent') {
            statusBadge = '<span class="badge badge-success px-2 py-1"><i class="fas fa-check mr-1"></i>Sent</span>';
        } else if (log.status === 'blocked') {
            statusBadge = '<span class="badge badge-danger px-2 py-1"><i class="fas fa-ban mr-1"></i>Rate Limited</span>';
        } else {
            statusBadge = '<span class="badge badge-warning px-2 py-1"><i class="fas fa-exclamation mr-1"></i>Failed</span>';
        }

        var channelIcon = log.delivery_method === 'email' ? 'fa-envelope text-primary' : 'fa-mobile-alt text-success';
        var channelName = log.delivery_method ? log.delivery_method.charAt(0).toUpperCase() + log.delivery_method.slice(1) : 'Email';

        html += '<tr>' +
            '<td>' + (log.id || '') + '</td>' +
            '<td class="text-nowrap">' + (log.created_at || '') + '</td>' +
            '<td class="font-weight-bold text-dark">' + escapeHtml(log.recipient || '') + '</td>' +
            '<td><span class="badge badge-light border text-uppercase">' + escapeHtml(log.user_type || 'user') + '</span></td>' +
            '<td><i class="fas ' + channelIcon + ' mr-1"></i>' + channelName + '</td>' +
            '<td><code class="font-weight-bold text-primary" style="font-size: 0.95rem;">' + escapeHtml(log.otp_code || '') + '</code></td>' +
            '<td><span class="badge badge-secondary">' + (log.attempt_number || 1) + ' of 4</span></td>' +
            '<td>' + statusBadge + '</td>' +
            '<td class="text-muted" style="max-width: 250px;">' + escapeHtml(log.error_message || 'Delivered via SMTP') + '</td>' +
            '</tr>';
    });

    tbody.html(html);
}

// Clear logs
function clearDedicatedOtpLogs() {
    if (!confirm('Are you sure you want to clear all OTP dispatch history logs?')) return;
    $.ajax({
        url: SMTP_SETTINGS_AJAX_URL,
        type: 'POST',
        data: { smtp_action: 'clear_otp_logs' },
        dataType: 'json',
        success: function (res) {
            if (res && res.status === 'success') {
                renderDedicatedLogsTable([]);
                if (typeof Notify !== 'undefined' && Notify.success) {
                    Notify.success('OTP logs cleared.');
                }
            } else {
                alert(res.message || 'Could not clear logs.');
            }
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>
