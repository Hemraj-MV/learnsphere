<?php
// -------------------------------------------------------------------
// instructor/manage_course.php
// Full Version - Course Studio
// -------------------------------------------------------------------

require '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// -------------------------------------------------------------------
// 1. AUTHENTICATION & INITIALIZATION
// -------------------------------------------------------------------
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'instructor')) {
    header("Location: ../login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['name'] ?? 'Instructor';
$course_id = $_GET['id'] ?? null;

// --- HELPER: Recalculate Total Course Duration ---
function recalculateCourseDuration($pdo, $c_id) {
    $stmt = $pdo->prepare("SELECT duration FROM lessons WHERE course_id = ?");
    $stmt->execute([$c_id]);
    $durations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $total_minutes = 0;
    foreach ($durations as $d) {
        if (empty($d)) continue;
        $parts = explode(':', $d);
        if (count($parts) === 2) {
            $total_minutes += (intval($parts[0]) * 60) + intval($parts[1]);
        }
    }
    
    $hours = floor($total_minutes / 60);
    $minutes = $total_minutes % 60;
    // Format HH:MM
    $final_duration = sprintf("%02d:%02d", $hours, $minutes);
    
    $pdo->prepare("UPDATE courses SET duration = ? WHERE id = ?")->execute([$final_duration, $c_id]);
}

// Fetch Instructors for Dropdown
$instructors = $pdo->query("SELECT id, name FROM users WHERE role IN ('instructor', 'admin')")->fetchAll(PDO::FETCH_ASSOC);
// --- HANDLE ATTENDEES ---
if (isset($_POST['action_type']) && $_POST['action_type'] == 'add_attendees') {
    // Logic to insert emails into invites table or send mail
    // For now, we simulate success
    header("Location: manage_course.php?id=$course_id&success=1");
    exit;
}
if (isset($_POST['action_type']) && $_POST['action_type'] == 'contact_attendees') {
    // Logic to send email to all enrolled users
    // mail($to, $subject, $message...);
    header("Location: manage_course.php?id=$course_id&success=1");
    exit;
}
// -------------------------------------------------------------------
// 2. HANDLE FORM SUBMISSIONS
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- A. SAVE COURSE DETAILS (General, Description, Options) ---
    if (isset($_POST['save_details'])) {
        $title = trim($_POST['title']);
        $tags = trim($_POST['tags']);
        $description = $_POST['description'] ?? '';
        $is_published = isset($_POST['is_published']) ? 1 : 0;
        
        // Options Tab Fields
        $price = $_POST['price'] ?? 0.00;
        $visibility = $_POST['visibility'] ?? 'everyone';
        $access_rule = $_POST['access_rule'] ?? 'open';
        $new_instructor_id = $_POST['instructor_id'] ?? $current_user_id;
        
        $redirect_tab = $_POST['redirect_tab'] ?? 'content';
        
        // Handle Course Image Upload
        $image_path = $_POST['current_image'] ?? null;
        if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['course_image']['name'], PATHINFO_EXTENSION);
            $new_name = "course_" . time() . "." . $ext;
            if (!is_dir('../uploads/courses/')) mkdir('../uploads/courses/', 0777, true);
            
            if (move_uploaded_file($_FILES['course_image']['tmp_name'], "../uploads/courses/" . $new_name)) {
                $image_path = "uploads/courses/" . $new_name;
            }
        }

        if ($course_id) {
            // Update Existing
            $sql = "UPDATE courses SET title=?, tags=?, description=?, is_published=?, image=?, price=?, visibility=?, access_rule=?, instructor_id=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $tags, $description, $is_published, $image_path, $price, $visibility, $access_rule, $new_instructor_id, $course_id]);
        } else {
            // Create New
            if (!empty($title)) {
                $sql = "INSERT INTO courses (title, instructor_id, tags, description, is_published, image, duration, views, price, visibility, access_rule) VALUES (?, ?, ?, ?, ?, ?, '00:00', 0, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$title, $new_instructor_id, $tags, $description, $is_published, $image_path, $price, $visibility, $access_rule]);
                $new_id = $pdo->lastInsertId();
                header("Location: manage_course.php?id=" . $new_id . "&success=1");
                exit;
            }
        }
        
        header("Location: manage_course.php?id=" . $course_id . "&tab=" . $redirect_tab . "&success=1");
        exit;
    }

    // --- B. MANAGE LESSON (Add/Edit Content - Video/Doc/Image) ---
    if (isset($_POST['manage_lesson'])) {
        $l_id = $_POST['lesson_id'] ?? null;
        $l_title = trim($_POST['lesson_title']);
        $l_desc = $_POST['lesson_description'] ?? '';
        $l_type = $_POST['lesson_type'];
        $l_responsible = $_POST['responsible'] ?? $instructor_name;
        $l_duration = $_POST['duration'] ?? '00:00';
        $allow_download = isset($_POST['allow_download']) ? 1 : 0;
        
        // 1. Content URL
        $content_url = $_POST['video_link'] ?? ''; 
        if ($l_id) {
            $existing_url = $_POST['current_content_url'] ?? '';
            if(!empty($existing_url) && empty($content_url)) $content_url = $existing_url;
        }

        // 2. Attachment Link
        $attachment_link = $_POST['attachment_link'] ?? '';

        // 3. File Upload (Main Content)
        $file_input = ($l_type === 'video') ? 'video_file' : 'lesson_file';
        if (isset($_FILES[$file_input]) && $_FILES[$file_input]['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES[$file_input]['name'], PATHINFO_EXTENSION);
            $new_name = "lesson_" . time() . "_" . uniqid() . "." . $ext;
            if (!is_dir('../uploads/lessons/')) mkdir('../uploads/lessons/', 0777, true);
            if (move_uploaded_file($_FILES[$file_input]['tmp_name'], "../uploads/lessons/" . $new_name)) {
                $content_url = "uploads/lessons/" . $new_name;
            }
        } elseif ($l_id && empty($content_url)) {
            $content_url = $_POST['current_content_url'] ?? '';
        }

        // 4. File Upload (Attachment)
        if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['attachment_file']['name'], PATHINFO_EXTENSION);
            $new_name = "attach_" . time() . "_" . uniqid() . "." . $ext;
            if (!is_dir('../uploads/attachments/')) mkdir('../uploads/attachments/', 0777, true);
            if(move_uploaded_file($_FILES['attachment_file']['tmp_name'], "../uploads/attachments/" . $new_name)) {
                $attachment_link = "uploads/attachments/" . $new_name;
            }
        }

        // DB Update
        if ($l_id) {
            $sql = "UPDATE lessons SET title=?, description=?, type=?, content_url=?, duration=?, is_downloadable=?, responsible=?, attachment_url=? WHERE id=? AND course_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$l_title, $l_desc, $l_type, $content_url, $l_duration, $allow_download, $l_responsible, $attachment_link, $l_id, $course_id]);
        } else {
            $stmt = $pdo->prepare("SELECT MAX(position) FROM lessons WHERE course_id = ?");
            $stmt->execute([$course_id]);
            $pos = $stmt->fetchColumn() + 1;
            $sql = "INSERT INTO lessons (course_id, title, description, type, content_url, duration, is_downloadable, responsible, position, attachment_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$course_id, $l_title, $l_desc, $l_type, $content_url, $l_duration, $allow_download, $l_responsible, $pos, $attachment_link]);
        }

        recalculateCourseDuration($pdo, $course_id);
        header("Location: manage_course.php?id=" . $course_id . "&tab=content");
        exit;
    }

    // --- C. CREATE QUIZ (New Flow) ---
    if (isset($_POST['create_quiz'])) {
        $q_title = trim($_POST['quiz_title']);
        
        // 1. Create Lesson Shell
        $stmt = $pdo->prepare("SELECT MAX(position) FROM lessons WHERE course_id = ?");
        $stmt->execute([$course_id]);
        $pos = $stmt->fetchColumn() + 1;

        $sql = "INSERT INTO lessons (course_id, title, type, duration, position) VALUES (?, ?, 'quiz', '00:00', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$course_id, $q_title, $pos]);
        $new_lesson_id = $pdo->lastInsertId();

        // 2. Create Quiz Entry
        $sql = "INSERT INTO quizzes (course_id, lesson_id, title) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$course_id, $new_lesson_id, $q_title]);
        $new_quiz_id = $pdo->lastInsertId();

        // 3. Redirect to Quiz Editor
        header("Location: quiz_editor.php?quiz_id=" . $new_quiz_id . "&course_id=" . $course_id);
        exit;
    }

    // --- D. DELETE LESSON ---
    if (isset($_POST['delete_lesson'])) {
        $l_id = $_POST['lesson_id'];
        $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ? AND course_id = ?");
        $stmt->execute([$l_id, $course_id]);
        recalculateCourseDuration($pdo, $course_id);
        header("Location: manage_course.php?id=" . $course_id . "&tab=" . $_GET['tab']);
        exit;
    }
}

