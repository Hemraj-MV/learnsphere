<?php
// student/enroll.php
require '../includes/db.php';

// 1. Start Session (Required to check login status)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'learner') {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$course_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // 3. Check if already enrolled (FIXED: Changed 'user_id' to 'student_id')
    $check = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
    $check->execute([$user_id, $course_id]);

    // 4. If not enrolled, enroll them (FIXED: Changed 'user_id' to 'student_id')
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, enrolled_at) VALUES (?, ?, NOW())");
        if (!$stmt->execute([$user_id, $course_id])) {
            die("Error enrolling in course.");
        }
    }

    // 5. Redirect to the Course Player
    header("Location: course_player.php?course_id=" . $course_id);
    exit;

} catch (PDOException $e) {
    // Catch specific database errors
    die("Database Error: " . $e->getMessage());
}
?>