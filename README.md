# HQ — Event services

Two Laravel 12 services for the GoCyc event flow:

| Service | Port (local) | Purpose |
|---|---|---|
| `api/`   | `8080` | Mobile / public REST API (Sanctum bearer tokens) |
| `admin/` | `8081` | Operator dashboard (event CRUD, web sessions) |
| `db`      | `3306` | MariaDB 11, shared schema |
| `adminer` | `8082` | DB browser (dev only) |

Schema is owned by `admin/` (it runs the migrations); `api/` consumes it.

## Local run

```bash
cp .env.example .env          # optional — defaults work
docker compose build
docker compose up -d
```

First boot runs admin migrations + seeds the demo data:

| Account            | Email              | Password         |
|--------------------|--------------------|------------------|
| Admin              | `admin@hq.local`   | `AdminDemo123!`  |
| Mobile user (API)  | `rider@hq.local`   | `RiderDemo123!`  |

- Admin panel: <http://localhost:8081/admin/login>
- API base:    <http://localhost:8080/api>
- Adminer:     <http://localhost:8082> (server `db`, user `hq`)

## Demo flow

```bash
# 1. Admin logs in via browser at http://localhost:8081/admin/login

# 2. From the API: log in as the demo rider
curl -s -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"rider@hq.local","password":"RiderDemo123!"}' | jq .

# 3. List upcoming events
curl -s http://localhost:8080/api/events | jq .

# 4. Join one (TOKEN from step 2)
curl -s -X POST http://localhost:8080/api/events/1/join \
  -H "Authorization: Bearer $TOKEN" | jq .

# 5. See the participant appear in admin: http://localhost:8081/admin/events/1
```

## Repository layout

```
HQ/
├── api/                 # Laravel 12 — REST API only
├── admin/               # Laravel 12 — admin panel only
├── docker/              # Shared nginx + php-fpm + supervisord configs
├── docker-compose.yml   # Local dev orchestration
├── .github/workflows/   # ci-api, ci-admin, security, deploy
└── docs/                # architecture, threat-model, demo-runbook
```

## Security posture

App-layer:
- Sanctum bearer tokens (api), session auth (admin), bcrypt + cast `'password' => 'hashed'`.
- Form Request validation everywhere — no raw input touches Eloquent.
- Rate-limited auth + join endpoints (per-IP and per-user).
- `SecurityHeaders` middleware: CSP (admin), HSTS, X-Frame-Options, Referrer-Policy.
- CSRF on admin (default), no CSRF on api (token auth).

Container:
- Multi-stage build, only artifacts in runtime image.
- `php:8.4-fpm-alpine` base.
- nginx + php-fpm workers run as `www-data`.
- `expose_php = Off`, `display_errors = Off`, opcache enabled.
- `.dockerignore` excludes `.env`, tests, git, node_modules.
- Trivy scan in CI gates HIGH/CRITICAL CVEs.

CI/CD:
- Per-service workflows (`ci-api`, `ci-admin`).
- `security.yml`: gitleaks, trivy-fs (SARIF → Code Scanning), hadolint.
- Deploy via OIDC (no static AWS keys), build-once-promote-by-SHA.
- Third-party actions pinned to commit SHAs.
