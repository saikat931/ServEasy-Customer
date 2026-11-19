<?php
/**
 * ServEasy Convenience Fee (Platform Fee) Management Component
 * Reusable across Admin Dashboard and Superadmin Dashboard
 * Targets: `platform_settings` and `fee_settings_logs`
 */

if (!isset($pdo)) {
    $cfgPath = file_exists(__DIR__ . '/../common/db_connection.php')
        ? __DIR__ . '/../common/db_connection.php'
        : (file_exists(__DIR__ . '/../../backend/config/db_connection.php') ? __DIR__ . '/../../backend/config/db_connection.php' : null);
    if ($cfgPath) require_once $cfgPath;
    $pdo = Database::connect();
}

if (!class_exists('\\App\\Repositories\\SettingRepository')) {
    $repoPath = file_exists(__DIR__ . '/../services/SettingRepository.php')
        ? __DIR__ . '/../services/SettingRepository.php'
        : (file_exists(__DIR__ . '/../../backend/app/repositories/SettingRepository.php') ? __DIR__ . '/../../backend/app/repositories/SettingRepository.php' : null);
    if ($repoPath) require_once $repoPath;
}

$settingRepo = new \App\Repositories\SettingRepository();

$is_super_admin_ctx = isset($is_super_admin) && $is_super_admin;
$dashboard_endpoint = $is_super_admin_ctx ? 'superadmin-dashboard.php' : 'admin-dashboard.php';
if (!file_exists($dashboard_endpoint) && file_exists('admin-dashboard.php')) {
    $dashboard_endpoint = 'admin-dashboard.php';
}

// ── Helper: Render Fee Logs Table Rows ─────────────────────────
if (!function_exists('renderFeeLogsTableRows')) {
    function renderFeeLogsTableRows(array $logs): string {
        if (empty($logs)) {
            return '<tr><td colspan="10" class="text-center py-4 text-muted"><i class="fas fa-info-circle mr-1"></i> No past fee modifications recorded yet. Default is ₹20.00.</td></tr>';
        }

        $html = '';
        foreach ($logs as $idx => $log) {
            $id = (int)$log['id'];
            $log_type = $log['fee_type'] ?? 'fixed';
            $is_log_pct = ($log_type === 'percentage');
            $scope = $log['service_scope'] ?? 'all';
            $scope_items = [];
            if (!empty($log['applicable_services'])) {
                $dec = json_decode($log['applicable_services'], true);
                $scope_items = is_array($dec) ? $dec : [];
            }

            $html .= '<tr id="fee-log-row-' . $id . '">';
            $html .= '<td class="font-weight-bold">' . ($idx + 1) . '</td>';
            $html .= '<td class="small text-muted">' . htmlspecialchars($log['created_at']) . '</td>';
            $html .= '<td><span class="badge badge-light border">🇮🇳 India (IN)</span></td>';

            if ($is_log_pct) {
                $html .= '<td><span class="badge badge-info"><i class="fas fa-percent mr-1"></i>Percentage</span></td>';
            } else {
                $html .= '<td><span class="badge badge-secondary"><i class="fas fa-tag mr-1"></i>Fixed Flat</span></td>';
            }

            if ($scope === 'all') {
                $html .= '<td><span class="badge badge-primary"><i class="fas fa-globe-asia mr-1"></i>All Services</span></td>';
            } else {
                $count = count($scope_items);
                $tooltip = htmlspecialchars(implode(', ', $scope_items));
                $html .= '<td><span class="badge badge-warning text-dark" title="' . $tooltip . '"><i class="fas fa-filter mr-1"></i>' . ($count > 0 ? "Subcats ({$count})" : 'Particular Subcats') . '</span></td>';
            }

            $old_str = '<em class="text-muted">Initial</em>';
            if ($log['old_amount'] !== null && $log['old_amount'] !== '') {
                $old_str = $is_log_pct ? (htmlspecialchars($log['old_amount']) . '%') : ('₹' . number_format((float)$log['old_amount'], 2));
            }
            $new_str = $is_log_pct ? (htmlspecialchars($log['new_amount']) . '%') : ('₹' . number_format((float)$log['new_amount'], 2));

            $html .= '<td>' . $old_str . '</td>';
            $html .= '<td class="font-weight-bold text-success">' . $new_str . '</td>';
            $html .= '<td><i class="fas fa-user-circle mr-1 text-secondary"></i>' . htmlspecialchars($log['updated_by'] ?: 'Admin') . '</td>';
            $html .= '<td class="text-center"><span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Applied</span></td>';

            // Action buttons (View, Edit, Delete)
            $html .= '<td class="text-center">';
            $html .= '<div class="btn-group btn-group-sm" role="group">';
            $html .= '<button type="button" class="btn btn-outline-info" title="View Details" onclick="viewLogRecord(' . $id . ');"><i class="fas fa-eye"></i></button>';
            $html .= '<button type="button" class="btn btn-outline-primary" title="Edit Record" onclick="editLogRecord(' . $id . ');"><i class="fas fa-edit"></i></button>';
            $html .= '<button type="button" class="btn btn-outline-danger" title="Delete Record" onclick="deleteLogRecord(' . $id . ');"><i class="fas fa-trash-alt"></i></button>';
            $html .= '</div>';
            $html .= '</td>';

            $html .= '</tr>';
        }

        return $html;
    }
}

