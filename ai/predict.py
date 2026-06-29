"""
ai/predict.py
==============
CLI predictor for school exam performance.

Usage:
    python predict.py --school_id=39 --exam_id=27

Returns JSON to stdout (consumed by PHP via shell_exec).
If model.pkl is missing, it automatically trains the model first.

Output JSON structure:
{
  "status": "success",
  "school_id": 39,
  "exam_id": 27,
  "school_name": "Iponga CDSS",
  "exam_name": "JCE 2026",
  "predicted_avg_score": 54.3,
  "predicted_pass_rate": 68.5,
  "confidence": 82,
  "grade_band": "Credit",
  "grade_distribution": {"D1":5,"D2":8,...},
  "subject_predictions": [...],
  "division_summary": {...},
  "risk_level": "Medium",
  "key_factors": [...],
  "mae": 4.2,
  "r2": 0.87,
  "model_version": "1.0"
}
"""

import sys
import os
import json
import pickle
import math
import argparse
import numpy as np

AI_DIR = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.dirname(AI_DIR))

MODEL_PATH  = os.path.join(AI_DIR, "model.pkl")
SCALER_PATH = os.path.join(AI_DIR, "scaler.pkl")

FEATURE_COLS = [
    "school_type_enc",
    "subject_category_enc",
    "total_students",
    "teacher_count",
    "past_avg_score",
    "past_pass_rate",
    "submission_rate",
    "marks_deadline_set",
    "days_to_exam",
    "years_of_history",
]

SCHOOL_TYPE_MAP = {
    "CDSS": 1, "DAY SECONDARY": 2, "BOARDING": 3, "PRIVATE": 4
}
CATEGORY_MAP = {
    "science": 1, "language": 2, "humanities": 3
}


# ─── Helpers ─────────────────────────────────────────────────────────────────
def clamp(v, lo=0, hi=100):
    return max(lo, min(hi, float(v)))


def score_to_grade(score):
    if score >= 80: return "D1"
    if score >= 70: return "D2"
    if score >= 65: return "D3"
    if score >= 60: return "C4"
    if score >= 55: return "C5"
    if score >= 50: return "C6"
    if score >= 40: return "P7"
    if score >= 35: return "P8"
    return "F9"


def grade_band(score):
    if score >= 70: return "Distinction"
    if score >= 55: return "Credit"
    if score >= 40: return "Pass"
    return "Fail"


def risk_level(score, pass_rate):
    if pass_rate < 40 or score < 40: return "High"
    if pass_rate < 60 or score < 50: return "Medium"
    return "Low"


def simulate_grade_distribution(avg_score, n_students):
    """Simulate realistic grade distribution around avg_score."""
    np.random.seed(int(avg_score * 7))
    scores = np.clip(np.random.normal(avg_score, 12, n_students), 0, 100)
    grades = [score_to_grade(s) for s in scores]
    dist   = {g: 0 for g in ["D1","D2","D3","C4","C5","C6","P7","P8","F9"]}
    for g in grades:
        dist[g] += 1
    return dist


def add_engineered(X_row):
    """Must match train_model.py add_engineered_features exactly."""
    teacher = X_row[FEATURE_COLS.index("teacher_count")]
    students = X_row[FEATURE_COLS.index("total_students")]
    history = X_row[FEATURE_COLS.index("years_of_history")]
    pass_r  = X_row[FEATURE_COLS.index("past_pass_rate")]
    avg     = X_row[FEATURE_COLS.index("past_avg_score")]
    sub     = X_row[FEATURE_COLS.index("submission_rate")]

    ratio   = teacher / max(students, 1)
    hist_x  = history * pass_r
    score_x = avg * sub
    return list(X_row) + [ratio, hist_x, score_x]


