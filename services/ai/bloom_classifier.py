class BloomClassifier:

    BLOOM_LEVELS = {
        "Remember": [
            "define", "list", "state", "name", "identify"
        ],

        "Understand": [
            "explain", "describe", "summarize"
        ],

        "Apply": [
            "solve", "demonstrate", "use", "calculate"
        ],

        "Analyze": [
            "compare", "differentiate", "analyze"
        ],

        "Evaluate": [
            "justify", "evaluate", "critique"
        ],

        "Create": [
            "design", "develop", "construct", "create"
        ]
    }

    def classify(self, question):

        question = question.lower()

        scores = {}

        for level, keywords in self.BLOOM_LEVELS.items():

            score = 0

            for word in keywords:
                if word in question:
                    score += 1

            scores[level] = score

        best_level = max(scores, key=scores.get)

        confidence = scores[best_level] / max(1, sum(scores.values()))

        return {
            "level": best_level,
            "confidence": round(confidence * 100, 2)
        }