import argparse
import json

from services.ai.moderation_engine import ModerationEngine


def main():
    parser = argparse.ArgumentParser(description="AI moderation CLI for question text")
    parser.add_argument("--question", required=True, help="Question text to analyze")
    parser.add_argument("--marks", type=int, default=0, help="Current marks for the question")
    args = parser.parse_args()

    engine = ModerationEngine()
    result = engine.moderate_question(args.question, marks=args.marks)
    print(json.dumps(result))


if __name__ == '__main__':
    main()
