#!/usr/bin/env python
"""
NED-SEMs — Analyze Question Entry Point
=========================================
Called by PHP bridge to analyze a single question.
Reads payload from file or stdin. Outputs JSON to stdout.

Usage:
    python analyze_question.py payload.json
    echo '{"question":"Define osmosis.","marks":2}' | python analyze_question.py
"""

import sys
import os
import json
import warnings

# Silence all output except our JSON
warnings.filterwarnings('ignore')
os.environ['TOKENIZERS_PARALLELISM'] = 'false'
os.environ['TRANSFORMERS_OFFLINE'] = '1'
os.environ['HF_HUB_DISABLE_PROGRESS_BARS'] = '1'
os.environ['HF_HUB_DISABLE_TELEMETRY'] = '1'
os.environ['HF_HUB_OFFLINE'] = '1'
os.environ['PYTHONWARNINGS'] = 'ignore'

# Redirect stderr to suppress any library warnings that escape
import io

# Add our directory to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))


def emit(data: dict):
    """Write JSON to stdout cleanly."""
    sys.stdout.write(json.dumps(data, ensure_ascii=False, default=str))
    sys.stdout.flush()


def read_payload() -> dict:
    """Read payload from file argument or stdin."""
    if len(sys.argv) > 1:
        arg = sys.argv[1]
        if os.path.isfile(arg):
            with open(arg, 'r', encoding='utf-8-sig') as f:
                return json.loads(f.read())
        # Might be raw JSON string
        try:
            return json.loads(arg)
        except Exception:
            pass
    # Read from stdin
    raw = sys.stdin.read().strip()
    if raw:
        return json.loads(raw)
    return {}


def main():
    try:
        payload = read_payload()
    except Exception as e:
        emit({
            "status": "failed",
            "error": f"Invalid payload: {e}",
            "ai_status": "Payload Error",
            "quality_score": 0,
            "recommendations": ["Could not parse input payload."],
            "warnings": [str(e)],
        })
        sys.exit(1)

    try:
        from question_ai_engine import analyze_question

        result = analyze_question(
            question_text=str(payload.get('question', '')),
            marks=int(payload.get('marks', 0) or 0),
            existing_questions=list(payload.get('existing_questions', [])),
        )
        emit(result)

    except RuntimeError as e:
        # Model not trained yet
        emit({
            "status": "failed",
            "error": str(e),
            "ai_status": "Model Not Trained",
            "quality_score": 0,
            "recommendations": ["Run python train_model.py first to set up the AI model."],
            "warnings": [str(e)],
        })
        sys.exit(1)

    except Exception as e:
        emit({
            "status": "failed",
            "error": str(e),
            "ai_status": "Analysis Failed",
            "quality_score": 0,
            "recommendations": ["AI analysis encountered an error."],
            "warnings": [str(e)],
        })
        sys.exit(1)


if __name__ == '__main__':
    main()
