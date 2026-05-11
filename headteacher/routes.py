from flask import Blueprint, render_template, session, redirect, request, flash
from config.db import get_db_connection
import random

headteacher = Blueprint('headteacher', __name__, url_prefix='/headteacher')


# =========================
# AUTH CHECK (Reusable)
# =========================
def check_headteacher():
    if 'user_id' not in session:
        return False
    if session.get('role') != 'headteacher':
        return False
    return True


# =========================
# DASHBOARD
# =========================
@headteacher.route('/dashboard')
def dashboard():

    if not check_headteacher():
        return redirect('/')

    school_id = session.get('school_id')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    # STUDENTS COUNT
    cursor.execute("SELECT COUNT(*) AS total FROM students WHERE school_id=%s", (school_id,))
    students = cursor.fetchone()['total']

    # RESULTS COUNT
    cursor.execute("""
        SELECT COUNT(*) AS total 
        FROM results r
        JOIN students s ON r.student_id = s.student_id
        WHERE s.school_id=%s
    """, (school_id,))
    results = cursor.fetchone()['total']

    cursor.close()
    conn.close()

    return render_template(
        'headteacher/dashboard.html',
        students=students,
        results=results
    )


# =========================
# ADD STUDENT
# =========================
@headteacher.route('/add-student', methods=['GET', 'POST'])
def add_student():

    if 'user_id' not in session:
        return redirect('/')

    if session.get('role') != 'headteacher':
        return "Access Denied"

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    # 🔥 GET SCHOOL ID SAFELY
    cursor.execute(
        "SELECT school_id FROM users WHERE user_id=%s",
        (session['user_id'],)
    )
    user = cursor.fetchone()

    if not user or not user['school_id']:
        return "Error: No school assigned"

    school_id = user['school_id']

    if request.method == 'POST':
        name = request.form['name']
        student_class = request.form['class']

        import random

        # 🔥 GENERATE UNIQUE EXAM NUMBER
        while True:
            exam_number = f"MW{school_id}{random.randint(1000,9999)}"

            cursor.execute(
                "SELECT * FROM students WHERE exam_number=%s",
                (exam_number,)
            )
            existing = cursor.fetchone()

            if not existing:
                break

        # ✅ INSERT (ALLOW SAME NAME)
        cursor.execute("""
            INSERT INTO students (name, class, school_id, exam_number)
            VALUES (%s, %s, %s, %s)
        """, (name, student_class, school_id, exam_number))

        conn.commit()

        cursor.close()
        conn.close()

        return redirect('/headteacher/manage-students')

    cursor.close()
    conn.close()

    return render_template('headteacher/add_student.html')

# =========================
# MANAGE STUDENTS
# =========================
@headteacher.route('/manage-students')
def manage_students():

    if 'user_id' not in session:
        return redirect('/')

    if session.get('role') != 'headteacher':
        return "Access Denied"

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    # ✅ GET school_id safely
    cursor.execute("""
        SELECT school_id FROM users WHERE user_id=%s
    """, (session['user_id'],))

    user = cursor.fetchone()

    if not user or not user['school_id']:
        cursor.close()
        conn.close()
        return "Error: No school assigned"

    school_id = user['school_id']

    # =========================
    # PARAMETERS
    # =========================
    search = request.args.get('search', '').strip()
    class_filter = request.args.get('class', '').strip()
    page = int(request.args.get('page', 1))
    per_page = 5
    offset = (page - 1) * per_page

    # =========================
    # CONDITIONS
    # =========================
    conditions = ["school_id=%s"]
    params = [school_id]

    if search:
        conditions.append("name LIKE %s")
        params.append(f"%{search}%")

    if class_filter:
        conditions.append("class=%s")
        params.append(class_filter)

    where_clause = " AND ".join(conditions)

    # =========================
    # COUNT
    # =========================
    cursor.execute(f"""
        SELECT COUNT(*) AS total
        FROM students
        WHERE {where_clause}
    """, tuple(params))

    total = cursor.fetchone()['total']

    # =========================
    # FETCH DATA
    # =========================
    cursor.execute(f"""
        SELECT *
        FROM students
        WHERE {where_clause}
        ORDER BY student_id DESC
        LIMIT %s OFFSET %s
    """, tuple(params + [per_page, offset]))

    students = cursor.fetchall()

    total_pages = (total + per_page - 1) // per_page

    cursor.close()
    conn.close()

    # ✅ THIS IS WHAT YOU WERE MISSING
    return render_template(
        'headteacher/manage_students.html',
        students=students,
        page=page,
        total_pages=total_pages,
        search=search,
        class_filter=class_filter
    )


# =========================
# EDIT STUDENT
# =========================
@headteacher.route('/edit-student/<int:student_id>', methods=['GET', 'POST'])
def edit_student(student_id):

    if not check_headteacher():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT * FROM students WHERE student_id=%s", (student_id,))
    student = cursor.fetchone()

    if not student:
        return "Student not found"

    if request.method == 'POST':
        name = request.form['name']
        student_class = request.form['class']

        # ❌ NO duplicate check by name anymore
        cursor.execute("""
            UPDATE students
            SET name=%s, class=%s
            WHERE student_id=%s
        """, (name, student_class, student_id))

        conn.commit()

        cursor.close()
        conn.close()

        return redirect('/headteacher/manage-students')

    cursor.close()
    conn.close()

    return render_template('headteacher/edit_student.html', student=student)


