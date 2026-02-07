<?php
require 'includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = 'learner'; // PDF: Public signup is for Learners [cite: 32]

    // 1. Validation Rules [cite: 40-47]
    if (strlen($password) <= 8) {
        $error = "Password must be longer than 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[\W]/', $password)) { // \W checks for non-word characters (special)
        $error = "Password must contain at least one special character.";
    } else {
        // 2. Check if Email Exists [cite: 35]
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email already registered.";
        } else {
            // 3. Create User
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$name, $email, $hashed_password, $role])) {
                // 4. Initialize Student Profile (Gamification)
                $user_id = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO student_profiles (user_id) VALUES (?)")->execute([$user_id]);
                
                $success = "Account created! <a href='login.php' class='underline'>Login here</a>";
            } else {
                $error = "Registration failed.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - LearnSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200 min-h-screen flex items-center justify-center">

    <div class="card w-96 bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title justify-center text-2xl font-bold mb-4">Join LearnSphere</h2>
            
            <?php if($error): ?>
                <div class="alert alert-error text-sm"><span><?= $error ?></span></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success text-sm"><span><?= $success ?></span></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-control w-full">
                    <label class="label"><span class="label-text">Full Name</span></label>
                    <input type="text" name="name" required class="input input-bordered w-full" />
                </div>

                <div class="form-control w-full mt-2">
                    <label class="label"><span class="label-text">Email</span></label>
                    <input type="email" name="email" required class="input input-bordered w-full" />
                </div>

                <div class="form-control w-full mt-2">
                    <label class="label"><span class="label-text">Password</span></label>
                    <input type="password" name="password" required class="input input-bordered w-full" />
                    <label class="label">
                        <span class="label-text-alt text-gray-500">Min 8 chars, Uppercase, Lowercase, Special</span>
                    </label>
                </div>

                <div class="card-actions justify-end mt-6">
                    <button type="submit" class="btn btn-primary w-full">Sign Up</button>
                </div>
            </form>
            
            <div class="text-center mt-4">
                <a href="login.php" class="link link-hover text-sm">Already have an account? Login</a>
            </div>
        </div>
    </div>

</body>
</html>