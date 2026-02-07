<?php
// instructor/manage_course.php
require '../includes/db.php';

// 1. Security & Validation
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'instructor')) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$course_id = $_GET['id'];
$instructor_id = $_SESSION['user_id'];

// 2. Fetch Course Details (Verify ownership)
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
$stmt->execute([$course_id, $instructor_id]);
$course = $stmt->fetch();

if (!$course) {
    die("Course not found or access denied.");
}

// 3. Handle Lesson Upload
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lesson'])) {
    $title = trim($_POST['title']);
    $type = $_POST['type'];
    $text_content = $_POST['text_content'] ?? ''; // For AI RAG
    
    // File Upload Logic
    $content_url = '';
    if (isset($_FILES['lesson_file']) && $_FILES['lesson_file']['error'] === 0) {
        $upload_dir = '../assets/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true); // Create dir if missing
        
        $file_name = time() . '_' . basename($_FILES['lesson_file']['name']);
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['lesson_file']['tmp_name'], $target_file)) {
            $content_url = 'assets/uploads/' . $file_name;
        }
    } elseif ($type === 'video' && !empty($_POST['video_url'])) {
        // Allow YouTube links if no file uploaded
        $content_url = $_POST['video_url'];
    }

    // Insert Lesson
    $stmt = $pdo->prepare("INSERT INTO lessons (course_id, title, type, content_url, text_content) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$course_id, $title, $type, $content_url, $text_content])) {
        $msg = "Lesson added successfully!";
    } else {
        $msg = "Failed to add lesson.";
    }
}

// 4. Handle Publish/Unpublish
if (isset($_GET['action']) && $_GET['action'] == 'publish') {
    $pdo->prepare("UPDATE courses SET is_published = 1 WHERE id = ?")->execute([$course_id]);
    header("Location: manage_course.php?id=$course_id");
    exit;
}

// 5. Fetch Existing Lessons
$lessons_stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY id ASC");
$lessons_stmt->execute([$course_id]);
$lessons = $lessons_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Course - <?= htmlspecialchars($course['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200 min-h-screen p-6">

    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <div>
                <a href="dashboard.php" class="text-sm hover:underline">← Back to Dashboard</a>
                <h1 class="text-3xl font-bold mt-2"><?= htmlspecialchars($course['title']) ?></h1>
                <span class="badge badge-lg <?= $course['is_published'] ? 'badge-success' : 'badge-warning' ?>">
                    <?= $course['is_published'] ? 'Published' : 'Draft Mode' ?>
                </span>
            </div>
            <div class="flex gap-2">
                <?php if(!$course['is_published']): ?>
                    <a href="manage_course.php?id=<?= $course_id ?>&action=publish" class="btn btn-success text-white">Publish Course</a>
                <?php endif; ?>
                <a href="edit_course.php?id=<?= $course_id ?>" class="btn btn-outline">Edit Info</a>
            </div>
        </div>

        <?php if($msg): ?>
            <div class="alert alert-success mb-4"><span><?= $msg ?></span></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="card bg-base-100 shadow-xl col-span-1 h-fit">
                <div class="card-body">
                    <h2 class="card-title text-lg">Add New Lesson</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="add_lesson" value="1">
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Lesson Title</span></label>
                            <input type="text" name="title" required class="input input-bordered input-sm" />
                        </div>

                        <div class="form-control mt-2">
                            <label class="label"><span class="label-text">Type</span></label>
                            <select name="type" class="select select-bordered select-sm" onchange="toggleType(this.value)">
                                <option value="video">Video</option>
                                <option value="document">PDF Document</option>
                            </select>
                        </div>

                        <div class="form-control mt-2">
                            <label class="label"><span class="label-text">Upload File</span></label>
                            <input type="file" name="lesson_file" class="file-input file-input-bordered file-input-sm w-full" />
                        </div>

                        <div class="form-control mt-2" id="video-url-group">
                            <label class="label"><span class="label-text">Or YouTube URL</span></label>
                            <input type="text" name="video_url" class="input input-bordered input-sm" placeholder="https://youtube.com/..." />
                        </div>

                        <div class="form-control mt-2">
                            <label class="label">
                                <span class="label-text">Text Content (For AI Tutor)</span>
                                <span class="label-text-alt text-gray-400">Paste transcript/text here</span>
                            </label>
                            <textarea name="text_content" class="textarea textarea-bordered h-20 text-xs"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm mt-4 w-full">Add Lesson</button>
                    </form>
                </div>
            </div>

            <div class="col-span-2">
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title mb-4">Course Content</h2>
                        
                        <?php if (count($lessons) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="table w-full">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lessons as $index => $lesson): ?>
                                            <tr class="hover">
                                                <th><?= $index + 1 ?></th>
                                                <td><?= htmlspecialchars($lesson['title']) ?></td>
                                                <td>
                                                    <?php if($lesson['type'] == 'video'): ?>
                                                        <span class="badge badge-info badge-outline">Video</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning badge-outline">PDF</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-xs btn-ghost">Edit</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-10 text-gray-500">
                                <p>No lessons yet. Add your first one on the left!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        function toggleType(val) {
            const vidGroup = document.getElementById('video-url-group');
            if(val === 'document') {
                vidGroup.style.display = 'none';
            } else {
                vidGroup.style.display = 'block';
            }
        }
    </script>
</body>
</html>
