import numpy as np

def predict_score(model, features):
    """
    Safe prediction function (NO NaN allowed)
    """

    # Convert to safe numeric values
    score = float(features.get("score") or 0)
    percentage = float(features.get("percentage") or 0)

    # FINAL SAFETY CHECK (remove NaN/inf)
    score = 0 if np.isnan(score) else score
    percentage = 0 if np.isnan(percentage) else percentage

    X = [[score, percentage]]

    return float(model.predict(X)[0])