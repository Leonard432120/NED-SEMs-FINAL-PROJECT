# Headteacher Module Services

import matplotlib.pyplot as plt
import io, base64
from datetime import datetime, timedelta

# ========================================
# PERFORMANCE ANALYTICS
# ========================================

def calculate_school_performance(school_id, conn):
    """
    Calculate comprehensive performance metrics for a school
    """
    
    # Get student statistics
    result = conn.query(f"""
        SELECT 
            COUNT(*) as total_students,
            SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_students
        FROM students
        WHERE school_id = {school_id}
    """)
    
    students_data = result.fetch_one() if result else {'total_students': 0, 'active_students': 0}
    
    # Get teacher statistics
    result = conn.query(f"""
        SELECT COUNT(*) as count
        FROM users
        WHERE school_id = {school_id}
        AND role = 'teacher'
        AND status = 'active'
    """)
    
    teacher_count = result.fetch_one()['count'] if result else 0
    
    # Get exam statistics
    result = conn.query(f"""
        SELECT 
            COUNT(*) as total_exams,
            SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved_exams,
            SUM(CASE WHEN status='under_moderation' THEN 1 ELSE 0 END) as under_moderation
        FROM exams
        WHERE created_by IN (SELECT user_id FROM users WHERE school_id = {school_id})
    """)
    
    exam_data = result.fetch_one() if result else {'total_exams': 0, 'approved_exams': 0, 'under_moderation': 0}
    
    # Get results statistics
    result = conn.query(f"""
        SELECT 
            COUNT(*) as total_results,
            AVG(percentage) as avg_score,
            SUM(CASE WHEN percentage >= 40 THEN 1 ELSE 0 END) as passing_count
        FROM results
        WHERE student_id IN (SELECT student_id FROM students WHERE school_id = {school_id})
    """)
    
    results_data = result.fetch_one() if result else {}
    
    pass_rate = 0
    if results_data.get('total_results', 0) > 0:
        pass_rate = round((results_data['passing_count'] / results_data['total_results']) * 100, 2)
    
    return {
        'total_students': students_data['total_students'],
        'active_students': students_data['active_students'],
        'teacher_count': teacher_count,
        'total_exams': exam_data['total_exams'],
        'approved_exams': exam_data['approved_exams'],
        'under_moderation': exam_data['under_moderation'],
        'total_results': results_data.get('total_results', 0),
        'avg_score': round(results_data.get('avg_score', 0), 2),
        'pass_rate': pass_rate
    }


def get_top_subjects(school_id, conn, limit=5):
    """
    Get top performing subjects for a school
    """
    
    result = conn.query(f"""
        SELECT 
            s.subject_id,
            s.subject_name,
            AVG(r.percentage) as avg_score,
            COUNT(r.result_id) as student_count
        FROM results r
        JOIN exams e ON r.exam_id = e.exam_id
        JOIN subjects s ON e.subject_id = s.subject_id
        JOIN students st ON r.student_id = st.student_id
        WHERE st.school_id = {school_id}
        GROUP BY s.subject_id
        ORDER BY avg_score DESC
        LIMIT {limit}
    """)
    
    subjects = []
    if result:
        while True:
            row = result.fetch_one()
            if not row:
                break
            subjects.append(row)
    
    return subjects


def get_class_performance(school_id, conn):
    """
    Get performance breakdown by class
    """
    
    result = conn.query(f"""
        SELECT 
            s.class,
            COUNT(DISTINCT s.student_id) as student_count,
            AVG(r.percentage) as avg_score,
            COUNT(r.result_id) as exam_count
        FROM results r
        JOIN students s ON r.student_id = s.student_id
        WHERE s.school_id = {school_id}
        GROUP BY s.class
        ORDER BY avg_score DESC
    """)
    
    classes = []
    if result:
        while True:
            row = result.fetch_one()
            if not row:
                break
            classes.append(row)
    
    return classes


