# Customer360 Server Backend Deployment Prompt

Use this prompt when asking an operator, teammate, or another coding agent to deploy the Python backend directly on the Ubuntu server and switch the PHP frontend to talk to that local backend.

## Prompt

You are deploying the Customer360 FastAPI backend on the same Ubuntu server that serves the PHP frontend. The goal is to stop routing PHP requests to Render or to a Mac laptop and instead run the Python API locally on the server at `127.0.0.1:8000`.

### Server context

- Public web server: `http://64.23.248.187:8080/customer360/frontend/`
- Ubuntu project root expected by Apache: `/var/www/customer360/customer360`
- Backend directory: `/var/www/customer360/customer360/backend`
- Frontend PHP proxy config: `/var/www/customer360/customer360/frontend/api/config.php`
- MySQL host: `64.23.248.187:3306`
- Preferred FastAPI bind: `127.0.0.1:8000`
- Preferred systemd service name: `customer360-backend`

### Deployment objectives

1. Verify `/var/www/customer360/customer360/backend/.env` exists and has production values for:
   - `DB_HOST`
   - `DB_PORT`
   - `DB_USER`
   - `DB_PASSWORD`
   - `DB_NAME`
   - `SECRET_KEY`
   - `ENVIRONMENT=production`
   - `DEBUG=false`
   - `ALLOWED_ORIGINS=http://64.23.248.187:8080`
2. Create or refresh the backend virtual environment in `/var/www/customer360/customer360/backend/.venv`.
3. Install `backend/requirements.txt` into that virtual environment.
4. Install a systemd unit that runs:
   - working directory: `/var/www/customer360/customer360/backend`
   - environment file: `/var/www/customer360/customer360/backend/.env`
   - command: `.venv/bin/python -m uvicorn app.main:app --host 127.0.0.1 --port 8000`
5. Update `/var/www/customer360/customer360/frontend/api/config.php` so the fallback backend host is `127.0.0.1` and fallback port is `8000`.
6. Validate PHP syntax with `php -l`.
7. Validate Apache config with `apache2ctl -t`.
8. Reload Apache.
9. Restart the FastAPI systemd service.
10. Verify from the server:
    - `curl http://127.0.0.1:8000/health`
    - one frontend request through `http://64.23.248.187:8080/customer360/frontend/api/auth.php?action=check` or another lightweight endpoint.

### Preferred implementation

Use the repo script:

```bash
cd /var/www/customer360/customer360 && \
sudo python3 deploy/deploy_backend_server.py \
  --project-root /var/www/customer360/customer360 \
  --backend-host 127.0.0.1 \
  --backend-port 8000 \
  --service-name customer360-backend \
  --service-user root
```

### Manual fallback commands

If the deployment script cannot be used, perform these commands manually:

```bash
cd /var/www/customer360/customer360/backend && \
python3 -m venv .venv && \
source .venv/bin/activate && \
pip install --upgrade pip && \
pip install -r requirements.txt
```

Create `/etc/systemd/system/customer360-backend.service`:

```ini
[Unit]
Description=Customer360 FastAPI backend
After=network-online.target mysql.service mariadb.service
Wants=network-online.target

[Service]
Type=simple
User=root
Group=www-data
WorkingDirectory=/var/www/customer360/customer360/backend
EnvironmentFile=/var/www/customer360/customer360/backend/.env
ExecStart=/var/www/customer360/customer360/backend/.venv/bin/python -m uvicorn app.main:app --host 127.0.0.1 --port 8000
Restart=always
RestartSec=5
TimeoutStopSec=30
KillSignal=SIGINT

[Install]
WantedBy=multi-user.target
```

Enable and start the service:

```bash
sudo systemctl daemon-reload && \
sudo systemctl enable customer360-backend && \
sudo systemctl restart customer360-backend && \
sudo systemctl --no-pager --full status customer360-backend
```

Update PHP fallback backend host in `/var/www/customer360/customer360/frontend/api/config.php`:

```php
$host = envValue('BACKEND_API_HOST', '127.0.0.1');
$port = envValue('BACKEND_API_PORT', '8000');
```

Reload Apache and verify:

```bash
php -l /var/www/customer360/customer360/frontend/api/config.php && \
apache2ctl -t && \
sudo systemctl reload apache2 && \
curl http://127.0.0.1:8000/health
```

### Troubleshooting checklist

- If `systemctl status customer360-backend` shows import errors, confirm `.venv` exists and `pip install -r requirements.txt` succeeded.
- If startup fails with MySQL auth errors, test credentials directly from the server using:
  ```bash
  /var/www/customer360/customer360/backend/.venv/bin/python -c "import pymysql; conn=pymysql.connect(host='64.23.248.187', port=3306, user='kiki', password='REAL_PASSWORD', database='customer360'); print('ok'); conn.close()"
  ```
- If PHP still calls Render or a laptop IP, grep the deployed code and Apache config:
  ```bash
  grep -R "customer360-w3vy.onrender.com\|BACKEND_API_URL\|BACKEND_API_HOST" /var/www/customer360 /etc/apache2 -n
  ```
- If `/health` works but uploads fail, inspect Apache and backend logs:
  ```bash
  tail -f /var/log/apache2/error.log
  journalctl -u customer360-backend -f
  ```
- If the service starts but report generation or clustering is slow, keep `MAX_ROWS`, `OPTIMAL_K_SUBSAMPLE`, and `SHAP_MAX_SAMPLES` tuned in `backend/.env`.

### Expected final state

- FastAPI listens only on `127.0.0.1:8000` on the server.
- PHP frontend proxies to `http://127.0.0.1:8000/api`.
- Public users access only Apache at `http://64.23.248.187:8080`.
- Backend logs are available through `journalctl -u customer360-backend -f`.
