def get_exam_results_data(exam_id):
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    query = """
    SELECT 
        r.result_id,
        r.percentage,
        r.grade,
        s.student_id,
        s.name AS student_name,
        sc.school_id,
        sc.school_name,
        sc.district,
        e.exam_id,
        sub.subject_name
    FROM results r
    JOIN students s ON r.student_id = s.student_id
    JOIN schools sc ON s.school_id = sc.school_id
    JOIN exams e ON r.exam_id = e.exam_id
    JOIN subjects sub ON e.subject_id = sub.subject_id
    WHERE r.exam_id = %s AND r.status = 'approved'
    """

    cursor.execute(query, (exam_id,))
    data = cursor.fetchall()
    conn.close()
    return data