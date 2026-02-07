<?php
// instructor/reporting.php
require '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'instructor')) {
    header("Location: ../login.php");
    exit;
}

$instructor_id = $_SESSION['user_id'];

// Fetch stats (Mock logic for now, or real if data exists)
// In a real app, you would filter this by a specific Course ID selected from a dropdown
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn(),
    'yet_to_start' => $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'yet_to_start'")->fetchColumn(),
    'in_progress' => $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'in_progress'")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'completed'")->fetchColumn()
];

// Fetch Table Data
$sql = "SELECT e.*, u.name as student_name, c.title as course_title 
        FROM enrollments e 
        JOIN users u ON e.student_id = u.id 
        JOIN courses c ON e.course_id = c.id
        ORDER BY e.enrolled_at DESC";
$participants = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Course Reporting - LearnSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        
        /* Card Styling to match wireframe functionality */
        .stat-card {
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid #e2e8f0;
        }
        .stat-card:hover, .stat-card.active {
            border-color: #2563eb;
            background-color: #eff6ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1);
        }
        
        /* Table Styling */
        .premium-table th { font-weight: 600; color: #64748b; font-size: 0.875rem; }
        .premium-table td { font-size: 0.875rem; color: #334155; font-weight: 500; }
        
        /* Checkbox customization */
        .custom-checkbox:checked {
            background-color: #2563eb;
            border-color: #2563eb;
        }
    </style>
</head>
<body class="p-8">

    <div class="max-w-7xl mx-auto mb-8 flex justify-between items-center">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="dashboard.php" class="text-gray-400 hover:text-gray-600"><i data-lucide="arrow-left" class="w-5 h-5"></i></a>
                <span class="text-sm font-bold text-orange-600 bg-orange-50 px-2 py-1 rounded border border-orange-100">Course Report</span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">Basics of Odoo CRM</h1> </div>
        
        <div class="flex -space-x-3">
            <div class="w-10 h-10 rounded-full bg-blue-500 text-white flex items-center justify-center border-2 border-white text-xs">P</div>
            <div class="w-10 h-10 rounded-full bg-green-500 text-white flex items-center justify-center border-2 border-white text-xs">R</div>
            <div class="w-10 h-10 rounded-full bg-purple-500 text-white flex items-center justify-center border-2 border-white text-xs">V</div>
            <div class="w-10 h-10 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center border-2 border-white text-xs font-bold">+19</div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto">
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white p-6 rounded-2xl shadow-sm" onclick="filterTable('all', this)">
                <div class="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center mb-4 text-gray-600">
                    <i data-lucide="users" class="w-6 h-6"></i>
                </div>
                <div class="text-4xl font-bold text-gray-900 mb-1"><?= $stats['total'] ?></div>
                <div class="text-sm font-medium text-gray-500">Total Participants</div>
            </div>

            <div class="stat-card bg-white p-6 rounded-2xl shadow-sm" onclick="filterTable('yet_to_start', this)">
                <div class="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center mb-4 text-orange-600">
                    <i data-lucide="clock" class="w-6 h-6"></i>
                </div>
                <div class="text-4xl font-bold text-gray-900 mb-1"><?= $stats['yet_to_start'] ?></div>
                <div class="text-sm font-medium text-gray-500">Yet to Start</div>
            </div>

            <div class="stat-card bg-white p-6 rounded-2xl shadow-sm" onclick="filterTable('in_progress', this)">
                <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center mb-4 text-blue-600">
                    <i data-lucide="loader" class="w-6 h-6"></i>
                </div>
                <div class="text-4xl font-bold text-gray-900 mb-1"><?= $stats['in_progress'] ?></div>
                <div class="text-sm font-medium text-gray-500">In Progress</div>
            </div>

            <div class="stat-card bg-white p-6 rounded-2xl shadow-sm" onclick="filterTable('completed', this)">
                <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center mb-4 text-green-600">
                    <i data-lucide="check-circle" class="w-6 h-6"></i>
                </div>
                <div class="text-4xl font-bold text-gray-900 mb-1"><?= $stats['completed'] ?></div>
                <div class="text-sm font-medium text-gray-500">Completed</div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            
            <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <div class="font-bold text-gray-700">Users List</div>
                
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-sm btn-outline bg-white hover:bg-gray-50 border-gray-300 text-gray-600 gap-2">
                        <i data-lucide="sliders-horizontal" class="w-4 h-4"></i> Customize Table
                    </div>
                    <ul tabindex="0" class="dropdown-content z-[1] menu p-3 shadow-xl bg-white rounded-xl w-60 border border-gray-100 mt-2">
                        <li class="menu-title text-xs font-bold text-gray-400 mb-2">PICK COLUMNS</li>
                        <li><label class="label cursor-pointer"><span class="label-text">Course Name</span> <input type="checkbox" checked onclick="toggleColumn(1)" class="checkbox checkbox-xs checkbox-primary" /></label></li>
                        <li><label class="label cursor-pointer"><span class="label-text">Enrolled Date</span> <input type="checkbox" checked onclick="toggleColumn(3)" class="checkbox checkbox-xs checkbox-primary" /></label></li>
                        <li><label class="label cursor-pointer"><span class="label-text">Start Date</span> <input type="checkbox" checked onclick="toggleColumn(4)" class="checkbox checkbox-xs checkbox-primary" /></label></li>
                        <li><label class="label cursor-pointer"><span class="label-text">Time Spent</span> <input type="checkbox" checked onclick="toggleColumn(5)" class="checkbox checkbox-xs checkbox-primary" /></label></li>
                        <li><label class="label cursor-pointer"><span class="label-text">Completion %</span> <input type="checkbox" checked onclick="toggleColumn(6)" class="checkbox checkbox-xs checkbox-primary" /></label></li>
                    </ul>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="table w-full premium-table" id="reportTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="w-16">S.No.</th>
                            <th class="col-1">Course Name</th>
                            <th>Participant Name</th>
                            <th class="col-3">Enrolled Date</th>
                            <th class="col-4">Start Date</th>
                            <th class="col-5">Time Spent</th>
                            <th class="col-6">Completion %</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($participants) > 0): ?>
                            <?php foreach($participants as $index => $row): ?>
                                <tr class="hover:bg-gray-50/50 transition border-b border-gray-100" data-status="<?= $row['status'] ?>">
                                    <td class="font-mono text-gray-400"><?= $index + 1 ?></td>
                                    <td class="col-1 font-semibold text-blue-600"><?= htmlspecialchars($row['course_title']) ?></td>
                                    <td class="font-bold flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-400 to-blue-600 text-white flex items-center justify-center text-xs">
                                            <?= strtoupper(substr($row['student_name'], 0, 1)) ?>
                                        </div>
                                        <?= htmlspecialchars($row['student_name']) ?>
                                    </td>
                                    <td class="col-3 text-gray-500"><?= date('M d', strtotime($row['enrolled_at'])) ?></td>
                                    <td class="col-4 text-gray-500"><?= $row['started_at'] ? date('M d', strtotime($row['started_at'])) : '-' ?></td>
                                    <td class="col-5 font-mono text-orange-600 font-bold"><?= $row['time_spent'] ?></td>
                                    <td class="col-6">
                                        <div class="flex items-center gap-2">
                                            <progress class="progress progress-primary w-16" value="<?= $row['progress_percent'] ?>" max="100"></progress>
                                            <span class="text-xs font-bold"><?= $row['progress_percent'] ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $statusColors = [
                                                'yet_to_start' => 'bg-gray-100 text-gray-600',
                                                'in_progress' => 'bg-blue-100 text-blue-700',
                                                'completed' => 'bg-green-100 text-green-700'
                                            ];
                                            $badge = $statusColors[$row['status']] ?? 'bg-gray-100';
                                            $label = ucwords(str_replace('_', ' ', $row['status']));
                                        ?>
                                        <span class="px-2 py-1 rounded text-xs font-bold <?= $badge ?>"><?= $label ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center py-10 text-gray-400">No participants found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Initialize Icons
        lucide.createIcons();

        // Filter Logic (Clicking Cards)
        function filterTable(status, cardElement) {
            // Highlight active card
            document.querySelectorAll('.stat-card').forEach(el => el.classList.remove('active', 'border-blue-500', 'bg-blue-50'));
            if(status !== 'all') {
                cardElement.classList.add('active');
            }

            const rows = document.querySelectorAll('#reportTable tbody tr');
            rows.forEach(row => {
                if (status === 'all' || row.getAttribute('data-status') === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Column Toggle Logic
        function toggleColumn(colIndex) {
            const cells = document.querySelectorAll(`.col-${colIndex}`);
            const header = document.querySelector(`th.col-${colIndex}`);
            
            // Toggle Display
            const isHidden = header.style.display === 'none';
            const newStyle = isHidden ? '' : 'none';
            
            header.style.display = newStyle;
            cells.forEach(cell => cell.style.display = newStyle);
        }
    </script>
</body>
</html>