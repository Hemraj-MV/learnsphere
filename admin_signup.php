<?php
// admin_signup.php (HIDDEN URL for creating Instructors)
require 'includes/db.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = 'instructor'; // <--- THIS IS THE MAGIC CHANGE

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        $msg = "Email already exists!";
    } else {
        $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$name, $email, $password, $role]);
        $msg = "Success! <a href='login.php'>Login now</a>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Instructor</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="p-10 bg-gray-100">
    <div class="max-w-md mx-auto bg-white p-8 rounded shadow">
        <h2 class="text-2xl font-bold mb-4 text-red-600">Create Instructor Account</h2>
        <?php if($msg) echo "<p class='mb-4 text-blue-600'>$msg</p>"; ?>
        
        <form method="POST">
            <input type="text" name="name" placeholder="Name" class="w-full border p-2 mb-2" required>
            <input type="email" name="email" placeholder="Email" class="w-full border p-2 mb-2" required>
            <input type="password" name="password" placeholder="Password" class="w-full border p-2 mb-4" required>
            <button class="bg-red-500 text-white w-full p-2 rounded">Create Instructor</button>
        </form>
    </div>
</body>
</html>