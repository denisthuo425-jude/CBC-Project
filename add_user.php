<?php
session_start();
require_once 'db_connect.php';
require_once 'activity_log.php'; // For logging user activities

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Plain validation
    if (empty($username)) {
        $error = "Username is required.";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters.";
    } elseif (empty($email)) {
        $error = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (empty($password)) {
        $error = "Password is required.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!in_array($role, ['Teacher', 'Head Teacher', 'County Admin', 'System Admin', 'Student'])) {
        $error = "Invalid role selected.";
    } else {
        // Check for duplicate username or email
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $error = "Username or email already exists.";
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
            if ($stmt->execute()) {
                $success = "User added successfully.";
                // Log add user action
                log_activity($_SESSION['user_id'], $_SESSION['role'], 'add_user');
            } else {
                $error = "Error adding user: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - CBC Performance Tracking</title>
    <link rel="stylesheet" href="assets/styles_system_admin.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="main-content">
            <div class="content-box">
                <h2>Add New User</h2>
                <?php if ($error): ?>
                    <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
                <?php if ($success): ?>
                    <p style="color: green;"><?php echo htmlspecialchars($success); ?></p>
                <?php endif; ?>
                <form method="post">
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Password:</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Role:</label>
                        <select name="role" required>
                            <option value="">Select Role</option>
                            <option value="Teacher">Teacher</option>
                            <option value="Head Teacher">Head Teacher</option>
                            <option value="County Admin">County Admin</option>
                            <option value="System Admin">System Admin</option>
                            <option value="Student">Student</option>
                        </select>
                    </div>
                    <input type="submit" value="Add User" class="submit-btn">
                    <a href="admin_dashboard.php?section=users" class="back-btn">Back</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>