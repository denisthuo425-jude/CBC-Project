<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_name = trim($_POST['school_name']);
    $county = trim($_POST['county']);
    $sub_county = trim($_POST['sub_county']);
    $ward = trim($_POST['ward']);
    $registration_id = trim($_POST['registration_id']);
    $date_of_registration = $_POST['date_of_registration'];
    $school_level = $_POST['school_level'];
    $school_category = $_POST['school_category'];

    // Plain validation
    if (empty($school_name)) {
        $error = "School name is required.";
    } elseif (empty($county)) {
        $error = "County is required.";
    } elseif (empty($sub_county)) {
        $error = "Sub-county is required.";
    } elseif (empty($ward)) {
        $error = "Ward is required.";
    } elseif (empty($date_of_registration)) {
        $error = "Date of registration is required.";
    } elseif (!DateTime::createFromFormat('Y-m-d', $date_of_registration)) {
        $error = "Invalid date format (YYYY-MM-DD).";    
    } elseif (!in_array($school_level, ['Primary', 'Secondary'])) {
        $error = "Invalid school level.";
    } elseif (!in_array($school_category, ['Day', 'Boarding', 'Mixed'])) {
        $error = "Invalid school category.";
    } else {
        // Check for duplicate school name in county
        $stmt = $conn->prepare("SELECT school_id FROM schools WHERE school_name = ? AND county = ?");
        $stmt->bind_param("ss", $school_name, $county);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $error = "School name already exists in this county.";
        } else {
            // Insert new school
            $stmt = $conn->prepare("INSERT INTO schools (school_name, county, sub_county, ward, registration_id, date_of_registration, school_level, school_category) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $school_name, $county, $sub_county, $ward, $registration_id, $date_of_registration, $school_level, $school_category);
            if ($stmt->execute()) {
                $success = "School added successfully.";
            } else {
                $error = "Error adding school: " . $stmt->error;
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
    <title>Add School - CBC Performance Tracking</title>
    <link rel="stylesheet" href="assets/styles_system_admin.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="main-content">
            <div class="content-box">
                <h2>Add New School</h2>
                <?php if ($error): ?>
                    <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
                <?php if ($success): ?>
                    <p style="color: green;"><?php echo htmlspecialchars($success); ?></p>
                <?php endif; ?>
                <form method="post">
                    <div class="form-group">
                        <label>School Name:</label>
                        <input type="text" name="school_name" value="<?php echo isset($_POST['school_name']) ? htmlspecialchars($_POST['school_name']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>County:</label>
                        <input type="text" name="county" value="<?php echo isset($_POST['county']) ? htmlspecialchars($_POST['county']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Sub-County:</label>
                        <input type="text" name="sub_county" value="<?php echo isset($_POST['sub_county']) ? htmlspecialchars($_POST['sub_county']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Ward:</label>
                        <input type="text" name="ward" value="<?php echo isset($_POST['ward']) ? htmlspecialchars($_POST['ward']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Registration ID:</label>
                        <input type="text" name="registration_id" value="<?php echo isset($_POST['registration_id']) ? htmlspecialchars($_POST['registration_id']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Date of Registration:</label>
                        <input type="date" name="date_of_registration" value="<?php echo isset($_POST['date_of_registration']) ? htmlspecialchars($_POST['date_of_registration']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>School Level:</label>
                        <select name="school_level" required>
                            <option value="">Select Level</option>
                            <option value="Primary">Primary</option>
                            <option value="Secondary">Secondary</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>School Category:</label>
                        <select name="school_category" required>
                            <option value="">Select Category</option>
                            <option value="Day">Day</option>
                            <option value="Boarding">Boarding</option>
                            <option value="Mixed">Mixed</option>
                        </select>
                    </div>
                    <input type="submit" value="Add School" class="submit-btn">
                    <a href="admin_dashboard.php?section=schools" class="back-btn">Back</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>