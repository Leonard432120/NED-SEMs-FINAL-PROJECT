import mysql.connector
import pandas as pd

def get_connection():
    return mysql.connector.connect(
        host="localhost",
        user="root",
        password="",
        database="ned_sems"
    )

def load_training_data():

    conn = get_connection()
    cursor = conn.cursor(dictionary=True)

    query = """
        SELECT 
            r.student_id,
            r.exam_id,
            r.total_score,
            r.percentage,
            e.class,
            e.year,
            e.subject_id,
            r.position_in_class
        FROM results r
        JOIN exams e ON r.exam_id = e.exam_id
    """

    cursor.execute(query)
    data = cursor.fetchall()

    cursor.close()
    conn.close()

    # convert to DataFrame (important for ML)
    df = pd.DataFrame(data)

    return df


# test run
if __name__ == "__main__":
    df = load_training_data()
    print(df.head())