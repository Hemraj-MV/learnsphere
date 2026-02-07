<?php
// instructor/manage_quiz.php
require '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'instructor')) {
    header("Location: ../login.php");
    exit;
}

$lesson_id = $_GET['lesson_id'] ?? null;
if (!$lesson_id) die("Invalid Quiz Lesson.");

// 1. FETCH QUIZ INFO
$stmt = $pdo->prepare("SELECT q.id as quiz_id, q.title, l.course_id FROM quizzes q JOIN lessons l ON q.lesson_id = l.id WHERE l.id = ?");
$stmt->execute([$lesson_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    // Auto-fix: Create quiz entry if missing
    $stmt = $pdo->prepare("SELECT course_id, title FROM lessons WHERE id = ?");
    $stmt->execute([$lesson_id]);
    $l = $stmt->fetch();
    $stmt = $pdo->prepare("INSERT INTO quizzes (course_id, lesson_id, title) VALUES (?, ?, ?)");
    $stmt->execute([$l['course_id'], $lesson_id, $l['title']]);
    header("Refresh:0");
    exit;
}

$quiz_id = $quiz['quiz_id'];
$course_id = $quiz['course_id'];

// 2. HANDLE ADD/EDIT QUESTION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_question'])) {
        $q_text = $_POST['question_text'];
        $op_a = $_POST['option_a'];
        $op_b = $_POST['option_b'];
        $op_c = $_POST['option_c'];
        $op_d = $_POST['option_d'];
        $correct = $_POST['correct_option'];
        
        $stmt = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$quiz_id, $q_text, $op_a, $op_b, $op_c, $op_d, $correct]);
        header("Location: manage_quiz.php?lesson_id=" . $lesson_id);
        exit;
    }
    
    if (isset($_POST['delete_question'])) {
        $q_id = $_POST['question_id'];
        $pdo->prepare("DELETE FROM questions WHERE id = ?")->execute([$q_id]);
        header("Location: manage_quiz.php?lesson_id=" . $lesson_id);
        exit;
    }
}

