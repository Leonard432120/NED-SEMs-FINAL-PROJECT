def log_action(user_id, action, details):
    from config.db import get_db_connection

    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("""
        INSERT INTO audit_logs (user_id, action, details)
        VALUES (%s, %s, %s)
    """, (user_id, action, details))

    conn.commit()
    cursor.close()
    conn.close()