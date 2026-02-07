<?php
// instructor/dashboard.php
require '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'instructor')) {
    header("Location: ../login.php");
    exit;
}

$instructor_id = $_SESSION['user_id'];
$search = $_GET['search'] ?? '';

// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Create Course
    if (isset($_POST['create_course'])) {
        $title = trim($_POST['title']);
        $sql = "INSERT INTO courses (title, instructor_id, is_published, tags, duration) VALUES (?, ?, 0, '', '00:00')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $instructor_id]);
        header("Location: dashboard.php");
        exit;
    }

    // 2. Manage Tags (Hidden Form Logic)
    if (isset($_POST['manage_tag'])) {
        $course_id = $_POST['course_id'];
        $tag_name = trim($_POST['tag_name']);
        $action = $_POST['action'];

        $stmt = $pdo->prepare("SELECT tags FROM courses WHERE id = ? AND instructor_id = ?");
        $stmt->execute([$course_id, $instructor_id]);
        $current = $stmt->fetchColumn();
        
        $tags_array = $current ? explode(',', $current) : [];
        $tags_array = array_map('trim', $tags_array);

        if ($action === 'add' && !empty($tag_name)) {
            if (!in_array($tag_name, $tags_array)) $tags_array[] = $tag_name;
        } elseif ($action === 'remove') {
            $tags_array = array_diff($tags_array, [$tag_name]);
        }

        $new_tags = implode(',', $tags_array);
        $update = $pdo->prepare("UPDATE courses SET tags = ? WHERE id = ?");
        $update->execute([$new_tags, $course_id]);
        header("Location: dashboard.php");
        exit;
    }
}

// Fetch Courses with Search Filter
$sql = "SELECT c.*, 
       (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as content_count 
       FROM courses c 
       WHERE c.instructor_id = ? AND c.title LIKE ?
       ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$instructor_id, "%$search%"]);
