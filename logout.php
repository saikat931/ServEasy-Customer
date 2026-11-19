<?php
session_start();
$is_super = isset($_SESSION['super_admin']);
session_unset();
session_destroy();

header("Location: index.php");
exit;
?>
