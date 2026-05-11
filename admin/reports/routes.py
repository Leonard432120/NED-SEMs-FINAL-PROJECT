from flask import Blueprint, render_template, request, send_file
from config.db import get_db_connection
import io
from reportlab.pdfgen import canvas

reports = Blueprint('reports', __name__, url_prefix='/admin/reports')


# ======================
# DASHBOARD
# ======================
@reports.route("/")
def reports_home():

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT COUNT(*) as total FROM schools")
    schools_count = cursor.fetchone()["total"]

    cursor.execute("SELECT COUNT(*) as total FROM students")
    students_count = cursor.fetchone()["total"]

    cursor.execute("SELECT COUNT(*) as total FROM results")
    results_count = cursor.fetchone()["total"]

    cursor.execute("""
        SELECT COUNT(*) as risk
        FROM (
            SELECT s.school_id, AVG(r.percentage) as avg_score
            FROM results r
            JOIN students s ON r.student_id = s.student_id
            GROUP BY s.school_id
            HAVING avg_score < 50
        ) t
    """)
    risk_schools = cursor.fetchone()["risk"]

    conn.close()

    return render_template(
        "admin/reports/index.html",
        schools_count=schools_count,
        students_count=students_count,
        results_count=results_count,
        risk_schools=risk_schools
    )


# ======================
# SCHOOL REPORT
# ======================
@reports.route("/school")
def school_report():

    school_id = request.args.get("school_id")

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT school_id, school_name FROM schools")
    schools = cursor.fetchall()

    report = None
    ranking = []

    if school_id:

        cursor.execute("""
            SELECT COUNT(*) as total_students
            FROM results r
            JOIN students s ON r.student_id = s.student_id
            WHERE s.school_id=%s
        """, (school_id,))
        total_students = cursor.fetchone()["total_students"]

        cursor.execute("""
            SELECT COUNT(*) as passed
            FROM results r
            JOIN students s ON r.student_id = s.student_id
            WHERE s.school_id=%s AND r.percentage >= 50
        """, (school_id,))
        passed = cursor.fetchone()["passed"]

        cursor.execute("""
            SELECT COUNT(*) as failed
            FROM results r
            JOIN students s ON r.student_id = s.student_id
            WHERE s.school_id=%s AND r.percentage < 50
        """, (school_id,))
        failed = cursor.fetchone()["failed"]

        cursor.execute("""
            SELECT AVG(percentage) as avg_score
            FROM results r
            JOIN students s ON r.student_id = s.student_id
            WHERE s.school_id=%s
        """, (school_id,))
        avg_score = cursor.fetchone()["avg_score"]

        cursor.execute("""
            SELECT 
                CASE 
                    WHEN percentage >= 75 THEN 'Distinction'
                    WHEN percentage >= 50 THEN 'Pass'
                    ELSE 'Fail'
                END as category,
                COUNT(*) as count
            FROM results r
            JOIN students s ON r.student_id = s.student_id
            WHERE s.school_id=%s
            GROUP BY category
        """, (school_id,))
        distribution = cursor.fetchall()

        report = {
            "total_students": total_students,
            "passed": passed,
            "failed": failed,
            "avg_score": round(avg_score or 0, 2),
            "distribution": distribution
        }

        cursor.execute("""
            SELECT sc.school_name, AVG(r.percentage) as avg_score
            FROM results r
            JOIN students s ON r.student_id=s.student_id
            JOIN schools sc ON s.school_id=sc.school_id
            GROUP BY sc.school_id
            ORDER BY avg_score DESC
        """)
        ranking = cursor.fetchall()

    conn.close()

    return render_template(
        "admin/reports/school_report.html",
        schools=schools,
        report=report,
        ranking=ranking
    )


# ======================
# DISTRICT REPORT (FIXED + FILTER)
# ======================
@reports.route("/district")
def district_report():

    district = request.args.get("district")

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT DISTINCT district FROM schools")
    districts_list = cursor.fetchall()

    query = """
        SELECT sc.district,
               COUNT(r.result_id) as total_students,
               AVG(r.percentage) as avg_score
        FROM results r
        JOIN students st ON r.student_id = st.student_id
        JOIN schools sc ON st.school_id = sc.school_id
    """

    params = []

    if district:
        query += " WHERE sc.district = %s"
        params.append(district)

    query += " GROUP BY sc.district"

    cursor.execute(query, params)
    districts = cursor.fetchall()

    conn.close()

    return render_template(
        "admin/reports/district_report.html",
        districts=districts,
        districts_list=districts_list,
        selected_district=district
    )


# ======================
# RANKING
# ======================
@reports.route("/ranking")
def ranking():

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT sc.school_name, AVG(r.percentage) as avg_score
        FROM results r
        JOIN students s ON r.student_id=s.student_id
        JOIN schools sc ON s.school_id=sc.school_id
        GROUP BY sc.school_id
        ORDER BY avg_score DESC
    """)

    ranking = cursor.fetchall()

    conn.close()

    return render_template(
        "admin/reports/ranking.html",
        ranking=ranking
    )


# ======================
# PERFORMANCE + AI INSIGHTS
# ======================
@reports.route("/performance")
def performance_report():

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT sc.school_name,
               sc.district,
               AVG(r.percentage) AS avg_score
        FROM results r
        JOIN students s ON r.student_id = s.student_id
        JOIN schools sc ON s.school_id = sc.school_id
        GROUP BY sc.school_id
        ORDER BY avg_score DESC
    """)
    ranking = cursor.fetchall()

    cursor.execute("""
        SELECT sc.district,
               AVG(r.percentage) AS avg_score
        FROM results r
        JOIN students s ON r.student_id = s.student_id
        JOIN schools sc ON s.school_id = sc.school_id
        GROUP BY sc.district
        ORDER BY avg_score DESC
    """)
    district_perf = cursor.fetchall()

    cursor.execute("""
        SELECT sc.school_name,
               AVG(r.percentage) AS avg_score
        FROM results r
        JOIN students s ON r.student_id = s.student_id
        JOIN schools sc ON s.school_id = sc.school_id
        GROUP BY sc.school_id
        HAVING avg_score < 50
    """)
    risk_schools = cursor.fetchall()

    cursor.execute("SELECT COUNT(*) as total FROM results")
    total_results = cursor.fetchone()["total"]

    cursor.execute("SELECT AVG(percentage) as avg FROM results")
    national_avg = cursor.fetchone()["avg"]

    conn.close()

    return render_template(
        "admin/reports/performance.html",
        ranking=ranking,
        district_perf=district_perf,
        risk_schools=risk_schools,
        total_results=total_results,
        national_avg=round(national_avg or 0, 2)
    )