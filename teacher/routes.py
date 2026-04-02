# Teacher routes

from flask import Blueprint, render_template, session, redirect

teacher = Blueprint('teacher', __name__, url_prefix='/teacher')

@teacher.route('/dashboard')
def dashboard():
    if 'user_id' not in session:
        return redirect('/')
    return render_template('teacher/dashboard.html')