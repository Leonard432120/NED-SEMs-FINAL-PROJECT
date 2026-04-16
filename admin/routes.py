from flask import Blueprint, render_template, request, redirect, session, flash, url_for
from config.db import get_db_connection
from common.email_service import send_email

admin = Blueprint('admin', __name__, url_prefix='/admin')


# =========================
# AUTH CHECK
# =========================
def admin_required():
    return 'user_id' in session and session.get('role') == 'admin'


# =========================
# DASHBOARD (FULL ANALYTICS)
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

    # 📊 STUDENTS PER SCHOOL
    cursor.execute("""
        SELECT s.school_name, COUNT(st.student_id) AS total
        FROM schools s
        LEFT JOIN students st ON s.school_id = st.school_id
        GROUP BY s.school_id
    """)
    chart_data = cursor.fetchall()

    # ROLE DISTRIBUTION
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

    # OVERLOADED SCHOOLS
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

        # CHECK DUPLICATE
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

            # EMAIL
            cursor.execute("SELECT name, email FROM users WHERE user_id=%s", (teacher_id,))
            teacher = cursor.fetchone()

            cursor.execute("SELECT title FROM exams WHERE exam_id=%s", (exam_id,))
            exam = cursor.fetchone()

            send_email(
                teacher['email'],
                "Exam Assignment",
                f"You are assigned as {role} for {exam['title']}"
            )

            flash("Assigned successfully!", "success")

        except Exception as e:
            conn.rollback()
            flash(str(e), "error")

        return redirect('/admin/assign')

    cursor.execute("SELECT exam_id, title FROM exams")
    exams = cursor.fetchall()

    cursor.execute("SELECT user_id, name FROM users WHERE role='teacher'")
    teachers = cursor.fetchall()

    return render_template('admin/assign.html', exams=exams, teachers=teachers)


# =========================
# VIEW ASSIGNMENTS
# =========================
@admin.route('/assignments')
def view_assignments():

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT e.title, u.name, ea.role, ea.assigned_at
        FROM exam_assignments ea
        JOIN exams e ON ea.exam_id = e.exam_id
        JOIN users u ON ea.teacher_id = u.user_id
    """)

    data = cursor.fetchall()

    return render_template('admin/view_assignments.html', assignments=data)


# =========================
# USERS MANAGEMENT
# =========================
@admin.route('/manage-users')
def manage_users():

    if not admin_required():
        return redirect('/')

    search = request.args.get('search', '')
    role_filter = request.args.get('role', '')
    page = int(request.args.get('page', 1))
    per_page = 5
    offset = (page - 1) * per_page

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    query = "SELECT * FROM users WHERE 1=1"
    params = []

    # SEARCH
    if search:
        query += " AND (name LIKE %s OR email LIKE %s)"
        params.extend([f"%{search}%", f"%{search}%"])

    # FILTER
    if role_filter:
        query += " AND role=%s"
        params.append(role_filter)

    # COUNT TOTAL
    count_query = query.replace("SELECT *", "SELECT COUNT(*) as total")
    cursor.execute(count_query, params)
    total = cursor.fetchone()['total']

    # PAGINATION
    query += " LIMIT %s OFFSET %s"
    params.extend([per_page, offset])

    cursor.execute(query, params)
    users = cursor.fetchall()

    cursor.close()
    conn.close()

    total_pages = (total + per_page - 1) // per_page

    return render_template(
        'admin/manage_users.html',
        users=users,
        page=page,
        total_pages=total_pages,
        search=search,
        role_filter=role_filter
    )

# =========================
# ADD USERS
# =========================
@admin.route('/add-user', methods=['GET', 'POST'])
def add_user():

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    try:
        # ======================
        # POST: CREATE USER
        # ======================
        if request.method == 'POST':

            name = request.form['name']
            email = request.form['email']
            phone = request.form['phone']
            role = request.form['role']
            school_id = request.form['school_id']

            if not school_id:
                flash("Please select a valid school", "error")
                return redirect('/admin/add-user')

            cursor.execute("""
                INSERT INTO users (name, email, phone, password, role, school_id)
                VALUES (%s, %s, %s, %s, %s, %s)
            """, (name, email, phone, '1234', role, school_id))

            conn.commit()

            # EMAIL
            message = f"""
Hello {name},

Your account has been created.

Email: {email}
Password: 1234

Please login and change your password.

