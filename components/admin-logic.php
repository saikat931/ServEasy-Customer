<?php
// Handle status toggle
if (isset($_POST['toggle_admin_id']) && isset($_POST['new_status'])) {
    $toggle_id = $_POST['toggle_admin_id'];
    $new_status = $_POST['new_status'];
    $super_admin_id = $_SESSION['admin_id'] ?? null;
    
    // Ensure status is valid
    if (in_array($new_status, ['approved', 'pending'])) {
        $toggle_stmt = $pdo->prepare("UPDATE admin_users SET status = ?, approved_by = ?, approved_at = NOW() WHERE admin_id = ?");
        $toggle_stmt->execute([$new_status, $super_admin_id, $toggle_id]);
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "#admin-users");
    exit();
}

// Handle permission toggle
if (isset($_POST['permission_admin_id']) && isset($_POST['perm_type']) && isset($_POST['perm_val'])) {
    if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin') {
        $perm_id = $_POST['permission_admin_id'];
        $type = $_POST['perm_type'];
        $val = $_POST['perm_val'] ? 1 : 0;
        
        $allowed_perms = ['admin', 'dealer', 'delivery', 'user'];
        if (in_array($type, $allowed_perms)) {
            $column = 'can_access_' . $type;
            $perm_stmt = $pdo->prepare("UPDATE admin_users SET $column = ? WHERE admin_id = ? AND role != 'super_admin'");
            $perm_stmt->execute([$val, $perm_id]);
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "#admin-users");
    exit();
}

// Fetch admins
$stmt = $pdo->query("SELECT * FROM admin_users WHERE role = 'admin' ORDER BY admin_id DESC");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
