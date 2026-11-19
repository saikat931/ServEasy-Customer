<?php
/**
 * ServEasy Category & Subcategory Management Component
 * Reusable across Admin Dashboard and Superadmin Dashboard
 * Targets: `serveasy-categories` and `serveasy-subcategories`
 */

if (!isset($pdo)) {
    $cfgPath = file_exists(__DIR__ . '/../common/db_connection.php')
        ? __DIR__ . '/../common/db_connection.php'
        : (file_exists(__DIR__ . '/../../backend/config/db_connection.php') ? __DIR__ . '/../../backend/config/db_connection.php' : null);
    if ($cfgPath) require_once $cfgPath;
    $pdo = Database::connect();
}

$is_super_admin_ctx = isset($is_super_admin) && $is_super_admin;
$dashboard_endpoint = $is_super_admin_ctx ? 'superadmin-dashboard.php' : 'admin-dashboard.php';
if (!file_exists($dashboard_endpoint) && file_exists('admin-dashboard.php')) {
    $dashboard_endpoint = 'admin-dashboard.php';
}

// ── Handle Category & Subcategory AJAX Actions ─────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['cat_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['cat_action'];

    try {
        // 1. Toggle Category Status (Start / Stop)
        if ($action === 'toggle_category') {
            $cat_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
            $new_status = !empty($_POST['is_active']) ? 1 : 0;

            if ($cat_id <= 0) {
                echo json_encode(['ok' => false, 'message' => 'Invalid category identifier.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE `serveasy-categories` SET `is_active` = ? WHERE `id` = ?");
            $stmt->execute([$new_status, $cat_id]);

            // Re-fetch category name
            $name_stmt = $pdo->prepare("SELECT `name` FROM `serveasy-categories` WHERE `id` = ?");
            $name_stmt->execute([$cat_id]);
            $cat_name = $name_stmt->fetchColumn() ?: "Category #{$cat_id}";

            $status_text = $new_status ? 'Started (Active)' : 'Stopped (Inactive)';
            echo json_encode([
                'ok' => true,
                'type' => 'category',
                'category_id' => $cat_id,
                'is_active' => $new_status,
                'name' => $cat_name,
                'message' => "Category \"{$cat_name}\" is now {$status_text}."
            ]);
            exit;
        }

        // 2. Toggle Subcategory Status (Start / Stop)
        if ($action === 'toggle_subcategory') {
            $sub_id = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : 0;
            $new_status = !empty($_POST['is_active']) ? 1 : 0;

            if ($sub_id <= 0) {
                echo json_encode(['ok' => false, 'message' => 'Invalid subcategory identifier.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE `serveasy-subcategories` SET `is_active` = ? WHERE `id` = ?");
            $stmt->execute([$new_status, $sub_id]);

            // Re-fetch subcategory name and category_id
            $name_stmt = $pdo->prepare("SELECT `name`, `category_id` FROM `serveasy-subcategories` WHERE `id` = ?");
            $name_stmt->execute([$sub_id]);
            $sub_row = $name_stmt->fetch(PDO::FETCH_ASSOC);
            $sub_name = $sub_row['name'] ?? "Subcategory #{$sub_id}";
            $parent_cat_id = $sub_row['category_id'] ?? 0;

            $status_text = $new_status ? 'Started (Active)' : 'Stopped (Inactive)';
            echo json_encode([
                'ok' => true,
                'type' => 'subcategory',
                'subcategory_id' => $sub_id,
                'category_id' => $parent_cat_id,
                'is_active' => $new_status,
                'name' => $sub_name,
                'message' => "Subcategory \"{$sub_name}\" is now {$status_text}."
            ]);
            exit;
        }

        // 3. Bulk Toggle All Subcategories Under a Category
        if ($action === 'bulk_toggle_subcategories') {
            $cat_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
            $new_status = !empty($_POST['is_active']) ? 1 : 0;

            if ($cat_id <= 0) {
                echo json_encode(['ok' => false, 'message' => 'Invalid category identifier.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE `serveasy-subcategories` SET `is_active` = ? WHERE `category_id` = ?");
            $stmt->execute([$new_status, $cat_id]);
            $affected = $stmt->rowCount();

            $status_text = $new_status ? 'Started (Active)' : 'Stopped (Inactive)';
            echo json_encode([
                'ok' => true,
                'type' => 'bulk_subcategories',
                'category_id' => $cat_id,
                'is_active' => $new_status,
                'affected' => $affected,
                'message' => "All {$affected} subcategories have been {$status_text}."
            ]);
            exit;
        }

        // 4. Update Category Details (Name, Slug, Icon)
        if ($action === 'update_category_meta') {
            $cat_id = (int)($_POST['category_id'] ?? 0);
            $name   = trim($_POST['name'] ?? '');
            $slug   = trim($_POST['slug'] ?? '');
            $icon   = trim($_POST['icon_class'] ?? '');

            if ($cat_id <= 0 || empty($name) || empty($slug)) {
                echo json_encode(['ok' => false, 'message' => 'Category ID, name and slug are required.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE `serveasy-categories` SET `name` = ?, `slug` = ?, `icon_class` = ? WHERE `id` = ?");
            $stmt->execute([$name, $slug, $icon, $cat_id]);

            echo json_encode(['ok' => true, 'message' => "Category \"{$name}\" updated successfully."]);
            exit;
        }

        // 5. Update Subcategory Details (Name, Slug, Icon)
        if ($action === 'update_subcategory_meta') {
            $sub_id = (int)($_POST['subcategory_id'] ?? 0);
            $name   = trim($_POST['name'] ?? '');
            $slug   = trim($_POST['slug'] ?? '');
            $icon   = trim($_POST['icon_class'] ?? '');

            if ($sub_id <= 0 || empty($name) || empty($slug)) {
                echo json_encode(['ok' => false, 'message' => 'Subcategory ID, name and slug are required.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE `serveasy-subcategories` SET `name` = ?, `slug` = ?, `icon_class` = ? WHERE `id` = ?");
            $stmt->execute([$name, $slug, $icon, $sub_id]);

            echo json_encode(['ok' => true, 'message' => "Subcategory \"{$name}\" updated successfully."]);
            exit;
        }

        echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// ── GET View: Query Categories, Subcategories, and Service Counts ───────
try {
    // 1. All Categories
    $cat_stmt = $pdo->query("SELECT * FROM `serveasy-categories` ORDER BY `id` ASC");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 2. Subcategories with service count
    $sub_stmt = $pdo->query("
        SELECT 
            sub.*,
            c.name AS category_name,
            c.is_active AS category_active,
            COUNT(s.id) AS service_count
        FROM `serveasy-subcategories` sub
        LEFT JOIN `serveasy-categories` c ON sub.category_id = c.id
        LEFT JOIN `serveasy-services` s ON s.subcategory_id = sub.id
        GROUP BY sub.id
        ORDER BY sub.category_id ASC, sub.id ASC
    ");
    $subcategories_raw = $sub_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Group subcategories by category_id
    $subcategories_by_cat = [];
    foreach ($subcategories_raw as $sub) {
        $subcategories_by_cat[$sub['category_id']][] = $sub;
    }

    // Metric Counters
    $total_categories = count($categories);
    $active_categories = 0;
    $stopped_categories = 0;
    foreach ($categories as $cat) {
        if (!empty($cat['is_active'])) $active_categories++;
        else $stopped_categories++;
    }

    $total_subcategories = count($subcategories_raw);
    $active_subcategories = 0;
    $stopped_subcategories = 0;
    foreach ($subcategories_raw as $sub) {
        if (!empty($sub['is_active'])) $active_subcategories++;
        else $stopped_subcategories++;
    }

} catch (Exception $e) {
    echo '<div class="alert alert-danger m-3">Error fetching categories: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

// Helper icon mapper from Ionicons names to FontAwesome
function get_fa_icon($icon_class, $default = 'fas fa-th-large') {
    $map = [
        'car-sport-outline'     => 'fas fa-car-side',
        'car-outline'           => 'fas fa-car',
        'bicycle-outline'       => 'fas fa-bicycle',
        'home-outline'          => 'fas fa-home',
        'people-outline'        => 'fas fa-user-friends',
        'maid-icon'             => 'fas fa-broom',
        'shield-outline'        => 'fas fa-user-shield',
        'restaurant-outline'    => 'fas fa-utensils',
        'heart-outline'         => 'fas fa-heartbeat',
        'truck-outline'         => 'fas fa-truck',
        'bus-outline'           => 'fas fa-bus',
        'ticket-outline'        => 'fas fa-ticket-alt',
        'bed-outline'           => 'fas fa-bed',
        'business-outline'      => 'fas fa-building',
        'compass-outline'       => 'fas fa-compass',
        'map-outline'           => 'fas fa-map-marked-alt',
        'locate-outline'        => 'fas fa-map-marker-alt',
        'fast-food-outline'     => 'fas fa-hamburger',
        'pizza-outline'         => 'fas fa-pizza-slice',
        'medkit-outline'        => 'fas fa-briefcase-medical',
        'key-outline'           => 'fas fa-key',
        'build-outline'         => 'fas fa-tools',
        'sparkles-outline'      => 'fas fa-sparkles',
        'hardware-chip-outline' => 'fas fa-microchip',
        'shirt-outline'         => 'fas fa-tshirt',
        'shield-alert-outline'  => 'fas fa-exclamation-triangle',
    ];
    return $map[$icon_class] ?? $default;
}
?>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- CATEGORY & SUBCATEGORY MANAGEMENT (LIST DESIGN WITH AJAX TOGGLES)    -->
<!-- ══════════════════════════════════════════════════════════════════════ -->

<style>
/* ── Category Management View Styles ── */
.cat-mgmt-wrapper {
    animation: fadeIn .25s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* KPI Cards */
.cat-kpi-card {
    border-radius: 12px;
    border: none;
    transition: transform .2s ease, box-shadow .2s ease;
}
.cat-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08) !important;
}

/* List Item Card */
.category-list-card {
    border-radius: 12px;
    border: 1px solid #e3e8ee;
    background: #fff;
    margin-bottom: 1rem;
    transition: all .25s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    overflow: hidden;
}
.category-list-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 6px 20px rgba(0,0,0,0.06);
}
.category-list-card.is-stopped {
    border-left: 5px solid #94a3b8;
    background: #fafbfc;
}
.category-list-card.is-active {
    border-left: 5px solid #05c279;
}

/* Header Row of Category */
.category-header-row {
    padding: 1.15rem 1.4rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.category-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #e8f9f1 0%, #d1f4e4 100%);
    color: #05c279;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
    transition: all .2s ease;
}
.category-list-card.is-stopped .category-icon-box {
    background: #f1f5f9;
    color: #64748b;
}

/* Custom iOS-style Switch */
.toggle-switch-container {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    user-select: none;
}
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
    margin-bottom: 0;
}
.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #cbd5e1;
    transition: .3s;
    border-radius: 34px;
}
.toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.toggle-switch input:checked + .toggle-slider {
    background-color: #05c279;
}
.toggle-switch input:focus + .toggle-slider {
    box-shadow: 0 0 1px #05c279;
}
.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(24px);
}
.toggle-switch.disabled {
    opacity: 0.5;
    pointer-events: none;
}

/* Mini Switch for Subcategories */
.toggle-switch-sm {
    width: 42px;
    height: 22px;
}
.toggle-switch-sm .toggle-slider:before {
    height: 16px;
    width: 16px;
    left: 3px;
    bottom: 3px;
}
.toggle-switch-sm input:checked + .toggle-slider:before {
    transform: translateX(20px);
}

/* Subcategories Container */
.subcategories-container {
    background: #f8fafc;
    border-top: 1px solid #edf2f7;
    padding: 1.2rem 1.4rem;
}
.subcategory-list-item {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    margin-bottom: 0.6rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all .2s ease;
}
.subcategory-list-item:hover {
    border-color: #cbd5e1;
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}
.subcategory-list-item.sub-stopped {
    background: #fbfcfe;
    border-left: 4px solid #94a3b8;
}
.subcategory-list-item.sub-active {
    border-left: 4px solid #05c279;
}

/* Status Badges */
.badge-status-active {
    background: #e6f9f1;
    color: #04975d;
    font-weight: 600;
    padding: 0.4em 0.75em;
    border-radius: 20px;
    font-size: 0.78rem;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-status-stopped {
    background: #f1f5f9;
    color: #64748b;
    font-weight: 600;
    padding: 0.4em 0.75em;
    border-radius: 20px;
    font-size: 0.78rem;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* Floating Toast Notification */
#cat-mgmt-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 10000;
    min-width: 290px;
    max-width: 420px;
    background: #1e293b;
    color: #fff;
    padding: 14px 18px;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    display: none;
    align-items: center;
    gap: 12px;
    font-size: 0.88rem;
    animation: slideInRight .25s ease-out;
}
@keyframes slideInRight {
    from { opacity: 0; transform: translateX(30px); }
    to { opacity: 1; transform: translateX(0); }
}
#cat-mgmt-toast.toast-success {
    border-left: 4px solid #05c279;
}
#cat-mgmt-toast.toast-danger {
    border-left: 4px solid #e53e3e;
}
</style>

<div class="cat-mgmt-wrapper">

    <!-- ── Page Header ────────────────────────────────────────────────── -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800 font-weight-bold">
                <i class="fas fa-layer-group text-primary mr-2" style="color:#05c279 !important;"></i>Categories & Subcategories
            </h1>
            <p class="text-muted small mb-0">
                Manage live status (Start/Stop) of service categories and subcategories shown on the Home Page and Service Directory.
            </p>
        </div>
        <div class="mt-3 mt-sm-0 d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary shadow-sm mr-2" onclick="expandAllCategories();">
                <i class="fas fa-expand-alt mr-1"></i> Expand All
            </button>
            <button class="btn btn-sm btn-outline-secondary shadow-sm mr-2" onclick="collapseAllCategories();">
                <i class="fas fa-compress-alt mr-1"></i> Collapse All
            </button>
            <button class="btn btn-sm btn-primary shadow-sm" style="background:#05c279; border-color:#05c279;" onclick="reloadCategoryList();">
                <i class="fas fa-sync-alt mr-1"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ── KPI Summary Cards ─────────────────────────────────────────── -->
    <div class="row mb-4">
        <!-- Total Categories -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card cat-kpi-card shadow-sm h-100 py-2 border-left-primary">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Categories</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="stat-total-cats"><?= $total_categories ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-layer-group fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Categories -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card cat-kpi-card shadow-sm h-100 py-2 border-left-success">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Categories</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span id="stat-active-cats"><?= $active_categories ?></span>
                                <small class="text-muted font-weight-normal text-xs ml-1">/ <?= $stopped_categories ?> Stopped</small>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-toggle-on fa-2x text-success" style="opacity:0.4;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Subcategories -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card cat-kpi-card shadow-sm h-100 py-2 border-left-info">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Subcategories</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="stat-total-subs"><?= $total_subcategories ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tags fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Subcategories -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card cat-kpi-card shadow-sm h-100 py-2 border-left-warning">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Active Subcategories</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span id="stat-active-subs"><?= $active_subcategories ?></span>
                                <small class="text-muted font-weight-normal text-xs ml-1">/ <?= $stopped_subcategories ?> Stopped</small>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-double fa-2x text-warning" style="opacity:0.4;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Filter & Search Toolbar ────────────────────────────────────── -->
    <div class="card shadow-sm mb-4 border-0" style="border-radius:12px;">
        <div class="card-body py-3">
            <div class="row align-items-center">
                <div class="col-md-5 mb-2 mb-md-0">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" id="catSearchInput" class="form-control border-left-0" placeholder="Search category, subcategory, or slug..." onkeyup="filterCategoriesList();">
                    </div>
                </div>
                <div class="col-md-7 d-flex justify-content-md-end align-items-center flex-wrap gap-2">
                    <div class="btn-group btn-group-toggle" data-toggle="buttons">
                        <label class="btn btn-sm btn-outline-secondary active" onclick="setStatusFilter('all');">
                            <input type="radio" name="options" checked> All (<span id="btn-count-all"><?= $total_categories ?></span>)
                        </label>
                        <label class="btn btn-sm btn-outline-success" onclick="setStatusFilter('active');">
                            <input type="radio" name="options"> Active (<span id="btn-count-active"><?= $active_categories ?></span>)
                        </label>
                        <label class="btn btn-sm btn-outline-secondary" onclick="setStatusFilter('stopped');">
                            <input type="radio" name="options"> Stopped (<span id="btn-count-stopped"><?= $stopped_categories ?></span>)
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Categories List View ───────────────────────────────────────── -->
    <div id="categoriesListContainer">
        <?php foreach ($categories as $cat): 
            $cat_id = (int)$cat['id'];
            $is_cat_active = !empty($cat['is_active']);
            $cat_subs = $subcategories_by_cat[$cat_id] ?? [];
            $sub_count = count($cat_subs);
            $active_subs_count = 0;
            $cat_total_services = 0;
            foreach ($cat_subs as $s) {
                if (!empty($s['is_active'])) $active_subs_count++;
                $cat_total_services += (int)$s['service_count'];
            }
            $fa_icon = get_fa_icon($cat['icon_class']);
        ?>
        <div class="category-list-card <?= $is_cat_active ? 'is-active' : 'is-stopped' ?>" 
             id="cat-card-<?= $cat_id ?>"
             data-cat-id="<?= $cat_id ?>"
             data-status="<?= $is_cat_active ? 'active' : 'stopped' ?>"
             data-name="<?= htmlspecialchars(strtolower($cat['name'])) ?>"
             data-slug="<?= htmlspecialchars(strtolower($cat['slug'])) ?>">

            <!-- Main Category Row -->
            <div class="category-header-row">
                <!-- Left: Icon, Name & Slug -->
                <div class="d-flex align-items-center" style="gap: 1rem; min-width: 280px; flex: 1;">
                    <div class="category-icon-box" id="cat-icon-box-<?= $cat_id ?>">
                        <i class="<?= htmlspecialchars($fa_icon) ?>"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center" style="gap: 8px;">
                            <h5 class="mb-0 font-weight-bold text-gray-800" style="font-size: 1.05rem;" id="cat-name-<?= $cat_id ?>">
                                <?= htmlspecialchars($cat['name']) ?>
                            </h5>
                            <span class="badge badge-light border text-muted small px-2 py-1 font-weight-normal" title="URL Slug">
                                <i class="fas fa-link mr-1 text-gray-400"></i><?= htmlspecialchars($cat['slug']) ?>
                            </span>
                        </div>
                        <div class="text-muted small mt-1 d-flex align-items-center flex-wrap" style="gap: 12px;">
                            <span>
                                <i class="fas fa-tags text-gray-400 mr-1"></i>
                                <strong class="text-gray-700" id="cat-sub-count-<?= $cat_id ?>"><?= $sub_count ?></strong> Subcategories
                            </span>
                            <span>
                                <i class="fas fa-concierge-bell text-gray-400 mr-1"></i>
                                <strong class="text-gray-700"><?= $cat_total_services ?></strong> Live Services
                            </span>
                            <span class="text-muted">• ID: #<?= $cat_id ?></span>
                        </div>
                    </div>
                </div>

                <!-- Center: Status Badge -->
                <div class="d-flex align-items-center" style="gap: 1rem;">
                    <span id="cat-badge-<?= $cat_id ?>" class="<?= $is_cat_active ? 'badge-status-active' : 'badge-status-stopped' ?>">
                        <i class="fas <?= $is_cat_active ? 'fa-check-circle' : 'fa-pause-circle' ?>"></i>
                        <span id="cat-badge-text-<?= $cat_id ?>"><?= $is_cat_active ? 'Active / Live' : 'Stopped / Hidden' ?></span>
                    </span>
                </div>

                <!-- Right: Toggle Button & Expand Control -->
                <div class="d-flex align-items-center" style="gap: 1.2rem;">
                    <!-- Category Start/Stop Toggle Switch -->
                    <div class="toggle-switch-container">
                        <label class="toggle-switch" title="Toggle category Live/Stopped status">
                            <input type="checkbox" 
                                   id="cat-toggle-<?= $cat_id ?>" 
                                   data-cat-id="<?= $cat_id ?>"
                                   <?= $is_cat_active ? 'checked' : '' ?>
                                   onchange="handleCategoryToggle(this);">
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="small font-weight-bold" id="cat-toggle-label-<?= $cat_id ?>" style="min-width: 48px; color: <?= $is_cat_active ? '#05c279' : '#64748b' ?>;">
                            <?= $is_cat_active ? 'Active' : 'Stopped' ?>
                        </span>
                    </div>

                    <!-- Expand/Collapse Button -->
                    <button class="btn btn-sm btn-light border px-3" 
                            type="button" 
                            data-toggle="collapse" 
                            data-target="#cat-subs-collapse-<?= $cat_id ?>" 
                            aria-expanded="false" 
                            aria-controls="cat-subs-collapse-<?= $cat_id ?>"
                            title="View child subcategories">
                        <i class="fas fa-chevron-down mr-1"></i>
                        <span>Subcategories (<?= $sub_count ?>)</span>
                    </button>
                </div>
            </div>

            <!-- Collapsible Subcategories List Container -->
            <div class="collapse" id="cat-subs-collapse-<?= $cat_id ?>">
                <div class="subcategories-container">
                    
                    <!-- Subcategory Header Row -->
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <div class="small text-muted font-weight-bold text-uppercase">
                            <i class="fas fa-list-ul mr-1 text-primary"></i> Subcategories in <?= htmlspecialchars($cat['name']) ?>
                            <span class="badge badge-pill badge-secondary ml-1"><?= $sub_count ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="small text-muted mr-2">Quick Actions:</span>
                            <button class="btn btn-xs btn-outline-success mr-1 py-1 px-2" style="font-size:0.75rem;" onclick="bulkToggleSubcategories(<?= $cat_id ?>, 1);">
                                <i class="fas fa-check-circle mr-1"></i> Start All
                            </button>
                            <button class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size:0.75rem;" onclick="bulkToggleSubcategories(<?= $cat_id ?>, 0);">
                                <i class="fas fa-pause-circle mr-1"></i> Stop All
                            </button>
                        </div>
                    </div>

                    <?php if (empty($cat_subs)): ?>
                        <div class="alert alert-light border small text-muted mb-0 py-2">
                            <i class="fas fa-info-circle mr-1"></i> No subcategories configured under this category.
                        </div>
                    <?php else: ?>
                        <div class="subcategory-items-list" id="subs-list-<?= $cat_id ?>">
                            <?php foreach ($cat_subs as $sub): 
                                $sub_id = (int)$sub['id'];
                                $is_sub_active = !empty($sub['is_active']);
                                $sub_icon = get_fa_icon($sub['icon_class'], 'fas fa-tag');
                            ?>
                            <div class="subcategory-list-item <?= $is_sub_active ? 'sub-active' : 'sub-stopped' ?>"
                                 id="sub-item-<?= $sub_id ?>"
                                 data-sub-id="<?= $sub_id ?>"
                                 data-cat-id="<?= $cat_id ?>"
                                 data-status="<?= $is_sub_active ? 'active' : 'stopped' ?>"
                                 data-name="<?= htmlspecialchars(strtolower($sub['name'])) ?>"
                                 data-slug="<?= htmlspecialchars(strtolower($sub['slug'])) ?>">

                                <!-- Left: Icon, Subcategory Name & Slug -->
                                <div class="d-flex align-items-center" style="gap: 12px; flex: 1;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width:32px; height:32px; background:#eef2f6; color:#475569; font-size:0.85rem;">
                                        <i class="<?= htmlspecialchars($sub_icon) ?>"></i>
                                    </div>
                                    <div>
                                        <div class="font-weight-bold text-gray-800" style="font-size:0.92rem;">
                                            <?= htmlspecialchars($sub['name']) ?>
                                            <span class="badge badge-light border text-muted ml-2 font-weight-normal small">
                                                <?= htmlspecialchars($sub['slug']) ?>
                                            </span>
                                        </div>
                                        <div class="small text-muted">
                                            <span><i class="fas fa-wrench mr-1 text-gray-400"></i><?= (int)$sub['service_count'] ?> Active Services</span>
                                            <span class="ml-2">• Sub ID: #<?= $sub_id ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Center: Subcategory Status Badge -->
                                <div class="d-flex align-items-center mr-3">
                                    <span id="sub-badge-<?= $sub_id ?>" class="<?= $is_sub_active ? 'badge-status-active' : 'badge-status-stopped' ?>">
                                        <i class="fas <?= $is_sub_active ? 'fa-check-circle' : 'fa-pause-circle' ?>"></i>
                                        <span id="sub-badge-text-<?= $sub_id ?>"><?= $is_sub_active ? 'Active' : 'Stopped' ?></span>
                                    </span>
                                </div>

                                <!-- Right: Subcategory Start/Stop Toggle Button -->
                                <div class="d-flex align-items-center">
                                    <div class="toggle-switch-container">
                                        <label class="toggle-switch toggle-switch-sm" title="Toggle subcategory Live/Stopped status">
                                            <input type="checkbox" 
                                                   id="sub-toggle-<?= $sub_id ?>" 
                                                   data-sub-id="<?= $sub_id ?>"
                                                   data-cat-id="<?= $cat_id ?>"
                                                   <?= $is_sub_active ? 'checked' : '' ?>
                                                   onchange="handleSubcategoryToggle(this);">
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="small font-weight-bold" id="sub-toggle-label-<?= $sub_id ?>" style="min-width: 44px; color: <?= $is_sub_active ? '#05c279' : '#64748b' ?>;">
                                            <?= $is_sub_active ? 'Start' : 'Stop' ?>
                                        </span>
                                    </div>
                                </div>

                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- ── Toast Feedback Notification ──────────────────────────────────── -->
<div id="cat-mgmt-toast" class="toast-success">
    <div id="cat-toast-icon"><i class="fas fa-check-circle text-success fa-lg"></i></div>
    <div id="cat-toast-message">Operation successful</div>
</div>

<!-- ── Category & Subcategory AJAX JavaScript Logic ──────────────────── -->
<script>
(function() {
    // Current endpoint URL for AJAX requests
    var endpoint = '<?= $dashboard_endpoint ?>';
    var toastTimeout = null;

    // Toast notification display helper
    window.showToast = function(message, isError = false) {
        var $toast = $('#cat-mgmt-toast');
        var $msg = $('#cat-toast-message');
        var $icon = $('#cat-toast-icon');

        clearTimeout(toastTimeout);
        $msg.text(message);

        if (isError) {
            $toast.removeClass('toast-success').addClass('toast-danger');
            $icon.html('<i class="fas fa-exclamation-triangle text-danger fa-lg"></i>');
        } else {
            $toast.removeClass('toast-danger').addClass('toast-success');
            $icon.html('<i class="fas fa-check-circle text-success fa-lg"></i>');
        }

        $toast.fadeIn(200);
        toastTimeout = setTimeout(function() {
            $toast.fadeOut(300);
        }, 3200);
    };

    // ── 1. Handle Category Start / Stop Toggle ──
    window.handleCategoryToggle = function(checkbox) {
        var $cb = $(checkbox);
        var catId = $cb.data('cat-id');
        var isChecked = $cb.is(':checked') ? 1 : 0;
        var $card = $('#cat-card-' + catId);
        var $label = $('#cat-toggle-label-' + catId);
        var $badge = $('#cat-badge-' + catId);
        var $badgeText = $('#cat-badge-text-' + catId);

        // Visual loading state
        $cb.prop('disabled', true);
        $label.text('Saving...');

        $.ajax({
            url: endpoint + (endpoint.indexOf('?') !== -1 ? '&' : '?') + 'ajax_action=fetch_list&type=categories',
            method: 'POST',
            data: {
                cat_action: 'toggle_category',
                category_id: catId,
                is_active: isChecked
            },
            dataType: 'json',
            success: function(res) {
                $cb.prop('disabled', false);
                if (res && res.ok) {
                    if (isChecked) {
                        $card.removeClass('is-stopped').addClass('is-active').attr('data-status', 'active');
                        $label.text('Active').css('color', '#05c279');
                        $badge.removeClass('badge-status-stopped').addClass('badge-status-active');
                        $badge.find('i').removeClass('fa-pause-circle').addClass('fa-check-circle');
                        $badgeText.text('Active / Live');
                        showToast(res.message || 'Category started successfully.');
                    } else {
                        $card.removeClass('is-active').addClass('is-stopped').attr('data-status', 'stopped');
                        $label.text('Stopped').css('color', '#64748b');
                        $badge.removeClass('badge-status-active').addClass('badge-status-stopped');
                        $badge.find('i').removeClass('fa-check-circle').addClass('fa-pause-circle');
                        $badgeText.text('Stopped / Hidden');
                        showToast(res.message || 'Category stopped successfully.');
                    }
                    updateCategoryCounters();
                } else {
                    // Revert state on failure
                    $cb.prop('checked', !isChecked);
                    $label.text(!isChecked ? 'Active' : 'Stopped');
                    showToast(res.message || 'Failed to update category status.', true);
                }
            },
            error: function(xhr, status, err) {
                $cb.prop('disabled', false);
                $cb.prop('checked', !isChecked);
                $label.text(!isChecked ? 'Active' : 'Stopped');
                showToast('Server communication error. Please try again.', true);
            }
        });
    };

    // ── 2. Handle Subcategory Start / Stop Toggle ──
    window.handleSubcategoryToggle = function(checkbox) {
        var $cb = $(checkbox);
        var subId = $cb.data('sub-id');
        var catId = $cb.data('cat-id');
        var isChecked = $cb.is(':checked') ? 1 : 0;
        var $item = $('#sub-item-' + subId);
        var $label = $('#sub-toggle-label-' + subId);
        var $badge = $('#sub-badge-' + subId);
        var $badgeText = $('#sub-badge-text-' + subId);

        $cb.prop('disabled', true);
        $label.text('...');

        $.ajax({
            url: endpoint + (endpoint.indexOf('?') !== -1 ? '&' : '?') + 'ajax_action=fetch_list&type=categories',
            method: 'POST',
            data: {
                cat_action: 'toggle_subcategory',
                subcategory_id: subId,
                is_active: isChecked
            },
            dataType: 'json',
            success: function(res) {
                $cb.prop('disabled', false);
                if (res && res.ok) {
                    if (isChecked) {
                        $item.removeClass('sub-stopped').addClass('sub-active').attr('data-status', 'active');
                        $label.text('Start').css('color', '#05c279');
                        $badge.removeClass('badge-status-stopped').addClass('badge-status-active');
                        $badge.find('i').removeClass('fa-pause-circle').addClass('fa-check-circle');
                        $badgeText.text('Active');
                        showToast(res.message || 'Subcategory started.');
                    } else {
                        $item.removeClass('sub-active').addClass('sub-stopped').attr('data-status', 'stopped');
                        $label.text('Stop').css('color', '#64748b');
                        $badge.removeClass('badge-status-active').addClass('badge-status-stopped');
                        $badge.find('i').removeClass('fa-check-circle').addClass('fa-pause-circle');
                        $badgeText.text('Stopped');
                        showToast(res.message || 'Subcategory stopped.');
                    }
                    updateCategoryCounters();
                } else {
                    $cb.prop('checked', !isChecked);
                    $label.text(!isChecked ? 'Start' : 'Stop');
                    showToast(res.message || 'Failed to update subcategory.', true);
                }
            },
            error: function() {
                $cb.prop('disabled', false);
                $cb.prop('checked', !isChecked);
                $label.text(!isChecked ? 'Start' : 'Stop');
                showToast('Server communication error. Please try again.', true);
            }
        });
    };

    // ── 3. Bulk Toggle All Subcategories in a Category ──
    window.bulkToggleSubcategories = function(catId, targetStatus) {
        if (!confirm('Are you sure you want to ' + (targetStatus ? 'START' : 'STOP') + ' all subcategories under this category?')) {
            return;
        }

        $.ajax({
            url: endpoint + (endpoint.indexOf('?') !== -1 ? '&' : '?') + 'ajax_action=fetch_list&type=categories',
            method: 'POST',
            data: {
                cat_action: 'bulk_toggle_subcategories',
                category_id: catId,
                is_active: targetStatus
            },
            dataType: 'json',
            success: function(res) {
                if (res && res.ok) {
                    // Update all subcategory DOM elements under this category
                    var $subs = $('#subs-list-' + catId).find('.subcategory-list-item');
                    $subs.each(function() {
                        var $sub = $(this);
                        var subId = $sub.data('sub-id');
                        var $cb = $('#sub-toggle-' + subId);
                        var $label = $('#sub-toggle-label-' + subId);
                        var $badge = $('#sub-badge-' + subId);
                        var $badgeText = $('#sub-badge-text-' + subId);

                        $cb.prop('checked', targetStatus === 1);
                        if (targetStatus === 1) {
                            $sub.removeClass('sub-stopped').addClass('sub-active').attr('data-status', 'active');
                            $label.text('Start').css('color', '#05c279');
                            $badge.removeClass('badge-status-stopped').addClass('badge-status-active');
                            $badge.find('i').removeClass('fa-pause-circle').addClass('fa-check-circle');
                            $badgeText.text('Active');
                        } else {
                            $sub.removeClass('sub-active').addClass('sub-stopped').attr('data-status', 'stopped');
                            $label.text('Stop').css('color', '#64748b');
                            $badge.removeClass('badge-status-active').addClass('badge-status-stopped');
                            $badge.find('i').removeClass('fa-check-circle').addClass('fa-pause-circle');
                            $badgeText.text('Stopped');
                        }
                    });
                    updateCategoryCounters();
                    showToast(res.message || 'All subcategories updated successfully.');
                } else {
                    showToast(res.message || 'Bulk operation failed.', true);
                }
            },
            error: function() {
                showToast('Server communication error during bulk update.', true);
            }
        });
    };

    // ── 4. Re-calculate Counters on Client ──
    function updateCategoryCounters() {
        var totalCats = $('.category-list-card').length;
        var activeCats = $('.category-list-card[data-status="active"]').length;
        var stoppedCats = totalCats - activeCats;

        var totalSubs = $('.subcategory-list-item').length;
        var activeSubs = $('.subcategory-list-item[data-status="active"]').length;
        var stoppedSubs = totalSubs - activeSubs;

        $('#stat-total-cats').text(totalCats);
        $('#stat-active-cats').text(activeCats);
        $('#stat-total-subs').text(totalSubs);
        $('#stat-active-subs').text(activeSubs);

        $('#btn-count-all').text(totalCats);
        $('#btn-count-active').text(activeCats);
        $('#btn-count-stopped').text(stoppedCats);
    }

    // ── 5. Search Filtering Logic ──
    window.filterCategoriesList = function() {
        var term = $.trim($('#catSearchInput').val()).toLowerCase();

        $('.category-list-card').each(function() {
            var $card = $(this);
            var catName = $card.data('name') || '';
            var catSlug = $card.data('slug') || '';
            var matchesCat = (catName.indexOf(term) !== -1 || catSlug.indexOf(term) !== -1);

            var matchingSubCount = 0;
            $card.find('.subcategory-list-item').each(function() {
                var $sub = $(this);
                var subName = $sub.data('name') || '';
                var subSlug = $sub.data('slug') || '';
                if (term === '' || subName.indexOf(term) !== -1 || subSlug.indexOf(term) !== -1) {
                    $sub.show();
                    matchingSubCount++;
                } else {
                    $sub.hide();
                }
            });

            if (term === '' || matchesCat || matchingSubCount > 0) {
                $card.show();
                if (term !== '' && matchingSubCount > 0 && !matchesCat) {
                    $card.find('.collapse').collapse('show');
                }
            } else {
                $card.hide();
            }
        });
    };

    // ── 6. Status Tab Filter (All, Active, Stopped) ──
    window.setStatusFilter = function(status) {
        $('.category-list-card').each(function() {
            var $card = $(this);
            var cardStatus = $card.attr('data-status');
            if (status === 'all' || cardStatus === status) {
                $card.show();
            } else {
                $card.hide();
            }
        });
    };

    // ── 7. Expand & Collapse All ──
    window.expandAllCategories = function() {
        $('.category-list-card .collapse').collapse('show');
    };
    window.collapseAllCategories = function() {
        $('.category-list-card .collapse').collapse('hide');
    };

    // ── 8. Refresh Current View ──
    window.reloadCategoryList = function() {
        if (typeof loadSection === 'function') {
            currentSection = ''; // reset cached section
            loadSection('categories');
        } else {
            location.reload();
        }
    };

})();
</script>
