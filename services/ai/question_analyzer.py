# =============================================================
# QUESTION ANALYZER — Teacher compose workflow
# =============================================================

import re
from moderation_engine import ModerationEngine
from similarity_engine import SimilarityEngine
from explanation_engine import AIExplanationEngine

BLOOM_TO_COGNITIVE = {
    "Remember": "Remembering",
    "Understand": "Understanding",
    "Apply": "Applying",
    "Analyze": "Analysing",
    "Evaluate": "Evaluating",
    "Create": "Creating",
}


class QuestionAnalyzer:

    def __init__(self):
        self.engine = ModerationEngine()
        self.similarity = SimilarityEngine()
        self.explainer = AIExplanationEngine()

    def analyze(self, question_text: str, marks: int = 0, existing_questions=None) -> dict:
        existing_questions = existing_questions or []
        question_text = (question_text or "").strip()

        if not question_text:
            return self._empty_result(marks)

        base = self.engine.moderate_question(question_text, marks)
        explanation = base.get("explanation") or self.explainer.generate_full_explanation(base)

        difficulty = explanation.get("difficulty") or self._difficulty_from_complexity(
            base.get("complexity_score", 0)
        )
        cognitive = BLOOM_TO_COGNITIVE.get(
            base.get("bloom_level", "Unknown"),
            base.get("bloom_level", "Unknown"),
        )

        grammar_corrections = self._grammar_check(question_text)
        grammar_corrections.extend(base.get("grammar", {}).get("corrections", []))

        clarity = self._clarity_recommendations(base, question_text)
        improvements = self._suggested_improvements(base, question_text)
        suggested_version = self._suggest_revised_question(
            question_text, grammar_corrections, base
        )

        keywords = base.get("keywords") or []
        topic = ", ".join(keywords[:3]) if keywords else "General"

        ambiguities = base.get("linguistics", {}).get("ambiguous_words", [])
        duplicate_info = self._check_duplicates(question_text, existing_questions)

        recommendations = list(dict.fromkeys(
            (base.get("feedback", []) or []) + improvements + clarity
        ))
        if duplicate_info.get("is_duplicate"):
            recommendations.append(
                "Possible duplicate detected (similarity "
                f"{duplicate_info['similarity_score']}% with an existing question)."
            )

        warnings = []
        if ambiguities:
            warnings.append("Ambiguous wording: " + ", ".join(ambiguities))
        if base.get("quality_score", 100) < 60:
            warnings.append("Low quality score — review before publishing.")
        if grammar_corrections:
            warnings.append(f"{len(grammar_corrections)} grammar issue(s) detected.")
        if duplicate_info.get("is_duplicate"):
            warnings.append("This question may duplicate an existing exam question.")

        quality_score = base.get("quality_score", 0)

        return {
            "status": "success",
            "analysis_type": "teacher_compose",
            "original_question": question_text,
            "suggested_question": suggested_version,
            "difficulty_level": difficulty,
            "cognitive_level": cognitive,
            "bloom_level": base.get("bloom_level", "Unknown"),
            "bloom_confidence": base.get("bloom_confidence", 0),
            "topic": topic,
            "quality_score": quality_score,
            "suggested_marks": base.get("suggested_marks", marks),
            "current_marks": marks,
            "suggested_improvements": improvements,
            "grammar_corrections": grammar_corrections,
            "clarity_recommendations": clarity,
            "ambiguities": ambiguities,
            "duplicate_detection": duplicate_info,
            "recommendations": recommendations,
            "warnings": warnings,
            "keywords": keywords,
            "readability": base.get("readability", {}),
            "complexity_score": base.get("complexity_score", 0),
            "teacher_guidance": explanation.get("teacher_guidance", []),
            "risk_level": explanation.get("risk_level", "Low"),
            "ai_status": base.get("ai_status", "Analysis Completed"),
        }

    def build_moderator_report(self, question_text: str, marks: int = 0, existing_questions=None) -> dict:
        analysis = self.analyze(question_text, marks, existing_questions)
        quality = analysis.get("quality_score", 0)

        if quality >= 75 and not analysis.get("warnings"):
            decision = "approve"
            decision_label = "Recommend Approval"
            decision_reason = "Question meets quality standards with no major issues."
        elif quality >= 50:
            decision = "revise"
            decision_label = "Recommend Revision"
            decision_reason = "Question is acceptable but would benefit from revision."
        else:
            decision = "revise"
            decision_label = "Recommend Revision"
            decision_reason = "Question has significant quality issues and should be revised."

        if analysis.get("duplicate_detection", {}).get("is_duplicate"):
            decision = "revise"
            decision_label = "Recommend Revision"
            decision_reason = "Possible duplicate question detected — verify uniqueness."

        analysis["analysis_type"] = "moderator_review"
        analysis["moderation_recommendation"] = {
            "decision": decision,
            "label": decision_label,
            "reason": decision_reason,
            "note": "AI assists only — moderator makes the final decision.",
        }
        return analysis

    def _grammar_check(self, text: str) -> list:
        corrections = []
        lowered = text.lower()

        patterns = [
            (r"\bi\b", "Use 'I' (capitalized) when referring to yourself."),
            (r"\s{2,}", "Remove extra spaces between words."),
            (r"[.?!]{2,}", "Use a single ending punctuation mark."),
            (r"\bteh\b", "Possible typo: 'teh' → 'the'."),
            (r"\brecieve\b", "Possible typo: 'recieve' → 'receive'."),
            (r"\boccured\b", "Possible typo: 'occured' → 'occurred'."),
        ]

        for pattern, message in patterns:
            if re.search(pattern, text if pattern != r"\bi\b" else text):
                if pattern == r"\bi\b" and re.search(r"(?<![A-Za-z])i(?![A-Za-z'])", text):
                    corrections.append(message)

        if text and text[0].islower():
            corrections.append("Capitalize the first letter of the question.")

        if text and not text.endswith(("?", ".", ":")):
            corrections.append("Consider ending the question with a question mark (?).")

        if "  " in text:
            corrections.append("Remove double spaces in the question text.")

        return corrections

    def _clarity_recommendations(self, base: dict, text: str) -> list:
        tips = []
        word_count = base.get("linguistics", {}).get("word_count", len(text.split()))

        if word_count > 40:
            tips.append("Break the question into shorter, clearer sentences.")
        if word_count < 8:
            tips.append("Add context so students understand what is being assessed.")
        if not base.get("linguistics", {}).get("has_verb", True):
            tips.append("Include a clear action verb (e.g. explain, calculate, compare).")
        if not base.get("linguistics", {}).get("is_proper_question", True):
            tips.append("Phrase as a direct question starting with an action verb or question word.")

        readability = base.get("readability", {})
        if readability.get("flesch_score", 100) < 40:
            tips.append("Simplify vocabulary to improve readability for students.")

        return tips

    def _suggested_improvements(self, base: dict, text: str) -> list:
        improvements = list(base.get("feedback", []))

        bloom = base.get("bloom_level", "")
        if bloom in ["Remember", "Understand"]:
            improvements.append(
                "Consider raising cognitive demand to Applying or Analysing level."
            )

        return improvements

    def _suggest_revised_question(self, text: str, grammar_corrections: list, base: dict) -> str:
        revised = text.strip()

        if revised and revised[0].islower():
            revised = revised[0].upper() + revised[1:]

        if revised and not revised.endswith(("?", ".", ":")):
            if base.get("linguistics", {}).get("is_proper_question", False):
                revised = revised.rstrip(".") + "?"
            else:
                revised = revised.rstrip(".") + "."

        revised = re.sub(r"\s{2,}", " ", revised)
        revised = re.sub(r"\bteh\b", "the", revised, flags=re.IGNORECASE)
        revised = re.sub(r"\brecieve\b", "receive", revised, flags=re.IGNORECASE)

        return revised

    def _check_duplicates(self, question_text: str, existing_questions: list) -> dict:
        best_match = {"similarity_score": 0, "matched_question": None, "is_duplicate": False}

        for existing in existing_questions:
            if not existing or not str(existing).strip():
                continue
            if str(existing).strip().lower() == question_text.strip().lower():
                return {
                    "is_duplicate": True,
                    "similarity_score": 100,
                    "matched_question": str(existing)[:120],
                }

            try:
                result = self.similarity.compare_questions(question_text, str(existing))
                score = result.get("similarity_score", 0)
                if score > best_match["similarity_score"]:
                    best_match = {
                        "is_duplicate": result.get("is_duplicate", False),
                        "similarity_score": score,
                        "matched_question": str(existing)[:120],
                    }
            except Exception:
                continue

        return best_match

    def _difficulty_from_complexity(self, score: int) -> str:
        if score <= 5:
            return "Easy"
        if score <= 12:
            return "Medium"
        return "Hard"

    def _empty_result(self, marks: int) -> dict:
        return {
            "status": "failed",
            "analysis_type": "teacher_compose",
            "original_question": "",
            "suggested_question": "",
            "difficulty_level": "Unknown",
            "cognitive_level": "Unknown",
            "topic": "",
            "quality_score": 0,
            "suggested_marks": marks,
            "current_marks": marks,
            "suggested_improvements": [],
            "grammar_corrections": [],
            "clarity_recommendations": [],
            "ambiguities": [],
            "duplicate_detection": {"is_duplicate": False, "similarity_score": 0},
            "recommendations": ["No question text provided."],
            "warnings": ["Question text is empty."],
            "ai_status": "Empty Input",
        }
