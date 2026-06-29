from flask import Flask, request, jsonify
import sys
import os

# Add our directory to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from question_ai_engine import analyze_question

app = Flask(__name__)

@app.route('/analyze', methods=['POST'])
def analyze():
    payload = request.json or {}
    try:
        result = analyze_question(
            question_text=str(payload.get('question', '')),
            marks=int(payload.get('marks', 0) or 0),
            existing_questions=list(payload.get('existing_questions', [])),
        )
        return jsonify(result)
    except Exception as e:
        return jsonify({
            "status": "failed",
            "error": str(e),
            "ai_status": "Analysis Failed",
            "quality_score": 0,
            "recommendations": ["AI analysis encountered an error."],
            "warnings": [str(e)],
        }), 500

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5899))
    app.run(host='127.0.0.1', port=port, debug=False)
