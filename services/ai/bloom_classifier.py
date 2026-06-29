# =============================================================
# BLOOM CLASSIFIER — Embedding-based (sentence-transformers)
# =============================================================

from sentence_transformers import SentenceTransformer
import numpy as np


class BloomClassifier:

    # Rich example sentences per level — more examples = better accuracy
    BLOOM_EXAMPLES = {
        "Remember": (
            "Define the term. List the names. State the facts. "
            "Identify the parts. Name the components. Recall the date. "
            "What is the definition of. Write down the formula."
        ),
        "Understand": (
            "Explain how it works. Describe the process. Summarize the main idea. "
            "Interpret the meaning. Classify the types. Give an example of. "
            "What does this mean. Outline the steps."
        ),
        "Apply": (
            "Solve the problem. Calculate the value. Demonstrate how to use it. "
            "Use the formula to find. Show how this works. Apply the concept. "
            "Compute the result. Construct a diagram."
        ),
        "Analyze": (
            "Compare the two approaches. Differentiate between. Analyze the relationship. "
            "Break down the components. Examine the causes. What are the differences. "
            "Investigate the factors. Distinguish between."
        ),
        "Evaluate": (
            "Justify your answer. Evaluate the effectiveness. Critique the approach. "
            "Assess the impact. Judge the importance. Defend your position. "
            "What is the best solution and why. Give reasons for your decision."
        ),
        "Create": (
            "Design a solution. Develop a plan. Construct a new model. "
            "Create an original. Propose a strategy. Formulate a hypothesis. "
            "Invent a method. Produce a report."
        )
    }

    def __init__(self):
        self.model = SentenceTransformer('all-MiniLM-L6-v2')
        # Pre-compute level embeddings once at init
        self.level_embeddings = {
            level: self.model.encode(text)
            for level, text in self.BLOOM_EXAMPLES.items()
        }

    def classify(self, question: str) -> dict:
        question = question.strip()
        if not question:
            return {"level": "Unknown", "confidence": 0, "scores": {}}

        q_emb = self.model.encode(question)
        scores = {}

        for level, emb in self.level_embeddings.items():
            sim = np.dot(q_emb, emb) / (
                np.linalg.norm(q_emb) * np.linalg.norm(emb) + 1e-9
            )
            scores[level] = float(sim)

        best_level = max(scores, key=scores.get)
        best_score = scores[best_level]

        total = sum(scores.values())
        confidence = round((best_score / max(total, 1e-9)) * 100, 2)
        confidence = max(50, min(99, confidence))

        return {
            "level":      best_level,
            "confidence": confidence,
            "scores":     {k: round(v * 100, 2) for k, v in scores.items()}
        }