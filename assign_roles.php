<?php
session_start();
require_once 'db_connect.php';
require_once 'activity_log.php'; // For logging user activities

// Check if user is authenticated and is a System Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $qualification = $_POST['qualification'];
    $mobile_number = $_POST['mobile_number'];
    $error = '';

    // Validate mobile number format (10 digits)
    // Check if mobile number length is exactly 10 characters
    if (strlen($mobile_number) !== 10) {
        $error = "Mobile number must be exactly 10 digits.";
    } else {
        // Check if all characters are digits (0-9)
        for ($i = 0; $i < strlen($mobile_number); $i++) {
            $char = $mobile_number[$i];
            if ($char < '0' || $char > '9') {
                $error = "Mobile number must contain only digits (0-9).";
                break;
            }
        }
    }

    // Get user details
    $user_stmt = $conn->prepare("SELECT username, email, role FROM users WHERE user_id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();

    if (!$error) {
        if ($_POST['action'] === 'assign_head_teacher') {
            $school_id = $_POST['school_id'];

            // Check if school already has a head teacher
            $check_stmt = $conn->prepare("SELECT staff_id FROM staffs WHERE school_id = ? AND role = 'Head Teacher'");
            $check_stmt->bind_param("i", $school_id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = "This school already has a head teacher assigned.";
            }
            $check_stmt->close();

            if (!$error) {
                // Insert head teacher record
                $stmt = $conn->prepare("INSERT INTO staffs (user_id, school_id, name, email, mobile_number, qualification, role) VALUES (?, ?, ?, ?, ?, ?, 'Head Teacher')");
                $stmt->bind_param("iissss", $user_id, $school_id, $user['username'], $user['email'], $mobile_number, $qualification);
                
                if ($stmt->execute()) {
                    header('Location: admin_dashboard.php?section=assign_roles&success=1');
                    exit();
                } else {
                    $error = "Error assigning head teacher: " . $stmt->error;
                }
                $stmt->close();
            }
        } elseif ($_POST['action'] === 'assign_county_admin') {
            $county = ucfirst(trim($_POST['county']));

            // Check if county already has an admin
            $check_stmt = $conn->prepare("SELECT staff_id FROM staffs WHERE role = 'County Admin' AND name LIKE ?");
            $county_pattern = "admin." . strtolower($county) . "%";
            $check_stmt->bind_param("s", $county_pattern);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = "This county already has an admin assigned.";
            }
            $check_stmt->close();

            if (!$error) {
                // Verify username format (admin.countyname)
                if (strpos($user['username'], 'admin.') !== 0) {
                    $error = "County admin username must start with 'admin.'";
                } else {
                    // Insert county admin record
                    $stmt = $conn->prepare("INSERT INTO staffs (user_id, name, email, mobile_number, qualification, role) VALUES (?, ?, ?, ?, ?, 'County Admin')");
                    $stmt->bind_param("issss", $user_id, $user['username'], $user['email'], $mobile_number, $qualification);
                    
                    if ($stmt->execute()) {
                        header('Location: admin_dashboard.php?section=assign_roles&success=1');
                        exit();
                    } else {
                        $error = "Error assigning county admin: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }

    if ($error) {
        header("Location: admin_dashboard.php?section=assign_roles&error=" . urlencode($error));
        exit();
    }
}

// If not POST request, redirect to dashboard
header('Location: admin_dashboard.php?section=assign_roles');
exit();
?> 