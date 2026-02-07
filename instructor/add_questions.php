<?php
// instructor/add_questions.php
require '../includes/db.php';

if (!isset($_GET['quiz_id'])) die("Quiz ID missing.");
$quiz_id = $_GET['quiz_id'];

// 1. Add Question Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = $_POST['question'];
    $opt_a = $_POST['option_a'];
    $opt_b = $_POST['option_b'];
    $opt_c = $_POST['option_c'];
    $opt_d = $_POST['option_d'];
    $correct = $_POST['correct'];

    $sql = "INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $pdo->prepare($sql)->execute([$quiz_id, $question, $opt_a, $opt_b, $opt_c, $opt_d, $correct]);
    
    $success = "Question Added!";
}

// 2. Fetch Existing Questions to show list
$questions = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id DESC");
$questions->execute([$quiz_id]);
$all_questions = $questions->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add Questions</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200 p-10">

    <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title">Add Question</h2>
                <?php if(isset($success)) echo "<div class='badge badge-success'>$success</div>"; ?>
                
                <form method="POST">
                    <textarea name="question" class="textarea textarea-bordered w-full mb-2" placeholder="Question Text" required></textarea>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="option_a" placeholder="Option A" class="input input-bordered input-sm" required>
                        <input type="text" name="option_b" placeholder="Option B" class="input input-bordered input-sm" required>
                        <input type="text" name="option_c" placeholder="Option C" class="input input-bordered input-sm" required>
                        <input type="text" name="option_d" placeholder="Option D" class="input input-bordered input-sm" required>
                    </div>

                    <div class="form-control mt-4">
                        <label class="label"><span class="label-text">Correct Answer</span></label>
                        <select name="correct" class="select select-bordered w-full">
                            <option value="A">Option A</option>
                            <option value="B">Option B</option>
                            <option value="C">Option C</option>
                            <option value="D">Option D</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-full mt-4">Add Question</button>
                </form>
                
                <div class="divider"></div>
                <a href="dashboard.php" class="btn btn-outline w-full">Finish & Go to Dashboard</a>
            </div>
        </div>

        <div class="card bg-white shadow-xl p-6 h-fit">
            <h3 class="font-bold text-lg mb-4">Quiz Preview</h3>
            <?php foreach($all_questions as $index => $q): ?>
                <div class="mb-4 border-b pb-2">
                    <p class="font-bold">Q<?= $index+1 ?>: <?= htmlspecialchars($q['question_text']) ?></p>
                    <ul class="text-sm text-gray-600 ml-4 list-disc">
                        <li class="<?= $q['correct_option']=='A'?'text-green-600 font-bold':'' ?>">A: <?= htmlspecialchars($q['option_a']) ?></li>
                        <li class="<?= $q['correct_option']=='B'?'text-green-600 font-bold':'' ?>">B: <?= htmlspecialchars($q['option_b']) ?></li>
                        <li class="<?= $q['correct_option']=='C'?'text-green-600 font-bold':'' ?>">C: <?= htmlspecialchars($q['option_c']) ?></li>
                        <li class="<?= $q['correct_option']=='D'?'text-green-600 font-bold':'' ?>">D: <?= htmlspecialchars($q['option_d']) ?></li>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</body>
</html>