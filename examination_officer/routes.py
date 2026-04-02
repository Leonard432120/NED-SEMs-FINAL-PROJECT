# Examination officer routes
from flask import Blueprint, render_template, session, redirect

examination_officer = Blueprint('examination_officer', __name__, url_prefix='/examination_officer')

@examination_officer.route('/dashboard')
def dashboard():
    if 'user_id' not in session:
        return redirect('/')
    return render_template('examination_officer/dashboard.html')