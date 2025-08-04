<?php
session_start();
require_once 'db_connect.php';
require_once 'activity_log.php'; // For logging user activities

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Head Teacher') {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: head_teacher_dashboard.php?section=manage_teachers');
    exit();
}

$staff_id = $_GET['id'];
$sql = "SELECT s.name, s.email, s.mobile_number, s.qualification, s.role, s.assigned_grade, s.school_id 
        FROM staffs s 
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $qualification = trim($_POST['qualification']);
    $role = trim($_POST['role']);
    $grade = $role === 'Teacher' ? trim($_POST['grade']) : null;

    $update_sql = "UPDATE staffs SET name = ?, email = ?, mobile_number = ?, qualification = ?, role = ?, assigned_grade = ? WHERE staff_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssssssi", $name, $email, $phone, $qualification, $role, $grade, $staff_id);
    if ($stmt->execute()) {
        header("Location: head_teacher_dashboard.php?section=manage_teachers&status=success");
        log_activity($_SESSION['user_id'], $_SESSION['role'], 'update_teacher');
    } else {
        echo "Error updating teacher: " . $stmt->error;
    }
    $stmt->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Teacher - CBC Performance Tracking</title>
    <link rel="stylesheet" href="assets/styles_head_teacher.css">
    <script>
        function toggleGradeField() {
            let role = document.getElementById("role").value;
            let gradeField = document.getElementById("grade");
            gradeField.disabled = role !== "Teacher";
            gradeField.required = role === "Teacher";
        }
    </script>
</head>
<body>
    <div class="content-box">
        <h2>Edit Teacher</h2>
        <form action="edit_teacher.php?id=<?php echo $staff_id; ?>" method="POST">
            <div class="form-group">
                <label for="name">Full Name:</label>
                <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($teacher['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($teacher['email']); ?>" required>
            </div>
            <div class="form-group">
                <label for="phone">Mobile Number:</label>
                <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($teacher['mobile_number']); ?>" required>
            </div>
            <div class="form-group">
                <label for="qualification">Qualification:</label>
                <select name="qualification" id="qualification" required>
                    <option value="Diploma" <?php echo $teacher['qualification'] === 'Diploma' ? 'selected' : ''; ?>>Diploma</option>
                    <option value="Degree" <?php echo $teacher['qualification'] === 'Degree' ? 'selected' : ''; ?>>Degree</option>
                    <option value="Masters" <?php echo $teacher['qualification'] === 'Masters' ? 'selected' : ''; ?>>Masters</option>
                    <option value="PhD" <?php echo $teacher['qualification'] === 'PhD' ? 'selected' : ''; ?>>PhD</option>
                </select>
            </div>
            <div class="form-group">
                <label for="role">Role:</label>
                <select name="role" id="role" onchange="toggleGradeField()" required>
                    <option value="Teacher" <?php echo $teacher['role'] === 'Teacher' ? 'selected' : ''; ?>>Teacher</option>
                    <option value="Head Teacher" <?php echo $teacher['role'] === 'Head Teacher' ? 'selected' : ''; ?>>Head Teacher</option>
                    <option value="County Admin" <?php echo $teacher['role'] === 'County Admin' ? 'selected' : ''; ?>>County Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label for="grade">Assigned Grade:</label>
                <select name="grade" id="grade" <?php echo $teacher['role'] !== 'Teacher' ? 'disabled' : ''; ?>>
                    <option value="">Select Grade</option>
                    <option value="1" <?php echo $teacher['assigned_grade'] === '1' ? 'selected' : ''; ?>>Grade 1</option>
                    <option value="2" <?php echo $teacher['assigned_grade'] === '2' ? 'selected' : ''; ?>>Grade 2</option>
                    <option value="3" <?php echo $teacher['assigned_grade'] === '3' ? 'selected' : ''; ?>>Grade 3</option>
                    <option value="4" <?php echo $teacher['assigned_grade'] === '4' ? 'selected' : ''; ?>>Grade 4</option>
                    <option value="5" <?php echo $teacher['assigned_grade'] === '5' ? 'selected' : ''; ?>>Grade 5</option>
                    <option value="6" <?php echo $teacher['assigned_grade'] === '6' ? 'selected' : ''; ?>>Grade 6</option>
                    <option value="7" <?php echo $teacher['assigned_grade'] === '7' ? 'selected' : ''; ?>>Grade 7</option>
                    <option value="8" <?php echo $teacher['assigned_grade'] === '8' ? 'selected' : ''; ?>>Grade 8</option>
                    <option value="9" <?php echo $teacher['assigned_grade'] === '9' ? 'selected' : ''; ?>>Grade 9</option>
                </select>
            </div>
            <button type="submit" class="submit-btn">Update Teacher</button>
        </form>
        <a href="head_teacher_dashboard.php?section=manage_teachers" class="back-btn">Cancel</a>
    </div>
</body>
</html>