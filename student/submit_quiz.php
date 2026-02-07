<?php
// student/submit_quiz.php
require '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$course_id = $_POST['course_id'] ?? 0; // Ensure you pass this in the form if needed, or fetch via quiz link
// Note: In your course_player.php form, you might need to add a hidden input for course_id if you want to redirect back easily.
// For now, we will handle the redirection logic carefully.

// 1. Collect Submitted Answers
$submitted_answers = [];
foreach ($_POST as $key => $value) {
    if (strpos($key, 'q') === 0) {
        $question_id = substr($key, 1); // Extract ID from "q12" -> "12"
        $submitted_answers[$question_id] = $value;
    }
}

if (empty($submitted_answers)) {
    die("No answers submitted.");
}

// 2. Fetch Correct Answers from Database
// We get the quiz_id from the first question answered to verify context
$first_q_id = array_key_first($submitted_answers);
$stmt = $pdo->prepare("SELECT quiz_id FROM questions WHERE id = ?");
$stmt->execute([$first_q_id]);
$quiz_id = $stmt->fetchColumn();

if (!$quiz_id) {
    die("Invalid quiz data.");
}

// Get all correct answers for this quiz
$q_stmt = $pdo->prepare("SELECT id, correct_option FROM questions WHERE quiz_id = ?");
$q_stmt->execute([$quiz_id]);
$correct_answers = $q_stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [id => 'A', id => 'B']

// 3. Calculate Score
$score = 0;
$total_questions = count($correct_answers);

foreach ($submitted_answers as $q_id => $user_ans) {
    if (isset($correct_answers[$q_id]) && $correct_answers[$q_id] === $user_ans) {
        $score++;
    }
}

// 4. (Optional) Save Attempt to Database
// Assuming you might have a table 'quiz_attempts' (id, user_id, quiz_id, score, total, created_at)
/* try {
    $save_stmt = $pdo->prepare("INSERT INTO quiz_attempts (user_id, quiz_id, score, total) VALUES (?, ?, ?, ?)");
    $save_stmt->execute([$user_id, $quiz_id, $score, $total_questions]);
} catch (Exception $e) {
    // Silent fail or log error
}
*/

// 5. Redirect User with Results
// We need to find the course_id to redirect back to the player.
$c_stmt = $pdo->prepare("SELECT course_id FROM quizzes WHERE id = ?");
$c_stmt->execute([$quiz_id]);
$course_id = $c_stmt->fetchColumn();

// Redirect back to the course player showing the quiz, adding a score parameter
header("Location: course_player.php?course_id=$course_id&quiz_id=$quiz_id&score=$score&total=$total_questions");
exit;
?>