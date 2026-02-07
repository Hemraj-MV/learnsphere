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
$instructor_name = $_SESSION['name'] ?? 'Instructor';
$course_id = $_GET['id'] ?? null;

// --- 1. HANDLE COURSE DETAILS (Create/Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // SAVE MAIN DETAILS
    if (isset($_POST['save_details'])) {
        $title = trim($_POST['title']);
        $tags = trim($_POST['tags']);
        $description = $_POST['description'] ?? ''; // Preserve description if set
        $is_published = isset($_POST['is_published']) ? 1 : 0; 

        if ($course_id) {
            // Update Existing
            // Note: We use dynamic building or just update everything. 
            // Here we ensure description isn't wiped if we are just toggling publish from the header.
            if(isset($_POST['description'])) {
                 $sql = "UPDATE courses SET title=?, tags=?, description=?, is_published=? WHERE id=? AND instructor_id=?";
                 $params = [$title, $tags, $description, $is_published, $course_id, $instructor_id];
            } else {
                 // Header toggle only sends published status, don't wipe description
                 $sql = "UPDATE courses SET title=?, tags=?, is_published=? WHERE id=? AND instructor_id=?";
                 $params = [$title, $tags, $is_published, $course_id, $instructor_id];
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $success_msg = "Course updated successfully.";
        } else {
            // Create New
            if (!empty($title)) {
                $sql = "INSERT INTO courses (title, instructor_id, tags, description, is_published, duration, views) VALUES (?, ?, ?, ?, ?, '0h 0m', 0)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$title, $instructor_id, $tags, $description, $is_published]);
                $new_id = $pdo->lastInsertId();
                header("Location: manage_course.php?id=" . $new_id . "&new=1");
                exit;
            }
        }
    }

    // --- 2. MANAGE CONTENT (Add / Edit) ---
    if (isset($_POST['manage_lesson'])) {
        $l_id = $_POST['lesson_id'] ?? null;
        $l_title = trim($_POST['lesson_title']);
        $l_type = $_POST['lesson_type'];
        
        if ($l_id) {
            // EDIT EXISTING
            $stmt = $pdo->prepare("UPDATE lessons SET title=?, type=? WHERE id=? AND course_id=?");
            $stmt->execute([$l_title, $l_type, $l_id, $course_id]);
        } else {
            // ADD NEW
            $stmt = $pdo->prepare("SELECT MAX(position) FROM lessons WHERE course_id = ?");
            $stmt->execute([$course_id]);
            $pos = $stmt->fetchColumn() + 1;

            $stmt = $pdo->prepare("INSERT INTO lessons (course_id, title, type, position) VALUES (?, ?, ?, ?)");
            $stmt->execute([$course_id, $l_title, $l_type, $pos]);
        }
        // Redirect to content tab
        header("Location: manage_course.php?id=" . $course_id . "&tab=content");
        exit;
    }

    // --- 3. DELETE CONTENT ---
    if (isset($_POST['delete_lesson'])) {
        $l_id = $_POST['lesson_id'];
        $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ? AND course_id = ?");
        $stmt->execute([$l_id, $course_id]);
        header("Location: manage_course.php?id=" . $course_id . "&tab=content");
        exit;
    }
}

// --- FETCH DATA ---
if ($course_id) {
    // Edit Mode
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
    $stmt->execute([$course_id, $instructor_id]);
    $course = $stmt->fetch();
    if (!$course) { die("Access Denied"); }

    // Fetch Lessons
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY position ASC");
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll();
} else {
    // Create Mode (Empty Defaults)
    $course = ['id' => null, 'title' => '', 'tags' => '', 'description' => '', 'is_published' => 0];
    $lessons = [];
}

