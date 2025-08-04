<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Teacher', 'Head Teacher'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: ' . ($_SESSION['role'] === 'Teacher' ? 'teacher_dashboard.php?section=my_class' : 'head_teacher_dashboard.php?section=all_students'));
    exit();
}

$student_id = $_GET['id'];
$sql = "SELECT name, date_of_birth, current_grade, gender, student_email,guardian_name, guardian_phone, guardian_email, guardian_address, nationality 
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student - CBC Performance Tracking</title>
    <link rel="stylesheet" href="<?php echo $_SESSION['role'] === 'Teacher' ? 'assets/styles.css' : 'assets/styles_head_teacher.css'; ?>">
    <script>
        function toggleDropdown() {
            document.getElementById("userDropdown").classList.toggle("hidden");
        }
    </script>
</head>
<body>
            <div class="content-box">
                <h2><?php echo htmlspecialchars($student['name']); ?></h2>
                <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($student['date_of_birth']); ?></p>
                <p><strong>Current Grade:</strong> <?php echo htmlspecialchars($student['current_grade']); ?></p>
                <p><strong>Gender:</strong> <?php echo htmlspecialchars($student['gender']); ?></p>
                <p><strong>Student email:</strong> <?php echo htmlspecialchars($student['student_email']); ?></p>
                <p><strong>Guardian Name:</strong> <?php echo htmlspecialchars($student['guardian_name']); ?></p>
                <p><strong>Guardian Phone:</strong> <?php echo htmlspecialchars($student['guardian_phone']); ?></p>
                <p><strong>Guardian Email:</strong> <?php echo htmlspecialchars($student['guardian_email'] ?? 'N/A'); ?></p>
                <p><strong>Guardian Address:</strong> <?php echo htmlspecialchars($student['guardian_address'] ?? 'N/A'); ?></p>
                <p><strong>Nationality:</strong> <?php echo htmlspecialchars($student['nationality'] ?? 'N/A'); ?></p>
                <a href="<?php echo $_SESSION['role'] === 'Teacher' ? 'teacher_dashboard.php?section=my_class' : 'head_teacher_dashboard.php?section=all_students'; ?>" class="back-btn">Back</a>
            </div>
        </div>
    </div>
</body>
</html>