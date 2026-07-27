# NED-SEMS — Secondary Education Examination Management System

**NED-SEMS** (Northern Education DivisionSMART EXAMINATION MANAGEMENT SYSTEM) is an enterprise-grade, web-based examination management and academic intelligence platform. Built specifically for secondary education districts, it digitizes and streamlines the entire lifecycle of national and district examinations—from candidate registration, scheduled teacher assignments, and AI-assisted question composition to multi-stage mark verification, automated compilation, security watermarking, and district-wide analytics.

---

## Key Highlights & System Architecture

- **Multi-Role Scoped Access Control**: Tailored dashboards for 4 distinct user tiers (EDM/Admin, Headteacher, Examination Officer, and Teacher).
- **Scheduled Access Windows**: Dynamic time-based security lockouts enforcing start and end datetime windows for mark entry and paper editing.
- **AI-Assisted Question Bank & Moderation**: Integrated Python/Gemini AI question generation and paper moderation workflows.
- **Forensic Security & Watermarking**: Real-time user/timestamp diagonal text watermarks, window unfocus auto-blur, print/screenshot protection, and smart PDF watermarks.
- **Strict Audience-Scoped Bulletins**: Division-wide global broadcasts vs. school-isolated staff announcements with real-time notification bell counts.
- **4-Stage Mark Approval Pipeline**: Structured workflow (Teacher Entry &rarr; Exam Officer Verification &rarr; Headteacher Approval &rarr; EDM Compilation & Publishing).
- **Automated Grading & Performance Analytics**: District and school KPI tiles, ranking badges, subject averages, performance distribution curves, and PDF report card exports.

---

## User Roles & Capabilities

### 1. EDM / Divisoion Administrator (`admin/`)
- **Division Overview**: Full oversight of all secondary schools, districts, subjects, and users within the division.
- **Master Examination Management**: Create, schedule, publish, or lock district exams with multi-paper timing breakdowns (`admin/schedule.php`, `admin/exams.php`).
- **Subject & Teacher Assignments**: Allocate subjects to teachers and assign precise datetime access windows (`admin/assign.php`, `admin/manage_assignments.php`).
- **District Result Compilation**: Automated computation of totals, averages, subject grades, ranks, and district pass rates (`admin/compile_results.php`, `admin/publish_results.php`).
- **User & School Management**: Activate/deactivate accounts and manage educational institution profiles (`admin/manage_users.php`, `admin/manage_schools.php`).

### 2. Headteacher / School Principal (`headteacher/`)
- **School Governance**: Oversee school teachers, examination officers, and candidate enrollment statistics.
- **Marks Reception & Approval**: Review submitted subject marks from examination officers and approve/forward them to the EDM (`headteacher/marks_reception.php`).
- **Assignment Locking Control**: Lock or unlock mark entry access for individual teachers (`headteacher/marks_management.php`).
- **Headteacher Inspection Findings**: Log inspection reports and school performance notes.
- **School Announcements**: Publish internal school bulletins isolated exclusively to school staff.

### 3. Examination Officer (`examination_officer/`)
- **Candidate Registration**: Scope and register students per form/class and attach subjects (`examination_officer/manage_exam_candidates.php`).
- **Marks Verification**: Track mark submission status across all subjects, verify teacher entries, and request re-entries or forward to Headteacher (`examination_officer/marks_management.php`).
- **Exam Document Download**: Access official, time-restricted examination papers with automated watermark controls (`common/exam_download.php`).

### 4. Teacher (`teacher/`)
- **Assessment Mark Entry**: Input student marks for assigned subjects within active access windows (`teacher/enter_marks.php`).
- **AI-Assisted Question Composition**: Author examination question papers with optional AI guidance (`teacher/compose_exam.php`).
- **Question Paper Moderation**: Moderate drafted questions against syllabus standards and bloom taxonomy levels (`teacher/moderate_exam.php`).
- **Class Analytics**: View student performance distributions and subject statistics.

---

## Technical Stack & Dependencies

