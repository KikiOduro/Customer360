#!/usr/bin/env python3
"""
Deploy the Customer360 FastAPI backend directly on the Ubuntu server.

What this script does:
- Creates a Python virtual environment for the backend if needed
- Installs backend dependencies from requirements.txt
- Validates that backend/.env exists and contains required production keys
- Installs/updates a systemd unit for Uvicorn
- Ensures the PHP proxy points to http://127.0.0.1:8000/api by default
- Reloads Apache
- Starts/restarts the backend service
- Verifies the FastAPI /health endpoint

Intended server layout:
- App checkout: /var/www/customer360/customer360
- Backend path: /var/www/customer360/customer360/backend
- Frontend PHP config: /var/www/customer360/customer360/frontend/api/config.php

Example:
  sudo python3 deploy/deploy_backend_server.py \
    --project-root /var/www/customer360/customer360 \
    --service-name customer360-backend \
    --backend-host 127.0.0.1 \
    --backend-port 8000
"""

from __future__ import annotations

import argparse
import os
import re
import shutil
import subprocess
import sys
import time
from pathlib import Path
from urllib.error import URLError
from urllib.request import urlopen


DEFAULT_PROJECT_ROOT = Path("/var/www/customer360/customer360")
DEFAULT_SERVICE_NAME = "customer360-backend"
DEFAULT_BACKEND_HOST = "127.0.0.1"
DEFAULT_BACKEND_PORT = 8000
DEFAULT_PHP_CONFIG = Path("frontend/api/config.php")
DEFAULT_BACKEND_DIR = Path("backend")

REQUIRED_ENV_KEYS = (
    "DB_HOST",
    "DB_PORT",
    "DB_USER",
    "DB_PASSWORD",
    "DB_NAME",
    "SECRET_KEY",
    "ENVIRONMENT",
)


def run_command(
    cmd: list[str],
    *,
    cwd: Path | None = None,
    check: bool = True,
    capture_output: bool = False,
) -> subprocess.CompletedProcess[str]:
    print(f"+ {' '.join(cmd)}")
    return subprocess.run(
        cmd,
        cwd=str(cwd) if cwd else None,
        check=check,
        text=True,
        capture_output=capture_output,
    )


def require_root() -> None:
    if os.geteuid() != 0:
        print("This script must be run as root because it writes systemd and reloads Apache.")
        print("Re-run with sudo.")
        sys.exit(1)


def parse_env_file(env_path: Path) -> dict[str, str]:
    values: dict[str, str] = {}
    for raw_line in env_path.read_text().splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        values[key.strip()] = value.strip().strip('"').strip("'")
    return values


def validate_backend_env(env_path: Path, backend_host: str, backend_port: int) -> dict[str, str]:
    if not env_path.exists():
        raise FileNotFoundError(
            f"Missing {env_path}. Create it from backend/.env.example and fill DB/JWT/Supabase values first."
        )

    env_values = parse_env_file(env_path)
    missing = [key for key in REQUIRED_ENV_KEYS if not env_values.get(key)]
    if missing:
        raise ValueError(f"{env_path} is missing required values: {', '.join(missing)}")

    if env_values.get("ENVIRONMENT", "").lower() != "production":
        print("WARNING: ENVIRONMENT is not set to production in backend/.env")

    if env_values.get("API_HOST") not in {None, "", backend_host, "0.0.0.0"}:
        print(f"WARNING: backend/.env API_HOST={env_values.get('API_HOST')} differs from service host {backend_host}")

    if env_values.get("API_PORT") and env_values["API_PORT"] != str(backend_port):
        print(f"WARNING: backend/.env API_PORT={env_values.get('API_PORT')} differs from service port {backend_port}")

    return env_values


def ensure_venv(backend_dir: Path, python_bin: str) -> Path:
    venv_dir = backend_dir / ".venv"
    venv_python = venv_dir / "bin" / "python"
    venv_pip = venv_dir / "bin" / "pip"

    if not venv_python.exists():
        run_command([python_bin, "-m", "venv", str(venv_dir)], cwd=backend_dir)

    run_command([str(venv_pip), "install", "--upgrade", "pip"], cwd=backend_dir)
    run_command([str(venv_pip), "install", "-r", "requirements.txt"], cwd=backend_dir)
    return venv_python


def systemd_unit_text(
    *,
    service_name: str,
    backend_dir: Path,
    venv_python: Path,
    backend_host: str,
    backend_port: int,
    user: str,
) -> str:
    return f"""[Unit]
Description=Customer360 FastAPI backend
After=network-online.target mysql.service mariadb.service
Wants=network-online.target

[Service]
Type=simple
User={user}
Group=www-data
WorkingDirectory={backend_dir}
EnvironmentFile={backend_dir / ".env"}
ExecStart={venv_python} -m uvicorn app.main:app --host {backend_host} --port {backend_port}
Restart=always
RestartSec=5
TimeoutStopSec=30
KillSignal=SIGINT

[Install]
WantedBy=multi-user.target
"""


def install_systemd_service(
    *,
    service_name: str,
    backend_dir: Path,
    venv_python: Path,
    backend_host: str,
    backend_port: int,
    user: str,
) -> Path:
    unit_path = Path("/etc/systemd/system") / f"{service_name}.service"
    unit_path.write_text(
        systemd_unit_text(
            service_name=service_name,
            backend_dir=backend_dir,
            venv_python=venv_python,
            backend_host=backend_host,
            backend_port=backend_port,
            user=user,
        )
    )

    run_command(["systemctl", "daemon-reload"])
    run_command(["systemctl", "enable", service_name])
    return unit_path


