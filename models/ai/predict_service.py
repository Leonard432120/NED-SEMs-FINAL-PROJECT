import logging
import numpy as np

# =========================
# LOGGING SETUP
# =========================
logging.basicConfig(
    filename="ai_predictions.log",
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s"
)

# =========================
# MAIN PREDICTION FUNCTION
# =========================
def predict_score(model, record):
    """
    Production-safe prediction service
    """

    try:
        # =========================
        # VALIDATION LAYER
        # =========================
        score = record.get("total_score")
        percentage = record.get("percentage")

        # Clean + fallback values
        score = float(score) if score not in [None, "", "None"] else 0.0
        percentage = float(percentage) if percentage not in [None, "", "None"] else 0.0

        features = np.array([[score, percentage]])

        # =========================
        # MODEL CHECK
        # =========================
        if model is None:
            logging.warning("Model not loaded")
            return 0

        # =========================
        # PREDICTION
        # =========================
        prediction = model.predict(features)[0]

        logging.info(f"Prediction success: {prediction}")

        return float(prediction)

    except Exception as e:
        logging.error(f"Prediction failed: {str(e)}")
        return 0