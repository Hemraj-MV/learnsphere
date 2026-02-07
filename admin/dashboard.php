<?php
// admin/dashboard.php
require '../includes/db.php';
session_start();

// SECURITY: Only Admin Access 
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$success = '';
$error = '';

// CREATE INTERNAL USER LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role']; // 'instructor' or 'admin'

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $error = "User already exists!";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$name, $email, $hash, $role]);
        $success = "New $role created successfully!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 min-h-screen p-10">

    <div class="navbar bg-gray-800 shadow-xl rounded-box mb-8">
        <div class="flex-1">
            <a class="btn btn-ghost text-xl text-white">⚡ Super User Panel</a>
        </div>
        <div class="flex-none gap-2">
            <span class="text-white mr-4">Welcome, Admin</span>
            <a href="../logout.php" class="btn btn-error btn-sm">Logout</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="card bg-gray-800 border border-gray-700 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-white">Create Internal User</h2>
                <p class="text-gray-400 text-sm">Create Instructors or other Admins here.</p>
                
                <?php if($success): ?>
                    <div class="alert alert-success text-white py-2"><?= $success ?></div>
                <?php endif; ?>
                <?php if($error): ?>
                    <div class="alert alert-error text-white py-2"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-control mb-2">
                        <label class="label"><span class="label-text text-gray-400">Name</span></label>
                        <input type="text" name="name" class="input input-bordered bg-gray-700 text-white" required />
                    </div>
                    <div class="form-control mb-2">
                        <label class="label"><span class="label-text text-gray-400">Email</span></label>
                        <input type="email" name="email" class="input input-bordered bg-gray-700 text-white" required />
                    </div>
                    <div class="form-control mb-2">
                        <label class="label"><span class="label-text text-gray-400">Role</span></label>
                        <select name="role" class="select select-bordered bg-gray-700 text-white w-full">
                            <option value="instructor">Instructor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-control mb-4">
                        <label class="label"><span class="label-text text-gray-400">Password</span></label>
                        <input type="text" name="password" class="input input-bordered bg-gray-700 text-white" required />
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Create User</button>
                </form>
            </div>
        </div>

        <div class="card bg-gray-800 border border-gray-700 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-white">System Overview</h2>
                <div class="stats stats-vertical shadow bg-gray-700 text-white mt-4">
                    <div class="stat">
                        <div class="stat-title text-gray-400">Total Users</div>
                        <div class="stat-value">
                            <?php 
                                echo $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); 
                            ?>
                        </div>
                    </div>
                    <div class="stat">
                        <div class="stat-title text-gray-400">Instructors</div>
                        <div class="stat-value text-secondary">
                             <?php 
                                echo $pdo->query("SELECT COUNT(*) FROM users WHERE role='instructor'")->fetchColumn(); 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>