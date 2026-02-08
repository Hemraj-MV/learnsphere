<?php
// student/submit_quiz.php
require '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }

$user_id = $_SESSION['user_id'];

// 1. Process Answers
$submitted_answers = [];
foreach ($_POST as $key => $value) {
    if (strpos($key, 'q') === 0) {
        $question_id = substr($key, 1);
        $submitted_answers[$question_id] = $value;
    }
}

if (empty($submitted_answers)) die("No answers submitted.");

// 2. Fetch Quiz Info
$first_q_id = array_key_first($submitted_answers);
$stmt = $pdo->prepare("SELECT quiz_id FROM questions WHERE id = ?");
$stmt->execute([$first_q_id]);
$quiz_id = $stmt->fetchColumn();

// 3. Calculate Score
$q_stmt = $pdo->prepare("SELECT id, correct_option FROM questions WHERE quiz_id = ?");
$q_stmt->execute([$quiz_id]);
$correct_answers = $q_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$score = 0;
$total_questions = count($correct_answers);
foreach ($submitted_answers as $q_id => $user_ans) {
    if (isset($correct_answers[$q_id]) && $correct_answers[$q_id] === $user_ans) {
        $score++;
    }
}

// 4. SAVE TO DATABASE (Dynamic Gamification)
// Check attempt number
$att_stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ? AND quiz_id = ?");
$att_stmt->execute([$user_id, $quiz_id]);
$attempt_count = $att_stmt->fetchColumn() + 1;

// Fetch Reward Rules
$r_stmt = $pdo->prepare("SELECT reward_first_try, reward_second_try, reward_third_try, reward_fourth_try, course_id FROM quizzes WHERE id = ?");
$r_stmt->execute([$quiz_id]);
$quiz_data = $r_stmt->fetch();
$course_id = $quiz_data['course_id'];

// Determine Points
$points_earned = 0;
if ($score == $total_questions) { // Only award points if 100% correct? Or scaled? Assuming scaled or pass:
    // Let's award points if they pass > 50%
    if (($score / $total_questions) >= 0.5) {
        if ($attempt_count == 1) $points_earned = $quiz_data['reward_first_try'];
        elseif ($attempt_count == 2) $points_earned = $quiz_data['reward_second_try'];
        elseif ($attempt_count == 3) $points_earned = $quiz_data['reward_third_try'];
        else $points_earned = $quiz_data['reward_fourth_try'];
    }
}

// Insert Attempt
$save = $pdo->prepare("INSERT INTO quiz_attempts (user_id, quiz_id, attempt_number, score_earned) VALUES (?, ?, ?, ?)");
$save->execute([$user_id, $quiz_id, $attempt_count, $points_earned]);

// Update Student Profile (Total Points)
$prof_check = $pdo->prepare("SELECT user_id FROM student_profiles WHERE user_id = ?");
$prof_check->execute([$user_id]);
if (!$prof_check->fetch()) {
    $pdo->prepare("INSERT INTO student_profiles (user_id, total_points) VALUES (?, ?)")->execute([$user_id, $points_earned]);
} else {
    $pdo->prepare("UPDATE student_profiles SET total_points = total_points + ? WHERE user_id = ?")->execute([$points_earned, $user_id]);
}

// Redirect
header("Location: course_player.php?course_id=$course_id&quiz_id=$quiz_id&score=$score&total=$total_questions&points=$points_earned");
exit;
?>