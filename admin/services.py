# Admin services

import matplotlib.pyplot as plt
import io, base64

# -----------------------------
# GENERIC BAR CHART
# -----------------------------
def plot_bar_chart(labels, values, title):
    plt.figure(figsize=(10, 5))
    plt.bar(labels, values)
    plt.title(title)
    plt.xticks(rotation=30, ha='right')

    buffer = io.BytesIO()
    plt.tight_layout()
    plt.savefig(buffer, format='png')
    buffer.seek(0)

    chart = base64.b64encode(buffer.read()).decode('utf-8')
    buffer.close()
    plt.close()

    return chart


# -----------------------------
# GENERIC PIE CHART
# -----------------------------
def plot_pie_chart(labels, values, title):
    plt.figure(figsize=(6, 6))
    plt.pie(values, labels=labels, autopct='%1.1f%%')
    plt.title(title)

    buffer = io.BytesIO()
    plt.savefig(buffer, format='png')
    buffer.seek(0)

    chart = base64.b64encode(buffer.read()).decode('utf-8')
    buffer.close()
    plt.close()

    return chart


# -----------------------------
# COMPUTE ANALYTICS
# -----------------------------
def compute_summary(report):

    total_schools = len(report)
    total_students = sum(r['students_count'] for r in report)
    total_teachers = sum(r['teachers_count'] for r in report)

    ratio = round(total_students / total_teachers, 1) if total_teachers else 0

    top_school = max(report, key=lambda x: x['pass_rate']) if report else None
    worst_school = min(report, key=lambda x: x['pass_rate']) if report else None

    avg_score = round(
        sum(r['avg_score'] for r in report) / total_schools, 1
    ) if total_schools else 0

    overcrowded = sum(1 for r in report if r['students_count'] > 500)

    return {
        "total_schools": total_schools,
        "total_students": total_students,
        "total_teachers": total_teachers,
        "students_per_teacher": ratio,
        "top_school": top_school['school_name'] if top_school else "N/A",
        "worst_school": worst_school['school_name'] if worst_school else "N/A",
        "avg_score": avg_score,
        "overcrowded_schools": overcrowded
    }


# -----------------------------
# AI ALERTS
# -----------------------------
def generate_alerts(report):

    alerts = []

    for r in report:

        if r['pass_rate'] < 40:
            alerts.append(f"⚠️ {r['school_name']} has low performance ({r['pass_rate']}%)")

        if r['teachers_count'] == 0:
            alerts.append(f"🚨 {r['school_name']} has NO teachers assigned")

        if r['students_count'] > 500:
            alerts.append(f"⚠️ {r['school_name']} is overcrowded ({r['students_count']} students)")

        if r['teachers_count'] > 0:
            ratio = r['students_count'] / r['teachers_count']
            if ratio > 60:
                alerts.append(f"⚠️ {r['school_name']} has high student-teacher ratio ({int(ratio)})")

    return alerts