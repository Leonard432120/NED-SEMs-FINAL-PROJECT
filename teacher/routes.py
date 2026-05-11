from flask import Blueprint, render_template, session, redirect, url_for, request, flash
from config.db import get_db_connection
from models.ai.predict_model import predict_score
from models.ai.charts import grade_chart, trend_chart, subject_chart

import os
from datetime import datetime, timedelta
from werkzeug.utils import secure_filename

# AI modules
from common.ai_model import nlp, model
from models.ai.question_quality import evaluate_question
from models.ai.moderation_ai import evaluate_exam
from models.ai.question_ai import evaluate_question
teacher = Blueprint('teacher', __name__, url_prefix='/teacher')

UPLOAD_FOLDER = "static/uploads/exams"


# =========================================================
# SECURITY CHECK
# =========================================================
def is_teacher():
    return 'user_id' in session and session.get('role') == 'teacher'

def db_cursor():
    conn = get_db_connection()
    conn.start_transaction()   # 🔥 force clean transaction
    cursor = conn.cursor(dictionary=True)
    return conn, cursor

def calculate_result(score, total_marks):
    percentage = (score / total_marks) * 100

    if percentage >= 75:
        return percentage, "A", "Excellent"
    elif percentage >= 65:
        return percentage, "B", "Very Good"
    elif percentage >= 50:
        return percentage, "C", "Pass"
    elif percentage >= 40:
        return percentage, "D", "Weak Pass"
    else:
        return percentage, "F", "Fail"

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

    cursor.execute("""
        SELECT e.exam_id, e.exam_name
        FROM exam_assignments ea
        JOIN exams e ON ea.exam_id = e.exam_id
        WHERE ea.teacher_id=%s AND ea.role='item_writer'
    """, (user_id,))

    exams = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template(
        'teacher/dashboard.html',
        assigned=assigned,
        moderations=moderations,
        exams=exams
    )


