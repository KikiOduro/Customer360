#!/bin/bash
# Database Connection Diagnostic Script

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║         MySQL Connection Diagnostics                         ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""

echo "1. Checking if MySQL is running..."
if pgrep -x "mysqld" > /dev/null; then
    echo "   ✅ MySQL daemon is running"
else
    echo "   ❌ MySQL daemon is NOT running"
    exit 1
fi

echo ""
echo "2. Attempting connection with different credentials..."
echo ""

# Try 1: root with no password
echo "   Trying: root (no password)..."
if mysql -u root -h 127.0.0.1 -e "SELECT 1;" 2>/dev/null; then
    echo "   ✅ SUCCESS: root@localhost (no password)"
else
    echo "   ❌ FAILED: root@localhost (no password)"
fi

echo ""

# Try 2: root with 'root' password
echo "   Trying: root (password: root)..."
if mysql -u root -p root -h 127.0.0.1 -e "SELECT 1;" 2>/dev/null; then
    echo "   ✅ SUCCESS: root@localhost (password: root)"
else
    echo "   ❌ FAILED: root@localhost (password: root)"
fi

echo ""

# Try 3: Try to get current user
echo "3. Checking MySQL user info..."
mysql -u root -h 127.0.0.1 -e "SELECT USER();" 2>/dev/null || echo "   ❌ Connection failed"

echo ""
echo "4. Checking if customer360 database exists..."
if mysql -u root -h 127.0.0.1 -e "USE customer360; SELECT 'Database exists';" 2>/dev/null; then
    echo "   ✅ Database 'customer360' exists"
else
    echo "   ❌ Database 'customer360' does NOT exist"
fi

echo ""
echo "5. Environment variables:"
grep -E "DB_" /Users/akuaoduro/Desktop/Capstone/customer360/backend/.env || echo "   (No .env file found)"

echo ""
echo "═══════════════════════════════════════════════════════════════"