| Component | Technology | Description |
|---|---|---|
| **Core Language** | PHP 8.2+ | Server-side logic with MySQLi prepared statements & strict exception handling |
| **Database** | MySQL / MariaDB 8.0+ | Relational schema (`ned_sems`) with transactional integrity |
| **Web Server** | Apache (WAMP / XAMPP) | Local/Production web hosting server |
| **Frontend** | HTML5, CSS3, Vanilla JS | Custom modular design system (`main.css`, `components.css`, `reports.css`, `watermark.css`) |
| **AI Integration** | Python 3 + Google Gemini API | Automated question authoring & moderation recommendations (`ai/`) |
| **Document Export** | FPDF / TCPDF | PDF report generation & watermarked exam paper downloads |
| **Security Shielding** | Vanilla JS + CSS | Forensic watermarks, window unfocus blur, anti-copy/print protection |

---

## Directory Structure

```
NED-SEMs FINAL YEAR PROJECT/
├── admin/                    # EDM / District Administrator module
├── examination_officer/      # Examination Officer module
├── headteacher/              # Headteacher / School Principal module
├── teacher/                  # Teacher module & mark entry workspace
├── ai/                       # Python AI question generation & moderation engine
├── assets/                   # Static assets (CSS, JS, images, icons)
│   ├── css/                  # Main stylesheets (main.css, components.css, reports.css, watermark.css)
│   └── js/                   # Core client scripts
├── auth/                     # Authentication & session controllers
├── common/                   # Shared headers, footers, watermarks, access guards, & modals
├── config/                   # Database connection (db.php), audit logger, & environment config
├── database/                 # SQL database scripts (ned_sems.sql) & seeds
├── models/                   # Data models & business logic classes
├── services/                 # Helper services (email, PDF generators)
├── uploads/                  # Uploaded exam assets & candidate attachments
├── index.php                 # System landing / portal route
├── login.php                 # Unified user login gateway
└── README.md                 # System documentation
```

---

## Database Setup & Installation

### Prerequisites
- **Wampserver** / **XAMPP** (PHP 8.2 or higher, MySQL 8.0/MariaDB)
- **Python 3.9+** (Optional, required for AI question generation features)
- Web browser (Chrome, Edge, Firefox, or Safari)

### 1. Database Initialization
1. Start your WAMP/XAMPP server and open **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Create a new database named `ned_sems` with collation `utf8mb4_unicode_ci`.
3. Import the SQL schema file located at:
   ```
   database/ned_sems.sql
   ```
4. *(Optional)* Seed demo data by running the seed script via browser or CLI:
   ```bash
   php database/full_seed.php
   ```

### 2. Configuration
Verify or edit `config/db.php` to match your local database credentials:
```php
function get_db_connection() {
    $host     = 'localhost';
    $user     = 'root';
    $password = '';       // Set your MySQL password
    $database = 'ned_sems';

    $conn = new mysqli($host, $user, $password, $database);
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
```

### 3. Application Deployment
1. Copy the project folder `NED-SEMs FINAL YEAR PROJECT` into your web server root:
   - WAMP: `C:/wamp64/www/NED-SEMs FINAL YEAR PROJECT/`
   - XAMPP: `C:/xampp/htdocs/NED-SEMs FINAL YEAR PROJECT/`
2. Open your web browser and navigate to:
   ```
   http://localhost/NED-SEMs%20FINAL%20YEAR%20PROJECT/
   ```

---

## Security & Privacy Governance

1. **Scheduled Access Windows**: Access guards (`common/assignment_access_guard.php`) restrict teacher action to exact datetime windows defined by district administrators.
2. **Forensic Dynamic Watermarks**: Screens display user name, IP, and timestamp in subtle diagonal overlays. PDF downloads automatically watermark papers downloaded prior to official start dates.
3. **Audience Scoping**: Bulletins are strictly scoped to isolate school-internal notices from district-wide broadcasts, preventing data leaks across schools.
4. **Unified Modal System**: High-contrast, standardized confirm popups (`.gdm-*` modals) prevent accidental item deletions or status changes.

---

## License & Credits

- **Project**: Northern Education DivisionSMART EXAMINATION MANAGEMENT SYSTEM (NED-SEMS)
- **Developer**: Final Year project / Education in ICT Project
- **Built for**: Ministry of Education / Education Division Education Office
