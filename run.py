from flask import Flask
from auth.routes import auth
from admin.routes import admin
from headteacher.routes import headteacher
from examination_officer.routes import examination_officer
from teacher.routes import teacher

app = Flask(__name__)
app.secret_key = "supersecretkey"

app.register_blueprint(auth)
app.register_blueprint(admin)
app.register_blueprint(headteacher)
app.register_blueprint(examination_officer)
app.register_blueprint(teacher)

if __name__ == "__main__":
    app.run(debug=True)