<?php
/**
 * Staff Registration System
 * This file handles the registration of teachers by head teachers
 * 
 * Dependencies:
 * - db_connect.php: Database connection
 * - session handling
 * - user authentication
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection file
require_once 'db_connect.php';

// Authentication check - Only head teachers can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Head Teacher') {
    header('Location: login.html');
    exit();
}

// Fetch Head Teacher's school_id using prepared statement
$stmt = $conn->prepare("SELECT school_id FROM staffs WHERE user_id = ? AND role = 'Head Teacher'");
$stmt->bind_param("i", $_SESSION['user_id']); // 'i' indicates integer parameter
$stmt->execute();
$result = $stmt->get_result();
$head_teacher = $result->fetch_assoc();
$school_id = $head_teacher['school_id'];
$stmt->close();

// Variable to store messages (success/error) for user feedback
$message = '';

// Form submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Array of required form fields
    $required_fields = ['name', 'email', 'phone', 'qualification', 'grade'];
    $missing_fields = false;
    
    // Validate that all required fields are filled
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            $missing_fields = true;
            break;
        }
    }

    if ($missing_fields) {
        $message = '<div class="alert alert-danger">All fields are required!</div>';
    } else {
        // Sanitize and store form data
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $mobile_number = trim($_POST['phone']);
        $qualification = trim($_POST['qualification']);
        $grade = trim($_POST['grade']);
        $role = 'Teacher';

        // Check if user exists in users table
        $user_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $user_stmt->bind_param("s", $email); // 's' indicates string parameter
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($user_result->num_rows === 0) {
            $message = '<div class="alert alert-danger">No user account found with this email. Please ensure the teacher has registered first.</div>';
        } else {
            // Get user_id for the teacher
            $user = $user_result->fetch_assoc();
            $user_id = $user['user_id'];
            
            // Check for existing staff record
            $check_stmt = $conn->prepare("SELECT staff_id FROM staffs WHERE user_id = ? OR email = ? OR mobile_number = ?");
            $check_stmt->bind_param("iss", $user_id, $email, $mobile_number);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $message = '<div class="alert alert-danger">A staff record already exists with this email or phone number.</div>';
            } else {
                // Insert new staff record
                $staff_stmt = $conn->prepare("INSERT INTO staffs (user_id, school_id, name, email, mobile_number, qualification, assigned_grade, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$staff_stmt) {
                    die('Prepare failed: ' . $conn->error);
                }
                $staff_stmt->bind_param("iissssss", $user_id, $school_id, $name, $email, $mobile_number, $qualification, $grade, $role);
                
                if ($staff_stmt->execute()) {
                    // Success message and redirect
                    $message = '<div class="alert alert-success">Teacher registered successfully! Redirecting...</div>';
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = 'head_teacher_dashboard.php?section=staff_management';
                        }, 2000);
                    </script>";
                } else {
                    $message = '<div class="alert alert-danger">Error registering teacher: ' . $staff_stmt->error . '</div>';
                }
                $staff_stmt->close();
            }
            $check_stmt->close();
        }
        $user_stmt->close();
    }
}

// Fetch school name for display
$school_name = $conn->query("SELECT school_name FROM schools WHERE school_id = $school_id")->fetch_assoc()['school_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Registration - CBC Performance Tracking</title>
    <!-- CSS Styles -->
    <link rel="stylesheet" href="assets/styles_head_teacher.css">
    <style>
        /* Alert styling for success/error messages */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="content-box">
        <h2>Register New Teacher</h2>
        <!-- Display success/error messages -->
        <?php if ($message) echo $message; ?>
        
        <!-- Staff Registration Form -->
        <form method="POST" onsubmit="return validateForm()">
            <!-- Name input field -->
            <div class="form-group">
                <label for="name">Full Name:</label>
                <input type="text" name="name" id="name" 
                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                       required>
            </div>
            
            <!-- School display field (non-editable) -->
            <div class="form-group">
                <label for="school">School:</label>
                <input type="text" id="school" 
                       value="<?php echo htmlspecialchars($school_name); ?>" 
                       disabled>
            </div>
            
            <!-- Email input field -->
            <div class="form-group">
                <label for="email">Email (Must match user registration email):</label>
                <input type="email" name="email" id="email" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                       required>
            </div>
            
            <!-- Phone number input field -->
            <div class="form-group">
                <label for="phone">Mobile Number:</label>
                <input type="text" name="phone" id="phone" 
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                       required>
            </div>
            
            <!-- Qualification dropdown -->
            <div class="form-group">
                <label for="qualification">Qualification:</label>
                <select name="qualification" id="qualification" required>
                    <option value="">Select Qualification</option>
                    <option value="Diploma" <?php echo (isset($_POST['qualification']) && $_POST['qualification'] === 'Diploma') ? 'selected' : ''; ?>>Diploma</option>
                    <option value="Degree" <?php echo (isset($_POST['qualification']) && $_POST['qualification'] === 'Degree') ? 'selected' : ''; ?>>Degree</option>
                    <option value="Masters" <?php echo (isset($_POST['qualification']) && $_POST['qualification'] === 'Masters') ? 'selected' : ''; ?>>Masters</option>
                    <option value="PhD" <?php echo (isset($_POST['qualification']) && $_POST['qualification'] === 'PhD') ? 'selected' : ''; ?>>PhD</option>
                </select>
            </div>
            
            <!-- Grade level dropdown -->
            <div class="form-group">
                <label for="grade">Assigned Grade:</label>
                <select name="grade" id="grade" required>
                    <option value="">Select Grade</option>
                    <option value="4" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '4') ? 'selected' : ''; ?>>Grade 4</option>
                    <option value="5" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '5') ? 'selected' : ''; ?>>Grade 5</option>
                    <option value="6" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '6') ? 'selected' : ''; ?>>Grade 6</option>
                    <option value="7" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '7') ? 'selected' : ''; ?>>Grade 7</option>
                    <option value="8" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '8') ? 'selected' : ''; ?>>Grade 8</option>
                    <option value="9" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '9') ? 'selected' : ''; ?>>Grade 9</option>
                </select>
            </div>
            
            <button type="submit" class="submit-btn">Register Teacher</button>
        </form>
    </div>

    <script>
    /**
     * Form Validation Function
     * Validates all form fields before submission
     * 
     * @returns {boolean} Returns true if all validations pass, false otherwise
     */
    function validateForm() {
        // Get form field values and trim whitespace
        var name = document.getElementById("name").value.trim();
        var email = document.getElementById("email").value.trim();
        var phone = document.getElementById("phone").value.trim();
        var qualification = document.getElementById("qualification").value;
        var grade = document.getElementById("grade").value;

        // Check if any field is empty
        if (name === "" || email === "" || phone === "" || qualification === "" || grade === "") {
            alert("All fields must be filled out.");
            return false;
        }

        // Simple email validation - just check for @ symbol
        if (!email.includes("@")) {
            alert("Please enter a valid email address.");
            return false;
        }

        // Simple phone validation - just check if it's 10 digits
        if (phone.length !== 10 || isNaN(phone)) {
            alert("Phone number must be 10 digits.");
            return false;
        }

        return true;
    }
    </script>
</body>
</html>