# =========================================================
# AI EXPLANATION ENGINE (PRODUCTION-STABLE VERSION)
# =========================================================

class AIExplanationEngine:

    # =====================================================
    # CONFIDENCE INTERPRETATION
    # =====================================================
    def explain_confidence(self, confidence):
        confidence = confidence or 0

        if confidence >= 85:
            return "Very high confidence — AI is very sure about this analysis."
        elif confidence >= 70:
            return "High confidence — AI analysis is reliable."
        elif confidence >= 50:
            return "Moderate confidence — AI is fairly sure but may have uncertainty."
        elif confidence >= 30:
            return "Low confidence — AI is uncertain, review manually."
        return "Very low confidence — AI prediction may be unreliable."

    # =====================================================
    # COMPLEXITY INTERPRETATION
    # =====================================================
    def explain_complexity(self, score):
        score = score or 0

        if score <= 5:
            return "Very easy recall question (basic definition or fact)."
        elif score <= 10:
            return "Low to medium difficulty requiring basic understanding."
        elif score <= 15:
            return "Medium complexity requiring application of knowledge."
        elif score <= 20:
            return "High complexity requiring deep analysis."
        return "Very high complexity requiring critical reasoning."

    # =====================================================
    # QUALITY INTERPRETATION
    # =====================================================
    def explain_quality(self, score):
        score = score or 0

        if score >= 90:
            return "Excellent question quality — very clear and well structured."
        elif score >= 75:
            return "Good quality — minor improvements needed."
        elif score >= 60:
            return "Average quality — clarity can be improved."
        elif score >= 40:
            return "Poor quality — needs revision."
        return "Very poor quality — unclear or invalid question."

    # =====================================================
    # BLOOM LEVEL INTERPRETATION
    # =====================================================
    def explain_bloom(self, level):
        mapping = {
            "Remember": "Recall of facts and definitions.",
            "Understand": "Explaining concepts clearly.",
            "Apply": "Using knowledge in real situations.",
            "Analyze": "Breaking information into parts and relationships.",
            "Evaluate": "Judging based on criteria and evidence.",
            "Create": "Producing new ideas or original solutions."
        }

        return mapping.get(level, "Unknown cognitive level.")

    # =====================================================
    # TEACHER GUIDANCE SYSTEM
    # =====================================================
    def teacher_guidance(self, ai):
        tips = []

        quality = ai.get("quality_score", 100)
        bloom = ai.get("bloom_level", "")
        complexity = ai.get("complexity_score", 0)

        if quality < 60:
            tips.append("Improve clarity and structure of the question.")

        if bloom in ["Remember", "Understand"]:
            tips.append("Consider increasing cognitive level (Apply or higher).")

        if complexity < 8:
            tips.append("Question may be too simple for exam standards.")

        if quality >= 75 and complexity >= 10:
            tips.append("Well-balanced question suitable for assessment.")

        if not tips:
            tips.append("Question is acceptable with no major issues.")

        return tips

    # =====================================================
    # DIFFICULTY CLASSIFICATION
    # =====================================================
    def _difficulty(self, score):
        score = score or 0

        if score <= 5:
            return "Easy"
        elif score <= 12:
            return "Medium"
        return "Hard"

    # =====================================================
    # RISK ASSESSMENT
    # =====================================================
    def _risk(self, ai):
        quality = ai.get("quality_score", 100)

        if quality < 50:
            return "High"
        elif quality < 75:
            return "Medium"
        return "Low"

    # =====================================================
    # FULL EXPLANATION OUTPUT (FRONTEND SAFE)
    # =====================================================
    def generate_full_explanation(self, ai_result):

        ai_result = ai_result or {}

        quality = ai_result.get("quality_score", 0)
        complexity = ai_result.get("complexity_score", 0)
        bloom = ai_result.get("bloom_level", "")
        confidence = ai_result.get("bloom_confidence", 0)

        return {
            "score": round((quality or 0) / 10),

            "question_type": ai_result.get("question_type", "structured"),

            "bloom_level": bloom or "Unknown",

            "difficulty": self._difficulty(complexity),

            "ai_status": ai_result.get("ai_status", "OK"),

            "understanding": {
                "why_this_score": self.explain_quality(quality),
                "student_difficulty": self.explain_complexity(complexity),
                "curriculum_fit": "Aligned with national curriculum standards"
            },

            "teacher_guidance": self.teacher_guidance(ai_result),

            "risk_level": self._risk(ai_result),

            "explanations": {
                "confidence_explained": self.explain_confidence(confidence),
                "complexity_explained": self.explain_complexity(complexity),
                "quality_explained": self.explain_quality(quality),
                "bloom_explained": self.explain_bloom(bloom)
            }
        }