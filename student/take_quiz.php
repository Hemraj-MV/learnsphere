<?php
// student/take_quiz.php
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Quiz ID missing.");
}

$quiz_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// 1. Fetch Quiz Info
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

if (!$quiz) die("Quiz not found.");

// 2. Fetch Questions
$q_stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ?");
$q_stmt->execute([$quiz_id]);
$questions = $q_stmt->fetchAll();

// 3. Handle Submission
$result_view = false;
$score = 0;
$total_questions = count($questions);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result_view = true;
    foreach ($questions as $q) {
        $user_answer = $_POST['q_' . $q['id']] ?? '';
        if ($user_answer === $q['correct_option']) {
            $score++;
        }
    }
    
    // Optional: Save score to database (quiz_results table)
    // For now, we just show it.
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Take Quiz - <?= htmlspecialchars($quiz['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200 min-h-screen p-6">

    <div class="max-w-3xl mx-auto">
        <a href="course_player.php?course_id=<?= $quiz['course_id'] ?>" class="btn btn-ghost mb-4">← Back to Course</a>

        <?php if ($result_view): ?>
            <div class="card bg-base-100 shadow-xl text-center p-10">
                <h1 class="text-4xl font-bold mb-4">Quiz Results</h1>
                <div class="radial-progress text-primary mx-auto mb-4" style="--value:<?= ($score/$total_questions)*100 ?>; --size:12rem;">
                    <?= round(($score/$total_questions)*100) ?>%
                </div>
                <p class="text-2xl">You scored <b><?= $score ?></b> out of <b><?= $total_questions ?></b></p>
                
                <div class="mt-8">
                    <?php if (($score/$total_questions) >= 0.5): ?>
                        <div class="alert alert-success text-white">🎉 Congratulations! You passed!</div>
                    <?php else: ?>
                        <div class="alert alert-error text-white">❌ Needs Improvement. Try again!</div>
                    <?php endif; ?>
                </div>
                
                <a href="course_player.php?course_id=<?= $quiz['course_id'] ?>" class="btn btn-primary mt-6">Return to Course</a>
            </div>

        <?php else: ?>
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h1 class="text-3xl font-bold mb-6"><?= htmlspecialchars($quiz['title']) ?></h1>
                    
                    <form method="POST">
                        <?php foreach ($questions as $index => $q): ?>
                            <div class="mb-8 border-b pb-6">
                                <h3 class="font-bold text-lg mb-4">
                                    <?= $index + 1 ?>. <?= htmlspecialchars($q['question_text']) ?>
                                </h3>
                                
                                <div class="form-control">
                                    <label class="label cursor-pointer justify-start gap-4">
                                        <input type="radio" name="q_<?= $q['id'] ?>" value="A" class="radio radio-primary" required />
                                        <span class="label-text"><?= htmlspecialchars($q['option_a']) ?></span>
                                    </label>
                                </div>
                                <div class="form-control">
                                    <label class="label cursor-pointer justify-start gap-4">
                                        <input type="radio" name="q_<?= $q['id'] ?>" value="B" class="radio radio-primary" />
                                        <span class="label-text"><?= htmlspecialchars($q['option_b']) ?></span>
                                    </label>
                                </div>
                                <div class="form-control">
                                    <label class="label cursor-pointer justify-start gap-4">
                                        <input type="radio" name="q_<?= $q['id'] ?>" value="C" class="radio radio-primary" />
                                        <span class="label-text"><?= htmlspecialchars($q['option_c']) ?></span>
                                    </label>
                                </div>
                                <div class="form-control">
                                    <label class="label cursor-pointer justify-start gap-4">
                                        <input type="radio" name="q_<?= $q['id'] ?>" value="D" class="radio radio-primary" />
                                        <span class="label-text"><?= htmlspecialchars($q['option_d']) ?></span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="card-actions justify-end">
                            <button type="submit" class="btn btn-primary btn-wide">Submit Quiz</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>