def get_teacher_performance(school_id, conn):
    """
    Get performance metrics for each teacher
    """
    
    result = conn.query(f"""
        SELECT 
            u.user_id,
            u.name,
            COUNT(DISTINCT e.exam_id) as exam_count,
            COUNT(DISTINCT ea.teacher_id) as assigned_count,
            AVG(r.percentage) as avg_student_score
        FROM users u
        LEFT JOIN exams e ON u.user_id = e.created_by
        LEFT JOIN exam_assignments ea ON u.user_id = ea.teacher_id
        LEFT JOIN results r ON e.exam_id = r.exam_id
        WHERE u.school_id = {school_id}
        AND u.role = 'teacher'
        GROUP BY u.user_id
        ORDER BY exam_count DESC
    """)
    
    teachers = []
    if result:
        while True:
            row = result.fetch_one()
            if not row:
                break
            teachers.append(row)
    
    return teachers


# ========================================
# CHART GENERATION
# ========================================

def plot_subject_performance(subjects):
    """
    Create a bar chart showing subject performance
    """
    
    if not subjects:
        return None
    
    subject_names = [s['subject_name'] for s in subjects]
    scores = [s['avg_score'] for s in subjects]
    
    plt.figure(figsize=(10, 6))
    bars = plt.bar(subject_names, scores, color='#667eea', edgecolor='#764ba2', linewidth=2)
    
    # Add value labels on bars
    for bar in bars:
        height = bar.get_height()
        plt.text(bar.get_x() + bar.get_width()/2., height,
                f'{height:.1f}%',
                ha='center', va='bottom', fontweight='bold')
    
    plt.title('Subject Performance Comparison', fontsize=14, fontweight='bold')
    plt.ylabel('Average Score (%)', fontsize=12)
    plt.xlabel('Subject', fontsize=12)
    plt.ylim(0, 100)
    plt.xticks(rotation=45, ha='right')
    
    buffer = io.BytesIO()
    plt.tight_layout()
    plt.savefig(buffer, format='png', dpi=100)
    buffer.seek(0)
    
    chart = base64.b64encode(buffer.read()).decode('utf-8')
    buffer.close()
    plt.close()
    
    return chart


def plot_class_performance(classes):
    """
    Create a bar chart showing class performance
    """
    
    if not classes:
        return None
    
    class_names = [c['class'] for c in classes]
    scores = [c['avg_score'] for c in classes]
    student_counts = [c['student_count'] for c in classes]
    
    fig, ax = plt.subplots(figsize=(10, 6))
    
    bars = ax.bar(class_names, scores, color='#764ba2', edgecolor='#667eea', linewidth=2)
    
    # Add value labels on bars
    for i, bar in enumerate(bars):
        height = bar.get_height()
        ax.text(bar.get_x() + bar.get_width()/2., height,
                f'{height:.1f}%\n({student_counts[i]} students)',
                ha='center', va='bottom', fontweight='bold', fontsize=9)
    
    ax.set_title('Class Performance Overview', fontsize=14, fontweight='bold')
    ax.set_ylabel('Average Score (%)', fontsize=12)
    ax.set_xlabel('Class', fontsize=12)
    ax.set_ylim(0, 100)
    plt.xticks(rotation=45, ha='right')
    
    buffer = io.BytesIO()
    plt.tight_layout()
    plt.savefig(buffer, format='png', dpi=100)
    buffer.seek(0)
    
    chart = base64.b64encode(buffer.read()).decode('utf-8')
    buffer.close()
    plt.close()
    
    return chart


def plot_pass_rate_pie(pass_rate, fail_rate):
    """
    Create a pie chart showing pass/fail distribution
    """
    
    sizes = [pass_rate, fail_rate]
    labels = [f'Passed ({pass_rate}%)', f'Failed ({fail_rate}%)']
    colors = ['#28a745', '#dc3545']
    explode = (0.05, 0.05)
    
    plt.figure(figsize=(8, 6))
    plt.pie(sizes, labels=labels, colors=colors, explode=explode, 
            autopct='%1.1f%%', shadow=True, startangle=90)
    
    plt.title('Overall Pass Rate', fontsize=14, fontweight='bold')
    
    buffer = io.BytesIO()
    plt.savefig(buffer, format='png', dpi=100)
    buffer.seek(0)
    
    chart = base64.b64encode(buffer.read()).decode('utf-8')
    buffer.close()
    plt.close()
    
    return chart


# ========================================
# MONTHLY TREND ANALYSIS
# ========================================

