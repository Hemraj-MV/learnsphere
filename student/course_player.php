<?php
// student/course_player.php
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$course_id = $_GET['course_id'];
$user_id = $_SESSION['user_id'];

// 1. Fetch Course Info
$course_stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$course_stmt->execute([$course_id]);
$course = $course_stmt->fetch();

// 2. Fetch All Lessons
$lessons_stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY id ASC");
$lessons_stmt->execute([$course_id]);
$lessons = $lessons_stmt->fetchAll();

// 3. Determine Current Lesson (Default to first one)
$current_lesson = null;
if (isset($_GET['lesson_id'])) {
    foreach ($lessons as $l) {
        if ($l['id'] == $_GET['lesson_id']) {
            $current_lesson = $l;
            break;
        }
    }
} else {
    if (count($lessons) > 0) $current_lesson = $lessons[0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($course['title']) ?> - LearnSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom scrollbar for lesson list */
        .lesson-list::-webkit-scrollbar { width: 8px; }
        .lesson-list::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
    </style>
</head>
<body class="bg-base-200 h-screen flex flex-col">

    <div class="navbar bg-base-100 shadow-md z-10">
        <div class="flex-1">
            <a href="dashboard.php" class="btn btn-ghost text-sm">← Back to Dashboard</a>
            <span class="font-bold text-lg ml-2"><?= htmlspecialchars($course['title']) ?></span>
        </div>
        <div class="flex-none">
            <button class="btn btn-primary btn-sm" onclick="my_modal_ai.showModal()">🤖 Ask AI Tutor</button>
        </div>
    </div>

    <div class="flex flex-1 overflow-hidden">
        
        <div class="w-80 bg-white border-r overflow-y-auto lesson-list hidden md:block">
            <div class="p-4 font-bold text-gray-500 uppercase text-xs tracking-wider">Course Content</div>
            <ul class="menu w-full p-2 rounded-box">
                <?php foreach ($lessons as $index => $lesson): ?>
                    <li>
                        <a href="?course_id=<?= $course_id ?>&lesson_id=<?= $lesson['id'] ?>" 
                           class="<?= ($current_lesson && $current_lesson['id'] == $lesson['id']) ? 'active' : '' ?>">
                            <span class="badge badge-sm badge-ghost"><?= $index + 1 ?></span>
                            <?= htmlspecialchars($lesson['title']) ?>
                            <?php if($lesson['type'] == 'video'): ?>
                                <span class="text-xs">🎥</span>
                            <?php else: ?>
                                <span class="text-xs">📄</span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="flex-1 flex flex-col overflow-y-auto p-6">
            <?php if ($current_lesson): ?>
                
                <h2 class="text-2xl font-bold mb-4"><?= htmlspecialchars($current_lesson['title']) ?></h2>

                <div class="bg-black rounded-xl overflow-hidden shadow-2xl w-full aspect-video flex items-center justify-center relative">
                    
                    <?php if ($current_lesson['type'] == 'video'): ?>
                        <?php 
                            // Extract YouTube ID properly
                            // Supports: youtube.com/watch?v=ID and youtu.be/ID
                            $video_id = '';
                            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $current_lesson['content_url'], $match)) {
                                $video_id = $match[1];
                            }
                        ?>
                        <?php if($video_id): ?>
                            <iframe class="w-full h-full" src="https://www.youtube.com/embed/<?= $video_id ?>" frameborder="0" allowfullscreen></iframe>
                        <?php else: ?>
                            <div class="text-white">Invalid Video URL</div>
                        <?php endif; ?>

                    <?php elseif ($current_lesson['type'] == 'document'): ?>
                        <iframe src="../<?= htmlspecialchars($current_lesson['content_url']) ?>" class="w-full h-full bg-white"></iframe>
                    
                    <?php endif; ?>
                </div>

                <div class="mt-6 flex justify-between">
    <?php 
        $prev_lesson = null;
        $next_lesson = null;
        foreach($lessons as $i => $l) {
            if($l['id'] == $current_lesson['id']) {
                if(isset($lessons[$i-1])) $prev_lesson = $lessons[$i-1];
                if(isset($lessons[$i+1])) $next_lesson = $lessons[$i+1];
                break;
            }
        }
    ?>

    <?php if($prev_lesson): ?>
        <a href="?course_id=<?= $course_id ?>&lesson_id=<?= $prev_lesson['id'] ?>" class="btn btn-outline">← Previous</a>
    <?php else: ?>
        <button class="btn btn-outline btn-disabled">← Previous</button>
    <?php endif; ?>

    <form method="POST" action="mark_complete.php">
        <input type="hidden" name="course_id" value="<?= $course_id ?>">
        <input type="hidden" name="lesson_id" value="<?= $current_lesson['id'] ?>">
        <?php if($next_lesson): ?>
            <input type="hidden" name="next_id" value="<?= $next_lesson['id'] ?>">
        <?php endif; ?>
        
        <button type="submit" class="btn btn-primary">
            Mark Complete & Next →
        </button>
    </form>
