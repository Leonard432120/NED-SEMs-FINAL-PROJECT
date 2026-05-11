from flask import Flask
from dotenv import load_dotenv
import os
import joblib

# =========================
# LOAD ENV VARIABLES
# =========================
load_dotenv()

# =========================
# CREATE FLASK APP FIRST
# =========================
app = Flask(__name__)
app.secret_key = "supersecretkey"

# =========================
# BASE PATH
# =========================
BASE_DIR = os.path.dirname(os.path.abspath(__file__))

# =========================
# LOAD AI MODEL SAFELY
# =========================
MODEL_PATH = os.path.join(BASE_DIR, "models", "ai", "trained_model.pkl")

if os.path.exists(MODEL_PATH):
    app.config["AI_MODEL"] = joblib.load(MODEL_PATH)
    print("✅ AI Model loaded successfully")
else:
    app.config["AI_MODEL"] = None
    print("⚠️ AI Model not found. Run training first!")

# =========================
# IMPORT BLUEPRINTS (AFTER APP CREATION)
# =========================
from auth.routes import auth
from admin.routes import admin
from admin.reports import reports
from headteacher.routes import headteacher
from examination_officer.routes import examination_officer
from teacher.routes import teacher

# =========================
# REGISTER BLUEPRINTS
# =========================
app.register_blueprint(auth)
app.register_blueprint(admin)
app.register_blueprint(reports)
app.register_blueprint(headteacher)
app.register_blueprint(examination_officer)
app.register_blueprint(teacher)

# =========================
# MAIN RUNNER
# =========================
if __name__ == "__main__":
    print("🚀 Starting NED-SEMs Server...")
    app.run(debug=True)