<?php
session_start(); // Starts or resumes a session
require_once 'db_connect.php'; // Includes database connection
require_once 'activity_log.php'; // For logging user activities

// Check teacher authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Teacher') { // Ensures only teachers can access
    header('Location: login.php');
    exit();
}

// Get teacher's assigned grade
$teacher_grade = $conn->query("SELECT assigned_grade FROM staffs WHERE user_id = " . $_SESSION['user_id'])->fetch_assoc()['assigned_grade'];
$new_grade = "Grade " . ((int)$teacher_grade + 1);

// Function to transition a single student
function transitionStudent($conn, $student_id, $new_grade) {
    $stmt = $conn->prepare("UPDATE students SET current_grade = ? WHERE student_id = ?");
    $stmt->bind_param("si", $new_grade, $student_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Handle both GET (individual) and POST (bulk) requests
if (isset($_GET['student_id'])) {
    // Individual student transition
    $student_id = (int)$_GET['student_id'];
    if (transitionStudent($conn, $student_id, $new_grade)) {
        header('Location: teacher_dashboard.php?section=student_transition&status=success');
        log_activity($_SESSION['user_id'], $_SESSION['role'], 'transition_student');
    } else {
        header('Location: teacher_dashboard.php?section=student_transition&status=error');
    }
} elseif (isset($_GET['student_ids'])) {
    // Bulk student transition
    $student_ids = array_map('intval', explode(',', $_GET['student_ids']));
    $success = true;
    
    foreach ($student_ids as $student_id) {
        if (!transitionStudent($conn, $student_id, $new_grade)) {
            $success = false;
            break;
        }
    }
    
    if ($success) {
        header('Location: teacher_dashboard.php?section=student_transition&status=success');
        log_activity($_SESSION['user_id'], $_SESSION['role'], 'transition_students_bulk');
    } else {
        header('Location: teacher_dashboard.php?section=student_transition&status=error');
    }
} else {
    header('Location: teacher_dashboard.php?section=student_transition&status=error');
}

$conn->close();
exit();
?>