<?php
/**
 * ServEasy - Super Admin Live Domains & Infrastructure Architecture Directory
 * Strictly restricted to Super Administrators
 * Read-Only Master Directory of all deployed portals, domains, and REST APIs
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
    echo '<div class="card shadow-sm border-0 m-4" style="background: rgba(18, 20, 29, 0.85); border-radius: 18px; border: 1px solid rgba(244, 63, 94, 0.3);"><div class="card-body text-center py-5 text-danger font-weight-bold"><i class="fas fa-lock fa-3x mb-3 d-block"></i>Access Denied: Live Domain Architecture Directory is strictly restricted to Super Administrators.</div></div>';
    exit();
}

// ── Server-Side Ping / Health Check Handler (Avoids Cross-Origin CORS Issues) ──
if (isset($_GET['ajax_ping_url'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $target = filter_var($_GET['ajax_ping_url'], FILTER_VALIDATE_URL);
    if (!$target) {
        echo json_encode(['ok' => false, 'message' => 'Invalid URL']);
        exit;
    }
    $start = microtime(true);
    $ch = curl_init($target);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ServEasy-HealthCheck/1.0');
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $latency = round((microtime(true) - $start) * 1000);
    $err = curl_error($ch);
    curl_close($ch);

    echo json_encode([
        'ok' => ($code >= 200 && $code < 400),
        'code' => $code,
        'latency_ms' => $latency,
        'target_url' => $target,
        'error' => $err
    ]);
    exit();
}

$svcPath = file_exists(__DIR__ . '/../services/DomainConfigService.php')
    ? __DIR__ . '/../services/DomainConfigService.php'
    : (file_exists(__DIR__ . '/../../../backend/app/services/DomainConfigService.php') ? __DIR__ . '/../../../backend/app/services/DomainConfigService.php' : null);
if ($svcPath) require_once $svcPath;

use App\Services\DomainConfigService;

$directory = DomainConfigService::getEcosystemDirectory();
$portals = $directory['portals'];
$database = $directory['database'];
$env = $directory['environment'];
?>

<style>
/* ─── Modern Glassmorphic Dark Architecture Styles ─── */
.arch-card {
    background: rgba(15, 17, 26, 0.85) !important;
    backdrop-filter: blur(16px) saturate(130%);
    -webkit-backdrop-filter: blur(16px) saturate(130%);
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 18px !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.55), inset 0 1px 0 rgba(255, 255, 255, 0.06);
    transition: transform 0.2s ease, border-color 0.2s ease;
}
.arch-card:hover {
    border-color: rgba(255, 255, 255, 0.15) !important;
}
.arch-header {
    background: rgba(255, 255, 255, 0.03) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.07) !important;
    border-top-left-radius: 18px !important;
    border-top-right-radius: 18px !important;
    padding: 16px 22px !important;
}
.arch-code-pill {
    background: rgba(56, 189, 248, 0.08) !important;
    border: 1px solid rgba(56, 189, 248, 0.22) !important;
    border-radius: 8px !important;
    color: #38bdf8 !important;
    font-family: 'JetBrains Mono', 'Fira Code', monospace !important;
    font-size: 0.84rem !important;
    padding: 6px 12px !important;
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.arch-copy-btn, .arch-open-btn, .arch-ping-btn {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.10);
    border-radius: 8px;
    color: #94a3b8;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 6px 12px;
    transition: all 0.18s ease;
    cursor: pointer;
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.arch-copy-btn:hover {
    background: rgba(59, 130, 246, 0.2);
    border-color: #3b82f6;
    color: #ffffff;
}
.arch-open-btn:hover {
    background: rgba(16, 185, 129, 0.2);
    border-color: #10b981;
    color: #ffffff;
}
.arch-ping-btn:hover {
    background: rgba(245, 158, 11, 0.2);
    border-color: #f59e0b;
    color: #ffffff;
}
.arch-table th {
    background: rgba(255, 255, 255, 0.02) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    color: #94a3b8 !important;
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 12px 18px !important;
}
.arch-table td {
    border-color: rgba(255, 255, 255, 0.05) !important;
    color: #e2e8f0 !important;
    font-size: 0.88rem !important;
    padding: 12px 18px !important;
    vertical-align: middle !important;
}
.arch-table tr:hover td {
    background: rgba(255, 255, 255, 0.035) !important;
}
.arch-search-input {
    background: rgba(10, 14, 24, 0.75) !important;
    border: 1px solid rgba(255, 255, 255, 0.14) !important;
    border-radius: 12px !important;
    color: #f8fafc !important;
    font-size: 0.92rem !important;
    height: 46px !important;
    padding: 8px 16px !important;
    padding-left: 42px !important;
}
.arch-search-input:focus {
    background: rgba(15, 22, 38, 0.95) !important;
    border-color: #38bdf8 !important;
    box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2) !important;
    color: #ffffff !important;
}
.pulse-live {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: pulseDot 2s infinite;
}
@keyframes pulseDot {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
</style>

<div class="container-fluid px-0">

    <!-- Top Banner -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4 flex-wrap" style="gap: 15px;">
        <div>
            <div class="d-flex align-items-center mb-1">
                <span class="mr-2" style="color: #38bdf8; font-size: 1.4rem;"><i class="fas fa-globe"></i></span>
                <h1 class="h3 mb-0 text-white font-weight-bold">Live Domains & Infrastructure Directory</h1>
                <span class="badge ml-3 px-2 py-1 font-weight-bold" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8; border: 1px solid rgba(14, 165, 233, 0.3); border-radius: 8px; font-size: 0.75rem;">
                    <i class="fas fa-lock mr-1"></i> Read-Only System Directory
                </span>
            </div>
            <p class="small mb-0" style="color: #94a3b8;">
                Real-time operational directory of all independent live portals, micro-frontends, REST APIs, and database clusters deployed on Hostinger Cloud.
            </p>
        </div>
        <div class="d-flex align-items-center" style="gap: 10px;">
            <button type="button" class="btn btn-sm arch-copy-btn" onclick="pingAllDomains()" id="btnPingAll">
                <i class="fas fa-satellite-dish text-warning"></i>
                <span class="font-weight-bold">Ping All 4 Portals</span>
            </button>
            <button type="button" class="btn btn-sm arch-copy-btn" onclick="loadSection('domain_config')" title="Refresh Directory">
                <i class="fas fa-sync-alt text-info"></i>
            </button>
        </div>
    </div>

    <!-- Read-Only Notice Box -->
    <div class="arch-card p-3 mb-4 d-flex align-items-center justify-content-between flex-wrap" style="gap: 14px; border-left: 4px solid #38bdf8 !important;">
        <div class="d-flex align-items-center">
            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 44px; height: 44px; background: rgba(56, 189, 248, 0.12); color: #38bdf8; font-size: 1.25rem;">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div>
                <h6 class="font-weight-bold text-white mb-0">Production Micro-Frontend Architecture Overview</h6>
                <small style="color: #94a3b8;">
                    All live URLs shown here reflect active DNS mappings. To preserve cross-domain authentication tokens, SSL certificates, and database integrity, live domains are locked from client-side alteration and managed exclusively via Hostinger hPanel & Git.
                </small>
            </div>
        </div>
        <div class="d-flex align-items-center" style="gap: 8px;">
            <span class="badge badge-pill font-weight-bold px-3 py-2" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #34d399; font-size: 0.8rem;">
                <span class="pulse-live mr-1"></span> All Services Operational
            </span>
        </div>
    </div>

    <!-- 4 Portals High-Level Status Cards -->
    <div class="row mb-4">
        <?php foreach ($portals as $key => $p): ?>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="arch-card p-3 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge font-weight-bold px-2 py-1" style="background: rgba(255, 255, 255, 0.05); color: <?= $p['badge_color'] ?>; border: 1px solid <?= $p['badge_color'] ?>40; border-radius: 6px; font-size: 0.72rem;">
                                <?= htmlspecialchars($p['tag']) ?>
                            </span>
                            <span class="badge badge-success px-2 py-1" id="badge_status_<?= $key ?>" style="font-size: 0.7rem; border-radius: 6px;">
                                <i class="fas fa-check-circle mr-1"></i> Ready
                            </span>
                        </div>
                        <h6 class="font-weight-bold text-white mb-1 d-flex align-items-center" style="font-size: 0.95rem;">
                            <i class="fas <?= $p['icon'] ?> mr-2" style="color: <?= $p['badge_color'] ?>;"></i>
                            <?= htmlspecialchars($p['name']) ?>
                        </h6>
                        <div class="arch-code-pill w-100 my-2" style="font-size: 0.78rem !important;">
                            <?= htmlspecialchars($p['domain']) ?>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mt-2 pt-2" style="border-top: 1px solid rgba(255, 255, 255, 0.05);">
                        <span class="small font-monospace" id="latency_<?= $key ?>" style="color: #94a3b8; font-size: 0.75rem;">
                            <i class="fas fa-clock mr-1"></i> Click Ping
                        </span>
                        <div style="display: flex; gap: 6px;">
                            <button class="arch-copy-btn p-1 px-2" onclick="copyText('<?= htmlspecialchars($p['domain']) ?>')" title="Copy Domain">
                                <i class="far fa-copy"></i>
                            </button>
                            <a href="<?= htmlspecialchars($p['domain']) ?>" target="_blank" class="arch-open-btn p-1 px-2" title="Open Link">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Search & Filter Bar -->
    <div class="arch-card p-3 mb-4">
        <div class="position-relative">
            <i class="fas fa-search position-absolute" style="left: 16px; top: 15px; color: #94a3b8; font-size: 0.95rem;"></i>
            <input type="text" id="archSearchInput" class="form-control arch-search-input" placeholder="Search any domain, route, endpoint, or portal URL (e.g. categories, bookings, wallet, login, razorpay)..." onkeyup="filterUrls()">
        </div>
    </div>

    <!-- Detailed Section for Each Portal -->
    <div class="row">
        <?php foreach ($portals as $key => $p): ?>
            <div class="col-12 mb-4 portal-block" data-portal-id="<?= $key ?>">
                <div class="arch-card">
                    <!-- Portal Header -->
                    <div class="arch-header d-flex align-items-center justify-content-between flex-wrap" style="gap: 10px;">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 38px; height: 38px; background: rgba(255, 255, 255, 0.05); color: <?= $p['badge_color'] ?>; font-size: 1.15rem;">
                                <i class="fas <?= $p['icon'] ?>"></i>
                            </div>
                            <div>
                                <h5 class="font-weight-bold text-white mb-0 d-flex align-items-center">
                                    <?= htmlspecialchars($p['name']) ?>
                                    <span class="badge ml-2 font-weight-bold" style="background: rgba(255, 255, 255, 0.06); color: <?= $p['badge_color'] ?>; font-size: 0.75rem;">
                                        <?= htmlspecialchars($p['tag']) ?>
                                    </span>
                                </h5>
                                <div class="small text-muted mt-1" style="color: #94a3b8 !important;">
                                    <?= htmlspecialchars($p['role']) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Header Actions & GitHub Repo -->
                        <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                            <?php if (!empty($p['repo'])): ?>
                                <a href="<?= htmlspecialchars($p['repo']) ?>" target="_blank" class="arch-copy-btn" title="View Source on GitHub">
                                    <i class="fab fa-github"></i>
                                    <span><?= htmlspecialchars($p['repo_name']) ?></span>
                                    <span class="badge badge-secondary ml-1" style="font-size: 0.65rem;">main</span>
                                </a>
                            <?php endif; ?>
                            <button class="arch-ping-btn" onclick="pingSingleUrl('<?= htmlspecialchars($p['domain']) ?>', '#status_icon_<?= $key ?>', '#latency_detail_<?= $key ?>')">
                                <i class="fas fa-satellite-dish"></i> Ping Host
                            </button>
                            <a href="<?= htmlspecialchars($p['domain']) ?>" target="_blank" class="arch-open-btn">
                                <i class="fas fa-external-link-alt"></i> Visit Portal
                            </a>
                        </div>
                    </div>

                    <!-- Domain Hero Strip -->
                    <div class="p-3 d-flex align-items-center justify-content-between flex-wrap" style="background: rgba(0, 0, 0, 0.25); border-bottom: 1px solid rgba(255, 255, 255, 0.05); gap: 12px;">
                        <div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
                            <span class="small font-weight-bold text-uppercase" style="color: #94a3b8; font-size: 0.75rem; letter-spacing: 0.5px;">LIVE DOMAIN:</span>
                            <code class="arch-code-pill font-weight-bold" style="font-size: 0.95rem !important; color: #ffffff !important; border-color: <?= $p['badge_color'] ?>60 !important;">
                                <?= htmlspecialchars($p['domain']) ?>
                            </code>
                            <button class="arch-copy-btn" onclick="copyText('<?= htmlspecialchars($p['domain']) ?>')">
                                <i class="far fa-copy mr-1"></i> Copy Domain
                            </button>
                        </div>
                        <div class="d-flex align-items-center" style="gap: 10px;">
                            <span id="status_icon_<?= $key ?>" class="badge badge-success px-2 py-1" style="font-size: 0.75rem;">
                                <i class="fas fa-check-circle mr-1"></i> HTTPS Active
                            </span>
                            <span id="latency_detail_<?= $key ?>" class="small font-monospace" style="color: #64748b;">
                                Latency: —
                            </span>
                        </div>
                    </div>

                    <!-- Routes & Endpoints Table -->
                    <div class="table-responsive">
                        <table class="table arch-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 22%;">Function / Page</th>
                                    <th style="width: 28%;">Purpose & Details</th>
                                    <th style="width: 38%;">Live URL / Endpoint</th>
                                    <th style="width: 12%; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($p['urls'] as $u): ?>
                                    <tr class="url-row" data-search="<?= strtolower(htmlspecialchars($u['label'] . ' ' . $u['desc'] . ' ' . $u['url'])) ?>">
                                        <td>
                                            <div class="font-weight-bold text-white d-flex align-items-center">
                                                <i class="fas fa-caret-right mr-2" style="color: <?= $p['badge_color'] ?>;"></i>
                                                <?= htmlspecialchars($u['label']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="small" style="color: #94a3b8;"><?= htmlspecialchars($u['desc']) ?></span>
                                        </td>
                                        <td>
                                            <code class="arch-code-pill w-100" title="<?= htmlspecialchars($u['url']) ?>">
                                                <?= htmlspecialchars($u['url']) ?>
                                            </code>
                                        </td>
                                        <td style="text-align: right;">
                                            <div class="d-inline-flex" style="gap: 5px;">
                                                <button class="arch-copy-btn p-1 px-2" onclick="copyText('<?= htmlspecialchars($u['url']) ?>')" title="Copy URL">
                                                    <i class="far fa-copy"></i>
                                                </button>
                                                <a href="<?= htmlspecialchars($u['url']) ?>" target="_blank" class="arch-open-btn p-1 px-2" title="Open Link">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Infrastructure, Cloud Database & Mobile App Row -->
    <div class="row mb-4">
        <!-- Cloud Database Card -->
        <div class="col-lg-6 mb-3">
            <div class="arch-card h-100">
                <div class="arch-header d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-white d-flex align-items-center">
                        <i class="fas fa-database mr-2" style="color: #10b981;"></i> Cloud Database Infrastructure
                    </h6>
                    <span class="badge font-weight-bold" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #34d399; font-size: 0.72rem; border-radius: 6px;">
                        Hostinger MySQL
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <span class="small font-weight-bold text-uppercase d-block mb-1" style="color: #64748b;">Host & Port</span>
                            <div class="font-weight-bold text-white font-monospace"><?= htmlspecialchars($database['host']) ?></div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <span class="small font-weight-bold text-uppercase d-block mb-1" style="color: #64748b;">Database Name</span>
                            <div class="font-weight-bold text-info font-monospace"><?= htmlspecialchars($database['name']) ?></div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <span class="small font-weight-bold text-uppercase d-block mb-1" style="color: #64748b;">Database User</span>
                            <div class="font-weight-bold text-white font-monospace"><?= htmlspecialchars($database['user']) ?></div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <span class="small font-weight-bold text-uppercase d-block mb-1" style="color: #64748b;">Charset & Collation</span>
                            <div class="font-weight-bold text-white font-monospace"><?= htmlspecialchars($database['charset']) ?></div>
                        </div>
                    </div>
                    <div class="p-2 rounded mt-2 small font-weight-bold d-flex align-items-center" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399;">
                        <i class="fas fa-check-double mr-2"></i> Connected & synchronized across all 4 independent portals via PDO connection bridge.
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile App EAS Variable Card -->
        <div class="col-lg-6 mb-3">
            <div class="arch-card h-100">
                <div class="arch-header d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-white d-flex align-items-center">
                        <i class="fas fa-mobile-alt mr-2" style="color: #a78bfa;"></i> Mobile App (React Native / EAS)
                    </h6>
                    <span class="badge font-weight-bold" style="background: rgba(139, 92, 246, 0.15); border: 1px solid rgba(139, 92, 246, 0.35); color: #c4b5fd; font-size: 0.72rem; border-radius: 6px;">
                        Expo Cloud
                    </span>
                </div>
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <p class="small mb-2" style="color: #94a3b8;">
                        Configure this environment variable inside your Expo CLI, EAS Build profile, or <code>app.json</code> so the mobile application communicates with your live central backend:
                    </p>
                    <div class="p-3 rounded mb-3 position-relative" style="background: rgba(0, 0, 0, 0.45); border: 1px solid rgba(255, 255, 255, 0.08); font-family: monospace; font-size: 0.88rem; color: #34d399;">
                        EXPO_PUBLIC_API_URL=<span id="mobileApiSnippet" style="color: #ffffff;"><?= htmlspecialchars($portals['backend']['api_base'] ?? ($portals['backend']['domain'] . '/api')) ?></span>
                    </div>
                    <button class="btn btn-sm arch-copy-btn font-weight-bold d-flex align-items-center justify-content-center" style="height: 42px;" onclick="copyText($('#mobileApiSnippet').text().trim())">
                        <i class="far fa-copy mr-2 text-info"></i> Copy Mobile App API Variable
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    // Copy to clipboard with toast notification
    function copyText(text) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(function() {
            if (typeof Notify !== 'undefined' && Notify.success) {
                Notify.success('Copied: ' + text);
            } else if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Copied to clipboard!',
                    text: text,
                    background: '#0f172a',
                    color: '#ffffff',
                    showConfirmButton: false,
                    timer: 1600
                });
            } else {
                alert('Copied: ' + text);
            }
        });
    }

    // Single URL Ping via server-side cURL
    function pingSingleUrl(targetUrl, iconSelector, latencySelector) {
        if (iconSelector) {
            $(iconSelector).removeClass('badge-success badge-danger badge-warning').addClass('badge-secondary').html('<i class="fas fa-spinner fa-spin mr-1"></i> Pinging...');
        }
        if (latencySelector) {
            $(latencySelector).text('Latency: ...');
        }

        $.ajax({
            url: 'superadmin-dashboard.php',
            type: 'GET',
            data: {
                ajax_action: 'fetch_list',
                type: 'domain_config',
                ajax_ping_url: targetUrl
            },
            dataType: 'json',
            timeout: 8000,
            success: function(res) {
                if (res && res.ok) {
                    if (iconSelector) {
                        $(iconSelector).removeClass('badge-secondary badge-danger badge-warning').addClass('badge-success').html('<i class="fas fa-check-circle mr-1"></i> HTTP ' + res.code);
                    }
                    if (latencySelector) {
                        var color = res.latency_ms < 500 ? '#10b981' : '#f59e0b';
                        $(latencySelector).html('<span style="color:' + color + '; font-weight: 700;">' + res.latency_ms + 'ms</span>');
                    }
                } else {
                    var code = (res && res.code) ? res.code : 'Err';
                    if (iconSelector) {
                        $(iconSelector).removeClass('badge-secondary badge-success badge-warning').addClass('badge-danger').html('<i class="fas fa-times-circle mr-1"></i> ' + code);
                    }
                    if (latencySelector) {
                        $(latencySelector).html('<span class="text-danger font-weight-bold">Unreachable</span>');
                    }
                }
            },
            error: function() {
                if (iconSelector) {
                    $(iconSelector).removeClass('badge-secondary badge-success').addClass('badge-danger').html('<i class="fas fa-times-circle mr-1"></i> Failed');
                }
                if (latencySelector) {
                    $(latencySelector).html('<span class="text-danger font-weight-bold">Timeout</span>');
                }
            }
        });
    }

    // Ping all 4 portals simultaneously
    function pingAllDomains() {
        var $btn = $('#btnPingAll');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Pinging All...');

        var portals = <?= json_encode(array_map(function($p) { return $p['domain']; }, $portals)) ?>;
        var keys = Object.keys(portals);
        var completed = 0;

        keys.forEach(function(k) {
            var url = portals[k];
            pingSingleUrl(url, '#badge_status_' + k, '#latency_' + k);
            pingSingleUrl(url, '#status_icon_' + k, '#latency_detail_' + k);
        });

        setTimeout(function() {
            $btn.prop('disabled', false).html('<i class="fas fa-satellite-dish text-warning"></i> <span class="font-weight-bold">Ping All 4 Portals</span>');
        }, 2500);
    }

    // Filter table rows by search keyword
    function filterUrls() {
        var query = ($('#archSearchInput').val() || '').trim().toLowerCase();
        if (!query) {
            $('.url-row').show();
            $('.portal-block').show();
            return;
        }

        $('.portal-block').each(function() {
            var $block = $(this);
            var visibleCount = 0;
            $block.find('.url-row').each(function() {
                var searchData = $(this).attr('data-search') || '';
                if (searchData.indexOf(query) !== -1) {
                    $(this).show();
                    visibleCount++;
                } else {
                    $(this).hide();
                }
            });

            if (visibleCount > 0) {
                $block.show();
            } else {
                $block.hide();
            }
        });
    }

    // Auto-ping when tab is opened
    $(document).ready(function() {
        setTimeout(function() {
            pingAllDomains();
        }, 400);
    });
</script>
