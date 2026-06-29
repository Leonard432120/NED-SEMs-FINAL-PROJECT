"""
ai/generate_data.py
====================
Generates realistic synthetic training data for the NED-SEMS school
performance prediction model.

The synthetic data mimics real-world patterns:
  - BOARDING schools score higher on average
  - Science subjects have wider variance
  - Schools with more teachers tend to do better
  - Pass rates correlate with average score
  - Marks deadline adherence boosts submission rate

Run directly to regenerate training_data.csv:
    python generate_data.py
"""

import csv
import random
import math
import os

random.seed(42)

# ─── CONFIG ──────────────────────────────────────────────────────────────────
OUTPUT_FILE = os.path.join(os.path.dirname(__file__), "training_data.csv")

SCHOOL_TYPES = {
    "CDSS":         {"base": 48, "noise": 18, "type_enc": 1},
    "DAY SECONDARY":{"base": 54, "noise": 16, "type_enc": 2},
    "BOARDING":     {"base": 63, "noise": 14, "type_enc": 3},
    "PRIVATE":      {"base": 68, "noise": 12, "type_enc": 4},
}

SUBJECT_CATEGORIES = {
    "science":     {"modifier": -3, "cat_enc": 1},  # science harder
    "language":    {"modifier":  2, "cat_enc": 2},
    "humanities":  {"modifier":  4, "cat_enc": 3},
}

DISTRICTS = ["Chitipa", "Karonga", "Rumphi", "Mzimba", "Nkhata Bay", "Likoma"]
EXAMS     = ["JCE", "MSCE"]

# ─── FEATURE COLUMNS ─────────────────────────────────────────────────────────
COLUMNS = [
    "school_type_enc",       # 1-4
    "subject_category_enc",  # 1-3
    "total_students",        # 20-350
    "teacher_count",         # 2-25
    "past_avg_score",        # historical avg (0-100), 0 = no history
    "past_pass_rate",        # 0.0-1.0, 0 = no history
    "submission_rate",       # fraction of marking assignments submitted on time
    "marks_deadline_set",    # 0 or 1
    "days_to_exam",          # 0-365
    "years_of_history",      # 0-10
    "predicted_avg_score",   # TARGET
]


def clamp(v, lo=0, hi=100):
    return max(lo, min(hi, v))


def generate_row(school_type: str, subject_cat: str, year_idx: int):
    st   = SCHOOL_TYPES[school_type]
    sc   = SUBJECT_CATEGORIES[subject_cat]

    # base score with noise
    base = st["base"] + sc["modifier"]
    noise = random.gauss(0, st["noise"] * 0.6)
    avg_score = clamp(base + noise)

    # derived features
    total_students = random.randint(25, 350)
    teacher_count  = max(2, int(total_students / random.uniform(12, 25)))

    # years of history: 0-10
    years_hist = random.randint(0, 10)

    # past data: if no history, use 0
    if years_hist == 0:
        past_avg   = 0.0
        past_pass  = 0.0
    else:
        past_avg   = clamp(avg_score + random.gauss(0, 5))
        past_pass  = clamp(past_avg / 100 * random.uniform(0.85, 1.10), 0, 1)

    # submission & deadline features
    marks_deadline_set = random.choices([0, 1], weights=[0.25, 0.75])[0]
    submission_rate    = clamp(
        random.uniform(0.5, 1.0) if marks_deadline_set else random.uniform(0.3, 0.85),
        0, 1
    )

    days_to_exam = random.randint(10, 365)

    # ── Build predicted score with realistic influence ──────────────────────
    influence = 0.0
    if past_avg > 0:
        # strong pull from history
        influence += (past_avg - avg_score) * 0.55
    # teacher density
    t_ratio = teacher_count / max(total_students, 1)
    influence += (t_ratio - 0.07) * 150           # ~0 effect at 1:14 ratio
    # submission rate bonus
    influence += (submission_rate - 0.75) * 20
    # deadline bonus
    influence += marks_deadline_set * 1.5
    # proximity to exam (more prep time → slight positive)
    influence += math.log1p(days_to_exam) * 0.3

    target = clamp(avg_score + influence + random.gauss(0, 3.5))

    return {
        "school_type_enc":      st["type_enc"],
        "subject_category_enc": sc["cat_enc"],
        "total_students":       total_students,
        "teacher_count":        teacher_count,
        "past_avg_score":       round(past_avg, 2),
        "past_pass_rate":       round(past_pass, 4),
        "submission_rate":      round(submission_rate, 4),
        "marks_deadline_set":   marks_deadline_set,
        "days_to_exam":         days_to_exam,
        "years_of_history":     years_hist,
        "predicted_avg_score":  round(target, 2),
    }


def generate_dataset(n_rows: int = 4000):
    rows = []
    school_types = list(SCHOOL_TYPES.keys())
    subject_cats = list(SUBJECT_CATEGORIES.keys())

    for i in range(n_rows):
        school_type = random.choice(school_types)
        subject_cat = random.choice(subject_cats)
        row = generate_row(school_type, subject_cat, i)
        rows.append(row)

    return rows


def save_csv(rows, filepath):
    with open(filepath, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=COLUMNS)
        writer.writeheader()
        writer.writerows(rows)
    print(f"[generate_data] Saved {len(rows)} rows -> {filepath}")


if __name__ == "__main__":
    rows = generate_dataset(4000)
    save_csv(rows, OUTPUT_FILE)
    # Quick stats
    scores = [r["predicted_avg_score"] for r in rows]
    print(f"  Target range : {min(scores):.1f} - {max(scores):.1f}")
    print(f"  Target mean  : {sum(scores)/len(scores):.1f}")
