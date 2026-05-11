import pandas as pd
import mysql.connector

conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="ned_sems"
)

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
WHERE r.percentage IS NOT NULL;
"""

df = pd.read_sql(query, conn)

print(df.head())

df.to_csv("dataset.csv", index=False)
print("Dataset saved successfully!")