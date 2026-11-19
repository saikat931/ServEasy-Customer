<?php
/**
 * ServEasy - Super Admin Dynamic Live Domain & Base URL Configuration View
 * Strictly restricted to Super Administrators
 * Complies with ServEasy Master Dark Glassmorphic Design System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
    echo '<div class="card shadow-sm border-0 m-4" style="background: rgba(18, 20, 29, 0.85); border-radius: 18px; border: 1px solid rgba(244, 63, 94, 0.3);"><div class="card-body text-center py-5 text-danger font-weight-bold"><i class="fas fa-lock fa-3x mb-3 d-block"></i>Access Denied: Live Domain Configuration is strictly restricted to Super Administrators.</div></div>';
    exit();
}

require_once __DIR__ . '/../../../backend/app/services/DomainConfigService.php';
use App\Services\DomainConfigService;

// Handle AJAX actions if called directly
if (isset($_POST['domain_action'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['domain_action'];
    $adminName = $_SESSION['standard_admin']['username'] ?? $_SESSION['superadmin']['username'] ?? 'Super Admin';

    if ($action === 'save_domain_config') {
        try {
            $updated = DomainConfigService::updateConfig($_POST, $adminName);
            echo json_encode([
                'status' => 'success',
                'message' => 'Live Domain & Base URL successfully updated and applied platform-wide!',
                'data' => $updated
            ]);
        } catch (\Throwable $e) {
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit();
    }
}

$config = DomainConfigService::getConfig();
$endpoints = DomainConfigService::getAllEndpoints();
$activeDomain = htmlspecialchars($config['domain_url']);
$activeApiPath = htmlspecialchars($config['api_path']);
$activeEnv = htmlspecialchars($config['environment']);
$updatedBy = htmlspecialchars($config['updated_by'] ?? 'System');
$updatedAt = htmlspecialchars($config['updated_at'] ?? 'Never');
?>

<style>
/* ─── Domain Manager Dark Glassmorphic Theme Overrides ─── */
.dm-glass-card {
    background: rgba(15, 17, 26, 0.82) !important;
    backdrop-filter: blur(16px) saturate(120%);
    -webkit-backdrop-filter: blur(16px) saturate(120%);
    border: 1px solid rgba(255, 255, 255, 0.09) !important;
    border-radius: 18px !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.55), inset 0 1px 0 rgba(255, 255, 255, 0.08);
}
.dm-card-header {
    background: rgba(255, 255, 255, 0.03) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-top-left-radius: 18px !important;
    border-top-right-radius: 18px !important;
    padding: 16px 22px !important;
}
.dm-input {
    background: rgba(10, 14, 24, 0.75) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    border-radius: 12px !important;
    color: #f8fafc !important;
    font-size: 0.95rem !important;
    height: 48px !important;
    padding: 10px 16px !important;
    transition: all 0.2s ease;
}
.dm-input:focus {
    background: rgba(15, 22, 38, 0.95) !important;
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25) !important;
    color: #ffffff !important;
}
.dm-select {
    background: #0f172a !important;
    border: 1px solid rgba(255, 255, 255, 0.14) !important;
    border-radius: 12px !important;
    color: #f8fafc !important;
    height: 48px !important;
    padding: 8px 14px !important;
    line-height: 1.5 !important;
    font-size: 0.95rem !important;
    font-weight: 500 !important;
}
.dm-select option {
    background-color: #0f172a !important;
    color: #f8fafc !important;
    padding: 10px !important;
}
.dm-code-pill {
    background: rgba(56, 189, 248, 0.08) !important;
    border: 1px solid rgba(56, 189, 248, 0.2) !important;
    border-radius: 8px !important;
    color: #38bdf8 !important;
    font-family: 'JetBrains Mono', 'Fira Code', monospace !important;
    font-size: 0.82rem !important;
    padding: 4px 10px !important;
    display: inline-block;
    max-width: 85%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.dm-preset-btn {
    background: rgba(255, 255, 255, 0.035);
    border: 1px solid rgba(255, 255, 255, 0.10);
    border-radius: 10px;
    color: #cbd5e1;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 8px 14px;
    transition: all 0.2s ease;
    cursor: pointer;
}
.dm-preset-btn:hover {
    background: rgba(59, 130, 246, 0.15);
    border-color: rgba(59, 130, 246, 0.4);
    color: #60a5fa;
    transform: translateY(-1px);
}
.dm-btn-glow {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
    border: none !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.35) !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    height: 48px !important;
    padding: 0 24px !important;
    transition: all 0.2s ease;
}
.dm-btn-glow:hover {
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5) !important;
    transform: translateY(-1px);
    color: #ffffff !important;
}
.dm-btn-ping {
    background: rgba(14, 165, 233, 0.12) !important;
    border: 1px solid rgba(14, 165, 233, 0.35) !important;
    border-radius: 12px !important;
    color: #38bdf8 !important;
    font-weight: 600 !important;
    height: 48px !important;
    padding: 0 20px !important;
    transition: all 0.2s ease;
}
.dm-btn-ping:hover {
    background: rgba(14, 165, 233, 0.25) !important;
    border-color: #38bdf8 !important;
    color: #ffffff !important;
}
.dm-copy-btn {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.10);
    border-radius: 6px;
    color: #94a3b8;
    padding: 4px 8px;
    transition: all 0.15s ease;
}
.dm-copy-btn:hover {
    background: rgba(59, 130, 246, 0.2);
    border-color: #3b82f6;
    color: #ffffff;
}
.dm-list-group-item {
    background: transparent !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
    padding: 12px 18px !important;
}
</style>

