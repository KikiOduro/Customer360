# Customer360 Deployment Walkthrough

This walkthrough documents the deployment change that moves the Python backend onto the Ubuntu server, reroutes PHP to that server-local backend, and verifies that the frontend and backend are connected.

## 1. Final Architecture

The deployed system now runs like this:

```text
Browser
  -> Apache + PHP frontend on http://64.23.248.187:8080/customer360/frontend/
  -> FastAPI backend on http://127.0.0.1:8000/api on the same Ubuntu server
  -> MySQL database on 64.23.248.187:3306
```

That means the browser only talks to Apache publicly. PHP then forwards API requests to FastAPI over localhost on the same server. This removes the old dependency on your Mac public IP and avoids hotspot/NAT issues.

## 2. Code Changes Made

### PHP backend routing

File:

```text
customer360/frontend/api/config.php
```

What changed:

- PHP now prefers `BACKEND_API_URL` when Apache exports it.
- If `BACKEND_API_URL` is not set, PHP falls back to `BACKEND_API_SCHEME`, `BACKEND_API_HOST`, and `BACKEND_API_PORT`.
- The server-local fallback is `127.0.0.1:8000`, so the deployed frontend talks to the FastAPI service running on the same machine.

Expected fallback behavior:

```php
$scheme = envValue('BACKEND_API_SCHEME', 'http');
$host = envValue('BACKEND_API_HOST', '127.0.0.1');
$port = envValue('BACKEND_API_PORT', '8000');
```

### Backend deploy automation

Files:

```text
customer360/deploy/deploy_backend_server.py
customer360/deploy/SERVER_BACKEND_DEPLOYMENT_PROMPT.md
customer360/deploy/WALKTHROUGH.md
```

What the deploy script does:

- creates `backend/.venv` on the server
- installs `backend/requirements.txt`
- validates the required backend `.env` keys
- writes a `systemd` service for Uvicorn
- updates PHP config fallback host/port
- checks PHP syntax
- checks Apache config syntax
- reloads Apache
- restarts the backend service
- verifies `http://127.0.0.1:8000/health`

## 3. Backend Environment File On The Server

Expected file path:

```bash
/var/www/customer360/customer360/backend/.env
```

Minimum required values:

```env
DB_HOST=64.23.248.187
DB_PORT=3306
DB_USER=kiki
DB_PASSWORD=YOUR_REAL_DB_PASSWORD
DB_NAME=customer360

SECRET_KEY=YOUR_REAL_SECRET_KEY
ENVIRONMENT=production
DEBUG=false
ALLOWED_ORIGINS=http://64.23.248.187:8080
ALLOW_CREDENTIALS=true

API_HOST=127.0.0.1
API_PORT=8000
```

Keep real secrets only in the server `.env`. Do not commit production credentials into git.

## 4. How The Server Deployment Was Performed

### SSH into the server

```bash
ssh root@64.23.248.187
```

### Pull the latest code

If the live tree is clean and safe to pull directly:

```bash
cd /var/www/customer360/customer360 && \
git pull origin main
```

If the live tree has local edits you do not want to risk overwriting, pull into a clean release clone and copy only the deployment files you need:

```bash
rm -rf /tmp/customer360-release && \
git clone https://github.com/KikiOduro/Customer360.git /tmp/customer360-release && \
cd /tmp/customer360-release && \
git pull origin main
```

### Run the deployment script

```bash
cd /var/www/customer360/customer360 && \
sudo python3 deploy/deploy_backend_server.py \
  --project-root /var/www/customer360/customer360 \
  --backend-host 127.0.0.1 \
  --backend-port 8000 \
  --service-name customer360-backend \
  --service-user root
```

## 5. What The Systemd Service Runs

The deployment script installs this service:

```bash
/etc/systemd/system/customer360-backend.service
```

The service launches FastAPI from the server venv:

```bash
/var/www/customer360/customer360/backend/.venv/bin/python -m uvicorn app.main:app --host 127.0.0.1 --port 8000
```

That keeps FastAPI private to localhost and lets Apache/PHP be the only public entrypoint.

## 6. How To Start, Stop, Restart, And Monitor The Backend

Start:

```bash
sudo systemctl start customer360-backend
```

Stop:

```bash
sudo systemctl stop customer360-backend
```

Restart:

```bash
sudo systemctl restart customer360-backend
```

Status:

```bash
sudo systemctl --no-pager --full status customer360-backend
```

Live backend logs:

```bash
journalctl -u customer360-backend -f
```

Recent backend logs:

```bash
journalctl -u customer360-backend -n 50 --no-pager
```

Live Apache/PHP logs:

```bash
tail -f /var/log/apache2/error.log
```

Pretty Apache logs:

```bash
tail -f /var/log/apache2/error.log | ccze -A
```

or:

```bash
lnav /var/log/apache2/error.log
```

## 7. Verification Tests That Were Run

### Check the backend service

```bash
systemctl is-active customer360-backend
```

Expected:

```text
active
```

### Check that FastAPI owns port 8000 on localhost

