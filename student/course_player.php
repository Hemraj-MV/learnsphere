<?php
// student/course_player.php
require '../includes/db.php';

// 1. Security Check
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$course_id = $_GET['course_id'] ?? 0;
$user_id = $_SESSION['user_id'];

// 2. Fetch Course Info
$course_stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$course_stmt->execute([$course_id]);
$course = $course_stmt->fetch();
if (!$course) die("Course not found.");

// 3. Fetch Lessons
$lessons_stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY id ASC");
$lessons_stmt->execute([$course_id]);
$lessons = $lessons_stmt->fetchAll();

// 4. Fetch Quizzes
$quizzes_stmt = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ?");
$quizzes_stmt->execute([$course_id]);
$quizzes = $quizzes_stmt->fetchAll();

// 5. Fetch User Progress (FIXED: Removed 'status' check and 'course_id' dependency)
// This query now joins the lessons table to filter by course, and assumes existence = progress.
$prog_sql = "
    SELECT DISTINCT lp.lesson_id 
    FROM lesson_progress lp
    JOIN lessons l ON lp.lesson_id = l.id
    WHERE lp.user_id = ? AND l.course_id = ?
";
$prog_stmt = $pdo->prepare($prog_sql);
$prog_stmt->execute([$user_id, $course_id]);
$completed_lessons = $prog_stmt->fetchAll(PDO::FETCH_COLUMN);

// 6. Determine Current Content (Lesson or Quiz)
$current_item = null;
$content_type = 'lesson'; // Default

if (isset($_GET['quiz_id'])) {
    $content_type = 'quiz';
    foreach ($quizzes as $q) {
        if ($q['id'] == $_GET['quiz_id']) {
            $current_item = $q;
            break;
        }
    }
} elseif (isset($_GET['lesson_id'])) {
    foreach ($lessons as $l) {
        if ($l['id'] == $_GET['lesson_id']) {
            $current_item = $l;
            break;
        }
    }
} else {
    // Default to first lesson
    if (count($lessons) > 0) $current_item = $lessons[0];
}

