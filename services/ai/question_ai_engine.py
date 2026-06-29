#!/usr/bin/env python
"""
NED-SEMs Question AI Engine (Unified)
=======================================
Single engine that does everything:
  - Bloom taxonomy classification (from trained local model)
  - Quality scoring
  - Grammar checking
  - Readability analysis
  - Duplicate detection
  - Topic extraction
  - Recommendations

Loads the trained model from model_cache/ — NO internet required.
"""

import os
import re
import sys
import json
import warnings
import math

warnings.filterwarnings('ignore')
os.environ['TOKENIZERS_PARALLELISM'] = 'false'
os.environ['TRANSFORMERS_OFFLINE'] = '1'          # never hit network
os.environ['HF_HUB_DISABLE_PROGRESS_BARS'] = '1'
os.environ['HF_HUB_DISABLE_TELEMETRY'] = '1'

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
CACHE_DIR = os.path.join(BASE_DIR, 'model_cache')
MODEL_LOCAL = os.path.join(CACHE_DIR, 'minilm_local')
BLOOM_EMBS_FILE = os.path.join(CACHE_DIR, 'bloom_embs.npy')
BLOOM_LABELS_FILE = os.path.join(CACHE_DIR, 'bloom_labels.json')

# ─────────────────────────────────────────────────────────────────────────────
# Module-level singletons — loaded ONCE, reused across calls
# ─────────────────────────────────────────────────────────────────────────────
_model = None
_bloom_embs = None        # shape (6, embedding_dim)
_bloom_embs_norm = None   # L2-normalised version
_bloom_labels = None
_nlp = None
_kw_model = None

# Subject/topic keyword banks for topic inference
TOPIC_KEYWORDS = {
    "Mathematics": ["equation", "calculate", "value", "formula", "graph", "geometry",
                    "algebra", "fraction", "ratio", "proportion", "probability",
                    "integer", "polynomial", "trigonometry", "matrix"],
    "Physics": ["force", "energy", "velocity", "acceleration", "mass", "newton",
                "gravity", "circuit", "electricity", "wave", "frequency",
                "temperature", "pressure", "ohm", "current"],
    "Chemistry": ["reaction", "atom", "molecule", "element", "compound", "acid",
                  "base", "pH", "oxidation", "reduction", "bond", "ionic",
                  "covalent", "solution", "concentration"],
    "Biology": ["cell", "organism", "photosynthesis", "respiration", "dna",
                "protein", "enzyme", "ecosystem", "species", "evolution",
                "mitosis", "digestion", "osmosis", "chromosome", "genetics"],
    "Geography": ["climate", "rainfall", "population", "map", "latitude",
                  "longitude", "soil", "erosion", "vegetation", "urbanization",
                  "migration", "continent", "river", "landform"],
    "History": ["war", "independence", "colonial", "revolution", "empire",
                "treaty", "century", "government", "leader", "ancient",
                "civilization", "nation", "parliament", "constitution"],
    "English": ["poem", "paragraph", "sentence", "grammar", "verb", "noun",
                "adjective", "metaphor", "narrative", "essay", "punctuation",
                "dialogue", "theme", "character", "plot"],
    "Agriculture": ["crop", "soil", "fertilizer", "irrigation", "harvest",
                    "planting", "pest", "livestock", "farm", "yield"],
}

AMBIGUOUS_WORDS = [
    "maybe", "sometimes", "often", "possibly", "can be", "might",
    "could be", "generally", "usually", "roughly", "approximately"
]

BLOOM_COGNITIVE_MAP = {
    "Remember": "Remembering",
    "Understand": "Understanding",
    "Apply": "Applying",
    "Analyze": "Analysing",
    "Evaluate": "Evaluating",
    "Create": "Creating",
}

BLOOM_MARK_MAP = {
    "Remember": 2,
    "Understand": 4,
    "Apply": 6,
    "Analyze": 8,
    "Evaluate": 10,
    "Create": 12,
}

