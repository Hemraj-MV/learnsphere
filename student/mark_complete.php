<?php
// student/mark_complete.php
require '../includes/db.php';

// 1. Start Session
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$lesson_id = $_POST['lesson_id'] ?? null;
$course_id = $_POST['course_id'] ?? null; // Used only for redirection

if (!$lesson_id || !$course_id) {
    header("Location: dashboard.php");
    exit;
}

// 2. Check if already marked complete
// We only need to check user_id and lesson_id
$check = $pdo->prepare("SELECT id FROM lesson_progress WHERE user_id = ? AND lesson_id = ?");
$check->execute([$user_id, $lesson_id]);

if (!$check->fetch()) {
    // 3. Mark as Complete
    // ERROR FIX: Removed 'course_id' from this INSERT query
    // If your table has a 'status' column, keep 'completed'. If not, remove it.
    // This version assumes a standard (user_id, lesson_id) structure.
    try {
        $stmt = $pdo->prepare("INSERT INTO lesson_progress (user_id, lesson_id, status) VALUES (?, ?, 'completed')");
        $stmt->execute([$user_id, $lesson_id]);
    } catch (PDOException $e) {
        // Fallback: If 'status' column also doesn't exist, try simplified insert
        $stmt = $pdo->prepare("INSERT INTO lesson_progress (user_id, lesson_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $lesson_id]);
    }
}

// 4. Find the Next Lesson to Redirect to
$next_stmt = $pdo->prepare("SELECT id FROM lessons WHERE course_id = ? AND id > ? ORDER BY id ASC LIMIT 1");
$next_stmt->execute([$course_id, $lesson_id]);
$next_lesson = $next_stmt->fetch();

if ($next_lesson) {
    // Go to next lesson
    header("Location: course_player.php?course_id=$course_id&lesson_id=" . $next_lesson['id']);
} else {
    // Course finished or no more lessons, go back to main player view
    header("Location: course_player.php?course_id=$course_id");
}
exit;
?>