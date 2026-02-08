<?php
// student/certificate.php
require '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || !isset($_GET['course_id'])) {
    die("Access Denied");
}

$user_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'];

// 1. VERIFY COMPLETION
$stmt = $pdo->prepare("SELECT status, completed_at FROM enrollments WHERE student_id = ? AND course_id = ?");
$stmt->execute([$user_id, $course_id]);
$enrollment = $stmt->fetch();

if (!$enrollment || $enrollment['status'] !== 'completed') {
    // If not complete, redirect back to dashboard
    header("Location: dashboard.php");
    exit;
}

// 2. FETCH DETAILS
$user_name = $_SESSION['name'];
$course_stmt = $pdo->prepare("SELECT title, u.name as instructor FROM courses c JOIN users u ON c.instructor_id = u.id WHERE c.id = ?");
$course_stmt->execute([$course_id]);
$course = $course_stmt->fetch();

$course_title = $course['title'];
$instructor = $course['instructor'];
$date = date("F j, Y", strtotime($enrollment['completed_at']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate - <?= htmlspecialchars($course_title) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        /* PRINT OPTIMIZATION */
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; -webkit-print-color-adjust: exact; }
            .certificate-container { box-shadow: none !important; margin: 0 !important; border: 4px solid #334155 !important; page-break-inside: avoid; }
        }

        /* SCREEN STYLING */
        body { background: #f1f5f9; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Plus Jakarta Sans', sans-serif; padding: 20px; }
        
        .certificate-container {
            width: 100%; max-width: 900px;
            background: white;
            padding: 40px;
            position: relative;
            box-shadow: 0 20px 50px rgba(0,0,0,0.1);
            border-radius: 4px;
            text-align: center;
            border: 1px solid #e2e8f0;
            background-image: radial-gradient(#f8fafc 15%, transparent 16%), radial-gradient(#f8fafc 15%, transparent 16%);
            background-size: 20px 20px;
        }

        .border-frame {
            border: 2px solid #0f172a;
            padding: 40px;
            height: 100%;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .corner-decoration {
            position: absolute; width: 40px; height: 40px; border: 4px solid #6366f1;
        }
        .top-left { top: -2px; left: -2px; border-right: none; border-bottom: none; }
        .top-right { top: -2px; right: -2px; border-left: none; border-bottom: none; }
        .bottom-left { bottom: -2px; left: -2px; border-right: none; border-top: none; }
        .bottom-right { bottom: -2px; right: -2px; border-left: none; border-top: none; }

        .logo { font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 24px; color: #0f172a; display: flex; align-items: center; gap: 8px; margin-bottom: 40px; }
        .logo-box { background: #0f172a; color: white; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px; font-size: 16px; }

        h1 { font-family: 'Playfair Display', serif; font-size: 48px; color: #0f172a; margin-bottom: 10px; font-weight: 700; letter-spacing: -0.02em; }
        .subtitle { font-size: 14px; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600; margin-bottom: 40px; }

        .recipient { font-family: 'Playfair Display', serif; font-size: 42px; color: #4f46e5; font-style: italic; border-bottom: 2px solid #e2e8f0; padding: 0 40px 10px 40px; margin: 0 auto 30px auto; display: inline-block; min-width: 400px; }
        
        .description { color: #334155; font-size: 18px; max-width: 600px; margin: 0 auto 30px auto; line-height: 1.6; }
        .course-name { font-weight: 800; color: #0f172a; }

        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; margin-top: 60px; width: 80%; }
        .signature-line { border-top: 1px solid #94a3b8; padding-top: 10px; font-size: 14px; color: #0f172a; font-weight: 700; text-transform: uppercase; }
        .signature-title { font-size: 11px; color: #64748b; font-weight: 500; text-transform: none; }

        .seal {
            position: absolute; bottom: 50px; right: 50px; opacity: 0.1; transform: rotate(-15deg);
        }
    </style>
</head>
<body>

    <div class="no-print fixed top-6 right-6 flex gap-3 z-50">
        <a href="dashboard.php" class="flex items-center gap-2 px-4 py-2 bg-white text-slate-600 rounded-full shadow-lg hover:bg-slate-50 border border-slate-200 font-bold text-sm transition">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Dashboard
        </a>
        <button onclick="window.print()" class="flex items-center gap-2 px-6 py-2 bg-indigo-600 text-white rounded-full shadow-lg hover:bg-indigo-700 font-bold text-sm transition">
            <i data-lucide="download" class="w-4 h-4"></i> Download PDF
        </button>
    </div>

    <div class="certificate-container">
        <div class="border-frame">
            
            <div class="corner-decoration top-left"></div>
            <div class="corner-decoration top-right"></div>
            <div class="corner-decoration bottom-left"></div>
            <div class="corner-decoration bottom-right"></div>

            <div class="logo">
                <div class="logo-box">LS</div>
                LearnSphere
            </div>

            <h1>Certificate of Completion</h1>
            <div class="subtitle">This certificate is awarded to</div>

            <div class="recipient"><?= htmlspecialchars($user_name) ?></div>

            <div class="description">
                For successfully completing the course<br>
                <span class="course-name"><?= htmlspecialchars($course_title) ?></span><br>
                demonstrating dedication to professional growth and excellence.
            </div>

            <div class="meta-grid">
                <div>
                    <div class="text-2xl font-handwriting text-slate-600 mb-2 font-serif italic"><?= htmlspecialchars($instructor) ?></div>
                    <div class="signature-line"><?= htmlspecialchars($instructor) ?></div>
                    <div class="signature-title">Course Instructor</div>
                </div>
                <div>
                    <div class="text-xl font-bold text-slate-800 mb-2"><?= $date ?></div>
                    <div class="signature-line">Date Issued</div>
                    <div class="signature-title">LearnSphere Official</div>
                </div>
            </div>

            <div class="seal">
                <i data-lucide="award" class="w-32 h-32 text-indigo-900"></i>
            </div>

        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>