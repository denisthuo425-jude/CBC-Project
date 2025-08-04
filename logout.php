<?php
session_start();
require_once 'db_connect.php';
require_once 'activity_log.php'; // For logging user activities

date_default_timezone_set('Africa/Nairobi');

// ✅ Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    $logout_time = date("Y-m-d H:i:s");

    // Log logout activity
    // log_activity($user_id, $role, 'logout');

    // Update logout time in the user_activity table
    $update_sql = "UPDATE user_activity SET logout_time = ? WHERE user_id = ? AND logout_time IS NULL ORDER BY login_time DESC LIMIT 1";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $logout_time, $user_id);
    $stmt->execute();
}

// ✅ Destroy session
session_unset();
session_destroy();

// ✅ Redirect to landing page page
header("Location: Landingpage.html");
exit();
?>
