import sqlite3

conn = sqlite3.connect('mydatabase.db')  # ✅ Opens existing DB or creates if not found
cursor = conn.cursor()

# Create the table if it doesn’t exist already
cursor.execute("""
CREATE TABLE IF NOT EXISTS prediction_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    age INTEGER,
    gender TEXT,
    hypertension INTEGER,
    heart_disease INTEGER,
    avg_glucose_level REAL,
    bmi REAL,
    smoking_status TEXT,
    ever_married TEXT,
    work_type TEXT,
    residence_type TEXT,
    risk_level TEXT,
    probability REAL,
    recommendations TEXT
)
""")

conn.commit()
conn.close()
