<?php
// activity_log.php
// Purpose: Provides a reusable function to log user activities to the user_activity table.
require_once 'db_connect.php';

/**
 * Logs a user activity to the user_activity table.
 * @param int $user_id - The user's ID.
 * @param string $role - The user's role.
 * @param string $activity_type - The type of activity (e.g., 'record_performance', 'add_user').
 *
 * Note: Do NOT use this for 'login' or 'logout' activities. Login/logout are tracked by session rows (login_time/logout_time).
 */
function log_activity($user_id, $role, $activity_type) {
    global $conn;
    // Prepare SQL to insert activity log
    $sql = "INSERT INTO user_activity (user_id, role, activity_type, login_time) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $role, $activity_type);
    $stmt->execute();
    $stmt->close();
}
?> 