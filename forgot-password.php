<?php
session_start();

// Clear forgot session if accessed fresh (GET request without action parameters)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['action'])) {
    unset($_SESSION['forgot_step']);
    unset($_SESSION['reset_admin_id']);
    unset($_SESSION['reset_admin_email']);
    unset($_SESSION['reset_admin_otp']);
    unset($_SESSION['forgot_role']);
}


// Database Connection
$dbConfigPath = file_exists(__DIR__ . '/common/db_connection.php')
    ? __DIR__ . '/common/db_connection.php'
    : (file_exists(__DIR__ . '/../backend/config/db_connection.php')
        ? __DIR__ . '/../backend/config/db_connection.php'
        : (file_exists(__DIR__ . '/../../backend/config/db_connection.php') ? __DIR__ . '/../../backend/config/db_connection.php' : null));
if ($dbConfigPath) {
    require_once $dbConfigPath;
    $pdo = Database::connect();
} else {
    $httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isLocal = in_array($httpHost, ['localhost', '127.0.0.1', '::1']) || str_starts_with($httpHost, 'localhost:');
    $host = $_ENV['DB_HOST'] ?? ($isLocal ? '127.0.0.1' : 'localhost');
    $dbname = $_ENV['DB_DATABASE'] ?? ($isLocal ? 'serveasy_v2' : 'u923365340_serveasy');
    $username = $_ENV['DB_USERNAME'] ?? ($isLocal ? 'root' : 'u923365340_serveasy');
    $password = $_ENV['DB_PASSWORD'] ?? ($isLocal ? '' : 'Serveasy@931');
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

$error = '';
$success = '';
$step = $_SESSION['forgot_step'] ?? 1;

// Hardcode role context to super_admin for this separate file
$role_context = 'super_admin';

if (isset($_GET['action']) && $_GET['action'] == 'cancel') {
    unset($_SESSION['forgot_step']);
    unset($_SESSION['reset_admin_id']);
    unset($_SESSION['reset_admin_email']);
    unset($_SESSION['reset_admin_otp']);
    unset($_SESSION['forgot_role']);
    header("Location: superadmin-login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($step == 1) {
        $username_or_email = trim($_POST['username_or_email'] ?? '');
        if (empty($username_or_email)) {
            $error = "Please enter your username or email address.";
        } else {
            // Find admin/superadmin user matching the role context
            // Find super admin user
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE (username = ? OR email = ?) AND role = 'super_admin'");
            $stmt->execute([$username_or_email, $username_or_email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if ($user['status'] !== 'approved') {
                    $error = "This account is not approved yet.";
                } else {
                    // Generate OTP
                    $otp = rand(100000, 999999);
                    
                    // Update user's OTP in database
                    $update = $pdo->prepare("UPDATE admin_users SET otp = ?, otp_created_at = NOW() WHERE admin_id = ?");
                    $update->execute([$otp, $user['admin_id']]);
                    
                    $_SESSION['reset_admin_id'] = $user['admin_id'];
                    $_SESSION['reset_admin_email'] = $user['email'];
                    $_SESSION['reset_admin_otp'] = $otp; // Store in session to show for easy simulation
                    $_SESSION['forgot_step'] = 2;
                    $step = 2;
                    $success = "OTP verification code generated! Please enter it below.";
                }
            } else {
                $error = "No account found with that username or email address.";
            }
        }
    } else if ($step == 2) {
        $otp_input = trim($_POST['otp'] ?? '');
        $admin_id = $_SESSION['reset_admin_id'] ?? null;
        
        if (empty($otp_input)) {
            $error = "Please enter the 6-digit OTP code.";
        } else if (!$admin_id) {
            $error = "Session expired. Please start over.";
            $_SESSION['forgot_step'] = 1;
            $step = 1;
        } else {
            // Verify OTP in DB
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE admin_id = ? AND otp = ? AND otp_created_at >= (NOW() - INTERVAL 15 MINUTE)");
            $stmt->execute([$admin_id, $otp_input]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $_SESSION['forgot_step'] = 3;
                $step = 3;
                $success = "OTP verified successfully. Please choose a new password.";
            } else {
                $error = "Invalid or expired OTP. Please try again.";
            }
        }
    } else if ($step == 3) {
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';
        $admin_id = $_SESSION['reset_admin_id'] ?? null;
        
        if (empty($new_pass) || empty($confirm_pass)) {
            $error = "Please fill in both password fields.";
        } else if ($new_pass !== $confirm_pass) {
            $error = "Passwords do not match!";
        } else if (!$admin_id) {
            $error = "Session expired. Please start over.";
            $_SESSION['forgot_step'] = 1;
            $step = 1;
        } else {
            // Update password in DB
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE admin_users SET password_hash = ?, otp = NULL, otp_created_at = NULL WHERE admin_id = ?");
            $update->execute([$new_hash, $admin_id]);
            
            // Clean up session
            unset($_SESSION['forgot_step']);
            unset($_SESSION['reset_admin_id']);
            unset($_SESSION['reset_admin_email']);
            unset($_SESSION['reset_admin_otp']);
            
            // Success redirect to superadmin login page with status
            header("Location: superadmin-login.php?success=reset");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | ServEasy</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/super-admin.css?v=21">
    <link rel="stylesheet" href="css/notify.css">
    <style>
        .login-links {
            text-align: center;
            margin-top: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .login-links a {
            color: #60a5fa;
            text-decoration: none;
            font-size: 0.95rem;
            transition: color 0.3s ease;
        }
        .login-links a:hover {
            color: #93c5fd;
            text-decoration: underline;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 25px;
            gap: 12px;
        }
        .step-dot {
            width: 10px;
            height: 10px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 50% !important;
            transition: all 0.3s;
        }
        .step-dot.active {
            background: var(--se-accent-blue, #007aff) !important;
            border-color: #60a5fa !important;
            box-shadow: 0 0 10px rgba(0, 122, 255, 0.6) !important;
            transform: scale(1.3);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="left-banner">
        <h2>ServEasy</h2>
        <p>Password recovery wizard. Reset your credentials securely via OTP verification.</p>
    </div>

    <div class="form-section">
        <div class="form-header">
            <h3>Forgot Password</h3>
            <p>Step <?= $step ?> of 3</p>
        </div>

        <div class="step-indicator">
            <div class="step-dot <?= $step == 1 ? 'active' : '' ?>"></div>
            <div class="step-dot <?= $step == 2 ? 'active' : '' ?>"></div>
            <div class="step-dot <?= $step == 3 ? 'active' : '' ?>"></div>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="" method="POST" id="forgotForm">
            <?php if ($step == 1): ?>
                <div class="form-step active" id="step1">
                    <div class="form-group">
                        <label>Username or Email Address</label>
                        <input type="text" name="username_or_email" required placeholder="Enter username or email address..." value="<?= htmlspecialchars($username_or_email ?? '') ?>">
                    </div>

                    <div class="btn-panel" style="display: flex; gap: 10px;">
                        <a href="superadmin-login.php" class="btn btn-outline" style="flex: 1; text-align: center; text-decoration: none; padding-top: 14px;">&larr; Back</a>
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Send OTP</button>
                    </div>
                    
                    <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">
                    
                    <div class="login-links">
                        <a href="superadmin-login.php">Back to Super Admin Login</a>
                    </div>
                </div>
            <?php elseif ($step == 2): ?>
                <div class="form-step active" id="step2">
                    <div class="security-question-card" style="padding: 16px 20px !important;">
                        <div class="security-question-header" style="margin-bottom: 8px !important;">
                            <span class="security-question-badge">
                                <svg class="security-badge-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                OTP Verification
                            </span>
                        </div>
                        <div class="security-question-text" style="font-size: 0.98rem !important; font-weight: 500 !important; color: #f1f5f9 !important;">
                            A 6-digit OTP verification code has been sent to the account matching the email or username.
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Enter 6-Digit OTP</label>
                        <input type="text" name="otp" required placeholder="Enter OTP code" maxlength="6">
                    </div>
                    <div class="btn-panel" style="display: flex; gap: 10px;">
                        <a href="?action=cancel" class="btn btn-outline" style="flex: 1; text-align: center; text-decoration: none; padding-top: 14px;">&larr; Cancel</a>
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Verify OTP</button>
                    </div>
                </div>
            <?php elseif ($step == 3): ?>
                <div class="form-step active" id="step3">
                    <div class="form-group">
                        <label>New Password</label>
                        <div class="input-icon">
                            <input type="password" name="new_password" id="new_password" required placeholder="Enter new password">
                            <span class="eye-toggle" onclick="togglePassword('new_password', this)">
                                <ion-icon name="eye-outline"></ion-icon>
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Confirm Password</label>
                        <div class="input-icon">
                            <input type="password" name="confirm_password" id="confirm_password" required placeholder="Confirm new password">
                            <span class="eye-toggle" onclick="togglePassword('confirm_password', this)">
                                <ion-icon name="eye-outline"></ion-icon>
                            </span>
                        </div>
                    </div>

                    <div class="btn-panel" style="display: flex; gap: 10px;">
                        <a href="?action=cancel" class="btn btn-outline" style="flex: 1; text-align: center; text-decoration: none; padding-top: 14px;">&larr; Cancel</a>
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Reset Password</button>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
    function togglePassword(inputId, el) {
        const input = document.getElementById(inputId);
        const icon = el.querySelector('ion-icon');
        
        if (input) {
            if (input.type === 'password') {
                input.type = 'text';
                icon.setAttribute('name', 'eye-off-outline');
            } else {
                input.type = 'password';
                icon.setAttribute('name', 'eye-outline');
            }
        }
    }
</script>

<!-- Ionicons -->
<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

<?php if ($step == 2 && isset($_SESSION['reset_admin_otp'])): ?>
<script src="js/notify.js"></script>
<script>
    Notify.info("Your OTP is: <?= $_SESSION['reset_admin_otp'] ?>", { title: "Dev OTP" });
</script>
<?php endif; ?>

</body>
</html>

