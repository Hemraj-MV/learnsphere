<?php
// instructor/quiz_editor.php
require '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. AUTHENTICATION
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'instructor')) {
    header("Location: ../login.php");
    exit;
}

$quiz_id = $_GET['quiz_id'] ?? null;
$course_id = $_GET['course_id'] ?? null;
$current_q_id = $_GET['q_id'] ?? null;

if (!$quiz_id || !$course_id) die("Missing Quiz ID");

// 2. FETCH DATA
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();

// Determine Question Number & Data
$current_q_number = count($questions) + 1;
$current_question = [
    'question_text' => '', 
    'option_a' => '', 'option_b' => '', 'option_c' => '', 'option_d' => '', 
    'correct_option' => 'A'
];

$initial_option_count = 2; 

if ($current_q_id) {
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$current_q_id]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        $current_question = $fetched;
        if(!empty($fetched['option_c'])) $initial_option_count = 3;
        if(!empty($fetched['option_d'])) $initial_option_count = 4;
        
        foreach($questions as $key => $q) {
            if($q['id'] == $current_q_id) { $current_q_number = $key + 1; break; }
        }
    }
}

$active_view = $_GET['view'] ?? 'questions';

// 3. POST HANDLERS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_question'])) {
        $q_text = trim($_POST['question_text']);
        $opts = $_POST['options'] ?? [];
        $opt_a = $opts[0] ?? ''; $opt_b = $opts[1] ?? ''; $opt_c = $opts[2] ?? ''; $opt_d = $opts[3] ?? '';
        $correct_idx = $_POST['correct_index'] ?? 0;
        $mapping = [0 => 'A', 1 => 'B', 2 => 'C', 3 => 'D'];
        $correct_char = $mapping[$correct_idx] ?? 'A';

        if (!empty($q_text)) {
            if ($current_q_id) {
                $sql = "UPDATE questions SET question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=? WHERE id=?";
                $pdo->prepare($sql)->execute([$q_text, $opt_a, $opt_b, $opt_c, $opt_d, $correct_char, $current_q_id]);
            } else {
                $sql = "INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$quiz_id, $q_text, $opt_a, $opt_b, $opt_c, $opt_d, $correct_char]);
            }
        }
        header("Location: quiz_editor.php?quiz_id=$quiz_id&course_id=$course_id&view=questions");
        exit;
    }

    if (isset($_POST['delete_question'])) {
        $del_id = $_POST['del_id'];
        $pdo->prepare("DELETE FROM questions WHERE id = ?")->execute([$del_id]);
        header("Location: quiz_editor.php?quiz_id=$quiz_id&course_id=$course_id&view=questions");
        exit;
    }

    if (isset($_POST['save_rewards'])) {
        $r1 = $_POST['reward_1']; $r2 = $_POST['reward_2']; $r3 = $_POST['reward_3']; $r4 = $_POST['reward_4'];
        $pdo->prepare("UPDATE quizzes SET reward_first_try=?, reward_second_try=?, reward_third_try=?, reward_fourth_try=? WHERE id=?")->execute([$r1, $r2, $r3, $r4, $quiz_id]);
        header("Location: quiz_editor.php?quiz_id=$quiz_id&course_id=$course_id&view=rewards&success=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Quiz Editor</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        
        /* HEADER */
        .editor-header { height: 64px; background: white; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; flex-shrink: 0; z-index: 50; }
        
        /* LAYOUT */
        .editor-body { display: flex; flex: 1; overflow: hidden; height: calc(100vh - 64px); }
        
        /* SIDEBAR */
        .sidebar { width: 300px; background: white; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; height: 100%; flex-shrink: 0; }
        .sidebar-title { padding: 24px; font-size: 0.85rem; color: #94a3b8; font-weight: 700; border-bottom: 1px solid #f1f5f9; }
        
        .q-list { flex: 1; overflow-y: auto; padding: 12px; }
        .q-item { padding: 12px 16px; margin-bottom: 4px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; color: #475569; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: 0.2s; }
        .q-item:hover { background: #f8fafc; }
        .q-item.active { background: #eef2ff; color: #4f46e5; border-left: 4px solid #4f46e5; }
        
        .sidebar-footer { padding: 20px; border-top: 1px solid #f1f5f9; background: white; margin-top: auto; }
        
        /* BUTTONS */
        .btn-sidebar { display: block; width: 100%; padding: 12px; text-align: center; border-radius: 10px; font-weight: 700; color: white; transition: 0.2s; border: none; cursor: pointer; margin-top: 8px; font-size: 0.9rem; }
        .btn-purple { background: #818cf8; }
        .btn-purple:hover { background: #6366f1; transform: translateY(-1px); }
        .btn-purple-dark { background: #7c3aed; }
        .btn-purple-dark:hover { background: #6d28d9; }

        /* MAIN AREA */
        .main-area { 
            flex: 1; 
            background: #f8fafc; 
            overflow-y: auto; 
            padding: 40px; 
            display: flex; 
            justify-content: center; 
            /* Huge padding at bottom to force scroll */
            padding-bottom: 300px; 
        }
        .canvas { width: 100%; max-width: 700px; }

        /* INPUTS */
        .input-underline { width: 100%; background: transparent; border: none; border-bottom: 2px solid #e2e8f0; font-weight: 600; color: #1e293b; padding: 8px 0; outline: none; transition: 0.3s; }
        .input-underline:focus { border-color: #6366f1; }
        .input-underline::placeholder { color: #cbd5e1; font-weight: 500; }

        /* QUESTION ROW */
        .q-row-header { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 40px; width: 100%; }
        .q-num { font-size: 1.5rem; font-weight: 800; color: #64748b; line-height: 1.5; padding-top: 4px; }
        .q-input-wrapper { flex: 1; }
        .q-input-lg { font-size: 1.25rem; font-weight: 700; color: #f97316; width: 100%; } 

        /* CHOICE ROW */
        .headers-row { display: grid; grid-template-columns: 1fr 60px; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #e2e8f0; }
        .col-header { font-size: 0.75rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
        .choice-row { display: grid; grid-template-columns: 1fr 60px; align-items: center; margin-bottom: 16px; animation: fadeIn 0.3s ease-in-out; }
        .choice-input-wrapper { margin-right: 20px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* CHECKBOX */
        .checkbox-wrapper { display: flex; justify-content: center; }
        .square-check { width: 20px; height: 20px; border: 2px solid #cbd5e1; border-radius: 4px; appearance: none; cursor: pointer; position: relative; transition: 0.2s; }
        .square-check:checked { background: #22c55e; border-color: #22c55e; }
        .square-check:checked::after { content: '✔'; position: absolute; color: white; font-size: 12px; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold; }

        /* ADD CHOICE */
        .add-choice-btn { color: #3b82f6; font-weight: 700; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; padding: 5px 0; user-select: none; }
        .add-choice-btn:hover { color: #2563eb; }
        .add-choice-btn.hidden { display: none; }

        /* REWARDS */
        .reward-group { margin-bottom: 24px; display: flex; align-items: center; gap: 20px; }
        .reward-label { width: 140px; font-weight: 700; color: #f59e0b; font-size: 1rem; }
        .reward-input-sm { width: 80px; text-align: center; font-size: 1.1rem; font-weight: 700; }
        
        /* SPACER */
        .spacer-box { height: 150px; width: 100%; }
    </style>
</head>
<body>

    <header class="editor-header">
        <div class="flex items-center gap-4">
            <a href="manage_course.php?id=<?= $course_id ?>&tab=quiz" class="btn btn-sm btn-ghost text-slate-400 hover:text-slate-700">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div class="h-6 w-px bg-slate-200"></div>
            <h1 class="text-lg font-bold text-slate-800"><?= htmlspecialchars($quiz['title']) ?></h1>
        </div>
        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest bg-slate-100 px-3 py-1 rounded-full">Editor Mode</div>
    </header>

    <div class="editor-body">
        
        <aside class="sidebar">
            <div class="sidebar-title">Question List</div>
            <div class="q-list">
                <?php if(empty($questions)): ?>
                    <div class="p-6 text-sm text-slate-400 italic text-center">No questions added.</div>
                <?php else: ?>
                    <?php foreach($questions as $index => $q): ?>
                        <div class="q-item group <?= ($current_q_id == $q['id']) ? 'active' : '' ?>" 
                             onclick="window.location.href='?quiz_id=<?= $quiz_id ?>&course_id=<?= $course_id ?>&q_id=<?= $q['id'] ?>&view=questions'">
                            <span>Question <?= $index + 1 ?></span>
                            <form method="POST" onsubmit="return confirm('Delete?'); event.stopPropagation();">
                                <input type="hidden" name="delete_question" value="1">
                                <input type="hidden" name="del_id" value="<?= $q['id'] ?>">
                                <button class="btn btn-ghost btn-xs btn-circle text-slate-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="sidebar-footer">
                <a href="?quiz_id=<?= $quiz_id ?>&course_id=<?= $course_id ?>&view=questions" class="btn-sidebar btn-purple">
                    <i data-lucide="plus" class="w-4 h-4 inline mr-1"></i> Add Question
                </a>
                <a href="?quiz_id=<?= $quiz_id ?>&course_id=<?= $course_id ?>&view=rewards" class="btn-sidebar btn-purple-dark">
                    <i data-lucide="award" class="w-4 h-4 inline mr-1"></i> Rewards
                </a>
            </div>
        </aside>

        <main class="main-area">
            <div class="canvas">
                
                <?php if($active_view === 'rewards'): ?>
                    <div class="mb-10">
                        <h2 class="text-3xl font-bold text-slate-900 mb-2">Rewards</h2>
                        <p class="text-slate-500">Configure points for user attempts.</p>
                    </div>

                    <?php if(isset($_GET['success'])): ?>
                        <div class="alert bg-emerald-50 text-emerald-600 border border-emerald-100 mb-6 font-bold rounded-xl text-sm flex gap-2">
                            <i data-lucide="check-circle" class="w-4 h-4"></i> Changes Saved
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="save_rewards" value="1">
                        
                        <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 mb-8">
                            <div class="reward-group">
                                <span class="reward-label text-emerald-600">First try :</span>
                                <input type="number" name="reward_1" value="<?= $quiz['reward_first_try'] ?? 10 ?>" class="input-underline reward-input-sm">
                                <span class="text-slate-400 font-bold text-sm">points</span>
                            </div>
                            <div class="reward-group">
                                <span class="reward-label text-blue-500">Second try :</span>
                                <input type="number" name="reward_2" value="<?= $quiz['reward_second_try'] ?? 7 ?>" class="input-underline reward-input-sm">
                                <span class="text-slate-400 font-bold text-sm">points</span>
                            </div>
                            <div class="reward-group">
                                <span class="reward-label text-orange-500">Third try :</span>
                                <input type="number" name="reward_3" value="<?= $quiz['reward_third_try'] ?? 5 ?>" class="input-underline reward-input-sm">
                                <span class="text-slate-400 font-bold text-sm">points</span>
                            </div>
                            <div class="reward-group mb-0">
                                <span class="reward-label text-slate-500">Fourth try+ :</span>
                                <input type="number" name="reward_4" value="<?= $quiz['reward_fourth_try'] ?? 2 ?>" class="input-underline reward-input-sm">
                                <span class="text-slate-400 font-bold text-sm">points</span>
                            </div>
                        </div>

                        <div class="text-right">
                            <button type="submit" class="btn-sidebar btn-purple w-40 inline-block shadow-xl shadow-indigo-200">Save</button>
                        </div>
                        
                        <div class="spacer-box"></div>
                    </form>

                <?php else: ?>
                    <form method="POST" id="questionForm">
                        <input type="hidden" name="save_question" value="1">
                        
                        <div class="q-row-header">
                            <div class="q-num"><?= $current_q_number ?>.</div>
                            <div class="q-input-wrapper">
                                <input type="text" name="question_text" class="input-underline q-input-lg" placeholder="Write your question here" value="<?= htmlspecialchars($current_question['question_text']) ?>" required autocomplete="off">
                            </div>
                        </div>

                        <div class="headers-row">
                            <span class="col-header pl-2">Choices</span>
                            <span class="col-header text-center">Correct</span>
                        </div>

                        <div id="choicesContainer">
                            <?php 
                                $opts = [
                                    $current_question['option_a'], $current_question['option_b'], 
                                    $current_question['option_c'], $current_question['option_d']
                                ];
                                $correct_char = $current_question['correct_option'];
                                $mapping = ['A', 'B', 'C', 'D'];
                                
                                // Render dynamic loop
                                for($i=0; $i<$initial_option_count; $i++): 
                                    $is_checked = ($mapping[$i] === $correct_char) ? 'checked' : '';
                            ?>
                                <div class="choice-row">
                                    <div class="choice-input-wrapper">
                                        <input type="text" name="options[]" class="input-underline" placeholder="Answer <?= $i + 1 ?>" value="<?= htmlspecialchars($opts[$i] ?? '') ?>" required>
                                    </div>
                                    <div class="checkbox-wrapper">
                                        <input type="radio" name="correct_index" value="<?= $i ?>" class="square-check" <?= $is_checked ?>>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div id="addChoiceBtn" onclick="addChoiceRow()" class="add-choice-btn <?= $initial_option_count >= 4 ? 'hidden' : '' ?>">
                            <i data-lucide="plus" class="w-4 h-4"></i> Add choice
                        </div>

                        <div class="mt-12 text-right">
                            <button type="submit" class="btn-sidebar btn-purple w-48 inline-block shadow-xl shadow-indigo-200">
                                <?= $current_q_id ? 'Update Question' : 'Save Question' ?>
                            </button>
                        </div>
                        
                        <div class="spacer-box"></div>
                    </form>
                <?php endif; ?>
                
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();

        function addChoiceRow() {
            const container = document.getElementById('choicesContainer');
            const btn = document.getElementById('addChoiceBtn');
            const currentCount = container.children.length;
            
            if (currentCount >= 4) return;

            const div = document.createElement('div');
            div.className = 'choice-row';
            div.innerHTML = `
                <div class="choice-input-wrapper">
                    <input type="text" name="options[]" class="input-underline" placeholder="Answer ${currentCount + 1}" required>
                </div>
                <div class="checkbox-wrapper">
                    <input type="radio" name="correct_index" value="${currentCount}" class="square-check">
                </div>
            `;
            container.appendChild(div);

            if (container.children.length >= 4) {
                btn.classList.add('hidden');
            }
        }
    </script>
</body>
</html>