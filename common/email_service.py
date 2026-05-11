import smtplib
from email.mime.text import MIMEText
import os
from dotenv import load_dotenv

load_dotenv()


# =========================================================
# BASE EMAIL SENDER
# =========================================================
def send_email(to_email, subject, message):

    sender_email = os.getenv("EMAIL_USER")
    sender_password = os.getenv("EMAIL_PASS")

    try:
        if not to_email:
            return False

        msg = MIMEText(message)
        msg['Subject'] = subject
        msg['From'] = sender_email
        msg['To'] = to_email

        server = smtplib.SMTP("smtp.gmail.com", 587)
        server.starttls()
        server.login(sender_email, sender_password)
        server.send_message(msg)
        server.quit()

        print("✅ Email sent successfully")
        return True

    except Exception as e:
        print("❌ Email error:", e)
        return False


# =========================================================
# ASSIGNMENT EMAIL (REUSABLE FOR ALL ROLES)
# =========================================================
def send_assignment_email(email, name, exam_name, role):

    subject = "NED-SEMS Assignment Notification"

    message = f"""
Hello {name},

You have been assigned a new role in the system.

Exam: {exam_name}
Role: {role}

Please log in to your account to view details.

Regards,
NED-SEMS
"""

    return send_email(email, subject, message)