# ─── DB Fetch ─────────────────────────────────────────────────────────────────
def fetch_school_data(school_id: int, exam_id: int):
    try:
        import mysql.connector
        conn = mysql.connector.connect(
            host="localhost", user="root", password="", database="ned_sems"
        )
        cur = conn.cursor(dictionary=True)

        # School info
        cur.execute("""
            SELECT school_name, district, cluster_name, school_type
            FROM schools WHERE school_id = %s
        """, (school_id,))
        school = cur.fetchone() or {}

        # Exam info
        cur.execute("""
            SELECT exam_name, exam_code, start_date, year, class, marks_deadline
            FROM exams WHERE exam_id = %s
        """, (exam_id,))
        exam = cur.fetchone() or {}

        # Total students at school for this exam's class
        exam_class = exam.get("class", "")
        cur.execute("""
            SELECT COUNT(*) AS cnt FROM students
            WHERE school_id = %s AND status = 'active'
            AND (class = %s OR %s = '')
        """, (school_id, exam_class, exam_class))
        total_students = (cur.fetchone() or {}).get("cnt", 30)

        # Teacher count
        cur.execute("""
            SELECT COUNT(*) AS cnt FROM users
            WHERE school_id = %s AND role = 'teacher' AND status = 'active'
        """, (school_id,))
        teacher_count = (cur.fetchone() or {}).get("cnt", 5)

        # Historical avg & pass rate (from marks table)
        cur.execute("""
            SELECT AVG(m.score) AS avg_score,
                   SUM(CASE WHEN m.score >= 40 THEN 1 ELSE 0 END) / COUNT(*) AS pass_rate,
                   COUNT(DISTINCT e.exam_id) AS exam_count
            FROM marks m
            JOIN students st ON m.student_id = st.student_id
            JOIN exams e ON m.exam_id = e.exam_id
            WHERE st.school_id = %s AND m.status IN ('submitted','approved')
        """, (school_id,))
        hist = cur.fetchone() or {}
        past_avg  = float(hist.get("avg_score") or 0)
        past_pass = float(hist.get("pass_rate") or 0)
        years_hist = int(hist.get("exam_count") or 0)

        # Submission rate (marking assignments)
        cur.execute("""
            SELECT
              COUNT(*) AS total,
              SUM(CASE WHEN ma.status = 'assigned' THEN 1 ELSE 0 END) AS assigned
            FROM marking_assignments ma
            WHERE ma.school_id = %s AND ma.exam_id = %s
        """, (school_id, exam_id))
        ma = cur.fetchone() or {}
        total_ma  = int(ma.get("total") or 1)
        assigned  = int(ma.get("assigned") or 0)
        sub_rate  = clamp(assigned / max(total_ma, 1), 0, 1)

        # Deadline set?
        marks_deadline_set = 1 if exam.get("marks_deadline") else 0

        # Days to exam
        from datetime import date
        start_date = exam.get("start_date")
        if start_date:
            delta = (start_date - date.today()).days
            days_to_exam = max(0, delta)
        else:
            days_to_exam = 90

        # Subject-level predictions
        cur.execute("""
            SELECT s.subject_id, s.subject_name, s.category,
                   AVG(m.score) AS avg_score,
                   SUM(CASE WHEN m.score>=40 THEN 1 ELSE 0 END)/COUNT(*) AS pass_rate,
                   COUNT(*) AS mark_count
            FROM marks m
            JOIN students st ON m.student_id = st.student_id
            JOIN subjects s ON m.subject_id = s.subject_id
            WHERE st.school_id = %s AND m.exam_id = %s
              AND m.status IN ('submitted','approved')
            GROUP BY s.subject_id
        """, (school_id, exam_id))
        subject_data = cur.fetchall()

        # All subjects in the exam (for prediction even without marks)
        cur.execute("""
            SELECT s.subject_id, s.subject_name, s.category
            FROM exam_subjects es
            JOIN subjects s ON es.subject_id = s.subject_id
            WHERE es.exam_id = %s AND es.status = 'active'
        """, (exam_id,))
        all_subjects = cur.fetchall()

        # Division (all schools) avg for comparison
        cur.execute("""
            SELECT sc.district,
                   AVG(m.score) AS district_avg,
                   SUM(CASE WHEN m.score>=40 THEN 1 ELSE 0 END)/COUNT(*) AS district_pass
            FROM marks m
            JOIN students st ON m.student_id = st.student_id
            JOIN schools sc ON st.school_id = sc.school_id
            WHERE m.exam_id = %s AND m.status IN ('submitted','approved')
        """, (exam_id,))
        div_row = cur.fetchone() or {}

        conn.close()

        return {
            "school": school,
            "exam": exam,
            "total_students": int(total_students),
            "teacher_count":  int(teacher_count),
            "past_avg_score": past_avg,
            "past_pass_rate": past_pass,
            "years_of_history": years_hist,
            "submission_rate": float(sub_rate),
            "marks_deadline_set": marks_deadline_set,
            "days_to_exam": int(days_to_exam),
            "subject_data": [dict(r) for r in subject_data],
            "all_subjects": [dict(r) for r in all_subjects],
            "division_row": dict(div_row) if div_row else {},
        }
    except Exception as e:
        return {"error": str(e)}


