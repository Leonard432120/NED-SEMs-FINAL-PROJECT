from config.db import get_db_connection

def get_dashboard_stats():
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT COUNT(*) AS total FROM schools")
    schools = cursor.fetchone()["total"]

    cursor.execute("SELECT COUNT(*) AS total FROM students")
    students = cursor.fetchone()["total"]

    cursor.execute("SELECT AVG(percentage) AS avg_score FROM results")
    avg_score = cursor.fetchone()["avg_score"] or 0

    cursor.close()
    conn.close()

    return {
        "schools": schools,
        "students": students,
        "avg_score": round(avg_score, 2)
    }