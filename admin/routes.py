# =========================
# IMPORTS
# =========================
from flask import Blueprint, render_template, request, redirect, session, flash, send_file
from config.db import get_db_connection
from common.email_service import send_email

import matplotlib
matplotlib.use('Agg')

import matplotlib.pyplot as plt
import io
import base64

from reportlab.platypus import SimpleDocTemplate, Paragraph, Table, TableStyle
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet


# =========================
# BLUEPRINT (ONLY ONCE ✅)
# =========================
admin = Blueprint('admin', __name__, url_prefix='/admin')


# =========================
# AUTH CHECK
# =========================
def admin_required():
    return 'user_id' in session and session.get('role') == 'admin'


# =========================
# CHART FUNCTIONS
# =========================
def plot_bar_chart(labels, values, title):
    plt.figure(figsize=(8, 4))
    plt.bar(labels, values, color='skyblue')
    plt.title(title)
    plt.xticks(rotation=45, ha='right')
    plt.tight_layout()

    buf = io.BytesIO()
    plt.savefig(buf, format='png')
    buf.seek(0)

    img = base64.b64encode(buf.getvalue()).decode('utf-8')
    plt.close()

    return img


def plot_pie_chart(labels, values, title):
    plt.figure(figsize=(6, 6))
    plt.pie(values, labels=labels, autopct='%1.1f%%', startangle=140)
    plt.title(title)
    plt.tight_layout()

    buf = io.BytesIO()
    plt.savefig(buf, format='png')
    buf.seek(0)

    img = base64.b64encode(buf.getvalue()).decode('utf-8')
    plt.close()

    return img


# =========================
# DASHBOARD
# =========================
@admin.route('/dashboard')
def dashboard():
    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    # COUNTS
    cursor.execute("SELECT COUNT(*) AS total FROM schools")
    schools = cursor.fetchone()['total']

    cursor.execute("SELECT COUNT(*) AS total FROM users")
    users = cursor.fetchone()['total']

    cursor.execute("SELECT COUNT(*) AS total FROM exams")
    exams = cursor.fetchone()['total']

    cursor.execute("SELECT COUNT(*) AS total FROM results")
    results = cursor.fetchone()['total']

    cursor.execute("SELECT COUNT(*) AS total FROM results WHERE status='pending'")
    pending = cursor.fetchone()['total']

    cursor.execute("SELECT COUNT(*) AS total FROM exams WHERE status='approved'")
    active_exams = cursor.fetchone()['total']

    # CHART DATA
    cursor.execute("""
        SELECT s.school_name, COUNT(st.student_id) AS total
        FROM schools s
        LEFT JOIN students st ON s.school_id = st.school_id
        GROUP BY s.school_id
    """)
    chart_data = cursor.fetchall()

    # ROLES
    cursor.execute("SELECT role, COUNT(*) as total FROM users GROUP BY role")
    roles = cursor.fetchall()

    # RECENT USERS
    cursor.execute("SELECT name, role FROM users ORDER BY user_id DESC LIMIT 5")
    recent_users = cursor.fetchall()

    # TOP SCHOOLS
    cursor.execute("""
        SELECT s.school_name, COUNT(st.student_id) AS total_students
        FROM schools s
        LEFT JOIN students st ON s.school_id = st.school_id
        GROUP BY s.school_id
        ORDER BY total_students DESC
        LIMIT 5
    """)
    top_schools = cursor.fetchall()

    # OVERLOADED
    cursor.execute("""
        SELECT s.school_name, COUNT(st.student_id) AS total
        FROM schools s
        JOIN students st ON s.school_id = st.school_id
        GROUP BY s.school_id
        HAVING total > 500
    """)
    overloaded_schools = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template(
        'admin/dashboard.html',
        schools=schools,
        users=users,
        exams=exams,
        results=results,
        pending=pending,
        active_exams=active_exams,
        roles=roles,
        recent_users=recent_users,
        chart_data=chart_data,
        top_schools=top_schools,
        overloaded_schools=overloaded_schools
    )


# =========================
# ASSIGN TEACHERS
# =========================
@admin.route('/assign', methods=['GET', 'POST'])
def assign_teachers():
    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    if request.method == 'POST':
        exam_id = request.form['exam_id']
        teacher_id = request.form['teacher_id']
        role = request.form['role']

        cursor.execute("""
            SELECT * FROM exam_assignments
            WHERE exam_id=%s AND teacher_id=%s
        """, (exam_id, teacher_id))

        if cursor.fetchone():
            flash("Teacher already assigned!", "error")
            return redirect('/admin/assign')

        try:
            cursor.execute("""
                INSERT INTO exam_assignments (exam_id, teacher_id, role, assigned_by)
                VALUES (%s, %s, %s, %s)
            """, (exam_id, teacher_id, role, session['user_id']))

            conn.commit()

            # EMAIL SAFE
            try:
                cursor.execute("SELECT name, email FROM users WHERE user_id=%s", (teacher_id,))
                teacher = cursor.fetchone()

                cursor.execute("SELECT title FROM exams WHERE exam_id=%s", (exam_id,))
                exam = cursor.fetchone()

                send_email(
                    teacher['email'],
                    "Exam Assignment",
                    f"You are assigned as {role} for {exam['title']}"
                )
            except Exception as e:
                print("Email failed:", e)

            flash("Assigned successfully!", "success")

        except Exception as e:
            conn.rollback()
            flash(str(e), "error")

        return redirect('/admin/assign')

    cursor.execute("SELECT exam_id, title FROM exams")
    exams = cursor.fetchall()

    cursor.execute("SELECT user_id, name FROM users WHERE role='teacher'")
    teachers = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template('admin/assign.html', exams=exams, teachers=teachers)