// ── Handle AJAX Actions ────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['fee_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['fee_action'];

    // 1. Save Platform Convenience Fee Setting
    if ($action === 'save_convenience_fee') {
        $country_code = strtoupper(trim($_POST['country_code'] ?? 'IN'));
        $country_name = trim($_POST['country_name'] ?? 'India');
        $fee_amount   = max(0, (float)($_POST['fee_amount'] ?? 20.00));
        $fee_type     = in_array($_POST['fee_type'] ?? '', ['fixed', 'percentage']) ? $_POST['fee_type'] : 'fixed';
        $is_active    = !empty($_POST['is_active']) ? 1 : 0;
        $service_scope = in_array($_POST['service_scope'] ?? '', ['all', 'specific']) ? $_POST['service_scope'] : 'all';
        $applicable_services = null;
        if ($service_scope === 'specific') {
            $services = isset($_POST['applicable_services']) && is_array($_POST['applicable_services']) ? $_POST['applicable_services'] : [];
            $applicable_services = json_encode(array_values($services));
        }
        $description  = trim($_POST['description'] ?? 'Platform convenience fee for customer bookings');
        $notes        = trim($_POST['fee_notes'] ?? 'Updated platform fee parameters');
        $admin_user   = $_SESSION['username'] ?? ($is_super_admin_ctx ? 'Super Admin' : 'Admin');

        $success = $settingRepo->updateConvenienceFee(
            $fee_amount,
            $fee_type,
            $is_active,
            $country_code,
            $country_name,
            $description,
            $admin_user,
            $service_scope,
            $applicable_services,
            $notes
        );

        if ($success) {
            $formatted_fee = ($fee_type === 'percentage')
                ? (rtrim(rtrim(number_format($fee_amount, 2, '.', ''), '0'), '.') . '%')
                : ('₹' . number_format($fee_amount, 2));

            $count_subcats = ($applicable_services) ? count(json_decode($applicable_services, true)) : 0;
            $scope_text = ($service_scope === 'all') ? 'All Services' : "Particular Subcategories ({$count_subcats})";

            echo json_encode([
                'ok' => true,
                'message' => "Convenience Fee updated to {$formatted_fee} (" . ($fee_type === 'percentage' ? 'Percentage' : 'Fixed') . " - {$scope_text}) for {$country_name}!",
                'fee_amount' => $fee_amount,
                'fee_type' => $fee_type,
                'is_active' => $is_active,
                'service_scope' => $service_scope,
                'applicable_services' => $applicable_services ? json_decode($applicable_services, true) : [],
                'updated_by' => $admin_user,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Failed to save fee settings in database.']);
        }
        exit();
    }

    // 2. Create Audit Log Record (CRUD: Create)
    if ($action === 'create_fee_log') {
        $admin_user = trim($_POST['updated_by'] ?? '') ?: ($_SESSION['username'] ?? 'Admin');
        $fee_type = in_array($_POST['fee_type'] ?? '', ['fixed', 'percentage']) ? $_POST['fee_type'] : 'fixed';
        $service_scope = in_array($_POST['service_scope'] ?? '', ['all', 'specific']) ? $_POST['service_scope'] : 'all';
        $applicable_services = null;
        if ($service_scope === 'specific') {
            $services = isset($_POST['applicable_services']) && is_array($_POST['applicable_services']) ? $_POST['applicable_services'] : [];
            $applicable_services = json_encode(array_values($services));
        }

        $logId = $settingRepo->createFeeLog([
            'setting_key' => 'convenience_fee',
            'country_code' => 'IN',
            'fee_type' => $fee_type,
            'service_scope' => $service_scope,
            'applicable_services' => $applicable_services,
            'old_amount' => $_POST['old_amount'] !== '' ? (float)$_POST['old_amount'] : null,
            'new_amount' => (float)($_POST['new_amount'] ?? 0),
            'notes' => trim($_POST['notes'] ?? ''),
            'updated_by' => $admin_user
        ]);

        if ($logId) {
            echo json_encode(['ok' => true, 'message' => 'New audit log entry #' . $logId . ' created successfully!']);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Failed to create audit log entry.']);
        }
        exit();
    }

    // 3. Read Single Audit Log Record (CRUD: Read)
    if ($action === 'get_fee_log') {
        $log_id = (int)($_POST['log_id'] ?? 0);
        $log = $settingRepo->getFeeLogById($log_id);
        if ($log) {
            $decoded_services = [];
            if (!empty($log['applicable_services'])) {
                $dec = json_decode($log['applicable_services'], true);
                $decoded_services = is_array($dec) ? $dec : [];
            }
            $log['applicable_services_list'] = $decoded_services;
            echo json_encode(['ok' => true, 'data' => $log]);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Audit log record not found.']);
        }
        exit();
    }

    // 4. Update Audit Log Record (CRUD: Update)
    if ($action === 'update_fee_log') {
        $log_id = (int)($_POST['log_id'] ?? 0);
        $fee_type = in_array($_POST['fee_type'] ?? '', ['fixed', 'percentage']) ? $_POST['fee_type'] : 'fixed';
        $service_scope = in_array($_POST['service_scope'] ?? '', ['all', 'specific']) ? $_POST['service_scope'] : 'all';
        $applicable_services = null;
        if ($service_scope === 'specific') {
            $services = isset($_POST['applicable_services']) && is_array($_POST['applicable_services']) ? $_POST['applicable_services'] : [];
            $applicable_services = json_encode(array_values($services));
        }

        $success = $settingRepo->updateFeeLog($log_id, [
            'fee_type' => $fee_type,
            'service_scope' => $service_scope,
            'applicable_services' => $applicable_services,
            'old_amount' => $_POST['old_amount'] !== '' ? (float)$_POST['old_amount'] : null,
            'new_amount' => (float)($_POST['new_amount'] ?? 0),
            'notes' => trim($_POST['notes'] ?? ''),
            'updated_by' => trim($_POST['updated_by'] ?? 'Admin')
        ]);

        if ($success) {
            echo json_encode(['ok' => true, 'message' => 'Audit log record #' . $log_id . ' updated successfully!']);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Failed to update audit log record.']);
        }
        exit();
    }

    // 5. Delete Audit Log Record (CRUD: Delete)
    if ($action === 'delete_fee_log') {
        $log_id = (int)($_POST['log_id'] ?? 0);
        $success = $settingRepo->deleteFeeLog($log_id);
        if ($success) {
            echo json_encode(['ok' => true, 'message' => 'Audit log record #' . $log_id . ' permanently deleted.']);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Failed to delete audit log record.']);
        }
        exit();
    }

    // 6. Fetch Logs Table HTML for seamless dynamic reload
    if ($action === 'fetch_fee_logs_table') {
        $logs = $settingRepo->getFeeLogs(50);
        echo json_encode(['ok' => true, 'html' => renderFeeLogsTableRows($logs)]);
        exit();
    }
}

// ── Fetch Current Setting for India ─────────────────────────────
$current_setting = $settingRepo->getConvenienceFee('IN');
$fee_amount  = $current_setting['amount'] ?? 20.00;
$fee_type    = $current_setting['type'] ?? 'fixed';
$fee_active  = !empty($current_setting['is_active']) ? 1 : 0;
$service_scope = $current_setting['service_scope'] ?? 'all';
$selected_services = $current_setting['applicable_services'] ?? [];
$country_code = $current_setting['country_code'] ?? 'IN';
$country_name = $current_setting['country_name'] ?? 'India';
$currency_symbol = $current_setting['currency_symbol'] ?? '₹';
$updated_by  = $current_setting['updated_by'] ?? 'System';
$updated_at  = $current_setting['updated_at'] ?? date('Y-m-d H:i:s');
$description = $current_setting['description'] ?? 'Standard platform convenience fee for India customer bookings';

// Fetch subcategories grouped by category from serveasy-services
$categories_with_subs = [];
$total_subcategories = 0;
try {
    $sub_stmt = $pdo->query("
        SELECT 
            s.id AS sub_id, 
            s.name AS sub_name, 
            s.slug AS sub_slug,
            c.id AS cat_id,
            c.name AS cat_name
        FROM `serveasy-subcategories` s
        JOIN `serveasy-categories` c ON s.category_id = c.id
        WHERE c.is_active = 1
        ORDER BY c.id ASC, s.id ASC
    ");
    $all_subs = $sub_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_subs as $row) {
        $categories_with_subs[$row['cat_name']][] = $row;
        $total_subcategories++;
    }
} catch (\Throwable $t) { }

// Fetch recent audit logs
$recent_logs = $settingRepo->getFeeLogs(50);
?>

<style>
/* Convenience Fee Dark Obsidian Polish */
.fee-container-card {
    background: var(--se-glass-bg-elevated) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 18px !important;
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.45) !important;
    overflow: hidden;
}
.fee-card-header {
    background: rgba(255, 255, 255, 0.03) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    padding: 1rem 1.25rem !important;
}
.fee-sub-card {
    background: rgba(255, 255, 255, 0.03) !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 14px !important;
    padding: 18px 20px !important;
}
.fee-sub-card label.custom-control-label {
    color: #f1f5f9 !important;
}
.fee-input-group .input-group-text {
    background: rgba(255, 255, 255, 0.07) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    border-right: none !important;
    color: #60a5fa !important;
    border-radius: 10px 0 0 10px !important;
}
.fee-input-group input.form-control {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    color: #ffffff !important;
    border-radius: 0 10px 10px 0 !important;
}
.fee-input-group input.form-control:focus {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: #007aff !important;
    color: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.3) !important;
}
.subcategories-accordion .category-block {
    background: rgba(255, 255, 255, 0.02) !important;
    border: 1px solid rgba(255, 255, 255, 0.07) !important;
    border-radius: 12px !important;
}
.subcategories-accordion .custom-checkbox {
    background: rgba(255, 255, 255, 0.04) !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 8px !important;
}
.subcategories-accordion .custom-checkbox label {
    color: #e2e8f0 !important;
}
.fee-info-list .list-group-item {
    background: transparent !important;
    border-color: rgba(255, 255, 255, 0.06) !important;
    color: #94a3b8 !important;
}
.fee-info-list .list-group-item strong {
    color: #ffffff !important;
}
</style>

