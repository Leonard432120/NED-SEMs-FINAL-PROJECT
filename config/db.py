# Database connection placeholder

import mysql.connector

def get_db_connection():
    conn = mysql.connector.connect(
        host="localhost",
        user="root",          # your MySQL username
        password="",          # your MySQL password (empty for XAMPP/WAMP usually)
        database="ned_sems"
    )
    return conn