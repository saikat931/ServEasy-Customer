<?php
/**
 * ServEasy - Super Admin Payment Gateway Key Configuration Component
 * Restricted strictly to Super Administrator
 * Manages Razorpay Key ID, Key Secret, Webhook Secret, and Environment settings
 */

if (!isset($pdo)) {
    require_once __DIR__ . '/../../../backend/config/db_connection.php';
    $pdo = Database::connect();
}

$is_super = false;
if (isset($is_super_admin) && $is_super_admin) {
    $is_super = true;
} elseif (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin') {
    $is_super = true;
}

if (!$is_super) {
    echo '<div class="card shadow-sm border-0 m-4"><div class="card-body text-center py-5 text-danger font-weight-bold"><i class="fas fa-lock fa-3x mb-3 d-block"></i>Access Denied: Payment Gateway Key configuration is strictly restricted to Super Administrators.</div></div>';
    exit();
}

// ── Handle AJAX Actions for Gateway Configuration ──────────────
if (isset($_POST['gateway_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['gateway_action'];

    if ($action === 'save_gateway_config') {
        $env = strtoupper(trim($_POST['environment'] ?? 'TEST'));
        if (!in_array($env, ['TEST', 'LIVE'])) $env = 'TEST';

        $keyId = trim($_POST['key_id'] ?? '');
        $keySecret = trim($_POST['key_secret'] ?? '');
        $webhookSecret = trim($_POST['webhook_secret'] ?? '');
        $merchantName = trim($_POST['merchant_name'] ?? 'ServEasy Marketplace');
        $currency = strtoupper(trim($_POST['currency'] ?? 'INR'));
        $isActive = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;
        $autoCapture = isset($_POST['auto_capture']) && $_POST['auto_capture'] == '1' ? 1 : 0;
        $adminName = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Super Admin';

        if (empty($keyId) || empty($keySecret)) {
            echo json_encode(['status' => 'error', 'message' => 'Both Razorpay Key ID and Key Secret are required.']);
            exit();
        }

        // Format checks
        if ($env === 'TEST' && !str_starts_with($keyId, 'rzp_test_')) {
            echo json_encode(['status' => 'error', 'message' => 'For TEST mode, the Key ID must start with "rzp_test_" (e.g. rzp_test_xxxxxxxxxx).']);
            exit();
        }
        if ($env === 'LIVE' && !str_starts_with($keyId, 'rzp_live_')) {
            echo json_encode(['status' => 'error', 'message' => 'For LIVE mode, the Key ID must start with "rzp_live_" (e.g. rzp_live_xxxxxxxxxx).']);
            exit();
        }

        try {
            // If activating this environment, deactivate other environments for the same gateway
            if ($isActive === 1) {
                $deactStmt = $pdo->prepare("UPDATE `payment_gateway_config` SET is_active = 0 WHERE gateway_name = 'razorpay'");
                $deactStmt->execute();
            }

            $sql = "INSERT INTO `payment_gateway_config`
                (`gateway_name`, `environment`, `key_id`, `key_secret`, `webhook_secret`, `merchant_name`, `currency`, `is_active`, `auto_capture`, `updated_by`, `updated_at`)
                VALUES ('razorpay', :env, :kid, :ksec, :wsec, :mname, :curr, :act, :acap, :uby, NOW())
                ON DUPLICATE KEY UPDATE
                    `key_id` = VALUES(`key_id`),
                    `key_secret` = VALUES(`key_secret`),
                    `webhook_secret` = VALUES(`webhook_secret`),
                    `merchant_name` = VALUES(`merchant_name`),
                    `currency` = VALUES(`currency`),
                    `is_active` = VALUES(`is_active`),
                    `auto_capture` = VALUES(`auto_capture`),
                    `updated_by` = VALUES(`updated_by`),
                    `updated_at` = NOW()";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':env' => $env,
                ':kid' => $keyId,
                ':ksec' => $keySecret,
                ':wsec' => $webhookSecret,
                ':mname' => $merchantName,
                ':curr' => $currency,
                ':act' => $isActive,
                ':acap' => $autoCapture,
                ':uby' => $adminName
            ]);

            // Synchronize with backend/.env file for automatic dual-persistence
            $envPath = dirname(__DIR__, 3) . '/backend/.env';
            if (file_exists($envPath)) {
                $envContent = file_get_contents($envPath);
                $envUpdates = [
                    'RAZORPAY_KEY_ID' => $keyId,
                    'RAZORPAY_KEY_SECRET' => $keySecret,
                    'RAZORPAY_WEBHOOK_SECRET' => $webhookSecret
                ];
                foreach ($envUpdates as $k => $v) {
                    if (preg_match("/^{$k}=.*$/m", $envContent)) {
                        $envContent = preg_replace("/^{$k}=.*$/m", "{$k}={$v}", $envContent);
                    } else {
                        $envContent .= "\n{$k}={$v}";
                    }
                }
                file_put_contents($envPath, $envContent);
            }

            echo json_encode([
                'status' => 'success',
                'message' => "Razorpay {$env} payment gateway credentials saved and activated successfully!"
            ]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            exit();
        }
    }

    if ($action === 'test_connection') {
        $keyId = trim($_POST['key_id'] ?? '');
        $keySecret = trim($_POST['key_secret'] ?? '');

        if (empty($keyId) || empty($keySecret)) {
            echo json_encode(['status' => 'error', 'message' => 'Please provide both Key ID and Key Secret to test.']);
            exit();
        }

        // Test API call to Razorpay
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'amount' => 100, // 1 Rupee in paise
            'currency' => 'INR',
            'receipt' => 'TEST_CONN_' . time(),
            'notes' => ['purpose' => 'superadmin_connection_test']
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            echo json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $curlErr]);
            exit();
        }

        if ($httpCode === 200 || $httpCode === 201) {
            $data = json_decode($response, true);
            echo json_encode([
                'status' => 'success',
                'message' => 'Razorpay credentials verified successfully! Test Order #' . ($data['id'] ?? 'OK') . ' generated.'
            ]);
            exit();
        } elseif ($httpCode === 401) {
            echo json_encode(['status' => 'error', 'message' => 'Authentication failed (401). Invalid Razorpay Key ID or Key Secret.']);
            exit();
        } else {
            $data = json_decode($response, true);
            $msg = $data['error']['description'] ?? ('Razorpay returned HTTP ' . $httpCode);
            echo json_encode(['status' => 'error', 'message' => 'Verification failed: ' . $msg]);
            exit();
        }
    }

    exit();
}

