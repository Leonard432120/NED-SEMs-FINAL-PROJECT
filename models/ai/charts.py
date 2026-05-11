import matplotlib
matplotlib.use("Agg")

import matplotlib.pyplot as plt
import io
from flask import send_file

# Grade Distribution Pie Chart
def grade_chart(grade_dist):
    labels = ['A','B','C','D','F']
    values = [
        grade_dist.get('A',0),
        grade_dist.get('B',0),
        grade_dist.get('C',0),
        grade_dist.get('D',0),
        grade_dist.get('F',0)
    ]

    plt.figure()
    plt.pie(values, labels=labels, autopct='%1.1f%%')
    plt.title("Grade Distribution")

    img = io.BytesIO()
    plt.savefig(img, format='png', bbox_inches="tight")
    plt.close()
    img.seek(0)
    return send_file(img, mimetype='image/png')


# Trend Line Chart
def trend_chart(historical):
    years = [str(h['year']) for h in historical]
    scores = [float(h['avg_score']) for h in historical]

    plt.figure()
    plt.plot(years, scores, marker='o')
    plt.title("Performance Trend")
    plt.xlabel("Year")
    plt.ylabel("Average Score")
    plt.grid(True)

    img = io.BytesIO()
    plt.savefig(img, format='png', bbox_inches="tight")
    plt.close()
    img.seek(0)
    return send_file(img, mimetype='image/png')


# Subject Performance Bar Chart
def subject_chart(subject_perf):
    names = [s['subject_name'] for s in subject_perf]
    scores = [float(s['avg_score']) for s in subject_perf]

    plt.figure()
    plt.bar(names, scores)
    plt.title("Subject Performance")
    plt.xticks(rotation=30)

    img = io.BytesIO()
    plt.savefig(img, format='png', bbox_inches="tight")
    plt.close()
    img.seek(0)
    return send_file(img, mimetype='image/png')