# =========================
# SCHOOL REPORT (ANALYTICS)
# =========================
# =========================
# SCHOOL ANALYTICS REPORT (PRO VERSION)
# =========================
@admin.route('/school-report')
def school_report():

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    # =========================
    # FETCH DATA
    # =========================
    cursor.execute("""
        SELECT 
            s.school_id,
            s.school_name,
            s.district,

            (SELECT COUNT(*) FROM users u 
             WHERE u.school_id = s.school_id 
             AND u.role='teacher') AS teachers_count,

            (SELECT COUNT(*) FROM students st 
             WHERE st.school_id = s.school_id) AS students_count,

            (SELECT AVG(r.percentage)
             FROM results r
             JOIN students st ON r.student_id = st.student_id
             WHERE st.school_id = s.school_id) AS avg_performance

        FROM schools s
        ORDER BY s.school_name
    """)

    report = cursor.fetchall()

    # =========================
    # CLEAN + ADD FIELDS
    # =========================
    for r in report:
        avg = r['avg_performance'] or 0
        r['avg_score'] = round(avg, 1)
        r['pass_rate'] = round(avg, 1)   # (simple logic for now)

    # =========================
    # SUMMARY
    # =========================
    total_schools = len(report)
    total_teachers = sum(r['teachers_count'] for r in report)
    total_students = sum(r['students_count'] for r in report)

    ratio = round(total_students / total_teachers, 1) if total_teachers else 0
    overcrowded = sum(1 for r in report if r['students_count'] > 500)

    summary = {
        'total_schools': total_schools,
        'total_teachers': total_teachers,
        'total_students': total_students,
        'students_per_teacher': ratio,
        'overcrowded_schools': overcrowded
    }

    # =========================
    # TOP & WORST
    # =========================
    sorted_schools = sorted(report, key=lambda x: x['avg_score'], reverse=True)

    top_school = sorted_schools[0] if sorted_schools else None
    worst_school = sorted_schools[-1] if sorted_schools else None

    # =========================
    # ALERTS
    # =========================
    alerts = []

    for r in report:
        if r['students_count'] > 500:
            alerts.append(f"{r['school_name']} is overcrowded")

        if r['teachers_count'] == 0:
            alerts.append(f"{r['school_name']} has no teachers")

        if r['pass_rate'] < 40:
            alerts.append(f"{r['school_name']} has poor performance")

    # =========================
    # CHART FUNCTIONS
    # =========================
    def generate_chart(fig):
        buf = io.BytesIO()
        fig.savefig(buf, format='png')
        buf.seek(0)
        img = base64.b64encode(buf.getvalue()).decode('utf-8')
        plt.close(fig)
        return img

    def bar_chart(labels, values, title):
        fig, ax = plt.subplots(figsize=(10, 4))
        ax.bar(labels, values)
        ax.set_title(title)
        ax.tick_params(axis='x', rotation=45)
        fig.tight_layout()
        return generate_chart(fig)

    def line_chart(labels, values, title):
        fig, ax = plt.subplots(figsize=(10, 4))
        ax.plot(labels, values, marker='o')
        ax.set_title(title)
        ax.tick_params(axis='x', rotation=45)
        fig.tight_layout()
        return generate_chart(fig)

    def pie_chart(labels, values, title):
        fig, ax = plt.subplots(figsize=(6, 6))
        ax.pie(values, labels=labels, autopct='%1.1f%%')
        ax.set_title(title)
        fig.tight_layout()
        return generate_chart(fig)

    # =========================
    # CHART DATA
    # =========================
    labels = [r['school_name'] for r in report]
    students = [r['students_count'] for r in report]
    teachers = [r['teachers_count'] for r in report]
    performance = [r['avg_score'] for r in report]

    chart_students = bar_chart(labels, students, "Students per School")
    chart_teachers = bar_chart(labels, teachers, "Teachers per School")
    chart_performance = line_chart(labels, performance, "Performance Trend")

    # PIE (TOP 5)
    top5 = sorted(report, key=lambda x: x['students_count'], reverse=True)[:5]
    pie_labels = [r['school_name'] for r in top5]
    pie_values = [r['students_count'] for r in top5]

    chart_pie = pie_chart(pie_labels, pie_values, "Top 5 Schools Distribution")

    cursor.close()
    conn.close()

    return render_template(
        'admin/school_report.html',
        report=report,
        summary=summary,
        top_school=top_school,
        worst_school=worst_school,
        alerts=alerts,
        chart_students=chart_students,
        chart_teachers=chart_teachers,
        chart_performance=chart_performance,
        chart_pie=chart_pie
    )