#!/usr/bin/env python
# Fresh moderator review report — independent from teacher compose analysis.
# Usage: python moderator_report.py payload.json

import sys
import json
import os
import warnings

warnings.filterwarnings('ignore')
os.environ.setdefault('TOKENIZERS_PARALLELISM', 'false')
os.environ.setdefault('HF_HUB_DISABLE_PROGRESS_BARS', '1')
os.environ.setdefault('PYTHONWARNINGS', 'ignore')

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))


def read_payload():
    if len(sys.argv) > 1:
        arg = sys.argv[1]
        if os.path.isfile(arg):
            with open(arg, 'r', encoding='utf-8-sig') as handle:
                return handle.read()
        return arg

    return sys.stdin.read()


def emit_json(data):
    sys.stdout.write(json.dumps(data, ensure_ascii=False))
    sys.stdout.flush()


def main():
    try:
        raw = read_payload()
        payload = json.loads(raw) if raw.strip() else {}

        from question_analyzer import QuestionAnalyzer

        analyzer = QuestionAnalyzer()
        result = analyzer.build_moderator_report(
            question_text=payload.get('question', ''),
            marks=int(payload.get('marks', 0) or 0),
            existing_questions=payload.get('existing_questions', []),
        )

        emit_json(result)

    except Exception as e:
        emit_json({
            'status': 'failed',
            'analysis_type': 'moderator_review',
            'error': str(e),
            'quality_score': 0,
            'warnings': [str(e)],
            'moderation_recommendation': {
                'decision': 'revise',
                'label': 'Analysis Unavailable',
                'reason': str(e),
                'note': 'Moderator must review manually.',
            },
        })
        sys.exit(1)


if __name__ == '__main__':
    main()
