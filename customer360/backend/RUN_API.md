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

4. Run the API:

```bash
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
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

Optional for self-signed HTTPS during development:

```bash
export BACKEND_VERIFY_SSL=false
```

## Render deployment

Use `render.yaml` in this directory or create the web service manually.

Start command:

```bash
uvicorn app.main:app --host 0.0.0.0 --port $PORT
```

Set these Render environment variables:

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