def get_monthly_trend(school_id, conn, months=6):
    """
    Get monthly submission trends
    """
    
    date_from = (datetime.now() - timedelta(days=30*months)).strftime('%Y-%m-01')
    
    result = conn.query(f"""
        SELECT 
            DATE_FORMAT(recorded_at, '%Y-%m') as month,
            COUNT(*) as submission_count,
            AVG(percentage) as avg_score
        FROM results
        WHERE student_id IN (SELECT student_id FROM students WHERE school_id = {school_id})
        AND recorded_at >= '{date_from}'
        GROUP BY DATE_FORMAT(recorded_at, '%Y-%m')
        ORDER BY month DESC
    """)
    
    data = []
    if result:
        while True:
            row = result.fetch_one()
            if not row:
                break
            data.append(row)
    
    return sorted(data, key=lambda x: x['month'])


def plot_monthly_trend(monthly_data):
    """
    Create a line chart showing monthly trends
    """
    
    if not monthly_data:
        return None
    
    months = [datetime.strptime(d['month'], '%Y-%m').strftime('%b %Y') for d in monthly_data]
    submissions = [d['submission_count'] for d in monthly_data]
    scores = [d['avg_score'] for d in monthly_data]
    
    fig, ax1 = plt.subplots(figsize=(12, 6))
    
    color = '#667eea'
    ax1.set_xlabel('Month', fontsize=12)
    ax1.set_ylabel('Submissions', color=color, fontsize=12)
    line1 = ax1.plot(months, submissions, color=color, marker='o', linewidth=2, label='Submissions')
    ax1.tick_params(axis='y', labelcolor=color)
    
    ax2 = ax1.twinx()
    color = '#764ba2'
    ax2.set_ylabel('Average Score (%)', color=color, fontsize=12)
    line2 = ax2.plot(months, scores, color=color, marker='s', linewidth=2, label='Avg Score')
    ax2.tick_params(axis='y', labelcolor=color)
    
    plt.title('Monthly Submission & Performance Trend', fontsize=14, fontweight='bold')
    plt.xticks(rotation=45, ha='right')
    
    buffer = io.BytesIO()
    plt.tight_layout()
    plt.savefig(buffer, format='png', dpi=100)
    buffer.seek(0)
    
    chart = base64.b64encode(buffer.read()).decode('utf-8')
    buffer.close()
    plt.close()
    
    return chart


# ========================================
# ALERTS & INSIGHTS
# ========================================

def generate_school_alerts(school_id, conn):
    """
    Generate actionable alerts for headteacher based on school performance
    """
    
    alerts = []
    
    # Get performance metrics
    perf = calculate_school_performance(school_id, conn)
    
    # Alert: Low pass rate
    if perf['pass_rate'] < 50:
        alerts.append({
            'type': 'warning',
            'icon': '⚠️',
            'message': f"Low pass rate detected ({perf['pass_rate']}%). Consider implementing support programs."
        })
    
    # Alert: No active teachers
    if perf['teacher_count'] == 0:
        alerts.append({
            'type': 'critical',
            'icon': '🚨',
            'message': "No active teachers found. Please assign teachers to your school."
        })
    
    # Alert: Low exam count
    if perf['total_exams'] < 5:
        alerts.append({
            'type': 'info',
            'icon': 'ℹ️',
            'message': f"Only {perf['total_exams']} exams created. Encourage teachers to create more exams."
        })
    
    # Alert: Pending moderation
    if perf['under_moderation'] > 0:
        alerts.append({
            'type': 'info',
            'icon': '📋',
            'message': f"{perf['under_moderation']} exam(s) are waiting for moderation."
        })
    
    # Alert: High student-teacher ratio
    if perf['teacher_count'] > 0:
        ratio = perf['total_students'] / perf['teacher_count']
        if ratio > 50:
            alerts.append({
                'type': 'warning',
                'icon': '⚠️',
                'message': f"High student-teacher ratio ({int(ratio)}:1). Consider hiring more teachers."
            })
    
    return alerts


def get_headteacher_summary(school_id, conn):
    """
    Generate a comprehensive summary for the headteacher dashboard
    """
    
    summary = {
        'performance': calculate_school_performance(school_id, conn),
        'top_subjects': get_top_subjects(school_id, conn, limit=3),
        'class_performance': get_class_performance(school_id, conn),
        'teacher_stats': get_teacher_performance(school_id, conn),
        'alerts': generate_school_alerts(school_id, conn),
        'monthly_trend': get_monthly_trend(school_id, conn, months=3)
    }
    
    return summary