```bash
ss -ltnp '( sport = :8000 )'
```

Expected:

```text
LISTEN ... 127.0.0.1:8000 ... users:(("python",pid=...,fd=...))
```

### Check FastAPI health directly on the server

```bash
curl -i --max-time 10 http://127.0.0.1:8000/health
```

Observed:

```text
HTTP/1.1 200 OK
{"status":"healthy","service":"Customer360 API","version":"1.0.0"}
```

### Check that PHP config points to the local backend

```bash
grep -n "BACKEND_API_URL\|BACKEND_API_HOST\|BACKEND_API_PORT\|customer360-w3vy" /var/www/customer360/customer360/frontend/api/config.php
```

Observed fallback:

```php
$host = envValue('BACKEND_API_HOST', '127.0.0.1');
$port = envValue('BACKEND_API_PORT', '8000');
```

### Check that the public frontend is still served by Apache

```bash
curl -I --max-time 20 http://64.23.248.187:8080/customer360/frontend/signin.php
```

Observed:

```text
HTTP/1.1 200 OK
Server: Apache/2.4.58 (Ubuntu)
Content-Type: text/html; charset=UTF-8
```

### Check that PHP auth requests reach FastAPI

Send a deliberate invalid login through the public PHP endpoint:

```bash
curl -i --max-time 20 \
  'http://64.23.248.187:8080/customer360/frontend/api/auth.php?action=login' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  --data '{"email":"invalid-login-probe@example.com","password":"wrong-password"}'
```

Observed PHP response:

```json
{"success":false,"error":"Incorrect email or password"}
```

Observed Apache log:

```text
Login result: {"success":false,"data":{"detail":"Incorrect email or password"},"raw_body":null,"http_code":401}
```

Observed FastAPI service log:

```text
127.0.0.1:49896 - "POST /api/auth/login/json HTTP/1.1" 401 Unauthorized
```

That confirms the public PHP endpoint is forwarding to the server-local FastAPI backend.

## 8. If Port 8000 Is Already In Use

During deployment, an old manually started Uvicorn process was still bound to port 8000. The fix was:

```bash
ss -ltnp '( sport = :8000 )'
kill <old_uvicorn_pid>
sudo systemctl restart customer360-backend
```

After that, the systemd-managed service owned `127.0.0.1:8000`.

## 9. How To Run The Backend Locally For Development

From your Mac:

```bash
cd /Users/akuaoduro/Desktop/Capstone/customer360/backend && \
source .venv/bin/activate && \
source .env && \
uvicorn app.main:app --host "${API_HOST:-0.0.0.0}" --port "${API_PORT:-8000}"
```

Health check:

```bash
curl http://127.0.0.1:8000/health
```

For local development, if PHP should talk to your local backend instead of the server backend, set Apache/PHP env variables or edit the local `frontend/api/config.php` fallback. For production on the Ubuntu server, keep the fallback on `127.0.0.1:8000`.

## 10. Practical Notes

- `DB_HOST` is the MySQL host used by FastAPI.
- `BACKEND_API_URL`, `BACKEND_API_HOST`, and `BACKEND_API_PORT` control where PHP sends API requests.
- `ALLOWED_ORIGINS` is CORS for browser-to-FastAPI rules. It is not a MySQL permission setting.
- Running FastAPI on the Ubuntu server is more reliable than pointing production PHP at your Mac, because your Mac IP can change and hotspot networks often block inbound traffic.
- The `browser-use` tool was not available in this environment, so frontend connectivity was validated with public `curl` requests plus Apache and systemd logs.

## 11. Analytics, Charts, and Export Flow

After a job completes, the primary results page is:

```text
http://64.23.248.187:8080/customer360/frontend/analytics.php?job_id=<job_id>
```

That page now combines these backend-generated pieces:

- `story_summary` from `backend/app/analytics/pipeline.py` for the plain-language business narrative.
- `segments`, `segment_summary`, `meta`, and SHAP ranked features for KPI cards, segment cards, and the “What Drives Your Segments” panel.
- `customer_table` for the searchable and paginated customer explorer.
- `charts` plus `frontend/api/process.php?action=chart&job_id=<job_id>&chart=<chart_key>` for the tabbed chart gallery.

CSV export now downloads the full backend-generated customer file instead of scraping the visible HTML table:

```text
frontend/api/process.php?action=download_csv&job_id=<job_id>
```

That proxies:

```text
/api/jobs/download/<job_id>/customers
```

The generated customer CSV now includes enriched fields such as `segment_description`, `recommended_action`, `risk_level`, `rfm_score`, `estimated_lifetime_value`, `first_purchase_date`, `last_purchase_date`, and `customer_tenure_days`.

If you need to verify this on the server after a deployment:

```bash
curl -I --max-time 20 'http://64.23.248.187:8080/customer360/frontend/api/process.php?action=download_csv&job_id=<job_id>'
curl -I --max-time 20 'http://64.23.248.187:8080/customer360/frontend/api/process.php?action=chart&job_id=<job_id>&chart=pareto'
```