System: NED-SEMS
"""
            send_email(email, "Account Created", message)

            flash("User created successfully!", "success")
            return redirect('/admin/manage-users')

        # ======================
        # GET: LOAD SCHOOLS
        # ======================
        cursor.execute("SELECT school_id, school_name, district FROM schools")
        schools = cursor.fetchall()

        return render_template('admin/add_user.html', schools=schools)

    except Exception as e:
        conn.rollback()
        flash(f"Error: {e}", "error")

    finally:
        cursor.close()
        conn.close()


# =========================
# ADD SCHOOL
# =========================
@admin.route('/add-school', methods=['GET', 'POST'])
def add_school():

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor()

    if request.method == 'POST':
        cursor.execute("""
            INSERT INTO schools (school_name, district, address,division)
            VALUES (%s, %s, %s, 'Northen')
        """, (
            request.form['school_name'],
            request.form['district'],
            request.form['address']
        ))

        conn.commit()
        flash("School added successfully!", "success")
        return redirect('/admin/manage-schools')

    return render_template('admin/add_school.html')

# =========================
# MANAGE SCHOOLS (ADVANCED)
# =========================
@admin.route('/manage-schools')
def manage_schools():

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    search = request.args.get('search', '')
    district_filter = request.args.get('district', '')
    page = request.args.get('page', 1, type=int)

    per_page = 10
    offset = (page - 1) * per_page

    query = "SELECT * FROM schools WHERE 1=1"
    params = []

    if search:
        query += " AND school_name LIKE %s"
        params.append(f"%{search}%")

    if district_filter:
        query += " AND district = %s"
        params.append(district_filter)

    # COUNT
    count_query = query.replace("SELECT *", "SELECT COUNT(*) as total")
    cursor.execute(count_query, params)
    total = cursor.fetchone()['total']

    total_pages = (total + per_page - 1) // per_page

    # DATA
    query += " ORDER BY school_id DESC LIMIT %s OFFSET %s"
    params.extend([per_page, offset])

    cursor.execute(query, params)
    schools = cursor.fetchall()

    # DISTRICTS
    cursor.execute("SELECT DISTINCT district FROM schools")
    districts = cursor.fetchall()

    return render_template(
        'admin/manage_schools.html',
        schools=schools,
        page=page,
        total_pages=total_pages,
        search=search,
        district_filter=district_filter,
        districts=districts
    )
# =========================
# EDIT SCHOOL (SAFE)
# =========================
@admin.route('/edit-school/<int:school_id>', methods=['GET', 'POST'])
def edit_school(school_id):

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    # ===== UPDATE (POST) =====
    if request.method == 'POST':
        school_name = request.form['school_name']
        district = request.form['district']
        address = request.form['address']

        cursor.execute("""
            UPDATE schools
            SET school_name=%s, district=%s, address=%s
            WHERE school_id=%s
        """, (school_name, district, address, school_id))

        conn.commit()

        flash("School updated successfully!", "success")

        return redirect(url_for('admin.manage_schools'))

    # ===== FETCH SCHOOL (GET) =====
    cursor.execute("SELECT * FROM schools WHERE school_id = %s", (school_id,))
    school = cursor.fetchone()

    if not school:
        return "School not found", 404

    return render_template('admin/edit_school.html', school=school)

# =========================
# DELETE SCHOOL (SAFE)
# =========================
@admin.route('/delete-school/<int:id>')
def delete_school(id):

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor()

    try:
        print("RECEIVED ID:", id)

        # STEP 1: Unassign teachers from this school
        cursor.execute("""
            UPDATE users
            SET school_id = NULL
            WHERE school_id = %s
        """, (id,))

        print("UPDATED ROWS:", cursor.rowcount)

        # STEP 2: Delete school
        cursor.execute("""
            DELETE FROM schools
            WHERE school_id = %s
        """, (id,))

        print("SCHOOL DELETED:", cursor.rowcount)

        conn.commit()

        flash("School deleted successfully. Teachers are now unassigned.", "success")

    except Exception as e:
        conn.rollback()
        print("ERROR:", e)
        flash(f"Failed to delete school: {e}", "error")

    finally:
        cursor.close()
        conn.close()

    return redirect('/admin/reassign-teachers')


# =========================
# REASSIGN TEACHERS PAGE
# =========================
@admin.route('/reassign-teachers')
def reassign_teachers():

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    try:
        # Fetch teachers without school
        cursor.execute("""
            SELECT user_id, name, email
            FROM users
            WHERE role = 'teacher'
            AND (school_id IS NULL OR school_id = '' OR school_id = 0)
        """)
        teachers = cursor.fetchall()

        # Fetch all schools
        cursor.execute("SELECT school_id, school_name FROM schools")
        schools = cursor.fetchall()

    except Exception as e:
        print("ERROR:", e)
        teachers = []
        schools = []
        flash("Error loading reassignment data", "error")

    finally:
        cursor.close()
        conn.close()

    return render_template(
        "admin/reassign_teachers.html",
        teachers=teachers,
        schools=schools
    )


# =========================
# ASSIGN TEACHER (SAFE)
# =========================
@admin.route('/assign-teacher', methods=['POST'])
def assign_teacher():

    if not admin_required():
        return redirect('/')

    teacher_id = request.form.get('teacher_id')
    school_id = request.form.get('school_id')

    if not teacher_id or not school_id:
        flash("Missing teacher or school selection", "error")
        return redirect('/admin/reassign-teachers')

    conn = get_db_connection()
    cursor = conn.cursor()

    try:
        cursor.execute("""
            UPDATE users
            SET school_id = %s
            WHERE user_id = %s AND role = 'teacher'
        """, (school_id, teacher_id))

        conn.commit()

        if cursor.rowcount > 0:
            flash("Teacher assigned successfully!", "success")
        else:
            flash("No teacher was updated. Check data.", "warning")

    except Exception as e:
        conn.rollback()
        print("ERROR:", e)
        flash(f"Error assigning teacher: {e}", "error")

    finally:
        cursor.close()
        conn.close()

    return redirect('/admin/reassign-teachers')

@admin.route('/exams')
def exam_list():

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT e.*, s.subject_name
        FROM exams e
        LEFT JOIN subjects s ON e.subject_id = s.subject_id
        ORDER BY e.exam_id DESC
    """)

    exams = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template('admin/exam_list.html', exams=exams)


