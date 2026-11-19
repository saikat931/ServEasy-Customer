<?php
session_start();

// Database Connection
require_once __DIR__ . '/../backend/config/db_connection.php';

// Check if user is legally here (has setup session)
if (!isset($_SESSION['setup_admin_id'])) {
    header("Location: ../frontend-admin/admin-login.php");
    exit();
}

$admin_id = $_SESSION['setup_admin_id'];
$error = '';

try {
    $pdo = Database::connect();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Arrays to collect questions and answers
    $questions = [];
    $answers = [];
    $valid = true;
    
    for ($i = 1; $i <= 10; $i++) {
        $q = $_POST['question_' . $i] ?? '';
        $a = $_POST['answer_' . $i] ?? '';
        
        if (empty(trim($q)) || empty(trim($a))) {
            $valid = false;
        }
        
        $questions[$i] = $q;
        $answers[$i] = $a;
    }

    if (!$valid) {
        $error = "Please fill out all 10 security questions and answers!";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE admin_users SET 
                question_1 = ?, answer_1 = ?, 
                question_2 = ?, answer_2 = ?,
                question_3 = ?, answer_3 = ?, 
                question_4 = ?, answer_4 = ?,
                question_5 = ?, answer_5 = ?, 
                question_6 = ?, answer_6 = ?,
                question_7 = ?, answer_7 = ?, 
                question_8 = ?, answer_8 = ?,
                question_9 = ?, answer_9 = ?, 
                question_10 = ?, answer_10 = ?
                WHERE admin_id = ?");

            $params = [];
            for ($i = 1; $i <= 10; $i++) {
                $params[] = $questions[$i];
                $params[] = $answers[$i];
            }
            $params[] = $admin_id;

            $stmt->execute($params);
            
            // Clean up session and redirect
            unset($_SESSION['setup_admin_id']);
            header("Location: ../frontend-admin/admin-login.php?success=setup");
            exit();
        } catch (PDOException $e) {
            $error = "Failed to save security questions: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Security Questions | ServEasy</title>
    <!-- google font link -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/super-admin.css?v=21">
</head>
<body>

<div class="container" style="max-width: 1100px;">
    <div class="left-banner">
        <h2>ServEasy</h2>
        <p>Enhance the security of your account by setting up personalized challenge questions.</p>
    </div>

    <div class="form-section" style="max-height: 85vh; overflow-y: auto;">
        <div class="form-header">
            <h3>Setup Security Questions</h3>
            <p>Welcome! Your account is approved. Please define 10 security questions to protect your account before logging in.</p>
        </div>

        <?php if($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="" method="POST" id="setupForm">
            <div class="security-question-card" style="padding: 16px 20px !important; margin-bottom: 24px !important;">
                <div class="security-question-header" style="margin-bottom: 8px !important;">
                    <span class="security-question-badge">
                        <svg class="security-badge-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        Security Protocol
                    </span>
                </div>
                <div class="security-question-text" style="font-size: 0.98rem !important; font-weight: 500 !important; color: #f1f5f9 !important;">
                    Please provide 10 unique security questions and answers. You will be asked one of these at random upon logging in.
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <?php for($i = 1; $i <= 10; $i++): ?>
                <div class="security-q-box" style="margin-bottom: 0;">
                    <label>Question <?= $i ?></label>
                    <input type="text" name="question_<?= $i ?>" placeholder="Enter custom question" required style="margin-bottom: 10px;" value="<?= htmlspecialchars($_POST['question_'.$i] ?? '') ?>">
                    <input type="text" name="answer_<?= $i ?>" placeholder="Your Answer" required value="<?= htmlspecialchars($_POST['answer_'.$i] ?? '') ?>">
                </div>
                <?php endfor; ?>
            </div>

            <div class="btn-panel" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Questions & Login</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
