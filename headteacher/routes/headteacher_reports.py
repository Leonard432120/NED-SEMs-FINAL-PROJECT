from flask import Blueprint, render_template, request, session, redirect
from config.db import get_db_connection

head_reports = Blueprint('head_reports', __name__, url_prefix='/headteacher/reports')


# =========================
# REPORT DASHBOARD (VIEW)
# =========================
@head_reports.route('/')
def reports_home():

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    school_id = session.get('school_id')

    # =========================
    # GET REPORTS (SCHOOL ONLY)
    # =========================
    cursor.execute("""
        SELECT *
        FROM school_reports
        WHERE school_id=%s
        ORDER BY created_at DESC
    """, (school_id,))
    reports = cursor.fetchall()

    # =========================
    # SAFE STATS DEFAULT (PREVENT ERROR)
    # =========================
    stats = {
        "avg_pass": 0,
        "avg_attendance": 0
    }

    cursor.execute("""
        SELECT 
            AVG(pass_rate) AS avg_pass,
            AVG(attendance_rate) AS avg_attendance
        FROM school_reports
        WHERE school_id=%s
    """, (school_id,))

    row = cursor.fetchone()

    if row and row["avg_pass"] is not None:
        stats = row

    # =========================
    # AI PREDICTION ENGINE
    # =========================
    cursor.execute("""
        SELECT 
            AVG(pass_rate) AS avg_pass,
            COUNT(*) AS total_reports
        FROM school_reports
        WHERE school_id=%s
    """, (school_id,))

    ai = cursor.fetchone()

    avg_pass = ai["avg_pass"] or 0
    total_reports = ai["total_reports"]

    if total_reports < 2:
        prediction = "📊 Not enough data for prediction"
    elif avg_pass < 40:
        prediction = "🔴 HIGH RISK: School performance is critically low"
    elif avg_pass < 60:
        prediction = "🟡 WARNING: Performance is unstable"
    else:
        prediction = "🟢 STABLE: School is performing well"

    conn.close()

    return render_template(
        "headteacher/reports.html",
        reports=reports,
        stats=stats,
        prediction=prediction
    )


# =========================
# CREATE REPORT (AUTO CALCULATION)
# =========================
@head_reports.route('/create', methods=['POST'])
def create_report():

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    school_id = session.get('school_id')

    term = request.form['term']
    year = request.form['year']
    comment = request.form.get('comment', '')
    action_plan = request.form.get('action_plan', '')

    # =========================
    # TOTAL STUDENTS
    # =========================
    cursor.execute("""
        SELECT COUNT(*) AS total
        FROM students
        WHERE school_id=%s
    """, (school_id,))
    total_students = cursor.fetchone()['total']

    # =========================
    # RESULTS (PASS RATE)
    # =========================
    cursor.execute("""
        SELECT COUNT(*) AS total_results
        FROM results r
        JOIN students s ON r.student_id = s.student_id
        WHERE s.school_id=%s
    """, (school_id,))
    total_results = cursor.fetchone()['total_results']

    cursor.execute("""
        SELECT COUNT(*) AS passed
        FROM results r
        JOIN students s ON r.student_id = s.student_id
        WHERE s.school_id=%s AND r.percentage >= 50
    """, (school_id,))
    passed = cursor.fetchone()['passed']

    pass_rate = (passed / total_results * 100) if total_results > 0 else 0

    # =========================
    # ATTENDANCE (SAFE MODEL)
    # =========================
    cursor.execute("""
        SELECT COUNT(*) AS present
        FROM attendance a
        JOIN students s ON a.student_id = s.student_id
        WHERE s.school_id=%s AND a.status='present'
    """, (school_id,))
    present = cursor.fetchone()['present']

    cursor.execute("""
        SELECT COUNT(*) AS total
        FROM attendance a
        JOIN students s ON a.student_id = s.student_id
        WHERE s.school_id=%s
    """, (school_id,))
    attendance_total = cursor.fetchone()['total']

    attendance_rate = (present / attendance_total * 100) if attendance_total > 0 else 0

    # =========================
    # SAVE REPORT
    # =========================
    cursor.execute("""
        INSERT INTO school_reports
        (school_id, term, year, total_students,
         total_present, pass_rate, attendance_rate,
         teacher_comment, action_plan, created_by)
        VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """, (
        school_id,
        term,
        year,
        total_students,
        present,
        pass_rate,
        attendance_rate,
        comment,
        action_plan,
        session.get('user_id')
    ))

    conn.commit()
    conn.close()

    return redirect('/headteacher/reports')