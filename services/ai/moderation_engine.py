# =============================================================
# AI MODERATION ENGINE — Full Power Version (FIXED)
# =============================================================

import re
import spacy
import textstat
from keybert import KeyBERT

from bloom_classifier import BloomClassifier
from explanation_engine import AIExplanationEngine


class ModerationEngine:

    def __init__(self):
        self.bloom_classifier = BloomClassifier()
        self.explainer = AIExplanationEngine()

        # spaCy — linguistic analysis
        self.nlp = spacy.load("en_core_web_sm")

        # LanguageTool placeholder (optional integration later)
        self.grammar_tool = None

        # KeyBERT — keyword extraction
        self.kw_model = KeyBERT()

    # ==========================================================
    # MAIN ENTRY POINT
    # ==========================================================
    def moderate_question(self, question_text: str, marks: int = 0) -> dict:

        question_text = question_text.strip()
        feedback = []

        if not question_text:
            return self._empty_result(marks)

        # ----------------------------------------------------------
        # 1. BLOOM CLASSIFICATION
        # ----------------------------------------------------------
        bloom = self.bloom_classifier.classify(question_text)
        bloom_level = bloom.get("level", "Unknown")
        bloom_confidence = bloom.get("confidence", 70)

        # ----------------------------------------------------------
        # 2. BASIC STATS
        # ----------------------------------------------------------
        words = question_text.split()
        word_count = len(words)

        # ----------------------------------------------------------
        # 3. SPACY ANALYSIS
        # ----------------------------------------------------------
        doc = self.nlp(question_text)
        sentence_count = max(1, len(list(doc.sents)))
        avg_words_per_sent = round(word_count / sentence_count, 1)

        entities = [ent.text for ent in doc.ents]

        has_verb = any(token.pos_ == "VERB" for token in doc)

        starts_with_action = (
            doc[0].pos_ in ["VERB", "AUX"] if len(doc) > 0 else False
        )
        is_proper_question = question_text.endswith("?") or starts_with_action

        # ----------------------------------------------------------
        # 4. TEXTSTAT
        # ----------------------------------------------------------
        flesch_score = textstat.flesch_reading_ease(question_text)
        grade_level = textstat.flesch_kincaid_grade(question_text)
        fog_index = textstat.gunning_fog(question_text)
        syllable_count = textstat.syllable_count(question_text)

        readability_label = self._readability_label(flesch_score)
        grade_label = self._grade_label(grade_level)

        # ----------------------------------------------------------
        # 5. GRAMMAR SECTION (FIXED INDENTATION HERE)
        # ----------------------------------------------------------
        grammar_errors = 0
        corrections = []

        # ----------------------------------------------------------
        # 6. KEYBERT
        # ----------------------------------------------------------
        try:
            raw_kw = self.kw_model.extract_keywords(
                question_text,
                keyphrase_ngram_range=(1, 2),
                stop_words="english",
                top_n=4
            )
            keywords = [kw for kw, _ in raw_kw]
        except Exception:
            keywords = []

        # ----------------------------------------------------------
        # 7. AMBIGUITY DETECTION
        # ----------------------------------------------------------
        ambiguous_words = [
            "maybe", "sometimes", "often", "possibly",
            "can be", "might", "could be", "generally"
        ]
        found_ambiguous = [
            w for w in ambiguous_words
            if w in question_text.lower()
        ]

        # ----------------------------------------------------------
        # 8. COMPLEXITY SCORE
        # ----------------------------------------------------------
        complexity_score = min(
            25,
            int((word_count * 0.4) + avg_words_per_sent)
        )

        # ----------------------------------------------------------
        # 9. QUALITY SCORE
        # ----------------------------------------------------------
        quality_score = 100

        if word_count < 5:
            quality_score -= 30
            feedback.append("Question is too short and may confuse learners.")

        if word_count > 80:
            quality_score -= 10
            feedback.append("Question is too long. Consider simplifying.")

        if not has_verb:
            quality_score -= 15
            feedback.append("Question lacks an action verb.")

        if not is_proper_question:
            quality_score -= 10
            feedback.append("Not properly phrased as a question.")

        if found_ambiguous:
            quality_score -= 15
            feedback.append(
                "Ambiguous language detected: " + ", ".join(found_ambiguous)
            )

        if grammar_errors > 0:
            deduction = min(grammar_errors * 5, 20)
            quality_score -= deduction
            feedback.append(f"{grammar_errors} grammar/spelling issues found.")
            if corrections:
                feedback.append("Suggestions: " + " | ".join(corrections))

        if flesch_score < 30:
            quality_score -= 10
            feedback.append("Very difficult readability level.")

        # ----------------------------------------------------------
        # 10. MARKS SUGGESTION
        # ----------------------------------------------------------
        mark_map = {
            "Remember": 2,
            "Understand": 4,
            "Apply": 6,
            "Analyze": 8,
            "Evaluate": 10,
            "Create": 12
        }

        suggested_marks = mark_map.get(bloom_level, marks)

        if marks > suggested_marks:
            feedback.append(
                f"Marks too high. Suggested: {suggested_marks}."
            )
        elif marks < suggested_marks:
            feedback.append(
                f"Consider increasing marks to {suggested_marks}."
            )
        else:
            feedback.append("Marks allocation is balanced.")

        # ----------------------------------------------------------
        # 11. COGNITIVE FEEDBACK
        # ----------------------------------------------------------
        if bloom_level in ["Analyze", "Evaluate", "Create"]:
            feedback.append("Good higher-order thinking question.")
        elif bloom_level == "Remember":
            feedback.append("Low-level recall question.")

        # ----------------------------------------------------------
        # 12. NORMALIZE
        # ----------------------------------------------------------
        quality_score = max(0, min(100, quality_score))
        bloom_confidence = max(50, min(99, bloom_confidence))

        # ----------------------------------------------------------
        # 13. RESULT
        # ----------------------------------------------------------
        ai_result = {
            "bloom_level": bloom_level,
            "bloom_confidence": bloom_confidence,
            "bloom_scores": bloom.get("scores", {}),

            "complexity_score": complexity_score,
            "quality_score": quality_score,

            "suggested_marks": suggested_marks,
            "current_marks": marks,

            "readability": {
                "flesch_score": round(flesch_score, 1),
                "grade_level": round(grade_level, 1),
                "fog_index": round(fog_index, 1),
                "syllable_count": syllable_count,
                "label": readability_label,
                "grade_label": grade_label
            },

            "grammar": {
                "errors_found": grammar_errors,
                "corrections": corrections
            },

            "linguistics": {
                "word_count": word_count,
                "sentence_count": sentence_count,
                "avg_words_per_sent": avg_words_per_sent,
                "has_verb": has_verb,
                "is_proper_question": is_proper_question,
                "entities": entities,
                "ambiguous_words": found_ambiguous
            },

            "keywords": keywords,
            "feedback": feedback,
            "ai_status": "AI Moderation Completed"
        }

        # ----------------------------------------------------------
        # 14. EXPLANATION
        # ----------------------------------------------------------
        ai_result["explanation"] = self.explainer.generate_full_explanation(ai_result)

        return ai_result

    # ==========================================================
    # HELPERS
    # ==========================================================
    def _readability_label(self, score: float) -> str:
        if score >= 70:
            return "Easy to read"
        if score >= 50:
            return "Moderate"
        if score >= 30:
            return "Difficult"
        return "Very difficult"

    def _grade_label(self, grade: float) -> str:
        if grade <= 6:
            return "Primary level"
        if grade <= 9:
            return "Junior Secondary"
        if grade <= 12:
            return "Senior Secondary"
        return "University level"

    def _empty_result(self, marks: int) -> dict:
        return {
            "bloom_level": "Unknown",
            "bloom_confidence": 0,
            "complexity_score": 0,
            "quality_score": 0,
            "suggested_marks": marks,
            "current_marks": marks,
            "readability": {},
            "grammar": {"errors_found": 0, "corrections": []},
            "linguistics": {},
            "keywords": [],
            "feedback": ["No question text provided."],
            "ai_status": "Empty Input"
        }