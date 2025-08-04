<?php
// Start session to manage user authentication
session_start();
// Include database connection for MySQLi queries
require_once 'db_connect.php';

// Check if user is logged in and has County Admin role
// isset() - function that verifies if the session variables exists and is not null.
// $_SESSION is a PHP superglobal array that stores user data across multiple pages
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'County Admin') {
    // Redirect to login page if not authenticated
    header('Location: login.php');
    exit();
}

// Variable: $username - String, admin username from session
$username = $_SESSION['username'] ?? '';
// Validate admin username format (must start with 'admin.')
if (empty($username) || strpos($username, 'admin.') !== 0) {
    // Destroy session and redirect with error if invalid format
    session_destroy();
    header('Location: login.php?error=' . urlencode('Invalid admin username format.'));
    exit();
}

// Variable: $county - String, extract county name from username (format: admin.county)
$county = ucfirst(explode('.', $username)[1] ?? '');
// Validate that county was successfully extracted
if (empty($county)) {
    // Destroy session and redirect with error if county cannot be determined
    session_destroy();
    header('Location: login.php?error=' . urlencode('Could not determine county from username.'));
    exit();
}

// Handle transfer approval/rejection from POST form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_action'])) {
    // Variable: $transfer_id - Integer, transfer request identifier
    $transfer_id = $_POST['transfer_id'];
    // Variable: $action - String, action type ('approve' or 'reject')
    $action = $_POST['transfer_action'];
    // Variable: $status - String, final status ('Approved' or 'Rejected')
    $status = $action === 'approve' ? 'Approved' : 'Rejected';
    // Variable: $approved_date - String, approval date in Y-m-d format (null if rejected)
    $approved_date = $status === 'Approved' ? date('Y-m-d') : null;

    // Update transfer status in student_transfers table
    // Using $conn->prepare() to create a secure SQL query that prevents SQL injection
    $stmt = $conn->prepare("UPDATE student_transfers SET status = ?, approved_date = ? WHERE transfer_id = ?");
    // Method: bind_param - Binds status (string), approved_date (string), transfer_id (integer)
    $stmt->bind_param("ssi", $status, $approved_date, $transfer_id);
    // Method: execute - Executes the query
    $stmt->execute();

    // If transfer is approved, update student's school_id
    if ($status === 'Approved') {
        // Update student's school to the destination school
        $stmt = $conn->prepare("UPDATE students s 
                                JOIN student_transfers st ON s.student_id = st.student_id 
                                SET s.school_id = st.to_school_id 
                                WHERE st.transfer_id = ?");
        // Method: bind_param - Binds transfer_id (integer)
        $stmt->bind_param("i", $transfer_id);
        // Method: execute - Executes the query
        $stmt->execute();
    }
    // Method: close - Closes the prepared statement
    $stmt->close();
}

// Fetch pending transfers for the county
// Variable: $transfers_sql - String, SQL query to get pending transfers
$transfers_sql = "SELECT st.transfer_id, st.student_id, s.name AS student_name, 
                  sf.school_name AS from_school, st.from_school_id, 
                  st.to_school_id, st.request_date, st.status
                  FROM student_transfers st
                  JOIN students s ON st.student_id = s.student_id
                  JOIN schools sf ON st.from_school_id = sf.school_id
                  JOIN schools stt ON st.to_school_id = stt.school_id
                  WHERE sf.county = ? AND st.status = 'Pending'";
// Using $conn->prepare() to create a secure SQL query that prevents SQL injection
$stmt = $conn->prepare($transfers_sql);
// Method: bind_param - Binds county (string)
$stmt->bind_param("s", $county);
// Method: execute - Executes the query
$stmt->execute();
// Variable: $transfers - Array, stores pending transfer requests
$transfers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
// Method: close - Closes the prepared statement
$stmt->close();

// Fetch county performance statistics
// Variable: $performance_sql - String, SQL query to get county performance data
$performance_sql = "SELECT AVG(p.average_score) AS avg_score, COUNT(DISTINCT p.student_id) AS student_count
                    FROM performance p
                    JOIN students s ON p.student_id = s.student_id
                    JOIN schools sch ON s.school_id = sch.school_id
                    WHERE sch.county = ?";