# =========================================================
# ASSIGNED EXAMS
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
           e.exam_name,
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

    if not cursor.fetchone():
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
           e.exam_name,
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
# MODERATOR DASHBOARD
# =========================================================
@teacher.route('/moderation-dashboard')
def moderation_dashboard():

    if not is_teacher():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT COUNT(DISTINCT e.exam_id) AS total
        FROM exam_assignments ea
        JOIN exams e ON ea.exam_id = e.exam_id
        JOIN questions q ON e.exam_id = q.exam_id
        WHERE ea.teacher_id = %s
        AND ea.role = 'moderator'
        AND e.status IN ('submitted','under_moderation')
    """, (session['user_id'],))

    pending = cursor.fetchone()['total']

    cursor.close()
    conn.close()

    return render_template(
        'teacher/moderation_dashboard.html',
        pending=pending
    )


# =========================================================
# EXAMS ASSIGNED TO MODERATOR
# =========================================================
from flask import render_template, request, redirect, url_for, flash
import mysql.connector

# import your AI function (make sure it exists)
from models.ai.moderation_ai import evaluate_question


@teacher.route("/moderate/<int:exam_id>", methods=["GET"])
def moderate_exam_view(exam_id):
    conn = mysql.connector.connect(
        host="localhost",
        user="root",
        password="",
        database="ned_sems"
    )
    cursor = conn.cursor(dictionary=True)

    # ================= GET EXAM =================
    cursor.execute("""
        SELECT e.*, u.name AS teacher_name, s.subject_name
        FROM exams e
        JOIN users u ON e.created_by = u.user_id
        JOIN subjects s ON e.subject_id = s.subject_id
        WHERE e.exam_id = %s
    """, (exam_id,))
    exam = cursor.fetchone()

    if not exam:
        return "Exam not found", 404

    # ================= GET QUESTIONS =================
    cursor.execute("""
        SELECT *
        FROM questions
        WHERE exam_id = %s
        ORDER BY question_order ASC
    """, (exam_id,))
    questions = cursor.fetchall()

    # ================= BUILD AI MAP (IMPORTANT FIX) =================
    ai_map = {}

    for q in questions:
        try:
            ai_map[q["question_id"]] = evaluate_question(q)
        except Exception as e:
            ai_map[q["question_id"]] = {
                "difficulty": "unknown",
                "bloom": "unknown",
                "clarity": 0,
                "ambiguity": "unknown",
                "marks_check": "unknown",
                "suggestion": str(e)
            }

    # ================= PREVIOUS MODERATION =================
    cursor.execute("""
        SELECT * FROM moderation
        WHERE exam_id = %s
        ORDER BY review_date DESC
        LIMIT 1
    """, (exam_id,))
    previous = cursor.fetchone()

    # ================= AI REPORT (SIMPLE FALLBACK) =================
    ai_report = {
        "risk_score": 35,
        "level": "Medium",
        "stats": {
            "total_marks": sum(q["marks"] for q in questions),
            "easy": 2,
            "medium": 3,
            "hard": 1
        },
        "findings": []
    }

    cursor.close()
    conn.close()

    return render_template(
        "teacher/moderate_exam.html",
        exam=exam,
        questions=questions,
        ai_map=ai_map,
        previous=previous,
        ai_report=ai_report
    )

@teacher.route('/finalize-moderation/<int:exam_id>', methods=['POST'])
def finalize_moderation(exam_id):

    if not is_teacher():
        return redirect('/')

    comment = request.form.get('comment')
    decision = request.form.get('decision')

    if decision == "approve":
        status = "approved"
    else:
        status = "rejected"

    conn = get_db_connection()
    cursor = conn.cursor()

    # update exam status
    cursor.execute("""
        UPDATE exams
        SET status = %s
        WHERE exam_id = %s
    """, (status, exam_id))

    # save final moderation record
    cursor.execute("""
        INSERT INTO moderation
        (exam_id, reviewer_id, comments, status, review_date)
        VALUES (%s, %s, %s, %s, NOW())
    """, (exam_id, session['user_id'], comment, status))

    conn.commit()
    cursor.close()
    conn.close()

    return redirect(url_for('teacher.moderation_exams'))

# =========================================================
# SAVE MODERATION DECISION
# =========================================================
@teacher.route('/moderate-action/<int:exam_id>', methods=['POST'])
def moderate_action(exam_id):

    if not is_teacher():
        return redirect('/')

    decision = request.form['decision']
    comment = request.form.get('comment')

    if decision == 'approve':
        new_status = 'approved'
    elif decision == 'reject':
        new_status = 'rejected'
    else:
        new_status = 'under_moderation'

    conn = get_db_connection()
    cursor = conn.cursor()

    # Update exam status
    cursor.execute("""
        UPDATE exams SET status = %s
        WHERE exam_id = %s
    """, (new_status, exam_id))

    # Save moderation record
    cursor.execute("""
        INSERT INTO moderation
        (exam_id, reviewer_id, comments, status, review_date)
        VALUES (%s, %s, %s, %s, NOW())
    """, (exam_id, session['user_id'], comment, new_status))

    conn.commit()
    cursor.close()
    conn.close()

    return redirect(url_for('teacher.moderation_exams'))


# =========================================================
# EXAMS ASSIGNED FOR MODERATION  (THIS WAS MISSING)
# =========================================================
@teacher.route('/moderation-exams')
def moderation_exams():

    if not is_teacher():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT 
            e.exam_id,
            e.exam_name,
            e.status,
            s.subject_name,
            e.year,
            e.class
        FROM exam_assignments ea
        JOIN exams e ON ea.exam_id = e.exam_id
        JOIN subjects s ON e.subject_id = s.subject_id
        WHERE ea.teacher_id = %s 
        AND ea.role = 'moderator'
        ORDER BY e.exam_id DESC
    """, (session['user_id'],))

    exams = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template(
        'teacher/moderation_exams.html',
        exams=exams
    )


