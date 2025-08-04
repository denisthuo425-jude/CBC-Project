<?php
session_start();
require_once 'db_connect.php';
require_once 'activity_log.php'; // For logging user activities

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header('Location: login.php');
    exit();
}

// Handle delete
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    // Log delete action
    log_activity($_SESSION['user_id'], $_SESSION['role'], 'delete_user');
    header('Location: admin_dashboard.php?section=users');
    exit();
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_BCRYPT) : null;

    if ($password) {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE user_id = ?");
        $stmt->bind_param("ssssi", $username, $email, $role, $password, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE user_id = ?");
        $stmt->bind_param("sssi", $username, $email, $role, $user_id);
    }
    $stmt->execute();
    $stmt->close();
    // Log update action
    log_activity($_SESSION['user_id'], $_SESSION['role'], 'update_user');
    header('Location: admin_dashboard.php?section=users');
    exit();
}

// Fetch user data
$user_id = $_GET['id'] ?? 0;
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) {
    header('Location: admin_dashboard.php?section=users');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - CBC Performance Tracking</title>
    <link rel="stylesheet" href="assets/styles_system_admin.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="main-content">
            <div class="content-box">
                <h2>Edit User</h2>
                <form method="post">
                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Role:</label>
                        <select name="role" required>
                            <option value="Teacher" <?php echo $user['role'] === 'Teacher' ? 'selected' : ''; ?>>Teacher</option>
                            <option value="Head Teacher" <?php echo $user['role'] === 'Head Teacher' ? 'selected' : ''; ?>>Head Teacher</option>
                            <option value="County Admin" <?php echo $user['role'] === 'County Admin' ? 'selected' : ''; ?>>County Admin</option>
                            <option value="System Admin" <?php echo $user['role'] === 'System Admin' ? 'selected' : ''; ?>>System Admin</option>
                            <option value="Student" <?php echo $user['role'] === 'Student' ? 'selected' : ''; ?>>Student</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>New Password (leave blank to keep current):</label>
                        <input type="password" name="password">
                    </div>
                    <input type="submit" name="update_user" value="Update User" class="submit-btn">
                    <a href="admin_dashboard.php?section=users" class="back-btn">Back</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>