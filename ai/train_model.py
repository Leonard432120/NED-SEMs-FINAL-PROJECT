"""
ai/train_model.py
==================
Builds, trains, and saves the NED-SEMS School Performance Prediction model.

Algorithm: Ensemble of Random Forest + Ridge Regression (blended prediction)
  - Random Forest  captures non-linear patterns (school type, history gaps)
  - Ridge Regression ensures smooth extrapolation for unseen schools

Usage:
    python train_model.py

Outputs:
    ai/model.pkl   — trained ensemble model
    ai/scaler.pkl  — fitted StandardScaler
    ai/training_data.csv  — generated dataset (if not already present)
"""

import os
import sys
import csv
import pickle
import math

import numpy as np
from sklearn.ensemble import RandomForestRegressor, GradientBoostingRegressor
from sklearn.linear_model import Ridge
from sklearn.preprocessing import StandardScaler
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.metrics import mean_absolute_error, r2_score

# ─── PATHS ───────────────────────────────────────────────────────────────────
AI_DIR       = os.path.dirname(__file__)
DATA_PATH    = os.path.join(AI_DIR, "training_data.csv")
MODEL_PATH   = os.path.join(AI_DIR, "model.pkl")
SCALER_PATH  = os.path.join(AI_DIR, "scaler.pkl")

FEATURE_COLS = [
    "school_type_enc",
    "subject_category_enc",
    "total_students",
    "teacher_count",
    "past_avg_score",
    "past_pass_rate",
    "submission_rate",
    "marks_deadline_set",
    "days_to_exam",
    "years_of_history",
]
TARGET_COL = "predicted_avg_score"


# ─── 1. GENERATE DATA IF MISSING ─────────────────────────────────────────────
def ensure_data():
    if not os.path.exists(DATA_PATH):
        print("[train] training_data.csv not found — generating...")
        sys.path.insert(0, AI_DIR)
        from generate_data import generate_dataset, save_csv
        rows = generate_dataset(4000)
        save_csv(rows, DATA_PATH)
    else:
        print(f"[train] Using existing dataset: {DATA_PATH}")


# ─── 2. LOAD DATA ────────────────────────────────────────────────────────────
def load_data():
    X_rows, y_rows = [], []
    with open(DATA_PATH, newline="", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            try:
                features = [float(row[c]) for c in FEATURE_COLS]
                target   = float(row[TARGET_COL])
                X_rows.append(features)
                y_rows.append(target)
            except (ValueError, KeyError):
                continue
    X = np.array(X_rows)
    y = np.array(y_rows)
    print(f"[train] Loaded {len(X)} samples, {X.shape[1]} features")
    return X, y


# ─── 3. FEATURE ENGINEERING ──────────────────────────────────────────────────
def add_engineered_features(X):
    """
    Adds derived columns:
      - teacher_student_ratio
      - history_x_pass  (interaction: years_of_history * past_pass_rate)
      - score_x_submission (past_avg_score * submission_rate)
    """
    teacher_col    = X[:, FEATURE_COLS.index("teacher_count")]
    students_col   = X[:, FEATURE_COLS.index("total_students")]
    history_col    = X[:, FEATURE_COLS.index("years_of_history")]
    pass_col       = X[:, FEATURE_COLS.index("past_pass_rate")]
    avg_col        = X[:, FEATURE_COLS.index("past_avg_score")]
    sub_col        = X[:, FEATURE_COLS.index("submission_rate")]

    ratio   = teacher_col / np.maximum(students_col, 1)
    hist_x  = history_col * pass_col
    score_x = avg_col * sub_col

    return np.column_stack([X, ratio, hist_x, score_x])


# ─── 4. TRAIN ────────────────────────────────────────────────────────────────
def train(X_eng, y):
    X_train, X_test, y_train, y_test = train_test_split(
        X_eng, y, test_size=0.2, random_state=42
    )

    scaler = StandardScaler()
    X_train_s = scaler.fit_transform(X_train)
    X_test_s  = scaler.transform(X_test)

    # ── Models ────────────────────────────────────────────────────────────
    rf = RandomForestRegressor(
        n_estimators=300,
        max_depth=12,
        min_samples_leaf=4,
        max_features="sqrt",
        random_state=42,
        n_jobs=-1,
    )
    gb = GradientBoostingRegressor(
        n_estimators=200,
        learning_rate=0.07,
        max_depth=5,
        subsample=0.85,
        random_state=42,
    )
    ridge = Ridge(alpha=5.0)

    # Train all three
    rf.fit(X_train_s, y_train)
    gb.fit(X_train_s, y_train)
    ridge.fit(X_train_s, y_train)

    # Blend predictions (RF 50% + GB 35% + Ridge 15%)
    def blend_predict(X_s):
        p_rf    = rf.predict(X_s)
        p_gb    = gb.predict(X_s)
        p_ridge = ridge.predict(X_s)
        return 0.50 * p_rf + 0.35 * p_gb + 0.15 * p_ridge

    y_pred = blend_predict(X_test_s)
    y_pred = np.clip(y_pred, 0, 100)

    mae  = mean_absolute_error(y_test, y_pred)
    r2   = r2_score(y_test, y_pred)
    rmse = math.sqrt(np.mean((y_test - y_pred) ** 2))

    print(f"\n[train] -- Model Evaluation -------------------------")
    print(f"  MAE  : {mae:.2f} marks")
    print(f"  RMSE : {rmse:.2f} marks")
    print(f"  R2   : {r2:.4f}")
    print(f"------------------------------------------------------")

    # Feature importance from RF
    feat_names = FEATURE_COLS + ["teacher_ratio", "hist_x_pass", "score_x_sub"]
    importance = list(zip(feat_names, rf.feature_importances_))
    importance.sort(key=lambda x: x[1], reverse=True)
    print("\n[train] Top Feature Importances (Random Forest):")
    for name, imp in importance[:8]:
        bar = "#" * int(imp * 50)
        print(f"  {name:<28} {imp:.4f}  {bar}")

    # Package everything
    model_bundle = {
        "rf":    rf,
        "gb":    gb,
        "ridge": ridge,
        "weights": (0.50, 0.35, 0.15),
        "feature_cols": FEATURE_COLS,
        "mae":   mae,
        "r2":    r2,
        "rmse":  rmse,
    }

    return model_bundle, scaler


# --- 5. SAVE -----------------------------------------------------------------
def save_model(model_bundle, scaler):
    with open(MODEL_PATH, "wb") as f:
        pickle.dump(model_bundle, f)
    with open(SCALER_PATH, "wb") as f:
        pickle.dump(scaler, f)
    print(f"\n[train] Model saved  -> {MODEL_PATH}")
    print(f"[train] Scaler saved -> {SCALER_PATH}")


# --- MAIN --------------------------------------------------------------------
if __name__ == "__main__":
    ensure_data()
    X, y = load_data()
    X_eng = add_engineered_features(X)
    model_bundle, scaler = train(X_eng, y)
    save_model(model_bundle, scaler)
    print("\n[train] Training complete. Run predict.py to make predictions.")