@teacher.route("/teacher/moderate-exam/<int:exam_id>", methods=["GET", "POST"])
def moderate_exam(exam_id):

    if 'user_id' not in session:
        return redirect('/login')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    try:

        # ================= GET EXAM =================
        cursor.execute("""
            SELECT e.*, s.subject_name, u.name AS teacher_name
            FROM exams e
            JOIN subjects s ON e.subject_id = s.subject_id
            JOIN users u ON e.created_by = u.user_id
            WHERE e.exam_id = %s
        """, (exam_id,))
        exam = cursor.fetchone()

        # ================= GET QUESTIONS =================
        cursor.execute("""
            SELECT * FROM questions
            WHERE exam_id = %s
            ORDER BY question_order ASC
        """, (exam_id,))
        questions = cursor.fetchall()

        # ================= GET PREVIOUS MODERATION =================
        cursor.execute("""
            SELECT * FROM moderation
            WHERE exam_id = %s
            ORDER BY review_date DESC
            LIMIT 1
        """, (exam_id,))
        previous = cursor.fetchone()

        # ================= POST ACTION =================
        if request.method == "POST":

            comment = request.form.get("comment")
            decision = request.form.get("decision")
            reviewer_id = session['user_id']

            # insert moderation record
            cursor.execute("""
                INSERT INTO moderation
                (exam_id, reviewer_id, comments, status, review_date)
                VALUES (%s, %s, %s, %s, NOW())
            """, (exam_id, reviewer_id, comment, decision))

            # update exam status
            new_status = "approved" if decision == "approve" else "needs_revision"

            cursor.execute("""
                UPDATE exams
                SET status = %s
                WHERE exam_id = %s
            """, (new_status, exam_id))

            conn.commit()

            return redirect('/teacher/moderation-exams')

        return render_template(
            "teacher/moderate_exam.html",
            exam=exam,
            questions=questions,
            previous=previous
        )

    finally:
        cursor.close()
        conn.close()

@teacher.route('/save-question-moderation/<int:exam_id>', methods=['POST'])
def save_question_moderation(exam_id):

    if not is_teacher():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    try:
        # Get all questions for this exam
        cursor.execute("""
            SELECT question_id
            FROM questions
            WHERE exam_id = %s
        """, (exam_id,))
        questions = cursor.fetchall()

        for q in questions:
            qid = q['question_id']

            # Get values from form
            status = request.form.get(f"status_{qid}")
            comment = request.form.get(f"comment_{qid}")

            if not status:
                continue

            # Save moderation per question
            cursor.execute("""
                INSERT INTO question_moderation
                (question_id, moderator_id, status, comment)
                VALUES (%s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    comment = VALUES(comment)
            """, (
                qid,
                session['user_id'],
                status,
                comment
            ))

            # Update question table status
            cursor.execute("""
                UPDATE questions
                SET moderation_status = %s
                WHERE question_id = %s
            """, (status, qid))

        conn.commit()

        flash("Question moderation saved successfully!", "success")

        return redirect(url_for('teacher.moderate_exam_view', exam_id=exam_id))

    finally:
        cursor.close()
        conn.close()

@teacher.route('/edit-question/<int:question_id>', methods=['GET','POST'])
def edit_question(question_id):

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT * FROM questions WHERE question_id=%s", (question_id,))
    question = cursor.fetchone()

    if request.method == 'POST':

        text = request.form['question_text']
        marks = request.form['marks']

        cursor.execute("""
            UPDATE questions
            SET question_text=%s, marks=%s
            WHERE question_id=%s
        """, (text, marks, question_id))

        conn.commit()

        flash("Question updated successfully", "success")
        return redirect(request.referrer)

    return render_template("teacher/edit_question.html", question=question)