// 3. FETCH QUESTIONS
$stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ?");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Edit Quiz - <?= htmlspecialchars($quiz['title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: #1e293b; }
        h1, h2, h3, .heading-font { font-family: 'Outfit', sans-serif; letter-spacing: -0.02em; }
        .btn-primary-custom { background-color: #4f46e5; color: white; border: none; }
        .btn-primary-custom:hover { background-color: #4338ca; }
        .modal-box-custom { 
            border-radius: 1rem; 
            max-height: 85vh; /* Limit height */
            display: flex; 
            flex-direction: column; 
        }
        .modal-content-scroll { 
            overflow-y: auto; /* Enable scrolling */
            flex: 1; 
            padding: 2rem; 
        }
    </style>
</head>
<body class="min-h-screen p-8 flex flex-col items-center">

    <div class="w-full max-w-4xl">
        <div class="mb-6">
            <a href="manage_course.php?id=<?= $course_id ?>&tab=quiz" class="btn btn-ghost btn-sm gap-2 text-slate-500 hover:text-indigo-600 pl-0">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Course Studio
            </a>
        </div>
        
        <div class="flex justify-between items-end mb-8 pb-6 border-b border-slate-200">
            <div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1 block">Quiz Editor</span>
                <h1 class="text-3xl font-extrabold text-slate-900 heading-font"><?= htmlspecialchars($quiz['title']) ?></h1>
            </div>
            <button onclick="document.getElementById('questionModal').showModal()" class="btn btn-primary-custom gap-2 shadow-lg shadow-indigo-200">
                <i data-lucide="plus-circle" class="w-5 h-5"></i> Add Question
            </button>
        </div>

        <div class="space-y-6">
            <?php if(empty($questions)): ?>
                <div class="py-24 text-center bg-white rounded-2xl border-2 border-dashed border-slate-200">
                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-400">
                        <i data-lucide="help-circle" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-700">No questions yet</h3>
                    <p class="text-slate-500 text-sm">Start building your quiz by adding a question.</p>
                </div>
            <?php else: ?>
                <?php foreach($questions as $index => $q): ?>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition relative group">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="font-bold text-lg text-slate-800 flex gap-3">
                                <span class="text-slate-300">Q<?= $index + 1 ?>.</span> <?= htmlspecialchars($q['question_text']) ?>
                            </h3>
                            <div class="dropdown dropdown-end">
                                <label tabindex="0" class="btn btn-ghost btn-xs btn-circle text-slate-400 hover:text-slate-600">
                                    <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                </label>
                                <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow-lg bg-white rounded-xl w-32 border border-slate-100">
                                    <li>
                                        <form method="POST" onsubmit="return confirm('Delete this question?');" class="p-0">
                                            <input type="hidden" name="delete_question" value="1">
                                            <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                            <button type="submit" class="text-red-500 hover:bg-red-50 w-full text-left px-4 py-2 flex items-center gap-2 rounded-lg">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div class="p-3 rounded-lg border <?= $q['correct_option'] == 'A' ? 'bg-green-50 border-green-200 text-green-800 font-bold' : 'bg-slate-50 border-slate-100 text-slate-600' ?>">
                                <span class="opacity-50 mr-2">A)</span> <?= htmlspecialchars($q['option_a']) ?>
                            </div>
                            <div class="p-3 rounded-lg border <?= $q['correct_option'] == 'B' ? 'bg-green-50 border-green-200 text-green-800 font-bold' : 'bg-slate-50 border-slate-100 text-slate-600' ?>">
                                <span class="opacity-50 mr-2">B)</span> <?= htmlspecialchars($q['option_b']) ?>
                            </div>
                            <div class="p-3 rounded-lg border <?= $q['correct_option'] == 'C' ? 'bg-green-50 border-green-200 text-green-800 font-bold' : 'bg-slate-50 border-slate-100 text-slate-600' ?>">
                                <span class="opacity-50 mr-2">C)</span> <?= htmlspecialchars($q['option_c']) ?>
                            </div>
                            <div class="p-3 rounded-lg border <?= $q['correct_option'] == 'D' ? 'bg-green-50 border-green-200 text-green-800 font-bold' : 'bg-slate-50 border-slate-100 text-slate-600' ?>">
                                <span class="opacity-50 mr-2">D)</span> <?= htmlspecialchars($q['option_d']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <dialog id="questionModal" class="modal">
        <div class="modal-box modal-box-custom max-w-2xl bg-white p-0 overflow-hidden">
            <div class="px-8 py-5 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center sticky top-0 z-10">
                <h3 class="font-bold text-lg text-slate-800 heading-font">New Question</h3>
                <form method="dialog">
                    <button class="btn btn-sm btn-circle btn-ghost text-slate-400 hover:bg-slate-100">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </form>
            </div>

            <div class="modal-content-scroll">
                <form method="POST">
                    <input type="hidden" name="save_question" value="1">
                    
                    <div class="mb-6">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Question Text</label>
                        <input type="text" name="question_text" class="input input-bordered w-full focus:outline-none focus:border-indigo-500 font-medium" placeholder="e.g. What does HTML stand for?" required>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Option A</label>
                            <input type="text" name="option_a" class="input input-bordered w-full input-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Option B</label>
                            <input type="text" name="option_b" class="input input-bordered w-full input-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Option C</label>
                            <input type="text" name="option_c" class="input input-bordered w-full input-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Option D</label>
                            <input type="text" name="option_d" class="input input-bordered w-full input-sm" required>
                        </div>
                    </div>

                    <div class="mb-8">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Correct Answer</label>
                        <select name="correct_option" class="select select-bordered w-full bg-slate-50 font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                            <option value="A">Option A</option>
                            <option value="B">Option B</option>
                            <option value="C">Option C</option>
                            <option value="D">Option D</option>
                        </select>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-slate-100 gap-3">
                        <form method="dialog"><button class="btn btn-ghost text-slate-500 hover:bg-slate-100">Cancel</button></form>
                        <button type="submit" class="btn btn-primary-custom px-6 shadow-md">Save Question</button>
                    </div>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop bg-slate-900/40 backdrop-blur-sm"><button>close</button></form>
    </dialog>

    <script>lucide.createIcons();</script>
</body>
</html>