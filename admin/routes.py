from flask import Blueprint, render_template, request, redirect, session, flash, url_for
from config.db import get_db_connection
from common.email_service import send_email
from common.email_service import send_assignment_email

admin = Blueprint('admin', __name__, url_prefix='/admin')


# =========================================================
# AUTH CHECK
# =========================================================
def admin_required():
    return 'user_id' in session and session.get('role') == 'admin'


# =========================================================
# SAFE EMAIL HELPER
# =========================================================
def safe_send_email(to_email, subject, message):
    try:
        if not to_email or '@' not in to_email:
            print("⚠️ Invalid email skipped:", to_email)
            return False

        return send_email(to_email, subject, message)

    except Exception as e:
        print("❌ Email error:", e)
        return False


# =========================================================
# DASHBOARD
# =========================================================
@admin.route('/dashboard')
def dashboard():

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    try:
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

        cursor.execute("""
            SELECT s.school_name, COUNT(st.student_id) AS total
            FROM schools s
            LEFT JOIN students st ON s.school_id = st.school_id
            GROUP BY s.school_id
        """)
        chart_data = cursor.fetchall()

        cursor.execute("SELECT role, COUNT(*) as total FROM users GROUP BY role")
        roles = cursor.fetchall()

        cursor.execute("SELECT name, role FROM users ORDER BY user_id DESC LIMIT 5")
        recent_users = cursor.fetchall()

        cursor.execute("""
            SELECT s.school_name, COUNT(st.student_id) AS total_students
            FROM schools s
            LEFT JOIN students st ON s.school_id = st.school_id
            GROUP BY s.school_id
            ORDER BY total_students DESC
            LIMIT 5
        """)
        top_schools = cursor.fetchall()

        cursor.execute("""
            SELECT s.school_name, COUNT(st.student_id) AS total
            FROM schools s
            JOIN students st ON s.school_id = st.school_id
            GROUP BY s.school_id
            HAVING total > 500
        """)
        overloaded_schools = cursor.fetchall()

    finally:
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


# =========================================================
# ASSIGN TEACHERS (ITEM WRITERS / MODERATORS / ETC)
# =========================================================
@admin.route('/assign', methods=['GET', 'POST'])
def assign_teachers():

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    try:

        # =====================================================
        # POST: ASSIGN TEACHER
        # =====================================================
        if request.method == 'POST':

            exam_id = request.form.get('exam_id')
            teacher_id = request.form.get('teacher_id')
            role = request.form.get('role')

            # ---------------- VALIDATION ----------------
            if not exam_id or not teacher_id or not role:
                flash("All fields are required!", "error")
                return redirect('/admin/assign')

            # ---------------- DUPLICATE CHECK ----------------
            cursor.execute("""
                SELECT 1 FROM exam_assignments
                WHERE exam_id=%s AND teacher_id=%s AND role=%s
            """, (exam_id, teacher_id, role))

            if cursor.fetchone():
                flash("This teacher is already assigned to this role!", "error")
                return redirect('/admin/assign')

            # ---------------- INSERT ASSIGNMENT ----------------
            cursor.execute("""
                INSERT INTO exam_assignments
                (exam_id, teacher_id, role, assigned_by)
                VALUES (%s, %s, %s, %s)
            """, (exam_id, teacher_id, role, session['user_id']))

            conn.commit()

            # =====================================================
            # GET EMAIL DATA (IMPORTANT FIX HERE)
            # =====================================================
            cursor.execute("""
                SELECT name, email FROM users WHERE user_id=%s
            """, (teacher_id,))
            teacher = cursor.fetchone()

            cursor.execute("""
                SELECT exam_name FROM exams WHERE exam_id=%s
            """, (exam_id,))
            exam = cursor.fetchone()

            # ================= DEBUG CHECK =================
            print("Teacher:", teacher)
            print("Exam:", exam)

            # =====================================================
            # SEND EMAIL SAFELY
            # =====================================================
            if teacher and exam:

                try:
                    result = send_assignment_email(
                        email=teacher['email'],
                        name=teacher['name'],
                        exam_name=exam['exam_name'],
                        role=role
                    )

                    print("📧 Email sent result:", result)

                except Exception as e:
                    print("❌ Email sending FAILED:", str(e))

            else:
                print("❌ Missing teacher or exam data - email not sent")

            flash("Teacher assigned successfully!", "success")
            return redirect('/admin/assign')

        # =====================================================
        # GET: LOAD FORM DATA
        # =====================================================
        cursor.execute("""
            SELECT exam_id, exam_name FROM exams ORDER BY exam_name ASC
        """)
        exams = cursor.fetchall()

        cursor.execute("""
            SELECT user_id, name, email
            FROM users
            WHERE role='teacher'
            ORDER BY name ASC
        """)
        teachers = cursor.fetchall()

        return render_template(
            'admin/assign.html',
            exams=exams,
            teachers=teachers
        )

    finally:
        cursor.close()
        conn.close()

