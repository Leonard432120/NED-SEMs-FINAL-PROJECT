import sys
import json
from moderation_engine import ModerationEngine

def main():
    try:
        # READ FROM STDIN (NOT argv)
        raw_input = sys.stdin.read()

        payload = json.loads(raw_input)

        engine = ModerationEngine()

        result = engine.moderate_question(
            question_text=payload.get("question", ""),
            marks=int(payload.get("marks", 0))
        )

        print(json.dumps(result, ensure_ascii=False))

    except Exception as e:
        print(json.dumps({
            "status": "failed",
            "error": str(e)
        }))

if __name__ == "__main__":
    main()