<div class="container-fluid p-0">
    <!-- Header Card -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4 flex-wrap" style="gap: 15px;">
        <div>
            <h1 class="h3 mb-1 text-gray-800 font-weight-bold">
                <i class="fas fa-hand-holding-usd text-primary mr-2"></i>Convenience Fee (Platform Fee)
            </h1>
            <p class="text-muted small mb-0">Set and manage the customer checkout convenience fee, percentage rates, and subcategory applicability for Indian territory bookings.</p>
        </div>
        <div class="d-flex align-items-center" style="gap: 10px;">
            <span class="badge badge-pill badge-primary px-3 py-2" style="font-size: 0.85rem;">
                <i class="fas fa-globe-asia mr-1"></i> Region: <strong>India (<?= htmlspecialchars($currency_symbol) ?> INR)</strong>
            </span>
            <span class="badge badge-pill <?= $fee_active ? 'badge-success' : 'badge-secondary' ?> px-3 py-2" id="badge-fee-status" style="font-size: 0.85rem;">
                <i class="fas <?= $fee_active ? 'fa-check-circle' : 'fa-pause-circle' ?> mr-1"></i> 
                <span id="badge-fee-status-text"><?= $fee_active ? 'Fee Active' : 'Fee Disabled' ?></span>
            </span>
        </div>
    </div>

    <!-- Alert / Toast Container -->
    <div id="fee-alert-box" style="display: none;"></div>

    <div class="row">
        <!-- Settings Form Column -->
        <div class="col-xl-7 col-lg-7 mb-4">
            <div class="card fee-container-card mb-4">
                <div class="card-header fee-card-header d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-sliders-h mr-1"></i> Fee Configuration Parameters
                    </h6>
                    <small class="text-muted"><i class="fas fa-shield-alt text-success mr-1"></i> Authorized: <?= $is_super_admin_ctx ? 'Super Admin' : 'Admin' ?></small>
                </div>
                <div class="card-body p-4">
                    <form id="convenienceFeeForm" onsubmit="submitConvenienceFeeForm(event); return false;">
                        <input type="hidden" name="fee_action" value="save_convenience_fee">

                        <!-- Country Selection Field -->
                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-gray-800" for="country_select">
                                <i class="fas fa-flag text-danger mr-1"></i> Select Operating Country <span class="text-danger">*</span>
                            </label>
                            <select class="form-control form-control-lg custom-select" id="country_select" name="country_code" onchange="handleCountryChange(this.value);" style="border-radius: 12px; font-weight: 600; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.12); color: #ffffff;">
                                <option value="IN" selected>India (INR - ₹)</option>
                                <option value="US" disabled>United States (USD - $) (Global Expansion - Coming Soon)</option>
                                <option value="AE" disabled>United Arab Emirates (AED) (Coming Soon)</option>
                            </select>
                            <input type="hidden" id="country_name_input" name="country_name" value="<?= htmlspecialchars($country_name) ?>">
                            <small class="form-text text-muted">Currently active for all Indian vehicle wash, maid, and home service checkout bookings.</small>
                        </div>

                        <!-- 1. Fee Calculation Type (Selected First) -->
                        <div class="form-group mb-4 fee-sub-card">
                            <label class="font-weight-bold text-gray-800 d-block mb-2">
                                <i class="fas fa-calculator text-primary mr-1"></i> Fee Calculation Type <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex flex-wrap" style="gap: 20px;">
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="fee_type_fixed" name="fee_type" value="fixed" class="custom-control-input" <?= $fee_type === 'fixed' ? 'checked' : '' ?> onchange="handleFeeTypeChange('fixed');">
                                    <label class="custom-control-label font-weight-bold" for="fee_type_fixed" style="cursor: pointer;">
                                        <i class="fas fa-tag text-primary mr-1"></i> Fixed Flat Fee (₹ per order)
                                    </label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="fee_type_percentage" name="fee_type" value="percentage" class="custom-control-input" <?= $fee_type === 'percentage' ? 'checked' : '' ?> onchange="handleFeeTypeChange('percentage');">
                                    <label class="custom-control-label font-weight-bold" for="fee_type_percentage" style="cursor: pointer;">
                                        <i class="fas fa-percent text-success mr-1"></i> Percentage (%) of Subtotal
                                    </label>
                                </div>
                            </div>
                            <small class="form-text text-muted mt-2">
                                If you choose <strong>Fixed Flat Fee</strong>, a set rupee amount will apply per order. If you choose <strong>Percentage</strong>, the set percentage will apply to each service subtotal amount.
                            </small>
                        </div>

                        <!-- 2. Convenience Fee Amount / Percentage Field -->
                        <div class="form-group mb-4" id="fee_amount_group">
                            <label class="font-weight-bold text-gray-800" for="fee_amount_input" id="fee_amount_label">
                                <?php if ($fee_type === 'percentage'): ?>
                                    <i class="fas fa-percent text-success mr-1"></i> Convenience Fee Percentage (%) <span class="text-danger">*</span>
                                <?php else: ?>
                                    <i class="fas fa-rupee-sign text-success mr-1"></i> Convenience Fee Amount (₹) <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group input-group-lg fee-input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text font-weight-bold" id="fee_addon_symbol" style="font-size: 1.25rem;">
                                        <?= $fee_type === 'percentage' ? '%' : '₹' ?>
                                    </span>
                                </div>
                                <input type="number" 
                                    class="form-control form-control-lg font-weight-bold" 
                                    id="fee_amount_input" 
                                    name="fee_amount" 
                                    value="<?= htmlspecialchars(number_format($fee_amount, 2, '.', '')) ?>" 
                                    step="<?= $fee_type === 'percentage' ? '0.1' : '0.50' ?>" 
                                    min="0" 
                                    max="<?= $fee_type === 'percentage' ? '100' : '5000' ?>" 
                                    required
                                    style="font-size: 1.3rem;"
                                    oninput="updateLivePreview();">
                            </div>
                            <small class="form-text text-muted" id="fee_help_text">
                                <?php if ($fee_type === 'percentage'): ?>
                                    This percentage (e.g. <strong>5%</strong>) is automatically calculated from the per-service amount / subtotal on customer checkout.
                                <?php else: ?>
                                    This amount (e.g. <strong>₹20.00</strong>) is automatically added as Convenience Fee / Platform Fee on customer booking summaries.
                                <?php endif; ?>
                            </small>
                        </div>

                        <!-- 3. Applicable Services Scope (Applies on Subcategories) -->
                        <div class="form-group mb-4 fee-sub-card">
                            <label class="font-weight-bold text-gray-800 d-block mb-2">
                                <i class="fas fa-layer-group text-warning mr-1"></i> Service Scope Applicability <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex flex-wrap" style="gap: 20px;">
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="scope_all" name="service_scope" value="all" class="custom-control-input" <?= ($service_scope === 'all') ? 'checked' : '' ?> onchange="handleScopeChange('all');">
                                    <label class="custom-control-label font-weight-bold" for="scope_all" style="cursor: pointer;">
                                        <i class="fas fa-globe-asia text-primary mr-1"></i> All Services (Universal Platform Fee)
                                    </label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="scope_specific" name="service_scope" value="specific" class="custom-control-input" <?= ($service_scope === 'specific') ? 'checked' : '' ?> onchange="handleScopeChange('specific');">
                                    <label class="custom-control-label font-weight-bold" for="scope_specific" style="cursor: pointer;">
                                        <i class="fas fa-filter text-info mr-1"></i> Particular Services Only (Apply on Subcategory)
                                    </label>
                                </div>
                            </div>
                            <small class="form-text text-muted mt-2">
                                Choose whether all bookings levy this fee or restrict applicability exclusively to checked <strong>subcategories</strong> below.
                            </small>

                            <!-- Subcategories Multi-Select Grouped Container -->
                            <div id="specific_services_container" class="mt-3 pt-3" style="border-top: 1px dashed rgba(255,255,255,0.12); display: <?= ($service_scope === 'specific') ? 'block' : 'none' ?>;">
                                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap" style="gap: 8px;">
                                    <span class="font-weight-bold small text-muted text-uppercase tracking-wider">
                                        <i class="fas fa-check-double text-success mr-1"></i> Select Subcategories to Apply Fee:
                                    </span>
                                    <div>
                                        <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 font-weight-bold" onclick="selectAllSubcategories(true);">Select All</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 ml-1 font-weight-bold" onclick="selectAllSubcategories(false);">Deselect All</button>
                                    </div>
                                </div>

                                <!-- Filter input -->
                                <div class="input-group input-group-sm mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); color: #94a3b8;"><i class="fas fa-search text-muted"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="subcat_search" placeholder="Type to filter subcategories (e.g. Car Wash, Bike Wash, AC Service...)" oninput="filterSubcategories(this.value);" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); color: #ffffff;">
                                </div>

                                <!-- Subcategories by Category List -->
                                <div class="subcategories-accordion" style="max-height: 380px; overflow-y: auto; padding-right: 5px;">
                                    <?php foreach ($categories_with_subs as $cat_title => $subs): ?>
                                        <div class="category-block mb-3 p-2">
                                            <div class="d-flex align-items-center justify-content-between pb-2 mb-2" style="border-bottom: 1px solid rgba(255,255,255,0.08);">
                                                <span class="font-weight-bold text-white small">
                                                    <i class="fas fa-folder text-warning mr-1"></i> <?= htmlspecialchars($cat_title) ?>
                                                    <span class="badge badge-light border text-muted ml-1" style="background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.1) !important; color: #94a3b8 !important;"><?= count($subs) ?> subcategories</span>
                                                </span>
                                                <button type="button" class="btn btn-xs btn-link p-0 text-primary font-weight-bold" onclick="toggleCategorySubs('cat_group_<?= md5($cat_title) ?>');">
                                                    Toggle All in Group
                                                </button>
                                            </div>
                                            <div class="row cat_group_<?= md5($cat_title) ?>">
                                                <?php foreach ($subs as $sub): ?>
                                                    <?php 
                                                        $is_sub_selected = in_array($sub['sub_slug'], $selected_services) 
                                                            || in_array((string)$sub['sub_id'], $selected_services)
                                                            || in_array($sub['sub_id'], $selected_services);
                                                    ?>
                                                    <div class="col-md-6 mb-2 subcat-item" data-name="<?= strtolower(htmlspecialchars($sub['sub_name'] . ' ' . $cat_title)) ?>">
                                                        <div class="custom-control custom-checkbox p-2">
                                                            <input type="checkbox" 
                                                                class="custom-control-input service-checkbox subcat-check-<?= md5($cat_title) ?>" 
                                                                id="sub_<?= $sub['sub_id'] ?>" 
                                                                name="applicable_services[]" 
                                                                value="<?= htmlspecialchars($sub['sub_slug']) ?>" 
                                                                <?= $is_sub_selected ? 'checked' : '' ?> 
                                                                onchange="updateLivePreview();">
                                                            <label class="custom-control-label font-weight-bold small d-block" for="sub_<?= $sub['sub_id'] ?>" style="cursor: pointer;">
                                                                <?= htmlspecialchars($sub['sub_name']) ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <small class="text-info d-block mt-2">
                                    <i class="fas fa-info-circle mr-1"></i> If a customer books a subcategory not checked above, the convenience fee will be waived (₹0.00).
                                </small>
                            </div>
                        </div>

                        <!-- 4. Status Active Toggle -->
                        <div class="form-group mb-4 fee-sub-card">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="fee_is_active" name="is_active" value="1" <?= $fee_active ? 'checked' : '' ?> onchange="updateLivePreview();">
                                <label class="custom-control-label font-weight-bold text-gray-800" for="fee_is_active">
                                    Enable Convenience Fee on Customer Checkout
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1 pl-4">If toggled OFF, convenience fee will be waived (₹0.00) during checkout for promotional periods.</small>
                        </div>

                        <!-- 5. Description Note -->
                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-gray-800" for="fee_description">
                                <i class="fas fa-info-circle text-secondary mr-1"></i> Customer Display Note
                            </label>
                            <input type="text" class="form-control" id="fee_description" name="description" value="<?= htmlspecialchars($description) ?>" placeholder="e.g. Standard platform & safety convenience fee" style="border-radius: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.12); color: #ffffff;">
                            <small class="form-text text-muted">Tooltip or explanation displayed alongside the bill breakdown.</small>
                        </div>

                        <!-- 6. Submit Buttons -->
                        <div class="d-flex align-items-center justify-content-between pt-3" style="border-top: 1px solid #edf2f7;">
                            <button type="button" class="btn btn-outline-secondary font-weight-bold px-3 py-2" onclick="resetFeeForm();" style="border-radius: 8px;">
                                <i class="fas fa-undo mr-1"></i> Reset Default (₹20.00)
                            </button>
                            <button type="submit" id="btn-save-fee" class="btn btn-primary font-weight-bold px-4 py-2" style="border-radius: 8px; box-shadow: 0 4px 12px rgba(78, 115, 223, 0.25);">
                                <span class="spinner-border spinner-border-sm mr-1" id="save-fee-spinner" style="display: none;" role="status"></span>
                                <i class="fas fa-save mr-1" id="save-fee-icon"></i> Save Convenience Fee
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Live Preview & Info Column -->
        <div class="col-xl-5 col-lg-5 mb-4">
            <!-- Real-Time Customer Checkout Simulator -->
            <div class="card fee-container-card mb-4" style="border: 1px solid rgba(139, 92, 246, 0.3) !important; background: linear-gradient(145deg, rgba(30, 27, 75, 0.8) 0%, rgba(15, 23, 42, 0.95) 100%) !important;">
                <div class="card-header fee-card-header d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-white">
                        <i class="fas fa-eye text-warning mr-1"></i> Live Customer Checkout Preview
                    </h6>
                    <span class="badge badge-warning font-weight-bold" style="font-size: 0.7rem; text-transform: uppercase;">Step 10 Simulator</span>
                </div>
                <div class="card-body p-4">
                    <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 20px;">
                        This preview reflects exactly how your updated Convenience Fee is displayed to customers on the <strong>Vehicle Car &amp; Bike Wash</strong> Order Summary screen.
                    </p>

                    <!-- Mock Order Summary Glass Container -->
                    <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 18px;">
                        <div style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: #a78bfa; margin-bottom: 12px; letter-spacing: 0.5px;">
                            Bill Summary (Car &amp; Bike Wash)
                        </div>

                        <!-- Subtotal -->
                        <div class="d-flex justify-content-between mb-2" style="font-size: 0.95rem; color: #cbd5e1;">
                            <span>Subtotal (Wash + Add-ons):</span>
                            <span class="font-weight-bold">₹119.00</span>
                        </div>

                        <!-- Convenience Fee Live Dynamic Row -->
                        <div class="d-flex justify-content-between mb-2" style="font-size: 0.95rem; color: #38bdf8;">
                            <span id="preview-fee-label">
                                <?= $fee_type === 'percentage' ? "Convenience Fee ({$fee_amount}% of Service):" : "Convenience Fee (Fixed Fee):" ?>
                                <i class="fas fa-info-circle ml-1" title="Configured in Admin Dashboard"></i>
                            </span>
                            <span class="font-weight-bold" id="preview-fee-amount" style="font-size: 1.05rem;">
                                <?php
                                $sample_subtotal = 119.00;
                                $init_preview_fee = 0;
                                $is_veh_wash_in_scope = ($service_scope === 'all') 
                                    || in_array('car-wash', $selected_services) 
                                    || in_array('bike-wash', $selected_services)
                                    || in_array('others-wash-services', $selected_services);
                                if ($fee_active && $is_veh_wash_in_scope) {
                                    $init_preview_fee = ($fee_type === 'percentage') ? (($sample_subtotal * $fee_amount) / 100) : $fee_amount;
                                }
                                echo '₹' . number_format($init_preview_fee, 2);
                                ?>
                            </span>
                        </div>

                        <hr style="border-color: rgba(255,255,255,0.15); margin: 12px 0;">

                        <!-- Total Row -->
                        <div class="d-flex justify-content-between align-items-center" style="font-size: 1.15rem; font-weight: 800; color: #10b981;">
                            <span>Total Payable:</span>
                            <span id="preview-total-amount">
                                ₹<?= number_format(119.00 + $init_preview_fee, 2) ?>
                            </span>
                        </div>
                    </div>

                    <div class="mt-3 p-2 text-center" style="background: rgba(16, 185, 129, 0.1); border: 1px dashed rgba(16, 185, 129, 0.3); border-radius: 8px;">
                        <small style="color: #6ee7b7;">
                            <i class="fas fa-check-circle mr-1"></i> Synchronized directly with <code>platform_settings</code> in <code>serveasy-admin</code>.
                        </small>
                    </div>
                </div>
            </div>

            <!-- Last Updated Info Card -->
            <div class="card fee-container-card mb-4">
                <div class="card-header fee-card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history mr-1"></i> Last Configuration State
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush fee-info-list" style="font-size: 0.9rem;">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Current Fee:</span>
                            <strong id="info-current-fee">
                                <?= $fee_type === 'percentage' ? (number_format($fee_amount, 2) . '% (Percentage)') : ('₹' . number_format($fee_amount, 2) . ' (Fixed)') ?>
                            </strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Calculation Type:</span>
                            <span class="badge badge-info" id="info-current-type">
                                <?= $fee_type === 'percentage' ? 'Percentage of Subtotal' : 'Fixed Flat Fee' ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Service Scope:</span>
                            <span class="badge <?= ($service_scope === 'all') ? 'badge-primary' : 'badge-warning text-dark' ?>" id="info-current-scope">
                                <?= ($service_scope === 'all') ? 'All Services' : 'Particular Subcategories (' . count($selected_services) . ')' ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Target Country:</span>
                            <strong>India (IN)</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Last Changed By:</span>
                            <span class="badge badge-light border" style="background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.1) !important; color: #94a3b8 !important;" id="info-updated-by"><?= htmlspecialchars($updated_by) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Last Modified:</span>
                            <span class="small" style="color: #cbd5e1;" id="info-updated-at"><?= htmlspecialchars($updated_at) ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Log History with Full CRUD -->
    <div class="card fee-container-card mb-4">
        <div class="card-header fee-card-header d-flex align-items-center justify-content-between flex-wrap" style="gap: 10px;">
            <div>
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-clipboard-list mr-1"></i> Fee Change Audit History
                </h6>
                <small class="text-muted">Manage, track, edit, or remove convenience fee audit records</small>
            </div>
            <div class="d-flex align-items-center" style="gap: 8px;">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshFeeLogsTable();" title="Refresh Table">
                    <i class="fas fa-sync-alt" id="btn-refresh-logs-icon"></i>
                </button>
                <button type="button" class="btn btn-sm btn-success font-weight-bold shadow-sm" onclick="openCreateLogModal();">
                    <i class="fas fa-plus-circle mr-1"></i> Add Audit Record
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size: 0.9rem;">
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0">#</th>
                            <th class="border-0">Timestamp</th>
                            <th class="border-0">Country</th>
                            <th class="border-0">Fee Type</th>
                            <th class="border-0">Applied Scope</th>
                            <th class="border-0">Previous Fee</th>
                            <th class="border-0">New Fee</th>
                            <th class="border-0">Updated By</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-center" style="min-width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="feeLogsTableBody">
                        <?= renderFeeLogsTableRows($recent_logs) ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: CREATE MANUAL AUDIT RECORD (CRUD: CREATE)         -->
