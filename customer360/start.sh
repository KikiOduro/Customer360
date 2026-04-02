#!/bin/bash
# Customer360 - Quick Start Script
# This script sets up and runs the Customer360 application

echo "🚀 Customer360 Quick Start"
echo "=========================="

# Check Python version
if ! command -v python3 &> /dev/null; then
    echo "❌ Python 3 is not installed. Please install Python 3.9 or higher."
    exit 1
fi

PYTHON_VERSION=$(python3 -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")')
echo "✅ Python $PYTHON_VERSION detected"

# Navigate to backend
cd "$(dirname "$0")/backend"

# Create virtual environment if it doesn't exist
if [ ! -d "venv" ]; then
    echo "📦 Creating virtual environment..."
    python3 -m venv venv
fi

# Activate virtual environment
source venv/bin/activate

# Install dependencies
echo "📦 Installing dependencies..."
pip install -r requirements.txt --quiet

API_HOST="${API_HOST:-0.0.0.0}"
API_PORT="${API_PORT:-8000}"

# Run the server
echo ""
echo "🌐 Starting Customer360 Backend..."
echo "   Bind: ${API_HOST}:${API_PORT}"
echo "   Local API: http://localhost:${API_PORT}"
echo "   Docs: http://localhost:${API_PORT}/docs"
echo "   External URL example: http://<your-public-ip>:${API_PORT}"
echo ""
echo "📱 To view the frontend, open a new terminal and run:"
echo "   cd frontend && python3 -m http.server 3000"
echo "   Then visit: http://localhost:3000"
echo ""
echo "Press Ctrl+C to stop the server"
echo ""

uvicorn app.main:app --reload --host "${API_HOST}" --port "${API_PORT}"
