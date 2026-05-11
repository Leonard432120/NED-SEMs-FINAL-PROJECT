import re
from common.ai_model import model


# =========================================================
# MAIN QUESTION ANALYZER
# =========================================================
def evaluate_question(text, teacher_marks):

    text_lower = text.lower()

    messages = []
    score = 0

    # =====================================================
    # 1. QUESTION TYPE CLASSIFICATION (Existing logic)
    # =====================================================
    define_words = ["define", "what is", "state"]
    list_words = ["list", "name", "mention"]
    explain_words = ["explain", "describe", "discuss", "outline", "analyze"]

    qtype = "unknown"

    if any(w in text_lower for w in define_words):
        qtype = "define"
        score += 3
        messages.append("✔ Definition-type question detected.")

    elif any(w in text_lower for w in list_words):
        qtype = "list"
        score += 3
        messages.append("✔ Listing-type question detected.")

    elif any(w in text_lower for w in explain_words):
        qtype = "explain"
        score += 4
        messages.append("✔ Higher-order (explain/discuss) question detected.")

    # =====================================================
    # 2. BLOOM'S TAXONOMY DETECTION ⭐ NEW
    # =====================================================
    blooms = {
        "remember": ["define", "list", "name", "state"],
        "understand": ["explain", "describe", "summarize"],
        "apply": ["solve", "calculate", "use", "demonstrate"],
        "analyze": ["compare", "differentiate", "analyze"],
        "evaluate": ["justify", "critique", "assess"],
        "create": ["design", "develop", "propose", "formulate"]
    }

    bloom_level = "unknown"
    for level, words in blooms.items():
        if any(w in text_lower for w in words):
            bloom_level = level
            break

    # =====================================================
    # 3. DEPTH & COMPLEXITY CHECK (Existing improved)
    # =====================================================
    word_count = len(text.split())

    if word_count >= 15:
        score += 3
        messages.append("✔ Good depth and clarity.")
    elif word_count >= 8:
        score += 2
        messages.append("✔ Acceptable question depth.")
    else:
        messages.append("⚠ Question is too short or unclear.")

    # =====================================================
    # 4. STRUCTURE CHECK (Existing)
    # =====================================================
    if "?" in text:
        score += 1
    else:
        messages.append("⚠ Missing question mark.")

    # =====================================================
    # 5. MULTI-PART DETECTION (Existing)
    # =====================================================
    numbers = re.findall(r"\d+", text)
    if len(numbers) >= 1:
        score += 2
        messages.append("✔ Multi-step requirement detected.")

    # =====================================================
    # 6. VAGUE WORD DETECTION (Improved)
    # =====================================================
    vague_words = ["thing", "stuff", "something", "etc", "discuss", "comment"]
    clarity_feedback = "Question wording looks clear."

    if any(w in text_lower for w in vague_words):
        score -= 2
        clarity_feedback = "Question may be too vague. Consider being more specific."
        messages.append("⚠ Question contains vague wording.")

    # Normalize score
    score = max(0, min(score, 10))

    # =====================================================
    # 7. DIFFICULTY CLASSIFICATION ⭐ NEW
    # =====================================================
    difficulty_score = 1

    if word_count > 20:
        difficulty_score += 1

    if bloom_level in ["analyze", "evaluate", "create"]:
        difficulty_score += 2
    elif bloom_level in ["apply", "understand"]:
        difficulty_score += 1

    difficulty_map = {
        1: "Easy",
        2: "Medium",
        3: "Hard",
        4: "Very Hard"
    }

    difficulty = difficulty_map.get(difficulty_score, "Medium")

    # =====================================================
    # 8. MARKS RECOMMENDATION ENGINE ⭐ NEW
    # =====================================================
    recommended_marks = {
        "remember": (1, 3),
        "understand": (3, 6),
        "apply": (5, 10),
        "analyze": (8, 15),
        "evaluate": (10, 20),
        "create": (15, 25)
    }

    min_m, max_m = recommended_marks.get(bloom_level, (3, 8))

    if teacher_marks < min_m:
        marks_feedback = "⚠ Marks too LOW for this question level."
    elif teacher_marks > max_m:
        marks_feedback = "⚠ Marks too HIGH for this question level."
    else:
        marks_feedback = "✔ Marks allocation looks appropriate."

    # =====================================================
    # 9. LEGACY MARK PREDICTION (Keep for compatibility)
    # =====================================================
    if qtype == "define":
        base_range = (1, 3)
    elif qtype == "list":
        base_range = (2, 5)
    elif qtype == "explain":
        base_range = (5, 10)
    else:
        base_range = (3, 7)

    predicted_marks = max(
        base_range[0],
        min(round((score / 10) * teacher_marks), base_range[1])
    )

    if predicted_marks <= 2:
        level = "Low-level question"
    elif predicted_marks <= 5:
        level = "Moderate question"
    else:
        level = "High-level question"

    # =====================================================
    # 10. FINAL RESPONSE (Expanded)
    # =====================================================
    return {
        # OLD fields (so your app doesn't break)
        "question_type": qtype,
        "score": score,
        "level": level,
        "predicted_marks": predicted_marks,
        "messages": messages,

        # NEW AI FIELDS ⭐
        "blooms_level": bloom_level,
        "difficulty": difficulty,
        "marks_feedback": marks_feedback,
        "clarity_feedback": clarity_feedback
    }