# =========================
# DELETE STUDENT
# =========================
@headteacher.route('/delete-student/<int:student_id>')
def delete_student(student_id):

    if not check_headteacher():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("DELETE FROM students WHERE student_id=%s", (student_id,))
    conn.commit()

    cursor.close()
    conn.close()

    return redirect('/headteacher/manage-students')


@headteacher.route('/reports', methods=['GET', 'POST'])
def school_reports():
    if 'user_id' not in session:
        return redirect(url_for('login'))

    db = get_db_connection()
    cursor = db.cursor(dictionary=True)

    school_id = session.get('school_id')  # SAFE FIX

    if not school_id:
        return "School not assigned to this headteacher", 403

    # =========================
    # 1. SAVE REPORT (POST)
    # =========================
    if request.method == 'POST':
        term = request.form.get('term')
        year = request.form.get('year')
        comments = request.form.get('comments')

        # compute attendance + pass rate from results
        cursor.execute("""
            SELECT 
                COUNT(*) as total_students,
                AVG(r.percentage) as pass_rate
            FROM results r
            JOIN students s ON s.student_id = r.student_id
            WHERE s.school_id = %s
        """, (school_id,))
        data = cursor.fetchone()

        total_students = data['total_students'] or 0
        pass_rate = data['pass_rate'] or 0

        attendance_rate = 0  # NOT AVAILABLE IN YOUR DB (safe default)

        cursor.execute("""
            INSERT INTO school_reports 
            (school_id, term, year, total_students, attendance_rate, pass_rate, comments)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
        """, (school_id, term, year, total_students, attendance_rate, pass_rate, comments))

        db.commit()

    # =========================
    # 2. LOAD REPORTS
    # =========================
    cursor.execute("""
        SELECT term, year, total_students, attendance_rate, pass_rate, comments, created_at
        FROM school_reports
        WHERE school_id = %s
        ORDER BY created_at DESC
    """, (school_id,))
    reports = cursor.fetchall()

    # =========================
    # 3. PERFORMANCE TREND (YEARLY)
    # =========================
    cursor.execute("""
        SELECT 
            YEAR(r.recorded_at) as year,
            AVG(r.percentage) as avg_score
        FROM results r
        JOIN students s ON s.student_id = r.student_id
        WHERE s.school_id = %s
        GROUP BY YEAR(r.recorded_at)
        ORDER BY year ASC
    """, (school_id,))
    trend_data = cursor.fetchall()

    years = [str(r['year']) for r in trend_data]
    scores = [float(r['avg_score']) if r['avg_score'] else 0 for r in trend_data]

    # =========================
    # 4. SCHOOL INSIGHTS (AI-like logic)
    # =========================
    avg_pass = sum(scores)/len(scores) if scores else 0

    if avg_pass >= 75:
        insight = "Excellent performance. Maintain teaching quality."
    elif avg_pass >= 50:
        insight = "Moderate performance. Improvement needed in weak subjects."
    else:
        insight = "Low performance. Urgent intervention required."

    return render_template(
        'headteacher/reports.html',
        reports=reports,
        years=years,
        scores=scores,
        avg_pass=avg_pass,
        insight=insight
    )

@headteacher.route('/performance')
def performance_dashboard():
    if 'user_id' not in session:
        return redirect(url_for('login'))

    db = get_db_connection()
    cursor = db.cursor(dictionary=True)

    # =========================
    # SCHOOL RANKING (ALL SCHOOLS)
    # =========================
    cursor.execute("""
        SELECT 
            s.school_id,
            s.school_name,
            AVG(r.percentage) as avg_score,
            COUNT(r.result_id) as total_results
        FROM schools s
        LEFT JOIN students st ON st.school_id = s.school_id
        LEFT JOIN results r ON r.student_id = st.student_id
        GROUP BY s.school_id
        ORDER BY avg_score DESC
    """)

    schools = cursor.fetchall()

    # =========================
    # BEST & WORST SCHOOL
    # =========================
    best_school = schools[0] if schools else None
    worst_school = schools[-1] if schools else None

    # =========================
    # DIVISION AVERAGE
    # =========================
    cursor.execute("""
        SELECT AVG(r.percentage) as division_avg
        FROM results r
    """)
    division = cursor.fetchone()

    division_avg = division['division_avg'] or 0

    return render_template(
        'headteacher/performance.html',
        schools=schools,
        best=best_school,
        worst=worst_school,
        division_avg=division_avg
    )

@headteacher.route('/edm/report/pdf')
def edm_pdf():
    db = get_db_connection()
    cursor = db.cursor()

    cursor.execute("""
        SELECT s.school_name, AVG(r.percentage)
        FROM schools s
        LEFT JOIN students st ON st.school_id = s.school_id
        LEFT JOIN results r ON r.student_id = st.student_id
        GROUP BY s.school_id
    """)

    data = cursor.fetchall()

    buffer = io.BytesIO()
    pdf = SimpleDocTemplate(buffer)

    table_data = [["School", "Average Score"]]

    for row in data:
        table_data.append([row[0], round(row[1] or 0, 1)])

    table = Table(table_data)
    pdf.build([table])

    buffer.seek(0)

    return send_file(buffer, as_attachment=True, download_name="edm_report.pdf")