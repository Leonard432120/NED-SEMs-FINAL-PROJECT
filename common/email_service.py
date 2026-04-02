import smtplib
from email.mime.text import MIMEText

def send_email(to_email, subject, message):

    sender_email = "leonardmlungupro@gmail.com"
    sender_password = "pzza mjot khbl ojya"  # App password

    msg = MIMEText(message)
    msg['Subject'] = subject
    msg['From'] = sender_email
    msg['To'] = to_email

    try:
        server = smtplib.SMTP('smtp.gmail.com', 587)
        server.starttls()
        server.login(sender_email, sender_password)

        server.send_message(msg)
        server.quit()

        print("✅ Email sent successfully")

    except Exception as e:
        print("❌ Email failed:", e)