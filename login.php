<?php
// login.php
require 'includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // 1. Find user by email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // 2. Login Success: Save User Info in Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role']; // 'admin', 'instructor', or 'learner'
        $_SESSION['name'] = $user['name'];

        // 3. Redirect based on Role (The Traffic Cop)
        if ($user['role'] === 'admin' || $user['role'] === 'instructor') {
            header("Location: instructor/dashboard.php");
        } else {
            header("Location: student/dashboard.php");
        }
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LearnSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200 min-h-screen flex items-center justify-center">

    <div class="card w-96 bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title justify-center text-2xl font-bold mb-4">Welcome Back</h2>
            
            <?php if($error): ?>
                <div class="alert alert-error text-sm"><span><?= $error ?></span></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-control w-full">
                    <label class="label"><span class="label-text">Email</span></label>
                    <input type="email" name="email" required class="input input-bordered w-full" />
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label"><span class="label-text">Password</span></label>
                    <input type="password" name="password" required class="input input-bordered w-full" />
                </div>

                <div class="card-actions justify-end mt-6">
                    <button type="submit" class="btn btn-primary w-full">Login</button>
                </div>
            </form>
            
            <div class="text-center mt-4">
                <p class="text-sm">New here? <a href="signup.php" class="link link-primary">Create an account</a></p>
            </div>
        </div>
    </div>

</body>
</html>