<?php
/**
 * ServEasy Super Admin - Profile Management View
 * Allows Super Admin to view and update personal profile, change password, and configure security questions
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

$admin_id = $_SESSION['admin_id'] ?? ($_SESSION['super_admin']['id'] ?? 1);

// ── Handle AJAX Profile Actions ──────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['profile_action'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['profile_action'];

    // 1. Update Profile (Name & Email)
    if ($action === 'update_profile') {
        $name  = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name) || empty($email)) {
            echo json_encode(['ok' => false, 'message' => 'Full Name and Email Address are required.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'message' => 'Please enter a valid email address.']);
            exit;
        }

        // Check if email is already taken by another admin
        $chk = $pdo->prepare("SELECT admin_id FROM admin_users WHERE email = ? AND admin_id != ?");
        $chk->execute([$email, $admin_id]);
        if ($chk->fetch()) {
            echo json_encode(['ok' => false, 'message' => 'Email address is already in use by another administrator.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE admin_users SET full_name = ?, email = ?, updated_at = NOW() WHERE admin_id = ?");
            $stmt->execute([$name, $email, $admin_id]);

            $_SESSION['admin_name'] = $name;
            if (isset($_SESSION['super_admin'])) {
                $_SESSION['super_admin']['name'] = $name;
                $_SESSION['super_admin']['email'] = $email;
            }

            echo json_encode(['ok' => true, 'message' => 'Profile details updated successfully!']);
        } catch (\Throwable $t) {
            echo json_encode(['ok' => false, 'message' => 'Database error: ' . $t->getMessage()]);
        }
        exit;
    }

    // 2. Change Password
    if ($action === 'change_password') {
        $curPass  = $_POST['current_password'] ?? '';
        $newPass  = $_POST['new_password'] ?? '';
        $confPass = $_POST['confirm_password'] ?? '';

        if (empty($curPass) || empty($newPass) || empty($confPass)) {
            echo json_encode(['ok' => false, 'message' => 'All password fields are required.']);
            exit;
        }

        if ($newPass !== $confPass) {
            echo json_encode(['ok' => false, 'message' => 'New password and confirmation password do not match.']);
            exit;
        }

        if (strlen($newPass) < 6) {
            echo json_encode(['ok' => false, 'message' => 'New password must be at least 6 characters long.']);
            exit;
        }

        // Fetch current password hash
        $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($curPass, $hash)) {
            echo json_encode(['ok' => false, 'message' => 'Current password entered is incorrect.']);
            exit;
        }

        try {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $up = $pdo->prepare("UPDATE admin_users SET password_hash = ?, updated_at = NOW() WHERE admin_id = ?");
            $up->execute([$newHash, $admin_id]);

            echo json_encode(['ok' => true, 'message' => 'Password changed successfully! Keep your new credentials safe.']);
        } catch (\Throwable $t) {
            echo json_encode(['ok' => false, 'message' => 'Error updating password: ' . $t->getMessage()]);
        }
        exit;
    }

    // 3. Update Security Answers
    if ($action === 'update_security_answers') {
        try {
            $updates = [];
            $params  = [];

            for ($i = 1; $i <= 10; $i++) {
                if (isset($_POST["answer_{$i}"])) {
                    $ans = trim($_POST["answer_{$i}"]);
                    if (!empty($ans)) {
                        $updates[] = "answer_{$i} = ?";
                        $params[]  = $ans;
                    }
                }
            }

            if (!empty($updates)) {
                $params[] = $admin_id;
                $sql = "UPDATE admin_users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE admin_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            echo json_encode(['ok' => true, 'message' => 'Security recovery questions updated successfully!']);
        } catch (\Throwable $t) {
            echo json_encode(['ok' => false, 'message' => 'Error saving security questions: ' . $t->getMessage()]);
        }
        exit;
    }
}

// ── Fetch Fresh Admin User Data ──────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE admin_id = ? LIMIT 1");
$stmt->execute([$admin_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo '<div class="alert alert-danger m-4">Unable to load administrator profile. Please log in again.</div>';
    exit;
}

$initials = '';
$parts = explode(' ', trim($user['full_name']));
foreach ($parts as $p) {
    if (!empty($p)) $initials .= strtoupper($p[0]);
    if (strlen($initials) >= 2) break;
}
if (empty($initials)) $initials = 'SA';
?>

<style>
/* ─── Profile Glassmorphism Styles ─── */
.prof-glass-card {
    background: rgba(15, 17, 26, 0.85) !important;
    backdrop-filter: blur(16px) saturate(130%);
    -webkit-backdrop-filter: blur(16px) saturate(130%);
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 18px !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.55), inset 0 1px 0 rgba(255, 255, 255, 0.06);
}
.prof-card-header {
    background: rgba(255, 255, 255, 0.03) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.07) !important;
    border-top-left-radius: 18px !important;
    border-top-right-radius: 18px !important;
    padding: 16px 22px !important;
}
.prof-input {
    background: rgba(10, 14, 24, 0.75) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    border-radius: 12px !important;
    color: #f8fafc !important;
    font-size: 0.95rem !important;
    height: 48px !important;
    padding: 10px 16px !important;
    transition: all 0.2s ease;
}
.prof-input:focus {
    background: rgba(15, 22, 38, 0.95) !important;
    border-color: #38bdf8 !important;
    box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.22) !important;
    color: #ffffff !important;
}
.prof-btn-primary {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
    border: none !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 15px rgba(2, 132, 199, 0.35) !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    height: 46px !important;
    padding: 0 22px !important;
    transition: all 0.2s ease;
}
.prof-btn-primary:hover {
    box-shadow: 0 6px 20px rgba(2, 132, 199, 0.5) !important;
    transform: translateY(-1px);
    color: #ffffff !important;
}
.prof-btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
    border: none !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.35) !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    height: 46px !important;
    padding: 0 22px !important;
    transition: all 0.2s ease;
}
.prof-btn-success:hover {
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5) !important;
    transform: translateY(-1px);
    color: #ffffff !important;
}
.avatar-circle {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: linear-gradient(135deg, #38bdf8 0%, #0284c7 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 800;
    box-shadow: 0 8px 24px rgba(56, 189, 248, 0.35);
    margin: 0 auto;
}
.eye-toggle-btn {
    position: absolute;
    right: 14px;
    top: 14px;
    color: #94a3b8;
    cursor: pointer;
    background: transparent;
    border: none;
    font-size: 1rem;
}
.eye-toggle-btn:hover {
    color: #38bdf8;
}
</style>

<div class="container-fluid px-0">

    <!-- Top Banner -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4 flex-wrap" style="gap: 15px;">
        <div>
            <div class="d-flex align-items-center mb-1">
                <span class="mr-2" style="color: #38bdf8; font-size: 1.4rem;"><i class="fas fa-user-circle"></i></span>
                <h1 class="h3 mb-0 text-white font-weight-bold">Super Admin Profile</h1>
                <span class="badge ml-3 px-2 py-1 font-weight-bold" style="background: rgba(139, 92, 246, 0.15); color: #c4b5fd; border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 8px; font-size: 0.75rem;">
                    <i class="fas fa-crown mr-1"></i> Master Governance
                </span>
            </div>
            <p class="small mb-0" style="color: #94a3b8;">
                Manage your administrator profile, security credentials, and two-factor recovery answers.
            </p>
        </div>
        <div class="d-flex align-items-center" style="gap: 10px;">
            <button type="button" class="btn btn-sm prof-glass-card text-white font-weight-bold px-3 py-2" onclick="loadSection('profile')">
                <i class="fas fa-sync-alt mr-1 text-info"></i> Refresh
            </button>
            <button type="button" class="btn btn-sm prof-glass-card text-white font-weight-bold px-3 py-2" onclick="loadSection('settings')">
                <i class="fas fa-cogs mr-1 text-warning"></i> Settings
            </button>
        </div>
    </div>

    <div class="row">

        <!-- Left Column: Avatar & Account Summary -->
        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="prof-glass-card p-4 text-center mb-4">
                <div class="position-relative d-inline-block mb-3">
                    <div class="avatar-circle">
                        <?= $initials ?>
                    </div>
                    <span class="badge badge-success p-1 position-absolute" style="bottom: 2px; right: 2px; border-radius: 50%; width: 18px; height: 18px; border: 3px solid #0f172a;"></span>
                </div>

                <h4 class="font-weight-bold text-white mb-1" id="disp_admin_name"><?= htmlspecialchars($user['full_name']) ?></h4>
                <div class="font-monospace small mb-3" style="color: #38bdf8;">@<?= htmlspecialchars($user['username']) ?></div>

                <div class="d-flex justify-content-center flex-wrap mb-4" style="gap: 8px;">
                    <span class="badge px-3 py-2 font-weight-bold" style="background: rgba(56, 189, 248, 0.12); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 20px; font-size: 0.78rem;">
                        <i class="fas fa-shield-alt mr-1"></i> Super Admin
                    </span>
                    <span class="badge px-3 py-2 font-weight-bold" style="background: rgba(16, 185, 129, 0.12); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 20px; font-size: 0.78rem;">
                        <i class="fas fa-check-circle mr-1"></i> <?= ucfirst(htmlspecialchars($user['status'])) ?>
                    </span>
                </div>

                <div class="text-left p-3 rounded" style="background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.05);">
                    <div class="d-flex justify-content-between py-2 border-bottom" style="border-color: rgba(255,255,255,0.05) !important;">
                        <span class="small font-weight-bold" style="color: #94a3b8;">Admin ID</span>
                        <span class="small font-weight-bold text-white">#<?= htmlspecialchars($user['admin_id']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom" style="border-color: rgba(255,255,255,0.05) !important;">
                        <span class="small font-weight-bold" style="color: #94a3b8;">Email Address</span>
                        <span class="small font-weight-bold text-info" id="disp_admin_email"><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom" style="border-color: rgba(255,255,255,0.05) !important;">
                        <span class="small font-weight-bold" style="color: #94a3b8;">Member Since</span>
                        <span class="small font-weight-bold text-white"><?= !empty($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : 'N/A' ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom" style="border-color: rgba(255,255,255,0.05) !important;">
                        <span class="small font-weight-bold" style="color: #94a3b8;">Last Login</span>
                        <span class="small font-weight-bold text-white"><?= !empty($user['last_login_time']) ? date('d M Y, h:i A', strtotime($user['last_login_time'])) : 'Current Session' ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span class="small font-weight-bold" style="color: #94a3b8;">Login IP</span>
                        <span class="small font-weight-bold text-white font-monospace"><?= htmlspecialchars($user['last_login_ip'] ?? '127.0.0.1') ?></span>
                    </div>
                </div>
            </div>

            <!-- Security Audit Card -->
            <div class="prof-glass-card p-4">
                <h6 class="font-weight-bold text-white mb-3 d-flex align-items-center">
                    <i class="fas fa-fingerprint mr-2" style="color: #a78bfa;"></i> Active Device & Session
                </h6>
                <div class="small" style="color: #94a3b8;">
                    <div class="mb-2 d-flex justify-content-between">
                        <span>Device:</span>
                        <strong class="text-white"><?= htmlspecialchars($user['device_name'] ?: 'Desktop PC') ?> (<?= htmlspecialchars($user['device_type'] ?: 'Computer') ?>)</strong>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span>Browser:</span>
                        <strong class="text-white"><?= htmlspecialchars($user['browser'] ?: 'Chrome / Edge') ?></strong>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span>OS:</span>
                        <strong class="text-white"><?= htmlspecialchars($user['operating_system'] ?: 'Windows') ?></strong>
                    </div>
                    <div class="pt-2 border-top d-flex justify-content-between" style="border-color: rgba(255,255,255,0.06) !important;">
                        <span>Session State:</span>
                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Active</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Edit Forms -->
        <div class="col-xl-8 col-lg-7">

            <!-- Card 1: Account Information -->
            <div class="prof-glass-card mb-4">
                <div class="prof-card-header d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-white d-flex align-items-center">
                        <i class="fas fa-user-edit mr-2" style="color: #38bdf8;"></i> Personal Information
                    </h6>
                    <span class="badge font-weight-bold" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8; font-size: 0.72rem; border-radius: 6px;">
                        Basic Profile
                    </span>
                </div>
                <div class="card-body p-4">
                    <form id="formUpdateProfile">
                        <input type="hidden" name="profile_action" value="update_profile">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-white mb-2">Full Name</label>
                                <input type="text" name="full_name" class="form-control prof-input font-weight-bold" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-white mb-2">Email Address</label>
                                <input type="email" name="email" class="form-control prof-input font-weight-bold" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-white mb-2">Username</label>
                                <input type="text" class="form-control prof-input text-muted font-monospace" value="<?= htmlspecialchars($user['username']) ?>" readonly style="cursor: not-allowed; opacity: 0.75;">
                                <small class="form-text mt-1" style="color: #64748b;">Unique username used for portal login (read-only).</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-white mb-2">Assigned Role</label>
                                <input type="text" class="form-control prof-input text-muted" value="Super Administrator" readonly style="cursor: not-allowed; opacity: 0.75;">
                                <small class="form-text mt-1" style="color: #64748b;">Unrestricted master governance role.</small>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end pt-2">
                            <button type="submit" class="btn prof-btn-primary d-flex align-items-center" id="btnSaveProfile">
                                <i class="fas fa-save mr-2"></i> Save Profile Details
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Card 2: Change Password -->
            <div class="prof-glass-card mb-4">
                <div class="prof-card-header d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-white d-flex align-items-center">
                        <i class="fas fa-lock mr-2" style="color: #f59e0b;"></i> Password & Security
                    </h6>
                    <span class="badge font-weight-bold" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; font-size: 0.72rem; border-radius: 6px;">
                        Authentication
                    </span>
                </div>
                <div class="card-body p-4">
                    <form id="formChangePassword">
                        <input type="hidden" name="profile_action" value="change_password">

                        <div class="form-group mb-3 position-relative">
                            <label class="font-weight-bold small text-white mb-2">Current Password</label>
                            <input type="password" name="current_password" id="cur_pass" class="form-control prof-input" placeholder="Enter your current password" required>
                            <button type="button" class="eye-toggle-btn" onclick="togglePass('cur_pass', this)">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3 position-relative">
                                <label class="font-weight-bold small text-white mb-2">New Password</label>
                                <input type="password" name="new_password" id="new_pass" class="form-control prof-input" placeholder="Min. 6 characters" required>
                                <button type="button" class="eye-toggle-btn" onclick="togglePass('new_pass', this)">
                                    <i class="far fa-eye"></i>
                                </button>
                            </div>
                            <div class="col-md-6 mb-3 position-relative">
                                <label class="font-weight-bold small text-white mb-2">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="conf_pass" class="form-control prof-input" placeholder="Re-enter new password" required>
                                <button type="button" class="eye-toggle-btn" onclick="togglePass('conf_pass', this)">
                                    <i class="far fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end pt-2">
                            <button type="submit" class="btn prof-btn-success d-flex align-items-center" id="btnChangePass">
                                <i class="fas fa-key mr-2"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Card 3: Two-Phase / Security Recovery Questions -->
            <div class="prof-glass-card mb-4">
                <div class="prof-card-header d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-white d-flex align-items-center">
                        <i class="fas fa-question-circle mr-2" style="color: #10b981;"></i> Two-Phase Security Recovery Answers
                    </h6>
                    <span class="badge font-weight-bold" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-size: 0.72rem; border-radius: 6px;">
                        Account Recovery
                    </span>
                </div>
                <div class="card-body p-4">
                    <p class="small mb-3" style="color: #94a3b8;">
                        These questions are challenged during multi-factor login or emergency account recovery. Set or update your answers below:
                    </p>

                    <form id="formSecurityAnswers">
                        <input type="hidden" name="profile_action" value="update_security_answers">
                        <div class="row">
                            <?php for ($i = 1; $i <= 4; $i++): 
                                $qKey = "question_{$i}";
                                $aKey = "answer_{$i}";
                                $qText = !empty($user[$qKey]) ? $user[$qKey] : "Security Question {$i}";
                                $aText = !empty($user[$aKey]) ? $user[$aKey] : "";
                            ?>
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-bold small text-white mb-1">
                                        <i class="fas fa-shield-alt mr-1 text-info"></i> <?= htmlspecialchars($qText) ?>
                                    </label>
                                    <input type="text" name="answer_<?= $i ?>" class="form-control prof-input" value="<?= htmlspecialchars($aText) ?>" placeholder="Your secret answer">
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div class="d-flex justify-content-end pt-2">
                            <button type="submit" class="btn prof-btn-primary d-flex align-items-center" id="btnSaveAnswers">
                                <i class="fas fa-shield-alt mr-2"></i> Update Security Answers
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    // Reveal / hide password
    function togglePass(inputId, btn) {
        var input = document.getElementById(inputId);
        var icon = $(btn).find('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    }

    // Submit Update Profile Form
    $('#formUpdateProfile').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#btnSaveProfile');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Saving...');

        $.ajax({
            url: 'superadmin-dashboard.php?ajax_action=fetch_list&type=profile',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Save Profile Details');
                if (res && res.ok) {
                    var newName = $('#formUpdateProfile input[name="full_name"]').val();
                    var newEmail = $('#formUpdateProfile input[name="email"]').val();
                    $('#disp_admin_name').text(newName);
                    $('#disp_admin_email').text(newEmail);
                    $('#topbar-user-name').text(newName);

                    if (typeof Notify !== 'undefined' && Notify.success) {
                        Notify.success(res.message);
                    } else {
                        alert(res.message);
                    }
                } else {
                    var msg = (res && res.message) ? res.message : 'Error updating profile.';
                    if (typeof Notify !== 'undefined' && Notify.error) {
                        Notify.error(msg);
                    } else {
                        alert(msg);
                    }
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Save Profile Details');
                alert('Server error updating profile.');
            }
        });
    });

    // Submit Change Password Form
    $('#formChangePassword').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#btnChangePass');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Updating...');

        $.ajax({
            url: 'superadmin-dashboard.php?ajax_action=fetch_list&type=profile',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fas fa-key mr-2"></i> Update Password');
                if (res && res.ok) {
                    $('#cur_pass, #new_pass, #conf_pass').val('');
                    if (typeof Notify !== 'undefined' && Notify.success) {
                        Notify.success(res.message);
                    } else {
                        alert(res.message);
                    }
                } else {
                    var msg = (res && res.message) ? res.message : 'Error changing password.';
                    if (typeof Notify !== 'undefined' && Notify.error) {
                        Notify.error(msg);
                    } else {
                        alert(msg);
                    }
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-key mr-2"></i> Update Password');
                alert('Server error changing password.');
            }
        });
    });

    // Submit Security Answers Form
    $('#formSecurityAnswers').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#btnSaveAnswers');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Saving...');

        $.ajax({
            url: 'superadmin-dashboard.php?ajax_action=fetch_list&type=profile',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fas fa-shield-alt mr-2"></i> Update Security Answers');
                if (res && res.ok) {
                    if (typeof Notify !== 'undefined' && Notify.success) {
                        Notify.success(res.message);
                    } else {
                        alert(res.message);
                    }
                } else {
                    var msg = (res && res.message) ? res.message : 'Error saving security questions.';
                    if (typeof Notify !== 'undefined' && Notify.error) {
                        Notify.error(msg);
                    } else {
                        alert(msg);
                    }
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-shield-alt mr-2"></i> Update Security Answers');
                alert('Server error updating security questions.');
            }
        });
    });
</script>
