import pandas as pd
import numpy as np
import joblib
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestClassifier
from sklearn.preprocessing import LabelEncoder

# Load dataset
df = pd.read_csv('train.csv')

# Drop 'id' column if it exists
if 'id' in df.columns:
    df.drop(columns=['id'], inplace=True)

# Rename columns to match Flask input format
df.rename(columns={
    "avg_glucose_level": "glucose_level",
    "ever_married": "married",
    "smoking_status": "smoking"
}, inplace=True)

# Encode categorical columns
categorical_columns = ['gender', 'married', 'work_type', 'Residence_type', 'smoking']
label_encoders = {}

for col in categorical_columns:
    le = LabelEncoder()
    df[col] = le.fit_transform(df[col])
    label_encoders[col] = le  # Store label encoder for Flask

# Save label encoders to a file
joblib.dump(label_encoders, "label_encoders.pkl")

# Fill missing values
df.fillna(df.mean(), inplace=True)

# Define features (X) and target (y)
X = df.drop(columns=['stroke'])
y = df['stroke']

# Split dataset
X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

# Train the model
model = RandomForestClassifier(n_estimators=100, random_state=42)
model.fit(X_train, y_train)

# Save trained model
joblib.dump(model, 'stroke_model.pkl')

print("Model training complete. Model saved as 'stroke_model.pkl'")


 