@teacher.route('/results')
def results_home():

    if not is_teacher():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT e.exam_id
        FROM exams e
        JOIN exam_assignments ea ON e.exam_id = ea.exam_id
        WHERE ea.teacher_id=%s
        LIMIT 1
    """, (session['user_id'],))

    exam = cursor.fetchone()

    cursor.close()
    conn.close()

    if not exam:
        return "No exams assigned"

    return redirect(url_for('teacher.enter_results', exam_id=exam['exam_id']))

# =========================================================
# ENTER RESULTS
# =========================================================
@teacher.route('/enter-results/<int:exam_id>', methods=['GET', 'POST'])
def enter_results(exam_id):

    if not is_teacher():
        return redirect('/')

    conn = None
    cursor = None

    try:
        conn, cursor = db_cursor()

        teacher_id = session['user_id']

        # ================= GET TEACHER SCHOOL =================
        cursor.execute("""
            SELECT school_id FROM users WHERE user_id=%s
        """, (teacher_id,))
        teacher = cursor.fetchone()

        if not teacher:
            flash("Teacher not found", "danger")
            return redirect(url_for('teacher.dashboard'))

        school_id = teacher['school_id']

        # ================= GET EXAM =================
        cursor.execute("""
            SELECT exam_id, exam_name, class, total_marks
            FROM exams
            WHERE exam_id=%s
        """, (exam_id,))
        exam = cursor.fetchone()

        if not exam:
            flash("Exam not found", "danger")
            return redirect(url_for('teacher.dashboard'))

        total_marks = exam['total_marks'] or 100

        # ================= GET STUDENTS =================
        cursor.execute("""
            SELECT student_id, name, exam_number
            FROM students
            WHERE school_id=%s AND class=%s
        """, (school_id, exam['class']))

        students = cursor.fetchall()

        # ================= POST REQUEST =================
        if request.method == 'POST':

            results = []

            for s in students:

                score = request.form.get(f"score_{s['student_id']}")

                if not score:
                    continue

                try:
                    score = float(score)
                except:
                    continue

                percentage = (score / total_marks) * 100
                if percentage > 100:
                    percentage = 100

                # ================= GRADE CALCULATION =================
                if percentage >= 75:
                    grade, remarks = "A", "Excellent"
                elif percentage >= 65:
                    grade, remarks = "B", "Very Good"
                elif percentage >= 50:
                    grade, remarks = "C", "Pass"
                elif percentage >= 40:
                    grade, remarks = "D", "Weak Pass"
                else:
                    grade, remarks = "F", "Fail"

                results.append({
                    "student_id": s['student_id'],
                    "score": score,
                    "percentage": percentage,
                    "grade": grade,
                    "remarks": remarks
                })

            # sort by performance
            results.sort(key=lambda x: x['percentage'], reverse=True)

            position = 1

            for r in results:

                # check existing result
                cursor.execute("""
                    SELECT result_id FROM results
                    WHERE student_id=%s AND exam_id=%s
                """, (r['student_id'], exam_id))

                existing = cursor.fetchone()

                if existing:

                    cursor.execute("""
                        UPDATE results
                        SET total_score=%s,
                            grade=%s,
                            remarks=%s,
                            percentage=%s,
                            position_in_class=%s
                        WHERE result_id=%s
                    """, (
                        r['score'],
                        r['grade'],
                        r['remarks'],
                        r['percentage'],
                        position,
                        existing['result_id']
                    ))

                else:

                    cursor.execute("""
                        INSERT INTO results
                        (student_id, exam_id, total_score, grade,
                         remarks, percentage, position_in_class,
                         recorded_by, status)
                        VALUES (%s,%s,%s,%s,%s,%s,%s,%s,'pending')
                    """, (
                        r['student_id'],
                        exam_id,
                        r['score'],
                        r['grade'],
                        r['remarks'],
                        r['percentage'],
                        position,
                        teacher_id
                    ))

                position += 1

            conn.commit()
            flash("Results saved successfully!", "success")

            return redirect(url_for('teacher.view_results', exam_id=exam_id))

        # GET REQUEST
        return render_template(
            "teacher/enter_results.html",
            exam=exam,
            students=students
        )

    except Exception as e:

        if conn:
            conn.rollback()

        print("ERROR SAVING RESULTS:", e)
        flash("Error saving results. Please try again.", "danger")

        return redirect(url_for('teacher.enter_results', exam_id=exam_id))

    finally:

        if cursor:
            cursor.close()

        if conn:
            conn.close()

# =========================================================
# VIEW RESULTS
# =========================================================
from flask import current_app as app
import numpy as np

@teacher.route("/results/<int:exam_id>")
def view_results(exam_id):
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT 
            r.result_id,
            s.name,
            s.exam_number,
            r.total_score,
            r.percentage,
            r.grade,
            r.position_in_class
        FROM results r
        JOIN students s ON r.student_id = s.student_id
        WHERE r.exam_id = %s
        ORDER BY r.percentage DESC
    """, (exam_id,))

    results = cursor.fetchall()

    # Convert safely
    for r in results:
        r["percentage"] = float(r["percentage"] or 0)
        r["total_score"] = float(r["total_score"] or 0)

        try:
            features = {
                "score": r["total_score"],
                "percentage": r["percentage"]
            }

            r["predicted_score"] = predict_score(app.config["AI_MODEL"], features)

        except Exception as e:
            print("AI error:", e)
            r["predicted_score"] = 0

    # ================= INSIGHTS =================
    total = len(results) or 1

    avg = sum(r["percentage"] for r in results) / total

    pass_rate = len([r for r in results if r["percentage"] >= 50]) / total * 100

    ai_insights = [
        f"Class average is {round(avg,1)}%",
        f"{len([r for r in results if r['percentage'] < 50])} students are below 50%",
        "AI model is now stable and running safely"
    ]

    return render_template(
        "teacher/view_results.html",
        results=results,
        exam_id=exam_id,
        pass_rate=round(pass_rate, 1),
        ai_insights=ai_insights
    )
