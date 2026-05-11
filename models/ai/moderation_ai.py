import re
import os
import json
from dotenv import load_dotenv

# ================================
# LOAD OPENAI (REAL AI MODE)
# ================================
try:
    from openai import OpenAI
    load_dotenv()
    client = OpenAI(api_key=os.getenv("OPENAI_API_KEY"))
    OPENAI_AVAILABLE = True
except Exception:
    OPENAI_AVAILABLE = False


# =========================================================
# FALLBACK RULE-BASED QUESTION ANALYSIS (SAFE MODE)
# =========================================================

EASY_WORDS = ["define", "list", "name", "state", "identify"]
MEDIUM_WORDS = ["explain", "describe", "summarise", "outline"]
HARD_WORDS = ["analyse", "evaluate", "compare", "justify", "criticise"]

BLOOM_LEVELS = {
    "remember": EASY_WORDS,
    "understand": ["explain", "describe", "summarise"],
    "apply": ["solve", "use", "calculate"],
    "analyse": ["analyse", "compare"],
    "evaluate": ["evaluate", "justify"],
}


def detect_difficulty(text):
    text = text.lower()
    if any(w in text for w in HARD_WORDS):
        return "hard"
    if any(w in text for w in MEDIUM_WORDS):
        return "medium"
    return "easy"


def detect_bloom(text):
    text = text.lower()
    for level, words in BLOOM_LEVELS.items():
        if any(w in text for w in words):
            return level
    return "remember"


def clarity_score(text):
    words = len(text.split())
    if words < 6:
        return 40
    if words < 12:
        return 70
    return 90


def ambiguity_check(text):
    bad = ["etc", "and so on", "briefly discuss"]
    return "high" if any(w in text.lower() for w in bad) else "low"


def marks_check(marks, difficulty):
    if difficulty == "easy" and marks > 5:
        return "too_high"
    if difficulty == "hard" and marks < 5:
        return "too_low"
    return "good"


def suggestion(clarity):
    if clarity < 60:
        return "Rewrite question more clearly"
    return "Looks good"


# =========================================================
# GPT-POWERED QUESTION ANALYSIS (PRIMARY AI MODE)
# =========================================================

def gpt_evaluate_question(q):
    if not OPENAI_AVAILABLE:
        return None

    prompt = f"""
You are an exam moderation AI.

Analyze this question:

QUESTION:
{q.get('question_text', '')}

MARKS:
{q.get('marks', 0)}

Return ONLY valid JSON:
{{
  "difficulty": "easy|medium|hard",
  "bloom": "remember|understand|apply|analyse|evaluate|create",
  "clarity": 0-100,
  "ambiguity": "low|medium|high",
  "marks_check": "good|too_high|too_low",
  "suggestion": "short improvement advice",
  "findings": ["issue1", "issue2"]
}}
"""

    try:
        response = client.chat.completions.create(
            model="gpt-4o-mini",
            messages=[{"role": "user", "content": prompt}],
            temperature=0.3
        )

        content = response.choices[0].message.content
        return json.loads(content)

    except Exception:
        return None


# =========================================================
# FINAL QUESTION EVALUATION (HYBRID SYSTEM)
# =========================================================

def evaluate_question(q, teacher_marks=None):

    text = q.get("question_text", "")
    marks = teacher_marks if teacher_marks is not None else q.get("marks", 0)

    # -------------------------
    # TRY GPT FIRST
    # -------------------------
    gpt_result = gpt_evaluate_question(q)

    if gpt_result:
        return {
            "question_id": q.get("question_id"),
            "marks": marks,
            "source": "gpt",
            "difficulty": gpt_result.get("difficulty", "unknown"),
            "bloom": gpt_result.get("bloom", "unknown"),
            "clarity": gpt_result.get("clarity", 0),
            "ambiguity": gpt_result.get("ambiguity", "unknown"),
            "marks_check": gpt_result.get("marks_check", "unknown"),
            "suggestion": gpt_result.get("suggestion", ""),
            "findings": gpt_result.get("findings", [])
        }

    # -------------------------
    # FALLBACK RULE ENGINE
    # -------------------------
    difficulty = detect_difficulty(text)
    clarity = clarity_score(text)
    ambiguity = ambiguity_check(text)

    findings = []

    if clarity < 50:
        findings.append("Question is unclear")

    if ambiguity == "high":
        findings.append("Possible vague wording")

    if marks_check(marks, difficulty) != "good":
        findings.append("Marks not well balanced")

    return {
        "question_id": q.get("question_id"),
        "marks": marks,
        "source": "rule",
        "difficulty": difficulty,
        "bloom": detect_bloom(text),
        "clarity": clarity,
        "ambiguity": ambiguity,
        "marks_check": marks_check(marks, difficulty),
        "suggestion": suggestion(clarity),
        "findings": findings
    }


# =========================================================
# EXAM-LEVEL MODERATION ENGINE (UNCHANGED BUT SAFE)
# =========================================================

def evaluate_exam(exam, questions):

    findings = []
    risk_score = 0

    total_questions = len(questions)
    total_marks = 0

    sections = {"A": 0, "B": 0, "C": 0}

    easy = medium = hard = 0

    for q in questions:
        section = (q.get("section_name") or "").upper()
        marks = q.get("marks") or 0
        total_marks += marks

        if "A" in section:
            sections["A"] += 1
        elif "B" in section:
            sections["B"] += 1
        elif "C" in section:
            sections["C"] += 1

        # difficulty by marks
        if marks <= 3:
            easy += 1
        elif marks <= 6:
            medium += 1
        else:
            hard += 1

    # -------------------------
    # STRUCTURE CHECK
    # -------------------------
    if sections["A"] == 0:
        findings.append("Section A missing")
        risk_score += 10

    if sections["B"] == 0:
        findings.append("Section B missing")
        risk_score += 10

    if sections["C"] == 0:
        findings.append("Section C missing")
        risk_score += 10

    # -------------------------
    # BALANCE CHECK
    # -------------------------
    if hard > easy:
        findings.append("Exam too difficult")
        risk_score += 20

    if easy == 0:
        findings.append("No easy questions")
        risk_score += 10

    # -------------------------
    # SIZE CHECK
    # -------------------------
    if total_questions < 5:
        findings.append("Too few questions")
        risk_score += 15

    if total_questions > 40:
        findings.append("Too many questions")
        risk_score += 10

    # -------------------------
    # MARKS CHECK
    # -------------------------
    if total_marks < 50:
        findings.append("Total marks too low")
        risk_score += 10

    if total_marks > 200:
        findings.append("Total marks too high")
        risk_score += 10

    # -------------------------
    # FINAL SCORE
    # -------------------------
    risk_score = min(risk_score, 100)

    if risk_score <= 30:
        level = "SAFE"
    elif risk_score <= 60:
        level = "REVIEW REQUIRED"
    else:
        level = "HIGH RISK"

    return {
        "risk_score": risk_score,
        "level": level,
        "findings": findings,
        "stats": {
            "total_questions": total_questions,
            "total_marks": total_marks,
            "sections": sections,
            "easy": easy,
            "medium": medium,
            "hard": hard
        }
    }