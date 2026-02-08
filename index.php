<?php
// index.php
require 'includes/db.php';

// Fetch Published Courses for the catalog
// We limit to 6 for the landing page
$stmt = $pdo->query("
    SELECT c.*, u.name as instructor_name, 
    (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count
    FROM courses c 
    JOIN users u ON c.instructor_id = u.id 
    WHERE c.is_published = 1 
    ORDER BY c.views DESC LIMIT 6
");
$courses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>LearnSphere - Master New Skills</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    
    <style>
        /* LANDING THEME */
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #ffffff; color: #1e293b; overflow-x: hidden; }
        h1, h2, h3, .heading-font { font-family: 'Outfit', sans-serif; letter-spacing: -0.02em; }

        /* HERO SECTION */
        .hero-bg { 
            background: radial-gradient(circle at 50% 0%, #f0f4ff 0%, #ffffff 100%); 
            position: relative;
        }
        .hero-bg::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 100%;
            background-image: linear-gradient(#e0e7ff 1px, transparent 1px), linear-gradient(90deg, #e0e7ff 1px, transparent 1px);
            background-size: 40px 40px;
            opacity: 0.3;
            mask-image: linear-gradient(to bottom, black 40%, transparent 100%);
        }

        /* NAVBAR */
        .glass-nav { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(0,0,0,0.05); transition: all 0.3s ease; }
        
        /* CARDS */
        .course-card { 
            border: 1px solid #f1f5f9; border-radius: 20px; overflow: hidden; transition: all 0.3s ease; background: white; cursor: pointer;
        }
        .course-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px -10px rgba(79, 70, 229, 0.1); border-color: #e0e7ff; }

        /* BUTTONS */
        .btn-primary-hero { background: #4f46e5; color: white; border: none; padding: 0 32px; border-radius: 12px; font-weight: 700; transition: 0.2s; box-shadow: 0 10px 30px -5px rgba(79, 70, 229, 0.4); }
        .btn-primary-hero:hover { background: #4338ca; transform: translateY(-2px); }
        
        html { scroll-behavior: smooth; }
    </style>
</head>
<body>

    <nav class="glass-nav fixed top-0 w-full z-50 h-20 flex items-center">
        <div class="max-w-7xl mx-auto w-full px-6 flex justify-between items-center">
            <a href="index.php" class="flex items-center gap-3 group">
                <div class="w-10 h-10 bg-black rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-lg group-hover:scale-110 transition-transform">LS</div>
                <span class="heading-font font-bold text-2xl text-slate-900 tracking-tight">LearnSphere</span>
            </a>
            
            <div class="hidden md:flex items-center gap-8">
                <a href="#courses" class="text-sm font-bold text-slate-500 hover:text-indigo-600 transition">Explore</a>
                <a href="#features" class="text-sm font-bold text-slate-500 hover:text-indigo-600 transition">Features</a>
                <a href="login.php" class="text-sm font-bold text-slate-500 hover:text-indigo-600 transition">Instructor Access</a>
            </div>

            <div class="flex items-center gap-4">
                <a href="login.php" class="text-sm font-bold text-slate-600 hover:text-indigo-600">Log in</a>
                <a href="login.php?mode=signup" class="btn btn-sm bg-slate-900 text-white border-none rounded-lg hover:bg-slate-700 shadow-lg shadow-slate-300">Get Started</a>
            </div>
        </div>
    </nav>

    <section class="hero-bg pt-40 pb-24 px-6">
        <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-16 items-center relative z-10">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-600 text-xs font-bold uppercase tracking-wider mb-6 animate-pulse">
                    <span class="w-2 h-2 rounded-full bg-indigo-600"></span> New Platform Launch
                </div>
                <h1 class="text-5xl md:text-7xl font-black text-slate-900 leading-[1.1] mb-6">
                    Unlock your <br> <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600">potential</span> today.
                </h1>
                <p class="text-lg text-slate-500 mb-8 max-w-lg leading-relaxed">
                    Access world-class courses from top instructors. Learn at your own pace, track your progress, and earn certificates.
                </p>
                <div class="flex flex-wrap gap-4">
                    <a href="#courses" class="btn btn-lg btn-primary-hero h-14 flex items-center gap-2">
                        Browse Courses <i data-lucide="arrow-down" class="w-5 h-5"></i>
                    </a>
                    <a href="login.php" class="btn btn-lg btn-ghost border border-slate-200 text-slate-600 hover:bg-slate-50 h-14 rounded-xl px-8">
                        Student Login
                    </a>
                </div>
                
                <div class="mt-12 flex items-center gap-8 text-slate-300 grayscale opacity-60">
                    <div class="flex gap-2 font-bold text-xl"><i data-lucide="hexagon" class="w-6 h-6"></i> Stripe</div>
                    <div class="flex gap-2 font-bold text-xl"><i data-lucide="triangle" class="w-6 h-6"></i> Vercel</div>
                    <div class="flex gap-2 font-bold text-xl"><i data-lucide="circle" class="w-6 h-6"></i> Linear</div>
                </div>
            </div>
            
            <div class="relative hidden lg:block">
                <div class="relative z-10 bg-white rounded-2xl shadow-2xl p-4 border border-slate-100 transform rotate-2 hover:rotate-0 transition duration-500 cursor-default">
                    <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=2671&auto=format&fit=crop" class="rounded-xl w-full object-cover h-[400px]">
                    
                    <div class="absolute -bottom-6 -left-6 bg-white p-4 rounded-xl shadow-xl border border-slate-50 flex items-center gap-4 animate-bounce" style="animation-duration: 3s;">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center text-green-600"><i data-lucide="check-circle" class="w-6 h-6"></i></div>
                        <div>
                            <div class="text-sm font-bold text-slate-900">Course Completed</div>
                            <div class="text-xs text-slate-500">You earned a certificate!</div>
                        </div>
                    </div>
                </div>
                <div class="absolute top-10 right-10 w-full h-full bg-indigo-600/5 rounded-2xl -z-10 transform rotate-6"></div>
            </div>
        </div>
    </section>

    <section id="features" class="border-y border-slate-100 bg-white py-12">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            <div class="p-4 hover:bg-slate-50 rounded-2xl transition">
                <div class="text-4xl font-black text-slate-900 mb-1">12k+</div>
                <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Active Learners</div>
            </div>
            <div class="p-4 hover:bg-slate-50 rounded-2xl transition">
                <div class="text-4xl font-black text-slate-900 mb-1">850+</div>
                <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Courses Added</div>
            </div>
            <div class="p-4 hover:bg-slate-50 rounded-2xl transition">
                <div class="text-4xl font-black text-slate-900 mb-1">120</div>
                <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Expert Mentors</div>
            </div>
            <div class="p-4 hover:bg-slate-50 rounded-2xl transition">
                <div class="text-4xl font-black text-slate-900 mb-1">4.9</div>
                <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">User Rating</div>
            </div>
        </div>
    </section>

    <section id="courses" class="py-24 px-6 bg-slate-50/50">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-end mb-12">
                <div>
                    <h2 class="text-3xl font-bold text-slate-900 mb-2">Featured Courses</h2>
                    <p class="text-slate-500">Hand-picked by our editors for you.</p>
                </div>
                <a href="login.php" class="btn btn-sm btn-ghost text-indigo-600 font-bold hidden md:flex hover:bg-indigo-50">View All <i data-lucide="arrow-right" class="w-4 h-4 ml-1"></i></a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if(empty($courses)): ?>
                    <div class="col-span-3 text-center py-20 bg-white rounded-3xl border border-dashed border-slate-200">
                        <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300">
                            <i data-lucide="book" class="w-8 h-8"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900">No courses yet</h3>
                        <p class="text-slate-400 font-medium mb-6">Be the first instructor to publish a course.</p>
                        <a href="login.php" class="btn btn-primary bg-indigo-600 border-none">Become an Instructor</a>
                    </div>
                <?php else: ?>
                    <?php foreach($courses as $c): ?>
                        <a href="course_details.php?id=<?= $c['id'] ?>" class="course-card group block h-full flex flex-col">
                            <div class="relative h-56 overflow-hidden">
                                <img src="<?= htmlspecialchars($c['image'] ?: 'assets/default_course.jpg') ?>" class="w-full h-full object-cover transition duration-700 group-hover:scale-105">
                                <div class="absolute top-3 right-3 bg-white/90 backdrop-blur px-3 py-1 rounded-lg text-xs font-bold text-slate-900 shadow-sm border border-white/50">
                                    <?= ($c['price'] > 0) ? '$'.number_format($c['price'], 2) : 'Free' ?>
                                </div>
                            </div>
                            <div class="p-6 flex flex-col flex-1">
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="px-2 py-1 bg-indigo-50 text-indigo-600 text-[10px] font-bold uppercase rounded-md tracking-wider border border-indigo-100">
                                        <?= htmlspecialchars(explode(',', $c['tags'])[0] ?? 'General') ?>
                                    </span>
                                    <span class="text-xs text-slate-400 font-medium flex items-center gap-1 ml-auto">
                                        <i data-lucide="play-circle" class="w-3 h-3"></i> <?= $c['lesson_count'] ?> Lessons
                                    </span>
                                </div>
                                
                                <h3 class="text-lg font-bold text-slate-900 mb-2 line-clamp-2 group-hover:text-indigo-600 transition-colors">
                                    <?= htmlspecialchars($c['title']) ?>
                                </h3>
                                
                                <div class="mt-auto pt-4 border-t border-slate-50 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-600">
                                            <?= strtoupper(substr($c['instructor_name'], 0, 1)) ?>
                                        </div>
                                        <span class="text-xs font-bold text-slate-500 truncate max-w-[100px]"><?= htmlspecialchars($c['instructor_name']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-1 text-xs font-bold text-slate-400">
                                        <i data-lucide="clock" class="w-3 h-3"></i> <?= $c['duration'] ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="mt-12 text-center md:hidden">
                <a href="login.php" class="btn btn-outline border-slate-300 text-slate-600 w-full">View All Courses</a>
            </div>
        </div>
    </section>

    <footer class="bg-white border-t border-slate-100 py-12 px-6">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-black rounded-lg flex items-center justify-center text-white font-bold">LS</div>
                <span class="font-bold text-slate-900">LearnSphere</span>
            </div>
            <div class="text-slate-400 text-sm font-medium text-center md:text-left">
                &copy; <?= date('Y') ?> LearnSphere Inc. All rights reserved. <br>
                <a href="#" class="hover:text-indigo-600">Privacy</a> • <a href="#" class="hover:text-indigo-600">Terms</a>
            </div>
            <div class="flex gap-6">
                <a href="#" class="text-slate-400 hover:text-slate-900 transition"><i data-lucide="twitter" class="w-5 h-5"></i></a>
                <a href="#" class="text-slate-400 hover:text-slate-900 transition"><i data-lucide="github" class="w-5 h-5"></i></a>
                <a href="#" class="text-slate-400 hover:text-slate-900 transition"><i data-lucide="linkedin" class="w-5 h-5"></i></a>
            </div>
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>