BLOOM_ACTION_VERBS = {
    "Remember": ["define", "list", "state", "name", "identify", "recall",
                 "write", "label", "match", "select", "outline"],
    "Understand": ["explain", "describe", "summarise", "interpret", "classify",
                   "paraphrase", "outline", "distinguish", "discuss"],
    "Apply": ["solve", "calculate", "demonstrate", "use", "apply", "compute",
              "construct", "determine", "show", "predict", "find"],
    "Analyze": ["compare", "differentiate", "analyze", "analyse", "examine",
                "break down", "investigate", "distinguish", "contrast"],
    "Evaluate": ["evaluate", "justify", "critique", "assess", "judge",
                 "defend", "argue", "rate", "rank", "recommend"],
    "Create": ["design", "develop", "construct", "create", "propose",
               "formulate", "invent", "produce", "compose", "devise"],
}


# ─────────────────────────────────────────────────────────────────────────────
# Model Loading
# ─────────────────────────────────────────────────────────────────────────────

def _load_model():
    """Load SentenceTransformer from local cache (offline)."""
    global _model
    if _model is not None:
        return _model

    import numpy as np
    from sentence_transformers import SentenceTransformer

    if not os.path.exists(MODEL_LOCAL):
        raise RuntimeError(
            f"Trained model not found at {MODEL_LOCAL}. "
            "Run 'python train_model.py' first."
        )
    _model = SentenceTransformer(MODEL_LOCAL)
    return _model


def _load_bloom():
    """Load pre-computed Bloom embeddings."""
    global _bloom_embs, _bloom_embs_norm, _bloom_labels

    if _bloom_embs is not None:
        return _bloom_embs, _bloom_embs_norm, _bloom_labels

    import numpy as np

    if not os.path.exists(BLOOM_EMBS_FILE) or not os.path.exists(BLOOM_LABELS_FILE):
        raise RuntimeError(
            "Bloom embeddings not found. Run 'python train_model.py' first."
        )

    _bloom_embs = np.load(BLOOM_EMBS_FILE)
    with open(BLOOM_LABELS_FILE, 'r') as f:
        _bloom_labels = json.load(f)

    norms = ((_bloom_embs ** 2).sum(axis=1, keepdims=True)) ** 0.5 + 1e-9
    _bloom_embs_norm = _bloom_embs / norms

    return _bloom_embs, _bloom_embs_norm, _bloom_labels


def _load_nlp():
    """Load spaCy model."""
    global _nlp
    if _nlp is not None:
        return _nlp
    try:
        import spacy
        _nlp = spacy.load("en_core_web_sm")
    except Exception:
        _nlp = None
    return _nlp


# ─────────────────────────────────────────────────────────────────────────────
# Core Analysis
# ─────────────────────────────────────────────────────────────────────────────

def classify_bloom(question_text: str) -> dict:
    """Classify into Bloom's taxonomy using trained embeddings."""
    import numpy as np

    model = _load_model()
    _, embs_norm, labels = _load_bloom()

    q_emb = model.encode([question_text.strip()], show_progress_bar=False)[0]
    q_norm = q_emb / (((q_emb ** 2).sum()) ** 0.5 + 1e-9)

    scores = embs_norm @ q_norm  # shape (6,)
    idx = int(scores.argmax())
    best_level = labels[idx]
    best_score = float(scores[idx])

    total = float(scores.sum())
    confidence = round((best_score / max(total, 1e-9)) * 100, 1)
    confidence = max(50.0, min(99.0, confidence))

    # Boost confidence if strong action verb found
    text_lower = question_text.lower()
    for verb in BLOOM_ACTION_VERBS.get(best_level, []):
        if verb in text_lower:
            confidence = min(99.0, confidence + 5)
            break

    return {
        "level": best_level,
        "confidence": confidence,
        "scores": {labels[i]: round(float(scores[i]) * 100, 2) for i in range(len(labels))}
    }


def compute_similarity(q1: str, q2: str) -> float:
    """Cosine similarity [0,1] between two questions."""
    import numpy as np

    model = _load_model()
    embs = model.encode([q1.strip(), q2.strip()], show_progress_bar=False)
    a, b = embs[0], embs[1]
    norm = (((a**2).sum())**0.5 + 1e-9) * (((b**2).sum())**0.5 + 1e-9)
    return float((a @ b) / norm)