$active_tab = $_GET['tab'] ?? 'content';
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?= $course_id ? 'Studio' : 'New' ?> - LearnSphere</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f0f4ff 0%, #eef2f6 100%);
            color: #1e293b;
            min-height: 100vh;
        }
        h1, h2, h3, .heading-font { font-family: 'Outfit', sans-serif; letter-spacing: -0.02em; }

        .premium-header {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05);
        }

        .premium-card {
            background: #ffffff;
            border: 1px solid white;
            border-radius: 1.5rem;
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08); 
            transition: all 0.3s ease-out;
        }

        .form-input-premium {
            border-bottom: 2px solid #e2e8f0;
            background: transparent;
            padding: 0.75rem 0;
            font-weight: 600;
            color: #1e293b;
            transition: all 0.3s;
            width: 100%;
        }
        .form-input-premium:focus { border-color: #6366f1; outline: none; }

        .tab-link {
            padding-bottom: 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #94a3b8;
            border-bottom: 2px solid transparent;
            transition: 0.2s;
        }
        .tab-active { border-color: #6366f1; color: #6366f1; }

        .btn-premium-dark {
            background: #0f172a; color: white; border: none; font-weight: 700;
            text-transform: uppercase; font-size: 11px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.2);
        }
        .btn-premium-dark:hover { background: #334155; transform: translateY(-1px); }

        .image-placeholder {
            background: #f8fafc;
            border: 2px dashed #e2e8f0;
            border-radius: 1.25rem;
            transition: 0.3s;
            position: relative;
            overflow: hidden;
        }
        .image-placeholder:hover { border-color: #6366f1; background: #f1f5f9; }
    </style>
</head>
<body class="flex flex-col">

    <header class="premium-header h-20 sticky top-0 z-40 px-8">
        <div class="max-w-7xl mx-auto h-full flex items-center justify-between">
            <div class="flex items-center gap-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-black rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-lg">LS</div>
                    <span class="heading-font font-bold text-2xl text-slate-900 tracking-tight">LearnSphere</span>
                </div>
                <nav class="hidden md:flex gap-1">
                    <a href="dashboard.php" class="px-4 py-2 text-slate-900 font-bold bg-white rounded-full shadow-sm">Workbench</a>
                    <a href="reporting.php" class="px-4 py-2 text-slate-500 font-semibold hover:text-slate-900 transition">Reporting</a>
                </nav>
            </div>

            <div class="flex items-center gap-4">
                
                <form method="POST" class="flex items-center">
                    <?php if($course_id): ?>
                        <input type="hidden" name="save_details" value="1">
                        <input type="hidden" name="title" value="<?= htmlspecialchars($course['title']) ?>">
                        <input type="hidden" name="tags" value="<?= htmlspecialchars($course['tags']) ?>">
                        
                        <div class="flex items-center gap-3 bg-slate-50 px-4 py-2 rounded-2xl border border-slate-200">
                            <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Publish</span>
                            <input type="checkbox" name="is_published" class="toggle toggle-success toggle-sm" 
                                   <?= $course['is_published'] ? 'checked' : '' ?> 
                                   onchange="this.form.submit()" />
                        </div>
                    <?php endif; ?>
                </form>

                <a href="../course_details.php?id=<?= $course_id ?>" target="_blank" class="btn btn-sm btn-ghost gap-2 font-bold rounded-xl <?= !$course_id ? 'btn-disabled opacity-50' : '' ?>">
                    <i data-lucide="eye" class="w-4 h-4"></i> Preview
                </a>

                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar border-2 border-white shadow-sm">
                        <div class="w-10 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold text-xs">
                            <?= strtoupper(substr($instructor_name, 0, 1)) ?>
                        </div>
                    </div>
                    <ul tabindex="0" class="mt-3 p-2 shadow-2xl menu menu-sm dropdown-content bg-white border border-white rounded-2xl w-52 text-slate-700">
                        <li class="px-4 py-2"><span class="font-bold text-slate-900"><?= htmlspecialchars($instructor_name) ?></span></li>
                        <div class="divider my-0 opacity-50"></div>
                        <li><a href="../logout.php" class="text-red-500 font-bold hover:bg-red-50"><i data-lucide="log-out" class="w-4 h-4"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-6xl mx-auto w-full p-8 pb-32">
        
        <?php if(isset($success_msg)): ?>
            <div class="alert bg-emerald-500 text-white border-none rounded-2xl shadow-lg mb-6 flex items-center gap-3">
                <i data-lucide="check-circle" class="w-5 h-5"></i>
                <span class="font-bold"><?= $success_msg ?></span>
            </div>
        <?php endif; ?>

        <div class="mb-8">
            <a href="manage_course.php" class="btn btn-sm btn-premium-dark gap-2 px-6 rounded-xl">
                <i data-lucide="plus" class="w-4 h-4"></i> New Course
            </a>
        </div>

        <div class="premium-card p-10 mb-10">
            <form method="POST" id="main-course-form" class="flex flex-col md:flex-row gap-12">
                <input type="hidden" name="save_details" value="1">
                <input type="hidden" name="is_published" value="<?= $course['is_published'] ?>"> <div class="flex-1 space-y-8">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Master Title</label>
                        <input type="text" name="title" value="<?= htmlspecialchars($course['title']) ?>" 
                               class="form-input-premium text-2xl heading-font font-bold placeholder-slate-300" 
                               placeholder="e.g. Advanced AI Architecture" required />
                    </div>

                    <div class="grid grid-cols-2 gap-8">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Tags</label>
                            <input type="text" name="tags" value="<?= htmlspecialchars($course['tags']) ?>" 
                                   class="form-input-premium text-sm" placeholder="e.g. design, strategy" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Responsible</label>
                            <input type="text" value="<?= htmlspecialchars($instructor_name) ?>" disabled 
                                   class="form-input-premium text-sm opacity-50 cursor-not-allowed" />
                        </div>
                    </div>
                </div>

                <div class="w-full md:w-64">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4 text-center">Course Image</label>
                    <div class="image-placeholder aspect-video flex flex-col items-center justify-center text-slate-300 cursor-pointer group hover:text-indigo-500">
                        <i data-lucide="image" class="w-10 h-10 mb-2 transition-colors"></i>
                        <span class="text-[10px] font-bold uppercase tracking-tighter">Click to Upload</span>
                    </div>
                </div>
            </form>
        </div>

        <?php if($course_id): ?>
            <div class="flex gap-8 mb-6 px-4">
                <a href="?id=<?= $course_id ?>&tab=content" class="tab-link <?= $active_tab == 'content' ? 'tab-active' : '' ?>">Content</a>
                <a href="?id=<?= $course_id ?>&tab=description" class="tab-link <?= $active_tab == 'description' ? 'tab-active' : '' ?>">Description</a>
                <a href="?id=<?= $course_id ?>&tab=options" class="tab-link <?= $active_tab == 'options' ? 'tab-active' : '' ?>">Options</a>
                <a href="?id=<?= $course_id ?>&tab=quiz" class="tab-link <?= $active_tab == 'quiz' ? 'tab-active' : '' ?>">Quiz</a>
            </div>

            <div class="premium-card p-8 min-h-[400px]">
                
                <?php if ($active_tab == 'content'): ?>
                    <div class="flex items-center justify-between mb-8">
                        <h3 class="heading-font font-bold text-xl">Course Modules</h3>
                        <button onclick="openLessonModal()" class="btn btn-sm btn-premium-dark rounded-xl px-6 gap-2">
                            <i data-lucide="plus" class="w-4 h-4"></i> Add Content
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead class="bg-slate-50/50">
                                <tr class="text-[10px] uppercase font-black text-slate-400 tracking-widest border-none">
                                    <th class="py-4 pl-6">Content Title</th>
                                    <th>Category</th>
                                    <th class="text-right pr-6">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-600">
                                <?php foreach($lessons as $lesson): ?>
                                    <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition">
                                        <td class="py-5 pl-6 font-bold text-slate-900 flex items-center gap-4">
                                            <i data-lucide="grip-vertical" class="w-4 h-4 text-slate-300 cursor-move"></i>
                                            <?= htmlspecialchars($lesson['title']) ?>
                                        </td>
                                        <td>
                                            <span class="px-3 py-1 bg-slate-100 rounded-full text-[10px] font-bold uppercase tracking-tighter">
                                                <?= ucfirst($lesson['type']) ?>
                                            </span>
                                        </td>
                                        <td class="text-right pr-6">
                                            <div class="dropdown dropdown-end">
                                                <label tabindex="0" class="btn btn-ghost btn-xs btn-circle">
                                                    <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                                </label>
                                                <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-white rounded-box w-32 border border-gray-100">
                                                    <li>
                                                        <a onclick="openLessonModal('<?= $lesson['id'] ?>', '<?= addslashes($lesson['title']) ?>', '<?= $lesson['type'] ?>')">
                                                            <i data-lucide="edit-2" class="w-3 h-3"></i> Edit
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this content?');">
                                                            <input type="hidden" name="delete_lesson" value="1">
                                                            <input type="hidden" name="lesson_id" value="<?= $lesson['id'] ?>">
                                                            <button type="submit" class="text-red-600 hover:bg-red-50 flex items-center gap-2 w-full text-left py-2 px-4 rounded">
                                                                <i data-lucide="trash-2" class="w-3 h-3"></i> Delete
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if(empty($lessons)): ?>
                                    <tr><td colspan="3" class="py-20 text-center text-slate-400 font-medium italic">No content added yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($active_tab == 'description'): ?>
                    <form method="POST">
                        <input type="hidden" name="save_details" value="1">
                        <input type="hidden" name="title" value="<?= htmlspecialchars($course['title']) ?>">
                        <input type="hidden" name="tags" value="<?= htmlspecialchars($course['tags']) ?>">
                        <input type="hidden" name="is_published" value="<?= $course['is_published'] ?>">
                        
                        <div class="space-y-4">
                            <h3 class="heading-font font-bold text-xl mb-4">Course Narrative</h3>
                            <textarea name="description" class="textarea textarea-bordered w-full h-64 bg-slate-50/50 rounded-2xl p-6 font-medium text-slate-700 focus:outline-none focus:border-blue-500" 
                                      placeholder="Describe the learning outcomes..."><?= htmlspecialchars($course['description'] ?? '') ?></textarea>
                            <div class="text-right">
                                <button type="submit" class="btn btn-premium-dark px-10 rounded-xl">Save Description</button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center py-20 text-slate-300">
                        <i data-lucide="construction" class="w-12 h-12 mb-4 opacity-50"></i>
                        <p class="font-bold uppercase tracking-widest text-xs">Tab Under Development</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="premium-card py-32 flex flex-col items-center justify-center border-2 border-dashed border-slate-200 bg-white/50">
                <div class="w-20 h-20 bg-indigo-50 rounded-3xl flex items-center justify-center text-indigo-500 mb-6">
                    <i data-lucide="pen-tool" class="w-10 h-10"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Initialize Course</h3>
                <p class="text-slate-500 text-sm">Enter a title above to start building.</p>
            </div>
        <?php endif; ?>

    </main>

    <dialog id="lessonModal" class="modal">
        <div class="modal-box bg-white p-0 rounded-3xl max-w-md shadow-2xl overflow-hidden">
            <div class="px-8 py-6 bg-slate-50 flex justify-between items-center border-b border-gray-100">
                <h3 class="heading-font font-bold text-xl text-slate-900" id="modalTitle">Add Content</h3>
                <form method="dialog"><button class="text-slate-400 hover:text-red-500"><i data-lucide="x" class="w-6 h-6"></i></button></form>
            </div>
            <div class="p-8">
                <form method="POST">
                    <input type="hidden" name="manage_lesson" value="1">
                    <input type="hidden" name="lesson_id" id="lessonIdInput" value="">
                    
                    <div class="space-y-6">
                        <div class="form-control">
                            <label class="label"><span class="label-text font-bold text-slate-400 uppercase text-[10px] tracking-widest">Content Title</span></label>
                            <input type="text" name="lesson_title" id="lessonTitleInput" class="form-input-premium w-full" required>
                        </div>
                        <div class="form-control">
                            <label class="label"><span class="label-text font-bold text-slate-400 uppercase text-[10px] tracking-widest">Type</span></label>
                            <select name="lesson_type" id="lessonTypeInput" class="select select-bordered w-full bg-slate-50 font-bold">
                                <option value="video">Video</option>
                                <option value="document">Document</option>
                                <option value="quiz">Quiz</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-premium-dark w-full py-4 rounded-xl mt-4">
                            Save Content
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop bg-slate-900/40 backdrop-blur-sm"><button>close</button></form>
    </dialog>

    <script>
        lucide.createIcons();

        // OPEN MODAL LOGIC (ADD or EDIT)
        function openLessonModal(id = '', title = '', type = 'video') {
            document.getElementById('lessonIdInput').value = id;
            document.getElementById('lessonTitleInput').value = title;
            document.getElementById('lessonTypeInput').value = type;
            document.getElementById('modalTitle').innerText = id ? 'Edit Content' : 'Add Content';
            document.getElementById('lessonModal').showModal();
        }

        // AUTO-SAVE FOR MAIN FORM
        const inputs = document.querySelectorAll('#main-course-form input[type="text"]');
        inputs.forEach(input => {
            input.addEventListener('blur', () => {
                if(document.querySelector('input[name="title"]').value.trim() !== "") {
                    document.getElementById('main-course-form').submit();
                }
            });
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if(document.querySelector('input[name="title"]').value.trim() !== "") {
                        document.getElementById('main-course-form').submit();
                    }
                }
            });
        });
    </script>
</body>
</html>