</div>

                <div class="mt-8 p-6 bg-white rounded-lg shadow">
                    <h3 class="font-bold text-lg mb-2">Lesson Notes</h3>
                    <p class="text-gray-600"><?= nl2br(htmlspecialchars($current_lesson['text_content'])) ?></p>
                </div>

            <?php else: ?>
                <div class="text-center mt-20">
                    <h2 class="text-2xl font-bold">No lessons yet!</h2>
                    <p>The instructor hasn't added any content to this course.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <dialog id="my_modal_ai" class="modal">
        <div class="modal-box h-96 flex flex-col">
            <h3 class="font-bold text-lg">🤖 AI Tutor</h3>
            <p class="py-4 text-sm text-gray-500">Ask me anything about this lesson!</p>
            
            <div id="ai-chat-box" class="flex-1 overflow-y-auto border p-2 rounded bg-gray-50 mb-4">
                <div class="chat chat-start">
                    <div class="chat-bubble bg-gray-200 text-black">Hello! I've read the lesson notes. What are you confused about?</div>
                </div>
            </div>

            <div class="flex gap-2">
                <input id="ai-input" type="text" placeholder="Type your question..." class="input input-bordered w-full" />
                <button id="ai-send-btn" class="btn btn-primary">Send</button>
            </div>
            
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                </form>
            </div>
        </div>
    </dialog>

<script>
    // 1. Get Elements securely by ID
    const chatBox = document.getElementById('ai-chat-box');
    const inputField = document.getElementById('ai-input');
    const sendBtn = document.getElementById('ai-send-btn'); 
    const lessonContext = <?= json_encode($current_lesson['text_content'] ?? '') ?>;

    // 2. Function to Add Message to UI
    function addMessage(text, sender) {
        const div = document.createElement('div');
        div.className = sender === 'user' ? 'chat chat-end' : 'chat chat-start';
        
        div.innerHTML = `
            <div class="chat-bubble ${sender === 'user' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-black'}">
                ${text}
            </div>
        `;
        chatBox.appendChild(div);
        chatBox.scrollTop = chatBox.scrollHeight; 
    }

    // 3. Handle "Send" Click
    async function sendMessage() {
        const question = inputField.value.trim();
        if (!question) return;

        // A. Show User Message
        addMessage(question, 'user');
        inputField.value = '';

        // B. Show "Thinking..."
        const loadingId = 'loading-' + Date.now();
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'chat chat-start';
        loadingDiv.id = loadingId;
        loadingDiv.innerHTML = `<div class="chat-bubble bg-gray-100 text-gray-400">Thinking...</div>`;
        chatBox.appendChild(loadingDiv);
        chatBox.scrollTop = chatBox.scrollHeight;

        try {
            // C. Send to Backend (PHP)
            const response = await fetch('../api/chat_logic.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    question: question,
                    context: lessonContext 
                })
            });

            const data = await response.json();
            
            // D. Remove Loading & Show AI Response
            document.getElementById(loadingId).remove();
            addMessage(data.answer, 'ai');

        } catch (error) {
            if(document.getElementById(loadingId)) document.getElementById(loadingId).remove();
            addMessage("Sorry, I can't connect right now.", 'ai');
            console.error(error);
        }
    }

    // 4. Attach Event Listeners (Using the specific IDs)
    sendBtn.addEventListener('click', (e) => {
        e.preventDefault(); 
        sendMessage();
    });

    inputField.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });
</script>
</body>
</html>