// 7. If Quiz selected, fetch Questions
$quiz_questions = [];
if ($content_type === 'quiz' && $current_item) {
    $q_stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ?");
    $q_stmt->execute([$current_item['id']]);
    $quiz_questions = $q_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($course['title']) ?> - LearnSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .lesson-list::-webkit-scrollbar { width: 8px; }
        .lesson-list::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
    </style>
</head>
<body class="bg-base-200 h-screen flex flex-col">

    <div class="navbar bg-base-100 shadow-md z-10">
        <div class="flex-1">
            <a href="dashboard.php" class="btn btn-ghost text-sm">← Back to Dashboard</a>
            <span class="font-bold text-lg ml-2 hidden md:inline"><?= htmlspecialchars($course['title']) ?></span>
        </div>
        <div class="flex-none gap-2">
            <button class="btn btn-square btn-ghost md:hidden" onclick="document.getElementById('sidebar').classList.toggle('hidden')">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-5 h-5 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
            <?php if($content_type === 'lesson'): ?>
                <button class="btn btn-primary btn-sm" onclick="document.getElementById('my_modal_ai').showModal()">🤖 Ask AI Tutor</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="flex flex-1 overflow-hidden relative">
        
        <div id="sidebar" class="w-80 bg-white border-r overflow-y-auto lesson-list absolute md:relative z-20 h-full hidden md:block">
            <div class="p-4 font-bold text-gray-500 uppercase text-xs tracking-wider border-b">Course Content</div>
            
            <ul class="menu w-full p-2">
                <?php foreach ($lessons as $index => $lesson): ?>
                    <?php $is_completed = in_array($lesson['id'], $completed_lessons); ?>
                    <li>
                        <a href="?course_id=<?= $course_id ?>&lesson_id=<?= $lesson['id'] ?>" 
                           class="<?= ($content_type == 'lesson' && isset($current_item['id']) && $current_item['id'] == $lesson['id']) ? 'active' : '' ?> flex justify-between">
                            <span>
                                <span class="badge badge-sm badge-ghost mr-1"><?= $index + 1 ?></span>
                                <?= htmlspecialchars($lesson['title']) ?>
                            </span>
                            <?php if($is_completed): ?>
                                <span class="text-success font-bold">✓</span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>

                <?php if (count($quizzes) > 0): ?>
                    <li class="menu-title mt-4 text-gray-500 uppercase text-xs">Assessments</li>
                    <?php foreach ($quizzes as $quiz): ?>
                        <li>
                            <a href="?course_id=<?= $course_id ?>&quiz_id=<?= $quiz['id'] ?>" 
                               class="<?= ($content_type == 'quiz' && isset($current_item['id']) && $current_item['id'] == $quiz['id']) ? 'active' : '' ?>">
                                📝 <?= htmlspecialchars($quiz['title']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="flex-1 flex flex-col overflow-y-auto p-6 w-full">
            <?php if ($current_item): ?>
                
                <h2 class="text-2xl font-bold mb-4"><?= htmlspecialchars($current_item['title']) ?></h2>

                <?php if ($content_type === 'lesson'): ?>
                    <div class="bg-black rounded-xl overflow-hidden shadow-2xl w-full aspect-video flex items-center justify-center relative mb-6">
                        <?php if ($current_item['type'] == 'video'): ?>
                            <?php 
                                $video_id = '';
                                // Regex to extract YouTube ID
                                if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $current_item['content_url'], $match)) {
                                    $video_id = $match[1];
                                }
                            ?>
                            <?php if($video_id): ?>
                                <iframe class="w-full h-full" src="https://www.youtube.com/embed/<?= $video_id ?>" frameborder="0" allowfullscreen></iframe>
                            <?php else: ?>
                                <div class="text-white p-4">Video content unavailable or invalid URL.</div>
                            <?php endif; ?>
                        <?php elseif ($current_item['type'] == 'document'): ?>
                            <iframe src="../<?= htmlspecialchars($current_item['content_url']) ?>" class="w-full h-full bg-white"></iframe>
                        <?php else: ?>
                            <div class="text-white">Text-only lesson</div>
                        <?php endif; ?>
                    </div>

                    <div class="flex justify-between items-center border-t pt-4">
                        <button class="btn btn-outline btn-sm" onclick="history.back()">Previous</button>
                        
                        <form method="POST" action="mark_complete.php">
                            <input type="hidden" name="course_id" value="<?= $course_id ?>">
                            <input type="hidden" name="lesson_id" value="<?= $current_item['id'] ?>">
                            <button type="submit" class="btn btn-primary">Mark as Complete & Next →</button>
                        </form>
                    </div>

                    <div class="mt-8 p-6 bg-white rounded-lg shadow">
                        <h3 class="font-bold text-lg mb-2">Lesson Notes</h3>
                        <p class="text-gray-600 leading-relaxed"><?= nl2br(htmlspecialchars($current_item['text_content'] ?? 'No notes available.')) ?></p>
                    </div>

                <?php elseif ($content_type === 'quiz'): ?>
                    <div class="card bg-base-100 shadow-xl border">
                        <div class="card-body">
                            <h3 class="card-title text-xl mb-4">Quiz Questions</h3>
                            
                            <form method="POST" action="submit_quiz.php"> 
                                <?php foreach($quiz_questions as $idx => $q): ?>
                                    <div class="mb-6">
                                        <p class="font-bold mb-2"><?= ($idx+1) . ". " . htmlspecialchars($q['question_text']) ?></p>
                                        <div class="form-control">
                                            <label class="label justify-start gap-3 cursor-pointer">
                                                <input type="radio" name="q<?= $q['id'] ?>" value="A" class="radio radio-primary radio-sm" />
                                                <span><?= htmlspecialchars($q['option_a']) ?></span>
                                            </label>
                                            <label class="label justify-start gap-3 cursor-pointer">
                                                <input type="radio" name="q<?= $q['id'] ?>" value="B" class="radio radio-primary radio-sm" />
                                                <span><?= htmlspecialchars($q['option_b']) ?></span>
                                            </label>
                                            <label class="label justify-start gap-3 cursor-pointer">
                                                <input type="radio" name="q<?= $q['id'] ?>" value="C" class="radio radio-primary radio-sm" />
                                                <span><?= htmlspecialchars($q['option_c']) ?></span>
                                            </label>
                                            <label class="label justify-start gap-3 cursor-pointer">
                                                <input type="radio" name="q<?= $q['id'] ?>" value="D" class="radio radio-primary radio-sm" />
                                                <span><?= htmlspecialchars($q['option_d']) ?></span>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="card-actions justify-end">
                                    <button type="button" class="btn btn-primary" onclick="alert('Quiz Submission logic to be implemented.')">Submit Quiz</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="hero h-full bg-base-200">
                    <div class="hero-content text-center">
                        <div class="max-w-md">
                            <h1 class="text-3xl font-bold">Welcome to the Course!</h1>
                            <p class="py-6">Select a lesson from the sidebar to begin learning.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <dialog id="my_modal_ai" class="modal">
        <div class="modal-box h-96 flex flex-col">
            <h3 class="font-bold text-lg">🤖 AI Tutor</h3>
            <p class="py-4 text-sm text-gray-500">Ask me anything about this lesson!</p>
            <div id="ai-chat-box" class="flex-1 overflow-y-auto border p-2 rounded bg-gray-50 mb-4">
                <div class="chat chat-start"><div class="chat-bubble bg-gray-200 text-black">Hello! Ask me anything.</div></div>
            </div>
            <div class="flex gap-2">
                <input id="ai-input" type="text" placeholder="Type question..." class="input input-bordered w-full" />
                <button id="ai-send-btn" class="btn btn-primary">Send</button>
            </div>
            <div class="modal-action">
                <form method="dialog"><button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button></form>
            </div>
        </div>
    </dialog>

    <script>
        // AI Logic
        const chatBox = document.getElementById('ai-chat-box');
        const inputField = document.getElementById('ai-input');
        const sendBtn = document.getElementById('ai-send-btn'); 
        const lessonContext = <?= json_encode($current_item['text_content'] ?? '') ?>;

        function addMessage(text, sender) {
            const div = document.createElement('div');
            div.className = sender === 'user' ? 'chat chat-end' : 'chat chat-start';
            div.innerHTML = `<div class="chat-bubble ${sender === 'user' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-black'}">${text}</div>`;
            chatBox.appendChild(div);
            chatBox.scrollTop = chatBox.scrollHeight; 
        }

        async function sendMessage() {
            const question = inputField.value.trim();
            if (!question) return;
            addMessage(question, 'user');
            inputField.value = '';

            try {
                // Point this to your actual AI backend endpoint
                const response = await fetch('../api/chat_logic.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ question: question, context: lessonContext })
                });
                if (!response.ok) throw new Error('API Error');
                const data = await response.json();
                addMessage(data.answer, 'ai');
            } catch (error) {
                console.error(error);
                addMessage("AI Offline (Check console).", 'ai');
            }
        }
        
        if(sendBtn) {
            sendBtn.addEventListener('click', (e) => { e.preventDefault(); sendMessage(); });
        }
        if(inputField) {
            inputField.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });
        }
    </script>
</body>
</html>