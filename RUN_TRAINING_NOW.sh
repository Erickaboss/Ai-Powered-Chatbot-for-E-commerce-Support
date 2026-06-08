#!/bin/bash

# ============================================================
# COMPREHENSIVE CHATBOT TRAINING SCRIPT
# Enhanced with Image Recognition, Document Analysis,
# Budget-Based Search, Multilingual Support & Professional Features
# ============================================================

echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║  🚀 COMPREHENSIVE CHATBOT TRAINING - ENHANCED VERSION      ║"
echo "║  Version 3.0.0 - Production Ready                         ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Check Python
echo "📋 Checking Python installation..."
if ! command -v python &> /dev/null; then
    echo "❌ Python not found. Please install Python 3.8+"
    exit 1
fi

PYTHON_VERSION=$(python --version 2>&1 | awk '{print $2}')
echo "✅ Python $PYTHON_VERSION found"
echo ""

# Check required packages
echo "📦 Checking required packages..."
PACKAGES=("sklearn" "pandas" "numpy" "matplotlib" "seaborn")
MISSING_PACKAGES=()

for package in "${PACKAGES[@]}"; do
    if ! python -c "import $package" 2>/dev/null; then
        MISSING_PACKAGES+=("$package")
    fi
done

if [ ${#MISSING_PACKAGES[@]} -gt 0 ]; then
    echo "⚠️  Missing packages: ${MISSING_PACKAGES[@]}"
    echo ""
    echo "Installing missing packages..."
    pip install scikit-learn pandas numpy matplotlib seaborn
    echo "✅ Packages installed"
else
    echo "✅ All required packages found"
fi
echo ""

# Navigate to chatbot-ml directory
echo "📂 Navigating to chatbot-ml directory..."
cd chatbot-ml || exit 1
echo "✅ Current directory: $(pwd)"
echo ""

# Check if training script exists
if [ ! -f "train_all_enhanced.py" ]; then
    echo "❌ train_all_enhanced.py not found!"
    echo "Please ensure you're in the correct directory."
    exit 1
fi
echo "✅ Training script found"
echo ""

# Create output directories
echo "📁 Creating output directories..."
mkdir -p models reports plots
echo "✅ Directories created"
echo ""

# Run training
echo "╔════════════════════════════════════════════════════════════╗"
echo "║  🤖 STARTING MODEL TRAINING...                            ║"
echo "║  This will take approximately 90 seconds                  ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

START_TIME=$(date +%s)

python train_all_enhanced.py

END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║  ✅ TRAINING COMPLETE!                                    ║"
echo "║  Duration: ${DURATION} seconds                            ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Verify output files
echo "📊 Verifying output files..."
echo ""

if [ -f "models/mlp_neural_network.pkl" ]; then
    echo "✅ MLP Neural Network model saved"
    SIZE=$(du -h models/mlp_neural_network.pkl | cut -f1)
    echo "   Size: $SIZE"
else
    echo "❌ MLP Neural Network model not found"
fi

if [ -f "models/model_results.json" ]; then
    echo "✅ Model results JSON saved"
    SIZE=$(du -h models/model_results.json | cut -f1)
    echo "   Size: $SIZE"
    
    # Extract key metrics
    BEST_MODEL=$(python -c "import json; data=json.load(open('models/model_results.json')); print(data['best_model'])" 2>/dev/null)
    ACCURACY=$(python -c "import json; data=json.load(open('models/model_results.json')); print(f\"{data['summary']['accuracy']*100:.2f}%\")" 2>/dev/null)
    NUM_INTENTS=$(python -c "import json; data=json.load(open('models/model_results.json')); print(len(data['intents']))" 2>/dev/null)
    
    echo "   Best Model: $BEST_MODEL"
    echo "   Accuracy: $ACCURACY"
    echo "   Intent Classes: $NUM_INTENTS"
else
    echo "❌ Model results JSON not found"
fi

if [ -f "reports/comprehensive_training_report.txt" ]; then
    echo "✅ Training report saved"
    SIZE=$(du -h reports/comprehensive_training_report.txt | cut -f1)
    echo "   Size: $SIZE"
else
    echo "❌ Training report not found"
fi

PLOT_COUNT=$(ls -1 plots/*.png 2>/dev/null | wc -l)
if [ $PLOT_COUNT -gt 0 ]; then
    echo "✅ Performance plots generated ($PLOT_COUNT files)"
else
    echo "❌ No plots generated"
fi

echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║  📋 NEXT STEPS                                            ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
echo "1. View training report:"
echo "   cat reports/comprehensive_training_report.txt"
echo ""
echo "2. Check model metrics:"
echo "   cat models/model_results.json | python -m json.tool"
echo ""
echo "3. View performance plots:"
echo "   • plots/all_metrics_comparison.png"
echo "   • plots/model_comparison_grouped.png"
echo "   • plots/cross_validation.png"
echo "   • plots/dataset_distribution.png"
echo ""
echo "4. Test the chatbot:"
echo "   Open http://localhost/index.php"
echo ""
echo "5. View admin dashboard:"
echo "   Open http://localhost/admin/ml_performance.php"
echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║  🎉 TRAINING COMPLETE - READY FOR DEPLOYMENT!            ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
