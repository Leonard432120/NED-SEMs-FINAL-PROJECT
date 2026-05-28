# =========================================================
# AI MODERATION ENGINE (UPGRADED + EXPLANATION READY)
# =========================================================

from services.ai.bloom_classifier import BloomClassifier
from services.ai.explanation_engine import AIExplanationEngine


class ModerationEngine:

    def __init__(self):
        self.bloom_classifier = BloomClassifier()
        self.explainer = AIExplanationEngine()

    # =====================================================
    # MAIN AI MODERATION FUNCTION
    # =====================================================
    def moderate_question(self, question_text, marks=0):

        feedback = []
        question_text = question_text.strip()

        # =====================================================
        # BASIC ANALYSIS
        # =====================================================
        word_count = len(question_text.split())
        sentence_count = max(1, len(question_text.split(".")))
        avg_words = word_count / sentence_count

        # =====================================================
        # BLOOM CLASSIFICATION
        # =====================================================
        bloom = self.bloom_classifier.classify(question_text)

        bloom_level = bloom.get("level", "Unknown")
        confidence = bloom.get("confidence", 70)

        # =====================================================
        # COMPLEXITY SCORE (0 - 25 scale)
        # =====================================================
        complexity_score = min(
            25,
            int((word_count * 0.4) + avg_words)
        )

        # =====================================================
        # QUALITY SCORE (100 base)
        # =====================================================
        quality_score = 100

        if word_count < 5:
            quality_score -= 30
            feedback.append("Question is too short and may confuse learners.")

        if word_count > 80:
            quality_score -= 10
            feedback.append("Question is too long. Consider simplifying.")

        # =====================================================
        # AMBIGUITY DETECTION
        # =====================================================
        ambiguous_words = [
            "maybe", "sometimes", "often", "possibly", "can be"
        ]

        found_ambiguous = [
            w for w in ambiguous_words
            if w in question_text.lower()
        ]

        if found_ambiguous:
            quality_score -= 15
            feedback.append(
                "Possible ambiguity detected: " + ", ".join(found_ambiguous)
            )

        # =====================================================
        # SUGGESTED MARKS LOGIC
        # =====================================================
        suggested_marks = marks

        if bloom_level == "Remember":
            suggested_marks = 2 if word_count <= 10 else 4

        elif bloom_level == "Understand":
            suggested_marks = 5

        elif bloom_level == "Apply":
            suggested_marks = 6

        elif bloom_level == "Analyze":
            suggested_marks = 8

        elif bloom_level == "Evaluate":
            suggested_marks = 10

        elif bloom_level == "Create":
            suggested_marks = 12

        # =====================================================
        # MARKS FEEDBACK
        # =====================================================
        if marks > suggested_marks:
            feedback.append(
                f"Current marks ({marks}) may be too high for this question."
            )
        elif marks < suggested_marks:
            feedback.append(
                f"Consider increasing marks to around {suggested_marks}."
            )
        else:
            feedback.append("Marks allocation appears balanced.")

        # =====================================================
        # COGNITIVE FEEDBACK
        # =====================================================
        if bloom_level in ["Analyze", "Evaluate", "Create"]:
            feedback.append("Question promotes higher-order thinking skills.")
        elif bloom_level == "Remember":
            feedback.append("Question mainly tests memory recall.")

        # =====================================================
        # FINAL NORMALIZATION
        # =====================================================
        quality_score = max(0, min(100, quality_score))
        confidence = max(60, min(100, confidence))

        # =====================================================
        # RAW AI RESULT
        # =====================================================
        ai_result = {

            "bloom_level": bloom_level,
            "bloom_confidence": confidence,

            "complexity_score": complexity_score,
            "quality_score": quality_score,

            "suggested_marks": suggested_marks,
            "current_marks": marks,

            "feedback": feedback,
            "ai_status": "AI Moderation Completed",
            "question_type": "structured"
        }

        # =====================================================
        # EXPLANATION LAYER (NEW POWER FEATURE)
        # =====================================================
        ai_result["explanation"] = self.explainer.generate_full_explanation(ai_result)

        return ai_result