def detect_duplicates(question: str, existing: list) -> dict:
    best = {"is_duplicate": False, "similarity_score": 0.0, "matched_question": None}
    if not existing:
        return best

    for ex in existing:
        ex = str(ex).strip()
        if not ex:
            continue
        if ex.lower() == question.strip().lower():
            return {"is_duplicate": True, "similarity_score": 100.0,
                    "matched_question": ex[:120]}
        try:
            sim = compute_similarity(question, ex)
            score = round(sim * 100, 1)
            if score > best["similarity_score"]:
                best = {
                    "is_duplicate": sim > 0.82,
                    "similarity_score": score,
                    "matched_question": ex[:120]
                }
        except Exception:
            pass
    return best


def grammar_check(text: str) -> list:
    """Rule-based grammar checking."""
    issues = []
    if not text:
        return issues

    if text[0].islower():
        issues.append("Start with a capital letter.")
    if not text.rstrip().endswith(("?", ".", ":")):
        issues.append("End the question with ? or .")
    if "  " in text:
        issues.append("Remove extra spaces.")
    if re.search(r"[.?!]{2,}", text):
        issues.append("Use a single punctuation mark at the end.")

    typos = [
        ("teh", "the"), ("recieve", "receive"), ("occured", "occurred"),
        ("accomodate", "accommodate"), ("seperate", "separate"),
        ("definately", "definitely"), ("arguement", "argument"),
    ]
    for wrong, right in typos:
        if re.search(r'\b' + wrong + r'\b', text, re.IGNORECASE):
            issues.append(f"Typo: '{wrong}' → '{right}'.")

    return issues


def extract_topic(text: str, bloom_keywords: list = None) -> str:
    """Infer topic from keyword matching."""
    text_lower = text.lower()
    scores = {}
    for subject, keywords in TOPIC_KEYWORDS.items():
        count = sum(1 for kw in keywords if kw in text_lower)
        if count > 0:
            scores[subject] = count

    if scores:
        return max(scores, key=scores.get)

    # Fallback: use bloom keywords if provided
    if bloom_keywords:
        return ", ".join(bloom_keywords[:2])

    # Fallback: extract nouns
    words = [w for w in text.split() if len(w) > 4 and w.isalpha()]
    return ", ".join(words[:2]) if words else "General"


def compute_readability(text: str) -> dict:
    """Flesch reading ease and grade level (pure math, no external lib needed as fallback)."""
    try:
        import textstat
        flesch = round(textstat.flesch_reading_ease(text), 1)
        grade = round(textstat.flesch_kincaid_grade(text), 1)
        fog = round(textstat.gunning_fog(text), 1)
    except Exception:
        # Pure fallback computation
        sentences = max(1, len(re.split(r'[.?!]', text)))
        words = text.split()
        syllables = sum(_count_syllables(w) for w in words)
        word_count = max(1, len(words))
        asl = word_count / sentences
        asw = syllables / word_count
        flesch = round(206.835 - 1.015 * asl - 84.6 * asw, 1)
        grade = round(0.39 * asl + 11.8 * asw - 15.59, 1)
        fog = round(0.4 * (asl + 100 * (syllables / max(1, word_count))), 1)

    if flesch >= 70:
        label = "Easy to read"
    elif flesch >= 50:
        label = "Moderate"
    elif flesch >= 30:
        label = "Difficult"
    else:
        label = "Very difficult"

    return {
        "flesch_score": flesch,
        "grade_level": grade,
        "fog_index": fog,
        "label": label
    }


def _count_syllables(word: str) -> int:
    word = word.lower()
    count = len(re.findall(r'[aeiouy]+', word))
    if word.endswith('e') and count > 1:
        count -= 1
    return max(1, count)


