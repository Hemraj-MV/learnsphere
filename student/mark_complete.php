<?php
// student/mark_complete.php
require '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$lesson_id = $_POST['lesson_id'] ?? null;
$course_id = $_POST['course_id'] ?? null;
$redirect_url = $_POST['redirect_url'] ?? null;

if (!$lesson_id || !$course_id) {
    header("Location: dashboard.php");
    exit;
}

// 1. Mark Lesson as Complete
$check = $pdo->prepare("SELECT id FROM lesson_progress WHERE user_id = ? AND lesson_id = ?");
$check->execute([$user_id, $lesson_id]);

if (!$check->fetch()) {
    try {
        $stmt = $pdo->prepare("INSERT INTO lesson_progress (user_id, lesson_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $lesson_id]);
    } catch (PDOException $e) {
        // Ignore duplicate errors
    }
}

// 2. Navigation Logic
if (!empty($redirect_url)) {
    // If the player sent a specific next step (like quiz_id=2), go there.
    header("Location: " . $redirect_url);
} else {
    // Fallback if no link provided
    $next_lesson_stmt = $pdo->prepare("SELECT id FROM lessons WHERE course_id = ? AND id > ? ORDER BY id ASC LIMIT 1");
    $next_lesson_stmt->execute([$course_id, $lesson_id]);
    $next_lesson = $next_lesson_stmt->fetch();

    if ($next_lesson) {
        header("Location: course_player.php?course_id=$course_id&lesson_id=" . $next_lesson['id']);
    } else {
        // No lessons left? Check for Quizzes
        $next_quiz_stmt = $pdo->prepare("SELECT id FROM quizzes WHERE course_id = ? ORDER BY id ASC LIMIT 1");
        $next_quiz_stmt->execute([$course_id]);
        $next_quiz = $next_quiz_stmt->fetch();

        if ($next_quiz) {
            header("Location: course_player.php?course_id=$course_id&quiz_id=" . $next_quiz['id']);
        } else {
            header("Location: dashboard.php?msg=course_completed");
        }
    }
}
exit;
?>