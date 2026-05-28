import json

class ResultsSummaryEngine:

    def _int(self, value):
        try:
            return int(value)
        except (TypeError, ValueError):
            return 0

    def _float(self, value):
        try:
            return float(value)
        except (TypeError, ValueError):
            return 0.0

    def generate_summary(self, metrics, context='overall'):
        total = self._int(metrics.get('total_published') or metrics.get('total_results'))
        average = self._float(metrics.get('average_percentage'))
        pass_count = self._int(metrics.get('pass_count'))
        pass_rate = (pass_count / total * 100) if total else 0
        parts = []

        if total == 0:
            parts.append('No final results have been released yet.')
        else:
            parts.append(
                f'The {context} results release includes {total} final records with an average score of {average:.1f}% and a pass rate of {pass_rate:.0f}%.'
            )

            if average >= 75:
                parts.append('Performance is strong overall, with many students achieving excellent grades.')
            elif average >= 60:
                parts.append('Academic performance is solid, but there is room to strengthen weaker areas.')
            else:
                parts.append('The average score indicates the need for targeted remediation before the next assessment.')

            if pass_rate >= 85:
                parts.append('The pass rate is excellent, showing consistent achievement across the cohort.')
            elif pass_rate >= 60:
                parts.append('The pass rate is moderate; focus on support for students at risk of failing.')
            else:
                parts.append('The pass rate is low; leadership should review curriculum support and intervention plans.')

            if metrics.get('pending_count') or metrics.get('submitted_count'):
                parts.append('Some results are still in the approval pipeline, so continue reviewing before the next publish cycle.')

        return ' '.join(parts)
