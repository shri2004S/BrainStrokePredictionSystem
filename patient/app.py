from flask import Flask, request, jsonify
import pandas as pd
import joblib
import logging

app = Flask(__name__)

# Set up logging
logging.basicConfig(level=logging.DEBUG)
logger = logging.getLogger(__name__)

# Load trained model
try:
    stroke_model = joblib.load("patient\model.joblib")
    logger.info("Model loaded successfully")
except Exception as e:
    logger.error(f"Failed to load model: {e}")
    stroke_model = None

def get_recommendations(risk_level, input_data):
    """Generate detailed recommendations based on risk level and input data"""
    base_recommendations = {
        "Low": "Maintain a healthy lifestyle with regular exercise and balanced diet.",
        "Moderate": "Consider regular checkups, improve diet and exercise, monitor blood pressure and glucose levels.",
        "High": "Consult a doctor immediately for comprehensive medical evaluation and treatment plan."
    }
    
    # Get base recommendation
    recommendation = base_recommendations.get(risk_level, "Consult healthcare provider.")
    
    # Add specific recommendations based on input
    additional_tips = []
    
    if input_data.get("hypertension") == 1:
        additional_tips.append("Monitor blood pressure regularly")
    
    if input_data.get("heart_disease") == 1:
        additional_tips.append("Follow cardiac care guidelines")
    
    if float(input_data.get("avg_glucose_level", 0)) > 140:
        additional_tips.append("Monitor blood glucose levels")
    
    if float(input_data.get("bmi", 0)) > 30:
        additional_tips.append("Consider weight management")
    
    if input_data.get("smoking_status") == "smokes":
        additional_tips.append("Consider smoking cessation programs")
    
    # Combine recommendations
    if additional_tips:
        recommendation += " Additional focus: " + ", ".join(additional_tips) + "."
    
    return recommendation

def predict_stroke(single_input):
    """Predict stroke risk for a single input"""
    try:
        if stroke_model is None:
            raise ValueError("Model not loaded")
        
        # Create DataFrame from input
        input_df = pd.DataFrame([single_input])
        
        # Get model components
        encoded_cols = stroke_model["encoded_cols"]
        numeric_cols = stroke_model["numeric_cols"] 
        preprocessor = stroke_model["preprocessor"]
        model = stroke_model['model']
        
        # Transform input
        input_df[encoded_cols] = preprocessor.transform(input_df)
        X = input_df[numeric_cols + encoded_cols]
        
        # Predict probability
        prediction_prob = model.predict_proba(X)[0][1] * 100
        
        # Determine risk level
        if prediction_prob < 30:
            risk_level = "Low"
        elif 30 <= prediction_prob < 70:
            risk_level = "Moderate"
        else:
            risk_level = "High"
        
        # Generate recommendations
        recommendations = get_recommendations(risk_level, single_input)
        
        logger.info(f"Prediction successful: Risk={risk_level}, Prob={prediction_prob:.2f}%")
        
        return {
            "probability": round(prediction_prob, 2),
            "risk_level": risk_level,
            "recommendations": recommendations,
            "success": True
        }
        
    except Exception as e:
        logger.error(f"Prediction error: {e}")
        return {
            "probability": 0,
            "risk_level": "Error",
            "recommendations": "Unable to generate prediction. Please try again.",
            "success": False,
            "error": str(e)
        }

@app.route("/api/predict", methods=["POST"])
def api_predict():
    """API endpoint for stroke prediction"""
    try:
        # Get JSON data
        data = request.get_json()
        
        if not data:
            return jsonify({
                "error": "No data provided",
                "success": False
            }), 400
        
        # Log received data
        logger.info(f"Received prediction request: {data}")
        
        # Required fields validation
        required_fields = [
            "age", "gender", "hypertension", "heart_disease", 
            "avg_glucose_level", "bmi", "smoking_status", 
            "ever_married", "work_type", "Residence_type"
        ]
        
        missing_fields = [field for field in required_fields if field not in data]
        if missing_fields:
            return jsonify({
                "error": f"Missing required fields: {missing_fields}",
                "success": False
            }), 400
        
        # Make prediction
        result = predict_stroke(data)
        
        # Log result
        logger.info(f"Prediction result: {result}")
        
        if result["success"]:
            return jsonify({
                "probability": result["probability"],
                "risk_level": result["risk_level"], 
                "recommendations": result["recommendations"],
                "success": True
            })
        else:
            return jsonify({
                "error": result.get("error", "Prediction failed"),
                "probability": 0,
                "risk_level": "Error",
                "recommendations": "Unable to generate prediction.",
                "success": False
            }), 500
            
    except Exception as e:
        logger.error(f"API error: {e}")
        return jsonify({
            "error": "Internal server error",
            "success": False
        }), 500

@app.route("/api/health", methods=["GET"])
def health_check():
    """Health check endpoint"""
    return jsonify({
        "status": "healthy",
        "model_loaded": stroke_model is not None
    })

if __name__ == "__main__":
    app.run(debug=True, port=5000, host='127.0.0.1')

    