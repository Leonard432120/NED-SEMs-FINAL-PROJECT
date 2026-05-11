from openai import OpenAI
import os
from dotenv import load_dotenv
import json

load_dotenv()

client = OpenAI(api_key=os.getenv("OPENAI_API_KEY"))

# =========================
# FALLBACK LOGIC (offline AI)
# =========================

def detect_difficulty(text):
    text = text.lower()
    if any(w in text for w in ["analyse", "evaluate", "justify", "criticise"]):
        return "hard"
    if any(w in text for w in ["explain", "describe", "outline"]):
        return "medium"
    return "easy"


def detect_bloom(text):
    text = text.lower()
    if any(w in text for w in ["remember", "define", "state"]):
        return "remember"
    if any(w in text for w in ["explain", "describe"]):
        return "understand"
    if any(w in text for w in ["solve", "calculate"]):
        return "apply"
    if any(w in text for w in ["analyse", "compare"]):
        return "analyse"
    if any(w in text for w in ["evaluate", "justify"]):
        return "evaluate"
    return "remember"


def clarity_score(text):
    words = len(text.split())
    if words < 6:
        return 40
    if words < 12:
        return 70
    return 90


def ambiguity(text):
    bad = ["etc", "and so on", "briefly discuss"]
    return "high" if any(b in text.lower() for b in bad) else "low"


def suggestion(clarity):
    return "Rewrite clearly" if clarity < 60 else "Good question"


# =========================
# MAIN FUNCTION (HYBRID AI)
# =========================

def evaluate_question(q):

    text = q.get("question_text", "")
    marks = q.get("marks", 0)

    # -------------------------
    # TRY GPT FIRST
    # -------------------------
    try:
        prompt = f"""
You are an exam moderation AI.

Question:
{text}

Marks: {marks}

Return ONLY JSON:
{{
  "difficulty": "easy|medium|hard",
  "bloom": "remember|understand|apply|analyse|evaluate|create",
  "clarity": 0-100,
  "ambiguity": "low|medium|high",
  "marks_check": "good|too_high|too_low",
  "suggestion": "short feedback",
  "findings": ["issue1", "issue2"]
}}
"""

        response = client.chat.completions.create(
            model="gpt-4o-mini",
            messages=[{"role": "user", "content": prompt}],
            temperature=0.3
        )

        return json.loads(response.choices[0].message.content)

    except:
        # -------------------------
        # FALLBACK AI (offline)
        # -------------------------
        return {
            "difficulty": detect_difficulty(text),
            "bloom": detect_bloom(text),
            "clarity": clarity_score(text),
            "ambiguity": ambiguity(text),
            "marks_check": "unknown",
            "suggestion": suggestion(clarity_score(text)),
            "findings": []
        }