def compute_quality_score(
    word_count: int,
    has_verb: bool,
    is_proper_question: bool,
    found_ambiguous: list,
    grammar_issues: list,
    flesch_score: float,
    bloom_level: str,
    marks: int,
) -> tuple:
    """Compute quality score and generate feedback list."""
    score = 100
    feedback = []

    if word_count < 5:
        score -= 30
        feedback.append("Question is too short — add more context.")
    elif word_count < 8:
        score -= 10
        feedback.append("Add more context so learners understand what is assessed.")

    if word_count > 80:
        score -= 10
        feedback.append("Question is too long — simplify into shorter sentences.")

    if not has_verb:
        score -= 15
        feedback.append("Include an action verb (e.g. explain, calculate, compare).")

    if not is_proper_question:
        score -= 10
        feedback.append("Phrase as a direct question or command starting with a verb.")

    if found_ambiguous:
        score -= 15
        feedback.append("Ambiguous language: " + ", ".join(found_ambiguous) + ".")

    if grammar_issues:
        deduct = min(len(grammar_issues) * 4, 20)
        score -= deduct
        feedback.extend(grammar_issues[:3])

    if flesch_score < 30:
        score -= 10
        feedback.append("Readability is very low — simplify vocabulary.")

    # Bloom mark suggestion
    suggested = BLOOM_MARK_MAP.get(bloom_level, marks or 2)
    if marks and marks > suggested + 2:
        feedback.append(f"Marks ({marks}) seem high for {bloom_level} level — suggest {suggested}.")
    elif marks and marks < suggested - 2:
        feedback.append(f"Consider increasing marks to {suggested} for {bloom_level} level.")
    else:
        feedback.append("Marks allocation is balanced.")

    if bloom_level in ["Analyze", "Evaluate", "Create"]:
        feedback.append("Good higher-order thinking question.")
    elif bloom_level == "Remember":
        feedback.append("Consider raising to a higher cognitive level (Apply/Analyse).")

    score = max(0, min(100, score))
    return score, feedback


def suggest_revised(text: str, grammar_issues: list) -> str:
    """Auto-correct common issues to suggest a revised version."""
    revised = text.strip()
    if revised and revised[0].islower():
        revised = revised[0].upper() + revised[1:]
    if revised and not revised.rstrip().endswith(("?", ".", ":")):
        if "?" in revised or re.match(r'(what|where|when|who|why|how)\b', revised, re.I):
            revised = revised.rstrip(".") + "?"
        else:
            revised = revised.rstrip() + "."
    revised = re.sub(r'\s{2,}', ' ', revised)
    for wrong, right in [("teh", "the"), ("recieve", "receive"), ("occured", "occurred")]:
        revised = re.sub(r'\b' + wrong + r'\b', right, revised, flags=re.IGNORECASE)
    return revised


# ─────────────────────────────────────────────────────────────────────────────
# Main Unified Entry Point
# ─────────────────────────────────────────────────────────────────────────────

