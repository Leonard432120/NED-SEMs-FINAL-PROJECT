from flask import Blueprint, render_template, session, redirect, url_for, request, flash
from config.db import get_db_connection
import os
from werkzeug.utils import secure_filename

teacher = Blueprint('teacher', __name__, url_prefix='/teacher')

UPLOAD_FOLDER = "static/uploads/exams"


# =========================================================
# SECURITY CHECK
# =========================================================
def is_teacher():
    return 'user_id' in session and session.get('role') == 'teacher'


# =========================================================
# DASHBOARD
# =========================================================
@teacher.route('/dashboard')
def dashboard():

    if not is_teacher():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    user_id = session['user_id']

    cursor.execute("""
        SELECT COUNT(*) as total
        FROM exam_assignments
        WHERE teacher_id=%s AND role='item_writer'
    """, (user_id,))
    assigned = cursor.fetchone()['total']

    cursor.execute("""
        SELECT COUNT(*) as total
        FROM exam_assignments
        WHERE teacher_id=%s AND role='moderator'
    """, (user_id,))
    moderations = cursor.fetchone()['total']

    cursor.close()
    conn.close()

    return render_template(
        'teacher/dashboard.html',
        assigned=assigned,
        moderations=moderations
    )


# =========================================================
# ITEM WRITER - ASSIGNED EXAMS
# =========================================================
@teacher.route('/assigned_exams')
def assigned_exams():

    if not is_teacher():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT 
            ea.exam_id,
            e.title,
            e.status,
            s.subject_name,
            e.year,
            e.class
        FROM exam_assignments ea
        JOIN exams e ON ea.exam_id = e.exam_id
        JOIN subjects s ON e.subject_id = s.subject_id
        WHERE ea.teacher_id=%s AND ea.role='item_writer'
        ORDER BY e.exam_id DESC
    """, (session['user_id'],))

    exams = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template('teacher/assigned_exams.html', exams=exams)


# =========================================================
# UPLOAD EXAM
# =========================================================
@teacher.route('/upload-exam/<int:exam_id>', methods=['GET', 'POST'])
def upload_exam(exam_id):

    if not is_teacher():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT * FROM exam_assignments
        WHERE teacher_id=%s AND exam_id=%s AND role='item_writer'
    """, (session['user_id'], exam_id))

    assignment = cursor.fetchone()

    if not assignment:
        flash("Not allowed")
        return redirect(url_for('teacher.assigned_exams'))

    cursor.execute("""
        SELECT e.*, s.subject_name
        FROM exams e
        JOIN subjects s ON e.subject_id = s.subject_id
        WHERE e.exam_id=%s
    """, (exam_id,))
    exam = cursor.fetchone()

    if request.method == 'POST':

        file = request.files.get('exam_file')

        if not file or file.filename == '':
            flash("Select file")
            return redirect(request.url)

        filename = secure_filename(file.filename)
        os.makedirs(UPLOAD_FOLDER, exist_ok=True)

        file_path = os.path.join(UPLOAD_FOLDER, filename)
        file.save(file_path)

        cursor.execute("""
            SELECT MAX(version_number) as v
            FROM exam_documents
            WHERE exam_id=%s
        """, (exam_id,))
        row = cursor.fetchone()

        next_v = 1 if row['v'] is None else row['v'] + 1

        cursor.execute("""
            UPDATE exam_documents
            SET is_current=0
            WHERE exam_id=%s
        """, (exam_id,))

        cursor.execute("""
            INSERT INTO exam_documents
            (exam_id, uploaded_by, file_path, version_number, is_current)
            VALUES (%s,%s,%s,%s,1)
        """, (exam_id, session['user_id'], file_path, next_v))

        cursor.execute("""
            UPDATE exams SET status='submitted'
            WHERE exam_id=%s
        """, (exam_id,))

        conn.commit()

        flash("Uploaded successfully for moderation")
        return redirect(url_for('teacher.my_submissions'))

    cursor.close()
    conn.close()

    return render_template('teacher/upload_exam.html', exam=exam)


