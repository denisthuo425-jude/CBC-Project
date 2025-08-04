<?php
session_start(); // Starts or resumes a session to track user data across pages
// Purpose: Initializes the session to access session variables like user_id and role
// Function: session_start() - Built-in PHP function to manage sessions

require_once 'db_connect.php'; // Includes the database connection file once
// Purpose: Ensures database connectivity by including db_connect.php
// Function: require_once - Ensures the file is included only once to avoid redefinition errors

require_once 'activity_log.php'; // For logging user activities

// Check if user is authenticated and a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Teacher') { // Checks if user_id is not set or role is not "Teacher"
// Purpose: Restricts access to authenticated teachers only
// Variables: $_SESSION['user_id'] - Integer/string, $_SESSION['role'] - String, session array elements
// Function: isset() - Checks if a variable is set and not null
    header('Location: login.php'); // Redirects to login.php if condition fails
    // Purpose: Sends the user back to login page
    // Function: header() - Sends a raw HTTP header
    exit(); // Terminates script execution
    // Function: exit() - Stops the script immediately
}

// Fetch school_id using prepared statement
$stmt = $conn->prepare("SELECT school_id FROM staffs WHERE user_id = ?"); // Prepares a SQL query with a placeholder
// Purpose: Safely retrieves school_id for the logged-in teacher
// Variable: $stmt - Object (mysqli_stmt) representing the prepared statement
// Variable: $conn - Object (mysqli) from db_connect.php, database connection
// Method: prepare() - mysqli object method to prepare a parameterized query
$stmt->bind_param("i", $_SESSION['user_id']); // Binds user_id to the placeholder (?)
// Purpose: Associates the user_id with the query parameter
// Method: bind_param() - mysqli_stmt method, "i" indicates integer type
$stmt->execute(); // Executes the prepared statement
// Purpose: Runs the query on the database
// Method: execute() - mysqli_stmt method to execute the prepared query
$result = $stmt->get_result(); // Retrieves the result set from the executed query
// Purpose: Stores the query results
// Variable: $result - Object (mysqli_result) containing the query results
// Method: get_result() - mysqli_stmt method to fetch results
if (!$result || $result->num_rows === 0) { // Checks if result is false or no rows returned
// Purpose: Ensures the query succeeded and returned data
// Property: num_rows - mysqli_result property (integer) counting returned rows
    die("Error: Could not fetch school_id."); // Terminates script with an error message
    // Function: die() - Outputs a message and stops execution
}
$school_id = $result->fetch_assoc()['school_id']; // Fetches the school_id from the result
// Purpose: Extracts school_id from the result set
// Variable: $school_id - Integer variable storing the fetched value
// Method: fetch_assoc() - mysqli_result method returning an associative array
$stmt->close(); // Closes the prepared statement
// Purpose: Frees up resources
// Method: close() - mysqli_stmt method to close the statement

// Get basic form data
$student_id = (int)$_POST['student_id']; // Retrieves and casts student_id from POST data to integer
// Purpose: Gets student_id from form submission
// Variable: $student_id - Integer
// Variable: $_POST['student_id'] - Associative array element (string) from form data
$term = $_POST['term']; // Retrieves term from POST data
// Purpose: Gets the term (e.g., "Term 1") from form submission
// Variable: $term - String
$year = (int)$_POST['year']; // Retrieves and casts year from POST data to integer
// Purpose: Gets the year from form submission
// Variable: $year - Integer

// Include subjects and populate with form data
require_once 'subjects.php'; // Includes the subjects.php file once
// Purpose: Loads the $subjects array defined in subjects.php
// Variable: $subjects - Array (global) of subject data (associative arrays)
// Validate subject scores: must be between 0 and 100
foreach ($subjects as $key => &$subject) {
    $score = (int)$_POST["{$key}_score"];
    if ($score < 0 || $score > 100) {
        // Show error and stop if score is invalid
        echo "<script>alert('Error: Score for " . htmlspecialchars($subject['name']) . " must be between 0 and 100.'); window.history.back();</script>";
        exit();
    }
    $subject['score'] = $score;
    $subject['comment'] = $_POST["{$key}_comment"] ?? '';
}
$general_comment = $_POST['general_comment'] ?? ''; // Retrieves general comment, defaults to empty string
// Purpose: Gets an overall comment from the form
// Variable: $general_comment - String

