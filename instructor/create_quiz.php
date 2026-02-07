<?php
// instructor/create_quiz.php
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../login.php");
    exit;
}

$course_id = $_GET['course_id'] ?? null;
if (!$course_id) die("Course ID missing.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    
    // Create the Quiz Entry
    $stmt = $pdo->prepare("INSERT INTO quizzes (course_id, title) VALUES (?, ?)");
    if ($stmt->execute([$course_id, $title])) {
        $quiz_id = $pdo->lastInsertId();
        // Redirect to add questions immediately
        header("Location: add_questions.php?quiz_id=" . $quiz_id);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Create Quiz</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200 min-h-screen flex items-center justify-center">

    <div class="card w-96 bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">Create New Quiz</h2>
            <form method="POST">
                <div class="form-control">
                    <label class="label"><span class="label-text">Quiz Title</span></label>
                    <input type="text" name="title" placeholder="e.g. Final Exam" class="input input-bordered" required />
                </div>
                <div class="card-actions justify-end mt-4">
                    <a href="manage_course.php?id=<?= $course_id ?>" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Next: Add Questions</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>