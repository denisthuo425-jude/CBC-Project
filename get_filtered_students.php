<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$school_id = $_GET['school_id'] ?? '';
$grade = $_GET['grade'] ?? '';
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$year = isset($_GET['year']) ? $_GET['year'] : '';
$term = isset($_GET['term']) ? $_GET['term'] : '';

$sql = "SELECT p.*, s.name AS student_name, s.current_grade, u.username AS teacher_name,
        YEAR(CURRENT_DATE()) as current_year
        FROM performance p 
        JOIN students s ON p.student_id = s.student_id 
        LEFT JOIN staffs st ON TRIM(SUBSTRING(s.current_grade, 7)) = TRIM(st.assigned_grade) AND s.school_id = st.school_id
        LEFT JOIN users u ON st.user_id = u.user_id
        WHERE s.school_id = ? AND s.current_grade = CONCAT('Grade ', ?)";
$params = [$school_id, $grade];
$types = "is";

if ($student_id) {
    $sql .= " AND p.student_id = ?";
    $params[] = $student_id;
    $types .= "i";
}
if ($year) {
    $sql .= " AND p.year = ?";
    $params[] = $year;
    $types .= "i";
}
if ($term) {
    $sql .= " AND p.term = ?";
    $params[] = $term;
    $types .= "s";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $current_grade_num = (int)substr($row['current_grade'], 6);
        $year_diff = $row['current_year'] - $row['year'];
        $historical_grade = "Grade " . ($current_grade_num - $year_diff);
        
        $students[] = [
            'student_name' => $row['student_name'],
            'teacher_name' => $row['teacher_name'] ?? 'Unknown',
            'term' => $row['term'],
            'year' => $row['year'],
            'current_grade' => $historical_grade,
            'maths_score' => $row['maths_score'],
            'maths_reflection' => $row['maths_reflection'],
            'maths_comment' => $row['maths_comment'] ?? '-',
            'english_score' => $row['english_score'],
            'english_reflection' => $row['english_reflection'],
            'english_comment' => $row['english_comment'] ?? '-',
            'kiswahili_score' => $row['kiswahili_score'],
            'kiswahili_reflection' => $row['kiswahili_reflection'],
            'kiswahili_comment' => $row['kiswahili_comment'] ?? '-',
            'cre_ire_score' => $row['cre_ire_score'],
            'cre_ire_reflection' => $row['cre_ire_reflection'],
            'cre_ire_comment' => $row['cre_ire_comment'] ?? '-',
            'social_studies_score' => $row['social_studies_score'],
            'social_studies_reflection' => $row['social_studies_reflection'],
            'social_studies_comment' => $row['social_studies_comment'] ?? '-',
            'pe_score' => $row['pe_score'],
            'pe_reflection' => $row['pe_reflection'],
            'pe_comment' => $row['pe_comment'] ?? '-',
            'agriculture_score' => $row['agriculture_score'],
            'agriculture_reflection' => $row['agriculture_reflection'],
            'agriculture_comment' => $row['agriculture_comment'] ?? '-',
            'homescience_score' => $row['homescience_score'],
            'homescience_reflection' => $row['homescience_reflection'],
            'homescience_comment' => $row['homescience_comment'] ?? '-',
            'business_studies_score' => $row['business_studies_score'],
            'business_studies_reflection' => $row['business_studies_reflection'],
            'business_studies_comment' => $row['business_studies_comment'] ?? '-',
            'art_craft_score' => $row['art_craft_score'],
            'art_craft_reflection' => $row['art_craft_reflection'],
            'art_craft_comment' => $row['art_craft_comment'] ?? '-',
            'science_tech_score' => $row['science_tech_score'],
            'science_tech_reflection' => $row['science_tech_reflection'],
            'science_tech_comment' => $row['science_tech_comment'] ?? '-',
            'music_score' => $row['music_score'],
            'music_reflection' => $row['music_reflection'],
            'music_comment' => $row['music_comment'] ?? '-',
            'total_score' => $row['total_score'],
            'average_score' => $row['average_score'],
            'reflection' => $row['reflection'],
            'general_comment' => $row['general_comment'] ?? 'No comment provided.'
        ];
    }
}

echo json_encode($students);

$conn->close();
?>
