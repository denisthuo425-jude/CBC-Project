<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Head Teacher') {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: head_teacher_dashboard.php?section=manage_teachers');
    exit();
}

$staff_id = $_GET['id'];
$sql = "SELECT s.name, s.email, s.mobile_number, s.qualification, s.role, s.assigned_grade, sc.school_name 
        FROM staffs s 
        LEFT JOIN schools sc ON s.school_id = sc.school_id 
        WHERE s.staff_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();
$stmt->close();

if (!$teacher) {
    echo "Teacher not found.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Teacher - CBC Performance Tracking</title>
    <link rel="stylesheet" href="styles_head_teacher.css">
</head>
<body>
    <div class="content-box">
        <h2>Teacher Details</h2>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($teacher['name']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($teacher['email']); ?></p>
        <p><strong>Mobile Number:</strong> <?php echo htmlspecialchars($teacher['mobile_number']); ?></p>
        <p><strong>Qualification:</strong> <?php echo htmlspecialchars($teacher['qualification']); ?></p>
        <p><strong>Role:</strong> <?php echo htmlspecialchars($teacher['role']); ?></p>
        <p><strong>Assigned Grade:</strong> <?php echo htmlspecialchars($teacher['assigned_grade'] ?? 'N/A'); ?></p>
        <p><strong>School:</strong> <?php echo htmlspecialchars($teacher['school_name']); ?></p>
        <a href="head_teacher_dashboard.php?section=manage_teachers" class="back-btn">Back to Teachers</a>
    </div>
</body>
</html>