# =========================================================
# VIEW ASSIGNMENTS
# =========================================================
@admin.route('/assignments')
def view_assignments():

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    query = """
        SELECT 
            a.assignment_id,
            a.role,
            a.assigned_at,

            e.exam_name AS exam_title,
            u.name AS teacher_name

        FROM exam_assignments a
        JOIN exams e ON a.exam_id = e.exam_id
        JOIN users u ON a.teacher_id = u.user_id

        ORDER BY a.assignment_id DESC
    """

    cursor.execute(query)
    assignments = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template(
        "admin/view_assignments.html",
        assignments=assignments
    )

@admin.route('/delete-assignment/<int:assignment_id>')
def delete_assignment(assignment_id):

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute(
        "DELETE FROM exam_assignments WHERE assignment_id=%s",
        (assignment_id,)
    )
    conn.commit()

    cursor.close()
    conn.close()

    flash("Assignment deleted successfully")
    return redirect('/admin/assignments')

@admin.route('/edit-assignment/<int:assignment_id>', methods=['GET','POST'])
def edit_assignment(assignment_id):

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    # fetch assignment
    cursor.execute("""
        SELECT ea.assignment_id, ea.role, u.name, e.exam_name
        FROM exam_assignments ea
        JOIN users u ON ea.teacher_id = u.user_id
        JOIN exams e ON ea.exam_id = e.exam_id
        WHERE ea.assignment_id=%s
    """,(assignment_id,))
    assignment = cursor.fetchone()

    # update role
    if request.method == 'POST':
        role = request.form['role']

        cursor.execute("""
            UPDATE exam_assignments
            SET role=%s
            WHERE assignment_id=%s
        """,(role, assignment_id))
        conn.commit()

        flash("Assignment updated successfully")
        return redirect('/admin/assignments')

    cursor.close()
    conn.close()

    return render_template(
        'admin/edit_assignment.html',
        assignment=assignment
    )


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

    if request.method == 'POST':

        name = request.form.get('name')
        email = request.form.get('email')
        role = request.form.get('role')
        phone = request.form.get('phone')
        password = request.form.get('password')

        conn = get_db_connection()
        cursor = conn.cursor()

        # ================= CHECK FIRST =================
        cursor.execute("SELECT * FROM users WHERE email=%s", (email,))
        existing = cursor.fetchone()

        if existing:
            flash("This email already exists ❌", "error")
            return redirect('/admin/add-user')

        # ================= INSERT USER =================
        try:
            cursor.execute("""
                INSERT INTO users(name, email, role, phone, password, status)
                VALUES (%s, %s, %s, %s, %s, 'active')
            """, (name, email, role, phone, password))

            conn.commit()

        except Exception as e:
            conn.rollback()
            flash("Failed to create user", "error")
            return redirect('/admin/add-user')

        # ================= SEND EMAIL =================
        subject = "NED-SEMS Account Created"

        message = f"""
Hello {name},

Your account has been created successfully.

Login Details:
Email: {email}
Password: {password}
Role: {role}

Please change your password after login.
"""

        send_email(email, subject, message)

        flash("User created successfully", "success")
        return redirect('/admin/manage-users')

    return render_template("admin/add_user.html")


@admin.route('/delete-user/<int:user_id>')
def delete_user(user_id):

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    # GET USER
    cursor.execute("SELECT * FROM users WHERE user_id=%s", (user_id,))
    user = cursor.fetchone()

    if not user:
        flash("User not found", "error")
        return redirect('/admin/manage-users')

    # SOFT DELETE
    cursor.execute("""
        UPDATE users
        SET status='deleted'
        WHERE user_id=%s
    """, (user_id,))

    conn.commit()

    # EMAIL NOTIFICATION
    subject = "Account Deactivated - NED-SEMS"
    message = f"""
Hello {user['name']},

Your account has been deactivated by the administrator.

If you think this is a mistake, contact support.
"""

    send_email(user['email'], subject, message)

    flash("User deactivated successfully", "success")
    return redirect('/admin/manage-users')