<!-- ======================================================== -->
<div class="modal fade" id="modalCreateLog" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title font-weight-bold" style="font-size: 1.1rem;">
                    <i class="fas fa-plus-circle mr-1"></i> Add Audit Log Record
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="createLogForm" onsubmit="submitCreateLogForm(event); return false;">
                <input type="hidden" name="fee_action" value="create_fee_log">
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark small">Fee Calculation Type *</label>
                        <select class="form-control" name="fee_type" required>
                            <option value="fixed">Fixed Flat Fee (₹ per order)</option>
                            <option value="percentage">Percentage (%) of Subtotal</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold text-dark small">Previous Fee Amount</label>
                            <input type="number" step="0.1" min="0" class="form-control" name="old_amount" placeholder="e.g. 20.00">
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold text-dark small">New Fee Amount *</label>
                            <input type="number" step="0.1" min="0" class="form-control font-weight-bold text-success" name="new_amount" placeholder="e.g. 25.00" required>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark small">Service Scope</label>
                        <select class="form-control" name="service_scope" id="modal_create_scope" onchange="toggleModalScopeSelect('modal_create_scope', 'modal_create_categories');">
                            <option value="all">All Services (Universal)</option>
                            <option value="specific">Particular Services Only (Subcategories)</option>
                        </select>
                    </div>
                    <div class="form-group mb-3" id="modal_create_categories" style="display: none;">
                        <label class="font-weight-bold text-dark small">Select Applicable Subcategories:</label>
                        <div class="p-2 border rounded bg-light" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach ($categories_with_subs as $cat_title => $subs): ?>
                                <div class="font-weight-bold text-primary small mt-1 mb-1"><?= htmlspecialchars($cat_title) ?></div>
                                <div class="row mb-2">
                                    <?php foreach ($subs as $sub): ?>
                                        <div class="col-md-6">
                                            <div class="custom-control custom-checkbox mb-1">
                                                <input type="checkbox" class="custom-control-input" id="m_add_sub_<?= $sub['sub_id'] ?>" name="applicable_services[]" value="<?= htmlspecialchars($sub['sub_slug']) ?>">
                                                <label class="custom-control-label small text-dark" for="m_add_sub_<?= $sub['sub_id'] ?>"><?= htmlspecialchars($sub['sub_name']) ?></label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark small">Updated By</label>
                        <input type="text" class="form-control" name="updated_by" value="<?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-dark small">Log Notes / Reason</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="e.g. Promotional fee adjustment approved by governance board"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm font-weight-bold px-3">
                        <i class="fas fa-check mr-1"></i> Create Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT AUDIT RECORD (CRUD: UPDATE)                   -->
