#!/bin/bash
# Quick Start Guide - Copy & Paste Commands

echo "🚀 Customer360 - Quick Start"
echo "================================"

# Step 1: Navigate to backend
echo -e "\n📁 Step 1: Navigate to backend directory..."
cd /Users/akuaoduro/Desktop/Capstone/customer360/backend
echo "✅ Current directory: $(pwd)"

# Step 2: Install dependencies (if not already done)
echo -e "\n📦 Step 2: Installing dependencies..."
pip install -r requirements.txt > /dev/null 2>&1
echo "✅ Dependencies installed"

# Step 3: Verify tests pass
echo -e "\n🧪 Step 3: Running tests (51 tests)..."
pytest tests/ -q --tb=no
if [ $? -eq 0 ]; then
  echo "✅ All tests passed!"
else
  echo "❌ Some tests failed"
  pytest tests/ -v
  exit 1
fi

# Step 4: Test pipeline integration
echo -e "\n🔄 Step 4: Testing pipeline integration..."
cd /Users/akuaoduro/Desktop/Capstone
python test_pipeline_integration.py > /tmp/pipeline_test.log 2>&1
if [ $? -eq 0 ]; then
  echo "✅ Pipeline integration test passed!"
  cat /tmp/pipeline_test.log
else
  echo "❌ Pipeline integration test failed"
  cat /tmp/pipeline_test.log
  exit 1
fi

# Step 5: Start backend
echo -e "\n🚀 Step 5: Starting backend server..."
API_HOST="${API_HOST:-0.0.0.0}"
API_PORT="${API_PORT:-8000}"
echo "Bind address: ${API_HOST}:${API_PORT}"
echo "Server will be available locally at: http://localhost:${API_PORT}"
echo "API Documentation: http://localhost:${API_PORT}/docs"
echo "Health check: http://localhost:${API_PORT}/health"
echo "Public URL example: http://<your-public-ip>:${API_PORT}"
echo ""
echo "Press Ctrl+C to stop the server"
echo ""

cd /Users/akuaoduro/Desktop/Capstone/customer360/backend
uvicorn app.main:app --reload --host "${API_HOST}" --port "${API_PORT}"
