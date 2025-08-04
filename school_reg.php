<?php
// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';
require_once 'activity_log.php'; // For logging user activities

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'County Admin') {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'] ?? '';
$session_county = ucfirst(explode('.', $username)[1] ?? '');
if (empty($session_county)) {
    header('Location: login.php?error=' . urlencode('Could not determine county from username.'));
    exit();
}

// Initialize form data for retention
$school_name = $_POST['school_name'] ?? ($_GET['school_name'] ?? '');
$sub_county = $_POST['sub_county'] ?? ($_GET['sub_county'] ?? '');
$ward = $_POST['ward'] ?? ($_GET['ward'] ?? '');
$registration_date = $_POST['registration_date'] ?? ($_GET['registration_date'] ?? '');
$school_level = $_POST['school_level'] ?? ($_GET['school_level'] ?? '');
$school_category = $_POST['school_category'] ?? ($_GET['school_category'] ?? '');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_school'])) {
    $school_name = trim($_POST['school_name'] ?? '');
    $county = trim($_POST['county'] ?? '');
    $sub_county = trim($_POST['sub_county'] ?? '');
    $ward = trim($_POST['ward'] ?? '');
    $registration_date = trim($_POST['registration_date'] ?? '');
    $school_level = trim($_POST['school_level'] ?? '');
    $school_category = trim($_POST['school_category'] ?? '');

    // Plain validation: Check for empty fields
    if (empty($school_name) || empty($county) || empty($sub_county) || empty($ward) || empty($registration_date) || empty($school_level) || empty($school_category)) {
        $query = http_build_query([
            'section' => 'register_school',
            'status' => 'error',
            'message' => 'All fields are required!',
            'school_name' => $school_name,
            'sub_county' => $sub_county,
            'ward' => $ward,
            'registration_date' => $registration_date,
            'school_level' => $school_level,
            'school_category' => $school_category
        ]);
        header('Location: county_admin_dashboard.php?' . $query);
        exit();
    }

    // Plain date validation: Check format (DD-MM-YYYY) without regex
    $date_parts = explode('-', $registration_date);
    if (count($date_parts) !== 3 || strlen($date_parts[0]) !== 2 || strlen($date_parts[1]) !== 2 || strlen($date_parts[2]) !== 4 || !is_numeric($date_parts[0]) || !is_numeric($date_parts[1]) || !is_numeric($date_parts[2])) {
        $query = http_build_query([
            'section' => 'register_school',
            'status' => 'error',
            'message' => 'Invalid date format. Please use DD-MM-YYYY.',
            'school_name' => $school_name,
            'sub_county' => $sub_county,
            'ward' => $ward,
            'registration_date' => $registration_date,
            'school_level' => $school_level,
            'school_category' => $school_category
        ]);
        header('Location: county_admin_dashboard.php?' . $query);
        exit();
    }

    // Convert date from DD-MM-YYYY to YYYY-MM-DD for database
    $registration_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];

    // Check county mismatch
    if ($county !== $session_county) {
        $query = http_build_query([
            'section' => 'register_school',
            'status' => 'error',
            'message' => 'County mismatch. You can only register schools in your county.',
            'school_name' => $school_name,
            'sub_county' => $sub_county,
            'ward' => $ward,
            'registration_date' => $registration_date,
            'school_level' => $school_level,
            'school_category' => $school_category
        ]);
        header('Location: county_admin_dashboard.php?' . $query);
        exit();
    }

    // Check for duplicate school name in county
    $stmt = $conn->prepare("SELECT school_id FROM schools WHERE school_name = ? AND county = ?");
    $stmt->bind_param("ss", $school_name, $county);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $query = http_build_query([
            'section' => 'register_school',
            'status' => 'error',
            'message' => 'School name already exists in this county.',
            'school_name' => $school_name,
            'sub_county' => $sub_county,
            'ward' => $ward,
            'registration_date' => $registration_date,
            'school_level' => $school_level,
            'school_category' => $school_category
        ]);
        header('Location: county_admin_dashboard.php?' . $query);
        exit();
    }
    $stmt->close();

    // Insert school into database
    $sql = "INSERT INTO schools (school_name, county, sub_county, ward, date_of_registration, school_level, school_category) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $school_name, $county, $sub_county, $ward, $registration_date, $school_level, $school_category);

    if ($stmt->execute()) {
        header('Location: county_admin_dashboard.php?section=register_school&status=success');
        log_activity($_SESSION['user_id'], $_SESSION['role'], 'register_school');
    } else {
        $query = http_build_query([
            'section' => 'register_school',
            'status' => 'error',
            'message' => $conn->error,
            'school_name' => $school_name,
            'sub_county' => $sub_county,
            'ward' => $ward,
            'registration_date' => $registration_date,
            'school_level' => $school_level,
            'school_category' => $school_category
        ]);
        header('Location: county_admin_dashboard.php?' . $query);
    }

    $stmt->close();
    $conn->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Registration - CBC Performance Tracking</title>
    <link rel="stylesheet" href="assets/styles_county_admin.css">