def ensure_php_backend_points_local(config_path: Path, backend_host: str, backend_port: int) -> None:
    if not config_path.exists():
        raise FileNotFoundError(f"Missing PHP config file: {config_path}")

    content = config_path.read_text()

    if "function backendBaseUrl()" in content:
        content = re.sub(
            r"\$host = envValue\('BACKEND_API_HOST', '[^']*'\);",
            f"$host = envValue('BACKEND_API_HOST', '{backend_host}');",
            content,
        )
        content = re.sub(
            r"\$port = envValue\('BACKEND_API_PORT', '[^']*'\);",
            f"$port = envValue('BACKEND_API_PORT', '{backend_port}');",
            content,
        )
    else:
        content = re.sub(
            r"\$backendApiUrl = rtrim\(envValue\('BACKEND_API_URL', '[^']*'\), '/'\);",
            f"$backendApiUrl = rtrim(envValue('BACKEND_API_URL', 'http://{backend_host}:{backend_port}'), '/');",
            content,
        )

    config_path.write_text(content)


def verify_php_config(config_path: Path) -> None:
    php_bin = shutil.which("php")
    if not php_bin:
        print("WARNING: php binary not found; skipping php -l validation")
        return
    run_command([php_bin, "-l", str(config_path)])


def reload_apache() -> None:
    apachectl = shutil.which("apache2ctl")
    if apachectl:
        run_command([apachectl, "-t"])
    run_command(["systemctl", "reload", "apache2"])


def restart_service(service_name: str) -> None:
    run_command(["systemctl", "restart", service_name])
    run_command(["systemctl", "--no-pager", "--full", "status", service_name], check=False)


def verify_health(backend_host: str, backend_port: int, timeout_seconds: int = 30) -> None:
    health_url = f"http://{backend_host}:{backend_port}/health"
    deadline = time.time() + timeout_seconds
    last_error: Exception | None = None

    while time.time() < deadline:
        try:
            with urlopen(health_url, timeout=5) as response:
                body = response.read().decode("utf-8", errors="replace")
                print(f"Health check OK: {health_url}")
                print(body)
                return
        except URLError as exc:
            last_error = exc
            time.sleep(2)

    raise RuntimeError(f"Health check failed for {health_url}: {last_error}")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Deploy Customer360 FastAPI backend as a systemd service on the server."
    )
    parser.add_argument(
        "--project-root",
        type=Path,
        default=DEFAULT_PROJECT_ROOT,
        help="Path to the customer360 app root containing backend/ and frontend/",
    )
    parser.add_argument(
        "--backend-dir",
        type=Path,
        default=DEFAULT_BACKEND_DIR,
        help="Backend directory relative to project root",
    )
    parser.add_argument(
        "--php-config",
        type=Path,
        default=DEFAULT_PHP_CONFIG,
        help="PHP config path relative to project root",
    )
    parser.add_argument(
        "--service-name",
        default=DEFAULT_SERVICE_NAME,
        help="systemd service name",
    )
    parser.add_argument(
        "--backend-host",
        default=DEFAULT_BACKEND_HOST,
        help="Host Uvicorn should bind to. Use 127.0.0.1 when Apache and FastAPI are on the same server.",
    )
    parser.add_argument(
        "--backend-port",
        type=int,
        default=DEFAULT_BACKEND_PORT,
        help="Port Uvicorn should listen on",
    )
    parser.add_argument(
        "--python-bin",
        default="python3",
        help="Python executable used to create the venv",
    )
    parser.add_argument(
        "--service-user",
        default="root",
        help="Linux user that runs the systemd service",
    )
    parser.add_argument(
        "--skip-apache-reload",
        action="store_true",
        help="Do not reload Apache after updating config.php",
    )
    parser.add_argument(
        "--skip-health-check",
        action="store_true",
        help="Do not verify /health after restarting the backend service",
    )
    return parser


def main() -> int:
    args = build_parser().parse_args()
    require_root()

    project_root = args.project_root.resolve()
    backend_dir = (project_root / args.backend_dir).resolve()
    php_config_path = (project_root / args.php_config).resolve()
    env_path = backend_dir / ".env"

    if not project_root.exists():
        raise FileNotFoundError(f"Project root not found: {project_root}")
    if not backend_dir.exists():
        raise FileNotFoundError(f"Backend directory not found: {backend_dir}")
    if not (backend_dir / "requirements.txt").exists():
        raise FileNotFoundError(f"requirements.txt not found in {backend_dir}")

    print(f"Project root: {project_root}")
    print(f"Backend dir: {backend_dir}")
    print(f"PHP config: {php_config_path}")
    print(f"Service: {args.service_name}")
    print(f"Bind target: {args.backend_host}:{args.backend_port}")

    validate_backend_env(env_path, args.backend_host, args.backend_port)
    venv_python = ensure_venv(backend_dir, args.python_bin)

    unit_path = install_systemd_service(
        service_name=args.service_name,
        backend_dir=backend_dir,
        venv_python=venv_python,
        backend_host=args.backend_host,
        backend_port=args.backend_port,
        user=args.service_user,
    )
    print(f"Installed systemd unit: {unit_path}")

    ensure_php_backend_points_local(php_config_path, args.backend_host, args.backend_port)
    verify_php_config(php_config_path)

    if not args.skip_apache_reload:
        reload_apache()

    restart_service(args.service_name)

    if not args.skip_health_check:
        verify_health(args.backend_host, args.backend_port)

    print("\nDeployment complete.")
    print(f"FastAPI backend: http://{args.backend_host}:{args.backend_port}")
    print(f"PHP proxy default backend: http://{args.backend_host}:{args.backend_port}/api")
    print(f"Service logs: journalctl -u {args.service_name} -f")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