def analyze_question(question_text: str, marks: int = 0, existing_questions=None) -> dict:
    """
    Full question analysis.
    Returns a complete JSON-serialisable dict for PHP to display.
    """
    existing_questions = existing_questions or []
    question_text = (question_text or "").strip()

    if not question_text:
        return {
            "status": "failed",
            "error": "No question text provided.",
            "quality_score": 0,
            "ai_status": "Empty Input",
            "recommendations": ["Question text is empty."],
            "warnings": ["Question text is required."],
        }

    # --- Bloom Classification ---
    try:
        bloom = classify_bloom(question_text)
        bloom_level = bloom["level"]
        bloom_confidence = bloom["confidence"]
        bloom_scores = bloom["scores"]
    except Exception as e:
        bloom_level = "Unknown"
        bloom_confidence = 50.0
        bloom_scores = {}

    # --- Linguistic Analysis ---
    words = question_text.split()
    word_count = len(words)

    # Check for verb using action verb lists (fast, no spaCy needed)
    text_lower = question_text.lower()
    has_verb = any(
        v in text_lower
        for verbs in BLOOM_ACTION_VERBS.values()
        for v in verbs
    )
    # Also check with spaCy if available
    nlp = _load_nlp()
    entities = []
    if nlp:
        try:
            doc = nlp(question_text)
            has_verb = has_verb or any(t.pos_ == "VERB" for t in doc)
            entities = [e.text for e in doc.ents]
        except Exception:
            pass

    starts_with_question = bool(re.match(
        r'^(what|where|when|who|why|how|which|define|explain|describe|list|'
        r'state|name|calculate|solve|compare|evaluate|justify|design|create)\b',
        question_text, re.IGNORECASE
    ))
    is_proper_question = question_text.rstrip().endswith("?") or starts_with_question

    # --- Grammar ---
    grammar_issues = grammar_check(question_text)

    # --- Readability ---
    readability = compute_readability(question_text)
    flesch = readability["flesch_score"]

    # --- Ambiguity ---
    found_ambiguous = [w for w in AMBIGUOUS_WORDS if w in text_lower]

    # --- Quality Score ---
    quality_score, feedback = compute_quality_score(
        word_count, has_verb, is_proper_question,
        found_ambiguous, grammar_issues, flesch,
        bloom_level, marks
    )

    # --- Topic ---
    topic = extract_topic(question_text)

    # --- Suggested Version ---
    suggested_version = suggest_revised(question_text, grammar_issues)

    # --- Duplicate Detection ---
    duplicate_info = detect_duplicates(question_text, existing_questions)

    # --- Warnings ---
    warnings_list = []
    if found_ambiguous:
        warnings_list.append("Ambiguous wording: " + ", ".join(found_ambiguous))
    if quality_score < 60:
        warnings_list.append("Low quality score — review before publishing.")
    if grammar_issues:
        warnings_list.append(f"{len(grammar_issues)} grammar issue(s) detected.")
    if duplicate_info.get("is_duplicate"):
        warnings_list.append(
            f"Possible duplicate ({duplicate_info['similarity_score']}% similar to existing question)."
        )

    # --- Recommendations ---
    recommendations = list(dict.fromkeys(feedback))
    if duplicate_info.get("is_duplicate"):
        recommendations.append(
            f"Possible duplicate — {duplicate_info['similarity_score']}% similar to an existing question."
        )

    # --- Moderation Decision ---
    if quality_score >= 75 and not warnings_list:
        moderation = "approved"
        mod_reason = f"Meets all quality standards (score: {quality_score}%)."
    elif quality_score >= 55:
        moderation = "revise"
        mod_reason = "Acceptable but needs minor revision before publishing."
    else:
        moderation = "revise"
        mod_reason = f"Low quality ({quality_score}%) — significant revision needed."

    if duplicate_info.get("is_duplicate"):
        moderation = "revise"
        mod_reason = "Duplicate detected — verify question uniqueness."

    # --- Suggested marks ---
    suggested_marks = BLOOM_MARK_MAP.get(bloom_level, marks or 2)

    # --- Complexity ---
    complexity_score = min(25, int(word_count * 0.4 + (word_count / max(1, len(re.split(r'[.?!]', question_text))))))

    return {
        "status": "success",
        "ai_status": "Analysis Complete",

        # Core metrics
        "quality_score": quality_score,
        "bloom_level": bloom_level,
        "bloom_confidence": bloom_confidence,
        "bloom_scores": bloom_scores,
        "cognitive_level": BLOOM_COGNITIVE_MAP.get(bloom_level, bloom_level),
        "difficulty_level": _difficulty_label(complexity_score),
        "complexity_score": complexity_score,
        "topic": topic,

        # Marks
        "current_marks": marks,
        "suggested_marks": suggested_marks,

        # Text analysis
        "original_question": question_text,
        "suggested_question": suggested_version,
        "grammar_corrections": grammar_issues,
        "ambiguities": found_ambiguous,

        # Readability
        "readability": readability,

        # Linguistics
        "linguistics": {
            "word_count": word_count,
            "has_verb": has_verb,
            "is_proper_question": is_proper_question,
            "entities": entities,
            "ambiguous_words": found_ambiguous,
        },

        # Feedback & recommendations
        "feedback": feedback,
        "recommendations": recommendations,
        "warnings": warnings_list,

        # Duplicate
        "duplicate_detection": duplicate_info,

        # Moderation suggestion
        "moderation_recommendation": moderation,
        "moderation_reason": mod_reason,

        # Teacher guidance
        "teacher_guidance": _teacher_guidance(quality_score, bloom_level, complexity_score),
        "risk_level": "High" if quality_score < 50 else ("Medium" if quality_score < 75 else "Low"),
    }


def _difficulty_label(complexity: int) -> str:
    if complexity <= 5:
        return "Easy"
    if complexity <= 12:
        return "Medium"
    return "Hard"


def _teacher_guidance(quality: int, bloom: str, complexity: int) -> list:
    tips = []
    if quality < 60:
        tips.append("Improve clarity and structure of the question.")
    if bloom in ["Remember", "Understand"]:
        tips.append("Consider increasing cognitive level (Apply or higher).")
    if complexity < 8:
        tips.append("Question may be too simple for exam standards.")
    if quality >= 75 and complexity >= 10:
        tips.append("Well-balanced question suitable for formal assessment.")
    if not tips:
        tips.append("Question is acceptable with no major issues.")
    return tips
