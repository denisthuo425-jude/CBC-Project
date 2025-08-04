<?php
session_start(); // Starts or resumes a session
// Purpose: Initializes session handling to track user data
// Function: session_start() - Built-in PHP function

require_once 'db_connect.php'; // Includes the database connection file once
// Purpose: Connects to the database using db_connect.php
// Function: require_once - Includes file once

// Check if user is logged in and a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Teacher') {
    header('Location: login.php');
    exit();
}

// Get teacher's assigned grade
$teacher_stmt = $conn->prepare("SELECT assigned_grade FROM staffs WHERE user_id = ?");
$teacher_stmt->bind_param("i", $_SESSION['user_id']);
$teacher_stmt->execute();
$assigned_grade = $teacher_stmt->get_result()->fetch_assoc()['assigned_grade'];
$teacher_stmt->close();

if (!$assigned_grade) {
    die("Error: Teacher does not have an assigned grade.");
}

// Retrieve form data safely
$name = $_POST['name'] ?? ''; // Gets student name, defaults to empty string if not set
// Purpose: Safely retrieves name from form submission
// Variable: $name - String
// Variable: $_POST['name'] - Associative array element (string)
// Operator: ?? - Null coalescing operator, provides default value
$dob = $_POST['dob'] ?? ''; // Gets date of birth from POST
// Variable: $dob - String (expected format: YYYY-MM-DD)
$gender = $_POST['gender'] ?? ''; // Gets gender from POST
// Variable: $gender - String
$guardian_name = $_POST['guardian_name'] ?? ''; // Gets guardian name from POST
// Variable: $guardian_name - String
$guardian_email = $_POST['guardian_email'] ?? ''; // Gets guardian email from POST
// Variable: $guardian_email - String
$guardian_phone = $_POST['guardian_phone'] ?? ''; // Gets guardian phone from POST
// Variable: $guardian_phone - String
$guardian_address = $_POST['guardian_address'] ?? ''; // Gets guardian address from POST
// Variable: $guardian_address - String
$nationality = $_POST['nationality'] ?? ''; // Gets nationality from POST
// Variable: $nationality - String
$school_id = isset($_POST['school']) ? (int)$_POST['school'] : null; // Gets school ID, casts to integer or null
$student_email = $_POST['student_email'] ?? ''; // Gets student email from POST
// Variable: $school_id - Integer or null
// Variable: $_POST['school'] - Associative array element (string)
// Function: isset() - Checks if key exists in $_POST

// Set current_grade based on teacher's assigned grade
$current_grade = "Grade " . $assigned_grade;

// Get user_id if exists
$user_id = null;
$check_user = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$check_user->bind_param("s", $student_email);
$check_user->execute();
$user_result = $check_user->get_result();
if ($user_result->num_rows > 0) {
    $user_id = $user_result->fetch_assoc()['user_id'];
}
$check_user->close();

// Validate required fields
if (empty($name) || empty($dob) || empty($gender) || empty($guardian_name) || empty($guardian_phone) || empty($nationality) || empty($school_id) || empty($student_email)) { // Checks for empty required fields
// Purpose: Ensures all mandatory fields are filled
// Function: empty() - Checks if a variable is empty
    $conn->close(); // Closes database connection
    // Method: close() - mysqli method
    die("Error: All required fields must be filled out, including school."); // Stops execution with error
    // Function: die() - Outputs message and terminates
}

// Additional validation
$dob_date = new DateTime($dob); // Creates DateTime object from date of birth
// Purpose: Validates and processes the date
// Variable: $dob_date - Object (DateTime)
// Class: DateTime - PHP class for date/time handling
$dob_year = (int)$dob_date->format('Y'); // Extracts year as integer
// Purpose: Gets the year of birth for validation
// Variable: $dob_year - Integer
// Method: format() - DateTime method to format date as string 
if ($dob_year < 2007 || $dob_year > 2019) { // Checks if year is within valid range
// Purpose: Ensures student age is between 6 and 18
    $conn->close(); // Closes database connection
    // Method: close() - mysqli method
    die("Error: Student must be between 6 and 18 years old (born 2007-2019)."); // Terminates with error
    // Function: die() - Outputs message and terminates
}
if (strlen($guardian_phone) !== 10 || !in_array(substr($guardian_phone, 0, 2), ['07', '01'])) { // Validates phone number
// Purpose: Ensures phone is 10 digits and starts with "07" or "01"
// Function: strlen() - Returns string length
// Function: substr() - Extracts substring (first 2 digits)
// Function: in_array() - Checks if value exists in array
    $conn->close(); // Closes database connection
    // Method: close() - mysqli method
    die("Error: Guardian phone number must be 10 digits and start with 07 or 01."); // Terminates with error
    // Function: die() - Outputs message and terminates
}

// Handle optional fields
$guardian_email_val = $guardian_email ?: null; // Sets email to null if empty
// Purpose: Converts empty string to null for database
// Variable: $guardian_email_val - String or null
// Operator: ?: - Ternary operator (short form of if-else)
$guardian_address_val = $guardian_address ?: null; // Sets address to null if empty
// Purpose: Converts empty string to null for database
// Variable: $guardian_address_val - String or null

// Convert date from DD-MM-YYYY to YYYY-MM-DD for MySQL
if (!empty($dob)) {
    $date_parts = explode('-', $dob);
    if (count($date_parts) === 3) {
        $dob = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
    }
}

// Prepare SQL query
$sql = "INSERT INTO students (school_id, name, date_of_birth, gender, current_grade, guardian_name, guardian_email, guardian_phone, guardian_address, nationality, student_email, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // Defines SQL query with placeholders
// Purpose: Prepares to insert student data
// Variable: $sql - String
$stmt = $conn->prepare($sql); // Prepares the query
// Purpose: Creates prepared statement for secure insertion
// Variable: $stmt - Object (mysqli_stmt)
// Variable: $conn - Object (mysqli)
// Method: prepare() - mysqli method
if (!$stmt) { // Checks if preparation failed
// Purpose: Ensures query preparation succeeded
    $conn->close(); // Closes database connection
    // Method: close() - mysqli method
    die("Error preparing statement: " . $conn->error); // Terminates with error
    // Property: $conn->error - String, error message from mysqli object
    // Function: die() - Outputs message and terminates
}

// Bind parameters
$stmt->bind_param("issssssssssi", $school_id, $name, $dob, $gender, $current_grade, $guardian_name, $guardian_email_val, $guardian_phone, $guardian_address_val, $nationality, $student_email, $user_id); // Binds values
// Purpose: Associates form data with query placeholders
// Method: bind_param() - mysqli_stmt method, "i" for integer, "s" for string

// Execute the query
if ($stmt->execute()) { // Executes and checks success
// Purpose: Inserts student data into database
// Method: execute() - mysqli_stmt method
    $stmt->close(); // Closes the prepared statement
    // Method: close() - mysqli_stmt method
    $conn->close(); // Closes database connection
    // Method: close() - mysqli method
    header("Location: teacher_dashboard.php?section=student_registration&status=success"); // Redirects on success
    // Purpose: Sends user to dashboard with success status
    // Function: header() - Sends HTTP header
    exit(); // Terminates script
    // Function: exit() - Stops execution
} else {
    $stmt->close(); // Closes the prepared statement
    // Method: close() - mysqli_stmt method
    $conn->close(); // Closes database connection
    // Method: close() - mysqli method
    die("Error: " . $stmt->error); // Terminates with error
    // Property: $stmt->error - String, error message from mysqli_stmt object
    // Function: die() - Outputs message and terminates
}
?>
