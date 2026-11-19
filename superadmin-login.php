<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0); // 0 means session expires when the browser is closed
    session_start();
}

// Database Connection
$dbConfigPath = file_exists(__DIR__ . '/../backend/config/db_connection.php')
    ? __DIR__ . '/../backend/config/db_connection.php'
    : (file_exists(__DIR__ . '/../../backend/config/db_connection.php') ? __DIR__ . '/../../backend/config/db_connection.php' : null);
if ($dbConfigPath) {
    require_once $dbConfigPath;
    $pdo = Database::connect();
} else {
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $dbname = $_ENV['DB_DATABASE'] ?? 'serveasy_v2';
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

$error = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'access_revoked') {
        $error = "Access Denied: Your account status is 'pending' or your super admin rights have been revoked.";
    }
}
$success = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] == 'setup') {
        $success = 'Security questions saved successfully! Please log in.';
    } else if ($_GET['success'] == 'reset') {
        $success = 'Password reset successfully! Please log in.';
    }
}

// Redirect if already logged in
if (isset($_SESSION['super_admin'])) {
    header("Location: superadmin-dashboard.php");
    exit();
}

if (isset($_GET['action']) && $_GET['action'] == 'cancel') {
    unset($_SESSION['login_step']);
    unset($_SESSION['temp_admin_id']);
    unset($_SESSION['temp_user']);
    unset($_SESSION['temp_q_num']);
    unset($_SESSION['temp_question']);
    unset($_SESSION['temp_answer']);
    header("Location: index.php");
    exit();
}

if (isset($_GET['action']) && $_GET['action'] == 'change_q' && isset($_SESSION['temp_user'])) {
    $user = $_SESSION['temp_user'];
    
    // Pick a different question
    $old_q = $_SESSION['temp_q_num'] ?? 0;
    do {
        $q_num = rand(1, 10);
    } while ($q_num == $old_q);
    
    $question_col = 'question_' . $q_num;
    $answer_col = 'answer_' . $q_num;
    
    $_SESSION['temp_q_num'] = $q_num;
    $_SESSION['temp_question'] = $user[$question_col];
    $_SESSION['temp_answer'] = $user[$answer_col];
    
    header("Location: superadmin-login.php");
    exit;
}

$step = $_SESSION['login_step'] ?? 1;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($step == 1) {
        $user_or_email = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';

        if (empty($user_or_email) || empty($pass)) {
            $error = "Please enter username and password!";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? OR email = ?");
                $stmt->execute([$user_or_email, $user_or_email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($pass, $user['password_hash'])) {
                    if ($user['role'] !== 'super_admin') {
                        $error = "Access denied. Only Super Admins can log in here.";
                    } else {
                        // Check if security questions are set up
                        if (empty($user['question_1'])) {
                            $_SESSION['setup_admin_id'] = $user['admin_id'];
                            header("Location: admin-setup.php");
                            exit;
                        }

                        // Success Step 1
                        $q_num = rand(1, 10);
                        $question_col = 'question_' . $q_num;
                        $answer_col = 'answer_' . $q_num;
                        
                        $_SESSION['temp_admin_id'] = $user['admin_id'] ?? null;
                        $_SESSION['temp_user'] = $user;
                        $_SESSION['temp_q_num'] = $q_num;
                        $_SESSION['temp_question'] = $user[$question_col];
                        $_SESSION['temp_answer'] = $user[$answer_col];
                        
                        $_SESSION['login_step'] = 2;
                        $step = 2;
                    }
                } else {
                    $error = "Invalid username or password!";
                }
            } catch (PDOException $e) {
                $error = "Login failed: " . $e->getMessage();
            }
        }
    } else if ($step == 2) {
        $user_answer = $_POST['security_answer'] ?? '';
        $correct_answer = $_SESSION['temp_answer'] ?? '';
        
        if (strtolower(trim($user_answer)) === strtolower(trim($correct_answer))) {
            $user = $_SESSION['temp_user'];
            
            // Final Success
            $_SESSION['super_admin'] = [
                'id' => $user['admin_id'] ?? null,
                'username' => $user['username'],
                'role' => $user['role'],
                'name' => $user['full_name'],
                'permissions' => [
                    'admin' => 1,
                    'dealer' => 1,
                    'user' => 1
                ]
            ];
            $_SESSION['admin_role'] = $user['role']; // keep for compatibility if needed elsewhere
            $_SESSION['just_logged_in'] = true;
            
            // Clean up temporary variables
            unset($_SESSION['login_step']);
            unset($_SESSION['temp_admin_id']);
            unset($_SESSION['temp_user']);
            unset($_SESSION['temp_q_num']);
            unset($_SESSION['temp_question']);
            unset($_SESSION['temp_answer']);
            
            session_write_close();
            header("Location: superadmin-dashboard.php");
            exit;
        } else {
            $error = "Incorrect security answer. Please try again.";
            
            // Change the question automatically on failure
            $user = $_SESSION['temp_user'];
            $old_q = $_SESSION['temp_q_num'] ?? 0;
            do {
                $q_num = rand(1, 10);
            } while ($q_num == $old_q); // Ensure it's a different question
            
            $question_col = 'question_' . $q_num;
            $answer_col = 'answer_' . $q_num;
            
            $_SESSION['temp_q_num'] = $q_num;
            $_SESSION['temp_question'] = $user[$question_col];
            $_SESSION['temp_answer'] = $user[$answer_col];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <script src="./config.js"></script>
  <script src="./serveasy-api-bridge.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login | ServEasy</title>
    <!-- google font link -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/super-admin.css?v=21">
</head>
<body>

<div class="container">
    <div class="left-banner">
        <h2>ServEasy</h2>
        <p>Login to your Super Admin account to oversee the freshest organic platform.</p>
    </div>

    <div class="form-section">
        <div class="form-header">
            <h3>Super Admin Login</h3>
            <p>Access your administrator dashboard.</p>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="" method="POST" id="loginForm">
            <?php if ($step == 1): ?>
                <div class="form-step active" id="step1">
                    <div class="form-group">
                        <label>Username or Email Address</label>
                        <input type="text" name="username" required placeholder="admin@ServEasy.com">
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-icon">
                            <input type="password" name="password" id="password" required placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
                            <span class="eye-toggle" onclick="togglePassword('password', this)">
                                <ion-icon name="eye-outline"></ion-icon>
                            </span>
                        </div>
                    </div>

                    <div class="btn-panel">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Next Phase &rarr;</button>
                    </div>
                    
                    <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">
                    
                    <div class="login-links">
                        <a href="forgot-password.php">Forgot Password?</a>
                        <a href="superadmin-register.php">Create an Account!</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="form-step active" id="step2">
                    <div class="security-question-card">
                        <div class="security-question-header">
                            <span class="security-question-badge">
                                <svg class="security-badge-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                </svg>
                                Security Question #<?= $_SESSION['temp_q_num'] ?>
                            </span>
                        </div>
                        <div class="security-question-text">
                            <?= htmlspecialchars($_SESSION['temp_question']) ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Your Answer</label>
                        <input type="text" name="security_answer" required placeholder="Enter your answer...">
                    </div>
                    <div class="btn-panel" style="display: flex; gap: 10px;">
                        <a href="?action=cancel" class="btn btn-outline" style="flex: 1; text-align: center; text-decoration: none;">&larr; Back</a>
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Login</button>
                    </div>
                    <div class="text-center" style="margin-top: 18px;">
                        <a href="?action=change_q" class="change-question-link">
                            <svg class="security-badge-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                            </svg>
                            Get a different question
                        </a>
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

</body>
</html>

