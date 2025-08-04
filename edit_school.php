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
    $stmt = $conn->prepare("DELETE FROM schools WHERE school_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    header('Location:admin_dashboard.php?section=schools');
    exit();
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_school'])) {
    $school_id = $_POST['school_id'];
    $school_name = $_POST['school_name'];
    $county = $_POST['county'];
    $sub_county = $_POST['sub_county'];
    $ward = $_POST['ward'];
    $registration_id = $_POST['registration_id'];
    $date_of_registration = $_POST['date_of_registration'];
    $school_level = $_POST['school_level'];
    $school_category = $_POST['school_category'];

    $stmt = $conn->prepare("UPDATE schools SET school_name = ?, county = ?, sub_county = ?, ward = ?, registration_id = ?, date_of_registration = ?, school_level = ?, school_category = ? WHERE school_id = ?");
    $stmt->bind_param("ssssssssi", $school_name, $county, $sub_county, $ward, $registration_id, $date_of_registration, $school_level, $school_category, $school_id);
    $stmt->execute();
    $stmt->close();
    header('Location: admin_dashboard.php?section=schools');
    exit();
}

// Fetch school data
$school_id = $_GET['id'] ?? 0;
$stmt = $conn->prepare("SELECT * FROM schools WHERE school_id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$school) {
    header('Location: admin_dashboard.php?section=schools');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit School - CBC Performance Tracking</title>
    <link rel="stylesheet" href="assets/styles_system_admin.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="main-content">
            <div class="content-box">
                <h2>Edit School</h2>
                <form method="post">
                    <input type="hidden" name="school_id" value="<?php echo $school['school_id']; ?>">
                    <div class="form-group">
                        <label>School Name:</label>
                        <input type="text" name="school_name" value="<?php echo htmlspecialchars($school['school_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>County:</label>
                        <input type="text" name="county" value="<?php echo htmlspecialchars($school['county']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Sub-County:</label>
                        <input type="text" name="sub_county" value="<?php echo htmlspecialchars($school['sub_county']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Ward:</label>
                        <input type="text" name="ward" value="<?php echo htmlspecialchars($school['ward']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Registration ID:</label>
                        <input type="text" name="registration_id" value="<?php echo htmlspecialchars($school['registration_id']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Date of Registration (DD-MM-YYYY):</label>
                        <input type="text" name="date_of_registration" placeholder="DD-MM-YYYY" value="<?php 
                            $date = new DateTime($school['date_of_registration']);
                            echo $date->format('d-m-Y');
                        ?>" required>
                    </div>
                    <div class="form-group">
                        <label>School Level:</label>
                        <select name="school_level" required>
                            <option value="Primary" <?php echo $school['school_level'] === 'Primary' ? 'selected' : ''; ?>>Primary</option>
                            <option value="Secondary" <?php echo $school['school_level'] === 'Secondary' ? 'selected' : ''; ?>>Secondary</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>School Category:</label>
                        <select name="school_category" required>
                            <option value="Day" <?php echo $school['school_category'] === 'Day' ? 'selected' : ''; ?>>Day</option>
                            <option value="Boarding" <?php echo $school['school_category'] === 'Boarding' ? 'selected' : ''; ?>>Boarding</option>
                            <option value="Mixed" <?php echo $school['school_category'] === 'Mixed' ? 'selected' : ''; ?>>Mixed</option>
                        </select>
                    </div>
                    <input type="submit" name="update_school" value="Update School" class="submit-btn">
                    <a href="admin_dashboard.php?section=schools" class="back-btn">Back</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>