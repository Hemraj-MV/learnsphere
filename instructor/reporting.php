<?php
// instructor/reporting.php
require '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'instructor')) {
    header("Location: ../login.php");
    exit;
}

$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['name'] ?? 'Instructor';

// --- FULLY DYNAMIC STATS ---
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn(),
    'yet_to_start' => $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'yet_to_start'")->fetchColumn(),
    'in_progress' => $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'in_progress'")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'completed'")->fetchColumn()
];

// --- FULLY DYNAMIC TABLE DATA ---
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
    <title>Overview - LearnSphere</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f0f4ff 0%, #eef2f6 100%);
            color: #1e293b;
            min-height: 100vh;
        }
        
        h1, h2, h3, .heading-font { font-family: 'Outfit', sans-serif; letter-spacing: -0.02em; }

        .premium-header {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05);
        }

        .premium-card {
            background: #ffffff;
            border: 1px solid white;
            border-radius: 1.5rem;
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08); 
            transition: all 0.3s ease-out;
        }
        .premium-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 50px -10px rgba(37, 99, 235, 0.1); 
        }

        .stat-card { cursor: pointer; border: 2px solid transparent; }
        .stat-card.active { border-color: #2563eb; background: #f8faff; }

        .btn-outline-premium {
            background: white; border: 2px solid #e2e8f0;
            color: #64748b; font-weight: 700; font-size: 11px; text-transform: uppercase;
        }

        .premium-table thead th { 
            background: #f8fafc; color: #64748b; 
            font-weight: 700; text-transform: uppercase; 
            font-size: 10px; letter-spacing: 0.05em;
            padding: 1.25rem 1rem;
        }
        .premium-table td { font-weight: 500; font-size: 0.875rem; color: #334155; padding: 1.25rem 1rem; }

        .premium-badge {
            font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;
            padding: 4px 12px; border-radius: 20px;
        }

        /* --- FIXED SCROLLABLE DROPDOWN --- */
        .scrollable-dropdown {
            max-height: 300px; /* Limits the height */
            overflow-y: auto;  /* Enables vertical scroll */
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }
        .scrollable-dropdown::-webkit-scrollbar { width: 5px; }
        .scrollable-dropdown::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="flex flex-col">

    <header class="premium-header h-20 sticky top-0 z-50 px-8">
        <div class="max-w-7xl mx-auto h-full flex items-center justify-between">
            <div class="flex items-center gap-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-black rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-lg">LS</div>
                    <span class="heading-font font-bold text-2xl text-slate-900 tracking-tight">LearnSphere</span>
                </div>
                <nav class="hidden md:flex gap-1">
                    <a href="dashboard.php" class="px-4 py-2 text-slate-500 font-semibold hover:text-slate-900 transition">Courses</a>
                    <a href="#" class="px-4 py-2 text-slate-900 font-bold bg-white rounded-full shadow-sm">Reporting</a>
                </nav>
            </div>

            <div class="flex items-center gap-6">
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar border-2 border-white shadow-sm">
                        <div class="w-10 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold">
                            <?= strtoupper(substr($instructor_name, 0, 1)) ?>
                        </div>
                    </div>
                    <ul tabindex="0" class="mt-3 z-[1] p-2 shadow-2xl menu menu-sm dropdown-content bg-white/90 backdrop-blur-lg border border-white rounded-2xl w-52 text-slate-700">
                        <li class="px-4 py-2"><span class="font-bold text-slate-900 block"><?= htmlspecialchars($instructor_name) ?></span></li>
                        <div class="divider my-0"></div>
                        <li><a href="../logout.php" class="text-red-500 font-bold hover:bg-red-50"><i data-lucide="log-out" class="w-4 h-4"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-7xl mx-auto w-full p-8 pb-32">
        
        <div class="mb-10 mt-4">
            <h1 class="text-4xl font-extrabold text-slate-900 heading-font">Global <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-purple-600">Overview</span></h1>
            <p class="text-slate-500 font-medium text-lg">Real-time engagement telemetry.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
            <div class="premium-card stat-card p-6 active" onclick="filterTable('all', this)">
                <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center mb-4 text-blue-600">
                    <i data-lucide="users" class="w-6 h-6"></i>
                </div>
                <div class="text-4xl font-black text-slate-900 mb-1"><?= number_format($stats['total']) ?></div>
                <div class="text-xs font-bold uppercase tracking-widest text-slate-400">Total Participants</div>
            </div>

            <div class="premium-card stat-card p-6" onclick="filterTable('yet_to_start', this)">
                <div class="w-12 h-12 rounded-2xl bg-orange-50 flex items-center justify-center mb-4 text-orange-600">
                    <i data-lucide="clock" class="w-6 h-6"></i>
                </div>
                <div class="text-4xl font-black text-slate-900 mb-1"><?= number_format($stats['yet_to_start']) ?></div>
                <div class="text-xs font-bold uppercase tracking-widest text-slate-400">Yet to Start</div>
            </div>

            <div class="premium-card stat-card p-6" onclick="filterTable('in_progress', this)">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center mb-4 text-indigo-600">
                    <i data-lucide="loader" class="w-6 h-6"></i>
                </div>
                <div class="text-4xl font-black text-slate-900 mb-1"><?= number_format($stats['in_progress']) ?></div>
                <div class="text-xs font-bold uppercase tracking-widest text-slate-400">In Progress</div>
            </div>

            <div class="premium-card stat-card p-6" onclick="filterTable('completed', this)">
                <div class="w-12 h-12 rounded-2xl bg-green-50 flex items-center justify-center mb-4 text-green-600">
                    <i data-lucide="check-circle" class="w-6 h-6"></i>
                </div>
                <div class="text-4xl font-black text-slate-900 mb-1"><?= number_format($stats['completed']) ?></div>
                <div class="text-xs font-bold uppercase tracking-widest text-slate-400">Completed</div>
            </div>
        </div>

        <div class="premium-card overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-slate-50/30">
                <h3 class="font-bold text-slate-700 uppercase text-xs tracking-widest flex items-center gap-2">
                    <i data-lucide="database" class="w-4 h-4"></i> Participant Registry
                </h3>
                
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-sm btn-outline-premium rounded-xl gap-2">
                        <i data-lucide="sliders-horizontal" class="w-4 h-4"></i> Customize Display
                    </div>
                    <ul tabindex="0" class="dropdown-content z-[100] menu p-4 shadow-2xl bg-white border border-slate-100 rounded-2xl w-72 mt-3 scrollable-dropdown">
                        <li class="menu-title text-[10px] font-black text-slate-400 uppercase tracking-tighter mb-2">Toggle Data Columns</li>
                        <?php 
                        $columns = ["S.No", "Course Name", "Participant", "Enrolled Date", "Start Date", "Time Spent", "Completion %", "Completed Date", "Status"];
                        foreach($columns as $i => $name): ?>
                        <li><label class="label cursor-pointer py-2 hover:bg-slate-50 rounded-lg transition-colors">
                            <span class="text-xs font-bold text-slate-600"><?= $name ?></span> 
                            <input type="checkbox" checked onclick="toggleColumn(<?= $i ?>)" class="checkbox checkbox-sm checkbox-primary rounded-md" />
                        </label></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="table w-full premium-table" id="reportTable">
                    <thead>
                        <tr>
                            <?php foreach($columns as $i => $name): ?>
                                <th class="col-<?= $i ?>"><?= $name ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($participants as $index => $row): ?>
                            <tr class="hover:bg-slate-50/50 transition border-b border-slate-100/50" data-status="<?= $row['status'] ?>">
                                <td class="col-0 font-mono text-slate-300"><?= $index + 1 ?></td>
                                <td class="col-1 font-bold text-blue-600"><?= htmlspecialchars($row['course_title']) ?></td>
                                <td class="col-2 font-bold flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-[10px] border border-slate-200">
                                        <?= strtoupper(substr($row['student_name'], 0, 1)) ?>
                                    </div>
                                    <?= htmlspecialchars($row['student_name']) ?>
                                </td>
                                <td class="col-3 text-slate-400 text-xs font-bold"><?= date('M d, Y', strtotime($row['enrolled_at'])) ?></td>
                                <td class="col-4 text-slate-400 text-xs font-bold"><?= $row['started_at'] ? date('M d, Y', strtotime($row['started_at'])) : '-' ?></td>
                                <td class="col-5 font-mono text-orange-600 font-bold"><?= $row['time_spent'] ?? '0h 0m' ?></td>
                                <td class="col-6">
                                    <div class="flex items-center gap-2">
                                        <progress class="progress progress-primary w-16 h-1.5" value="<?= $row['progress_percent'] ?>" max="100"></progress>
                                        <span class="text-[10px] font-black"><?= $row['progress_percent'] ?>%</span>
                                    </div>
                                </td>
                                <td class="col-7 text-slate-400 text-xs font-bold"><?= $row['completed_at'] ? date('M d, Y', strtotime($row['completed_at'])) : '-' ?></td>
                                <td class="col-8">
                                    <?php 
                                        $colors = [
                                            'yet_to_start' => 'bg-slate-100 text-slate-400',
                                            'in_progress' => 'bg-blue-100 text-blue-600',
                                            'completed' => 'bg-green-100 text-green-600'
                                        ];
                                        $label = ucwords(str_replace('_', ' ', $row['status']));
                                    ?>
                                    <span class="premium-badge <?= $colors[$row['status']] ?? 'bg-slate-100' ?>"><?= $label ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();

        function filterTable(status, cardElement) {
            document.querySelectorAll('.stat-card').forEach(el => el.classList.remove('active'));
            cardElement.classList.add('active');

            const rows = document.querySelectorAll('#reportTable tbody tr');
            rows.forEach(row => {
                if (status === 'all' || row.getAttribute('data-status') === status) {
                    row.style.display = '';
                } else { row.style.display = 'none'; }
            });
        }

        function toggleColumn(colIndex) {
            const cells = document.querySelectorAll(`.col-${colIndex}`);
            const header = document.querySelector(`th.col-${colIndex}`);
            const isHidden = header.style.display === 'none';
            const newStyle = isHidden ? '' : 'none';
            header.style.display = newStyle;
            cells.forEach(cell => cell.style.display = newStyle);
        }
    </script>
</body>
</html>