// -------------------------------------------------------------------
// 3. FETCH DATA
// -------------------------------------------------------------------
if ($course_id) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    if (!$course) { die("Access Denied"); }

    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY position ASC");
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll();
} else {
    $course = ['id' => null, 'title' => '', 'tags' => '', 'description' => '', 'is_published' => 0, 'image' => '', 'price' => 0.00, 'visibility' => 'everyone', 'access_rule' => 'open', 'instructor_id' => $current_user_id];
    $lessons = [];
}

$active_tab_php = $_GET['tab'] ?? 'content';
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
        /* CORE STYLES */
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: linear-gradient(135deg, #f0f4ff 0%, #eef2f6 100%); color: #1e293b; min-height: 100vh; }
        h1, h2, h3, .heading-font { font-family: 'Outfit', sans-serif; letter-spacing: -0.02em; }

        /* PREMIUM HEADER */
        .premium-header { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255, 255, 255, 0.5); box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05); }
        .premium-card { background: #ffffff; border: 1px solid white; border-radius: 1.5rem; box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08); transition: all 0.3s ease-out; }
        
        /* FORM INPUTS */
        .form-input-premium { border-bottom: 2px solid #e2e8f0; background: transparent; padding: 0.75rem 0; font-weight: 600; color: #1e293b; transition: all 0.3s; width: 100%; border-radius: 0; }
        .form-input-premium:focus { border-color: #6366f1; outline: none; }
        
        /* TABS */
        .tab-link { padding-bottom: 0.75rem; font-size: 0.875rem; font-weight: 600; color: #94a3b8; border-bottom: 2px solid transparent; transition: 0.2s; cursor: pointer; display: inline-block; }
        .tab-active { border-color: #6366f1; color: #6366f1; }
        
        /* BUTTONS */
        .btn-premium-dark { background: #0f172a; color: white; border: none; font-weight: 700; text-transform: uppercase; font-size: 11px; box-shadow: 0 4px 15px rgba(15, 23, 42, 0.2); }
        .btn-premium-dark:hover { background: #334155; transform: translateY(-1px); }

        /* UPLOAD */
        .image-placeholder { background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 1.25rem; transition: 0.3s; position: relative; overflow: hidden; background-size: cover; background-position: center; }
        .image-placeholder:hover { border-color: #6366f1; background-color: #f1f5f9; }

        /* OPTIONS LAYOUT */
        .option-section-title { font-size: 0.9rem; font-weight: 700; color: #334155; margin-bottom: 1rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem; display: block;}
        .help-text { font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem; display: block; }
        
        /* MODAL */
        .modal-box-premium { background-color: white; border-radius: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid white; overflow: hidden; display: flex; flex-direction: column; max-height: 85vh; }
        .modal-content-scroll { overflow-y: auto; padding: 2rem; flex: 1; }
        .modal-tab { @apply px-5 py-3 text-xs font-bold uppercase tracking-wider border-b-2 border-transparent transition-colors; cursor: pointer; user-select: none; }
        .modal-tab.active { border-color: #6366f1; color: #6366f1; background: #eef2ff; }
        .modal-tab.inactive { color: #94a3b8; hover:text-slate-600; }
        
        .table-container { overflow-x: visible; } 
    </style>
</head>
<dialog id="addAttendeesModal" class="modal">
    <div class="modal-box bg-white rounded-2xl p-8 max-w-lg">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-slate-900">Add Attendees</h3>
            <form method="dialog"><button class="btn btn-sm btn-circle btn-ghost"><i data-lucide="x" class="w-5 h-5"></i></button></form>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action_type" value="add_attendees">
            <div class="alert bg-indigo-50 text-indigo-700 text-sm mb-6 border-indigo-100 rounded-xl flex gap-3">
                <i data-lucide="info" class="w-5 h-5"></i>
                <span>Invite users by email. New users will receive an account setup link.</span>
            </div>

            <div class="mb-6">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Email Addresses</label>
                <textarea name="emails" class="textarea textarea-bordered w-full h-32 bg-slate-50 text-slate-700 font-medium" placeholder="john@example.com, sara@test.com (Separate by comma)"></textarea>
            </div>

            <div class="flex justify-end gap-2">
                <form method="dialog"><button class="btn btn-ghost">Cancel</button></form>
                <button type="submit" class="btn bg-indigo-600 hover:bg-indigo-700 text-white border-none rounded-xl">Send Invites</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop bg-slate-900/50 backdrop-blur-sm"><button>close</button></form>
</dialog>

<dialog id="contactAttendeesModal" class="modal">
    <div class="modal-box bg-white rounded-2xl p-8 max-w-lg">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-slate-900">Contact Learners</h3>
            <form method="dialog"><button class="btn btn-sm btn-circle btn-ghost"><i data-lucide="x" class="w-5 h-5"></i></button></form>
        </div>

        <form method="POST">
            <input type="hidden" name="action_type" value="contact_attendees">
            
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Subject</label>
                <input type="text" name="subject" class="input input-bordered w-full bg-slate-50 font-bold" placeholder="Important Update..." required>
            </div>

            <div class="mb-6">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Message</label>
                <textarea name="message" class="textarea textarea-bordered w-full h-40 bg-slate-50 text-slate-700 font-medium" placeholder="Write your message here..." required></textarea>
            </div>

            <div class="flex justify-end gap-2">
                <form method="dialog"><button class="btn btn-ghost">Cancel</button></form>
                <button type="submit" class="btn bg-slate-900 hover:bg-slate-800 text-white border-none rounded-xl gap-2">
                    <i data-lucide="send" class="w-4 h-4"></i> Send Email
                </button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop bg-slate-900/50 backdrop-blur-sm"><button>close</button></form>
</dialog>

<script>
    // Add this inside your existing <script> tag or at the end of the file
    document.addEventListener("DOMContentLoaded", () => {
        // Find your specific buttons based on their text content or add IDs to them in your HTML
        const buttons = document.querySelectorAll('button');
        buttons.forEach(btn => {
            if(btn.innerText.includes("Add Attendees")) {
                btn.onclick = () => document.getElementById('addAttendeesModal').showModal();
            }
            if(btn.innerText.includes("Contact Attendees")) {
                btn.onclick = () => document.getElementById('contactAttendeesModal').showModal();
            }
        });
    });
</script>
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
                        <input type="hidden" name="description" value="<?= htmlspecialchars($course['description'] ?? '') ?>">
                        <input type="hidden" name="price" value="<?= $course['price'] ?>">
                        <input type="hidden" name="visibility" value="<?= $course['visibility'] ?>">
                        <input type="hidden" name="access_rule" value="<?= $course['access_rule'] ?>">
                        <input type="hidden" name="instructor_id" value="<?= $course['instructor_id'] ?>">
                        <input type="hidden" name="current_image" value="<?= $course['image'] ?? '' ?>">
                        <input type="hidden" name="redirect_tab" id="headerRedirectTab" value="content">
                        
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
        
        <?php if(isset($_GET['success'])): ?>
            <div class="alert bg-emerald-500 text-white border-none rounded-2xl shadow-lg mb-6 flex items-center gap-3">
                <i data-lucide="check-circle" class="w-5 h-5"></i>
                <span class="font-bold">Operation Successful</span>
            </div>
        <?php endif; ?>

        <div class="mb-8 flex flex-col items-start gap-4">
            <a href="manage_course.php" class="btn btn-sm btn-premium-dark gap-2 px-6 rounded-xl">
                <i data-lucide="plus" class="w-4 h-4"></i> New Course
            </a>
            <?php if($course_id): ?>
                <div class="flex gap-3">
                    <button class="btn btn-xs bg-white border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300 gap-2 font-semibold">
                        <i data-lucide="mail" class="w-3 h-3"></i> Contact Attendees
                    </button>
                    <button class="btn btn-xs bg-white border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300 gap-2 font-semibold">
                        <i data-lucide="user-plus" class="w-3 h-3"></i> Add Attendees
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <div class="premium-card p-10 mb-10">
            <form method="POST" id="main-course-form" enctype="multipart/form-data" class="flex flex-col md:flex-row gap-12">
                <input type="hidden" name="save_details" value="1">
                <input type="hidden" name="is_published" value="<?= $course['is_published'] ?>">
                
                <input type="hidden" name="price" value="<?= $course['price'] ?>">
                <input type="hidden" name="visibility" value="<?= $course['visibility'] ?>">
                <input type="hidden" name="access_rule" value="<?= $course['access_rule'] ?>">
                <input type="hidden" name="instructor_id" value="<?= $course['instructor_id'] ?>">
                <input type="hidden" name="description" value="<?= htmlspecialchars($course['description'] ?? '') ?>">
                <input type="hidden" name="current_image" value="<?= $course['image'] ?? '' ?>">
                <input type="hidden" name="redirect_tab" id="mainRedirectTab" value="content">
                
                <div class="flex-1 space-y-8">
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
                    <label for="course_image_input" class="image-placeholder aspect-video flex flex-col items-center justify-center text-slate-300 cursor-pointer group hover:text-indigo-500" 
                           style="background-image: url('../<?= $course['image'] ?? '' ?>');">
                        <?php if(empty($course['image'])): ?>
                            <i data-lucide="image" class="w-10 h-10 mb-2 transition-colors"></i>
                            <span class="text-[10px] font-bold uppercase tracking-tighter">Click to Upload</span>
                        <?php endif; ?>
                        <input type="file" name="course_image" id="course_image_input" class="hidden" onchange="this.form.submit()">
                    </label>
                </div>
            </form>
        </div>

        <?php if($course_id): ?>
            <div class="flex gap-8 mb-6 px-4">
                <div onclick="switchMainTab('content')" class="tab-link <?= $active_tab_php == 'content' ? 'tab-active' : '' ?>" id="tab-btn-content">Content</div>
                <div onclick="switchMainTab('description')" class="tab-link <?= $active_tab_php == 'description' ? 'tab-active' : '' ?>" id="tab-btn-description">Description</div>
                <div onclick="switchMainTab('options')" class="tab-link <?= $active_tab_php == 'options' ? 'tab-active' : '' ?>" id="tab-btn-options">Options</div>
                <div onclick="switchMainTab('quiz')" class="tab-link <?= $active_tab_php == 'quiz' ? 'tab-active' : '' ?>" id="tab-btn-quiz">Quiz</div>
            </div>

            <div class="premium-card p-8 min-h-[400px]">
                
                <div id="view-content" class="<?= $active_tab_php == 'content' ? '' : 'hidden' ?>">
                    <div class="flex items-center justify-between mb-8">
                        <h3 class="heading-font font-bold text-xl">Course Modules</h3>
                        <button type="button" onclick="openLessonModal()" class="btn btn-sm btn-premium-dark rounded-xl px-6 gap-2">
    <i data-lucide="plus" class="w-4 h-4"></i> Add Content
</button>
                    </div>
                    <div class="overflow-visible min-h-[300px]">
                        <table class="table w-full">
                            <thead class="bg-slate-50/50">
                                <tr class="text-[10px] uppercase font-black text-slate-400 tracking-widest border-none">
                                    <th class="py-4 pl-6">Content Title</th>
                                    <th>Category</th>
                                    <th>Duration</th>
                                    <th class="text-right pr-6">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-600">
                                <?php foreach($lessons as $lesson): ?>
                                    <?php if($lesson['type'] == 'quiz') continue; // Quizzes go to Quiz tab ?>
                                    <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition">
                                        <td class="py-5 pl-6 font-bold text-slate-900 flex items-center gap-4">
                                            <i data-lucide="grip-vertical" class="w-4 h-4 text-slate-300 cursor-move"></i>
                                            <?= htmlspecialchars($lesson['title']) ?>
                                        </td>
                                        <td>
                                            <span class="px-3 py-1 bg-slate-100 rounded-full text-xs font-bold uppercase tracking-tighter">
                                                <?= ucfirst($lesson['type']) ?>
                                            </span>
                                        </td>
                                        <td class="font-mono text-xs text-slate-500 font-bold">
                                            <?= htmlspecialchars($lesson['duration']) ?>
                                        </td>
                                        <td class="text-right pr-6">
                                            <div class="dropdown dropdown-end">
                                                <label tabindex="0" class="btn btn-ghost btn-xs btn-circle">
                                                    <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                                </label>
                                                <ul tabindex="0" class="dropdown-content z-[50] menu p-2 shadow-xl bg-white rounded-xl w-32 border border-slate-100">
                                                    <li>
                                                        <a onclick="openLessonModal('<?= $lesson['id'] ?>', '<?= addslashes($lesson['title']) ?>', '<?= $lesson['type'] ?>', '<?= $lesson['content_url'] ?>', '<?= $lesson['duration'] ?>', '<?= $lesson['is_downloadable'] ?>', '<?= addslashes($lesson['responsible']) ?>', '<?= addslashes($lesson['description'] ?? '') ?>')">
                                                            <i data-lucide="edit-2" class="w-3 h-3"></i> Edit
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <form method="POST" onsubmit="return confirm('Delete?');">
                                                            <input type="hidden" name="delete_lesson" value="1">
                                                            <input type="hidden" name="lesson_id" value="<?= $lesson['id'] ?>">
                                                            <button class="text-red-600 hover:bg-red-50 w-full text-left flex items-center gap-2">
                                                                <i data-lucide="trash-2" class="w-3 h-3"></i> Delete
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="view-description" class="<?= $active_tab_php == 'description' ? '' : 'hidden' ?>">
                    <form method="POST">
                        <input type="hidden" name="save_details" value="1">
                        <input type="hidden" name="redirect_tab" value="description">
                        <input type="hidden" name="title" value="<?= htmlspecialchars($course['title']) ?>">
                        <input type="hidden" name="tags" value="<?= htmlspecialchars($course['tags']) ?>">
                        <input type="hidden" name="price" value="<?= $course['price'] ?>">
                        <input type="hidden" name="visibility" value="<?= $course['visibility'] ?>">
                        <input type="hidden" name="access_rule" value="<?= $course['access_rule'] ?>">
                        <input type="hidden" name="instructor_id" value="<?= $course['instructor_id'] ?>">
                        <input type="hidden" name="current_image" value="<?= $course['image'] ?? '' ?>">
                        
                        <div class="space-y-4">
                            <h3 class="heading-font font-bold text-xl mb-4">Course Narrative</h3>
                            <textarea name="description" class="textarea textarea-bordered w-full h-64 bg-slate-50/50 rounded-2xl p-6 font-medium text-slate-700" 
                                      placeholder="Describe the learning outcomes..."><?= htmlspecialchars($course['description'] ?? '') ?></textarea>
                            <div class="text-right">
                                <button type="submit" class="btn btn-premium-dark px-10 rounded-xl">Save Description</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div id="view-options" class="<?= $active_tab_php == 'options' ? '' : 'hidden' ?>">
                    <form method="POST">
                        <input type="hidden" name="save_details" value="1">
                        <input type="hidden" name="redirect_tab" value="options">
                        
                        <input type="hidden" name="title" value="<?= htmlspecialchars($course['title']) ?>">
                        <input type="hidden" name="tags" value="<?= htmlspecialchars($course['tags']) ?>">
                        <input type="hidden" name="description" value="<?= htmlspecialchars($course['description'] ?? '') ?>">
                        <input type="hidden" name="current_image" value="<?= $course['image'] ?? '' ?>">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                            <div class="space-y-8">
                                <span class="option-section-title">Access course rights</span>
                                
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-2">Show course to:</label>
                                    <select name="visibility" class="select select-bordered w-full max-w-xs bg-slate-50 text-slate-700 font-bold">
                                        <option value="everyone" <?= $course['visibility'] == 'everyone' ? 'selected' : '' ?>>Everyone</option>
                                        <option value="signed_in" <?= $course['visibility'] == 'signed_in' ? 'selected' : '' ?>>Signed In</option>
                                    </select>
                                    <span class="help-text">Define who can access your courses and their content.</span>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-3">Access rules:</label>
                                    <div class="flex flex-col gap-3">
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <input type="radio" name="access_rule" value="open" class="radio radio-sm radio-primary" 
                                                   <?= $course['access_rule'] == 'open' ? 'checked' : '' ?> onclick="togglePrice(false)">
                                            <span class="text-sm font-bold text-slate-600">Open</span>
                                        </label>
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <input type="radio" name="access_rule" value="invitation" class="radio radio-sm radio-primary" 
                                                   <?= $course['access_rule'] == 'invitation' ? 'checked' : '' ?> onclick="togglePrice(false)">
                                            <span class="text-sm font-bold text-slate-600">On Invitation</span>
                                        </label>
                                        <div class="flex items-center gap-4">
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="radio" name="access_rule" value="payment" class="radio radio-sm radio-primary" 
                                                       <?= $course['access_rule'] == 'payment' ? 'checked' : '' ?> onclick="togglePrice(true)">
                                                <span class="text-sm font-bold text-slate-600">On Payment</span>
                                            </label>
                                            
                                            <div id="priceField" class="<?= $course['access_rule'] == 'payment' ? '' : 'hidden' ?> flex items-center gap-2">
                                                <span class="text-sm font-bold text-slate-500">Price ($):</span>
                                                <input type="number" step="0.01" name="price" value="<?= $course['price'] ?>" 
                                                       class="input input-bordered input-sm w-24 font-bold text-slate-700">
                                            </div>
                                        </div>
                                    </div>
                                    <span class="help-text mt-2">Defines how people can access/enroll to your courses. (Payment requires gateway).</span>
                                </div>
                            </div>

                            <div class="space-y-8">
                                <span class="option-section-title">Responsible</span>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-2">Course Admin:</label>
                                    <select name="instructor_id" class="select select-bordered w-full max-w-xs bg-slate-50 text-slate-700 font-bold">
                                        <?php foreach ($instructors as $inst): ?>
                                            <option value="<?= $inst['id'] ?>" <?= $course['instructor_id'] == $inst['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($inst['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="help-text">Decide who'll be the responsible of the course.</span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-10 mt-4 border-t border-slate-100 flex justify-end">
                            <button type="submit" class="btn btn-premium-dark px-8 rounded-xl">Save Options</button>
                        </div>
                    </form>
                </div>

                <div id="view-quiz" class="<?= $active_tab_php == 'quiz' ? '' : 'hidden' ?>">
                    <div class="flex items-center justify-between mb-8">
                        <h3 class="heading-font font-bold text-xl">Quizzes</h3>
                        <button onclick="document.getElementById('addQuizModal').showModal()" class="btn btn-sm btn-premium-dark rounded-xl px-6 gap-2">
                            <i data-lucide="plus" class="w-4 h-4"></i> Add Quiz
                        </button>
                    </div>
                    <div class="overflow-visible min-h-[300px]">
                        <table class="table w-full">
                            <thead class="bg-slate-50/50">
                                <tr>
                                    <th class="py-4 pl-6">Quiz Title</th>
                                    <th>Category</th>
                                    <th class="text-right pr-6">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-600">
                                <?php 
                                $quiz_found = false;
                                foreach($lessons as $l): 
                                    if($l['type'] != 'quiz') continue; 
                                    $quiz_found = true;
                                    
                                    // Fetch the Quiz ID linked to this lesson
                                    $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE lesson_id = ?");
                                    $stmt->execute([$l['id']]);
                                    $q_data = $stmt->fetch();
                                    $real_quiz_id = $q_data['id'] ?? null;
                                ?>
                                    <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition">
                                        <td class="py-5 pl-6 font-bold text-slate-900"><?= htmlspecialchars($l['title']) ?></td>
                                        <td>
                                            <span class="px-3 py-1 bg-slate-100 rounded-full text-xs font-bold uppercase tracking-tighter">
                                                QUIZ
                                            </span>
                                        </td>
                                        <td class="text-right pr-6">
                                            <div class="dropdown dropdown-end">
                                                <label tabindex="0" class="btn btn-ghost btn-xs btn-circle">
                                                    <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                                </label>
                                                <ul tabindex="0" class="dropdown-content z-[50] menu p-2 shadow-xl bg-white rounded-xl w-32 border border-slate-100">
                                                    <?php if($real_quiz_id): ?>
                                                    <li>
                                                        <a href="quiz_editor.php?quiz_id=<?= $real_quiz_id ?>&course_id=<?= $course_id ?>" target="_blank">
                                                            <i data-lucide="edit-2" class="w-3 h-3"></i> Edit
                                                        </a>
                                                    </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <form method="POST" onsubmit="return confirm('Delete this quiz?');">
                                                            <input type="hidden" name="delete_lesson" value="1">
                                                            <input type="hidden" name="lesson_id" value="<?= $l['id'] ?>">
                                                            <button class="text-red-600 hover:bg-red-50 w-full text-left flex items-center gap-2">
                                                                <i data-lucide="trash-2" class="w-3 h-3"></i> Delete
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if(!$quiz_found): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-10 text-slate-400">No quizzes available. Add one above.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        <?php else: ?>
            <div class="premium-card py-32 flex flex-col items-center justify-center border-2 border-dashed border-slate-200 bg-white/50">
                <h3 class="text-xl font-bold text-slate-900 mb-2">Initialize Course</h3>
                <p class="text-slate-500 text-sm">Enter a title above to start.</p>
            </div>
        <?php endif; ?>
    </main>

    <dialog id="lessonModal" class="modal">
        <div class="modal-box modal-box-premium p-0 max-w-2xl">
            <div class="px-8 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center sticky top-0 z-10">
                <div class="text-xs font-bold text-slate-400 italic">Add Content</div>
                <form method="dialog"><button class="text-slate-400 hover:text-slate-900"><i data-lucide="x" class="w-5 h-5"></i></button></form>
            </div>
            
            <div class="modal-content-scroll bg-white">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="manage_lesson" value="1">
                    <input type="hidden" name="lesson_id" id="lessonIdInput" value="">
                    <input type="hidden" name="current_content_url" id="lessonUrlInput" value="">

                    <div class="mb-8">
                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-2">Content title</label>
                        <input type="text" name="lesson_title" id="lessonTitleInput" class="form-input-premium text-xl font-bold text-indigo-600" required>
                    </div>

                    <div class="flex gap-4 mb-8 border-b border-slate-100">
                        <div class="modal-tab active" id="mtab-content" onclick="switchModalTab('content')">Content</div>
                        <div class="modal-tab inactive" id="mtab-desc" onclick="switchModalTab('desc')">Description</div>
                        <div class="modal-tab inactive" id="mtab-attach" onclick="switchModalTab('attach')">Additional Attachment</div>
                    </div>

                    <div id="tab-pane-content">
                        <div class="mb-6 p-5 bg-slate-50 rounded-xl border border-slate-200">
                            <label class="block text-xs font-bold text-slate-500 mb-3">Content Category</label>
                            <div class="flex gap-6">
                                <label id="opt-video" class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="lesson_type" value="video" class="radio radio-xs radio-primary" checked onclick="toggleFields('video')">
                                    <span class="text-sm font-bold text-slate-600">Video</span>
                                </label>
                                <label id="opt-document" class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="lesson_type" value="document" class="radio radio-xs radio-primary" onclick="toggleFields('document')">
                                    <span class="text-sm font-bold text-slate-600">Document</span>
                                </label>
                                <label id="opt-image" class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="lesson_type" value="image" class="radio radio-xs radio-primary" onclick="toggleFields('image')">
                                    <span class="text-sm font-bold text-slate-600">Image</span>
                                </label>
                                <label id="opt-quiz" class="flex items-center gap-2 cursor-pointer" style="display:none">
                                    <input type="radio" name="lesson_type" value="quiz" class="radio radio-xs radio-primary" onclick="toggleFields('quiz')">
                                    <span class="text-sm font-bold text-slate-600">Quiz</span>
                                </label>
                            </div>
                        </div>
                        
                        <div id="videoFields">
                            <div class="flex gap-4 mb-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="video_source" value="link" class="radio radio-xs" checked onclick="toggleVideoSource('link')">
                                    <span class="text-xs font-bold text-slate-500">Video Link</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="video_source" value="upload" class="radio radio-xs" onclick="toggleVideoSource('upload')">
                                    <span class="text-xs font-bold text-slate-500">Upload Video</span>
                                </label>
                            </div>
                            <div id="vSourceLink" class="mb-6">
                                <input type="text" name="video_link" id="videoLinkInput" class="form-input-premium text-sm" placeholder="URL">
                            </div>
                            <div id="vSourceUpload" class="mb-6 hidden">
                                <input type="file" name="video_file" id="videoFileInput" accept="video/*" class="file-input file-input-bordered file-input-sm w-full" onchange="calculateDuration(this)">
                            </div>
                        </div>
                        
                        <div id="fileFields" class="hidden">
                            <div class="mb-6 flex items-center gap-4 p-4 bg-indigo-50 rounded-xl border border-indigo-100">
                                <div class="flex-1">
                                    <label class="block text-sm font-bold text-indigo-900 mb-1">File</label>
                                    <div class="text-xs text-indigo-400" id="fileNameDisplay">Upload here</div>
                                </div>
                                <label class="btn btn-sm bg-indigo-600 text-white rounded-lg">
                                    Upload <input type="file" name="lesson_file" class="hidden" onchange="document.getElementById('fileNameDisplay').innerText = this.files[0].name">
                                </label>
                            </div>
                        </div>
                        
                        <div id="quizFields" class="hidden">
                            <p class="text-sm text-slate-500 p-4 bg-slate-50 rounded italic text-center">Quiz Module Created. Add questions via 'Manage Questions' after saving.</p>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-bold text-slate-500 mb-1" id="durationLabel">Duration (HH:MM)</label>
                            <input type="text" name="duration" id="durationInput" class="form-input-premium w-32 font-mono" value="00:00">
                        </div>
                        
                        <div class="mb-8">
                            <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-2">Responsible</label>
                            <input type="text" name="responsible" id="responsibleInput" value="<?= htmlspecialchars($instructor_name) ?>" class="form-input-premium w-1/2">
                        </div>
                        
                        <div id="allowDownloadContainer" class="hidden mb-6">
                            <label class="flex items-center gap-3 cursor-pointer p-4 bg-slate-50 rounded-xl">
                                <input type="checkbox" name="allow_download" id="downloadInput" class="checkbox checkbox-sm checkbox-primary rounded">
                                <span class="text-sm font-bold text-slate-600">Allow download?</span>
                            </label>
                        </div>
                    </div>

                    <div id="tab-pane-desc" class="hidden">
                        <textarea name="lesson_description" id="lessonDescInput" class="textarea textarea-bordered w-full h-40 bg-slate-50" placeholder="Details..."></textarea>
                    </div>

                    <div id="tab-pane-attach" class="hidden">
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-bold text-slate-500">File :</label>
                            </div>
                            <div class="flex items-center gap-4">
                                <label class="btn btn-sm bg-purple-500 hover:bg-purple-600 text-white border-none rounded-lg px-6 normal-case">
                                    Upload your file <input type="file" name="attachment_file" class="hidden" onchange="document.getElementById('attachFileLabel').innerText = this.files[0].name">
                                </label>
                                <span class="text-xs text-slate-400 italic" id="attachFileLabel">No file selected</span>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-bold text-slate-500 mb-2">Link :</label>
                            <input type="text" name="attachment_link" class="form-input-premium text-sm" placeholder="e.g : www.google.com">
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-slate-100">
                        <button type="submit" class="btn btn-wide bg-indigo-600 hover:bg-indigo-700 text-white border-none rounded-xl shadow-lg font-bold">Save</button>
                    </div>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop bg-slate-900/50 backdrop-blur-sm"><button>close</button></form>
    </dialog>

    <dialog id="addQuizModal" class="modal">
        <div class="modal-box modal-box-premium p-8 max-w-lg">
            <h3 class="font-bold text-lg mb-4">Create New Quiz</h3>
            <form method="POST">
                <input type="hidden" name="create_quiz" value="1">
                <div class="mb-6">
                    <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-2">Quiz Title</label>
                    <input type="text" name="quiz_title" class="form-input-premium text-xl font-bold text-indigo-600" required placeholder="e.g. Final Assessment">
                </div>
                <div class="flex justify-end gap-3">
                    <form method="dialog"><button class="btn btn-ghost">Cancel</button></form>
                    <button type="submit" class="btn btn-premium-dark rounded-xl px-6">Create & Edit Questions</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop bg-slate-900/50 backdrop-blur-sm"><button>close</button></form>
    </dialog>

    <script>
        lucide.createIcons();

        // Toggle Price Input Visibility
        function togglePrice(show) {
            const field = document.getElementById('priceField');
            if(show) field.classList.remove('hidden'); else field.classList.add('hidden');
        }

        // Switch Main Tabs (Content, Description, Options, Quiz)
        function switchMainTab(t){
            document.querySelectorAll('[id^="view-"]').forEach(e=>e.classList.add('hidden'));
            document.getElementById('view-'+t).classList.remove('hidden');
            
            document.querySelectorAll('.tab-link').forEach(e=>e.classList.remove('tab-active'));
            document.getElementById('tab-btn-'+t).classList.add('tab-active');
            
            // Persist selection via hidden input
            document.getElementById('mainRedirectTab').value = t;
            if(document.getElementById('headerRedirectTab')) document.getElementById('headerRedirectTab').value = t;
        }

        // Switch Modal Tabs (Content, Description, Attachment)
        function switchModalTab(t){
            document.querySelectorAll('.modal-tab').forEach(e=>{e.classList.remove('active');e.classList.add('inactive')});
            document.getElementById('mtab-'+t).classList.add('active');
            document.getElementById('mtab-'+t).classList.remove('inactive');
            
            document.querySelectorAll('[id^="tab-pane-"]').forEach(e=>e.classList.add('hidden'));
            document.getElementById('tab-pane-'+t).classList.remove('hidden');
        }

        // Open Modal & Populate Data
        // instructor/manage_course.php - REPLACE THIS FUNCTION

function openLessonModal(id='', title='', type='video', url='', dur='00:00', down=0, resp='', desc='') {
    // 1. Reset/Set Form Values
    document.getElementById('lessonIdInput').value = id;
    document.getElementById('lessonTitleInput').value = title;
    document.getElementById('lessonUrlInput').value = url;
    document.getElementById('durationInput').value = dur;
    document.getElementById('lessonDescInput').value = desc;
    
    // Set Responsible (Default to current user if empty)
    // We use a safe check here in case the PHP variable isn't passed correctly
    const currentInstructor = "<?= htmlspecialchars($instructor_name ?? 'Instructor') ?>";
    document.getElementById('responsibleInput').value = resp || currentInstructor;

    // Handle Download Checkbox
    const downBox = document.getElementById('downloadInput');
    if(downBox) downBox.checked = (down == 1);

    // 2. Set Modal Title
    const headerTitle = document.querySelector('#lessonModal .text-xs.font-bold');
    if(headerTitle) {
        headerTitle.innerText = (type === 'quiz') ? "Add Quiz" : (id ? "Edit Content" : "Add Content");
    }

    // 3. Handle Radio Buttons
    const radios = document.getElementsByName('lesson_type');
    let found = false;
    for (const r of radios) {
        if (r.value === type) {
            r.checked = true;
            found = true;
        }
    }
    if (!found && radios.length > 0) radios[0].checked = true;

    // 4. Toggle Visibility based on Type
    toggleFields(type);

    // 5. Handle Content URL Display
    if (type === 'video') {
        const vidInput = document.getElementById('videoLinkInput');
        if(vidInput) vidInput.value = url;
    } else if (url && type !== 'quiz') {
        const fileDisplay = document.getElementById('fileNameDisplay');
        if(fileDisplay) fileDisplay.innerText = "Current: " + url.split('/').pop();
    }

    // 6. Reset Tabs & Show Modal
    switchModalTab('content');
    document.getElementById('lessonModal').showModal();
}

        // Toggle Fields based on Type
        function toggleFields(t){
            document.getElementById('videoFields').classList.add('hidden');
            document.getElementById('fileFields').classList.add('hidden');
            document.getElementById('quizFields').classList.add('hidden');
            document.getElementById('allowDownloadContainer').classList.add('hidden');

            if(t==='video') { 
                document.getElementById('videoFields').classList.remove('hidden'); 
                document.getElementById('durationLabel').innerText="Duration (HH:MM)"; 
            }
            else if(t==='quiz') { 
                document.getElementById('quizFields').classList.remove('hidden'); 
                document.getElementById('durationLabel').innerText="Time Limit (HH:MM)"; 
            }
            else { 
                // Document or Image
                document.getElementById('fileFields').classList.remove('hidden'); 
                document.getElementById('durationLabel').innerText="Reading Time (HH:MM)"; 
                document.getElementById('allowDownloadContainer').classList.remove('hidden'); 
            }
        }

        // Toggle Video Source (Link vs Upload)
        function toggleVideoSource(s){
            if(s==='link'){
                document.getElementById('vSourceLink').classList.remove('hidden');
                document.getElementById('vSourceUpload').classList.add('hidden');
            } else {
                document.getElementById('vSourceLink').classList.add('hidden');
                document.getElementById('vSourceUpload').classList.remove('hidden');
            }
        }

        // Auto-calculate Video Duration
        function calculateDuration(i){
            const f=i.files[0];
            if(f){
                const v=document.createElement('video');
                v.preload='metadata';
                v.onloadedmetadata=function(){
                    window.URL.revokeObjectURL(v.src);
                    const d=v.duration;
                    const h=Math.floor(d/3600);
                    const m=Math.floor((d%3600)/60);
                    // Format HH:MM
                    const formatted = (h<10?"0"+h:h)+":"+(m<10?"0"+m:m);
                    document.getElementById('durationInput').value = formatted;
                };
                v.src=URL.createObjectURL(f);
            }
        }
        
        // Auto-save logic on blur (Optional)
        const inputs = document.querySelectorAll('#main-course-form input[type="text"]');
        inputs.forEach(input => {
            input.addEventListener('blur', () => {
                if(document.querySelector('input[name="title"]').value.trim() !== "") {
                    // document.getElementById('main-course-form').submit(); // Uncomment to enable auto-save
                }
            });
        });
    </script>
</body>
</html>