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