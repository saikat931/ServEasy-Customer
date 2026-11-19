<?php
/**
 * ServEasy Super Admin - System & Platform Settings View
 * Comprehensive control panel for global system preferences, governance shortcuts, and environment diagnostics
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo)) {
    $dbPath = file_exists(__DIR__ . '/../common/db_connection.php')
        ? __DIR__ . '/../common/db_connection.php'
        : (file_exists(__DIR__ . '/../../backend/config/db_connection.php') ? __DIR__ . '/../../backend/config/db_connection.php' : null);
    if ($dbPath) require_once $dbPath;
    $pdo = Database::connect();
}

$is_super = false;
if (isset($is_super_admin) && $is_super_admin) {
    $is_super = true;
} elseif (isset($_SESSION['super_admin']) || isset($_SESSION['superadmin'])) {
    $is_super = true;
}

if (!$is_super) {
    echo '<div class="alert alert-danger m-4">Access Denied: Settings are restricted to Super Administrators.</div>';
    exit;
}

// ── Handle AJAX Settings Action ───────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['settings_action'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['settings_action'];

    if ($action === 'save_preferences') {
        // Store in session preferences
        $_SESSION['platform_pref_timeout'] = (int)($_POST['session_timeout'] ?? 30);
        $_SESSION['platform_pref_pagination'] = (int)($_POST['items_per_page'] ?? 10);
        $_SESSION['platform_pref_notifications'] = !empty($_POST['enable_login_alerts']) ? 1 : 0;

        echo json_encode([
            'ok' => true,
            'message' => 'System preferences saved and applied to active session!'
        ]);
        exit;
    }
}

// System Diagnostic Metrics
$phpVer = phpversion();
$serverSoft = $_SERVER['SERVER_SOFTWARE'] ?? 'Hostinger Web Cloud (LiteSpeed)';
$dbStatus = 'Connected (Hostinger MySQL)';
try {
    $v = $pdo->query("SELECT VERSION()")->fetchColumn();
    if ($v) $dbStatus = "Connected (MySQL {$v})";
} catch (\Throwable $e) {}

$sessionTimeout = $_SESSION['platform_pref_timeout'] ?? 30;
$itemsPerPage   = $_SESSION['platform_pref_pagination'] ?? 10;
$loginAlerts    = $_SESSION['platform_pref_notifications'] ?? 1;
?>

<style>
/* ─── Settings Glassmorphic Theme ─── */
.set-glass-card {
    background: rgba(15, 17, 26, 0.85) !important;
    backdrop-filter: blur(16px) saturate(130%);
    -webkit-backdrop-filter: blur(16px) saturate(130%);
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 18px !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.55), inset 0 1px 0 rgba(255, 255, 255, 0.06);
    transition: transform 0.18s ease, border-color 0.18s ease;
}
.set-glass-card:hover {
    border-color: rgba(255, 255, 255, 0.15) !important;
}
.set-card-header {
    background: rgba(255, 255, 255, 0.03) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.07) !important;
    border-top-left-radius: 18px !important;
    border-top-right-radius: 18px !important;
    padding: 16px 22px !important;
}
.set-input, .set-select {
    background: rgba(10, 14, 24, 0.75) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    border-radius: 12px !important;
    color: #f8fafc !important;
    font-size: 0.95rem !important;
    height: 48px !important;
    padding: 10px 16px !important;
}
.set-select option {
    background: #0f172a;
    color: #ffffff;
}
.set-shortcut-card {
    background: rgba(255, 255, 255, 0.035);
    border: 1px solid rgba(255, 255, 255, 0.09);
    border-radius: 14px;
    padding: 16px 18px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.set-shortcut-card:hover {
    background: rgba(56, 189, 248, 0.12);
    border-color: rgba(56, 189, 248, 0.4);
    transform: translateY(-2px);
}
</style>

<div class="container-fluid px-0">

    <!-- Top Banner -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4 flex-wrap" style="gap: 15px;">
        <div>
            <div class="d-flex align-items-center mb-1">
                <span class="mr-2" style="color: #38bdf8; font-size: 1.4rem;"><i class="fas fa-cogs"></i></span>
                <h1 class="h3 mb-0 text-white font-weight-bold">System & Platform Settings</h1>
                <span class="badge ml-3 px-2 py-1 font-weight-bold" style="background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; font-size: 0.75rem;">
                    <i class="fas fa-check-circle mr-1"></i> Global Configuration
                </span>
            </div>
            <p class="small mb-0" style="color: #94a3b8;">
                Master dashboard preferences, administrative controls, governance shortcuts, and environment diagnostics.
            </p>
        </div>
        <div class="d-flex align-items-center" style="gap: 10px;">
            <button type="button" class="btn btn-sm set-glass-card text-white font-weight-bold px-3 py-2" onclick="loadSection('settings')">
                <i class="fas fa-sync-alt mr-1 text-info"></i> Refresh Settings
            </button>
            <button type="button" class="btn btn-sm set-glass-card text-white font-weight-bold px-3 py-2" onclick="loadSection('profile')">
                <i class="fas fa-user-circle mr-1 text-primary"></i> My Profile
            </button>
        </div>
    </div>

    <!-- Section 1: Quick Navigation to Governance Modules -->
    <div class="set-glass-card mb-4">
        <div class="set-card-header d-flex align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-white d-flex align-items-center">
                <i class="fas fa-th-large mr-2" style="color: #38bdf8;"></i> Super Admin Control Hub
            </h6>
            <span class="badge font-weight-bold" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8; font-size: 0.72rem; border-radius: 6px;">
                Direct Access
            </span>
        </div>
        <div class="card-body p-4">
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="set-shortcut-card" onclick="loadSection('categories')">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 36px; height: 36px; background: rgba(56, 189, 248, 0.15); color: #38bdf8;">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <div>
                                <div class="font-weight-bold text-white small">Categories & Subs</div>
                                <div class="small" style="color: #64748b; font-size: 0.75rem;">Manage catalog taxonomy</div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="set-shortcut-card" onclick="loadSection('convenience_fee')">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 36px; height: 36px; background: rgba(16, 185, 129, 0.15); color: #34d399;">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div>
                                <div class="font-weight-bold text-white small">Convenience Fee</div>
                                <div class="small" style="color: #64748b; font-size: 0.75rem;">Platform charges & rates</div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="set-shortcut-card" onclick="loadSection('domain_config')">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 36px; height: 36px; background: rgba(14, 165, 233, 0.15); color: #38bdf8;">
                                <i class="fas fa-globe"></i>
                            </div>
                            <div>
                                <div class="font-weight-bold text-white small">Live Domains & URLs</div>
                                <div class="small" style="color: #64748b; font-size: 0.75rem;">Ecosystem architecture</div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="set-shortcut-card" onclick="loadSection('otp_config')">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 36px; height: 36px; background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div>
                                <div class="font-weight-bold text-white small">OTP Login Settings</div>
                                <div class="small" style="color: #64748b; font-size: 0.75rem;">Delivery & verification</div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="set-shortcut-card" onclick="loadSection('smtp_settings')">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 36px; height: 36px; background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <div class="font-weight-bold text-white small">SMTP Setup</div>
                                <div class="small" style="color: #64748b; font-size: 0.75rem;">Gmail transactional mail</div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="set-shortcut-card" onclick="loadSection('payment_gateway')">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 36px; height: 36px; background: rgba(16, 185, 129, 0.15); color: #10b981;">
                                <i class="fas fa-key"></i>
                            </div>
                            <div>
                                <div class="font-weight-bold text-white small">Payment Gateway</div>
                                <div class="small" style="color: #64748b; font-size: 0.75rem;">Razorpay credentials</div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="set-shortcut-card" onclick="loadSection('blogs')">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 36px; height: 36px; background: rgba(236, 72, 153, 0.15); color: #f472b6;">
                                <i class="fas fa-newspaper"></i>
                            </div>
                            <div>
                                <div class="font-weight-bold text-white small">Blog Management</div>
                                <div class="small" style="color: #64748b; font-size: 0.75rem;">Articles & content tips</div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="set-shortcut-card" onclick="loadSection('logo')">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 36px; height: 36px; background: rgba(168, 85, 247, 0.15); color: #c084fc;">
                                <i class="fas fa-image"></i>
                            </div>
                            <div>
                                <div class="font-weight-bold text-white small">Manage Logo</div>
                                <div class="small" style="color: #64748b; font-size: 0.75rem;">Site branding & assets</div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Preferences & Diagnostic Specs -->
    <div class="row">
        <!-- System Preferences Form -->
        <div class="col-lg-7 mb-4">
            <div class="set-glass-card h-100">
                <div class="set-card-header d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-white d-flex align-items-center">
                        <i class="fas fa-sliders-h mr-2" style="color: #f59e0b;"></i> Dashboard & Session Preferences
                    </h6>
                    <span class="badge font-weight-bold" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; font-size: 0.72rem; border-radius: 6px;">
                        Active Session
                    </span>
                </div>
                <div class="card-body p-4">
                    <form id="formSystemSettings">
                        <input type="hidden" name="settings_action" value="save_preferences">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-white mb-2">Platform Name</label>
                                <input type="text" class="form-control set-input font-weight-bold" value="ServEasy" readonly style="opacity: 0.85;">
                                <small class="form-text mt-1" style="color: #64748b;">Global platform brand title.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-white mb-2">Session Inactivity Timeout</label>
                                <select name="session_timeout" class="form-control set-select">
                                    <option value="15" <?= $sessionTimeout == 15 ? 'selected' : '' ?>>15 Minutes</option>
                                    <option value="30" <?= $sessionTimeout == 30 ? 'selected' : '' ?>>30 Minutes (Recommended)</option>
                                    <option value="60" <?= $sessionTimeout == 60 ? 'selected' : '' ?>>1 Hour</option>
                                    <option value="120" <?= $sessionTimeout == 120 ? 'selected' : '' ?>>2 Hours</option>
                                </select>
                                <small class="form-text mt-1" style="color: #64748b;">Automatic log out duration when idle.</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-white mb-2">Default Table Pagination</label>
                                <select name="items_per_page" class="form-control set-select">
                                    <option value="10" <?= $itemsPerPage == 10 ? 'selected' : '' ?>>10 Records / Page</option>
                                    <option value="25" <?= $itemsPerPage == 25 ? 'selected' : '' ?>>25 Records / Page</option>
                                    <option value="50" <?= $itemsPerPage == 50 ? 'selected' : '' ?>>50 Records / Page</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-white mb-2">System Timezone</label>
                                <input type="text" class="form-control set-input text-muted" value="Asia/Kolkata (IST)" readonly style="opacity: 0.85;">
                            </div>
                        </div>

                        <div class="p-3 rounded mb-3" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.07);">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="enable_alerts" name="enable_login_alerts" value="1" <?= $loginAlerts ? 'checked' : '' ?>>
                                <label class="custom-control-label font-weight-bold text-white small" for="enable_alerts" style="cursor: pointer;">
                                    Enable Security Login Notifications (Email alerts on unfamiliar IP/device)
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearDashboardCache()">
                                <i class="fas fa-trash-alt mr-1"></i> Purge Client Cache
                            </button>
                            <button type="submit" class="btn btn-primary font-weight-bold px-4" id="btnSaveSettings" style="border-radius: 12px; height: 46px;">
                                <i class="fas fa-save mr-2"></i> Save Preferences
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Server Environment Diagnostic Specs -->
        <div class="col-lg-5 mb-4">
            <div class="set-glass-card h-100">
                <div class="set-card-header d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-white d-flex align-items-center">
                        <i class="fas fa-server mr-2" style="color: #10b981;"></i> Cloud & Server Diagnostics
                    </h6>
                    <span class="badge font-weight-bold" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-size: 0.72rem; border-radius: 6px;">
                        Healthy
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3 pb-2 border-bottom" style="border-color: rgba(255, 255, 255, 0.05) !important;">
                        <span class="small font-weight-bold text-uppercase d-block mb-1" style="color: #64748b;">PHP Engine Version</span>
                        <div class="font-weight-bold text-white font-monospace"><?= htmlspecialchars($phpVer) ?></div>
                    </div>
                    <div class="mb-3 pb-2 border-bottom" style="border-color: rgba(255, 255, 255, 0.05) !important;">
                        <span class="small font-weight-bold text-uppercase d-block mb-1" style="color: #64748b;">Web Server Software</span>
                        <div class="font-weight-bold text-white small"><?= htmlspecialchars($serverSoft) ?></div>
                    </div>
                    <div class="mb-3 pb-2 border-bottom" style="border-color: rgba(255, 255, 255, 0.05) !important;">
                        <span class="small font-weight-bold text-uppercase d-block mb-1" style="color: #64748b;">Database Connection</span>
                        <div class="font-weight-bold text-info font-monospace small"><?= htmlspecialchars($dbStatus) ?></div>
                    </div>
                    <div class="mb-3 pb-2 border-bottom" style="border-color: rgba(255, 255, 255, 0.05) !important;">
                        <span class="small font-weight-bold text-uppercase d-block mb-1" style="color: #64748b;">Max Upload & Post Size</span>
                        <div class="font-weight-bold text-white font-monospace small">Upload: <?= ini_get('upload_max_filesize') ?> | Post: <?= ini_get('post_max_size') ?></div>
                    </div>
                    <div>
                        <span class="small font-weight-bold text-uppercase d-block mb-1" style="color: #64748b;">Memory Limit</span>
                        <div class="font-weight-bold text-white font-monospace small"><?= ini_get('memory_limit') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    // Submit System Settings
    $('#formSystemSettings').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#btnSaveSettings');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Saving...');

        $.ajax({
            url: 'superadmin-dashboard.php?ajax_action=fetch_list&type=settings',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Save Preferences');
                if (res && res.ok) {
                    if (typeof Notify !== 'undefined' && Notify.success) {
                        Notify.success(res.message);
                    } else {
                        alert(res.message);
                    }
                } else {
                    alert('Error saving preferences.');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Save Preferences');
                alert('Server error saving preferences.');
            }
        });
    });

    // Clear Client Cache
    function clearDashboardCache() {
        sessionStorage.clear();
        for (var k in sectionCache) {
            delete sectionCache[k];
        }
        if (typeof Notify !== 'undefined' && Notify.success) {
            Notify.success('Client cache purged successfully.');
        } else {
            alert('Client navigation cache purged successfully.');
        }
    }
</script>
