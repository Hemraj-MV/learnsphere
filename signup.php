<?php
// signup.php
require 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (isset($_SESSION['user_id'])) {
    header("Location: student/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Strict Validation Logic
    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) <= 8 || !preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[\W]/", $password)) { 
        $error = "Password requirements not met.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email Id already exists.";
        } else {
            $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'learner')";
            $pdo->prepare($sql)->execute([$name, $email, $hashed_pass]);
            
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['role'] = 'learner';
            $_SESSION['name'] = $name;
            $pdo->prepare("INSERT INTO student_profiles (user_id) VALUES (?)")->execute([$_SESSION['user_id']]);

            header("Location: student/dashboard.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Sign Up - LearnSphere</title>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Urbanist', sans-serif; }
        .fade-in { animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Validation Dot Transitions */
        .dot { transition: all 0.3s ease; }
        .valid { background-color: #10b981; border-color: #10b981; }
        .invalid { background-color: #e5e7eb; border-color: #d1d5db; }
        .text-valid { color: #047857; }
        .text-invalid { color: #6b7280; }
    </style>
</head>
<body class="bg-white h-screen overflow-hidden flex">

    <div class="hidden lg:flex flex-col justify-between w-[45%] bg-blue-900 text-white p-12 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-full bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-10"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[500px] h-[500px] bg-blue-500 rounded-full blur-[150px] opacity-30"></div>

        <div class="relative z-10">
            <div class="flex items-center gap-3 text-2xl font-bold tracking-tight">
                <span class="bg-white text-blue-900 p-2 rounded-lg">🎓</span> LearnSphere
            </div>
        </div>

        <div class="relative z-10 mb-20">
            <h1 class="text-5xl font-bold leading-tight mb-6">Start your learning <br>journey.</h1>
            <p class="text-blue-200 text-lg max-w-sm">Create an account to access unlimited courses, quizzes, and certificates.</p>
        </div>

        <div class="relative z-10 text-sm text-blue-300">
            © 2026 LearnSphere Inc.
        </div>
    </div>

    <div class="w-full lg:w-[55%] flex items-center justify-center p-6 bg-gray-50 h-full overflow-y-auto">
        <div class="w-full max-w-lg fade-in bg-white p-10 rounded-3xl shadow-xl border border-gray-100 my-auto">
            
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-gray-900">Create Account</h2>
                <p class="text-gray-500 mt-2">Join us in seconds.</p>
            </div>

            <?php if($error): ?>
                <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-100 flex items-center gap-3 text-red-600">
                    <span class="font-bold">Error:</span> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" onsubmit="return validatePassword()" class="space-y-5">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Full Name</label>
                        <input type="text" name="name" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 outline-none font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Email</label>
                        <input type="email" name="email" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 outline-none font-medium">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Password</label>
                    <input type="password" id="pass" name="password" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 outline-none font-medium">
                    
                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs font-semibold">
                        <div id="rule-len" class="flex items-center gap-2 text-invalid"><div class="dot w-2 h-2 rounded-full border bg-gray-200"></div> Min 9 Chars</div>
                        <div id="rule-upper" class="flex items-center gap-2 text-invalid"><div class="dot w-2 h-2 rounded-full border bg-gray-200"></div> Uppercase</div>
                        <div id="rule-lower" class="flex items-center gap-2 text-invalid"><div class="dot w-2 h-2 rounded-full border bg-gray-200"></div> Lowercase</div>
                        <div id="rule-spec" class="flex items-center gap-2 text-invalid"><div class="dot w-2 h-2 rounded-full border bg-gray-200"></div> Special Char</div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Re-enter Password</label>
                    <input type="password" id="confirm_pass" name="confirm_password" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 outline-none font-medium">
                </div>

                <button type="submit" class="w-full py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-lg shadow-lg shadow-blue-500/30 transition-all duration-300 mt-2">
                    Create Account
                </button>
            </form>

            <div class="text-center mt-6">
                <p class="text-sm text-gray-500">Already have an account? <a href="login.php" class="text-blue-600 font-bold hover:underline">Log in</a></p>
            </div>
        </div>
    </div>

    <script>
        const passInput = document.getElementById('pass');
        
        passInput.addEventListener('input', function() {
            const val = this.value;
            updateRule('rule-len', val.length > 8);
            updateRule('rule-upper', /[A-Z]/.test(val));
            updateRule('rule-lower', /[a-z]/.test(val));
            updateRule('rule-spec', /[\W_]/.test(val));
        });

        function updateRule(id, isValid) {
            const container = document.getElementById(id);
            const dot = container.querySelector('.dot');
            if(isValid) {
                container.classList.remove('text-invalid');
                container.classList.add('text-valid');
                dot.classList.remove('invalid');
                dot.classList.add('valid');
            } else {
                container.classList.add('text-invalid');
                container.classList.remove('text-valid');
                dot.classList.add('invalid');
                dot.classList.remove('valid');
            }
        }

        function validatePassword() {
            const pass = document.getElementById('pass').value;
            const confirm = document.getElementById('confirm_pass').value;
            const strongRegex = new RegExp("^(?=.*[a-z])(?=.*[A-Z])(?=.*[\\W_]).{9,}$");

            if (pass !== confirm) { alert("Passwords do not match"); return false; }
            if (!strongRegex.test(pass)) { alert("Please meet all password requirements."); return false; }
            return true;
        }
    </script>
</body>
</html>