@admin.route('/edit-user/<int:user_id>', methods=['GET', 'POST'])
def edit_user(user_id):

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    # GET USER
    cursor.execute("SELECT * FROM users WHERE user_id=%s", (user_id,))
    user = cursor.fetchone()

    if not user:
        flash("User not found", "error")
        return redirect('/admin/manage-users')

    if request.method == 'POST':

        name = request.form['name']
        email = request.form['email']
        role = request.form['role']
        phone = request.form['phone']

        cursor2 = conn.cursor()
        cursor2.execute("""
            UPDATE users
            SET name=%s, email=%s, role=%s, phone=%s
            WHERE user_id=%s
        """, (name, email, role, phone, user_id))

        conn.commit()

        # EMAIL NOTIFICATION
        subject = "Account Updated - NED-SEMS"
        message = f"""
Hello {name},

Your account details have been updated by the administrator.

If this was not expected, contact your school admin.
"""

        send_email(email, subject, message)

        flash("User updated successfully", "success")
        return redirect('/admin/manage-users')

    return render_template("admin/edit_user.html", user=user)



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


# =========================================================
# ADD SUBJECT
# =========================================================
@admin.route('/subjects/add', methods=['GET','POST'])
def add_subject():

    if request.method == 'POST':
        subject_name = request.form['subject_name'].strip()

        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)

        # Check if subject exists (active OR archived)
        cursor.execute("""
            SELECT * FROM subjects
            WHERE LOWER(subject_name)=LOWER(%s)
        """, (subject_name,))
        subject = cursor.fetchone()

        # CASE 1 — Subject exists and ACTIVE
        if subject and subject['status'] == 'active':
            flash("Subject already exists.", "error")
            return redirect('/admin/subjects/add')

        # CASE 2 — Subject exists but ARCHIVED → RESTORE IT
        if subject and subject['status'] == 'archived':
            cursor.execute("""
                UPDATE subjects
                SET status='active'
                WHERE subject_id=%s
            """, (subject['subject_id'],))
            conn.commit()

            flash("Archived subject restored successfully 🎉", "success")
            return redirect('/admin/subjects')

        # CASE 3 — Subject does not exist → CREATE NEW
        cursor.execute("""
            INSERT INTO subjects(subject_name, status)
            VALUES(%s,'active')
        """, (subject_name,))
        conn.commit()

        flash("Subject created successfully", "success")
        return redirect('/admin/subjects')

    return render_template("admin/add_subject.html")

@admin.route('/subjects')
def subjects():

    search = request.args.get('search', '')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT * FROM subjects
        WHERE status='active'
        AND subject_name LIKE %s
        ORDER BY subject_id DESC
    """, (f"%{search}%",))

    subjects = cursor.fetchall()

    return render_template("admin/subjects.html", subjects=subjects, search=search)

@admin.route('/subjects/archived')
def archived_subjects():

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT * FROM subjects
        WHERE status='archived'
        ORDER BY subject_id DESC
    """)

    subjects = cursor.fetchall()

    return render_template("admin/subjects_archived.html", subjects=subjects)

@admin.route('/subjects/archive/<int:subject_id>')
def archive_subject(subject_id):

    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("""
        UPDATE subjects
        SET status='archived'
        WHERE subject_id=%s
    """, (id,))

    conn.commit()

    return redirect('/admin/subjects')

@admin.route('/subjects/delete/<int:subject_id>')
def delete_subject(subject_id):

    conn = get_db_connection()
    cursor = conn.cursor()

    # archive instead of delete
    cursor.execute("""
        UPDATE subjects 
        SET status='archived'
        WHERE subject_id=%s
    """, (subject_id,))
    
    conn.commit()

    flash("Subject archived successfully", "success")
    return redirect('/admin/subjects')

@admin.route('/subjects/restore/<int:subject_id>')
def restore_subject(subject_id):

    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("""
        UPDATE subjects
        SET status='active'
        WHERE subject_id=%s
    """, (subject_id,))

    conn.commit()
    conn.close()

    flash("Subject restored successfully", "success")
    return redirect('/admin/subjects')

