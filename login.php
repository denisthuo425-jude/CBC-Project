<?php
date_default_timezone_set('Africa/Nairobi');
session_start(); //built in function that starts or resumessession
require_once 'db_connect.php'; // Includes database connection to establish a MySQLi connection once.
require_once 'activity_log.php'; // For logging user activities

// Get input values
// Remove role from input processing
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
//  Function: isset() checks if 'username' was submitted in the POST form
//  Function: trim() removes spaces from both ends of the input.
//  $username is a string variable containing the cleaned input. 
//  $_POST - Global PHP array that retrieves data sent from a form
$password = isset($_POST['password']) ? trim($_POST['password']) : ''; // Retrieves and trims password
//  Function: isset() checks if 'password' was submitted in the POST form
//  Function: trim() removes spaces from both ends of the input.
//  $password is a string variable containing the cleaned input. 
// Remove role from empty check
if (empty($username) || empty($password)) { // Function: empty() checks if a variable has no value.
    header("Location: login.html?error=" . urlencode("All fields are required!")); // Redirects with error
    exit();
}
// Functions:
//   - empty(): Checks if a variable is empty (built-in PHP).
//   - header(): Sends HTTP headers for redirection (built-in PHP).
//   - urlencode(): Encodes error message for URL safety (built-in PHP).
//   - exit(): Terminates script execution (built-in PHP).
// Symbols:
//   - ||: Logical OR operator.
//   - .: String concatenation.

// Prepare query to fetch user based on username
$sql = "SELECT user_id, username, password, role FROM users WHERE username = ?"; // SQL query with placeholder
$stmt = $conn->prepare($sql); // // Method: prepare() - MySQLi method to create a prepared statement.
$stmt->bind_param("s", $username); // Binds username to placeholder
$stmt->execute(); // Executes the query
$result = $stmt->get_result(); // Method: get_result() - MySQLi method.
//   - $conn: MySQLi connection object (from db_connect.php).

// Checks if exactly one user is found
if ($result->num_rows === 1) { 
    $row = $result->fetch_assoc(); // Fetches user data (results) as associative array
    // Variables:
    //   - $result: mysqli_result object.
    //   - $row: Associative array of user data.
    // Property: num_rows - Number of rows in the result set (MySQLi).
    // Method: fetch_assoc() - Retrieves result row as an associative array (MySQLi).
    // Symbol: === - Strict equality comparison. password check 
   
    $stored_password = $row['password']; // Stored password from database
    $password_correct = password_verify($password, $stored_password) || ($password === $stored_password);
    // Variable: $stored_password - String (hashed or plain text)
    // Variable: $password_correct - Boolean, true if either verification passes
    // Function: password_verify() - Checks if input matches hashed password
    // Operator: || - Logical OR, goes ahead to check for plain text comparison
    //   - ===: Strict equality for plaintext

    // Verify password and role
    if ($password_correct) { // Checks if password and role match
        // Set session variables
        $_SESSION['user_id'] = $row['user_id']; // Stores user_id in session
        $_SESSION['username'] = $row['username']; // Stores username in session
        $_SESSION['role'] = $row['role']; // Stores role in session
        // ($_SESSION - array): Type: Global variable : Storing Data in Sessions


        // Insert login record into user_activity
        // log_activity($row['user_id'], $row['role'], 'login'); // Log login activity (REMOVED)
        $stmt_activity = $conn->prepare("INSERT INTO user_activity (user_id, role, login_time) VALUES (?, ?, NOW())");
        $stmt_activity->bind_param("is", $row['user_id'], $row['role']);
        $stmt_activity->execute();
        $stmt_activity->close();

        // Redirect user based on role
        $role = $row['role'];
        $stmt->close(); // Closes main statement
        $conn->close(); // Closes connection
        if ($role === "Teacher") {
            header("Location: teacher_dashboard.php");
        } elseif ($role === "Head Teacher") {
            header("Location: head_teacher_dashboard.php");
        } elseif ($role === "County Admin") {
            header("Location: county_admin_dashboard.php");
        } elseif ($role === "System Admin") {
            header("Location: admin_dashboard.php");
        } elseif ($role === "Student") {
            header("Location: student_dashboard.php");        
        } else {
            header("Location: login.html?error=" . urlencode("Unknown role. Contact admin."));
        }
        exit();
    } else { // If password or role doesn't match
        $stmt->close(); // Closes statement
        $conn->close(); // Closes connection
        if (!$password_correct) { // If password is incorrect
            header("Location: login.html?error=" . urlencode("Incorrect password!"));
        } 
        exit();
    }
} else { // If username not found
    $stmt->close(); // Closes statement
    $conn->close(); // Closes connection
    header("Location: login.html?error=" . urlencode("Username not found!"));
    exit();
}
?>