# ─── Load model ──────────────────────────────────────────────────────────────
def load_model():
    if not os.path.exists(MODEL_PATH):
        # Auto-train
        import subprocess
        train_path = os.path.join(AI_DIR, "train_model.py")
        subprocess.run([sys.executable, train_path], check=True)

    with open(MODEL_PATH,  "rb") as f: bundle = pickle.load(f)
    with open(SCALER_PATH, "rb") as f: scaler = pickle.load(f)
    return bundle, scaler


def blend_predict_single(bundle, scaler, feature_row):
    X = np.array([add_engineered(feature_row)])
    X_s = scaler.transform(X)
    w = bundle["weights"]
    p = (w[0] * bundle["rf"].predict(X_s) +
         w[1] * bundle["gb"].predict(X_s) +
         w[2] * bundle["ridge"].predict(X_s))
    return clamp(float(p[0]))


# ─── Build key factors explanation ───────────────────────────────────────────
def build_key_factors(data, pred_score):
    factors = []
    if data["past_avg_score"] > 0:
        diff = pred_score - data["past_avg_score"]
        direction = "up" if diff > 0 else "down"
        factors.append({
            "label": "Historical Performance",
            "detail": f"Past avg {data['past_avg_score']:.1f}% — predicted to go {direction} by {abs(diff):.1f}%",
            "impact": "positive" if diff >= 0 else "negative"
        })
    else:
        factors.append({
            "label": "No Historical Data",
            "detail": "First-time prediction based on school profile",
            "impact": "neutral"
        })

    sr = data["submission_rate"]
    factors.append({
        "label": "Submission Rate",
        "detail": f"{sr*100:.0f}% of marking assignments submitted on time",
        "impact": "positive" if sr >= 0.75 else "negative"
    })

    tc = data["teacher_count"]
    ts = data["total_students"]
    ratio = tc / max(ts, 1)
    factors.append({
        "label": "Teacher-Student Ratio",
        "detail": f"1:{int(1/ratio) if ratio > 0 else '∞'} — {'Good' if ratio >= 0.06 else 'Below average'}",
        "impact": "positive" if ratio >= 0.06 else "negative"
    })

    if data["marks_deadline_set"]:
        factors.append({
            "label": "Deadline Management",
            "detail": "Marks deadline has been set — good governance indicator",
            "impact": "positive"
        })
    else:
        factors.append({
            "label": "Deadline Management",
            "detail": "No marks deadline set — risk of late submissions",
            "impact": "negative"
        })

    return factors