$courses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Instructor Dashboard - LearnSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        
        /* RIBBON STYLING */
        .ribbon-wrapper {
            width: 85px;
            height: 85px;
            overflow: hidden;
            position: absolute;
            top: 0;
            right: 0;
            pointer-events: none;
            z-index: 20;
        }
        .ribbon {
            font-size: 10px;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            transform: rotate(45deg);
            position: absolute;
            padding: 5px 0;
            width: 120px;
            top: 15px;
            right: -35px;
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .ribbon.published { background: #10b981; }
        .ribbon.draft { background: #64748b; }

        /* TAGS */
        .tag {
            background: #f1f5f9;
            color: #475569;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .tag-remove { cursor: pointer; color: #94a3b8; }
        .tag-remove:hover { color: #ef4444; }

        /* VIEW TOGGLE */
        .view-toggle {
            display: flex;
            background: #f1f5f9;
            padding: 2px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .view-btn {
            padding: 6px;
            border-radius: 4px;
            color: #64748b;
            transition: all 0.2s;
            cursor: pointer;
        }
        .view-btn.active {
            background: white;
            color: #0f172a;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        /* FAB */
        .fab {
            width: 60px;
            height: 60px;
            background-color: #7c3aed;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
            transition: transform 0.2s;
            position: fixed;
            bottom: 40px;
            left: 40px;
            border: none;
            cursor: pointer;
            z-index: 50;
        }
        .fab:hover { transform: translateY(-2px); background-color: #6d28d9; }
        
        /* GRID VIEW STYLES */
        #courseContainer.grid-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        #courseContainer.grid-view .course-card {
            flex-direction: column;
            height: auto;
            min-height: 320px;
        }
        #courseContainer.grid-view .course-left {
            border-right: none;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 1rem;
        }
        #courseContainer.grid-view .course-center {
            width: 100%;
            border-right: none;
            border-bottom: 1px solid #f1f5f9;
            padding: 1rem;
        }
        #courseContainer.grid-view .course-right {
            width: 100%;
            padding: 1rem;
            flex-direction: row;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <header class="bg-white border-b border-gray-200 h-16 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 h-full flex items-center justify-between">
            <div class="flex items-center gap-12">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold text-sm">LS</div>
                    <span class="font-bold text-lg tracking-tight text-gray-900">LearnSphere</span>
                </div>
                <nav class="hidden md:flex gap-6">
                    <a href="#" class="text-slate-900 font-semibold border-b-2 border-slate-900 py-5">Courses</a>
                    <a href="reporting.php" class="text-slate-500 font-medium hover:text-slate-900 py-5 transition">Reporting</a>
                    <a href="#" class="text-slate-500 font-medium hover:text-slate-900 py-5 transition">Setting</a>
                </nav>
            </div>

            <div class="flex-1 max-w-md mx-6">
                <form method="GET" action="" class="relative group">
                    <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-gray-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search courses..." 
                           class="w-full bg-gray-100 border-transparent focus:bg-white focus:border-blue-500 rounded-lg pl-10 py-2 text-sm outline-none border">
                </form>
            </div>

            <div>
                <div class="view-toggle">
                    <button id="btnList" class="view-btn active" onclick="toggleView('list')"><i data-lucide="list" class="w-4 h-4"></i></button>
                    <button id="btnGrid" class="view-btn" onclick="toggleView('grid')"><i data-lucide="layout-grid" class="w-4 h-4"></i></button>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-6xl mx-auto w-full p-6 pb-24">
        
        <div id="courseContainer" class="space-y-4">
            <?php foreach($courses as $course): ?>
                <div class="course-card bg-white rounded-xl border border-gray-200 shadow-sm relative overflow-hidden flex flex-col md:flex-row h-auto md:h-32 hover:border-gray-300 transition-colors">
                    
                    <div class="ribbon-wrapper">
                        <div class="ribbon <?= $course['is_published'] ? 'published' : 'draft' ?>">
                            <?= $course['is_published'] ? 'Published' : 'Draft' ?>
                        </div>
                    </div>

                    <div class="course-left p-5 flex-1 flex flex-col justify-center border-b md:border-b-0 md:border-r border-gray-100">
                        <h3 class="text-lg font-bold text-blue-600 mb-3 truncate pr-8">
                            <?= htmlspecialchars($course['title']) ?>
                        </h3>
                        <div class="flex flex-wrap items-center gap-2">
                            <?php 
                                $tags = array_filter(explode(',', $course['tags']));
                                foreach($tags as $tag): 
                                    $tag = trim($tag);
                                    if(empty($tag)) continue;
                            ?>
                                <span class="tag">
                                    <?= $tag ?> 
                                    <i data-lucide="x" class="w-3 h-3 tag-remove" 
                                       onclick="modifyTag(<?= $course['id'] ?>, 'remove', '<?= $tag ?>')"></i>
                                </span>
                            <?php endforeach; ?>
                            <input type="text" placeholder="Add tag..." 
                                   class="text-xs bg-transparent border-none focus:ring-0 text-gray-500 placeholder-gray-400 p-0 w-24"
                                   onkeydown="if(event.key === 'Enter') { modifyTag(<?= $course['id'] ?>, 'add', this.value); }">
                        </div>
                    </div>

                    <div class="course-center w-full md:w-64 p-4 flex flex-col justify-center border-b md:border-b-0 md:border-r border-gray-100 bg-gray-50/50">
                        <div class="grid grid-cols-2 gap-y-2 text-xs">
                            <span class="text-gray-500 font-medium">Views</span>
                            <span class="font-bold text-orange-600"><?= $course['views'] ?></span>
                            <span class="text-gray-500 font-medium">Contents</span>
                            <span class="font-bold text-orange-600"><?= $course['content_count'] ?></span>
                            <span class="text-gray-500 font-medium">Duration</span>
                            <span class="font-bold text-orange-600"><?= $course['duration'] ?></span>
                        </div>
                    </div>

                    <div class="course-right w-full md:w-40 p-4 flex flex-col gap-2 justify-center items-center z-10 bg-white">
                        <button onclick="shareCourse(<?= $course['id'] ?>)" class="w-full py-1.5 px-3 rounded border border-gray-300 text-gray-700 text-xs font-medium hover:bg-gray-50 bg-white shadow-sm">
                            Share
                        </button>
                        <a href="manage_course.php?id=<?= $course['id'] ?>" class="w-full text-center py-1.5 px-3 rounded border border-gray-300 text-gray-700 text-xs font-medium hover:bg-gray-50 bg-white shadow-sm">
                            Edit
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if(empty($courses)): ?>
                <div class="text-center py-20">
                    <p class="text-gray-400">No courses found matching "<?= htmlspecialchars($search) ?>"</p>
                    <a href="dashboard.php" class="text-blue-500 hover:underline">Clear Search</a>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <button onclick="document.getElementById('createModal').showModal()" class="fab"><i data-lucide="plus" class="w-8 h-8"></i></button>

    <dialog id="createModal" class="modal">
        <div class="modal-box bg-[#1e1e2d] text-white p-0 rounded-xl max-w-md overflow-hidden shadow-2xl">
            <div class="px-6 py-4 flex justify-between items-center border-b border-gray-700">
                <h3 class="font-bold text-lg">Create Course</h3>
                <form method="dialog"><button class="text-gray-400 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button></form>
            </div>
            <div class="p-8">
                <form method="POST">
                    <label class="block text-sm text-gray-400 mb-2">Course Name</label>
                    <input type="text" name="title" placeholder="e.g. Basics of Odoo CRM" class="w-full bg-transparent border-0 border-b border-gray-600 text-white placeholder-gray-600 focus:border-purple-500 focus:ring-0 px-0 py-2 mb-8" required>
                    <button type="submit" name="create_course" class="w-full bg-[#7c3aed] hover:bg-[#6d28d9] text-white font-bold py-3 rounded-lg shadow-lg flex items-center justify-center gap-2">
                        Create <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop"><button class="cursor-default"></button></form>
    </dialog>

    <script>
        lucide.createIcons();

        // VIEW TOGGLE LOGIC
        function toggleView(viewType) {
            const container = document.getElementById('courseContainer');
            const btnList = document.getElementById('btnList');
            const btnGrid = document.getElementById('btnGrid');

            if (viewType === 'grid') {
                container.classList.add('grid-view');
                container.classList.remove('space-y-4');
                btnGrid.classList.add('active');
                btnList.classList.remove('active');
                localStorage.setItem('courseView', 'grid');
            } else {
                container.classList.remove('grid-view');
                container.classList.add('space-y-4');
                btnList.classList.add('active');
                btnGrid.classList.remove('active');
                localStorage.setItem('courseView', 'list');
            }
        }

        document.addEventListener("DOMContentLoaded", () => {
            const savedView = localStorage.getItem('courseView') || 'list';
            toggleView(savedView);
        });

        function shareCourse(courseId) {
            const baseUrl = window.location.origin + "/learnsphere/course_details.php?id=" + courseId;
            navigator.clipboard.writeText(baseUrl).then(() => {
                alert("Copied: " + baseUrl);
            });
        }

        function modifyTag(courseId, action, tagName) {
            if(!tagName) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'dashboard.php';
            const inputs = [
                {name: 'manage_tag', value: 'true'},
                {name: 'course_id', value: courseId},
                {name: 'action', value: action},
                {name: 'tag_name', value: tagName}
            ];
            inputs.forEach(d => {
                const i = document.createElement('input');
                i.type = 'hidden';
                i.name = d.name;
                i.value = d.value;
                form.appendChild(i);
            });
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>