<?php
// instructor/create_course.php
require '../includes/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'instructor')) {
    header("Location: ../login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = $_POST['price'] ?? 0;
    $access_rule = $_POST['access_rule'];
    $instructor_id = $_SESSION['user_id'];

    // Handle File Upload (Thumbnail)
    $thumbnail_path = '';
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === 0) {
        $upload_dir = '../assets/uploads/';
        // Create unique name to prevent overwriting
        $file_name = time() . '_' . basename($_FILES['thumbnail']['name']);
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $target_file)) {
            $thumbnail_path = 'assets/uploads/' . $file_name;
        } else {
            $error = "Failed to upload thumbnail.";
        }
    }

    if (!$error) {
        // Insert into Database
        $stmt = $pdo->prepare("
            INSERT INTO courses (title, description, price, access_rule, instructor_id, thumbnail, is_published) 
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");
        
        if ($stmt->execute([$title, $description, $price, $access_rule, $instructor_id, $thumbnail_path])) {
            // Redirect to "Manage Course" to add lessons immediately
            $course_id = $pdo->lastInsertId();
            header("Location: manage_course.php?id=" . $course_id);
            exit;
        } else {
            $error = "Database Error: Could not create course.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Course - LearnSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200 min-h-screen p-10">

    <div class="max-w-2xl mx-auto bg-base-100 shadow-xl rounded-box">
        <div class="card-body">
            <h2 class="card-title text-2xl mb-6">Create New Course</h2>

            <?php if($error): ?>
                <div class="alert alert-error"><span><?= $error ?></span></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                
                <div class="form-control w-full">
                    <label class="label"><span class="label-text">Course Title</span></label>
                    <input type="text" name="title" required class="input input-bordered w-full" placeholder="e.g., Master PHP in 24 Hours" />
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label"><span class="label-text">Description</span></label>
                    <textarea name="description" required class="textarea textarea-bordered h-24" placeholder="What will students learn?"></textarea>
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label"><span class="label-text">Course Thumbnail</span></label>
                    <input type="file" name="thumbnail" accept="image/*" class="file-input file-input-bordered w-full" />
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Access Rule</span></label>
                        <select name="access_rule" class="select select-bordered" onchange="togglePrice(this.value)">
                            <option value="open">Open (Free)</option>
                            <option value="payment">Paid</option>
                            <option value="invitation">Invitation Only</option>
                        </select>
                    </div>

                    <div class="form-control" id="price-field" style="display:none;">
                        <label class="label"><span class="label-text">Price ($)</span></label>
                        <input type="number" name="price" class="input input-bordered" value="0" />
                    </div>
                </div>

                <div class="card-actions justify-end mt-8">
                    <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create & Add Lessons</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePrice(value) {
            const priceField = document.getElementById('price-field');
            if (value === 'payment') {
                priceField.style.display = 'block';
            } else {
                priceField.style.display = 'none';
            }
        }
    </script>

</body>
</html>