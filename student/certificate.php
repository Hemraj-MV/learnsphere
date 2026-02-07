<?php
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['course_id'])) {
    die("Access Denied");
}

$user_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'];

// 1. Verify Completion
$stmt = $pdo->prepare("SELECT status, completed_at FROM enrollments WHERE user_id = ? AND course_id = ?");
$stmt->execute([$user_id, $course_id]);
$enrollment = $stmt->fetch();

if (!$enrollment || $enrollment['status'] !== 'completed') {
    die("You must complete the course to get this certificate.");
}

// 2. Fetch Details
$user_name = $_SESSION['name'];
$course_stmt = $pdo->prepare("SELECT title FROM courses WHERE id = ?");
$course_stmt->execute([$course_id]);
$course_title = $course_stmt->fetchColumn();
$date = date("F j, Y", strtotime($enrollment['completed_at']));

?>
<!DOCTYPE html>
<html>
<head>
    <title>Certificate of Completion</title>
    <style>
        body { margin: 0; padding: 0; background: #f0f0f0; font-family: 'Georgia', serif; }
        .certificate-container {
            width: 800px; height: 600px; margin: 50px auto;
            background: #fff; padding: 20px; text-align: center;
            border: 10px solid #78350f; /* Brown Border */
            position: relative;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
        }
        .inner-border {
            border: 2px solid #999; height: 100%; padding: 20px;
            box-sizing: border-box; position: relative;
        }
        h1 { font-size: 50px; color: #78350f; margin-bottom: 10px; }
        h2 { font-size: 25px; color: #333; margin-top: 0; }
        .recipient { font-size: 40px; font-weight: bold; border-bottom: 2px solid #ccc; display: inline-block; padding: 10px 50px; margin: 20px 0; color: #000; }
        .course { font-size: 30px; font-weight: bold; color: #1d4ed8; margin: 20px 0; }
        .date { font-size: 18px; margin-top: 40px; }
        .seal {
            position: absolute; bottom: 50px; right: 50px;
            width: 100px; height: 100px; background: #d97706;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: white; font-weight: bold; box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .btn-print {
            position: fixed; top: 20px; right: 20px; padding: 10px 20px;
            background: #2563eb; color: white; text-decoration: none; font-family: sans-serif;
            border-radius: 5px; cursor: pointer;
        }
        @media print { .btn-print { display: none; } body { background: white; } .certificate-container { margin: 0; box-shadow: none; } }
    </style>
</head>
<body>

    <a onclick="window.print()" class="btn-print">🖨️ Print / Save as PDF</a>

    <div class="certificate-container">
        <div class="inner-border">
            <br><br>
            <h1>Certificate of Completion</h1>
            <h2>This is to certify that</h2>
            
            <div class="recipient"><?= htmlspecialchars($user_name) ?></div>
            
            <h2>has successfully completed the course</h2>
            
            <div class="course"><?= htmlspecialchars($course_title) ?></div>
            
            <div class="date">Awarded on <?= $date ?></div>

            <div class="seal">
                LEARN<br>SPHERE
            </div>
            
            <div style="margin-top: 50px; display: flex; justify-content: space-around;">
                <div>
                    _______________________<br>
                    <b>Instructor Signature</b>
                </div>
                <div>
                    _______________________<br>
                    <b>Director Signature</b>
                </div>
            </div>
        </div>
    </div>

</body>
</html>