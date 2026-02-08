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

// 1. MARK LESSON AS COMPLETE
// Check if already completed to avoid duplicates
$check = $pdo->prepare("SELECT id FROM lesson_progress WHERE user_id = ? AND lesson_id = ?");
$check->execute([$user_id, $lesson_id]);

if (!$check->fetch()) {
    $stmt = $pdo->prepare("INSERT INTO lesson_progress (user_id, lesson_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $lesson_id]);
}

// 2. CHECK COURSE COMPLETION (The Missing Logic)
// Count total lessons
$stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
$stmt->execute([$course_id]);
$total_lessons = $stmt->fetchColumn();

// Count completed lessons for this course
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT l.id) FROM lessons l 
                       JOIN lesson_progress lp ON l.id = lp.lesson_id 
                       WHERE l.course_id = ? AND lp.user_id = ?");
$stmt->execute([$course_id, $user_id]);
$completed_count = $stmt->fetchColumn();

// Calculate Progress %
$new_progress = ($total_lessons > 0) ? round(($completed_count / $total_lessons) * 100) : 0;

// Update Enrollment Table
if ($completed_count >= $total_lessons) {
    // COURSE COMPLETED!
    $update = $pdo->prepare("UPDATE enrollments SET progress = 100, status = 'completed', completed_at = NOW() WHERE student_id = ? AND course_id = ?");
    $update->execute([$user_id, $course_id]);
} else {
    // JUST UPDATE PROGRESS
    $update = $pdo->prepare("UPDATE enrollments SET progress = ?, status = 'in_progress' WHERE student_id = ? AND course_id = ?");
    $update->execute([$new_progress, $user_id, $course_id]);
}

// 3. NAVIGATION LOGIC
if (!empty($redirect_url)) {
    header("Location: " . $redirect_url);
} else {
    // Auto-find next lesson
    $next_lesson_stmt = $pdo->prepare("SELECT id FROM lessons WHERE course_id = ? AND position > (SELECT position FROM lessons WHERE id = ?) ORDER BY position ASC LIMIT 1");
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
            // Course is fully done, go to dashboard or certificate
            header("Location: dashboard.php?msg=course_completed");
        }
    }
}
exit;
?>