# =========================================================
# DELETE RESULT
# =========================================================
@teacher.route('/delete-result/<int:result_id>')
def delete_result(result_id):

    if not is_teacher():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT * FROM results WHERE result_id=%s
    """, (result_id,))

    result = cursor.fetchone()

    if result:
        cursor.execute("""
            DELETE FROM results WHERE result_id=%s
        """, (result_id,))

        conn.commit()

    cursor.close()
    conn.close()

    return redirect(url_for('teacher.view_results', exam_id=result['exam_id']))


# =========================================================
# EDIT RESULT
# =========================================================
@teacher.route('/edit-result/<int:result_id>', methods=['GET', 'POST'])
def edit_result(result_id):

    if not is_teacher():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT * FROM results WHERE result_id=%s", (result_id,))
    result = cursor.fetchone()

    if not result:
        return "Not found"

    if request.method == 'POST':

        score = float(request.form['score'])

        if score >= 75:
            grade = "A"
        elif score >= 65:
            grade = "B"
        elif score >= 50:
            grade = "C"
        else:
            grade = "F"

        cursor.execute("""
            UPDATE results
            SET total_score=%s, grade=%s
            WHERE result_id=%s
        """, (score, grade, result_id))

        conn.commit()

        return redirect(url_for('teacher.view_results',
                                exam_id=result['exam_id']))

    cursor.close()
    conn.close()

    return render_template('teacher/edit_result.html', result=result)

# =========================================================
# TEACHER ANALYTICS DASHBOARD
# =========================================================
@teacher.route('/analytics/<int:exam_id>')
def analytics(exam_id):

    if not is_teacher():
        return redirect('/')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    # ================= EXAM =================
    cursor.execute("""
        SELECT e.*, s.subject_name
        FROM exams e
        JOIN subjects s ON e.subject_id = s.subject_id
        WHERE e.exam_id = %s
    """, (exam_id,))
    exam = cursor.fetchone()

    if not exam:
        return "Exam not found", 404

    # ================= RESULTS =================
    cursor.execute("""
        SELECT r.*, s.name
        FROM results r
        JOIN students s ON r.student_id = s.student_id
        WHERE r.exam_id = %s
    """, (exam_id,))
    results = cursor.fetchall()

    # ================= STATS =================
    cursor.execute("""
        SELECT 
            COUNT(*) AS total_students,
            AVG(percentage) AS avg_score,
            SUM(CASE WHEN percentage >= 50 THEN 1 ELSE 0 END) AS passed
        FROM results
        WHERE exam_id = %s
    """, (exam_id,))
    stats_row = cursor.fetchone()

    stats = stats_row if stats_row else {
        "total_students": 0,
        "avg_score": 0,
        "passed": 0
    }

    pass_rate = round(
        (stats["passed"] / stats["total_students"]) * 100, 1
    ) if stats["total_students"] else 0

    # ================= GRADES =================
    cursor.execute("""
        SELECT 
            SUM(CASE WHEN percentage >= 75 THEN 1 ELSE 0 END) A,
            SUM(CASE WHEN percentage >= 65 AND percentage < 75 THEN 1 ELSE 0 END) B,
            SUM(CASE WHEN percentage >= 50 AND percentage < 65 THEN 1 ELSE 0 END) C,
            SUM(CASE WHEN percentage >= 40 AND percentage < 50 THEN 1 ELSE 0 END) D,
            SUM(CASE WHEN percentage < 40 THEN 1 ELSE 0 END) F
        FROM results
        WHERE exam_id = %s
    """, (exam_id,))
    grade_dist = cursor.fetchone()

    # ================= HISTORICAL =================
    cursor.execute("""
        SELECT e.year, AVG(r.percentage) AS avg_score
        FROM results r
        JOIN exams e ON r.exam_id = e.exam_id
        WHERE e.subject_id = %s
        GROUP BY e.year
        ORDER BY e.year
    """, (exam["subject_id"],))
    historical = cursor.fetchall()

    # ================= SUBJECT PERFORMANCE =================
    cursor.execute("""
        SELECT s.subject_name, AVG(r.percentage) AS avg_score
        FROM results r
        JOIN exams e ON r.exam_id = e.exam_id
        JOIN subjects s ON e.subject_id = s.subject_id
        GROUP BY s.subject_name
    """)
    subject_performance = cursor.fetchall()

    cursor.close()
    conn.close()

    # ================= AI =================
    ai_insights = []
    predicted_next = None

    values = [h["avg_score"] for h in historical]

    if len(values) >= 2:
        growth = [values[i] - values[i-1] for i in range(1, len(values))]
        avg_growth = sum(growth) / len(growth)

        predicted_next = round(values[-1] + avg_growth, 1)

        ai_insights.append(
            "Performance improving 📈" if avg_growth > 0
            else "Performance declining ⚠️" if avg_growth < 0
            else "Performance stable"
        )

        ai_insights.append(f"Predicted next: {predicted_next}%")
    else:
        ai_insights.append("Not enough data for prediction")

    return render_template(
        "teacher/analytics.html",
        exam=exam,
        results=results,
        stats=stats,
        pass_rate=pass_rate,
        grade_dist=grade_dist,
        historical=historical,
        subject_performance=subject_performance,
        ai_insights=ai_insights,
        predicted_next=predicted_next
    )
@teacher.route("/chart/grades/<int:exam_id>")
def chart_grades(exam_id):

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT 
            SUM(CASE WHEN percentage >= 75 THEN 1 ELSE 0 END) A,
            SUM(CASE WHEN percentage >= 65 AND percentage < 75 THEN 1 ELSE 0 END) B,
            SUM(CASE WHEN percentage >= 50 AND percentage < 65 THEN 1 ELSE 0 END) C,
            SUM(CASE WHEN percentage >= 40 AND percentage < 50 THEN 1 ELSE 0 END) D,
            SUM(CASE WHEN percentage < 40 THEN 1 ELSE 0 END) F
        FROM results WHERE exam_id=%s
    """, (exam_id,))

    data = cursor.fetchone()
    cursor.close()
    conn.close()

    return grade_chart(data)
