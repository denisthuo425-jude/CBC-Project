<?php
session_start();
require_once 'db_connect.php';
require_once 'activity_log.php'; // For logging user activities

// Check teacher authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Teacher') {
    header('Location: login.php');
    exit();
}

// Ensure student ID is provided
if (!isset($_GET['id'])) {
    header('Location: teacher_dashboard.php?section=my_class');
    exit();
}

$student_id = $_GET['id'];

// Fetch student data with prepared statement
$sql = "SELECT name, date_of_birth, current_grade, gender, guardian_name, guardian_phone, guardian_email, guardian_address, nationality 
        FROM students 
        WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    echo "Student not found.";
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $dob = trim($_POST['date_of_birth']);
    $current_grade = trim($_POST['current_grade']);
    $gender = trim($_POST['gender']);
    $guardian_name = trim($_POST['guardian_name']);
    $guardian_phone = trim($_POST['guardian_phone']);
    $guardian_email = trim($_POST['guardian_email']) ?: null;
    $guardian_address = trim($_POST['guardian_address']) ?: null;
    $nationality = trim($_POST['nationality']) ?: null;

    if (empty($name) || empty($dob) || empty($current_grade) || empty($gender) || empty($guardian_name) || empty($guardian_phone)) {
        echo "Required fields cannot be empty.";
    } else {
        $sql = "UPDATE students SET name = ?, date_of_birth = ?, current_grade = ?, gender = ?, guardian_name = ?, guardian_phone = ?, guardian_email = ?, guardian_address = ?, nationality = ? WHERE student_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssi", $name, $dob, $current_grade, $gender, $guardian_name, $guardian_phone, $guardian_email, $guardian_address, $nationality, $student_id);
        if ($stmt->execute()) {
            header('Location: teacher_dashboard.php?section=my_class');
            exit();
        } else {
            echo "Error updating student: " . $conn->error;
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
    <title>Edit Student - CBC Performance Tracking</title>
    <link rel="stylesheet" href="assets/styles.css">
    <script>
        // Validate edit student form
        function validateEditForm(event) {
            event.preventDefault();
            let name = document.getElementById("name").value.trim();
            let dob = document.getElementById("date_of_birth").value.trim();
            let currentGrade = document.getElementById("current_grade").value.trim();
            let gender = document.getElementById("gender").value;
            let guardianName = document.getElementById("guardian_name").value.trim();
            let guardianPhone = document.getElementById("guardian_phone").value.trim();
            let guardianEmail = document.getElementById("guardian_email").value.trim();

            // Check required fields
            if (name === "" || dob === "" || currentGrade === "" || gender === "" || 
                guardianName === "" || guardianPhone === "") {
                alert("All required fields must be filled out.");
                return false;
            }

            // Simple email validation if provided
            if (guardianEmail !== "" && !guardianEmail.includes("@")) {
                alert("Please enter a valid email address.");
                return false;
            }

            // Simple phone validation - check if it's 10 digits
            if (guardianPhone.length !== 10 || isNaN(guardianPhone)) {
                alert("Phone number must be 10 digits.");
                return false;
            }

            // Validate date format (DD-MM-YYYY)
            let dateParts = dob.split('-');
            if (dateParts.length !== 3 || dateParts[0].length !== 2 || dateParts[1].length !== 2 || dateParts[2].length !== 4) {
                alert("Please enter date in DD-MM-YYYY format");
                return false;
            }

            // Convert date to YYYY-MM-DD for database
            let dbDate = dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0];
            document.getElementById("date_of_birth").value = dbDate;

            // Submit form if all validations pass
            document.getElementById("editForm").submit();
        }
    </script>
</head>
<body>
    <div class="content-box">
        <h2>Edit Student</h2>
        <form id="editForm" method="POST" onsubmit="validateEditForm(event)">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($student['name']); ?>">
            </div>
            <div class="form-group">
                <label for="date_of_birth">Date of Birth (DD-MM-YYYY):</label>
                <input type="text" name="date_of_birth" id="date_of_birth" placeholder="DD-MM-YYYY" value="<?php 
                    $date = new DateTime($student['date_of_birth']);
                    echo $date->format('d-m-Y');
                ?>">
            </div>
            <div class="form-group">
                <label for="current_grade">Current Grade:</label>
                <input type="text" name="current_grade" id="current_grade" value="<?php echo htmlspecialchars($student['current_grade']); ?>">
            </div>
            <div class="form-group">
                <label for="gender">Gender:</label>
                <select name="gender" id="gender">
                    <option value="Male" <?php if ($student['gender'] === 'Male') echo 'selected'; ?>>Male</option>
                    <option value="Female" <?php if ($student['gender'] === 'Female') echo 'selected'; ?>>Female</option>
                </select>
            </div>
            <div class="form-group">
                <label for="guardian_name">Guardian Name:</label>
                <input type="text" name="guardian_name" id="guardian_name" value="<?php echo htmlspecialchars($student['guardian_name']); ?>">
            </div>
            <div class="form-group">
                <label for="guardian_phone">Guardian Phone:</label>
                <input type="text" name="guardian_phone" id="guardian_phone" value="<?php echo htmlspecialchars($student['guardian_phone']); ?>">
            </div>
            <div class="form-group">
                <label for="guardian_email">Guardian Email:</label>
                <input type="text" name="guardian_email" id="guardian_email" value="<?php echo htmlspecialchars($student['guardian_email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="guardian_address">Guardian Address:</label>
                <textarea name="guardian_address" id="guardian_address"><?php echo htmlspecialchars($student['guardian_address'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="nationality">Nationality:</label>
                <input type="text" name="nationality" id="nationality" value="<?php echo htmlspecialchars($student['nationality'] ?? ''); ?>">
            </div>
            <button type="submit" class="submit-btn">Update Student</button>
            <div class="form-actions">
                <a href="teacher_dashboard.php?section=my_class" class="back-btn">Back to My Class</a>
                <a href="teacher_dashboard.php?section=my_class" class="action-btn cancel-btn">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>