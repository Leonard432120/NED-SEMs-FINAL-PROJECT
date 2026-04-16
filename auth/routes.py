from flask import Blueprint, render_template, request, redirect, session
from flask import Blueprint, render_template, request, redirect, session, flash, url_for
from config.db import get_db_connection

auth = Blueprint('auth', __name__)

# =========================
# LANDING PAGE
# =========================
@auth.route('/')
def home():
    return render_template('index.html')   # ✅ landing page


# =========================
# LOGIN PAGE (GET)
# =========================
@auth.route('/login', methods=['GET'])
def show_login():
    return render_template('auth/login.html')


# =========================
# HANDLE LOGIN (POST)
# =========================
@auth.route('/login', methods=['POST'])
def login():

    email = request.form['email']
    password = request.form['password']

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute(
        "SELECT * FROM users WHERE email = %s AND password = %s",
        (email, password)
    )

    user = cursor.fetchone()

    cursor.close()
    conn.close()

    if user:
        session['user_id'] = user['user_id']
        session['role'] = user['role']

        if user['role'] == 'admin':
            return redirect('/admin/dashboard')

        elif user['role'] == 'teacher':
            return redirect('/teacher/dashboard')

        elif user['role'] == 'headteacher':
            return redirect('/headteacher/dashboard')

        elif user['role'] == 'examination_officer':
            return redirect('/examination_officer/dashboard')

    return "Invalid email or password"

@auth.route('/logout')
def logout():
    session.clear()
    flash("You have been logged out.", "info")
    return redirect(url_for('auth.login'))