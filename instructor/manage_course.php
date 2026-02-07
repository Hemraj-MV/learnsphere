<?php
// instructor/manage_course.php
require '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'instructor')) {
    header("Location: ../login.php");
    exit;
}

$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['name'];

// Get ID (If null, we are in "Create New" mode)
$course_id = $_GET['id'] ?? null;

// --- HANDLE FORM SUBMISSION (Save/Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_details'])) {
    $title = trim($_POST['title']);
    $tags = trim($_POST['tags']);
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    $is_shared = isset($_POST['is_shared']) ? 1 : 0;

    if ($course_id) {
        // --- UPDATE EXISTING COURSE ---
        $sql = "UPDATE courses SET title = ?, tags = ?, is_published = ?, is_shared = ? WHERE id = ? AND instructor_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $tags, $is_published, $is_shared, $course_id, $instructor_id]);
        $success_msg = "Course saved successfully!";
    } else {
        // --- CREATE NEW COURSE ---
        if (!empty($title)) {
            $sql = "INSERT INTO courses (title, instructor_id, tags, is_published, is_shared, duration) VALUES (?, ?, ?, ?, ?, '00:00')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $instructor_id, $tags, $is_published, $is_shared]);
            
            // Get the new ID and redirect to Edit Mode
            $new_id = $pdo->lastInsertId();
            header("Location: manage_course.php?id=" . $new_id . "&new=1");
            exit;
        } else {
            $error_msg = "Please enter a course title to start.";
        }
    }
}

// --- FETCH DATA ---
if ($course_id) {
    // Edit Mode: Fetch existing data
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
    $stmt->execute([$course_id, $instructor_id]);
    $course = $stmt->fetch();

    if (!$course) { die("Course not found or access denied."); }

    // Fetch Lessons
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY position ASC");
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll();
} else {
    // Create Mode: Set Empty Defaults
    $course = [
        'id' => null,
        'title' => '',
        'tags' => '',
        'description' => '',
        'is_published' => 0,
        'is_shared' => 0
    ];
    $lessons = [];
}

