import argparse
import json
import sys

from services.ai.results_summary_engine import ResultsSummaryEngine


def main():
    parser = argparse.ArgumentParser(description='AI results summary CLI')
    parser.add_argument('--context', choices=['overall', 'exam', 'school'], default='overall')
    parser.add_argument('--metrics', required=True, help='JSON encoded metrics object')
    args = parser.parse_args()

    try:
        metrics = json.loads(args.metrics)
    except json.JSONDecodeError:
        print(json.dumps({'error': 'Invalid metrics JSON provided.'}))
        sys.exit(1)

    engine = ResultsSummaryEngine()
    summary_text = engine.generate_summary(metrics, context=args.context)

    output = {
        'context': args.context,
        'metrics': metrics,
        'summary': summary_text
    }
    print(json.dumps(output))


if __name__ == '__main__':
    main()
