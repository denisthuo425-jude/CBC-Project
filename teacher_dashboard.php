<?php
// Start session to manage user authentication
session_start();
// Include database connection for MySQLi queries
require_once 'db_connect.php';
// Include subjects array for subjects report
require_once 'subjects.php';

// Check if user is logged in and has Teacher role
// isset() -  function that verifies if the session variables exists and is not null.
// $_SESSION is a PHP superglobal array that stores user data across multiple pages
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Teacher') {
    // Redirect to login page if not authenticated
    header('Location: login.php');
    exit();
}

// Fetch teacher details from users and staffs tables
// Using $conn->prepare() to create a secure SQL query that prevents SQL injection
$teacher_stmt = $conn->prepare("SELECT u.username, s.email, s.mobile_number, s.role, s.qualification, s.assigned_grade, s.school_id 
                               FROM users u JOIN staffs s ON u.user_id = s.user_id 
                               WHERE u.user_id = ?");
// Method: bind_param - Binds user_id to query ('i' for integer)
$teacher_stmt->bind_param("i", $_SESSION['user_id']);
// Method: execute - Executes the query
$teacher_stmt->execute();
// Variable: $teacher_details - Associative array, stores teacher data
$teacher_details = $teacher_stmt->get_result()->fetch_assoc();
// Method: close - Closes the prepared statement
$teacher_stmt->close();

// Fetch students for "My Class" and "Student Transition"
// Variable: $students declaration initializing an empty array to store student records.
$students = [];
// Variable: $search - String, search term from GET request (trimmed)
// $_GET is a PHP superglobal array used to retrieve data sent through HTTP GET method.
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
// Variable: $sql - String, base SQL query to fetch students
$sql = "SELECT * FROM students WHERE school_id = ? AND current_grade = CONCAT('Grade ', ?)";
if ($search) {
    // Adds a partial name search using SQL LIKE operator with % wildcards (e.g., 'john' will match 'Johnny', 'Dejohn', etc.)
    $sql .= " AND name LIKE ?";
}
// Object: $stmt - MySQLi prepared statement for secure query
$stmt = $conn->prepare($sql);
// Variable: $search_param - String, search term  (e.g., '%John%')
$search_param = "%$search%";
// Bind parameters based on search presence
if ($search) {
    // Method: bind_param - Binds school_id (integer), assigned_grade (string), search_param (string)
    $stmt->bind_param("iss", $teacher_details['school_id'], $teacher_details['assigned_grade'], $search_param);
} else {
    // Method: bind_param - Binds school_id (integer), assigned_grade (string)
    $stmt->bind_param("is", $teacher_details['school_id'], $teacher_details['assigned_grade']);
}
// Method: execute - Executes the query
$stmt->execute();
// Method: get_result - Retrieves query results
$result = $stmt->get_result();
// Loop through results and store in $students
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
// Method: close - Closes the prepared statement
$stmt->close();

// Runs a SQL query to retrieve a list of students (IDs and names) 
// who belong to the same school and grade as the currently logged-in teacher
// Then, fetches all rows and converts them into an associative array
// Variable: $student_list (Variable) - Array, stores student IDs and names for dropdown
$student_list = $conn->query("SELECT student_id, name FROM students WHERE school_id = " . $teacher_details['school_id'] . 
                            " AND current_grade = 'Grade " . $teacher_details['assigned_grade'] . "'")->fetch_all(MYSQLI_ASSOC);


// Function: filter_form - Creates a standardized filter form for viewing student reports
// Parameters:
//   - $student_list: Array of student records (ID and name) for dropdown
//   - $current_section: String indicating which section is using the form (e.g., 'reports')
// Purpose: Generates a form with student selection, year, and term filters
function filter_form($student_list, $current_section) {
    // Create form with GET method to allow bookmarking and sharing of filtered views
    echo '<form method="GET" class="filter-form">';
    // Hidden input to maintain current section when form is submitted
    echo '<input type="hidden" name="section" value="' . htmlspecialchars($current_section) . '">';
    
    // Student dropdown - Allows selection of specific student
    echo '<div class="form-group">';
    echo '<label for="student_id">Student:</label>';
    echo '<select name="student_id" id="student_id">';
    // Default option for student selection
    echo '<option value="">-- Select Student --</option>';
    // Loop through student list to create dropdown options
    foreach ($student_list as $student) {
        // Mark option as selected if it matches current GET parameter
        $selected = (isset($_GET['student_id']) && $_GET['student_id'] == $student['student_id']) ? 'selected' : '';
        echo "<option value='" . htmlspecialchars($student['student_id']) . "' $selected>";
        echo htmlspecialchars($student['name']) . '</option>';
    }
    echo '</select></div>';

    // Year dropdown - Allows selection of academic year
    echo '<div class="form-group">';
    echo '<label for="year">Year:</label>';
    echo '<select name="year" id="year">
            <option value="">-- Select Year --</option>
            <option value="2025" ' . (isset($_GET['year']) && $_GET['year'] == '2025' ? 'selected' : '') . '>2025</option>
            <option value="2024" ' . (isset($_GET['year']) && $_GET['year'] == '2024' ? 'selected' : '') . '>2024</option>
            <option value="2023" ' . (isset($_GET['year']) && $_GET['year'] == '2023' ? 'selected' : '') . '>2023</option>
            <option value="2022" ' . (isset($_GET['year']) && $_GET['year'] == '2022' ? 'selected' : '') . '>2022</option>
            <option value="2021" ' . (isset($_GET['year']) && $_GET['year'] == '2021' ? 'selected' : '') . '>2021</option>
            <option value="2020" ' . (isset($_GET['year']) && $_GET['year'] == '2020' ? 'selected' : '') . '>2020</option>
          </select>';
    echo '</div>';

    // Term dropdown - Allows selection of academic term
    echo '<div class="form-group">';
    echo '<label for="term">Term:</label>';
    echo '<select name="term" id="term">
            <option value="">-- Select Term --</option>
            <option value="Term 1" ' . (isset($_GET['term']) && $_GET['term'] == 'Term 1' ? 'selected' : '') . '>Term 1</option>
            <option value="Term 2" ' . (isset($_GET['term']) && $_GET['term'] == 'Term 2' ? 'selected' : '') . '>Term 2</option>
            <option value="Term 3" ' . (isset($_GET['term']) && $_GET['term'] == 'Term 3' ? 'selected' : '') . '>Term 3</option>
          </select>';
    echo '</div>';

    // Submit button to apply filters
    echo '<button type="submit" class="filter-btn">View Report</button>';
    echo '</form>';
}

function render_report_card($student_info, $report_data, $subjects) {
    // Purpose: Renders a  report card display
    // Parameters: $student_info - Array of student details, $report_data - Array of performance data, $subjects - Array of subjects
    echo '<div class="report-card">';
    
    // Header section
    echo '<div class="report-header">';
    echo '<h4>Student Report Card</h4>';
    echo '<div class="student-info">';
    echo '<p><strong>Name:</strong> ' . htmlspecialchars($student_info['student_name']) . '</p>';
    echo '<p><strong>Grade:</strong> ' . htmlspecialchars($student_info['current_grade']) . '</p>';
    echo '<p><strong>Term:</strong> ' . htmlspecialchars($report_data['term']) . ' <strong>Year:</strong> ' . htmlspecialchars($report_data['year']) . '</p>';
    echo '</div></div>';

    // Performance table
    echo '<table class="data-table report-table">';
    echo '<thead><tr><th>Subject</th><th>Score</th><th>Reflection</th><th>Comment</th></tr></thead>';
    echo '<tbody>';
    foreach ($subjects as $key => $subject) {
        $score = $report_data[$key . '_score'] ?? 'N/A';
        $reflection = $report_data[$key . '_reflection'] ?? 'N/A';
        $comment = $report_data[$key . '_comment'] ?? 'N/A';
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($subject['name']) . '</td>';
        echo '<td>' . htmlspecialchars($score) . '</td>';
        echo '<td>' . htmlspecialchars($reflection) . '</td>';
        echo '<td>' . htmlspecialchars($comment) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    // Summary section
    echo '<div class="summary-box">';
    echo '<div class="summary-item"><strong>Total Score:</strong> ' . htmlspecialchars($report_data['total_score'] ?? 'N/A') . '</div>';
    echo '<div class="summary-item"><strong>Average Score:</strong> ' . htmlspecialchars($report_data['average_score'] ?? 'N/A') . '%</div>';
    echo '<div class="summary-item"><strong>Assessed By:</strong> ' . htmlspecialchars($report_data['teacher_name'] ?? 'N/A') . '</div>';
    echo '</div>';

    // General comment if available
    if (!empty($report_data['general_comment'])) {
        echo '<div class="comment-section">';
        echo '<h5>General Comment</h5>';
        echo '<div class="comment-box">' . htmlspecialchars($report_data['general_comment']) . '</div>';
        echo '</div>';
    }
    
    echo '</div>'; // End report-card
}

function render_student_table($students) {
    // Purpose: Renders a  student table
    // Parameter: $students - Array of student records
    /* Check if there are any students to display
     */
    if (count($students) > 0) {
        /* Create the main table structure with a specific class for styling
         * Class 'data-table' links to CSS styles for consistent table appearance
         * Table will contain student information in a structured grid format
         */
        echo '<table class="data-table">';
        
        /* Create the table header row with column titles
         * Columns: Student ID, Name, Gender, Guardian info, and Action buttons
         * Each th element represents a column header
         */
        echo '<thead><tr><th>Student ID</th><th>Name</th><th>Gender</th><th>Guardian Name</th><th>Guardian Phone</th><th>Actions</th></tr></thead>';
        
        /* Start the table body section
         * tbody element contains the main data rows
         * Separates content from headers for better organization
         */
        echo '<tbody>';
        
        /* Iterate through each student in the array
         * foreach loop creates a new row for each student
         * $students array contains associative arrays with student data
         * Each student record is processed and displayed in sequence
         */
        foreach ($students as $student) {
            /* Start a new table row for each student
             * tr element contains individual student data
             * Each row represents one complete student record
             */
            echo '<tr>';
            
            /* Output each piece of student data in separate cells
             * htmlspecialchars() prevents XSS attacks by escaping special characters
             * Each td element contains one piece of student information
             * Student data is accessed from the associative array using keys
             */
            echo '<td>' . htmlspecialchars($student['student_id']) . '</td>';
            echo '<td>' . htmlspecialchars($student['name']) . '</td>';
            echo '<td>' . htmlspecialchars($student['gender']) . '</td>';
            echo '<td>' . htmlspecialchars($student['guardian_name']) . '</td>';
            echo '<td>' . htmlspecialchars($student['guardian_phone']) . '</td>';
            
            /* Create the actions cell with view and edit buttons
             * td element contains two action buttons
             */
            echo '<td>';
            echo '<a href="view_student.php?id=' . htmlspecialchars($student['student_id']) . '" class="action-btn view-btn">👁️</a>';
            echo '<a href="edit_student.php?id=' . htmlspecialchars($student['student_id']) . '" class="action-btn edit-btn">✏️</a>';
            echo '</td></tr>';
        }
        
        /* Close the table body and table elements
         */
        echo '</tbody></table>';
    } else {
        /* Display message when no students are found
         * Provides user feedback for empty results
         */
        echo '<p>No students found.</p>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta tags for character encoding and responsive viewport -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - CBC Performance Tracking</title>
    <!-- Link to styles.css for styling -->
    <link rel="stylesheet" href="assets/styles.css">
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
                <a href="?section=my_class" class="nav-item"><span class="nav-icon">📊</span> My Class</a>
                <a href="?section=student_registration" class="nav-item"><span class="nav-icon">📝</span> Student Registration</a>
                <a href="?section=assessments" class="nav-item"><span class="nav-icon">📋</span> Assessments</a>
                <a href="?section=reports" class="nav-item"><span class="nav-icon">📈</span> Reports</a>
                <a href="?section=student_transition" class="nav-item"><span class="nav-icon">🔄</span> Student Transition</a>
            </nav>
        </aside>
        <!-- Main content area -->
        <div class="main-content">
            <!-- Top header with title and user menu -->
            <header class="top-header">
                <h1>Teacher Dashboard</h1>
                <!-- User menu for profile and logout -->
                <div class="user-menu">
                    <!-- Button to toggle user menu -->
                    <button id="userMenuBtn" class="user-menu-btn">
                        <!-- Variable: $teacher_name - Teacher's username for display -->
                        <span><?php echo htmlspecialchars($teacher_details['username'] ?? 'Teacher'); ?></span>
                        <!-- Dynamic avatar using ui-avatars.com -->
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($teacher_details['username'] ?? 'Teacher'); ?>" alt="Teacher" class="user-avatar">
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
                    <!-- Home section with welcome message and cards -->
                    <div class="content-box">
                        <h2>Welcome Tr. <?php echo htmlspecialchars($teacher_details['username'] ?? 'Teacher'); ?>!</h2>
                        <p>Use the sidebar or cards below to navigate:</p>
                        <div class="dashboard-cards">
                            <div class="dashboard-card">
                                <a href="?section=my_class"><img src="assets/my_class.png" alt="My Class" class="card-image"></a>
                                <h3>My Class</h3>
                            </div>
                            <div class="dashboard-card">
                                <a href="?section=student_registration"><img src="assets/student_reg.png" alt="Student Registration" class="card-image"></a>
                                <h3>Student Registration</h3>
                            </div>
                            <div class="dashboard-card">
                                <a href="?section=assessments"><img src="assets/assessments.png" alt="Assessments" class="card-image"></a>
                                <h3>Assessments</h3>
                            </div>
                            <div class="dashboard-card">
                                <a href="?section=reports"><img src="assets/reports.png" alt="Reports" class="card-image"></a>
                                <h3>Reports</h3>
                            </div>
                            <div class="dashboard-card">
                                <a href="?section=student_transition"><img src="assets/transition.png" alt="Student Transition" class="card-image"></a>
                                <h3>Student Transition</h3>
                            </div>
                        </div>
                        <p class="quote">"Education is the key to unlocking the world." - Oprah Winfrey</p>
                    </div>
                    <?php
                } elseif ($section === 'student_registration') {
                    ?>
                    <!-- Student Registration section -->
                    <div class="content-box">
                        <h2>Student Registration</h2>
                        <!-- Make school_id available before including the form -->
                        <?php 
                        $school_id = $teacher_details['school_id'];
                        include 'student_registration.html'; 
                        ?>
                        <a href="?section=home" class="back-btn">Back to Home</a>
                    </div>
                    <?php
                } elseif ($section === 'assessments') {
                    ?>
                    <!-- Assessments section for entering performance -->
                    <div class="content-box">
                        <h2>Assessments</h2>
                        <!-- Success message for performance entry -->
                        <div id="success-msg" class="success" style="display:none;">Performance Recorded Successfully!</div>
                        <script>      
                            /**
                             * Function: window.onload
                             * Purpose: Displays success message if performance was recorded successfully
                             */
                            window.onload = function() {
                                // Variable: urlParams - URLSearchParams object, parses URL query string
                                // URLSearchParams - Built-in browser API for working with URL parameters
                                const urlParams = new URLSearchParams(window.location.search);
                                
                                // Check if status parameter exists and equals 'success'
                                // get() - Returns the value of the specified parameter, or null if not found
                                if (urlParams.get('status') === 'success') {
                                    /**
                                     * Success Message Display
                                     * Purpose: Show user feedback that performance was recorded successfully
                                     */
                                    // Get the success message element and make it visible
                                    // getElementById() - Returns the element with specified ID
                                    // style.display = 'block' - Makes element visible (overrides CSS 'none')
                                    document.getElementById('success-msg').style.display = 'block';
                                    
                                    /**
                                     * Auto-hide Success Message
                                     * Purpose: Automatically hide success message after 3 seconds
                                     * setTimeout() - Executes function after specified delay (3000ms = 3 seconds)
                                     */
                                    setTimeout(() => { 
                                        // Hide the success message by setting display to 'none'
                                        document.getElementById('success-msg').style.display = 'none'; 
                                    }, 3000);
                                }
                            };
                        </script>
                        <!-- Include performance entry form -->
                        <?php include 'performance_entry.html'; ?>
                        <a href="?section=home" class="back-btn">Back to Home</a>
                    </div>
                    <?php
                } elseif ($section === 'my_class') {
                    echo '<div class="content-box">';
                    echo '<h2>My Class</h2>';
                    
                    // Search form
                    echo '<form method="get" class="filter-form">';
                    echo '<input type="hidden" name="section" value="my_class">';
                    echo '<div class="form-group">';
                    echo '<label>Search Students:</label>';
                    echo '<input type="text" name="search" placeholder="Enter student name" value="' . htmlspecialchars($_GET['search'] ?? '') . '">';
                    echo '</div>';
                    echo '<input type="submit" value="Search" class="submit-btn" style="width: auto;">';
                    echo '</form>';

                    echo '<div class="grade-box">';
                    echo '<h3>Grade ' . htmlspecialchars($teacher_details['assigned_grade'] ?: 'Not Assigned') . '</h3>';
                    echo '</div>';

                    render_student_table($students);
                    
                    echo '<a href="?section=home" class="back-btn">Back to Home</a>';
                    echo '</div>';
                } elseif ($section === 'reports') {
                    echo '<div class="content-box">';
                    echo '<h2>Performance Reports</h2>';
                    
                    // Create filter form
                    filter_form($student_list, 'reports');

                    // Display report if filters are selected
                    if (isset($_GET['student_id']) && isset($_GET['year']) && isset($_GET['term'])) {
                        // Fetch student and performance data
                        $stmt = $conn->prepare("SELECT s.name as student_name, s.current_grade 
                                              FROM students s 
                                              WHERE s.student_id = ?");
                        $stmt->bind_param("i", $_GET['student_id']);
                        $stmt->execute();
                        $student_info = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        $stmt = $conn->prepare("SELECT p.*, u.username as teacher_name 
                                              FROM performance p 
                                              LEFT JOIN students s ON p.student_id = s.student_id
                                              LEFT JOIN staffs st ON TRIM(SUBSTRING(s.current_grade, 7)) = TRIM(st.assigned_grade) 
                                                AND s.school_id = st.school_id
                                              LEFT JOIN users u ON st.user_id = u.user_id
                                              WHERE p.student_id = ? AND p.year = ? AND p.term = ?");
                        $stmt->bind_param("iss", $_GET['student_id'], $_GET['year'], $_GET['term']);
                        $stmt->execute();
                        $report_data = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if ($report_data) {
                            // Export to PDF button above the report card
                            echo '<button class="download-btn" onclick="exportReportCardToPDF(this)">Export to PDF</button>';
                            render_report_card($student_info, $report_data, $subjects);
                        } else {
                            echo '<div class="no-data-message">';
                            echo '<p>No report card available for the selected student, term and year.</p>';
                            echo '</div>';
                        }
                    }
                    
                    echo '<a href="?section=home" class="back-btn">Back to Home</a>';
                    echo '</div>';
                } elseif ($section === 'student_transition') {
                    echo '<div class="content-box">';
                    echo '<h2>Student Transition</h2>';
                    
                    echo '<div class="transition-options">';
                    echo '<button id="bulkTransitionBtn" class="action-btn">Transition All Students</button>';
                    echo '<p>Or select individual students below:</p>';
                    echo '</div>';

                    if (count($students) > 0) {
                        echo '<table class="data-table">';
                        echo '<thead><tr>';
                        echo '<th><input type="checkbox" id="selectAll" title="Select/Deselect All"></th>';
                        echo '<th>Student ID</th><th>Name</th><th>Current Grade</th><th>Actions</th>';
                        echo '</tr></thead><tbody>';
                        
                        foreach ($students as $student) {
                            echo '<tr>';
                            echo '<td><input type="checkbox" class="student-checkbox" value="' . htmlspecialchars($student['student_id']) . '"></td>';
                            echo '<td>' . htmlspecialchars($student['student_id']) . '</td>';
                            echo '<td>' . htmlspecialchars($student['name']) . '</td>';
                            echo '<td>' . htmlspecialchars($student['current_grade']) . '</td>';
                            echo '<td><button onclick="transitionStudent(' . htmlspecialchars($student['student_id']) . ')" class="action-btn transition-btn">Transition</button></td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                    } else {
                        echo '<p>No students available for transition.</p>';
                    }
                    
                    echo '<a href="?section=home" class="back-btn">Back to Home</a>';
                    echo '</div>';
                } elseif ($section === 'profile') {
                    echo '<div class="content-box">';
                    echo '<h2>Teacher Profile</h2>';
                    echo '<div class="profile-details">';
                    foreach (['username' => 'Name', 'email' => 'Email', 'mobile_number' => 'Mobile Number', 
                              'role' => 'Role', 'qualification' => 'Qualification', 'assigned_grade' => 'Assigned Grade'] as $key => $label) {
                        $value = $teacher_details[$key] ?? 'Not Assigned';
                        echo "<p><strong>$label:</strong> " . htmlspecialchars($value) . '</p>';
                    }
                    echo '</div>';
                    echo '<a href="?section=home" class="back-btn">Back to Home</a>';
                    echo '</div>';
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
            if (!userMenuBtn.contains(event.target) && !userMenu.contains(event.target)) {
                // Add 'hidden' class to close the dropdown menu
                userMenu.classList.add('hidden');
            }
        });
        /**
         * Event Listener: Select All Checkbox
         * Purpose: Selects/deselects all student checkboxes when "Select All" is clicked
         * Event: 'change' - Triggered when checkbox state changes
         */
        document.getElementById('selectAll')?.addEventListener('change', function() {
            // Get all student checkboxes on the page
            // querySelectorAll('.student-checkbox') - Returns NodeList of all elements with class 'student-checkbox'
            const studentCheckboxes = document.querySelectorAll('.student-checkbox');
            
            // Loop through each student checkbox and set its checked state
            // forEach() - Executes a function for each element in the NodeList
            studentCheckboxes.forEach(checkbox => {
                // Set each checkbox to match the "Select All" checkbox state
                // this.checked - Refers to the "Select All" checkbox's checked state
                checkbox.checked = this.checked;
            });
        });

        /**
         * Function: Transition Individual Student
         * Purpose: Handles transition of a single student to the next grade
         * @param {number} studentId - The unique identifier of the student to transition
         * Parameters: studentId - Integer, the student's unique ID from the database
         */
        function transitionStudent(studentId) {
            // Show confirmation dialog before proceeding
            // confirm() - Built-in browser function that shows OK/Cancel dialog
            // Returns true if user clicks OK, false if user clicks Cancel
            if (confirm('Are you sure you want to transition this student to the next grade?')) {
                // Redirect to transition processing page with student ID
                // window.location.href - Changes the current page URL
                window.location.href = `transition_students.php?student_id=${studentId}`;
            }
        }

        /**
         * Event Listener: Bulk Transition Button
         * Purpose: Handles transition of multiple selected students at once
         * Event: 'click' - Triggered when bulk transition button is clicked
         */
        document.getElementById('bulkTransitionBtn')?.addEventListener('click', function() {
            // Get all checked student checkboxes
            // querySelectorAll('.student-checkbox:checked') - Returns only checked checkboxes
            // Array.from() - Converts NodeList to Array for easier manipulation
            const selectedStudents = Array.from(document.querySelectorAll('.student-checkbox:checked'))
                // map() - Creates new array by transforming each checkbox to its value
                .map(checkbox => checkbox.value);
            
            // Check if any students are selected
            if (selectedStudents.length === 0) {
                // Show error message if no students are selected
                alert('Please select at least one student to transition.');
                return; // Exit function 
            }
            
            // Show confirmation dialog with number of selected students
            if (confirm(`Are you sure you want to transition ${selectedStudents.length} student(s) to the next grade?`)) {
                // Redirect to transition processing page with comma-separated student IDs
                // join(',') - Converts array to string with commas between elements
                window.location.href = `transition_students.php?student_ids=${selectedStudents.join(',')}`;
            }
        });
    </script>
    
    <!-- JavaScript for PDF export functionality -->
    <script>
    /**
     * Export only the report card section to PDF using the browser's print dialog
     * This function hides everything except the report card, prints, then restores the page.
     * @param {HTMLElement} btn - The button that was clicked
     */
    function exportReportCardToPDF(btn) {
        // Find the report card div immediately after the button
        var reportCard = btn.nextElementSibling;
        if (!reportCard || !reportCard.classList.contains('report-card')) return;
        // Save the original page HTML
        var originalBody = document.body.innerHTML;
        // Replace the body with only the report card
        document.body.innerHTML = reportCard.outerHTML;
        // Print (user can choose 'Save as PDF')
        window.print();
        // Restore the original page
        document.body.innerHTML = originalBody;
        // Optionally, reload scripts/styles if needed (not required for simple use)
    }
    </script>

</body>
</html>