# =========================================================
# ASSIGN ITEM WRITERS
# =========================================================
@admin.route('/exams/assign-item-writers/<int:exam_id>', methods=['GET', 'POST'])
def assign_item_writers(exam_id):
    if not admin_required():
        return redirect(url_for('auth.login'))

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    # Get exam
    cursor.execute("SELECT * FROM exams WHERE exam_id=%s", (exam_id,))
    exam = cursor.fetchone()

    # If form submitted → SAVE assignments
    if request.method == 'POST':
        selected_teachers = request.form.getlist('teachers')

        for teacher_id in selected_teachers:
            try:
                cursor.execute("""
                    INSERT INTO exam_assignments (exam_id, teacher_id, role, assigned_by)
                    VALUES (%s, %s, 'item_writer', %s)
                """, (exam_id, teacher_id, session['user_id']))
            except:
                # prevents duplicate crash because table has UNIQUE constraint
                pass

        conn.commit()
        flash("Item writers assigned successfully!", "success")
        return redirect(url_for('admin.assign_item_writers', exam_id=exam_id))

    # Get teachers list
    cursor.execute("SELECT user_id, name, email FROM users WHERE role='teacher'")
    teachers = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template(
        "admin/assign_item_writers.html",
        exam=exam,
        teachers=teachers
    )


@admin.route('/exams/assign-moderators/<int:exam_id>', methods=['GET', 'POST'])
def assign_moderators(exam_id):
    if not admin_required():
        return redirect(url_for('auth.login'))

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT * FROM exams WHERE exam_id=%s", (exam_id,))
    exam = cursor.fetchone()

    if request.method == 'POST':
        selected_teachers = request.form.getlist('teachers')

        for teacher_id in selected_teachers:
            try:
                cursor.execute("""
                    INSERT INTO exam_assignments (exam_id, teacher_id, role, assigned_by)
                    VALUES (%s, %s, 'moderator', %s)
                """, (exam_id, teacher_id, session['user_id']))
            except:
                pass

        conn.commit()
        flash("Moderators assigned successfully!", "success")
        return redirect(url_for('admin.assign_moderators', exam_id=exam_id))

    cursor.execute("SELECT user_id, name, email FROM users WHERE role='teacher'")
    teachers = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template(
        "admin/assign_moderators.html",
        exam=exam,
        teachers=teachers
    )