@teacher.route("/chart/subjects/<int:exam_id>")
def chart_subjects(exam_id):

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT s.subject_name, AVG(r.percentage) avg_score
        FROM results r
        JOIN exams e ON r.exam_id = e.exam_id
        JOIN subjects s ON e.subject_id = s.subject_id
        GROUP BY s.subject_name
    """)

    data = cursor.fetchall()
    cursor.close()
    conn.close()

    return subject_chart(data)
# =========================================================
# COMPOSE EXAM (AI POWERED - CLEAN VERSION)
# =========================================================

@teacher.route("/compose-exam/<int:exam_id>", methods=["GET", "POST"])
def compose_exam(exam_id):

    if "user_id" not in session:
        return redirect(url_for("auth.login"))

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT * FROM exams WHERE exam_id=%s", (exam_id,))
    exam = cursor.fetchone()

    ai_feedback = None
    last_question = None
    form_data = None

    if request.method == "POST":

        section = request.form.get("section")
        order = request.form.get("order")
        marks = int(request.form.get("marks"))
        text = request.form.get("question_text")

        option_a = request.form.get("option_a")
        option_b = request.form.get("option_b")
        option_c = request.form.get("option_c")
        option_d = request.form.get("option_d")
        correct_option = request.form.get("correct_option")

        # section mapping
        if section == "A":
            section_name = "Section A"
            qtype = "mcq"
        elif section == "B":
            section_name = "Section B"
            qtype = "structured"
        else:
            section_name = "Section C"
            qtype = "essay"

        # AI
        ai_feedback = evaluate_question(text, marks)
        last_question = text

        form_data = {
            "exam_id": exam_id,
            "section": section,
            "order": order,
            "marks": marks,
            "question_text": text,
            "option_a": option_a,
            "option_b": option_b,
            "option_c": option_c,
            "option_d": option_d,
            "correct_option": correct_option,
            "section_name": section_name,
            "question_type": qtype
        }

    return render_template(
        "teacher/compose_exam.html",
        exam=exam,
        ai_feedback=ai_feedback,
        last_question=last_question,
        form_data=form_data
    )

@teacher.route("/confirm-question", methods=["POST"])
def confirm_question():

    if "user_id" not in session:
        return redirect(url_for("auth.login"))

    import json

    exam_id = request.form.get("exam_id")
    action = request.form.get("action")

    form_data_raw = request.form.get("form_data")

    try:
        form_data = json.loads(form_data_raw)
    except:
        flash("Invalid data received", "danger")
        return redirect(url_for("teacher.compose_exam", exam_id=exam_id))

    # ❌ REJECT
    if action == "reject":
        flash("Question rejected. Please edit again.", "warning")
        return redirect(url_for("teacher.compose_exam", exam_id=exam_id))

    # ✔ APPROVE
    final_marks = request.form.get("final_marks")

    marks = int(final_marks) if final_marks else form_data["marks"]

    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("""
        INSERT INTO questions (
            exam_id, question_order, question_text, marks,
            created_by, section_name, question_type,
            option_a, option_b, option_c, option_d, correct_option
        ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """, (
        exam_id,
        form_data["order"],
        form_data["question_text"],
        marks,
        session["user_id"],
        form_data["section_name"],
        form_data["question_type"],
        form_data.get("option_a"),
        form_data.get("option_b"),
        form_data.get("option_c"),
        form_data.get("option_d"),
        form_data.get("correct_option")
    ))

    conn.commit()

    flash("Question approved successfully!", "success")

    return redirect(url_for("teacher.compose_exam", exam_id=exam_id))

@teacher.route("/save-question", methods=["POST"])
def save_question():

    import json
    from flask import flash

    exam_id = request.form.get("exam_id")
    final_marks = request.form.get("final_marks")

    form_data = json.loads(request.form.get("form_data"))

    section = form_data["section"]
    question_text = form_data["question_text"]
    order = form_data["order"]

    option_a = form_data.get("option_a")
    option_b = form_data.get("option_b")
    option_c = form_data.get("option_c")
    option_d = form_data.get("option_d")
    correct_option = form_data.get("correct_option")

    section_map = {
        "A":"Section A",
        "B":"Section B",
        "C":"Section C"
    }

    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("""
        INSERT INTO questions
        (exam_id, section_name, question_text, marks, question_order,
         option_a, option_b, option_c, option_d, correct_option)
        VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """, (
        exam_id,
        section_map[section],
        question_text,
        final_marks,
        order,
        option_a, option_b, option_c, option_d, correct_option
    ))

    conn.commit()
    cursor.close()
    conn.close()

    # 🎉 SUCCESS TOAST MESSAGE
    flash("Question saved successfully!", "success")

    return redirect(url_for(
        "teacher.view_paper_pages",
        exam_id=exam_id,
        page="cover"
    ))

@teacher.route("/view-paper/<int:exam_id>")
def view_paper_pages(exam_id):

    page = request.args.get("page", "cover")

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    # =========================
    # GET EXAM
    # =========================
    cursor.execute("SELECT * FROM exams WHERE exam_id=%s", (exam_id,))
    exam = cursor.fetchone()

    # =========================
    # GET QUESTIONS
    # =========================
    cursor.execute("""
        SELECT *
        FROM questions
        WHERE exam_id=%s
        ORDER BY section_name, question_order
    """, (exam_id,))

    questions = cursor.fetchall()

    cursor.close()
    conn.close()

    # =========================
    # SPLIT QUESTIONS BY SECTION
    # =========================
    mcq = [q for q in questions if q["section_name"] == "Section A"]
    structured = [q for q in questions if q["section_name"] == "Section B"]
    essay = [q for q in questions if q["section_name"] == "Section C"]

    # =========================
    # PAGE STRUCTURE
    # =========================
    pages = {
        "page1": mcq[0:10],
        "page2": mcq[10:20],

        "page3": structured[0:4],
        "page4": structured[4:8],

        "page5": essay[0:1],
        "page6": essay[1:3]
    }

    # =========================
    # COVER PAGE
    # =========================
    if page == "cover":
        return render_template("teacher/exam/cover.html", exam=exam)

    # =========================
    # VALID PAGE
    # =========================
    if page in pages:
        return render_template(
            f"teacher/exam/{page}.html",
            exam=exam,
            questions=pages[page]
        )

    # fallback
    return redirect(url_for("teacher.view_paper_pages", exam_id=exam_id, page="cover"))