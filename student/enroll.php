<?php
// student/enroll.php
require '../includes/db.php';

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

// 1. Check if already enrolled
$check = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
$check->execute([$user_id, $course_id]);

if (!$check->fetch()) {
    // 2. Enroll the student
    $stmt = $pdo->prepare("INSERT INTO enrollments (user_id, course_id) VALUES (?, ?)");
    if ($stmt->execute([$user_id, $course_id])) {
        // Optional: Add gamification points for enrolling?
    } else {
        die("Error enrolling in course.");
    }
}

// 3. Redirect to the Classroom (The Player)
header("Location: course_player.php?course_id=" . $course_id);
exit;
?>