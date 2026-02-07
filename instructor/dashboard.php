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
$instructor_name = $_SESSION['name'] ?? 'Instructor';
$search = $_GET['search'] ?? '';

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_course'])) {
        $title = trim($_POST['title']);
        // Default values for a new course
        $sql = "INSERT INTO courses (title, instructor_id, is_published, tags, duration, views) VALUES (?, ?, 0, '', '0h 0m', 0)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $instructor_id]);
        $new_id = $pdo->lastInsertId();
        header("Location: manage_course.php?id=" . $new_id . "&new=1");
        exit;
    }
    if (isset($_POST['manage_tag'])) {
        $course_id = $_POST['course_id'];
        $tag_name = trim($_POST['tag_name']);
        $action = $_POST['action'];
        
        $stmt = $pdo->prepare("SELECT tags FROM courses WHERE id = ? AND instructor_id = ?");
        $stmt->execute([$course_id, $instructor_id]);
        $current = $stmt->fetchColumn();
        
        $tags_array = $current ? explode(',', $current) : [];
        $tags_array = array_map('trim', array_filter($tags_array));
        
        if ($action === 'add' && !empty($tag_name) && !in_array($tag_name, $tags_array)) {
            $tags_array[] = $tag_name;
        } elseif ($action === 'remove') {
            $tags_array = array_diff($tags_array, [$tag_name]);
        }
        
        $update = $pdo->prepare("UPDATE courses SET tags = ? WHERE id = ?");
        $update->execute([implode(',', $tags_array), $course_id]);
        header("Location: dashboard.php");
        exit;
    }
}

