# Customer360 Server Deployment Walkthrough

This walkthrough explains the deployment change that moves the Python backend onto the Ubuntu server itself, how the PHP frontend connects to it, and how to start, verify, and troubleshoot the system.

## What Changed

Before this deployment, the PHP frontend was sometimes calling:

- the old Render backend
- or a Python backend running on your Mac public IP

That design was fragile because laptop IPs change and hotspot/NAT networks often block inbound traffic to your Mac.

The new production design is:

```text
Browser
  -> Apache + PHP on http://64.23.248.187:8080
  -> FastAPI on http://127.0.0.1:8000 on the same Ubuntu server
  -> MySQL on 64.23.248.187:3306
```

So the browser still talks to Apache publicly, but the PHP proxy talks to FastAPI over localhost on the same server.

## Files Added Or Updated

### 1) `customer360/deploy/deploy_backend_server.py`

This is the server deployment script. It:

- creates `/var/www/customer360/customer360/backend/.venv`
- installs `backend/requirements.txt`
- checks that `backend/.env` contains the required DB/JWT values
- creates or refreshes a systemd service for Uvicorn
- updates `frontend/api/config.php` so PHP falls back to `127.0.0.1:8000`
- validates PHP syntax
- validates Apache config
- reloads Apache
- restarts the backend service
- checks `http://127.0.0.1:8000/health`

### 2) `customer360/deploy/SERVER_BACKEND_DEPLOYMENT_PROMPT.md`

This is a detailed operator prompt/runbook for deploying the backend manually or with the script.

### 3) `customer360/frontend/api/config.php`

The fallback backend host was changed to `127.0.0.1`, so if Apache does not provide `BACKEND_API_URL`, PHP still routes to the server-local FastAPI instance.

## Required Backend `.env`

On the server, the backend environment file should be:

```text
/var/www/customer360/customer360/backend/.env
```

At minimum it should contain:

```env
DB_HOST=64.23.248.187
DB_PORT=3306
DB_USER=kiki
DB_PASSWORD=YOUR_REAL_PASSWORD
DB_NAME=customer360

SECRET_KEY=YOUR_REAL_SECRET_KEY
ENVIRONMENT=production
DEBUG=false
ALLOWED_ORIGINS=http://64.23.248.187:8080
ALLOW_CREDENTIALS=true

API_HOST=127.0.0.1
API_PORT=8000

SUPABASE_URL=https://bsacwgdxmnuifvhfasof.supabase.co
SUPABASE_STORAGE_BUCKET=user_uploads
SUPABASE_SERVICE_ROLE_KEY=YOUR_REAL_SUPABASE_SERVICE_ROLE_KEY

GROQ_API_KEY=YOUR_REAL_GROQ_KEY
```

Do not commit real secrets into git. Keep secrets only in the deployed server `.env`.

## One-Command Server Deployment

After pulling the latest code on the Ubuntu server, run:

```bash
cd /var/www/customer360/customer360 && \
sudo python3 deploy/deploy_backend_server.py \
  --project-root /var/www/customer360/customer360 \
  --backend-host 127.0.0.1 \
  --backend-port 8000 \
  --service-name customer360-backend \
  --service-user root
```

## What The Systemd Service Does

The script installs `/etc/systemd/system/customer360-backend.service`.

The service runs FastAPI from the backend virtual environment:

```bash
/var/www/customer360/customer360/backend/.venv/bin/python -m uvicorn app.main:app --host 127.0.0.1 --port 8000
```

That means:

- FastAPI is not publicly exposed directly
- Apache/PHP can reach it locally
- systemd restarts it if it crashes

## How To Start, Stop, Restart, And Monitor The Backend

### Start

```bash
sudo systemctl start customer360-backend
```

### Stop

```bash
sudo systemctl stop customer360-backend
```

### Restart

```bash
sudo systemctl restart customer360-backend
```

### Status

```bash
sudo systemctl --no-pager --full status customer360-backend
```

### Live backend logs

```bash
journalctl -u customer360-backend -f
```

### Live Apache/PHP logs

```bash
tail -f /var/log/apache2/error.log
```

If `ccze` is installed:

```bash
tail -f /var/log/apache2/error.log | ccze -A
```

If `lnav` is installed:

```bash
lnav /var/log/apache2/error.log
```