$active_tab = $_GET['tab'] ?? 'content';
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?= $course_id ? 'Edit' : 'New' ?> Course - LearnSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .form-input {
            border-bottom-width: 2px;
            --tw-border-opacity: 1;
            border-color: rgb(226 232 240 / var(--tw-border-opacity));
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
            outline: 2px solid transparent;
            outline-offset: 2px;
            transition: all 0.2s;
        }
        .form-input:focus {
            border-color: #6366f1;
        }
        .tab-active {
            border-bottom: 2px solid #6366f1;
            color: #6366f1;
            font-weight: 600;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <header class="bg-white border-b border-gray-200 h-16 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 h-full flex items-center justify-between">
            <div class="flex items-center gap-12">
                <a href="dashboard.php" class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold text-sm">LS</div>
                    <span class="font-bold text-lg tracking-tight text-gray-900">LearnSphere</span>
                </a>
                <nav class="hidden md:flex gap-6">
                    <a href="dashboard.php" class="text-slate-500 font-medium hover:text-slate-900 py-5 transition">Courses</a>
                    <a href="reporting.php" class="text-slate-500 font-medium hover:text-slate-900 py-5 transition">Reporting</a>
                    <a href="#" class="text-slate-500 font-medium hover:text-slate-900 py-5 transition">Setting</a>
                </nav>
            </div>
            
            <div class="flex items-center gap-4">
                <form method="POST" id="top-actions-form" class="flex items-center gap-4">
                    <?php if($course_id): ?>
                        <input type="hidden" name="save_details" value="1">
                        <input type="hidden" name="title" value="<?= htmlspecialchars($course['title']) ?>">
                        <input type="hidden" name="tags" value="<?= htmlspecialchars($course['tags']) ?>">
                    <?php endif; ?>

                    <div class="flex items-center gap-2 bg-gray-100 p-2 rounded-lg border">
                        <label class="label cursor-pointer gap-2" title="<?= !$course_id ? 'Save course first' : '' ?>">
                            <span class="label-text text-xs font-semibold text-gray-600">Publish</span> 
                            <input type="checkbox" name="is_published" class="toggle toggle-success toggle-sm" 
                                   <?= $course['is_published'] ? 'checked' : '' ?> 
                                   <?= !$course_id ? 'disabled' : 'onchange="this.form.submit()"' ?> />
                        </label>
                        <div class="w-px h-6 bg-gray-300"></div>
                        <label class="label cursor-pointer gap-2" title="<?= !$course_id ? 'Save course first' : '' ?>">
                            <span class="label-text text-xs font-semibold text-gray-600">Share</span> 
                            <input type="checkbox" name="is_shared" class="toggle toggle-primary toggle-sm" 
                                   <?= $course['is_shared'] ? 'checked' : '' ?> 
                                   <?= !$course_id ? 'disabled' : 'onchange="this.form.submit()"' ?> />
                        </label>
                    </div>
                </form>
                
                <button class="btn btn-sm btn-outline gap-2 font-bold" <?= !$course_id ? 'disabled' : '' ?>>
                    <i data-lucide="eye" class="w-4 h-4"></i> Preview
                </button>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-5xl mx-auto w-full p-6 pb-24">
        
        <?php if(isset($success_msg)): ?>
            <div class="alert alert-success text-white shadow-sm mb-4">
                <i data-lucide="check-circle" class="w-5 h-5"></i>
                <span><?= $success_msg ?></span>
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['new'])): ?>
            <div class="alert alert-info text-white shadow-sm mb-4">
                <i data-lucide="info" class="w-5 h-5"></i>
                <span>Draft created! You can now add content below.</span>
            </div>
        <?php endif; ?>

        <div class="flex gap-3 mb-8">
            <a href="manage_course.php" class="btn btn-sm bg-blue-600 hover:bg-blue-700 text-white border-none shadow-sm gap-2">
                <i data-lucide="plus-circle" class="w-4 h-4"></i> New
            </a>

            <?php if($course_id): ?>
                <button class="btn btn-sm bg-white border-gray-300 text-gray-700 hover:bg-gray-50 shadow-sm gap-2">
                    <i data-lucide="mail" class="w-4 h-4"></i> Contact Attendees
                </button>
                <button class="btn btn-sm bg-white border-gray-300 text-gray-700 hover:bg-gray-50 shadow-sm gap-2">
                    <i data-lucide="user-plus" class="w-4 h-4"></i> Add Attendees
                </button>
            <?php endif; ?>
        </div>

        <form method="POST" class="bg-white p-8 rounded-2xl border border-gray-200 shadow-sm mb-8 flex gap-8">
            <input type="hidden" name="save_details" value="1">
            
            <div class="flex-1 space-y-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Course Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="<?= htmlspecialchars($course['title']) ?>" 
                           class="form-input w-full bg-transparent text-lg font-bold text-gray-900 placeholder-gray-300" 
                           placeholder="e.g. Mastering Python" required />
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Tags</label>
                    <input type="text" name="tags" value="<?= htmlspecialchars($course['tags']) ?>" 
                           class="form-input w-full bg-transparent text-sm text-gray-600" 
                           placeholder="e.g. marketing, sales" />
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Responsible</label>
                    <input type="text" value="<?= htmlspecialchars($instructor_name) ?>" disabled class="form-input w-full bg-gray-50 text-sm text-gray-500 cursor-not-allowed" />
                </div>
            </div>

            <div class="w-48 h-32 bg-gray-100 border-2 border-dashed border-gray-300 rounded-xl flex flex-col items-center justify-center text-gray-400 cursor-pointer hover:bg-gray-50 hover:border-gray-400 transition">
                <i data-lucide="image" class="w-8 h-8 mb-2"></i>
                <span class="text-xs font-medium">Course Image</span>
            </div>
            
            <button type="submit" class="hidden"></button> 
        </form>

        <?php if($course_id): ?>
            <div class="border-b border-gray-200 mb-6 flex gap-8">
                <a href="?id=<?= $course_id ?>&tab=content" class="pb-3 text-sm font-medium hover:text-gray-700 transition <?= $active_tab == 'content' ? 'tab-active' : 'text-gray-500' ?>">Content</a>
                <a href="?id=<?= $course_id ?>&tab=description" class="pb-3 text-sm font-medium hover:text-gray-700 transition <?= $active_tab == 'description' ? 'tab-active' : 'text-gray-500' ?>">Description</a>
                <a href="?id=<?= $course_id ?>&tab=options" class="pb-3 text-sm font-medium hover:text-gray-700 transition <?= $active_tab == 'options' ? 'tab-active' : 'text-gray-500' ?>">Options</a>
                <a href="?id=<?= $course_id ?>&tab=quiz" class="pb-3 text-sm font-medium hover:text-gray-700 transition <?= $active_tab == 'quiz' ? 'tab-active' : 'text-gray-500' ?>">Quiz</a>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm min-h-[300px]">
                <?php if ($active_tab == 'content'): ?>
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th scope="col" class="px-6 py-3">Content title</th>
                                <th scope="col" class="px-6 py-3">Category</th>
                                <th scope="col" class="px-6 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($lessons as $lesson): ?>
                                <tr class="bg-white border-b hover:bg-gray-50 transition">
                                    <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap flex items-center gap-3">
                                        <i data-lucide="grip-vertical" class="w-4 h-4 text-gray-300 cursor-move"></i>
                                        <?= htmlspecialchars($lesson['title']) ?>
                                    </th>
                                    <td class="px-6 py-4">
                                        <?php 
                                            $icons = ['video' => 'video', 'document' => 'file-text', 'quiz' => 'help-circle'];
                                            $colors = ['video' => 'text-blue-500', 'document' => 'text-orange-500', 'quiz' => 'text-purple-500'];
                                        ?>
                                        <span class="flex items-center gap-2 font-medium <?= $colors[$lesson['type']] ?>">
                                            <i data-lucide="<?= $icons[$lesson['type']] ?>" class="w-4 h-4"></i>
                                            <?= ucfirst($lesson['type']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="dropdown dropdown-end">
                                            <label tabindex="0" class="btn btn-ghost btn-sm btn-circle avatar">
                                                <i data-lucide="more-vertical" class="w-5 h-5 text-gray-500"></i>
                                            </label>
                                            <ul tabindex="0" class="mt-3 z-[1] p-2 shadow menu menu-sm dropdown-content bg-base-100 rounded-box w-32">
                                                <li><a><i data-lucide="edit-2" class="w-4 h-4"></i> Edit</a></li>
                                                <li><a class="text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Delete</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if(empty($lessons)): ?>
                                <tr><td colspan="3" class="px-6 py-8 text-center text-gray-400">No content added yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <button class="btn btn-primary btn-sm mt-6 gap-2 font-bold">
                        <i data-lucide="plus" class="w-4 h-4"></i> Add content
                    </button>

                <?php elseif ($active_tab == 'description'): ?>
                    <textarea class="textarea textarea-bordered w-full h-48" placeholder="Course description..."><?= htmlspecialchars($course['description']) ?></textarea>
                <?php else: ?>
                    <p class="text-gray-400 text-center py-10">Content for <?= ucfirst($active_tab) ?> tab is coming soon.</p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="text-center py-20 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                <i data-lucide="arrow-up-circle" class="w-12 h-12 text-blue-500 mx-auto mb-4 opacity-50"></i>
                <h3 class="text-lg font-bold text-gray-700">Start by naming your course</h3>
                <p class="text-gray-500">Enter a title above and press Enter or click away to create your course draft.</p>
            </div>
        <?php endif; ?>

    </main>

    <script>
        lucide.createIcons();
        // Auto-save form logic
        const form = document.querySelector('form.bg-white');
        const inputs = form.querySelectorAll('input[type="text"]');
        
        inputs.forEach(input => {
            input.addEventListener('blur', () => {
                // Only submit if Title is present (don't save empty drafts)
                if(form.querySelector('input[name="title"]').value.trim() !== "") {
                    form.submit();
                }
            });
            
            // Handle Enter key manually to ensure submission
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if(form.querySelector('input[name="title"]').value.trim() !== "") {
                        form.submit();
                    }
                }
            });
        });
    </script>
</body>
</html>