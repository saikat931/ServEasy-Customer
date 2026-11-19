<?php
session_start();
$timeout_duration = 1800;

if (isset($_SESSION['last_ping']) && (time() - $_SESSION['last_ping'] > $timeout_duration)) {
    session_unset();
    session_destroy();
    http_response_code(401);
    echo "Session expired";
    exit();
}

if (isset($_SESSION['super_admin']) || isset($_SESSION['standard_admin'])) {
    $_SESSION['last_ping'] = time();
    echo "OK";
} else {
    http_response_code(401);
    echo "Not logged in";
}
?>
