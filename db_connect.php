<?php
// Include database configuration file with credentials
// __DIR__ -  contains the directory of the current file
// '/../conf/config.php' - Relative path to configuration file (one directory up, then conf folder)
// Purpose: Loads database connection parameters (servername, username, password, dbname)
// Variable: $servername, $username, $password, $dbname - String variables from config file
include __DIR__ . '/../conf/config.php';

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname); // Creates a new MySQLi connection object
// Purpose: Establishes a connection to the MySQL database using provided credentials
// Variable: $conn - Object (mysqli), represents the database connection
// Class: mysqli - PHP class for MySQLi

// Check connection
if ($conn->connect_error) { // Checks if the connection failed
// Purpose: Verifies that the database connection was successful
// Property: connect_error - String property of mysqli object, contains error message if connection fails
    die("Connection failed: " . $conn->connect_error); // Terminates script with error message if connection fails
    // Purpose: Outputs the error and stops execution if connection cannot be established
    // Function: die() - Built-in PHP function, outputs a message and terminates script
}
?>