<!-- ======================================================== -->
<div class="modal fade" id="modalEditLog" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title font-weight-bold" style="font-size: 1.1rem;">
                    <i class="fas fa-edit mr-1"></i> Edit Audit Record <span id="edit-log-id-title"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editLogForm" onsubmit="submitEditLogForm(event); return false;">
                <input type="hidden" name="fee_action" value="update_fee_log">
                <input type="hidden" name="log_id" id="edit_log_id">
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark small">Fee Calculation Type *</label>
                        <select class="form-control" name="fee_type" id="edit_fee_type" required>
                            <option value="fixed">Fixed Flat Fee (₹ per order)</option>
                            <option value="percentage">Percentage (%) of Subtotal</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold text-dark small">Previous Fee Amount</label>
                            <input type="number" step="0.1" min="0" class="form-control" name="old_amount" id="edit_old_amount">
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold text-dark small">New Fee Amount *</label>
                            <input type="number" step="0.1" min="0" class="form-control font-weight-bold text-primary" name="new_amount" id="edit_new_amount" required>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark small">Service Scope</label>
                        <select class="form-control" name="service_scope" id="edit_service_scope" onchange="toggleModalScopeSelect('edit_service_scope', 'edit_categories_container');">
                            <option value="all">All Services (Universal)</option>
                            <option value="specific">Particular Services Only (Subcategories)</option>
                        </select>
                    </div>
                    <div class="form-group mb-3" id="edit_categories_container" style="display: none;">
                        <label class="font-weight-bold text-dark small">Select Applicable Subcategories:</label>
                        <div class="p-2 border rounded bg-light" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach ($categories_with_subs as $cat_title => $subs): ?>
                                <div class="font-weight-bold text-primary small mt-1 mb-1"><?= htmlspecialchars($cat_title) ?></div>
                                <div class="row mb-2">
                                    <?php foreach ($subs as $sub): ?>
                                        <div class="col-md-6">
                                            <div class="custom-control custom-checkbox mb-1">
                                                <input type="checkbox" class="custom-control-input edit-sub-checkbox" id="m_edit_sub_<?= $sub['sub_id'] ?>" name="applicable_services[]" value="<?= htmlspecialchars($sub['sub_slug']) ?>">
                                                <label class="custom-control-label small text-dark" for="m_edit_sub_<?= $sub['sub_id'] ?>"><?= htmlspecialchars($sub['sub_name']) ?></label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark small">Updated By</label>
                        <input type="text" class="form-control" name="updated_by" id="edit_updated_by" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-dark small">Notes / Reason</label>
                        <textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-3">
                        <i class="fas fa-save mr-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: VIEW AUDIT DETAILS (CRUD: READ)                    -->