## How PHP Connects To Python

The browser submits forms and AJAX requests to PHP endpoints like:

```text
/customer360/frontend/api/auth.php
/customer360/frontend/api/upload.php
/customer360/frontend/api/process.php
/customer360/frontend/api/analyze.php
```

Those PHP files use:

```text
/var/www/customer360/customer360/frontend/api/config.php
```

That config builds `BACKEND_API_URL`.

In the new server-local deployment, the fallback target is:

```text
http://127.0.0.1:8000/api
```

So a PHP request to `/jobs/upload` becomes:

```text
http://127.0.0.1:8000/api/jobs/upload
```

## Verification Tests

Run these on the Ubuntu server after deployment.

### 1) Check FastAPI directly

```bash
curl -i http://127.0.0.1:8000/health
```

Expected: HTTP 200 and JSON with `"status":"healthy"`.

### 2) Check that PHP no longer points to Render or a laptop IP

```bash
grep -n "BACKEND_API_HOST\|BACKEND_API_URL\|customer360-w3vy" /var/www/customer360/customer360/frontend/api/config.php
```

Expected: fallback host `127.0.0.1`, no Render fallback URL.

### 3) Check Apache config

```bash
apache2ctl -t
```

Expected: `Syntax OK`

### 4) Check PHP syntax

```bash
php -l /var/www/customer360/customer360/frontend/api/config.php
```

Expected: `No syntax errors detected`

### 5) Hit a lightweight public PHP endpoint

```bash
curl -i "http://64.23.248.187:8080/customer360/frontend/api/auth.php?action=check"
```

Expected: a normal HTTP response from PHP. If no session cookie is supplied, a `401` response is acceptable for this endpoint, because that still proves Apache/PHP is serving.

### 6) Check that login/upload requests are reaching Python

Open one terminal:

```bash
journalctl -u customer360-backend -f
```

Open a second terminal:

```bash
tail -f /var/log/apache2/error.log | ccze -A
```

Then log in or upload from the browser. You should see PHP logs and FastAPI logs changing together.

## Common Failure Modes And What They Mean

### PHP still calls Render

Symptom in Apache logs:

```text
Backend URL: https://customer360-w3vy.onrender.com/api
```

Fix:

- update `/var/www/customer360/customer360/frontend/api/config.php`
- remove any Apache `BACKEND_API_URL` override if it points to Render
- reload Apache

### FastAPI service fails to start

Check:

```bash
journalctl -u customer360-backend -n 200 --no-pager
```

Most common causes:

- missing `.env`
- MySQL auth failure
- missing Python package in `.venv`
- bad file permissions

### MySQL auth failure

Test directly from the server:

```bash
/var/www/customer360/customer360/backend/.venv/bin/python -c "import pymysql; conn=pymysql.connect(host='64.23.248.187', port=3306, user='kiki', password='YOUR_REAL_PASSWORD', database='customer360'); print('ok'); conn.close()"
```

If this fails with `Access denied`, fix MySQL grants/password on the database server side.

### Upload request times out

If `/health` works but uploads time out, inspect:

- `journalctl -u customer360-backend -f`
- `/var/log/apache2/error.log`

Possible causes:

- backend service is not running
- PHP proxy points to the wrong backend URL
- analysis job crashed in the background task
- CSV is too large or malformed

## How To Deploy New Code Later

1. Commit and push changes from your local machine.
2. SSH into the server:

```bash
ssh root@64.23.248.187
```

3. Pull latest code:

```bash
cd /var/www/customer360/customer360 && git pull origin main
```

4. Re-run the deployment script:

```bash
cd /var/www/customer360/customer360 && \
sudo python3 deploy/deploy_backend_server.py \
  --project-root /var/www/customer360/customer360 \
  --backend-host 127.0.0.1 \
  --backend-port 8000 \
  --service-name customer360-backend \
  --service-user root
```

5. Watch logs and smoke-test the site.

## Local Development Reminder

For local Mac development only:

```bash
cd /Users/akuaoduro/Desktop/Capstone/customer360/backend && \
source .venv/bin/activate && \
source .env && \
uvicorn app.main:app --host "${API_HOST:-0.0.0.0}" --port "${API_PORT:-8000}"
```

But for production, the intended setup is server-local FastAPI behind Apache, not a laptop backend.
