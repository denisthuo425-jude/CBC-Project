<?php
// Start session to manage user authentication
session_start();
// Include database connection for MySQLi queries
require_once 'db_connect.php';

// Check if user is authenticated and has role 'System Admin'
// Variables: $_SESSION['user_id'], $_SESSION['role'] - Session properties for user ID and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    // Redirect to login page if not authenticated
    header('Location: login.php');
    exit();
}

// Variable: $section - String, determines current dashboard section (defaults to 'home')
$section = $_GET['section'] ?? 'home';

// Function: render_table - Renders a table with optional search filter
// Parameters:
//   $conn (mysqli) - MySQLi connection object
//   $sql (array) - Associative array with 'base' and 'search' SQL queries
//   $headers (array) - Array of table column headers
//   $row_callback (callable) - Callback to format each row
//   $search (string) - Search term for filtering (default: empty)
// Returns: None (outputs HTML table)
function render_table($conn, $sql, $headers, $row_callback, $search = '') {
    if ($search) {
        // Object: $stmt - MySQLi prepared statement for search query
        $stmt = $conn->prepare($sql['search']);
        // Variable: $search_param - String, search term with wildcards
        $search_param = "%$search%";
        // Method: bind_param - Binds search parameters
        $stmt->bind_param(str_repeat('s', substr_count($sql['search'], '?')), ...array_fill(0, substr_count($sql['search'], '?'), $search_param));
        // Method: execute - Executes the query
        $stmt->execute();
        // Method: get_result - Retrieves results
        $result = $stmt->get_result();
        // Method: close - Closes the statement
        $stmt->close();
    } else {
        // Method: query - Executes base query without parameters
        $result = $conn->query($sql['base']);
    }
    ?>
    <table>
        <tr>
            <?php foreach ($headers as $header): ?>
                <th><?php echo $header; ?></th>
            <?php endforeach; ?>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr><?php echo $row_callback($row); ?></tr>
        <?php endwhile; ?>
    </table>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta tags for character encoding and responsive viewport -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Admin Dashboard - CBC Performance Tracking</title>
    <!-- Link to styles_system_admin.css for styling -->
    <link rel="stylesheet" href="assets/styles_system_admin.css">
</head>
<body>
    <!-- Main container for dashboard layout -->
    <div class="dashboard-container">
        <!-- Sidebar navigation -->
        <div class="sidebar">
            <!-- Sidebar header -->
            <div class="sidebar-header">
                <h2>Admin Dashboard</h2>
            </div>
            <?php
            // Variable: $nav_items - Array, navigation items for sidebar
            $nav_items = [
                'home' => ['icon' => '🏠', 'label' => 'Home'],
                'users' => ['icon' => '👤', 'label' => 'Users'],
                'assign_roles' => ['icon' => '🔑', 'label' => 'Assign Roles'],
                'schools' => ['icon' => '🏫', 'label' => 'Schools'],
                'students' => ['icon' => '🎓', 'label' => 'Assessed Students'],
                'transfers' => ['icon' => '↔', 'label' => 'Transfers'],
                'activity' => ['icon' => '📋', 'label' => 'Activity Logs']
            ];
            foreach ($nav_items as $key => $item): ?>
                <a href="admin_dashboard.php?section=<?php echo $key; ?>" class="nav-item <?php echo $section === $key ? 'active' : ''; ?>">
                    <span class="nav-icon"><?php echo $item['icon']; ?></span><?php echo $item['label']; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <!-- Main content area -->
        <div class="main-content">
            <!-- Top header with title and user menu -->
            <div class="top-header">
                <h1>CBC Performance Tracking</h1>
                <div class="user-menu">
                    <!-- Button to toggle user menu -->
                    <button class="user-menu-btn">
                        <!-- Variable: $_SESSION['username'] - String, admin's username -->
                        <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <img src="https://ui-avatars.com/api/?name=Denis+Jude" alt="Admin" class="user-avatar">
                    </button>
                    <!-- Dropdown menu, hidden by default -->
                    <div id="userMenu" class="user-menu-dropdown hidden">
                        <a href="logout.php" class="dropdown-item">Logout</a>
                    </div>
                </div>
            </div>
            <!-- Content box for section-specific content -->
            <div class="content-box">
                <?php
                switch ($section) {
                    case 'home':
                        ?>
                        <!-- Home section -->
                        <h2>Welcome, System Admin</h2>
                        <p>Manage users, schools, students, transfers, and activity logs via the sidebar.</p>
                        <div class="dashboard-cards">
                            <?php
                            // Variable: $cards - Array, dashboard card links
                            $cards = [
                                ['link' => 'users', 'img' => 'users.png', 'label' => 'Manage Users'],
                                ['link' => 'assign_roles', 'img' => 'roles.png', 'label' => 'Assign Roles'],
                                ['link' => 'schools', 'img' => 'schools.png', 'label' => 'Manage Schools'],
                                ['link' => 'students', 'img' => 'admn students.png', 'label' => 'Assessed Students'],
                                ['link' => 'transfers', 'img' => 'admn transfers.png', 'label' => 'Transfers'],
                                ['link' => 'activity', 'img' => 'activity.png', 'label' => 'Activity Logs']
                            ];
                            foreach ($cards as $card): ?>
                                <div class="dashboard-card">
                                    <img src="assets/<?php echo $card['img']; ?>" alt="<?php echo $card['label']; ?>" class="card-image">
                                    <h3><a href="admin_dashboard.php?section=<?php echo $card['link']; ?>"><?php echo $card['label']; ?></a></h3>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php
                        break;

                    case 'users':
                        ?>
                        <!-- Users section -->
                        <h2>Manage Users</h2>
                        <a href="add_user.php" class="submit-btn" style="width: auto; display: inline-block; margin-bottom: 20px;">Add New User</a>
                        <form method="get" class="filter-form">
                            <input type="hidden" name="section" value="users">
                            <div class="form-group">
                                <label>Search Users:</label>
                                <input type="text" name="search" placeholder="Enter username or email" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            </div>
                            <input type="submit" value="Search" class="submit-btn" style="width: auto;">
                        </form>
                        <?php
                        render_table($conn, [
                            'base' => "SELECT user_id, username, email, role FROM users",
                            'search' => "SELECT user_id, username, email, role FROM users WHERE username LIKE ? OR email LIKE ?"
                        ], ['Username', 'Email', 'Role', 'Actions'], function($row) {
                            return "<td>" . htmlspecialchars($row['username']) . "</td>
                                    <td>" . htmlspecialchars($row['email']) . "</td>
                                    <td>" . htmlspecialchars($row['role']) . "</td>
                                    <td>
                                        <a href='edit_user.php?id={$row['user_id']}' class='action-btn edit-btn'>Edit</a>
                                        <a href='edit_user.php?delete_id={$row['user_id']}' class='action-btn delete-btn' onclick='return confirm(\"Are you sure you want to delete this user?\");'>Delete</a>
                                    </td>";
                        }, isset($_GET['search']) ? trim($_GET['search']) : '');
                        break;

                    case 'schools':
                        ?>
                        <!-- Schools section -->
                        <h2>Manage Schools</h2>
                        <a href="add_school.php" class="submit-btn" style="width: auto; display: inline-block; margin-bottom: 20px;">Add New School</a>
                        <form method="get" class="filter-form">
                            <input type="hidden" name="section" value="schools">
                            <div class="form-group">
                                <label>Search Schools:</label>
                                <input type="text" name="search" placeholder="Enter school name or county" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            </div>
                            <input type="submit" value="Search" class="submit-btn" style="width: auto;">
                        </form>
                        <?php
                        render_table($conn, [
                            'base' => "SELECT school_id, school_name, county, school_level, school_category FROM schools",
                            'search' => "SELECT school_id, school_name, county, school_level, school_category FROM schools WHERE school_name LIKE ? OR county LIKE ?"
                        ], ['School Name', 'County', 'Level', 'Category', 'Actions'], function($row) {
                            return "<td>" . htmlspecialchars($row['school_name']) . "</td>
                                    <td>" . htmlspecialchars($row['county']) . "</td>
                                    <td>" . htmlspecialchars($row['school_level']) . "</td>
                                    <td>" . htmlspecialchars($row['school_category']) . "</td>
                                    <td>
                                        <a href='edit_school.php?id={$row['school_id']}' class='action-btn edit-btn'>Edit</a>
                                        <a href='edit_school.php?delete_id={$row['school_id']}' class='action-btn delete-btn' onclick='return confirm(\"Are you sure you want to delete this school?\");'>Delete</a>
                                    </td>";
                        }, isset($_GET['search']) ? trim($_GET['search']) : '');
                        break;

                    case 'students':
                        ?>
                        <!-- Assessed Students section -->
                        <h2>Assessed Students</h2>
                        <?php
                        render_table($conn, [
                            'base' => "SELECT s.name, sc.school_name, s.current_grade, p.term, p.year, p.average_score
                                       FROM students s
                                       JOIN schools sc ON s.school_id = sc.school_id
                                       JOIN performance p ON s.student_id = p.student_id",
                            'search' => ''
                        ], ['Student Name', 'School', 'Grade', 'Term', 'Year', 'Average Score'], function($row) {
                            return "<td>" . htmlspecialchars($row['name']) . "</td>
                                    <td>" . htmlspecialchars($row['school_name']) . "</td>
                                    <td>" . htmlspecialchars($row['current_grade']) . "</td>
                                    <td>" . htmlspecialchars($row['term']) . "</td>
                                    <td>" . htmlspecialchars($row['year']) . "</td>
                                    <td>" . number_format($row['average_score'], 2) . "</td>";
                        });
                        break;

                    case 'transfers':
                        ?>
                        <!-- Transfers section -->
                        <h2>Student Transfers</h2>
                        <?php
                        render_table($conn, [
                            'base' => "SELECT s.name, sf.school_name AS from_school, st.school_name AS to_school, t.request_date, t.status
                                       FROM student_transfers t
                                       JOIN students s ON t.student_id = s.student_id
                                       JOIN schools sf ON t.from_school_id = sf.school_id
                                       JOIN schools st ON t.to_school_id = st.school_id",
                            'search' => ''
                        ], ['Student Name', 'From School', 'To School', 'Request Date', 'Status'], function($row) {
                            return "<td>" . htmlspecialchars($row['name']) . "</td>
                                    <td>" . htmlspecialchars($row['from_school']) . "</td>
                                    <td>" . htmlspecialchars($row['to_school']) . "</td>
                                    <td>" . htmlspecialchars($row['request_date']) . "</td>
                                    <td>" . htmlspecialchars($row['status']) . "</td>";
                        });
                        break;

                    case 'activity':
                        ?>
                        <!-- Activity Logs section -->
                        <h2>User Activity Logs</h2>
                        <h3>Session Logs (Login/Logout)</h3>
                        <?php
                        render_table($conn, [
                            'base' => "SELECT u.username, u.role, a.login_time, a.logout_time FROM user_activity a JOIN users u ON a.user_id = u.user_id WHERE a.activity_type IS NULL ORDER BY a.login_time DESC",
                            'search' => ''
                        ], ['Username', 'Role', 'Login Time', 'Logout Time'], function($row) {
                            return "<td>" . htmlspecialchars($row['username']) . "</td>"
                                . "<td>" . htmlspecialchars($row['role']) . "</td>"
                                . "<td>" . htmlspecialchars($row['login_time']) . "</td>"
                                . "<td>" . htmlspecialchars($row['logout_time'] ?? 'N/A') . "</td>";
                        });
                        ?>
                        <h3>Other Activities</h3>
                        <?php
                        render_table($conn, [
                            'base' => "SELECT u.username, u.role, a.activity_type, a.login_time FROM user_activity a JOIN users u ON a.user_id = u.user_id WHERE a.activity_type IS NOT NULL ORDER BY a.login_time DESC",
                            'search' => ''
                        ], ['Username', 'Role', 'Activity', 'Time'], function($row) {
                            return "<td>" . htmlspecialchars($row['username']) . "</td>"
                                . "<td>" . htmlspecialchars($row['role']) . "</td>"
                                . "<td>" . htmlspecialchars($row['activity_type']) . "</td>"
                                . "<td>" . htmlspecialchars($row['login_time']) . "</td>";
                        });
                        break;

                    case 'assign_roles':
                        ?>
                        <!-- Assign Roles section -->
                        <h2>Assign Roles</h2>
                        <?php
                        // Display success/error messages
                        if (isset($_GET['success'])) {
                            echo '<div class="alert alert-success">Role assigned successfully!</div>';
                        }
                        if (isset($_GET['error'])) {
                            echo '<div class="alert alert-danger">' . htmlspecialchars($_GET['error']) . '</div>';
                        }
                        ?>
                        <div class="role-assignment-container">
                            <!-- Head Teacher Assignment Form -->
                            <div class="assignment-form">
                                <h3>Assign Head Teacher to School</h3>
                                <form method="post" action="assign_roles.php" class="filter-form">
                                    <input type="hidden" name="action" value="assign_head_teacher">
                                    <div class="form-group">
                                        <label>Select User:</label>
                                        <select name="user_id" required>
                                            <option value="">Select Head Teacher</option>
                                            <?php
                                            $head_teachers = $conn->query("SELECT u.user_id, u.username, u.email FROM users u 
                                                                         LEFT JOIN staffs s ON u.user_id = s.user_id 
                                                                         WHERE u.role = 'Head Teacher' AND s.user_id IS NULL");
                                            while ($ht = $head_teachers->fetch_assoc()) {
                                                echo "<option value='{$ht['user_id']}'>{$ht['username']} ({$ht['email']})</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Select School:</label>
                                        <select name="school_id" required>
                                            <option value="">Select School</option>
                                            <?php
                                            $schools = $conn->query("SELECT school_id, school_name FROM schools s 
                                                                   WHERE NOT EXISTS (
                                                                       SELECT 1 FROM staffs 
                                                                       WHERE school_id = s.school_id 
                                                                       AND role = 'Head Teacher'
                                                                   )");
                                            while ($school = $schools->fetch_assoc()) {
                                                echo "<option value='{$school['school_id']}'>{$school['school_name']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Qualification:</label>
                                        <select name="qualification" required>
                                            <option value="">Select Qualification</option>
                                            <option value="Diploma">Diploma</option>
                                            <option value="Degree">Degree</option>
                                            <option value="Masters">Masters</option>
                                            <option value="PhD">PhD</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Mobile Number:</label>
                                        <input type="text" name="mobile_number" required placeholder="Enter 10-digit number">
                                    </div>
                                    <input type="submit" value="Assign Head Teacher" class="submit-btn">
                                </form>
                            </div>

                            <!-- County Admin Assignment Form -->
                            <div class="assignment-form">
                                <h3>Assign County Admin</h3>
                                <form method="post" action="assign_roles.php" class="filter-form">
                                    <input type="hidden" name="action" value="assign_county_admin">
                                    <div class="form-group">
                                        <label>Select User:</label>
                                        <select name="user_id" required>
                                            <option value="">Select County Admin</option>
                                            <?php
                                            $county_admins = $conn->query("SELECT u.user_id, u.username, u.email FROM users u 
                                                                         LEFT JOIN staffs s ON u.user_id = s.user_id 
                                                                         WHERE u.role = 'County Admin' AND s.user_id IS NULL");
                                            while ($ca = $county_admins->fetch_assoc()) {
                                                echo "<option value='{$ca['user_id']}'>{$ca['username']} ({$ca['email']})</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>County:</label>
                                        <input type="text" name="county" required placeholder="Enter county name">
                                    </div>
                                    <div class="form-group">
                                        <label>Qualification:</label>
                                        <select name="qualification" required>
                                            <option value="">Select Qualification</option>
                                            <option value="Diploma">Diploma</option>
                                            <option value="Degree">Degree</option>
                                            <option value="Masters">Masters</option>
                                            <option value="PhD">PhD</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Mobile Number:</label>
                                        <input type="text" name="mobile_number" required placeholder="Enter 10-digit number">
                                    </div>
                                    <input type="submit" value="Assign County Admin" class="submit-btn">
                                </form>
                            </div>
                        </div>
                        <?php
                        break;
                }
                ?>
            </div>
        </div>
    </div>
    <!-- JavaScript for user menu toggle -->
    <script>
        // Variables: userMenuBtn, userMenu - DOM elements for menu button and dropdown
        const userMenuBtn = document.querySelector('.user-menu-btn');
        const userMenu = document.getElementById('userMenu');
        // Event listener to toggle dropdown visibility
        userMenuBtn.addEventListener('click', () => userMenu.classList.toggle('hidden'));
        // Event listener to hide dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!userMenuBtn.contains(e.target) && !userMenu.contains(e.target)) {
                userMenu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>