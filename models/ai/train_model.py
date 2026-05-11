# models/ai/train_model.py

import os
import joblib
import numpy as np

from sklearn.pipeline import Pipeline
from sklearn.impute import SimpleImputer
from sklearn.linear_model import LinearRegression
from sklearn.model_selection import train_test_split

# =========================
# SAMPLE DATA (replace later with DB data)
# =========================
X = np.array([
    [40, 40],
    [60, 60],
    [80, 80],
    [50, np.nan],   # example missing value
    [np.nan, 70]
])

y = np.array([40, 60, 80, 52, 70])

# =========================
# PIPELINE (PRODUCTION SAFE)
# =========================
pipeline = Pipeline([
    ("imputer", SimpleImputer(strategy="mean")),  # FIXES NaN
    ("model", LinearRegression())
])

# =========================
# TRAIN
# =========================
pipeline.fit(X, y)

# =========================
# SAVE MODEL
# =========================
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(BASE_DIR, "trained_model.pkl")

joblib.dump(pipeline, MODEL_PATH)

print("✅ Production ML pipeline trained and saved!")