# ─── MAIN ────────────────────────────────────────────────────────────────────
def predict(school_id: int, exam_id: int):
    # 1. Fetch DB data
    data = fetch_school_data(school_id, exam_id)
    if "error" in data:
        return {"status": "error", "message": data["error"]}

    school = data["school"]
    exam   = data["exam"]

    # 2. Build feature row for overall school prediction
    school_type_str = (school.get("school_type") or "CDSS").upper()
    school_type_enc = SCHOOL_TYPE_MAP.get(school_type_str, 1)

    feature_row = [
        school_type_enc,
        2,  # default "language" for overall; overridden per-subject
        data["total_students"],
        data["teacher_count"],
        data["past_avg_score"],
        data["past_pass_rate"],
        data["submission_rate"],
        data["marks_deadline_set"],
        data["days_to_exam"],
        data["years_of_history"],
    ]

    bundle, scaler = load_model()

    # 3. Overall school prediction
    pred_avg = blend_predict_single(bundle, scaler, feature_row)
    pred_pass = clamp(pred_avg / 100 * (0.85 + data["submission_rate"] * 0.15) * 100)
    # refine pass rate: score->pass mapping
    pass_score_map = [(80,98),(70,92),(65,88),(60,82),(55,75),(50,65),(45,52),(40,42),(35,30),(0,10)]
    for cutoff, pct in pass_score_map:
        if pred_avg >= cutoff:
            pred_pass = clamp(pct + (pred_avg - cutoff) * 0.3)
            break

    confidence = int(clamp(
        60 + data["years_of_history"] * 3 + data["submission_rate"] * 20,
        60, 95
    ))

    # 4. Per-subject predictions
    subject_preds = []
    # Use actual marks subjects if available, else all exam subjects
    subjects_to_predict = data["subject_data"] if data["subject_data"] else data["all_subjects"]

    for subj in subjects_to_predict:
        cat_str  = (subj.get("category") or "language")
        cat_enc  = CATEGORY_MAP.get(cat_str, 2)
        past_sub = float(subj.get("avg_score") or data["past_avg_score"])
        pass_sub = float(subj.get("pass_rate") or data["past_pass_rate"])

        subj_row = list(feature_row)
        subj_row[FEATURE_COLS.index("subject_category_enc")] = cat_enc
        subj_row[FEATURE_COLS.index("past_avg_score")] = past_sub
        subj_row[FEATURE_COLS.index("past_pass_rate")] = pass_sub

        subj_pred = blend_predict_single(bundle, scaler, subj_row)
        subj_pass_pct = 0
        for cutoff, pct in pass_score_map:
            if subj_pred >= cutoff:
                subj_pass_pct = clamp(pct + (subj_pred - cutoff) * 0.3)
                break

        subject_preds.append({
            "subject_name": subj.get("subject_name", "Unknown"),
            "category": cat_str,
            "predicted_avg": round(subj_pred, 1),
            "predicted_pass_rate": round(subj_pass_pct, 1),
            "grade": score_to_grade(subj_pred),
            "actual_marks_count": int(subj.get("mark_count") or 0),
        })

    subject_preds.sort(key=lambda x: x["predicted_avg"], reverse=True)

    # 5. Grade distribution
    grade_dist = simulate_grade_distribution(pred_avg, data["total_students"])

    # 6. Division comparison
    div = data["division_row"]
    division_summary = {
        "district": school.get("district", "Unknown"),
        "district_avg": round(float(div.get("district_avg") or pred_avg), 1),
        "district_pass": round(float(div.get("district_pass") or pred_pass/100) * 100, 1),
        "school_vs_district": round(pred_avg - float(div.get("district_avg") or pred_avg), 1),
    }

    # 7. Key factors
    key_factors = build_key_factors(data, pred_avg)

    result = {
        "status": "success",
        "school_id": school_id,
        "exam_id": exam_id,
        "school_name": school.get("school_name", f"School #{school_id}"),
        "school_type": school.get("school_type", "Unknown"),
        "district": school.get("district", "Unknown"),
        "cluster": school.get("cluster_name", ""),
        "exam_name": exam.get("exam_name", f"Exam #{exam_id}"),
        "exam_class": exam.get("class", ""),
        "exam_year": str(exam.get("year", "")),
        "total_students": data["total_students"],
        "teacher_count": data["teacher_count"],
        "predicted_avg_score": round(pred_avg, 1),
        "predicted_pass_rate": round(pred_pass, 1),
        "confidence": confidence,
        "grade_band": grade_band(pred_avg),
        "risk_level": risk_level(pred_avg, pred_pass),
        "grade_distribution": grade_dist,
        "subject_predictions": subject_preds,
        "division_summary": division_summary,
        "key_factors": key_factors,
        "model_metrics": {
            "mae": round(bundle.get("mae", 0), 2),
            "r2": round(bundle.get("r2", 0), 4),
            "rmse": round(bundle.get("rmse", 0), 2),
        },
        "model_version": "1.0",
    }

    return result


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="NED-SEMS School Prediction")
    parser.add_argument("--school_id", type=int, required=True)
    parser.add_argument("--exam_id",   type=int, required=True)
    args = parser.parse_args()

    result = predict(args.school_id, args.exam_id)
    print(json.dumps(result, default=str, indent=2))