// Construct SQL query dynamically
$sql_fields = implode(', ', array_map(fn($key) => "{$key}_score, {$key}_comment", array_keys($subjects))); // Creates a string of field names
// Purpose: Dynamically builds SQL column names (e.g., "maths_score, maths_comment, ...")
// Variable: $sql_fields - String of comma-separated column names
// Function: implode() - Joins array elements into a string with separator
// Function: array_map() - Applies a callback (anonymous function) to each array element
// Function: array_keys() - Returns the keys of the $subjects array
// Function: fn() - Arrow function, short syntax for anonymous function
$sql_placeholders = implode(', ', array_fill(0, count($subjects) * 2, '?')); // Creates a string of placeholders
// Purpose: Generates placeholders (e.g., "?, ?, ?, ...") for each score and comment
// Variable: $sql_placeholders - String of comma-separated question marks
// Function: array_fill() - Creates an array filled with a value (?)
// Function: count() - Returns the number of elements in $subjects

// Prepare the SQL statement
$sql = "INSERT INTO performance (
    student_id, school_id, term, year, 
    $sql_fields, general_comment
) VALUES (
    ?, ?, ?, ?, 
    $sql_placeholders, ?
)"; // Constructs the full INSERT query
// Purpose: Defines the SQL query to insert performance data
// Variable: $sql - String containing the SQL query
$stmt = $conn->prepare($sql); // Prepares the SQL query
// Purpose: Prepares the parameterized query for execution
// Variable: $stmt - Object (mysqli_stmt)
if (!$stmt) { // Checks if preparation failed
// Purpose: Ensures the query was prepared successfully
    die("Prepare failed: " . $conn->error); // Terminates with error message
    // Property: $conn->error - String property of mysqli object containing the error
}

// Bind parameters
$param_types = "iisi" . str_repeat('is', count($subjects)) . 's'; // Defines parameter types
// Purpose: Specifies data types for binding (integer, integer, string, integer, then 'is' per subject, string)
// Variable: $param_types - String (e.g., "iisiiiiissss...")
// Function: str_repeat() - Repeats a string ('is') for each subject
$values = [$student_id, $school_id, $term, $year]; // Initializes array with initial values
// Purpose: Starts building the values array for binding
// Variable: $values - Array of mixed types (int, int, string, int)
foreach ($subjects as $subject) { // Loops through subjects
// Purpose: Adds each subject's score and comment to the values array
    $values[] = $subject['score']; // Adds score to array
    // Element: Integer
    $values[] = $subject['comment']; // Adds comment to array
    // Element: String
}
$values[] = $general_comment; // Adds general comment to values
// Element: String
$stmt->bind_param($param_types, ...$values); // Binds all parameters to the query
// Purpose: Associates values with placeholders in the query
// Method: bind_param() - mysqli_stmt method, uses splat operator (...) to unpack $values

// Execute the query and redirect
if ($stmt->execute()) { // Executes the query and checks success
// Purpose: Inserts the data into the database
// Method: execute() - mysqli_stmt method
    // Log activity for recording performance
    log_activity($_SESSION['user_id'], $_SESSION['role'], 'record_performance');
    header("Location: teacher_dashboard.php?section=assessments&status=success"); // Redirects on success
    // Purpose: Sends user to dashboard with success status
    // Function: header() - Sends HTTP header
} else {
    die("Insert failed: " . $stmt->error); // Terminates with error on failure
    // Property: $stmt->error - String property of mysqli_stmt object containing the error
}

// Clean up
$stmt->close(); // Closes the statement
// Purpose: Frees resources
// Method: close() - mysqli_stmt method
$conn->close(); // Closes the database connection
// Purpose: Ends the database session
// Method: close() - mysqli method
?>