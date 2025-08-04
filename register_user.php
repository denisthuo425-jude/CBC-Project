<?php
require_once 'db_connect.php';
require_once 'activity_log.php'; // For logging user activities

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    if ($password !== $confirm_password) {
        $message = "Passwords do not match!";
        $messageType = 'error';
    } else {
        $check_username = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $check_username->bind_param("s", $username);
        $check_username->execute();
        $username_result = $check_username->get_result();

        $check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $email_result = $check_email->get_result();

        if ($username_result->num_rows > 0) {
            $message = "Username already exists!";
            $messageType = 'error';
        } elseif ($email_result->num_rows > 0) {
            $message = "Email already registered!";
            $messageType = 'error';
        } else {
            // Check if student exists with this email
            $check_student = $conn->prepare("SELECT student_id FROM students WHERE student_email = ?");
            $check_student->bind_param("s", $email);
            $check_student->execute();
            $student_result = $check_student->get_result();
            
            if ($role === 'Student' && $student_result->num_rows === 0) {
                $message = "You need to be registered by a teacher first!";
                $messageType = 'error';
                $check_student->close();
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $insert_stmt->bind_param("ssss", $username, $email, $hashed_password, $role);

                if ($insert_stmt->execute()) {
                    // If student exists, update their user_id
                    if ($role === 'Student' && $student_result->num_rows > 0) {
                        $user_id = $insert_stmt->insert_id;
                        $update_student = $conn->prepare("UPDATE students SET user_id = ? WHERE student_email = ?");
                        $update_student->bind_param("is", $user_id, $email);
                        $update_student->execute();
                        $update_student->close();
                    }
                    // Log registration action if admin is logged in
                    if (isset($_SESSION['user_id'])) {
                        log_activity($_SESSION['user_id'], $_SESSION['role'], 'register_user');
                    }
                    
                    $message = "Registration successful! Redirecting to login...";
                    $messageType = 'success';
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = 'login.html';
                        }, 2000);
                    </script>";
                } else {
                    $message = "Error: " . $insert_stmt->error;
                    $messageType = 'error';
                }
                $insert_stmt->close();
            }
            if (isset($check_student)) {
                $check_student->close();
            }
        }
        
        $check_username->close();
        $check_email->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration - CBC Performance Tracking</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="registration-container">
    <div class="container">
        <h2>User Registration</h2>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="form-group">
                <label for="role">Select Role:</label>
                <select id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="Teacher">Teacher</option>
                    <option value="Student">Student</option>
                    <option value="Head Teacher">Head Teacher</option>
                    <option value="County Admin">County Admin</option>
                </select>
            </div>

            <button type="submit" class="submit-btn">Register</button>
        </form>

        <div class="links">
            <p>Already have an account? <a href="login.html">Login here</a></p>
        </div>
    </div>
</body>
</html>