@admin.route('/subjects/edit/<int:subject_id>', methods=['GET', 'POST'])
def edit_subject(subject_id):
    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    # GET SUBJECT
    cursor.execute("SELECT * FROM subjects WHERE subject_id=%s", (subject_id,))
    subject = cursor.fetchone()

    if request.method == 'POST':
        subject_name = request.form['subject_name']

        cursor2 = conn.cursor()
        cursor2.execute("""
            UPDATE subjects
            SET subject_name=%s
            WHERE subject_id=%s
        """, (subject_name, subject_id))

        conn.commit()
        cursor2.close()

        return redirect('/admin/subjects')

    cursor.close()
    conn.close()

    return render_template("admin/edit_subject.html", subject=subject)


@admin.route('/exams')
def exam_list():

    if not admin_required():
        return redirect('/')

    search = request.args.get('search', '').strip()
    status = request.args.get('status', '').strip()

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    query = """
        SELECT 
            e.exam_id,
            e.exam_name,
            e.subject_id,
            e.year,
            e.class,
            e.status,
            s.subject_name
        FROM exams e
        LEFT JOIN subjects s ON e.subject_id = s.subject_id
        WHERE 1=1
    """

    params = []

    # ================= SEARCH =================
    if search:
        query += " AND e.exam_name LIKE %s"
        params.append(f"%{search}%")

    # ================= STATUS FILTER =================
    # ONLY exams.status is used here
    if status:
        query += " AND e.status = %s"
        params.append(status)

    query += " ORDER BY e.exam_id DESC"

    cursor.execute(query, params)
    exams = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template(
        'admin/exam_list.html',
        exams=exams,
        search=search,
        status=status
    )



@admin.route('/exams/create', methods=['GET', 'POST'])
def create_exam():

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    try:

        if request.method == 'POST':

            exam_name = request.form['title'].strip()
            subject_id = request.form['subject_id']
            year = request.form['year']
            class_name = request.form['class']
            exam_date = request.form['exam_date']
            duration = request.form['duration_minutes']
            total_marks = request.form['total_marks']

            # =========================
            # VALIDATION
            # =========================
            if not exam_name or not subject_id:
                flash("Exam name and subject are required!", "error")
                return redirect('/admin/exams/create')

            # =========================
            # UNIQUE CHECK
            # =========================
            cursor.execute("""
                SELECT 1 FROM exams WHERE exam_name=%s
            """, (exam_name,))

            if cursor.fetchone():
                flash("Exam already exists!", "error")
                return redirect('/admin/exams/create')

            # =========================
            # INSERT
            # =========================
            cursor.execute("""
                INSERT INTO exams
                (exam_name, subject_id, created_by, exam_date, duration_minutes, total_marks, status, year, class)
                VALUES (%s, %s, %s, %s, %s, %s, 'draft', %s, %s)
            """, (
                exam_name,
                subject_id,
                session['user_id'],
                exam_date,
                duration,
                total_marks,
                year,
                class_name
            ))

            conn.commit()

            flash("Exam created successfully!", "success")
            return redirect('/admin/exams')

        # LOAD SUBJECTS
        cursor.execute("SELECT * FROM subjects")
        subjects = cursor.fetchall()

        return render_template("admin/create_exam.html", subjects=subjects)

    finally:
        cursor.close()
        conn.close()

@admin.route('/exams/view/<int:exam_id>')
def view_exam(exam_id):

    if not admin_required():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    try:
        # exam details
        cursor.execute("""
            SELECT e.*, s.subject_name
            FROM exams e
            LEFT JOIN subjects s ON e.subject_id = s.subject_id
            WHERE e.exam_id = %s
        """, (exam_id,))
        exam = cursor.fetchone()

        if not exam:
            return "Exam not found", 404

        # assignments (teachers involved)
        cursor.execute("""
            SELECT u.name, ea.role, ea.assigned_at
            FROM exam_assignments ea
            JOIN users u ON ea.teacher_id = u.user_id
            WHERE ea.exam_id = %s
        """, (exam_id,))
        assignments = cursor.fetchall()

        return render_template(
            "admin/view_exam.html",
            exam=exam,
            assignments=assignments
        )

    finally:
        cursor.close()
        conn.close()

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
