<?php
session_start();
require_once __DIR__ . '/config/db.php';

$conn = get_db_connection();

$school_count = 18;
$teacher_count = 245;
$student_count = 6850;

if ($conn) {
    $res = $conn->query("SELECT COUNT(*) as total FROM schools");
    if ($res) $school_count = (int)$res->fetch_assoc()['total'];

    $res = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='teacher'");
    if ($res) $teacher_count = (int)$res->fetch_assoc()['total'];

    $res = $conn->query("SELECT COUNT(*) as total FROM students WHERE status='active'");
    if ($res) $student_count = (int)$res->fetch_assoc()['total'];

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NED-SEMS | Northern Education Division</title>

    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/landing.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --forest: #14532D;
            --forest-deep: #0E3D21;
            --ochre: #E0932C;
            --ink: #1B1B1B;
            --bone: #FAF7F2;
            --bone-dim: #F0EAD9;
            --line: #DDD2B4;
            --muted: #6B6455;
            --font-display: 'Space Grotesk', 'Segoe UI', sans-serif;
            --font-body: 'Work Sans', 'Segoe UI', Arial, sans-serif;
        }

        * { box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-body);
            background: var(--bone);
            color: var(--ink);
            line-height: 1.6;
            margin-left: 260px;
        }

        .container {
            max-width: 1080px;
            margin: 0 auto;
            padding: 0 28px;
        }

        a { color: inherit; }

        /* -------- Left image sidebar -------- */
        .exam-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 260px;
            overflow: hidden;
            background: var(--forest-deep);
            box-shadow: 4px 0 24px rgba(0,0,0,0.12);
            z-index: 40;
        }

        .exam-sidebar .slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 1.1s ease;
        }
        .exam-sidebar .slide.active { opacity: 1; }

        .exam-sidebar .slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .exam-sidebar .slide-caption {
            position: absolute;
            left: 0; right: 0; bottom: 0;
            padding: 22px 20px 20px;
            background: linear-gradient(to top, rgba(14,61,33,0.92), transparent);
            color: var(--bone);
            font-family: var(--font-display);
            font-size: 13.5px;
            font-weight: 500;
            line-height: 1.4;
        }

        .exam-sidebar .dots {
            position: absolute;
            top: 20px;
            left: 20px;
            display: flex;
            gap: 6px;
            z-index: 5;
        }
        .exam-sidebar .dots span {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: rgba(250,247,242,0.4);
            transition: background 0.3s ease;
        }
        .exam-sidebar .dots span.active { background: var(--ochre); }

        @media (prefers-reduced-motion: reduce) {
            .exam-sidebar .slide { transition: none; }
        }

        @media (max-width: 980px) {
            body { margin-left: 0; }
            .exam-sidebar { display: none; }
        }

        /* -------- Improved Hero -------- */

                section.hero {
                    padding: 90px 0 100px;
                    background:
                        linear-gradient(
                            135deg,
                            rgba(250,247,242,0.98),
                            rgba(240,234,217,0.95)
                        );
                }


                .hero .eyebrow {
                    font-family: var(--font-display);
                    font-size: 15px;
                    font-weight: 700;
                    letter-spacing: 1px;
                    text-transform: uppercase;
                    color: #14532D;
                    margin-bottom: 20px;
                }


                .hero h1 {

                    font-family: var(--font-display);

                    font-size: 56px;

                    line-height: 1.05;

                    font-weight: 700;

                    color: #0E3D21;

                    letter-spacing: -1.5px;

                    margin-bottom: 25px;

                }
                .hero .hero-inner {
                    display:grid;
                    grid-template-columns:1.05fr 0.95fr;
                    gap:30px;
                    align-items:center;
                }


                .hero .hero-sub {

                    font-size: 18px;

                    line-height: 1.8;

                    max-width: 520px;

                    color: #4A463D;

                    margin-bottom: 35px;

                }

        /* -------- Hub map (signature element) -------- */
        .hub-wrap { display: flex; justify-content: center; }
        .hub-wrap svg { width: 100%; max-width: 400px; height: auto; }
        .hub-wrap .hub-region { fill: var(--forest, #cad4ce); opacity: 0.06; }
        .hub-wrap .hub-spoke {
            stroke: var(--forest, #14532D);
            stroke-width: 1.4;
            stroke-dasharray: 3 4;
            opacity: 0.55;
        }
        .hub-wrap .hub-center circle { fill: var(--forest, #14532D); }
        .hub-wrap .hub-center text { fill: var(--bone, #FAF7F2); font-family: var(--font-display); font-weight: 600; }
        .hub-wrap .hub-node circle { fill: var(--bone, #FAF7F2); stroke: var(--ochre, #E0932C); stroke-width: 2.5; }
        .hub-wrap .hub-node text {
            fill: var(--ink, #1B1B1B);
            font-family: var(--font-body);
            font-size: 11px;
            font-weight: 500;
        }

        /* -------- Stats -------- */
        .stats {
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            padding: 44px 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
        }
        .stat-item {
            text-align: center;
            padding: 0 12px;
        }
        .stat-item::before {
            content: "";
            display: block;
            width: 26px;
            height: 3px;
            background: var(--ochre);
            margin: 0 auto 16px;
        }
        .stat-number {
            display: block;
            font-family: var(--font-display);
            font-size: 40px;
            font-weight: 700;
            color: var(--forest);
        }
        .stat-item p {
            font-size: 13px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: var(--muted);
            margin: 10px 0 0;
        }

        /* -------- Section defaults -------- */
        .section { padding: 88px 0; }
        .rule {
            width: 46px;
            height: 3px;
            background: var(--ochre);
            border: none;
            margin: 0 0 24px;
        }
        .rule.center { margin: 0 auto 24px; }
        .section h2 {
            font-family: var(--font-display);
            color: var(--ink);
            font-size: 30px;
            font-weight: 700;
            margin: 0 0 20px;
        }
        .section h2.center { text-align: center; }

        /* -------- About -------- */
        .about-grid {
            display: grid;
            grid-template-columns: 0.9fr 1.1fr;
            gap: 60px;
        }
        .about-text p { color: var(--muted); font-size: 16px; }
        .about-text p + p { margin-top: 16px; }

        /* -------- Mock exam -------- */
        .mock-section { background: var(--bone-dim); text-align: center; }
        .mock-content { max-width: 660px; margin: 0 auto; }
        .mock-content p { color: var(--muted); }

        /* -------- Roles -------- */
        .roles {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            margin-top: 36px;
        }
        .role {
            padding: 0 32px;
            border-left: 1px solid var(--line);
            transition: transform 0.2s ease;
        }
        .role:first-child { border-left: none; padding-left: 0; }
        .role:hover { transform: translateY(-3px); }
        .role h3 {
            font-family: var(--font-display);
            font-size: 19px;
            font-weight: 600;
            color: var(--ink);
            margin: 0 0 10px;
        }
        .role p { font-size: 14px; color: var(--muted); margin: 0; }

        /* -------- Closing -------- */
        .closing {
            background: var(--forest-deep);
            color: var(--bone);
            text-align: center;
        }
        .closing h2 {
            color: var(--bone);
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 14px;
        }
        .closing p { max-width: 480px; margin: 0 auto 26px; color: #C9CFC5; }
        .closing a.text-link {
            color: var(--ochre);
            font-family: var(--font-display);
            font-weight: 600;
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        /* -------- Fade-in on scroll -------- */
        .fade-in {
            opacity: 0;
            transform: translateY(16px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        @media (prefers-reduced-motion: reduce) {
            .fade-in, .btn { transition: none !important; opacity: 1 !important; transform: none !important; }
        }

        /* -------- Responsive -------- */
        @media (max-width: 820px) {
            .hero-inner { grid-template-columns: 1fr; }
            .hero h1 { font-size: 36px; }
            .hero-sub { max-width: 100%; }
            .hub-wrap { order: -1; margin-bottom: 20px; }
            .hub-wrap svg { max-width: 300px; }
            .about-grid { grid-template-columns: 1fr; gap: 32px; }
            .stats-grid { grid-template-columns: 1fr; row-gap: 24px; }
            .roles { grid-template-columns: 1fr; row-gap: 28px; }
            .role { border-left: none; padding-left: 0; padding-top: 20px; border-top: 1px solid var(--line); }
            .role:first-child { border-top: none; padding-top: 0; }
        }
    </style>
</head>
<body>

    <aside class="exam-sidebar" aria-hidden="true">

    <div class="dots">
        <span class="active"></span>
        <span></span>
        <span></span>
        <span></span>
    </div>

    <div class="slide active">
        <img src="<?= BASE_URL ?>/assets/images/exam_hall.jpg" alt="Students sitting examinations">
        <p class="slide-caption">
            Students sitting the MSCE Mock Examination
        </p>
    </div>

    <div class="slide">
        <img src="<?= BASE_URL ?>/assets/images/marking.jpg" alt="Teachers marking examinations">
        <p class="slide-caption">
            Teachers entering and verifying marks
        </p>
    </div>

    <div class="slide">
        <img src="<?= BASE_URL ?>/assets/images/classroom.jpg" alt="Classroom learning">
        <p class="slide-caption">
            Classrooms across the Northern Region
        </p>
    </div>

    <div class="slide">
        <img src="<?= BASE_URL ?>/assets/images/exams.jpg" alt="Examination results">
        <p class="slide-caption">
            Results, ready for the next step
        </p>
    </div>

</aside>

    <?php $landing_page = true; include __DIR__ . '/common/header.php'; ?>

    <main>
        <!-- Hero -->
        <section class="hero">
            <div class="container hero-inner">
                <div class="hero-text">
                    <p class="eyebrow">Northern Education Division</p>
                    <h1>One system, six districts, every mock exam.</h1>
                    <p class="hero-sub">NED-SEMS coordinates MSCE Mock Examinations across the Northern Region &mdash; candidate registration, mark entry and results, in one place.</p>
                    <a href="login.php" class="btn">Login to Portal</a>
                </div>

                <div class="hub-wrap" aria-hidden="true">
                    <svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">
                        <path class="hub-region" d="M200,30 C280,35 355,90 365,180 C375,265 320,340 235,365 C150,388 70,350 40,270 C12,195 35,110 105,65 C140,42 165,28 200,30 Z"/>

                        <g class="hub-spoke">
                            <line x1="200" y1="200" x2="200" y2="70"/>
                            <line x1="200" y1="200" x2="315" y2="135"/>
                            <line x1="200" y1="200" x2="315" y2="265"/>
                            <line x1="200" y1="200" x2="200" y2="330"/>
                            <line x1="200" y1="200" x2="85" y2="265"/>
                            <line x1="200" y1="200" x2="85" y2="135"/>
                        </g>

                        <g class="hub-node"><circle cx="200" cy="70" r="7"/><text x="200" y="52" text-anchor="middle">Mzimba</text></g>
                        <g class="hub-node"><circle cx="315" cy="135" r="7"/><text x="330" y="120" text-anchor="start">Karonga</text></g>
                        <g class="hub-node"><circle cx="315" cy="265" r="7"/><text x="330" y="270" text-anchor="start">Rumphi</text></g>
                        <g class="hub-node"><circle cx="200" cy="330" r="7"/><text x="200" y="356" text-anchor="middle">Nkhata Bay</text></g>
                        <g class="hub-node"><circle cx="85" cy="265" r="7"/><text x="70" y="270" text-anchor="end">Chitipa</text></g>
                        <g class="hub-node"><circle cx="85" cy="135" r="7"/><text x="70" y="120" text-anchor="end">Likoma Is.</text></g>

                        <g class="hub-center">
                            <circle cx="200" cy="200" r="30"/>
                            <text x="200" y="205" text-anchor="middle" font-size="13">NED</text>
                        </g>
                    </svg>
                </div>
            </div>
        </section>

        <!-- Stats -->
        <section class="stats">
            <div class="container">
                <div class="stats-grid">
                    <div class="stat-item fade-in">
                        <span class="stat-number" data-target="<?= $school_count ?>">0</span>
                        <p>Schools</p>
                    </div>
                    <div class="stat-item fade-in">
                        <span class="stat-number" data-target="<?= $teacher_count ?>">0</span>
                        <p>Teachers</p>
                    </div>
                    <div class="stat-item fade-in">
                        <span class="stat-number" data-target="<?= $student_count ?>">0</span>
                        <p>Students</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- About NED -->
        <section class="section" id="about">
            <div class="container">
                <div class="about-grid">
                    <div class="fade-in">
                        <hr class="rule">
                        <h2>About the Division</h2>
                    </div>
                    <div class="about-text fade-in">
                        <p>The Northern Education Division (NED) is responsible for delivering quality secondary education across the Northern Region of Malawi, coordinating standards, resources and examinations for every school under its charge.</p>
                        <p>Our mission is to raise academic standards and prepare students for national examinations through consistent support and innovation.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- MSCE Mock Examinations -->
        <section class="section mock-section" id="mock">
            <div class="container">
                <hr class="rule center">
                <h2 class="fade-in center">MSCE Mock Examinations</h2>
                <div class="mock-content fade-in">
                    <p>Every year, NED conducts standardized Mock Examinations to prepare students for the Malawi School Certificate of Education (MSCE). These mocks help identify learning gaps, improve teaching strategies, and boost student confidence ahead of the final exams.</p>
                </div>
            </div>
        </section>

        <!-- Who Uses -->
        <section class="section" id="features">
            <div class="container">
                <hr class="rule">
                <h2 class="fade-in">Who Uses NED-SEMS?</h2>
                <div class="roles">
                    <div class="role fade-in">
                        <h3>NED Administrators</h3>
                        <p>Manage school participation and student records across the division..</p>
                    </div>
                    <div class="role fade-in">
                        <h3>Teachers</h3>
                        <p>Enter and manage student marks.</p>
                    </div>
                    
                    <div class="role fade-in">
                        <h3>Examination Officers</h3>
                        <p>Coordinate and monitor school-level exams activities.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Closing -->
        <section class="section closing">
            <div class="container">
                <div class="fade-in">
                    <h2>Thank you for visiting</h2>
                    <p>We are glad you are part of this important initiative to improve examination management in the Northern Education Division.</p>
                    <a href="login.php" class="text-link">Login to Portal &rarr;</a>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/common/footer.php'; ?>

    <script>
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-target'));
                let count = 0;
                const increment = Math.ceil(target / 80);

                const timer = setInterval(() => {
                    count += increment;
                    if (count >= target) {
                        count = target;
                        clearInterval(timer);
                    }
                    counter.textContent = count.toLocaleString();
                }, 30);
            });
        }

        function handleScroll() {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach(el => {
                const rect = el.getBoundingClientRect();
                if (rect.top <= window.innerHeight * 0.85) {
                    el.classList.add('visible');
                }
            });
        }

        function runSidebarCarousel() {
            const slides = document.querySelectorAll('.exam-sidebar .slide');
            const dots = document.querySelectorAll('.exam-sidebar .dots span');
            if (!slides.length) return;

            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduceMotion) return;

            let current = 0;
            setInterval(() => {
                slides[current].classList.remove('active');
                dots[current].classList.remove('active');
                current = (current + 1) % slides.length;
                slides[current].classList.add('active');
                dots[current].classList.add('active');
            }, 4000);
        }

        window.onload = () => {
            animateCounters();
            handleScroll();
            window.addEventListener('scroll', handleScroll);
            runSidebarCarousel();
        };
    </script>
</body>
</html>