// Using $conn->prepare() to create a secure SQL query that prevents SQL injection
$stmt = $conn->prepare($performance_sql);
// Method: bind_param - Binds county (string)
$stmt->bind_param("s", $county);
// Method: execute - Executes the query
$stmt->execute();
// Variable: $county_performance - Associative array, stores county performance data
$county_performance = $stmt->get_result()->fetch_assoc();
// Method: close - Closes the prepared statement
$stmt->close();

// Fetch sub-counties for report filters
// Variable: $sub_counties_sql - String, SQL query to get unique sub-counties
$sub_counties_sql = "SELECT DISTINCT sub_county FROM schools WHERE county = ?";
// Using $conn->prepare() to create a secure SQL query that prevents SQL injection
$stmt = $conn->prepare($sub_counties_sql);
// Method: bind_param - Binds county (string)
$stmt->bind_param("s", $county);
// Method: execute - Executes the query
$stmt->execute();
// Variable: $sub_counties - Array, stores sub-county names
$sub_counties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
// Method: close - Closes the prepared statement
$stmt->close();

// Fetch sub-county performance data for charts
// Variable: $sub_county_perf_sql - String, SQL query to get sub-county performance
$sub_county_perf_sql = "SELECT sch.sub_county, AVG(p.average_score) as avg_score
                        FROM performance p
                        JOIN students s ON p.student_id = s.student_id
                        JOIN schools sch ON s.school_id = sch.school_id
                        WHERE sch.county = ? AND p.average_score IS NOT NULL
                        GROUP BY sch.sub_county
                        HAVING avg_score >= 0";
// Using $conn->prepare() to create a secure SQL query that prevents SQL injection
$stmt = $conn->prepare($sub_county_perf_sql);
// Method: bind_param - Binds county (string)
$stmt->bind_param("s", $county);
// Method: execute - Executes the query
$stmt->execute();
// Variable: $sub_county_perf - Array, stores sub-county performance data
$sub_county_perf = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
// Method: close - Closes the prepared statement
$stmt->close();

// Handle report generation from POST form
// Variable: $report_data - Array, stores generated report data
$report_data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    // Variable: $sub_county_filter - String, selected sub-county filter
    $sub_county_filter = $_POST['sub_county'] ?? '';
    // Variable: $term_filter - String, selected term filter
    $term_filter = $_POST['term'] ?? '';
    // Variable: $year_filter - String, selected year filter
    $year_filter = $_POST['year'] ?? '';
    
    // Build dynamic SQL query based on selected filters
    // Variable: $query - String, base SQL query for report generation
    $query = "SELECT s.name AS student_name, p.term, p.year, p.average_score, sch.school_name
              FROM performance p
              JOIN students s ON p.student_id = s.student_id
              JOIN schools sch ON s.school_id = sch.school_id
              WHERE sch.county = ?";
    // Variable: $params - Array, stores parameter values for prepared statement
    $params = [$county];
    // Variable: $types - String, stores parameter types for bind_param
    $types = "s";
    
    // Add sub-county filter if selected
    if ($sub_county_filter) {
        $query .= " AND sch.sub_county = ?";
        $params[] = $sub_county_filter;
        $types .= "s";
    }
    
    // Add term filter if selected
    if ($term_filter) {
        $query .= " AND p.term = ?";
        $params[] = $term_filter;
        $types .= "s";
    }
    
    // Add year filter if selected
    if ($year_filter) {
        $query .= " AND p.year = ?";
        $params[] = $year_filter;
        $types .= "i";
    }
    
    // Execute prepared statement with dynamic parameters
    $stmt = $conn->prepare($query);
    // Method: bind_param - Binds parameters based on selected filters
    $stmt->bind_param($types, ...$params);
    // Method: execute - Executes the query
    $stmt->execute();
    // Variable: $report_data - Array, stores filtered report results
    $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    // Method: close - Closes the prepared statement
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta tags for character encoding and responsive viewport -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>County Admin Dashboard - CBC Performance Tracking</title>
    <!-- Link to county admin-specific styles for styling -->
    <link rel="stylesheet" href="assets/styles_county_admin.css">
