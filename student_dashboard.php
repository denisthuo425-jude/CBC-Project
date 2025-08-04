<?php
// Start session to manage user authentication
session_start();
// Include database connection for MySQLi queries
require_once 'db_connect.php';
// Include subjects array for report card subjects
require_once 'subjects.php';

// Check if user is logged in and has Student role
// isset() - function that verifies if the session variables exists and is not null.
// $_SESSION is a PHP superglobal array that stores user data across multiple pages
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Student') {
    // Redirect to login page if not authenticated
    header('Location: login.php');
    exit();
}

// Fetch student details from students and schools tables
// Using $conn->prepare() to create a secure SQL query that prevents SQL injection
$stmt = $conn->prepare("SELECT s.student_id, s.name, s.current_grade, sc.school_name 
                        FROM students s 
                        JOIN schools sc ON s.school_id = sc.school_id 
                        WHERE s.user_id = ?");
// Method: bind_param - Binds user_id to query ('i' for integer)
$stmt->bind_param("i", $_SESSION['user_id']);
// Method: execute - Executes the query
$stmt->execute();
// Variable: $student - Associative array, stores student data
$student = $stmt->get_result()->fetch_assoc();
// Check if student record exists
if (!$student) {
    // Redirect to login with error message if student record not found
    header('Location: login.php?error=' . urlencode("Student record not found."));
    exit();
}
// Variable: $student_id - Integer, student's unique identifier
$student_id = $student['student_id'];
// Variable: $student_name - String, student's full name
$student_name = $student['name'];
// Variable: $school_name - String, name of student's school
$school_name = $student['school_name'];
// Variable: $school_id - Integer, school's unique identifier
$school_id = $conn->query("SELECT school_id FROM students WHERE student_id = $student_id")->fetch_assoc()['school_id'];
// Variable: $current_grade - String, student's current grade level
$current_grade = $student['current_grade'];
// Method: close - Closes the prepared statement
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta tags for character encoding and responsive viewport -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - CBC Performance Tracking</title>
    <!-- Link to student-specific styles for styling -->
    <link rel="stylesheet" href="assets/styles_student.css">
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
                <a href="?section=report_card" class="nav-item"><span class="nav-icon">📋</span> Report Card</a>
            </nav>
        </aside>
        <!-- Main content area -->
        <div class="main-content">
            <!-- Top header with title and user menu -->
            <header class="top-header">
                <h1>Student Dashboard</h1>
                <!-- User menu for profile and logout -->
                <div class="user-menu">
                    <!-- Button to toggle user menu -->
                    <button id="userMenuBtn" class="user-menu-btn">
                        <!-- Variable: $student_name - Student's name for display -->
                        <span><?php echo htmlspecialchars($student_name); ?></span>
                        <!-- Dynamic avatar using ui-avatars.com -->
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($student_name); ?>" alt="Student" class="user-avatar">
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
                        <h2>Welcome, <?php echo htmlspecialchars($student_name); ?>!</h2>
                        <p>School: <?php echo htmlspecialchars($school_name); ?></p>
                        <p>View your academic performance in the Report Card section below.</p>
                        <div class="dashboard-cards">
                            <div class="dashboard-card"><a href="?section=report_card"><img src="assets/report_card.png" alt="Report Card" class="card-image"><h3>Report Card</h3></a></div>
                        </div>
                    </div>
                    <?php
                } elseif ($section === 'report_card') {
                    ?>
                    <!-- Report Card section for viewing academic performance -->
                    <div class="content-box">
                        <h2>Performance Reports</h2>
                        
                        <!-- Filter form for selecting year and term -->
                        <form method="GET" class="filter-form">
                            <!-- Hidden input to maintain current section -->
                            <input type="hidden" name="section" value="report_card">
                            <!-- Year selection dropdown -->
                            <div class="form-group">
                                <label for="year">Year:</label>
                                <select name="year" id="year">
                                    <option value="">-- Select Year --</option>
                                    <?php
                                    // Loop through years 2020-2025 to create dropdown options
                                    for ($year = 2020; $year <= 2025; $year++) {
                                        // Mark option as selected if it matches current GET parameter
                                        $selected = (isset($_GET['year']) && $_GET['year'] == $year) ? 'selected' : '';
                                        echo "<option value='$year' $selected>$year</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <!-- Term selection dropdown -->
                            <div class="form-group">
                                <label for="term">Term:</label>
                                <select name="term" id="term">
                                    <option value="">-- Select Term --</option>
                                    <?php
                                    // Loop through terms 1-3 to create dropdown options
                                    for ($term = 1; $term <= 3; $term++) {
                                        $term_value = "Term $term";
                                        // Mark option as selected if it matches current GET parameter
                                        $selected = (isset($_GET['term']) && $_GET['term'] == $term_value) ? 'selected' : '';
                                        echo "<option value='$term_value' $selected>$term_value</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <!-- Submit button to apply filters -->
                            <button type="submit" class="filter-btn">View Report</button>
                        </form>

                        <?php
                        // Display report card if year and term are selected
                        if (isset($_GET['year']) && isset($_GET['term'])) {
                            // Variable: $selected_year - String, selected academic year
                            $selected_year = $_GET['year'];
                            // Variable: $selected_term - String, selected academic term
                            $selected_term = $_GET['term'];
                            
                            // Fetch performance data for selected term and year
                            // Using $conn->prepare() to create a secure SQL query that prevents SQL injection
                            $stmt = $conn->prepare("SELECT p.*, u.username as teacher_name 
                                                  FROM performance p 
                                                  LEFT JOIN students s ON p.student_id = s.student_id
                                                  LEFT JOIN staffs st ON TRIM(SUBSTRING(s.current_grade, 7)) = TRIM(st.assigned_grade) 
                                                    AND s.school_id = st.school_id
                                                  LEFT JOIN users u ON st.user_id = u.user_id
                                                  WHERE p.student_id = ? AND p.year = ? AND p.term = ?");
                            // Method: bind_param - Binds student_id (integer), year (string), term (string)
                            $stmt->bind_param("iss", $student_id, $selected_year, $selected_term);
                            // Method: execute - Executes the query
                            $stmt->execute();
                            // Variable: $report_data - Associative array, stores performance data
                            $report_data = $stmt->get_result()->fetch_assoc();
                            // Method: close - Closes the prepared statement
                            $stmt->close();

                            if ($report_data) {
                                ?>
                                <!-- Export to PDF button above the report card -->
                                <button class="export-pdf-btn" onclick="exportReportCardToPDF(this)">Export to PDF</button>
                                <!-- Report card display when data is available -->
                                <div class="report-card">
                                    <!-- Header section with student information -->
                                    <div class="report-header">
                                        <h4>Student Report Card</h4>
                                        <div class="student-info">
                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($student_name); ?></p>
                                            <p><strong>Grade:</strong> <?php echo htmlspecialchars($current_grade); ?></p>
                                            <p><strong>Term:</strong> <?php echo htmlspecialchars($selected_term); ?> <strong>Year:</strong> <?php echo htmlspecialchars($selected_year); ?></p>
                                        </div>
                                    </div>

                                    <!-- Academic Performance section with subject scores -->
                                    <div class="academic-performance">
                                        <h5>Academic Performance</h5>
                                        <table class="data-table report-table">
                                            <thead><tr><th>Subject</th><th>Score</th><th>Reflection</th><th>Comment</th></tr></thead>
                                            <tbody>
                                                <?php
                                                // Loop through subjects array to display performance data
                                                foreach ($subjects as $key => $subject) {
                                                    // Variable: $score - String, subject score or 'N/A'
                                                    $score = $report_data[$key . '_score'] ?? 'N/A';
                                                    // Variable: $reflection - String, subject reflection or 'N/A'
                                                    $reflection = $report_data[$key . '_reflection'] ?? 'N/A';
                                                    // Variable: $comment - String, subject comment or 'N/A'
                                                    $comment = $report_data[$key . '_comment'] ?? 'N/A';
                                                    
                                                    echo '<tr>';
                                                    echo '<td>' . htmlspecialchars($subject['name']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($score) . '</td>';
                                                    echo '<td>' . htmlspecialchars($reflection) . '</td>';
                                                    echo '<td>' . htmlspecialchars($comment) . '</td>';
                                                    echo '</tr>';
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Summary section with overall performance metrics -->
                                    <div class="summary-box">
                                        <div class="summary-item"><strong>Total Score:</strong> <?php echo htmlspecialchars($report_data['total_score'] ?? 'N/A'); ?></div>
                                        <div class="summary-item"><strong>Average Score:</strong> <?php echo htmlspecialchars($report_data['average_score'] ?? 'N/A'); ?>%</div>
                                        <div class="summary-item"><strong>Assessed By:</strong> <?php echo htmlspecialchars($report_data['teacher_name'] ?? 'N/A'); ?></div>
                                    </div>

                                    <?php
                                    // General Comment section - only display if comment exists
                                    if (!empty($report_data['general_comment'])) {
                                        ?>
                                        <div class="comment-section">
                                            <h5>General Comment</h5>
                                            <div class="comment-box"><?php echo htmlspecialchars($report_data['general_comment']); ?></div>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div> <!-- End report-card -->
                                <?php
                            } else {
                                ?>
                                <!-- Message displayed when no report data is available -->
                                <div class="no-data-message">
                                    <p>No report card available for the selected term and year.</p>
                                </div>
                                <?php
                            }
                        }
                        ?>
                        
                        <a href="?section=home" class="back-btn">Back to Home</a>
                    </div>
                    <?php
                } elseif ($section === 'profile') {
                    ?>
                    <!-- Profile section displaying student information -->
                    <div class="content-box">
                        <h2>Student Profile</h2>
                        <div class="profile-details">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($student_name); ?></p>
                            <p><strong>Grade:</strong> <?php echo htmlspecialchars($current_grade); ?></p>
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
        /**
         * JavaScript for User Menu Dropdown Functionality
         */
        
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
    <!-- At the end of the file, add the JavaScript for export -->
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