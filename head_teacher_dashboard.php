<?php
// Start session to manage user authentication
session_start();
// Include database connection for MySQLi queries
require_once 'db_connect.php';
// Include subjects array for report card subjects and performance reports
require_once 'subjects.php';

// Check if user is logged in and has Head Teacher role
// isset() - function that verifies if the session variables exists and is not null.
// $_SESSION is a PHP superglobal array that stores user data across multiple pages
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Head Teacher') {
    // Redirect to login page if not authenticated
    header('Location: login.php');
    exit();
}

// Fetch head teacher details from users and staffs tables
// Using $conn->prepare() to create a secure SQL query that prevents SQL injection
$stmt = $conn->prepare("SELECT u.username, s.email, s.mobile_number, s.school_id 
                        FROM users u JOIN staffs s ON u.user_id = s.user_id 
                        WHERE u.user_id = ?");
// Method: bind_param - Binds user_id to query ('i' for integer)
$stmt->bind_param("i", $_SESSION['user_id']);
// Method: execute - Executes the query
$stmt->execute();
// Variable: $head_teacher - Associative array, stores head teacher data
$head_teacher = $stmt->get_result()->fetch_assoc();
// Variable: $head_teacher_name - String, head teacher's username
$head_teacher_name = $head_teacher['username'];
// Variable: $school_id - Integer, school's unique identifier
$school_id = $head_teacher['school_id'];
// Method: close - Closes the prepared statement
$stmt->close();

// Check if head teacher is assigned to a school
if (!$school_id) {
    // Display error and stop execution if no school assignment
    die("Error: Head Teacher not assigned to a school.");
}

// Fetch school name and county from schools table
// Using $conn->prepare() to create a secure SQL query that prevents SQL injection
$stmt = $conn->prepare("SELECT school_name, county FROM schools WHERE school_id = ?");
// Method: bind_param - Binds school_id to query ('i' for integer)
$stmt->bind_param("i", $school_id);
// Method: execute - Executes the query
$stmt->execute();
// Variable: $school_data - Associative array, stores school information
$school_data = $stmt->get_result()->fetch_assoc();
// Variable: $school_name - String, name of the school
$school_name = $school_data['school_name'];
// Variable: $county - String, county where school is located
$county = $school_data['county'];
// Method: close - Closes the prepared statement
$stmt->close();

// Handle transfer request submission from POST form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_transfer'])) {
    // Variable: $student_id - Integer, student's unique identifier
    $student_id = $_POST['student_id'];
    // Variable: $to_school_id - Integer, destination school's unique identifier
    $to_school_id = $_POST['to_school_id'];
    // Variable: $request_date - String, current date in Y-m-d format
    $request_date = date('Y-m-d');

    // Insert transfer request into student_transfers table
    // Using $conn->prepare() to create a secure SQL query that prevents SQL injection
    $stmt = $conn->prepare("INSERT INTO student_transfers (student_id, from_school_id, to_school_id, request_date, status) 
                            VALUES (?, ?, ?, ?, 'Pending')");
    // Method: bind_param - Binds student_id (integer), from_school_id (integer), to_school_id (integer), request_date (string)
    $stmt->bind_param("iiis", $student_id, $school_id, $to_school_id, $request_date);
    // Check if transfer request was successfully inserted
    if ($stmt->execute()) {
        // Variable: $transfer_message - String, success message for transfer request
        $transfer_message = "Transfer request submitted successfully.";
    } else {
        // Variable: $transfer_message - String, error message for transfer request failure
        $transfer_message = "Error submitting transfer request: " . $conn->error;
    }
    // Method: close - Closes the prepared statement
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta tags for character encoding and responsive viewport -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Head Teacher Dashboard - CBC Performance Tracking</title>
    <!-- Link to head teacher-specific styles for styling -->
    <link rel="stylesheet" href="assets/styles_head_teacher.css">
    <!-- Link to responsive styles for mobile compatibility -->
    <link rel="stylesheet" href="assets/responsive.css">
</head>
<body>
    <!-- Main container for dashboard layout (flexbox for sidebar and content) -->
    <div class="dashboard-container">
        <!-- Sidebar navigation -->
        <aside class="sidebar">
            <!-- Sidebar header with dashboard title -->
            <div class="sidebar-header">
                <h2>CBC Dashboard</h2>
            </div>
            <!-- Navigation links for dashboard sections -->
            <nav class="sidebar-nav">
                <a href="?section=home" class="nav-item"><span class="nav-icon">🏠</span> Home</a>
                <a href="?section=staff_registration" class="nav-item"><span class="nav-icon">📝</span> Staff Registration</a>
                <a href="?section=manage_teachers" class="nav-item"><span class="nav-icon">👩‍🏫</span> Manage Teachers</a>
                <a href="?section=all_students" class="nav-item"><span class="nav-icon">📊</span> All Students</a>
                <a href="?section=student_transfers" class="nav-item"><span class="nav-icon">🔄</span> Student Transfers</a>
                <a href="?section=performance_reports" class="nav-item"><span class="nav-icon">📈</span> Performance Reports</a>
            </nav>
        </aside>
        <!-- Main content area -->
        <div class="main-content">
            <!-- Top header with title and user menu -->
            <header class="top-header">
                <h1>Head Teacher Dashboard</h1>
                <!-- User menu for profile and logout -->
                <div class="user-menu">
                    <!-- Button to toggle user menu -->
                    <button id="userMenuBtn" class="user-menu-btn">
                        <!-- Variable: $head_teacher_name - Head teacher's name for display -->
                        <span><?php echo htmlspecialchars($head_teacher_name ?? 'Head Teacher'); ?></span>
                        <!-- Dynamic avatar using ui-avatars.com -->
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($head_teacher_name ?? 'Head Teacher'); ?>" alt="Head Teacher" class="user-avatar">
                    </button>
                    <!-- Dropdown menu, hidden by default -->
                    <div id="userMenu" class="user-menu-dropdown hidden">
                        <a href="?section=profile" class="dropdown-item">Profile</a>
                        <a href="logout.php" class="dropdown-item">Logout</a>
                    </div>
                </div>
            </header>
            <!-- Main content area for sections -->
            <main class="content-area">
                <?php
                // Variable: $section - String, determines current section from URL
                $section = isset($_GET['section']) ? $_GET['section'] : 'home';
                if ($section === 'home') {
                    ?>
                    <!-- Home section with welcome message and navigation cards -->
                    <div class="content-box">
                        <h2>Welcome Mr.<?php echo htmlspecialchars($head_teacher_name ?? 'Head Teacher'); ?>!</h2>
                        <p>School: <?php echo htmlspecialchars($school_name); ?></p>
                        <p>Use the sidebar or cards below to navigate:</p>
                        <div class="dashboard-cards">
                            <div class="dashboard-card"><a href="?section=staff_registration"><img src="assets/student_reg.png" alt="Staff Registration" class="card-image"><h3>Staff Registration</h3></a></div>
                            <div class="dashboard-card"><a href="?section=manage_teachers"><img src="assets/manage tr.png" alt="Manage Teachers" class="card-image"><h3>Manage Teachers</h3></a></div>
                            <div class="dashboard-card"><a href="?section=all_students"><img src="assets/view students.png" alt="All Students" class="card-image"><h3>All Students</h3></a></div>
                            <div class="dashboard-card"><a href="?section=student_transfers"><img src="assets/transfer_students.png" alt="Student Transfers" class="card-image"><h3>Student Transfers</h3></a></div>
                            <div class="dashboard-card"><a href="?section=performance_reports"><img src="assets/reports ht.png" alt="Performance Reports" class="card-image"><h3>Performance Reports</h3></a></div>
                        </div>
                    </div>
                    <?php
                } elseif ($section === 'staff_registration') {
                    // Include staff registration form from external file
                    include 'register_staff.php';
                    echo '<a href="?section=home" class="back-btn">Back to Home</a>';
                } elseif ($section === 'manage_teachers') {
                    // Fetch all teachers for the current school
                    // Variable: $teachers - Array, stores teacher records for the school
                    $teachers = $conn->query("SELECT * FROM staffs WHERE school_id = $school_id AND role = 'Teacher'")->fetch_all(MYSQLI_ASSOC);
                    ?>
                    <!-- Manage Teachers section for viewing and editing teacher information -->
                    <div class="content-box">
                        <h2>Manage Teachers</h2>
                        <?php
                        // Check if there are teachers to display
                        if (count($teachers) > 0) {
                            ?>
                            <div class="table-responsive">
                                <table>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Grade</th>
                                        <th>Actions</th>
                                    </tr>
                                    <?php
                                    // Loop through teachers array to display each teacher
                                    foreach ($teachers as $teacher) {
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($teacher['staff_id']); ?></td>
                                            <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                            <td><?php echo htmlspecialchars($teacher['assigned_grade'] ?? 'N/A'); ?></td>
                                            <td>
                                                <a href="view_teacher.php?id=<?php echo $teacher['staff_id']; ?>" class="action-btn view-btn">View</a>
                                                <a href="edit_teacher.php?id=<?php echo $teacher['staff_id']; ?>" class="action-btn edit-btn">Edit</a>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                </table>
                            </div>
                            <?php
                        } else {
                            echo '<p>No teachers found.</p>';
                        }
                        ?>
                        <a href="?section=home" class="back-btn">Back to Home</a>
                    </div>
                    <?php
                } elseif ($section === 'all_students') {
                    // Fetch all students for the current school
                    // Variable: $students - Array, stores student records for the school
                    $students = $conn->query("SELECT * FROM students WHERE school_id = $school_id")->fetch_all(MYSQLI_ASSOC);
                    ?>
                    <!-- All Students section for viewing student information -->
                    <div class="content-box">
                        <h2>All Students</h2>
                        <?php
                        // Check if there are students to display
                        if (count($students) > 0) {
                            ?>
                            <div class="table-responsive">
                                <table>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Grade</th>
                                        <th>Action</th>
                                    </tr>
                                    <?php
                                    // Loop through students array to display each student
                                    foreach ($students as $student) {
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['current_grade']); ?></td>
                                            <td><a href="view_student.php?id=<?php echo $student['student_id']; ?>" class="action-btn view-btn">View</a></td>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                </table>
                            </div>
                            <?php
                        } else {
                            echo '<p>No students found.</p>';
                        }
                        ?>
                        <a href="?section=home" class="back-btn">Back to Home</a>
                    </div>
                    <?php
                } elseif ($section === 'student_transfers') {
                    // Fetch students and other schools for transfer functionality
                    // Variable: $students - Array, stores student records for transfer selection
                    $students = $conn->query("SELECT student_id, name FROM students WHERE school_id = $school_id")->fetch_all(MYSQLI_ASSOC);
                    // Variable: $other_schools - Array, stores other schools in the same county
                    $other_schools = $conn->query("SELECT school_id, school_name FROM schools WHERE county = '$county' AND school_id != $school_id")->fetch_all(MYSQLI_ASSOC);
                    ?>
                    <!-- Student Transfers section for requesting student transfers -->
                    <div class="content-box">
                        <h2>Request Student Transfer</h2>
                        <?php
                        // Display transfer message if available
                        if (isset($transfer_message)) echo "<p>$transfer_message</p>";
                        ?>
                        <!-- Transfer request form -->
                        <form method="post" class="transfer-form">
                            <!-- Student selection dropdown -->
                            <div class="form-group">
                                <label for="student_id">Select Student:</label>
                                <select name="student_id" id="student_id" required>
                                    <option value="">-- Select Student --</option>
                                    <?php
                                    // Loop through students to create dropdown options
                                    foreach ($students as $student) {
                                        echo '<option value="' . $student['student_id'] . '">' . htmlspecialchars($student['name']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <!-- Destination school selection dropdown -->
                            <div class="form-group">
                                <label for="to_school_id">Transfer To:</label>
                                <select name="to_school_id" id="to_school_id" required>
                                    <option value="">-- Select School --</option>
                                    <?php
                                    // Loop through other schools to create dropdown options
                                    foreach ($other_schools as $school) {
                                        echo '<option value="' . $school['school_id'] . '">' . htmlspecialchars($school['school_name']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <!-- Submit button for transfer request -->
                            <input type="submit" name="request_transfer" value="Submit Request" class="submit-btn">
                        </form>
                        <a href="?section=home" class="back-btn">Back to Home</a>
                    </div>
                    <?php
                } elseif ($section === 'performance_reports') {
                    ?>
                    <!-- Performance Reports section for viewing academic performance data -->
                    <div class="content-box">
                        <h2>Performance Reports</h2>
                        
                        <!-- Filter form for performance data -->
                        <div class="filters-container">
                            <form method="get" class="filter-form">
                                <!-- Hidden input to maintain current section -->
                                <input type="hidden" name="section" value="performance_reports">
                                
                                <!-- Grade filter dropdown -->
                                <div class="form-group">
                                    <label for="grade">Grade:</label>
                                    <select name="grade" id="grade">
                                        <option value="">All Grades</option>
                                        <?php
                                        // Loop through grades 4-9 to create dropdown options
                                        for ($i = 4; $i <= 9; $i++) {
                                            // Mark option as selected if it matches current GET parameter
                                            $selected = (isset($_GET['grade']) && $_GET['grade'] == $i) ? 'selected' : '';
                                            echo "<option value=\"Grade $i\" $selected>Grade $i</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <!-- Year filter dropdown -->
                                <div class="form-group">
                                    <label for="year">Year:</label>
                                    <select name="year" id="year">
                                        <option value="">All Years</option>
                                        <?php
                                        // Variable: $current_year - Integer, current year for filter options
                                        $current_year = date('Y');
                                        // Loop through current year and previous 2 years
                                        for ($i = $current_year; $i >= $current_year - 2; $i--) {
                                            // Mark option as selected if it matches current GET parameter
                                            $selected = (isset($_GET['year']) && $_GET['year'] == $i) ? 'selected' : '';
                                            echo "<option value=\"$i\" $selected>$i</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <!-- Term filter dropdown -->
                                <div class="form-group">
                                    <label for="term">Term:</label>
                                    <select name="term" id="term">
                                        <option value="">All Terms</option>
                                        <?php
                                        // Variable: $terms - Array, available academic terms
                                        $terms = ['Term 1', 'Term 2', 'Term 3'];
                                        // Loop through terms to create dropdown options
                                        foreach ($terms as $term) {
                                            // Mark option as selected if it matches current GET parameter
                                            $selected = (isset($_GET['term']) && $_GET['term'] == $term) ? 'selected' : '';
                                            echo "<option value=\"$term\" $selected>$term</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <!-- Subject filter dropdown -->
                                <div class="form-group">
                                    <label for="subject">Subject:</label>
                                    <select name="subject" id="subject">
                                        <option value="">All Subjects</option>
                                        <?php
                                        // Loop through subjects array to create dropdown options
                                        foreach ($subjects as $key => $subj) {
                                            // Mark option as selected if it matches current GET parameter
                                            $selected = (isset($_GET['subject']) && $_GET['subject'] == $key) ? 'selected' : '';
                                            echo "<option value=\"$key\" $selected>{$subj['name']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <!-- Submit button to apply filters -->
                                <button type="submit" class="filter-btn">Apply Filters</button>
                            </form>
                        </div>
                        
                        <?php
                        // Build SQL query based on selected filters
                        // Variable: $where_clauses - Array, stores WHERE conditions for SQL query
                        $where_clauses = ["s.school_id = $school_id"];
                        // Variable: $params - Array, stores parameter values for prepared statement
                        $params = [];
                        // Variable: $types - String, stores parameter types for bind_param
                        $types = "";
                        
                        // Add grade filter to WHERE clause if selected
                        if (!empty($_GET['grade'])) {
                            $where_clauses[] = "s.current_grade = ?";
                            $params[] = $_GET['grade'];
                            $types .= "s";
                        }
                        
                        // Add year filter to WHERE clause if selected
                        if (!empty($_GET['year'])) {
                            $where_clauses[] = "p.year = ?";
                            $params[] = $_GET['year'];
                            $types .= "i";
                        }
                        
                        // Add term filter to WHERE clause if selected
                        if (!empty($_GET['term'])) {
                            $where_clauses[] = "p.term = ?";
                            $params[] = $_GET['term'];
                            $types .= "s";
                        }
                        
                        // Build SELECT fields based on subject filter
                        // Variable: $select_fields - Array, stores SELECT field names
                        $select_fields = [];
                        if (!empty($_GET['subject'])) {
                            // If specific subject selected, show only that subject's average
                            $subject_key = $_GET['subject'];
                            $select_fields[] = "AVG({$subject_key}_score) as subject_score";
                            $select_fields[] = "'{$subjects[$subject_key]['name']}' as subject_name";
                        } else {
                            // If no specific subject, show all subjects' averages
                            foreach ($subjects as $key => $subj) {
                                $select_fields[] = "AVG({$key}_score) as {$key}_score";
                            }
                        }
                        
                        // Construct final SQL query
                        // Variable: $sql - String, complete SQL query with filters
                        $sql = "SELECT s.current_grade, COUNT(DISTINCT s.student_id) as student_count, " . implode(", ", $select_fields) . "
                                FROM students s
                                LEFT JOIN performance p ON s.student_id = p.student_id
                                WHERE " . implode(" AND ", $where_clauses) . "
                                GROUP BY s.current_grade
                                ORDER BY s.current_grade";
                        
                        // Execute prepared statement with dynamic parameters
                        $stmt = $conn->prepare($sql);
                        if (!empty($types)) {
                            // Method: bind_param - Binds parameters if any filters are selected
                            $stmt->bind_param($types, ...$params);
                        }
                        // Method: execute - Executes the query
                        $stmt->execute();
                        // Variable: $results - Array, stores performance data grouped by grade
                        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        
                        // Display results if data is available
                        if (count($results) > 0) {
                            ?>
                            <div class="table-responsive">
                                <table class="performance-table">
                                    <thead>
                                        <tr>
                                            <th>Grade</th>
                                            <th>Students</th>
                                            <?php
                                            // Display subject headers based on filter
                                            if (!empty($_GET['subject'])) {
                                                echo '<th>' . htmlspecialchars($subjects[$_GET['subject']]['name']) . ' Average</th>';
                                            } else {
                                                // Display all subject headers
                                                foreach ($subjects as $subj) {
                                                    echo '<th>' . htmlspecialchars($subj['name']) . ' Average</th>';
                                                }
                                            }
                                            ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Loop through results to display performance data
                                        foreach ($results as $row) {
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['current_grade']); ?></td>
                                                <td><?php echo htmlspecialchars($row['student_count']); ?></td>
                                                <?php
                                                // Display subject scores based on filter
                                                if (!empty($_GET['subject'])) {
                                                    echo '<td>' . number_format($row['subject_score'] ?? 0, 1) . '%</td>';
                                                } else {
                                                    // Display all subject scores
                                                    foreach ($subjects as $key => $subj) {
                                                        $score_key = "{$key}_score";
                                                        echo '<td>' . number_format($row[$score_key] ?? 0, 1) . '%</td>';
                                                    }
                                                }
                                                ?>
                                            </tr>
                                            <?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php
                        } else {
                            echo '<p>No performance data available for the selected filters.</p>';
                        }
                        ?>
                        
                        <a href="?section=home" class="back-btn">Back to Home</a>
                    </div>
                    <?php
                } elseif ($section === 'profile') {
                    ?>
                    <!-- Profile section displaying head teacher information -->
                    <div class="content-box">
                        <h2>Head Teacher Profile</h2>
                        <div class="profile-details">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($head_teacher['username']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($head_teacher['email']); ?></p>
                            <p><strong>Mobile Number:</strong> <?php echo htmlspecialchars($head_teacher['mobile_number']); ?></p>
                            <p><strong>School:</strong> <?php echo htmlspecialchars($school_name); ?></p>
                        </div>
                        <a href="?section=home" class="back-btn">Back to Home</a>
                    </div>
                    <?php
                }
                ?>
            </main>
        </div>
    </div>
    <!-- JavaScript for user menu toggle functionality -->
    <script>
        
        // DOM Element References - Get references to HTML elements for manipulation
        // Variable: userMenuBtn - DOM element, the button that toggles the dropdown menu
        const userMenuBtn = document.getElementById('userMenuBtn');
        // Variable: userMenu - DOM element, the dropdown menu container
        const userMenu = document.getElementById('userMenu');
        
        /**
         * Event Listener: Toggle Dropdown Menu
         * Purpose: Shows/hides the user menu when the button is clicked
         * Event: 'click' - Triggered when user clicks the menu button
         * Method: classList.toggle() - Adds 'hidden' class if not present, removes if present
         */
        userMenuBtn.addEventListener('click', () => {
            // Toggle the 'hidden' CSS class to show/hide the dropdown
            // If 'hidden' class exists, it gets removed (menu shows)
            // If 'hidden' class doesn't exist, it gets added (menu hides)
            userMenu.classList.toggle('hidden');
        });
        
        /**
         * Event Listener: Hide Dropdown When Clicking Outside
         * Purpose: Closes the dropdown menu when user clicks anywhere else on the page
         * Event: 'click' - Triggered when user clicks anywhere on the document
         * Logic: Checks if click target is outside both the button and dropdown
         */
        document.addEventListener('click', (event) => {
            // Check if the clicked element is NOT the menu button AND NOT inside the dropdown
            // userMenuBtn.contains(event.target) - Returns true if click was on/inside the button
            // userMenu.contains(event.target) - Returns true if click was on/inside the dropdown
            // Logical AND (&&) - Both conditions must be false to close the menu
            if (!userMenuBtn.contains(event.target) && !userMenu.contains(event.target)) {
                // Add 'hidden' class to close the dropdown menu
                // classList.add() - Adds CSS class to element, making it hidden via CSS
                userMenu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>