</head>
<body>
    <!-- Main container for dashboard layout (flexbox for sidebar and content) -->
    <div class="dashboard-container">
        <!-- Sidebar navigation -->
        <aside class="sidebar">
            <!-- Sidebar header with dashboard title -->
            <div class="sidebar-header">
                <h2>County Admin Dashboard</h2>
            </div>
            <!-- Navigation links for dashboard sections -->
            <nav class="sidebar-nav">
                <a href="?section=home" class="nav-item"><span class="nav-icon">🏠</span> Home</a>
                <a href="?section=transfers" class="nav-item"><span class="nav-icon">🔄</span> Transfers</a>
                <a href="?section=reports" class="nav-item"><span class="nav-icon">📑</span> Reports</a>
                <a href="?section=register_school" class="nav-item"><span class="nav-icon">🏫</span> Register School</a>
            </nav>
        </aside>
        <!-- Main content area -->
        <div class="main-content">
            <!-- Top header with title and user menu -->
            <header class="top-header">
                <h1>County Admin - <?php echo htmlspecialchars($county); ?></h1>
                <!-- User menu for profile and logout -->
                <div class="user-menu">
                    <!-- Button to toggle user menu -->
                    <button id="userMenuBtn" class="user-menu-btn">
                        <!-- Variable: $username - Admin username for display -->
                        <span><?php echo htmlspecialchars($username); ?></span>
                        <!-- Dynamic avatar using ui-avatars.com -->
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($username); ?>" alt="Admin" class="user-avatar">
                    </button>
                    <!-- Dropdown menu, hidden by default -->
                    <div id="userMenu" class="user-menu-dropdown hidden">
                        <!-- Profile details section -->
                        <div class="profile-details">
                            <h3>Profile</h3>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($username); ?></p>
                            <p><strong>Role:</strong> County Admin</p>
                            <p><strong>County:</strong> <?php echo htmlspecialchars($county); ?></p>
                        </div>
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
                        <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
                        <p>Manage your county's schools using the options below:</p>
                        <div class="dashboard-cards">
                            <div class="dashboard-card"><a href="?section=transfers"><img src="assets/transfer_students.png" alt="Transfers" class="card-image"><h3>Transfers</h3></a></div>
                            <div class="dashboard-card"><a href="?section=reports"><img src="assets/reports.png" alt="Reports" class="card-image"><h3>Reports</h3></a></div>
                            <div class="dashboard-card"><a href="?section=register_school"><img src="assets/register_school.png" alt="Register School" class="card-image"><h3>Register School</h3></a></div>
                        </div>
                    </div>
                    <?php
                } elseif ($section === 'transfers') {
                    ?>
                    <!-- Transfers section for managing pending student transfers -->
                    <div class="content-box">
                        <h2>Pending Student Transfers</h2>
                        <?php
                        // Check if there are pending transfers to display
                        if (empty($transfers)) {
                            echo '<p>No pending transfers in ' . htmlspecialchars($county) . '.</p>';
                        } else {
                            ?>
                            <table>
                                <tr>
                                    <th>Student</th>
                                    <th>From School</th>
                                    <th>To School</th>
                                    <th>Request Date</th>
                                    <th>Action</th>
                                </tr>
                                <?php
                                // Loop through transfers array to display each transfer request
                                foreach ($transfers as $transfer) {
                                    // Fetch destination school name for display
                                    $to_school_sql = "SELECT school_name FROM schools WHERE school_id = ?";
                                    $stmt = $conn->prepare($to_school_sql);
                                    $stmt->bind_param("i", $transfer['to_school_id']);
                                    $stmt->execute();
                                    $to_school = $stmt->get_result()->fetch_assoc()['school_name'];
                                    $stmt->close();

                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($transfer['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($transfer['from_school']); ?></td>
                                        <td><?php echo htmlspecialchars($to_school); ?></td>
                                        <td><?php echo htmlspecialchars($transfer['request_date']); ?></td>
                                        <td>
                                            <!-- Transfer action form with approve/reject buttons -->
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="transfer_id" value="<?php echo $transfer['transfer_id']; ?>">
                                                <button type="submit" name="transfer_action" value="approve" class="btn btn-approve">Approve</button>
                                                <button type="submit" name="transfer_action" value="reject" class="btn btn-reject">Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </table>
                            <?php
                        }
                        ?>
                        <a href="?section=home" class="back-btn">Back to Home</a>
                    </div>
                    <?php
                } elseif ($section === 'reports') {
                    ?>
                    <!-- Reports section for generating performance reports -->
                    <div class="content-box">
                        <h2>Generate Reports</h2>
                        <!-- Report filter form -->
                        <form method="post" class="filter-form">
                            <!-- Sub-county filter dropdown -->
                            <div class="form-group">
                                <label for="sub_county">Sub-County:</label>
                                <select name="sub_county" id="sub_county">
                                    <option value="">All Sub-Counties</option>
                                    <?php
                                    // Loop through sub-counties to create dropdown options
                                    foreach ($sub_counties as $sc) {
                                        // Mark option as selected if it matches current filter
                                        $selected = ($sub_county_filter ?? '') === $sc['sub_county'] ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($sc['sub_county']) . '" ' . $selected . '>' . htmlspecialchars($sc['sub_county']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <!-- Term filter dropdown -->
                            <div class="form-group">
                                <label for="term">Term:</label>
                                <select name="term" id="term">
                                    <option value="">All Terms</option>
                                    <option value="Term 1">Term 1</option>
                                    <option value="Term 2">Term 2</option>
                                    <option value="Term 3">Term 3</option>
                                </select>
                            </div>
                            
                            <!-- Year filter dropdown -->
                            <div class="form-group">
                                <label for="year">Year:</label>
                                <select name="year" id="year">
                                    <option value="">All Years</option>
                                    <?php
                                    // Loop through years from current year back to 2020
                                    for ($y = date("Y"); $y >= 2020; $y--) {
                                        // Mark option as selected if it matches current filter
                                        $selected = ($year_filter ?? '') == $y ? 'selected' : '';
                                        echo '<option value="' . $y . '" ' . $selected . '>' . $y . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <!-- Submit button to generate report -->
                            <input type="submit" name="generate_report" value="Generate Report" class="btn-report">
                        </form>
                        
                        <?php
                        // Display report results if data is available
                        if (!empty($report_data)) {
                            // Variable: $sub_county_display - String, display name for sub-county filter
                            $sub_county_display = $sub_county_filter ?: 'All Sub-Counties';
                            // Variable: $year_display - String, display name for year filter
                            $year_display = $year_filter ?: 'All Years';
                            ?>
                            <h3>Performance Report for <?php echo htmlspecialchars($sub_county_display); ?> - <?php echo htmlspecialchars($year_display); ?></h3>
                            <table>
                                <tr>
                                    <th>Student</th>
                                    <th>School</th>
                                    <th>Term</th>
                                    <th>Year</th>
                                    <th>Average Score</th>
                                </tr>
                                <?php
                                // Loop through report data to display each student's performance
                                foreach ($report_data as $row) {
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['school_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['term']); ?></td>
                                        <td><?php echo htmlspecialchars($row['year']); ?></td>
                                        <td><?php echo htmlspecialchars($row['average_score']); ?></td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </table>
                            <?php
                        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            echo '<p>No data available for the selected filter.</p>';
                        }
                        ?>
                        <a href="?section=home" class="back-btn">Back to Home</a>
                    </div>
                    <?php
                } elseif ($section === 'register_school') {
                    ?>
                    <!-- Register School section for adding new schools -->
                    <div class="content-box">
                        <?php
                        // Include school registration form from external file
                        include 'school_reg.php';
                        ?>
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