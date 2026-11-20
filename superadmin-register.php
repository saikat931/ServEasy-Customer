<?php
session_start();

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

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    // Arrays to collect questions and answers
    $questions = [];
    $answers = [];
    for ($i = 1; $i <= 10; $i++) {
        $questions[$i] = $_POST['question_' . $i] ?? '';
        $answers[$i] = $_POST['answer_' . $i] ?? '';
    }

    if ($pass !== $confirmPass) {
        $error = "Passwords do not match!";
    } else {
        $passHash = password_hash($pass, PASSWORD_BCRYPT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO admin_users (
                full_name, email, username, password_hash, role, status,
                question_1, answer_1, question_2, answer_2,
                question_3, answer_3, question_4, answer_4,
                question_5, answer_5, question_6, answer_6,
                question_7, answer_7, question_8, answer_8,
                question_9, answer_9, question_10, answer_10
            ) VALUES (
                ?, ?, ?, ?, 'super_admin', 'approved',
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )");

            $params = [$fullName, $email, $user, $passHash];
            for ($i = 1; $i <= 10; $i++) {
                $params[] = $questions[$i];
                $params[] = $answers[$i];
            }

            $stmt->execute($params);
            $success = "Registration successful! You can now log in.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Username or Email already exists!";
            } else {
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}

// User will define their own 10 security questions instead of defaults
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- ServEasy Favicon -->
  <link rel="icon" type="image/svg+xml" href="favicon.svg">
  <link rel="alternate icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Registration | ServEasy</title>
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
        <p>Join as a Super Admin to oversee the freshest organic platform.</p>
    </div>

    <div class="form-section">
        <div class="form-header">
            <h3>Super Admin Register</h3>
            <p>Setup your highest-level administrator account.</p>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="step-indicator">
            <div class="step-dot active" id="dot1"></div>
            <div class="step-dot" id="dot2"></div>
        </div>

        <form action="" method="POST" id="registerForm">
            <!-- STEP 1 -->
            <div class="form-step active" id="step1">
                <div class="form-group grid">
                    <div>
                        <label>Full Name</label>
                        <input type="text" name="full_name" required placeholder="John Doe">
                    </div>
                    <div>
                        <label>Username</label>
                        <input type="text" name="username" required placeholder="johndoe_admin">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="admin@ServEasy.com">
                </div>

                <div class="form-group grid">
                    <div>
                        <label>Password</label>
                        <div class="input-icon">
                            <input type="password" name="password" id="password" required placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
                            <span class="eye-toggle" onclick="togglePassword('password', this)">
                                <ion-icon name="eye-outline"></ion-icon>
                            </span>
                        </div>
                    </div>
                    <div>
                        <label>Confirm Password</label>
                        <div class="input-icon">
                            <input type="password" name="confirm_password" id="confirm_password" required placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
                            <span class="eye-toggle" onclick="togglePassword('confirm_password', this)">
                                <ion-icon name="eye-outline"></ion-icon>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="btn-panel">
                    <button type="button" class="btn btn-primary" onclick="nextStep()">Next Phase &rarr;</button>
                </div>
                <div class="text-center" style="margin-top: 15px;">
                    <a href="superadmin-login.php">Already have an account? Login!</a>
                </div>
            </div>

            <!-- STEP 2 -->
            <div class="form-step" id="step2">
                <p style="margin-bottom: 20px; color: var(--se-text-secondary); text-align: center; font-size: 0.95rem;">
                    Please provide 10 security questions and answers for account recovery.
                </p>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <?php for($i = 1; $i <= 10; $i++): ?>
                    <div class="security-q-box" style="margin-bottom: 0;">
                        <label>Question <?= $i ?></label>
                        <input type="text" name="question_<?= $i ?>" placeholder="Enter your custom question" required style="margin-bottom: 10px;">
                        <input type="text" name="answer_<?= $i ?>" placeholder="Your Answer" required>
                    </div>
                    <?php endfor; ?>
                </div>

                <div class="btn-panel">
                    <button type="button" class="btn btn-outline" onclick="prevStep()">&larr; Back</button>
                    <button type="submit" class="btn btn-primary">Complete Registration</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function nextStep() {
        // Simple HTML5 validation for step 1
        const step1Inputs = document.querySelectorAll('#step1 input[required]');
        let valid = true;
        step1Inputs.forEach(input => {
            if (!input.checkValidity()) {
                input.reportValidity();
                valid = false;
            }
        });

        if (valid) {
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step2').classList.add('active');
            document.getElementById('dot1').classList.remove('active');
            document.getElementById('dot2').classList.add('active');
        }
    }

    function prevStep() {
        document.getElementById('step2').classList.remove('active');
        document.getElementById('step1').classList.add('active');
        document.getElementById('dot2').classList.remove('active');
        document.getElementById('dot1').classList.add('active');
    }
    function togglePassword(inputId, el) {
        const input = document.getElementById(inputId);
        const icon = el.querySelector('ion-icon');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.setAttribute('name', 'eye-off-outline');
        } else {
            input.type = 'password';
            icon.setAttribute('name', 'eye-outline');
        }
    }
</script>

<!-- Ionicons -->
<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

</body>
</html>