</head>
<body>
    <div class="content-box">
        <h2>Register New School</h2>
        <?php
        if (isset($_GET['status']) && $_GET['status'] === 'success') {
            echo '<p class="success">School registered successfully!</p>';
        } elseif (isset($_GET['status']) && $_GET['status'] === 'error') {
            echo '<p class="error">' . htmlspecialchars($_GET['message'] ?? 'Error registering school.') . '</p>';
        }
        ?>
        <form id="schoolForm" action="school_reg.php" method="POST">
            <div class="form-group">
                <label for="school_name">School Name:</label>
                <input type="text" name="school_name" id="school_name" value="<?php echo htmlspecialchars($school_name); ?>" required>
            </div>
            <div class="form-group">
                <label for="county">County:</label>
                <input type="text" name="county" id="county" value="<?php echo htmlspecialchars($session_county); ?>" readonly>
            </div>
            <div class="form-group">
                <label for="sub_county">Sub-County:</label>
                <input type="text" name="sub_county" id="sub_county" value="<?php echo htmlspecialchars($sub_county); ?>" required>
            </div>
            <div class="form-group">
                <label for="ward">Ward:</label>
                <input type="text" name="ward" id="ward" value="<?php echo htmlspecialchars($ward); ?>" required>
            </div>
            <div class="form-group">
                <label for="registration_date">Registration Date (DD-MM-YYYY):</label>
                <input type="text" name="registration_date" id="registration_date" value="<?php 
                    if (!empty($registration_date)) {
                        $date = new DateTime($registration_date);
                        echo $date->format('d-m-Y');
                    } else {
                        echo htmlspecialchars($registration_date);
                    }
                ?>" placeholder="DD-MM-YYYY" required>
            </div>
            <div class="form-group">
                <label for="school_level">School Level:</label>
                <select name="school_level" id="school_level" required>
                    <option value="">Select Level</option>
                    <option value="Primary" <?php echo $school_level === 'Primary' ? 'selected' : ''; ?>>Primary</option>
                    <option value="Secondary" <?php echo $school_level === 'Secondary' ? 'selected' : ''; ?>>Secondary</option>
                </select>
            </div>
            <div class="form-group">
                <label for="school_category">School Category:</label>
                <select name="school_category" id="school_category" required>
                    <option value="">Select Category</option>
                    <option value="Day" <?php echo $school_category === 'Day' ? 'selected' : ''; ?>>Day</option>
                    <option value="Boarding" <?php echo $school_category === 'Boarding' ? 'selected' : ''; ?>>Boarding</option>
                    <option value="Mixed" <?php echo $school_category === 'Mixed' ? 'selected' : ''; ?>>Mixed</option>
                </select>
            </div>
            <input type="hidden" name="register_school" value="1">
            <button type="submit" class="submit-btn">Register School</button>
        </form>
    </div>
</body>
</html>