# =========================================================
# MY SUBMISSIONS
# =========================================================
@teacher.route('/my-submissions')
def my_submissions():

    if not is_teacher():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT 
            d.document_id,
            d.exam_id,
            d.file_path,
            d.version_number,
            d.uploaded_at,
            e.title,
            e.status
        FROM exam_documents d
        JOIN exams e ON d.exam_id = e.exam_id
        WHERE d.uploaded_by = %s
        ORDER BY d.uploaded_at DESC
    """, (session['user_id'],))

    submissions = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template('teacher/my_submissions.html', submissions=submissions)


# =========================================================
# MODERATION DASHBOARD
# =========================================================
@teacher.route('/moderation-dashboard')
def moderation_dashboard():

    if not is_teacher():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT COUNT(*) as total
        FROM exam_assignments ea
        JOIN exams e ON ea.exam_id=e.exam_id
        WHERE ea.teacher_id=%s
        AND ea.role='moderator'
        AND e.status='submitted'
    """, (session['user_id'],))

    pending = cursor.fetchone()['total']

    cursor.close()
    conn.close()

    return render_template(
        'teacher/moderation_dashboard.html',
        pending=pending
    )


# =========================================================
# MODERATION LIST (FIXED - NO exam_moderators)
# =========================================================
@teacher.route('/moderation-exams')
def moderation_exams():

    if 'user_id' not in session:
        return redirect('/login')

    teacher_id = session['user_id']

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT 
            e.exam_id,
            e.title,
            s.subject_name,
            e.year,
            e.class,
            e.status,
            u.name AS teacher_name
        FROM exam_assignments ea
        JOIN exams e ON ea.exam_id = e.exam_id
        JOIN subjects s ON e.subject_id = s.subject_id
        JOIN users u ON e.created_by = u.user_id
        WHERE ea.teacher_id = %s
        AND ea.role = 'moderator'
        ORDER BY e.exam_id DESC
    """, (teacher_id,))

    exams = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template(
        'teacher/moderation_exams.html',
        exams=exams
    )

# =========================================================
# MODERATE SINGLE EXAM VIEW
# =========================================================
@teacher.route('/moderate/<int:exam_id>')
def moderate_exam_view(exam_id):

    if not is_teacher():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT e.*, s.subject_name, u.name as teacher_name
        FROM exams e
        JOIN subjects s ON e.subject_id=s.subject_id
        JOIN users u ON e.created_by=u.user_id
        WHERE e.exam_id=%s
    """, (exam_id,))
    exam = cursor.fetchone()

    cursor.execute("""
        SELECT *
        FROM exam_documents
        WHERE exam_id=%s
        ORDER BY version_number DESC
        LIMIT 1
    """, (exam_id,))
    document = cursor.fetchone()

    cursor.close()
    conn.close()

    return render_template(
        'teacher/moderate_exam.html',
        exam=exam,
        document=document
    )


# =========================================================
# MODERATION ACTION
# =========================================================
@teacher.route('/moderate-action/<int:exam_id>', methods=['POST'])
def moderate_action(exam_id):

    if not is_teacher():
        return redirect('/')

    decision = request.form['decision']
    comment = request.form.get('comment')

    if decision == 'approve':
        status = 'approved'
    elif decision == 'reject':
        status = 'rejected'
    else:
        status = 'under_moderation'

    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("""
        UPDATE exams
        SET status=%s
        WHERE exam_id=%s
    """, (status, exam_id))

    cursor.execute("""
        INSERT INTO moderation
        (exam_id, reviewer_id, comments, status, review_date)
        VALUES (%s,%s,%s,%s,NOW())
    """, (exam_id, session['user_id'], comment, status))

    conn.commit()

    cursor.close()
    conn.close()

    return redirect(url_for('teacher.moderation_exams'))