// --- FETCH COURSES (Fully Dynamic) ---
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
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    
    <style>
        /* --- LOCKED PREMIUM DESIGN DNA --- */
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

        .course-card {
            background: #ffffff;
            border: 1px solid white;
            border-radius: 1.5rem;
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08); 
            transition: all 0.3s ease-out;
            position: relative;
            overflow: hidden;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px -10px rgba(37, 99, 235, 0.15); 
            border-color: #e0e7ff;
        }

        .ribbon-wrapper {
            width: 90px; height: 90px; overflow: hidden; position: absolute; top: 0; right: 0; pointer-events: none; z-index: 20;
        }
        .ribbon {
            font-size: 10px; font-weight: 800; text-align: center; text-transform: uppercase; letter-spacing: 0.1em;
            transform: rotate(45deg); position: absolute; padding: 8px 0; width: 130px; top: 18px; right: -38px; color: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .ribbon.published { background: #3b82f6; } 
        .ribbon.draft { background: #94a3b8; }

        .tag {
            background: #eff6ff;
            color: #2563eb;
            padding: 5px 14px; border-radius: 20px;
            font-size: 12px; font-weight: 700; 
            border: 1px solid #dbeafe;
            display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.2s;
        }
        .tag:hover { background: #2563eb; color: white; border-color: #2563eb; }

        .btn-edit {
            background: #0f172a;
            color: white; border: none; font-weight: 700; letter-spacing: 0.05em;
            text-transform: uppercase; font-size: 11px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.2);
        }
        .btn-edit:hover { background: #334155; transform: translateY(-1px); }

        .btn-share {
            background: white; border: 2px solid #e2e8f0;
            color: #64748b; font-weight: 700; font-size: 11px; text-transform: uppercase;
        }
        .btn-share:hover { border-color: #94a3b8; color: #1e293b; background: #f8fafc; }

        .search-input {
            background: white; border: 2px solid #eef2f6; border-radius: 12px;
            padding: 10px 16px 10px 42px; font-weight: 500; color: #334155;
            transition: all 0.3s;
        }
        .search-input:focus { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); outline: none; }

        .fab {
            width: 68px; height: 68px;
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            color: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 10px 30px rgba(124, 58, 237, 0.4);
            border: 4px solid rgba(255,255,255,0.2);
            position: fixed; bottom: 40px; left: 40px; cursor: pointer; z-index: 50; transition: transform 0.2s;
        }
        .fab:hover { transform: scale(1.1) rotate(90deg); }

        .toggle-btn { padding: 8px; border-radius: 8px; color: #94a3b8; transition: 0.2s; }
        .toggle-btn.active { background: white; color: #2563eb; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }

        /* Grid View Layout */
        #courseContainer.grid-view { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 2rem; }
        #courseContainer.grid-view .course-card { flex-direction: column; min-height: 380px; }
        #courseContainer.grid-view .course-left { border: none; padding-bottom: 0; }
        #courseContainer.grid-view .course-center { background: transparent; border: none; padding: 0 2rem 1rem 2rem; }
        #courseContainer.grid-view .course-right { flex-direction: row; padding: 1.5rem 2rem; border-top: 1px dashed #e2e8f0; }
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
                    <a href="#" class="px-4 py-2 text-slate-900 font-bold bg-white rounded-full shadow-sm">Courses</a>
                    <a href="reporting.php" class="px-4 py-2 text-slate-500 font-semibold hover:text-slate-900 transition">Reporting</a>
                </nav>
            </div>

            <div class="flex-1 max-w-lg mx-12 relative">
                <i data-lucide="search" class="absolute left-4 top-3.5 w-5 h-5 text-gray-400"></i>
                <form method="GET">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search your masterpieces..." class="search-input w-full">
                </form>
            </div>

            <div class="flex items-center gap-6">
                <div class="bg-gray-100 p-1.5 rounded-xl flex gap-1">
                    <button id="btnList" class="toggle-btn active" onclick="toggleView('list')"><i data-lucide="list" class="w-5 h-5"></i></button>
                    <button id="btnGrid" class="toggle-btn" onclick="toggleView('grid')"><i data-lucide="layout-grid" class="w-5 h-5"></i></button>
                </div>

                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar border-2 border-white shadow-sm">
                        <div class="w-10 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold">
                            <?= strtoupper(substr($instructor_name, 0, 1)) ?>
                        </div>
                    </div>
                    <ul tabindex="0" class="mt-3 z-[1] p-2 shadow-2xl menu menu-sm dropdown-content bg-white/90 backdrop-blur-lg border border-white rounded-2xl w-52 text-slate-700">
                        <li class="px-4 py-2">
                            <span class="font-bold text-slate-900 block"><?= htmlspecialchars($instructor_name) ?></span>
                            <span class="text-[10px] uppercase font-bold text-slate-400 tracking-widest">Instructor</span>
                        </li>
                        <div class="divider my-0"></div>
                        <li><a href="../logout.php" class="text-red-500 font-bold hover:bg-red-50"><i data-lucide="log-out" class="w-4 h-4"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-7xl mx-auto w-full p-8 pb-32">
        
        <div class="mb-10 mt-4">
            <h1 class="text-4xl font-extrabold text-slate-900 mb-2 heading-font">Your <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-purple-600">Masterpieces</span></h1>
            <p class="text-slate-500 font-medium text-lg">Manage and track your curriculum.</p>
        </div>

        <div id="courseContainer" class="space-y-6">
            <?php foreach($courses as $course): ?>
                <div class="course-card flex flex-col md:flex-row h-auto md:h-44 group">
                    
                    <div class="ribbon-wrapper">
                        <div class="ribbon <?= $course['is_published'] ? 'published' : 'draft' ?>">
                            <?= $course['is_published'] ? 'Published' : 'Draft' ?>
                        </div>
                    </div>

                    <div class="course-left p-8 flex-1 flex flex-col justify-center border-r border-dashed border-gray-100">
                        <h3 class="heading-font text-2xl font-bold text-slate-800 mb-4 truncate pr-12">
                            <?= htmlspecialchars($course['title']) ?>
                        </h3>
                        
                        <div class="flex flex-wrap items-center gap-2">
                            <?php 
                                $tags = array_filter(explode(',', $course['tags'] ?? ''));
                                foreach($tags as $tag): $tag = trim($tag); if(empty($tag)) continue;
                            ?>
                                <span class="tag">
                                    <?= $tag ?> 
                                    <i data-lucide="x" class="w-3 h-3 tag-remove cursor-pointer" onclick="modifyTag(<?= $course['id'] ?>, 'remove', '<?= $tag ?>')"></i>
                                </span>
                            <?php endforeach; ?>
                            <input type="text" placeholder="+ Add" class="text-xs bg-transparent border-none focus:ring-0 text-slate-400 font-bold p-0 w-16 hover:text-blue-600"
                                   onkeydown="if(event.key === 'Enter') { modifyTag(<?= $course['id'] ?>, 'add', this.value); }">
                        </div>
                    </div>

                    <div class="course-center w-full md:w-80 p-8 flex flex-col justify-center bg-slate-50/30">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <span class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-1">Views</span>
                                <span class="block text-2xl font-bold text-slate-900"><?= number_format($course['views'] ?? 0) ?></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-1">Content</span>
                                <span class="block text-2xl font-bold text-slate-900"><?= $course['content_count'] ?></span>
                            </div>
                            <div class="col-span-2 flex items-center gap-2 pt-2 border-t border-gray-100">
                                <i data-lucide="clock" class="w-4 h-4 text-blue-500"></i>
                                <span class="text-sm font-bold text-blue-600"><?= htmlspecialchars($course['duration'] ?? '0h 0m') ?></span>
                                <span class="text-xs text-slate-400 font-bold uppercase ml-1">Total Duration</span>
                            </div>
                        </div>
                    </div>

                    <div class="course-right w-full md:w-64 p-8 flex flex-col gap-3 justify-center items-center bg-white">
                        <button onclick="shareCourse(<?= $course['id'] ?>)" class="btn-share w-full py-3 rounded-xl flex items-center justify-center gap-2 transition-all">
                            <i data-lucide="share-2" class="w-3 h-3"></i> Share Link
                        </button>
                        <a href="manage_course.php?id=<?= $course['id'] ?>" class="btn-edit w-full py-3 rounded-xl flex items-center justify-center gap-2 transition-all">
                            Edit Course <i data-lucide="arrow-right" class="w-3 h-3"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <button onclick="document.getElementById('createModal').showModal()" class="fab" title="Create New">
        <i data-lucide="plus" class="w-8 h-8"></i>
    </button>

    <dialog id="createModal" class="modal">
        <div class="modal-box bg-white p-0 rounded-3xl max-w-lg shadow-2xl overflow-hidden">
            <div class="px-8 py-6 bg-slate-50 flex justify-between items-center border-b border-gray-100">
                <h3 class="heading-font font-bold text-xl text-slate-900">Start New Course</h3>
                <form method="dialog"><button class="text-slate-400 hover:text-red-500"><i data-lucide="x" class="w-6 h-6"></i></button></form>
            </div>
            <div class="p-10">
                <form method="POST">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide mb-3">Course Title</label>
                    <input type="text" name="title" placeholder="e.g. Advanced Python Patterns" class="search-input w-full text-lg mb-8" required>
                    <button type="submit" name="create_course" class="w-full bg-black text-white font-bold py-4 rounded-xl shadow-lg hover:bg-gray-800 transition-all flex items-center justify-center gap-2">
                        Create Project <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop bg-slate-900/40 backdrop-blur-sm"><button>close</button></form>
    </dialog>

    <script>
        lucide.createIcons();

        function toggleView(viewType) {
            const c = document.getElementById('courseContainer');
            const bL = document.getElementById('btnList');
            const bG = document.getElementById('btnGrid');
            if (viewType === 'grid') {
                c.classList.add('grid-view'); c.classList.remove('space-y-6');
                bG.classList.add('active'); bL.classList.remove('active');
                localStorage.setItem('courseView', 'grid');
            } else {
                c.classList.remove('grid-view'); c.classList.add('space-y-6');
                bL.classList.add('active'); bG.classList.remove('active');
                localStorage.setItem('courseView', 'list');
            }
        }

        document.addEventListener("DOMContentLoaded", () => toggleView(localStorage.getItem('courseView') || 'list'));
        
        function shareCourse(id) {
            const url = window.location.origin + "/learnsphere/course_details.php?id=" + id;
            navigator.clipboard.writeText(url).then(() => alert("Copied to clipboard!"));
        }

        function modifyTag(id, action, tag) {
            if(!tag) return;
            const f = document.createElement('form'); f.method = 'POST'; f.action = 'dashboard.php';
            [['manage_tag','true'],['course_id',id],['action',action],['tag_name',tag]].forEach(kv => {
                const i = document.createElement('input'); i.type='hidden'; i.name=kv[0]; i.value=kv[1]; f.appendChild(i);
            });
            document.body.appendChild(f); f.submit();
        }
    </script>
</body>
</html>