<div class="container-fluid px-0">

    <!-- Header Banner -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 text-white font-weight-bold d-flex align-items-center">
                <span class="mr-2" style="color: #38bdf8;"><i class="fas fa-globe"></i></span>
                Live Domain & Deployment Manager
            </h1>
            <p class="small mb-0" style="color: #94a3b8;">
                Centrally configure the live production domain or temporary host. When updated, all 22 API endpoints, web portals, mobile apps, and webhook listeners synchronize automatically.
            </p>
        </div>
        <div class="d-flex align-items-center mt-3 mt-sm-0" style="gap: 10px;">
            <div class="px-3 py-2 font-weight-bold d-flex align-items-center" style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.35); border-radius: 12px; color: #10b981; font-size: 0.85rem;">
                <span class="badge badge-success mr-2 p-1" style="border-radius: 50%; width: 8px; height: 8px; display: inline-block;"></span>
                <span>Active: <strong id="topActiveBadge" style="color: #ffffff;"><?= $activeDomain ?></strong></span>
            </div>
            <button type="button" class="btn btn-sm dm-preset-btn" onclick="loadSection('domain_config')" title="Refresh View">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <!-- Glowing Informational Card -->
    <div class="dm-glass-card p-3 mb-4 d-flex align-items-center justify-content-between flex-wrap" style="gap: 14px; border-left: 4px solid #38bdf8 !important;">
        <div class="d-flex align-items-center">
            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 44px; height: 44px; background: rgba(56, 189, 248, 0.15); color: #38bdf8; font-size: 1.25rem;">
                <i class="fas fa-satellite-dish"></i>
            </div>
            <div>
                <h6 class="font-weight-bold text-white mb-0">Dynamic URL Orchestration Engine</h6>
                <small style="color: #94a3b8;">
                    Enter your live temporary or custom domain below and click <strong>"Save & Apply Live Domain"</strong>. The system immediately updates the database, syncs <code>backend/.env</code>, and realigns all service endpoints with zero code modifications required.
                </small>
            </div>
        </div>
        <div>
            <span class="badge badge-pill font-weight-bold px-3 py-2" style="background: rgba(139, 92, 246, 0.18); border: 1px solid rgba(139, 92, 246, 0.4); color: #c4b5fd; font-size: 0.8rem;">
                <i class="fas fa-shield-alt mr-1"></i> Multi-Probability Protected
            </span>
        </div>
    </div>

    <div class="row">
        <!-- Configuration Form Column -->
        <div class="col-xl-7 col-lg-7 mb-4">
            <div class="dm-glass-card h-100 d-flex flex-column">
                <div class="dm-card-header d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-white d-flex align-items-center">
                        <i class="fas fa-sliders-h mr-2" style="color: #38bdf8;"></i> Host & Path Settings
                    </h6>
                    <span class="badge font-weight-bold" style="background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.35); color: #60a5fa; font-size: 0.75rem; padding: 4px 10px; border-radius: 8px;">
                        Super Admin Access
                    </span>
                </div>
                <div class="card-body p-4 flex-grow-1">
                    <form id="domainConfigForm">
                        <!-- Live Domain Input -->
                        <div class="form-group mb-3">
                            <label class="font-weight-bold mb-2 d-flex justify-content-between align-items-center" style="color: #f1f5f9;">
                                <span><i class="fas fa-link mr-1" style="color: #38bdf8;"></i> Live Domain / Host Base URL:</span>
                                <span class="badge" style="background: rgba(255, 255, 255, 0.06); color: #94a3b8; font-size: 0.7rem;">Required</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text font-weight-bold" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.12); border-right: none; color: #38bdf8; border-top-left-radius: 12px; border-bottom-left-radius: 12px;">
                                        URL
                                    </span>
                                </div>
                                <input type="url" class="form-control dm-input font-weight-bold" id="domain_url" name="domain_url"
                                       value="<?= $activeDomain ?>" placeholder="https://yourdomain.com" required
                                       style="border-top-left-radius: 0 !important; border-bottom-left-radius: 0 !important; font-size: 1.05rem; color: #38bdf8 !important;">
                            </div>
                            <small class="form-text mt-2" style="color: #94a3b8;">
                                E.g. <span style="color: #38bdf8; cursor: pointer;" onclick="$('#domain_url').val('http://localhost/ServEasy').trigger('input');">http://localhost/ServEasy</span> or custom domain <span style="color: #38bdf8; cursor: pointer;" onclick="$('#domain_url').val('https://serveasy.in').trigger('input');">https://serveasy.in</span> (protocol included).
                            </small>
                        </div>

                        <!-- API Subpath -->
                        <div class="form-group mb-3">
                            <label class="font-weight-bold mb-2" style="color: #f1f5f9;">
                                <i class="fas fa-code mr-1" style="color: #a78bfa;"></i> API Sub-Path:
                            </label>
                            <input type="text" class="form-control dm-input" id="api_path" name="api_path"
                                   value="<?= $activeApiPath ?>" placeholder="/api" required>
                            <small class="form-text mt-2" style="color: #94a3b8;">
                                Use <code style="color: #38bdf8; background: rgba(56, 189, 248, 0.08); padding: 2px 6px; border-radius: 4px;">/api</code> when backend files are directly in <code>public_html</code>. If inside a subfolder, use <code style="color: #38bdf8; background: rgba(56, 189, 248, 0.08); padding: 2px 6px; border-radius: 4px;">/ServEasy/backend/api</code>.
                            </small>
                        </div>

                        <!-- Environment & Sync Options -->
                        <div class="row mb-3 align-items-center">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="font-weight-bold mb-2" style="color: #f1f5f9;">
                                    <i class="fas fa-layer-group mr-1" style="color: #f59e0b;"></i> Target Environment:
                                </label>
                                <select class="form-control dm-select" id="environment" name="environment">
                                    <option value="production" <?= $activeEnv === 'production' ? 'selected' : '' ?>>Production (Live)</option>
                                    <option value="staging" <?= $activeEnv === 'staging' ? 'selected' : '' ?>>Staging / Test</option>
                                    <option value="development" <?= $activeEnv === 'development' ? 'selected' : '' ?>>Local Development</option>
                                </select>
                            </div>
                            <div class="col-md-6 pt-md-4">
                                <div class="custom-control custom-checkbox p-2" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 10px;">
                                    <input type="checkbox" class="custom-control-input" id="sync_env" name="sync_env" value="1" checked>
                                    <label class="custom-control-label font-weight-bold" for="sync_env" style="color: #f8fafc; font-size: 0.88rem; cursor: pointer;">
                                        Auto-Sync to <code>backend/.env</code>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Presets -->
                        <div class="mb-4 pt-2">
                            <label class="small font-weight-bold d-block mb-2" style="color: #64748b; letter-spacing: 0.5px;">QUICK ONE-CLICK PRESETS:</label>
                            <div class="d-flex flex-wrap" style="gap: 10px;">
                                <button type="button" class="dm-preset-btn d-flex align-items-center" onclick="applyPreset('http://localhost/ServEasy', '/backend/api', 'development')">
                                    <i class="fas fa-laptop-code mr-2 text-warning"></i> Local XAMPP Preset
                                </button>
                                <button type="button" class="dm-preset-btn d-flex align-items-center" onclick="applyPreset('https://yourdomain.com', '/api', 'production')">
                                    <i class="fas fa-cloud mr-2 text-info"></i> Live Production Preset
                                </button>
                            </div>
                        </div>

                        <hr style="border-color: rgba(255, 255, 255, 0.08);">

                        <!-- Action Buttons -->
                        <div class="d-flex flex-wrap align-items-center justify-content-between pt-2" style="gap: 12px;">
                            <button type="button" class="btn dm-btn-ping d-flex align-items-center" id="btnPingDomain" onclick="pingLiveDomain()">
                                <i class="fas fa-satellite-dish mr-2"></i> Test Connection (Ping)
                            </button>
                            <button type="submit" class="btn dm-btn-glow d-flex align-items-center" id="btnSaveDomain">
                                <i class="fas fa-save mr-2"></i> Save & Apply Live Domain
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Footer Metadata -->
                <div class="p-3 d-flex justify-content-between align-items-center small" style="background: rgba(0, 0, 0, 0.25); border-top: 1px solid rgba(255, 255, 255, 0.05); color: #64748b; border-bottom-left-radius: 18px; border-bottom-right-radius: 18px;">
                    <span><i class="fas fa-user-edit mr-1 text-info"></i> Updated by: <strong class="text-white"><?= $updatedBy ?></strong></span>
                    <span><i class="far fa-clock mr-1 text-info"></i> <?= $updatedAt ?></span>
                </div>
            </div>

            <!-- Ping Result Card -->
            <div class="dm-glass-card mt-3 p-3" id="pingResultCard" style="display: none; border-left: 4px solid #10b981 !important;">
                <div class="d-flex align-items-center justify-content-between flex-wrap" id="pingResultContent" style="gap: 10px;">
                    <!-- Dynamically injected -->
                </div>
            </div>
        </div>

        <!-- Calculated Live Endpoints Column -->
        <div class="col-xl-5 col-lg-5 mb-4">
            <div class="dm-glass-card mb-4">
                <div class="dm-card-header d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-white d-flex align-items-center">
                        <i class="fas fa-network-wired mr-2" style="color: #10b981;"></i> Calculated Live Endpoints
                    </h6>
                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #34d399; font-size: 0.72rem; border-radius: 6px;">
                        Real-Time
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" style="max-height: 440px; overflow-y: auto;">
                        <div class="dm-list-group-item" style="background: rgba(255, 255, 255, 0.02) !important;">
                            <span class="small font-weight-bold text-uppercase" style="color: #64748b; letter-spacing: 0.5px;">API SERVICES</span>
                        </div>
                        <div class="dm-list-group-item">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold small text-white">API Base URL:</span>
                                <button class="dm-copy-btn" onclick="copyToClipboard('#lbl_api_base')" title="Copy URL">
                                    <i class="far fa-copy mr-1"></i>Copy
                                </button>
                            </div>
                            <code class="dm-code-pill w-100" id="lbl_api_base"><?= $endpoints['api_base'] ?></code>
                        </div>
                        <div class="dm-list-group-item">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold small text-white">Categories API:</span>
                                <button class="dm-copy-btn" onclick="copyToClipboard('#lbl_cat_api')">
                                    <i class="far fa-copy mr-1"></i>Copy
                                </button>
                            </div>
                            <code class="dm-code-pill w-100" id="lbl_cat_api"><?= $endpoints['api_services']['categories'] ?></code>
                        </div>
                        <div class="dm-list-group-item">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold small text-white">Bookings API:</span>
                                <button class="dm-copy-btn" onclick="copyToClipboard('#lbl_book_api')">
                                    <i class="far fa-copy mr-1"></i>Copy
                                </button>
                            </div>
                            <code class="dm-code-pill w-100" id="lbl_book_api"><?= $endpoints['api_services']['bookings'] ?></code>
                        </div>
                        <div class="dm-list-group-item">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold small" style="color: #34d399;">Razorpay Webhook:</span>
                                <button class="dm-copy-btn" onclick="copyToClipboard('#lbl_webhook_api')">
                                    <i class="far fa-copy mr-1"></i>Copy
                                </button>
                            </div>
                            <code class="dm-code-pill w-100" id="lbl_webhook_api" style="color: #34d399 !important; border-color: rgba(16, 185, 129, 0.25) !important; background: rgba(16, 185, 129, 0.08) !important;"><?= $endpoints['api_services']['payment_webhook'] ?></code>
                        </div>

                        <div class="dm-list-group-item" style="background: rgba(255, 255, 255, 0.02) !important;">
                            <span class="small font-weight-bold text-uppercase" style="color: #64748b; letter-spacing: 0.5px;">WEB PORTALS</span>
                        </div>
                        <div class="dm-list-group-item">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold small text-white">Customer Web:</span>
                                <div>
                                    <button class="dm-copy-btn mr-1" onclick="copyToClipboard('#lbl_web_portal')">
                                        <i class="far fa-copy"></i>
                                    </button>
                                    <a href="<?= $endpoints['web_portals']['customer_web'] ?>" target="_blank" class="dm-copy-btn text-info" title="Open in new tab">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </div>
                            </div>
                            <code class="dm-code-pill w-100" id="lbl_web_portal"><?= $endpoints['web_portals']['customer_web'] ?></code>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile App EAS Cloud Integration Card -->
            <div class="dm-glass-card p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="m-0 font-weight-bold text-white d-flex align-items-center">
                        <i class="fas fa-mobile-alt mr-2" style="color: #a78bfa;"></i> Mobile App EAS Setting
                    </h6>
                    <span class="badge" style="background: rgba(139, 92, 246, 0.15); border: 1px solid rgba(139, 92, 246, 0.35); color: #c4b5fd; font-size: 0.72rem; border-radius: 6px;">
                        Expo Cloud
                    </span>
                </div>
                <p class="small mb-2" style="color: #94a3b8;">
                    Copy into <a href="https://expo.dev" target="_blank" class="text-info font-weight-bold">expo.dev</a> -> Environment Variables or compile into standalone APK:
                </p>
                <div class="p-3 rounded mb-3 position-relative" style="background: rgba(0, 0, 0, 0.45); border: 1px solid rgba(255, 255, 255, 0.08); font-family: monospace; font-size: 0.85rem; color: #34d399;">
                    EXPO_PUBLIC_API_URL=<span id="snippetApiUrl" style="color: #ffffff;"><?= $endpoints['api_base'] ?></span>
                </div>
                <button class="btn btn-sm btn-block dm-preset-btn font-weight-bold" onclick="copyToClipboard('#snippetApiUrl')">
                    <i class="far fa-copy mr-1 text-info"></i> Copy Mobile API Variable
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Live update endpoint preview as user types
    $('#domain_url, #api_path').on('input', function() {
        var domain = ($('#domain_url').val() || '').trim().replace(/\/+$/, '');
        var apiPath = ($('#api_path').val() || '').trim().replace(/^\/+|\/+$/g, '');
        var fullApi = domain + (apiPath ? '/' + apiPath : '');

        $('#lbl_api_base').text(fullApi);
        $('#lbl_cat_api').text(fullApi + '/categories.php');
        $('#lbl_book_api').text(fullApi + '/bookings.php');
        $('#lbl_webhook_api').text(fullApi + '/payment-webhook.php');
        $('#lbl_web_portal').text(domain + '/Frontend/Web/index.php');
        $('#snippetApiUrl').text(fullApi);
    });

    // Apply quick presets
    function applyPreset(domain, apiPath, env) {
        $('#domain_url').val(domain);
        $('#api_path').val(apiPath);
        $('#environment').val(env);
        $('#domain_url').trigger('input');
    }

    // Ping / Test connection with visual latency metric
    function pingLiveDomain() {
        var domain = ($('#domain_url').val() || '').trim().replace(/\/+$/, '');
        var apiPath = ($('#api_path').val() || '').trim().replace(/^\/+|\/+$/g, '');
        var testUrl = domain + (apiPath ? '/' + apiPath : '') + '/categories.php';

        var $btn = $('#btnPingDomain');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Pinging...');

        $('#pingResultCard').hide();

        $.ajax({
            url: (window.serveasyApiUrl ? window.serveasyApiUrl('platform-settings.php') : '../../backend/api/platform-settings.php'),
            type: 'GET',
            data: {
                action: 'ping_domain',
                target_url: testUrl
            },
            dataType: 'json',
            timeout: 10000,
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fas fa-satellite-dish mr-2"></i> Test Connection (Ping)');
                $('#pingResultCard').show();
                if (res.success && res.data && res.data.is_reachable) {
                    var latencyBadge = res.data.latency_ms < 500
                        ? '<span class="badge badge-success px-2 py-1"><i class="fas fa-bolt mr-1"></i> Fast (' + res.data.latency_ms + 'ms)</span>'
                        : '<span class="badge badge-warning px-2 py-1"><i class="fas fa-tachometer-alt mr-1"></i> ' + res.data.latency_ms + 'ms</span>';

                    $('#pingResultCard').css('border-left-color', '#10b981');
                    $('#pingResultContent').html(
                        '<div class="d-flex align-items-center" style="gap: 10px;">' +
                            '<span class="badge badge-success px-3 py-2 font-weight-bold" style="font-size: 0.85rem; border-radius: 8px;"><i class="fas fa-check-circle mr-1"></i> Reachable (HTTP ' + res.data.http_code + ')</span>' +
                            latencyBadge +
                        '</div>' +
                        '<div class="small text-muted font-monospace" style="color: #94a3b8 !important;">' + res.data.target_url + '</div>'
                    );
                } else {
                    var code = (res.data ? res.data.http_code : 0);
                    $('#pingResultCard').css('border-left-color', '#f59e0b');
                    $('#pingResultContent').html(
                        '<div class="d-flex align-items-center" style="gap: 10px;">' +
                            '<span class="badge badge-warning px-3 py-2 font-weight-bold" style="font-size: 0.85rem; border-radius: 8px;"><i class="fas fa-exclamation-triangle mr-1"></i> Status ' + code + '</span>' +
                            '<span style="color: #cbd5e1;">' + (res.message || 'Server reached with diagnostic notice') + '</span>' +
                        '</div>'
                    );
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-satellite-dish mr-2"></i> Test Connection (Ping)');
                $('#pingResultCard').show();
                var msg = 'Failed to connect to target domain. Check URL or internet connection.';
                try {
                    var json = JSON.parse(xhr.responseText);
                    if (json.message) msg = json.message;
                } catch(e) {}
                $('#pingResultCard').css('border-left-color', '#ef4444');
                $('#pingResultContent').html(
                    '<div class="d-flex align-items-center" style="gap: 10px;">' +
                        '<span class="badge badge-danger px-3 py-2 font-weight-bold" style="font-size: 0.85rem; border-radius: 8px;"><i class="fas fa-times-circle mr-1"></i> Unreachable</span>' +
                        '<span class="small font-weight-bold text-danger">' + msg + '</span>' +
                    '</div>'
                );
            }
        });
    }

    // Save & Apply Configuration
    $('#domainConfigForm').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#btnSaveDomain');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Applying...');

        var formData = $(this).serializeArray();
        var postData = {
            action: 'update_domain_config'
        };
        $.each(formData, function(i, field) {
            postData[field.name] = field.value;
        });

        $.ajax({
            url: (window.serveasyApiUrl ? window.serveasyApiUrl('platform-settings.php') : '../../backend/api/platform-settings.php'),
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Save & Apply Live Domain');
                if (res.success) {
                    var newDomain = ($('#domain_url').val() || '').trim();
                    $('#topActiveBadge').text(newDomain);

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Domain Applied Successfully!',
                            text: res.message || 'All endpoints and configurations are now live.',
                            background: '#0f172a',
                            color: '#ffffff',
                            confirmButtonColor: '#10b981'
                        }).then(function() {
                            loadSection('domain_config');
                        });
                    } else {
                        alert(res.message || 'Domain saved successfully!');
                        loadSection('domain_config');
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Configuration Error',
                            text: res.message || 'Failed to update domain.',
                            background: '#0f172a',
                            color: '#ffffff'
                        });
                    } else {
                        alert(res.message || 'Failed to update domain.');
                    }
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Save & Apply Live Domain');
                var msg = 'Failed to update domain configuration.';
                try {
                    var json = JSON.parse(xhr.responseText);
                    if (json.message) msg = json.message;
                } catch(e) {}
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: msg,
                        background: '#0f172a',
                        color: '#ffffff'
                    });
                } else {
                    alert(msg);
                }
            }
        });
    });

    // Copy to clipboard helper
    function copyToClipboard(selector) {
        var text = $(selector).text().trim();
        if (!text) return;
        navigator.clipboard.writeText(text).then(function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Copied to clipboard!',
                    background: '#0f172a',
                    color: '#ffffff',
                    showConfirmButton: false,
                    timer: 1500
                });
            } else {
                alert('Copied: ' + text);
            }
        });
    }
</script>
