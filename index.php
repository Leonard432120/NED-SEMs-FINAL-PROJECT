<?php
session_start();
require_once __DIR__ . '/config/db.php';

$conn = get_db_connection();

// Fetch dynamic stats for landing page with fallback values
$school_count = 12;
$teacher_count = 145;
$exam_count = 34;
$student_count = 4280;

if ($conn) {
    // 1. Schools
    $res = $conn->query("SELECT COUNT(*) as total FROM schools");
    if ($res) {
        $school_count = (int)$res->fetch_assoc()['total'];
    }
    // 2. Teachers
    $res = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='teacher'");
    if ($res) {
        $teacher_count = (int)$res->fetch_assoc()['total'];
    }
    // 3. Exams
    $res = $conn->query("SELECT COUNT(*) as total FROM exams");
    if ($res) {
        $exam_count = (int)$res->fetch_assoc()['total'];
    }
    // 4. Active Students
    $res = $conn->query("SELECT COUNT(*) as total FROM students WHERE status='active'");
    if ($res) {
        $student_count = (int)$res->fetch_assoc()['total'];
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NED-SEMS | Northern Education Division Smart Examination System</title>
    <!-- Import standard base and layout stylesheets first for variables -->
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <!-- Load custom landing page design override -->
    <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body class="landing-body">

    <?php
    $landing_page = true;
    include __DIR__ . '/common/header.php';
    ?>

    <main>
        <!-- ================= HERO SECTION ================= -->
        <section class="landing-section hero-section">
            <div class="landing-container">
                <div class="hero-grid">
                    <div>
                        <h1 class="hero-title">
                            Northern Education Division Mock Examinations Management system (NED-SEMS)
                        </h1>
                        <p class="hero-description">
                            The official examination management system of the Northern Education Division (NED). Streamlining division-wide item writing, secure paper moderation, conflict-free timetabling, and AI-powered mock performance analytics.
                        </p>
                        <div class="hero-ctas">
                            <a href="login.php" class="btn-landing-primary">Portal Login</a>
                            <a href="#features" class="btn-landing-secondary">Explore Modules</a>
                        </div>
                        
                        <!-- Real-time Stats -->
                        <div class="hero-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?= $school_count ?></span>
                                <span class="stat-label">Active Schools</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= $teacher_count ?>+</span>
                                <span class="stat-label">Instructors</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= $exam_count ?></span>
                                <span class="stat-label">Exams Logged</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= number_format($student_count) ?>+</span>
                                <span class="stat-label">Candidates</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="hero-graphic">
                        <div class="hero-logo-box">
                            <img src="/NED-SEMs FINAL YEAR PROJECT/assets/images/logo1.png" alt="NED-SEMS Logo">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= NED MOCK INITIATIVE DETAIL SECTION ================= -->
        <section class="landing-section landing-section--white">
            <div class="landing-container">
                <div class="section-heading">
                    <h2>Standardizing Mock Assessments Across The Division</h2>
                    <p>
                        Every year, the Northern Education Division conducts standardized MSCE Mock Examinations to evaluate candidate readiness, assure marking quality, and improve national MSCE examination scores.
                    </p>
                </div>
                
                <div class="mock-info-grid">
                    <!-- Point 1 -->
                    <div class="mock-info-card">
                        <div class="mock-card-icon">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                        </div>
                        <h3>Standardized Quality</h3>
                        <p>Subject-majored teachers from top schools draft syllabus-aligned questions, building a high-quality exam repository that prepares candidates for the rigorous MSCE national exam.</p>
                    </div>

                    <!-- Point 2 -->
                    <div class="mock-info-card">
                        <div class="mock-card-icon">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <h3>Logistics & Security</h3>
                        <p>Uniform timetable delivery and paper locking mechanisms ensure zero leakages across remote and urban centers in Likoma, Chitipa, Karonga, Rumphi, Nkhata Bay, and Mzimba.</p>
                    </div>

                    <!-- Point 3 -->
                    <div class="mock-info-card">
                        <div class="mock-card-icon">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <h3>Academic Diagnostics</h3>
                        <p>Digital submission and approval pipelines consolidate mock scores early, exposing weak subjects and allowing the division to deploy timely instructional support.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= FEATURES GRID ================= -->
        <section id="features" class="landing-section">
            <div class="landing-container">
                <div class="section-heading">
                    <h2>Core Examination Modules</h2>
                    <p>Designed to automate and secure the entire lifecycle of Mock examinations across the Northern Education Division.</p>
                </div>
                
                <div class="features-grid">
                    <!-- Card 1 -->
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                        </div>
                        <h3>Identity & Security Control</h3>
                        <p>Granular role-based security tailored for Divisional Managers, school administrators, subject instructors, and review officers.</p>
                    </div>

                    <!-- Card 2 -->
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </div>
                        <h3>Collaborative Exam Builder</h3>
                        <p>Secure workspace for composing, review-locking, and double-moderation check workflows with zero leakages.</p>
                    </div>

                    <!-- Card 3 -->
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3>Smart Timetable Manager</h3>
                        <p>Conflict-free divisional scheduling engine that publishes synchronized, print-ready exam timetables and sessions.</p>
                    </div>

                    <!-- Card 4 -->
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                        </div>
                        <h3>Verified Score Capture</h3>
                        <p>Digital entry interfaces with strict deadline controls, automated grade calculation, and supervisor lock overrides.</p>
                    </div>

                    <!-- Card 5 -->
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3>Divisional Diagnostics</h3>
                        <p>Instant performance charts, subject averages, and comparative analytics generated across schools and divisions.</p>
                    </div>

                    <!-- Card 6 -->
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3>AI Academic Insights</h3>
                        <p>Statistical anomaly detection, marking discrepancy detection, and future score trends powered by data analytics.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= WORKFLOW SECTION ================= -->
        <section class="landing-section landing-section--white">
            <div class="landing-container">
                <div class="section-heading">
                    <h2>The Examination Lifecycle</h2>
                    <p>A streamlined, end-to-end digital lifecycle managed securely on a single platform.</p>
                </div>
                
                <div class="workflow-timeline">
                    <!-- Step 1 -->
                    <div class="workflow-step">
                        <span class="step-num">01</span>
                        <div class="step-header">
                            <svg class="step-header-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                            </svg>
                            <span class="step-title">Design & Development</span>
                        </div>
                        <p class="step-description">
                            Teachers author exam questions inside the encrypted item-writer workspace, matching curriculum major/minor specializations.
                        </p>
                    </div>

                    <!-- Step 2 -->
                    <div class="workflow-step">
                        <span class="step-num">02</span>
                        <div class="step-header">
                            <svg class="step-header-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                            <span class="step-title">Moderation & Approval</span>
                        </div>
                        <p class="step-description">
                            Designated moderators critique, adjust, and approve final question sheets. The division locks items and issues them securely to schools.
                        </p>
                    </div>

                    <!-- Step 3 -->
                    <div class="workflow-step">
                        <span class="step-num">03</span>
                        <div class="step-header">
                            <svg class="step-header-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <span class="step-title">Execution & Analysis</span>
                        </div>
                        <p class="step-description">
                            Instructors capture student raw marks under strict deadline periods, followed by automated division-wide consolidation and performance indexing.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= SUPPORT SECTION ================= -->
        <section class="landing-section">
            <div class="landing-container">
                <div class="contact-card">
                    <h3>Divisional Support Desk</h3>
                    <p>Have questions or encountering technical issues? Our systems engineers and administrators are here to support your school.</p>
                    
                    <div class="contact-grid">
                        <div class="contact-info-item">
                            <div class="contact-info-label">E-Mail Address</div>
                            <div class="contact-info-value">support@ned-sems.gov.mw</div>
                        </div>
                        <div class="contact-info-item">
                            <div class="contact-info-label">Office Location</div>
                            <div class="contact-info-value">Northern Education Division HQ, Mzuzu</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- ================= FOOTER ================= -->
    <footer class="footer">
        <div class="landing-container">
            <div class="footer-grid">
                <div>
                    <div class="footer-logo">
                        <img src="/NED-SEMs FINAL YEAR PROJECT/assets/images/logo.png" alt="NED-SEMS Logo">
                        <h3>NED-SEMS</h3>
                    </div>
                    <p class="footer-desc">
                        Northern Education Division Smart Examination Management System. Leading digital transformation in divisional examination logistics.
                    </p>
                </div>
                
                <div class="footer-col">
                    <h4>Core Modules</h4>
                    <ul>
                        <li><a href="#features">Exam Builder</a></li>
                        <li><a href="#features">Scheduling Engine</a></li>
                        <li><a href="#features">Verified Capture</a></li>
                        <li><a href="#features">AI Diagnostics</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h4>Access & Security</h4>
                    <ul>
                        <li><a href="login.php">Portal Access</a></li>
                        <li><a href="login.php">Teacher Workspace</a></li>
                        <li><a href="login.php">Officer Dashboard</a></li>
                        <li><a href="login.php">EDM Control Room</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div>&copy; <?= date('Y') ?> NED-SEMS | Northern Education Division. All rights reserved.</div>
                <div style="font-weight:600;color:#ffffff;">Ministry of Education, Malawi</div>
            </div>
        </div>
    </footer>

</body>
</html>