// ── Fetch current configurations ──────────────────────────────
$configs = [];
try {
    $stmt = $pdo->query("SELECT * FROM `payment_gateway_config` WHERE gateway_name = 'razorpay'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $configs[$row['environment']] = $row;
    }
} catch (Exception $e) {}

$activeEnv = 'TEST';
foreach ($configs as $envKey => $c) {
    if (!empty($c['is_active'])) {
        $activeEnv = $envKey;
        break;
    }
}

$currentCfg = $configs[$activeEnv] ?? [
    'environment' => 'TEST',
    'key_id' => '',
    'key_secret' => '',
    'webhook_secret' => '',
    'merchant_name' => 'ServEasy Marketplace',
    'currency' => 'INR',
    'is_active' => 1,
    'auto_capture' => 1,
    'updated_by' => 'Not configured',
    'updated_at' => null
];

// Pre-calculate Webhook URL dynamically from DomainConfigService
if (class_exists('\\App\\Services\\DomainConfigService')) {
    $webhookUrl = \App\Services\DomainConfigService::getApiUrl() . '/payment-webhook.php';
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $webhookUrl = $protocol . $host . '/api/payment-webhook.php';
}
?>

<div class="container-fluid px-0">
    <!-- Breadcrumb / Header Title -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800 font-weight-bold">
                <i class="fas fa-key text-success mr-2"></i> Payment Gateway Key Configuration
            </h1>
            <p class="text-muted small mb-0">
                Configure your official Razorpay payment gateway credentials. Only Super Administrator accounts have access to view and update these settings.
            </p>
        </div>
        <div class="d-flex align-items-center mt-3 mt-sm-0" style="gap: 10px;">
            <span class="badge <?= $activeEnv === 'LIVE' ? 'badge-success' : 'badge-warning' ?> px-3 py-2 font-weight-bold" style="font-size: 0.85rem; border-radius: 8px;">
                <i class="fas fa-circle mr-1" style="font-size: 0.65rem;"></i> <?= $activeEnv === 'LIVE' ? 'PRODUCTION (LIVE)' : 'SANDBOX (TEST)' ?>
            </span>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadSection('payment_gateway')" title="Reload Settings">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <!-- Security Notice Banner -->
    <div class="alert alert-light border-left-success shadow-sm mb-4" style="border-left-width: 4px !important; border-radius: 10px; background: #fff;">
        <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap: 12px;">
            <div class="d-flex align-items-center">
                <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 42px; height: 42px; background: #e6fbf5; color: #05C279; font-size: 1.2rem;">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div>
                    <h6 class="font-weight-bold text-gray-800 mb-0">Secure Credential Vault</h6>
                    <small class="text-muted">
                        Keys are stored in ``serveasy-admin``.``payment_gateway_config`` and used across Customer Checkout, Auto-Capture, and Webhook Signatures.
                        <?php if (!empty($currentCfg['updated_at'])): ?>
                            Last updated on <strong><?= htmlspecialchars($currentCfg['updated_at']) ?></strong> by <strong><?= htmlspecialchars($currentCfg['updated_by']) ?></strong>.
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <div>
                <a href="https://dashboard.razorpay.com/" target="_blank" class="btn btn-sm btn-outline-primary font-weight-bold px-3 py-2" style="border-radius: 8px;">
                    <i class="fas fa-external-link-alt mr-1"></i> Open Razorpay Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Form Column -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 14px;">
                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between border-bottom">
                    <h6 class="m-0 font-weight-bold text-gray-800">
                        <i class="fas fa-sliders-h mr-2 text-primary"></i> Gateway Credentials & Environment
                    </h6>
                    <div class="btn-group btn-group-sm" role="group" id="envToggleGroup">
                        <button type="button" class="btn <?= $activeEnv === 'TEST' ? 'btn-primary' : 'btn-light border' ?> font-weight-bold" onclick="switchGatewayEnv('TEST')" id="btnEnvTest">
                            <i class="fas fa-flask mr-1"></i> TEST (Sandbox)
                        </button>
                        <button type="button" class="btn <?= $activeEnv === 'LIVE' ? 'btn-success' : 'btn-light border' ?> font-weight-bold" onclick="switchGatewayEnv('LIVE')" id="btnEnvLive">
                            <i class="fas fa-rocket mr-1"></i> LIVE (Production)
                        </button>
                    </div>
                </div>

                <div class="card-body p-4">
                    <form id="formPaymentGateway" onsubmit="submitGatewayConfig(event)">
                        <input type="hidden" id="gatewayEnvironment" name="environment" value="<?= htmlspecialchars($activeEnv) ?>">

                        <!-- Provider & Merchant Info Row -->
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="font-weight-bold text-gray-800 small text-uppercase">Payment Gateway Provider</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white"><i class="fas fa-credit-card text-primary"></i></span>
                                    </div>
                                    <input type="text" class="form-control font-weight-bold" value="Razorpay (Default Gateway)" readonly style="background: #f8fafc;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="font-weight-bold text-gray-800 small text-uppercase">Merchant / Brand Name <span class="text-danger">*</span></label>
                                <input type="text" id="merchantName" name="merchant_name" class="form-control" value="<?= htmlspecialchars($currentCfg['merchant_name'] ?? 'ServEasy Marketplace') ?>" placeholder="e.g. ServEasy" required>
                                <small class="text-muted">Displayed on the Razorpay customer checkout popup</small>
                            </div>
                        </div>

                        <!-- Key ID Field -->
                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-gray-800 small text-uppercase">
                                Razorpay Key ID <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-white"><i class="fas fa-id-badge text-muted"></i></span>
                                </div>
                                <input type="text" id="gatewayKeyId" name="key_id" class="form-control font-weight-bold font-monospace" value="<?= htmlspecialchars($currentCfg['key_id'] ?? '') ?>" placeholder="e.g. rzp_test_xxxxxxxxxx" required autocomplete="off">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyFieldValue('gatewayKeyId')" title="Copy Key ID">
                                        <i class="far fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted mt-1 d-block" id="keyIdHint">
                                <?= $activeEnv === 'TEST' ? 'TEST Key ID starts with <code>rzp_test_...</code>' : 'LIVE Key ID starts with <code>rzp_live_...</code>' ?>
                            </small>
                        </div>

                        <!-- Key Secret Field -->
                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-gray-800 small text-uppercase">
                                Razorpay Key Secret <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-white"><i class="fas fa-lock text-muted"></i></span>
                                </div>
                                <input type="password" id="gatewayKeySecret" name="key_secret" class="form-control font-monospace" value="<?= htmlspecialchars($currentCfg['key_secret'] ?? '') ?>" placeholder="Enter Razorpay Secret Key" required autocomplete="off">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="toggleSecretVisibility('gatewayKeySecret', this)" title="Show/Hide Secret">
                                        <i class="far fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyFieldValue('gatewayKeySecret')" title="Copy Secret">
                                        <i class="far fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted mt-1 d-block">Generated along with your Key ID in Razorpay Dashboard &rarr; Settings &rarr; API Keys</small>
                        </div>

                        <!-- Webhook Secret Field -->
                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-gray-800 small text-uppercase">
                                Webhook Secret (Optional / Recommended)
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-white"><i class="fas fa-broadcast-tower text-muted"></i></span>
                                </div>
                                <input type="password" id="gatewayWebhookSecret" name="webhook_secret" class="form-control font-monospace" value="<?= htmlspecialchars($currentCfg['webhook_secret'] ?? '') ?>" placeholder="Secret used to sign webhook payloads" autocomplete="off">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="toggleSecretVisibility('gatewayWebhookSecret', this)" title="Show/Hide">
                                        <i class="far fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyFieldValue('gatewayWebhookSecret')" title="Copy">
                                        <i class="far fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted mt-1 d-block">Used by <code>backend/api/payment-webhook.php</code> to verify signature</small>
                        </div>

                        <!-- Toggles & Parameters Row -->
                        <div class="row p-3 mb-4 rounded border bg-light mx-0" style="gap: 16px;">
                            <div class="col-md-5 px-0">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="switchGatewayActive" name="is_active" value="1" <?= !empty($currentCfg['is_active']) ? 'checked' : '' ?>>
                                    <label class="custom-control-label font-weight-bold text-gray-800" for="switchGatewayActive">
                                        Enable Payment Gateway
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-1">If disabled, customer payments via Razorpay will be paused</small>
                            </div>
                            <div class="col-md-6 px-0">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="switchAutoCapture" name="auto_capture" value="1" <?= !empty($currentCfg['auto_capture']) ? 'checked' : '' ?>>
                                    <label class="custom-control-label font-weight-bold text-gray-800" for="switchAutoCapture">
                                        Auto-Capture Payments
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-1">Instantly settle authorized payments without manual review</small>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap: 12px;">
                            <button type="button" class="btn btn-outline-primary font-weight-bold px-4 py-2" id="btnTestConnection" onclick="testGatewayConnection()" style="border-radius: 8px;">
                                <i class="fas fa-plug mr-2"></i> Test API Connection
                            </button>
                            <button type="submit" class="btn btn-emerald font-weight-bold px-4 py-2 shadow-sm text-white" id="btnSaveGateway" style="border-radius: 8px; background: #05c279;">
                                <i class="fas fa-save mr-2"></i> Save & Apply Credentials
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar Helper Column -->
        <div class="col-xl-4 col-lg-5">
            <!-- Webhook Integration Card -->
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 14px;">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 font-weight-bold text-gray-800">
                        <i class="fas fa-satellite-dish mr-2 text-info"></i> Webhook Endpoint URL
                    </h6>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-2">
                        Add this URL in your Razorpay Dashboard (<strong>Settings &rarr; Webhooks &rarr; Add New Webhook</strong>) to receive real-time payment notifications:
                    </p>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control form-control-sm font-monospace bg-light" id="webhookUrlInput" value="<?= htmlspecialchars($webhookUrl) ?>" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-sm btn-outline-secondary" type="button" onclick="copyFieldValue('webhookUrlInput')" title="Copy URL">
                                <i class="far fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="small font-weight-bold text-gray-800 mb-1">Recommended Webhook Events:</div>
                    <ul class="text-muted small pl-3 mb-0">
                        <li><code>payment.captured</code> &mdash; Booking payment confirmation</li>
                        <li><code>payment.failed</code> &mdash; Failed transaction alert</li>
                        <li><code>order.paid</code> &mdash; Order settlement completed</li>
                        <li><code>refund.processed</code> &mdash; Customer refund tracking</li>
                    </ul>
                </div>
            </div>

            <!-- Quick Instructions Card -->
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 14px; background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);">
                <div class="card-body p-4">
                    <h6 class="font-weight-bold text-gray-800 mb-3">
                        <i class="fas fa-info-circle mr-2 text-warning"></i> How to get Razorpay Keys
                    </h6>
                    <ol class="text-muted small pl-3 mb-3" style="line-height: 1.7;">
                        <li>Log in to <a href="https://dashboard.razorpay.com/" target="_blank" class="font-weight-bold">dashboard.razorpay.com</a>.</li>
                        <li>Toggle between <strong>Test Mode</strong> and <strong>Live Mode</strong> in the top header.</li>
                        <li>Navigate to <strong>Account & Settings &rarr; API Keys</strong>.</li>
                        <li>Click <strong>Generate Key</strong> to retrieve your <strong>Key ID</strong> and <strong>Key Secret</strong>.</li>
                        <li>Paste them here and click <strong>Test API Connection</strong> before saving.</li>
                    </ol>
                    <div class="p-2 rounded bg-white border small text-muted">
                        <i class="fas fa-lock mr-1 text-success"></i> <strong>Zero Exposure</strong>: API credentials are stored in database-qualified tables with strict Super Admin access limits.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Store configurations for TEST and LIVE modes in memory for zero-lag tab switching
    var gatewayConfigs = <?= json_encode($configs) ?>;

    function switchGatewayEnv(env) {
        $('#gatewayEnvironment').val(env);
        if (env === 'TEST') {
            $('#btnEnvTest').removeClass('btn-light border').addClass('btn-primary');
            $('#btnEnvLive').removeClass('btn-success').addClass('btn-light border');
            $('#keyIdHint').html('TEST Key ID starts with <code>rzp_test_...</code>');
        } else {
            $('#btnEnvLive').removeClass('btn-light border').addClass('btn-success');
            $('#btnEnvTest').removeClass('btn-primary').addClass('btn-light border');
            $('#keyIdHint').html('LIVE Key ID starts with <code>rzp_live_...</code>');
        }

        var cfg = gatewayConfigs[env] || {
            key_id: '',
            key_secret: '',
            webhook_secret: '',
            merchant_name: 'ServEasy Marketplace',
            is_active: 1,
            auto_capture: 1
        };

        $('#gatewayKeyId').val(cfg.key_id || '');
        $('#gatewayKeySecret').val(cfg.key_secret || '');
        $('#gatewayWebhookSecret').val(cfg.webhook_secret || '');
        $('#merchantName').val(cfg.merchant_name || 'ServEasy Marketplace');
        $('#switchGatewayActive').prop('checked', parseInt(cfg.is_active || 1) === 1);
        $('#switchAutoCapture').prop('checked', parseInt(cfg.auto_capture || 1) === 1);
    }

    function toggleSecretVisibility(inputId, btn) {
        var el = document.getElementById(inputId);
        if (!el) return;
        var icon = btn.querySelector('i');
        if (el.type === 'password') {
            el.type = 'text';
            if (icon) {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        } else {
            el.type = 'password';
            if (icon) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    }

    function copyFieldValue(inputId) {
        var el = document.getElementById(inputId);
        if (!el) return;
        var prevType = el.type;
        if (prevType === 'password') el.type = 'text';
        el.select();
        el.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(el.value).then(function() {
            if (typeof Notify !== 'undefined' && Notify.success) {
                Notify.success('Copied to clipboard!');
            } else {
                alert('Copied to clipboard!');
            }
        });
        if (prevType === 'password') el.type = 'password';
    }

    function testGatewayConnection() {
        var kid = $('#gatewayKeyId').val().trim();
        var ksec = $('#gatewayKeySecret').val().trim();
        if (!kid || !ksec) {
            if (typeof Notify !== 'undefined' && Notify.warning) {
                Notify.warning('Please enter both Key ID and Key Secret to test.');
            } else {
                alert('Please enter both Key ID and Key Secret to test.');
            }
            return;
        }

        var btn = $('#btnTestConnection');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Connecting to Razorpay...');

        $.post('superadmin-dashboard.php', {
            ajax_action: 'fetch_list',
            type: 'payment_gateway',
            gateway_action: 'test_connection',
            key_id: kid,
            key_secret: ksec
        }, function(res) {
            btn.prop('disabled', false).html('<i class="fas fa-plug mr-2"></i> Test API Connection');
            if (res.status === 'success') {
                if (typeof Notify !== 'undefined' && Notify.success) {
                    Notify.success(res.message, { title: 'Razorpay Connected' });
                } else {
                    alert(res.message);
                }
            } else {
                if (typeof Notify !== 'undefined' && Notify.error) {
                    Notify.error(res.message, { title: 'Connection Test Failed' });
                } else {
                    alert(res.message);
                }
            }
        }, 'json').fail(function() {
            btn.prop('disabled', false).html('<i class="fas fa-plug mr-2"></i> Test API Connection');
            if (typeof Notify !== 'undefined' && Notify.error) {
                Notify.error('Network error during connection test.');
            }
        });
    }

    function submitGatewayConfig(e) {
        e.preventDefault();
        var form = $('#formPaymentGateway');
        var btn = $('#btnSaveGateway');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Saving...');

        var data = form.serializeArray();
        data.push({ name: 'ajax_action', value: 'fetch_list' });
        data.push({ name: 'type', value: 'payment_gateway' });
        data.push({ name: 'gateway_action', value: 'save_gateway_config' });

        $.post('superadmin-dashboard.php', data, function(res) {
            btn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Save & Apply Credentials');
            if (res.status === 'success') {
                if (typeof Notify !== 'undefined' && Notify.success) {
                    Notify.success(res.message, { title: 'Settings Saved' });
                } else {
                    alert(res.message);
                }
                setTimeout(function() {
                    loadSection('payment_gateway');
                }, 800);
            } else {
                if (typeof Notify !== 'undefined' && Notify.error) {
                    Notify.error(res.message, { title: 'Save Failed' });
                } else {
                    alert(res.message);
                }
            }
        }, 'json').fail(function() {
            btn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Save & Apply Credentials');
            if (typeof Notify !== 'undefined' && Notify.error) {
                Notify.error('Network error while saving gateway settings.');
            }
        });
    }
</script>

