# Customer360 API Runbook

## Local startup

1. Create and activate a virtual environment:

```bash
cd customer360/backend
python3 -m venv .venv
source .venv/bin/activate
```

2. Install dependencies:

```bash
pip install -r requirements.txt
```

3. Configure environment:

```bash
cp .env.example .env
```

Required values to review in `.env`:

- `SECRET_KEY`
- `GROQ_API_KEY`
- `DATABASE_URL` or `DB_*`
- `SUPABASE_SERVICE_ROLE_KEY`
- `API_HOST` and `API_PORT` if another machine must reach the API

Recommended if the deployed frontend at `http://64.23.248.187:8080/` should call this backend directly:

```bash
API_HOST=0.0.0.0
API_PORT=8000
ALLOWED_ORIGINS=http://64.23.248.187:8080
```

4. Run the API:

```bash
source .env
uvicorn app.main:app --reload --host "${API_HOST:-0.0.0.0}" --port "${API_PORT:-8000}"
```

5. Verify the API:

```bash
curl http://localhost:8000/health
```

Swagger docs:

- `http://localhost:8000/docs`

## Local frontend connection

The PHP frontend proxies requests to the backend through `frontend/api/config.php`.
Set this before starting your web server if the backend is not on `http://localhost:8000/api`:

```bash
export BACKEND_API_URL="http://localhost:8000/api"
```

If the frontend hosted at `64.23.248.187:8080` should connect to your local machine, point it at your public IP instead:

```bash
export BACKEND_API_URL="http://<your-public-ip>:8000/api"
```

Or configure it in parts:

```bash
export BACKEND_API_SCHEME="http"
export BACKEND_API_HOST="<your-public-ip>"
export BACKEND_API_PORT="8000"
```

Optional for self-signed HTTPS during development:

```bash
export BACKEND_VERIFY_SSL=false
```

## External access requirement

`--host 0.0.0.0` only makes FastAPI listen on your machine. It does not bypass NAT, router, or firewall rules.

For `http://64.23.248.187:8080/` to reach your local backend, you still need all of the following:

1. Inbound access to port `8000` allowed on your computer.
2. Router port forwarding from public port `8000` to this machine if you are behind NAT.
3. The deployed PHP app configured with your actual public IP, not `localhost` or a LAN IP.
4. External verification with `curl http://<your-public-ip>:8000/health` from a different network.

If inbound port forwarding is not possible, you will need a tunnel or reverse proxy. That part is outside the app code.

## Render deployment

Use `render.yaml` in this directory or create the web service manually.

Start command:

```bash
uvicorn app.main:app --host 0.0.0.0 --port $PORT
```

Set these Render environment variables:

- `PYTHON_VERSION=3.11.11`
- `ENVIRONMENT=production`
- `DEBUG=false`
- `DATABASE_URL=<your mysql connection string>`
- `SECRET_KEY=<long random secret>`
- `GROQ_API_KEY=<your groq key>`
- `SUPABASE_URL=https://bsacwgdxmnuifvhfasof.supabase.co`
- `SUPABASE_STORAGE_BUCKET=user_uploads`
- `SUPABASE_SERVICE_ROLE_KEY=<your supabase service role key>`
- `ALLOWED_ORIGINS=<comma-separated frontend origins>`
- `ALLOW_CREDENTIALS=true`

Important deployment note:

- Push and redeploy whenever backend response contracts change, especially these files:
  - `app/routes/jobs.py`
  - `app/storage.py`
  - `app/schemas.py`
- If uploads still fail after deploy, inspect the response from `POST /api/jobs/upload` directly in Render logs or `/docs`.
- The PHP proxy expects a JSON body containing `job_id` on success. If Render serves stale code or returns a wrapped/non-JSON body, the frontend upload will fail even when HTTP status is `200`.

## Frontend deployment note

If the PHP frontend is hosted separately, it must know the backend base URL.
Set this environment variable in the PHP service:

```bash
BACKEND_API_URL=https://your-render-api-host/api
```

If the backend uses a valid TLS certificate, leave `BACKEND_VERIFY_SSL=true`.

## Production checks

Run these after deploy:

```bash
curl https://your-render-api-host/health
curl https://your-render-api-host/docs
```

Then verify from the website:

1. Register or sign in.
2. Upload a CSV.
3. Confirm the job appears on dashboard and reports.
4. Open `analysis.php` and run a mapped analysis.
5. Generate one Groq profile and one PDF report.