<!-- ======================================================== -->
<div class="modal fade" id="modalViewLog" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title font-weight-bold" style="font-size: 1.1rem;">
                    <i class="fas fa-info-circle mr-1 text-info"></i> Audit Record Details <span id="view-log-id-title"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <table class="table table-bordered mb-0" style="font-size: 0.9rem;">
                    <tbody>
                        <tr>
                            <th class="bg-light" style="width: 35%;">Record ID</th>
                            <td id="view_log_id" class="font-weight-bold">#</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Timestamp</th>
                            <td id="view_created_at">-</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Calculation Type</th>
                            <td id="view_fee_type">-</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Service Scope</th>
                            <td id="view_service_scope">-</td>
                        </tr>
                        <tr id="view_services_row" style="display: none;">
                            <th class="bg-light">Target Subcategories</th>
                            <td id="view_applicable_services" style="word-break: break-word;">-</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Previous Fee</th>
                            <td id="view_old_amount">-</td>
                        </tr>
                        <tr>
                            <th class="bg-light">New Applied Fee</th>
                            <td id="view_new_amount" class="font-weight-bold text-success">-</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Updated By</th>
                            <td id="view_updated_by">-</td>
                        </tr>
                        <tr>
                            <th class="bg-light">Notes / Reason</th>
                            <td id="view_notes" class="text-muted font-italic">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle service scope container in main settings form
    function handleScopeChange(scope) {
        var container = document.getElementById('specific_services_container');
        if (container) {
            container.style.display = (scope === 'specific') ? 'block' : 'none';
        }
        updateLivePreview();
    }

    // Quick select / deselect all subcategory checkboxes
    function selectAllSubcategories(select) {
        var checkboxes = document.querySelectorAll('.service-checkbox');
        checkboxes.forEach(function(cb) {
            cb.checked = select;
        });
        updateLivePreview();
    }

    // Toggle all subcategories in a specific category group
    function toggleCategorySubs(groupClass) {
        var group = document.querySelector('.' + groupClass);
        if (group) {
            var checks = group.querySelectorAll('input[type="checkbox"]');
            var anyUnchecked = false;
            checks.forEach(function(cb) { if (!cb.checked) anyUnchecked = true; });
            checks.forEach(function(cb) { cb.checked = anyUnchecked; });
            updateLivePreview();
        }
    }

    // Filter subcategories in the checklist in real time
    function filterSubcategories(query) {
        var q = (query || '').toLowerCase().trim();
        var items = document.querySelectorAll('.subcat-item');
        items.forEach(function(item) {
            var name = item.getAttribute('data-name') || '';
            if (!q || name.indexOf(q) !== -1) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }

    // Toggle modal scope select
    function toggleModalScopeSelect(selectId, targetContainerId) {
        var val = document.getElementById(selectId).value;
        var el = document.getElementById(targetContainerId);
        if (el) el.style.display = (val === 'specific') ? 'block' : 'none';
    }

    // Handle Fee Type toggle: swaps labels, symbol prefix, step, and validation
    function handleFeeTypeChange(type) {
        var labelEl = document.getElementById('fee_amount_label');
        var symbolEl = document.getElementById('fee_addon_symbol');
        var inputEl = document.getElementById('fee_amount_input');
        var helpEl = document.getElementById('fee_help_text');

        if (type === 'percentage') {
            if (labelEl) labelEl.innerHTML = '<i class="fas fa-percent text-success mr-1"></i> Convenience Fee Percentage (%) <span class="text-danger">*</span>';
            if (symbolEl) symbolEl.textContent = '%';
            if (inputEl) {
                inputEl.step = '0.1';
                inputEl.min = '0';
                inputEl.max = '100';
                inputEl.placeholder = 'e.g. 5.0';
            }
            if (helpEl) helpEl.innerHTML = 'This percentage (e.g. <strong>5%</strong>) is automatically calculated from each service subtotal amount on customer checkout.';
        } else {
            if (labelEl) labelEl.innerHTML = '<i class="fas fa-rupee-sign text-success mr-1"></i> Convenience Fee Amount (₹) <span class="text-danger">*</span>';
            if (symbolEl) symbolEl.textContent = '₹';
            if (inputEl) {
                inputEl.step = '0.50';
                inputEl.min = '0';
                inputEl.max = '5000';
                inputEl.placeholder = 'e.g. 20.00';
            }
            if (helpEl) helpEl.innerHTML = 'This fixed amount (e.g. <strong>₹20.00</strong>) is automatically added as a flat Convenience Fee on customer booking summaries.';
        }
        updateLivePreview();
    }

    // Live simulator update as inputs change
    function updateLivePreview() {
        var feeInput = document.getElementById('fee_amount_input');
        var isActiveInput = document.getElementById('fee_is_active');
        var isPctRadio = document.getElementById('fee_type_percentage');
        var isPercentage = isPctRadio ? isPctRadio.checked : false;

        var isScopeSpecific = document.getElementById('scope_specific') && document.getElementById('scope_specific').checked;
        var isVehicleWashChecked = true;
        if (isScopeSpecific) {
            var carWash = document.querySelector('.service-checkbox[value="car-wash"]');
            var bikeWash = document.querySelector('.service-checkbox[value="bike-wash"]');
            var othersWash = document.querySelector('.service-checkbox[value="others-wash-services"]');
            isVehicleWashChecked = (carWash && carWash.checked) || (bikeWash && bikeWash.checked) || (othersWash && othersWash.checked);
        }

        var previewFee = document.getElementById('preview-fee-amount');
        var previewFeeLabel = document.getElementById('preview-fee-label');
        var previewTotal = document.getElementById('preview-total-amount');

        var amount = parseFloat(feeInput ? feeInput.value : 0) || 0;
        var isActive = isActiveInput ? isActiveInput.checked : true;

        var baseSubtotal = 119.00;
        var calculatedFee = 0;

        if (isActive && isVehicleWashChecked) {
            if (isPercentage) {
                calculatedFee = (baseSubtotal * amount) / 100;
            } else {
                calculatedFee = amount;
            }
        } else {
            calculatedFee = 0;
        }

        var total = baseSubtotal + calculatedFee;

        if (previewFee) {
            if (!isActive) {
                previewFee.innerHTML = '<span class="text-muted" style="font-size: 0.9rem; text-decoration: line-through;">Waived</span> ₹0.00';
            } else if (!isVehicleWashChecked && isScopeSpecific) {
                previewFee.innerHTML = '<span class="text-warning font-weight-bold" style="font-size: 0.85rem;"><i class="fas fa-ban mr-1"></i>Waived (Subcategory Exempt)</span> ₹0.00';
            } else if (isPercentage) {
                previewFee.innerHTML = '₹' + calculatedFee.toFixed(2) + ' <small class="text-info font-weight-normal" style="font-size: 0.75rem;">(' + amount + '% of ₹' + baseSubtotal.toFixed(2) + ')</small>';
            } else {
                previewFee.textContent = '₹' + calculatedFee.toFixed(2);
            }
        }

        if (previewFeeLabel) {
            if (!isVehicleWashChecked && isScopeSpecific) {
                previewFeeLabel.innerHTML = 'Convenience Fee (Car/Bike Wash Exempt): <i class="fas fa-info-circle ml-1" title="Wash subcategory not selected in fee scope"></i>';
            } else if (isPercentage) {
                previewFeeLabel.innerHTML = 'Convenience Fee (' + amount + '% of Service): <i class="fas fa-info-circle ml-1" title="Percentage calculated on service amount"></i>';
            } else {
                previewFeeLabel.innerHTML = 'Convenience Fee (Fixed Fee): <i class="fas fa-info-circle ml-1" title="Fixed flat fee per order"></i>';
            }
        }

        if (previewTotal) {
            previewTotal.textContent = '₹' + total.toFixed(2);
        }
    }

    // Reset default helper
    function resetFeeForm() {
        document.getElementById('fee_amount_input').value = '20.00';
        document.getElementById('fee_is_active').checked = true;
        document.getElementById('fee_type_fixed').checked = true;
        document.getElementById('scope_all').checked = true;
        handleScopeChange('all');
        document.getElementById('fee_description').value = 'Standard platform convenience fee for India customer bookings';
        handleFeeTypeChange('fixed');
    }

    // Country selection handler
    function handleCountryChange(code) {
        var countryNameInput = document.getElementById('country_name_input');
        if (code === 'IN') {
            if (countryNameInput) countryNameInput.value = 'India';
        }
    }

    // Helper to get exact current dashboard endpoint URL dynamically
    function getFeeEndpointUrl() {
        return (window.location.pathname || '<?= $dashboard_endpoint ?>') + '?ajax_action=fetch_list&type=convenience_fee';
    }

    // Submit convenience fee settings form via AJAX
    function submitConvenienceFeeForm(e) {
        if (e && e.preventDefault) e.preventDefault();

        var form = $('#convenienceFeeForm');
        var btn = $('#btn-save-fee');
        var spinner = $('#save-fee-spinner');
        var icon = $('#save-fee-icon');

        btn.prop('disabled', true);
        spinner.show();
        icon.hide();

        var postUrl = getFeeEndpointUrl();

        $.ajax({
            url: postUrl,
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false);
                spinner.hide();
                icon.show();

                if (res && res.ok) {
                    var newAmt = parseFloat(res.fee_amount) || 0;
                    var isPct = (res.fee_type === 'percentage');
                    $('#info-current-fee').text(isPct ? (newAmt + '% (Percentage)') : ('₹' + newAmt.toFixed(2) + ' (Fixed)'));
                    $('#info-current-type').text(isPct ? 'Percentage of Subtotal' : 'Fixed Flat Fee');

                    var scopeBadge = $('#info-current-scope');
                    if (res.service_scope === 'all') {
                        scopeBadge.removeClass('badge-warning text-dark').addClass('badge-primary').text('All Services');
                    } else {
                        var count = (res.applicable_services && res.applicable_services.length) ? res.applicable_services.length : 0;
                        scopeBadge.removeClass('badge-primary').addClass('badge-warning text-dark').text('Particular Subcategories (' + count + ')');
                    }

                    $('#info-updated-by').text(res.updated_by || 'Admin');
                    $('#info-updated-at').text(res.updated_at || 'Just now');

                    var statusBadge = $('#badge-fee-status');
                    var statusText = $('#badge-fee-status-text');
                    if (res.is_active) {
                        statusBadge.removeClass('badge-secondary').addClass('badge-success');
                        statusText.text('Fee Active');
                    } else {
                        statusBadge.removeClass('badge-success').addClass('badge-secondary');
                        statusText.text('Fee Disabled');
                    }

                    if (typeof Notify !== 'undefined' && Notify.success) {
                        Notify.success(res.message);
                    } else {
                        $('#fee-alert-box')
                            .html('<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle mr-2"></i>' + res.message + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>')
                            .fadeIn();
                    }

                    updateLivePreview();
                    refreshFeeLogsTable();
                } else {
                    var errMsg = (res && res.message) ? res.message : 'Failed to update fee.';
                    if (typeof Notify !== 'undefined' && Notify.error) {
                        Notify.error(errMsg);
                    } else {
                        $('#fee-alert-box')
                            .html('<div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle mr-2"></i>' + errMsg + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>')
                            .fadeIn();
                    }
                }
            },
            error: function() {
                btn.prop('disabled', false);
                spinner.hide();
                icon.show();
                var msg = 'An unexpected server error occurred while saving the convenience fee.';
                if (typeof Notify !== 'undefined' && Notify.error) {
                    Notify.error(msg);
                } else {
                    $('#fee-alert-box')
                        .html('<div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle mr-2"></i>' + msg + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>')
                        .fadeIn();
                }
            }
        });
    }

    // ── CRUD: Refresh Fee Logs Table ───────────────────────────
    function refreshFeeLogsTable() {
        var postUrl = getFeeEndpointUrl();
        var spinIcon = $('#btn-refresh-logs-icon');
        spinIcon.addClass('fa-spin');

        $.ajax({
            url: postUrl,
            type: 'POST',
            data: { fee_action: 'fetch_fee_logs_table' },
            dataType: 'json',
            success: function(res) {
                spinIcon.removeClass('fa-spin');
                if (res && res.ok && res.html) {
                    $('#feeLogsTableBody').html(res.html);
                }
            },
            error: function() {
                spinIcon.removeClass('fa-spin');
            }
        });
    }

    // ── CRUD: Open Create Log Modal ────────────────────────────
    function openCreateLogModal() {
        $('#createLogForm')[0].reset();
        document.getElementById('modal_create_scope').value = 'all';
        document.getElementById('modal_create_categories').style.display = 'none';
        $('#modalCreateLog').modal('show');
    }

    // ── CRUD: Submit Create Log Form ───────────────────────────
    function submitCreateLogForm(e) {
        if (e && e.preventDefault) e.preventDefault();

        var form = $('#createLogForm');
        var postUrl = getFeeEndpointUrl();

        $.ajax({
            url: postUrl,
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(res) {
                if (res && res.ok) {
                    $('#modalCreateLog').modal('hide');
                    if (typeof Notify !== 'undefined' && Notify.success) {
                        Notify.success(res.message);
                    }
                    refreshFeeLogsTable();
                } else {
                    var errMsg = (res && res.message) ? res.message : 'Failed to create log.';
                    if (typeof Notify !== 'undefined' && Notify.error) {
                        Notify.error(errMsg);
                    } else {
                        alert(errMsg);
                    }
                }
            },
            error: function() {
                var errMsg = 'An error occurred while creating audit log entry.';
                if (typeof Notify !== 'undefined' && Notify.error) {
                    Notify.error(errMsg);
                } else {
                    alert(errMsg);
                }
            }
        });
    }

    // ── CRUD: View Log Record Details ──────────────────────────
    function viewLogRecord(logId) {
        var postUrl = getFeeEndpointUrl();

        $.ajax({
            url: postUrl,
            type: 'POST',
            data: { fee_action: 'get_fee_log', log_id: logId },
            dataType: 'json',
            success: function(res) {
                if (res && res.ok && res.data) {
                    var d = res.data;
                    $('#view-log-id-title').text('#' + d.id);
                    $('#view_log_id').text('#' + d.id);
                    $('#view_created_at').text(d.created_at || '-');

                    var isPct = (d.fee_type === 'percentage');
                    $('#view_fee_type').html(isPct ? '<span class="badge badge-info">Percentage (%) of Subtotal</span>' : '<span class="badge badge-secondary">Fixed Flat Fee (₹)</span>');

                    var scope = d.service_scope || 'all';
                    $('#view_service_scope').html(scope === 'all' ? '<span class="badge badge-primary">All Services</span>' : '<span class="badge badge-warning text-dark">Particular Subcategories</span>');

                    if (scope === 'specific' && d.applicable_services_list && d.applicable_services_list.length > 0) {
                        $('#view_services_row').show();
                        $('#view_applicable_services').text(d.applicable_services_list.join(', '));
                    } else {
                        $('#view_services_row').hide();
                    }

                    var oldStr = (d.old_amount !== null && d.old_amount !== '') ? (isPct ? d.old_amount + '%' : '₹' + parseFloat(d.old_amount).toFixed(2)) : 'Initial';
                    var newStr = isPct ? d.new_amount + '%' : '₹' + parseFloat(d.new_amount).toFixed(2);
                    $('#view_old_amount').text(oldStr);
                    $('#view_new_amount').text(newStr);
                    $('#view_updated_by').text(d.updated_by || 'Admin');
                    $('#view_notes').text(d.notes || 'No specific notes recorded');

                    $('#modalViewLog').modal('show');
                } else {
                    var errMsg = 'Log record not found.';
                    if (typeof Notify !== 'undefined' && Notify.error) Notify.error(errMsg); else alert(errMsg);
                }
            },
            error: function() {
                var errMsg = 'Failed to retrieve log details.';
                if (typeof Notify !== 'undefined' && Notify.error) Notify.error(errMsg); else alert(errMsg);
            }
        });
    }

    // ── CRUD: Edit Log Record ──────────────────────────────────
    function editLogRecord(logId) {
        var postUrl = getFeeEndpointUrl();

        $.ajax({
            url: postUrl,
            type: 'POST',
            data: { fee_action: 'get_fee_log', log_id: logId },
            dataType: 'json',
            success: function(res) {
                if (res && res.ok && res.data) {
                    var d = res.data;
                    $('#edit_log_id').val(d.id);
                    $('#edit-log-id-title').text('#' + d.id);
                    $('#edit_fee_type').val(d.fee_type || 'fixed');
                    $('#edit_old_amount').val(d.old_amount !== null ? d.old_amount : '');
                    $('#edit_new_amount').val(d.new_amount !== null ? d.new_amount : '');
                    $('#edit_updated_by').val(d.updated_by || 'Admin');
                    $('#edit_notes').val(d.notes || '');

                    var scope = d.service_scope || 'all';
                    $('#edit_service_scope').val(scope);
                    toggleModalScopeSelect('edit_service_scope', 'edit_categories_container');

                    // Check subcategories
                    var selected = d.applicable_services_list || [];
                    $('.edit-sub-checkbox').each(function() {
                        $(this).prop('checked', selected.indexOf($(this).val()) !== -1);
                    });

                    $('#modalEditLog').modal('show');
                } else {
                    var errMsg = 'Log record not found.';
                    if (typeof Notify !== 'undefined' && Notify.error) Notify.error(errMsg); else alert(errMsg);
                }
            },
            error: function() {
                var errMsg = 'Failed to retrieve log record for editing.';
                if (typeof Notify !== 'undefined' && Notify.error) Notify.error(errMsg); else alert(errMsg);
            }
        });
    }

    // ── CRUD: Submit Edit Log Form ─────────────────────────────
    function submitEditLogForm(e) {
        if (e && e.preventDefault) e.preventDefault();

        var form = $('#editLogForm');
        var postUrl = getFeeEndpointUrl();

        $.ajax({
            url: postUrl,
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(res) {
                if (res && res.ok) {
                    $('#modalEditLog').modal('hide');
                    if (typeof Notify !== 'undefined' && Notify.success) {
                        Notify.success(res.message);
                    }
                    refreshFeeLogsTable();
                } else {
                    var errMsg = (res && res.message) ? res.message : 'Failed to update log.';
                    if (typeof Notify !== 'undefined' && Notify.error) Notify.error(errMsg); else alert(errMsg);
                }
            },
            error: function() {
                var errMsg = 'An error occurred while updating the audit log record.';
                if (typeof Notify !== 'undefined' && Notify.error) Notify.error(errMsg); else alert(errMsg);
            }
        });
    }

    // ── CRUD: Delete Log Record ────────────────────────────────
    function deleteLogRecord(logId) {
        var confirmMsg = 'Are you sure you want to permanently delete audit record #' + logId + '? This action cannot be undone.';
        
        var proceed = function() {
            var postUrl = getFeeEndpointUrl();
            $.ajax({
                url: postUrl,
                type: 'POST',
                data: { fee_action: 'delete_fee_log', log_id: logId },
                dataType: 'json',
                success: function(res) {
                    if (res && res.ok) {
                        if (typeof Notify !== 'undefined' && Notify.success) {
                            Notify.success(res.message);
                        } else {
                            alert(res.message);
                        }
                        $('#fee-log-row-' + logId).fadeOut(300, function() {
                            $(this).remove();
                            refreshFeeLogsTable();
                        });
                    } else {
                        var errMsg = (res && res.message) ? res.message : 'Failed to delete record.';
                        if (typeof Notify !== 'undefined' && Notify.error) {
                            Notify.error(errMsg);
                        } else {
                            alert(errMsg);
                        }
                    }
                },
                error: function(xhr, status, err) {
                    var errMsg = 'Error deleting audit log record #' + logId + '.';
                    if (typeof Notify !== 'undefined' && Notify.error) {
                        Notify.error(errMsg);
                    } else {
                        alert(errMsg);
                    }
                }
            });
        };

        if (typeof Notify !== 'undefined' && typeof Notify.confirm === 'function') {
            Notify.confirm(confirmMsg, {
                title: 'Delete Audit Record',
                confirmText: 'Yes, Delete',
                cancelText: 'Cancel',
                type: 'danger'
            }).then(function(confirmed) {
                if (confirmed) {
                    proceed();
                }
            }).catch(function(err) {
                console.error('Notify.confirm error:', err);
                if (confirm(confirmMsg)) {
                    proceed();
                }
            });
        } else if (confirm(confirmMsg)) {
            proceed();
        }
    }

    // Initialize display state on component load
    (function initFeeState() {
        var currentFeeType = <?= json_encode($fee_type) ?>;
        handleFeeTypeChange(currentFeeType);
        handleScopeChange(<?= json_encode($service_scope) ?>);
    })();
</script>
