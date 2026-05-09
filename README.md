# HQ — GoCyc microservices (DevSecOps final)

Two Laravel 12 services split out of the legacy gocyc monolith:

| Service | Port (local) | Purpose |
|---|---|---|
| `api/`   | `8080` | Mobile / public REST API (Sanctum bearer tokens) |
| `admin/` | `8081` | Operator dashboard (event CRUD, web sessions) |
| `db`      | `3306` | MariaDB 11, shared schema |
| `adminer` | `8082` | DB browser (dev only) |

The schema is owned by `admin/` (it runs the migrations); `api/` consumes it.

## Local run

```bash
cp .env.example .env          # optional — defaults work
docker compose build
docker compose up -d
```

First boot will run admin migrations + seed the demo data:

| Account            | Email              | Password         |
|--------------------|--------------------|------------------|
| Admin              | `admin@hq.local`   | `AdminDemo123!`  |
| Mobile user (API)  | `rider@hq.local`   | `RiderDemo123!`  |

- Admin panel: <http://localhost:8081/admin/login>
- API base:    <http://localhost:8080/api>
- Adminer:     <http://localhost:8082> (server `db`, user `hq`)

## Demo flow (the soutenance script)

```bash
# 1. Admin logs in via browser to http://localhost:8081/admin/login

# 2. From the API: register or log in
curl -s -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"rider@hq.local","password":"RiderDemo123!"}' | jq .

# 3. List upcoming events
curl -s http://localhost:8080/api/events | jq .

# 4. Join one
TOKEN=...   # from step 2
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
└── docs/                # architecture, secrets-contract, threat-model, demo-runbook
```

The matching infra repo (Terraform module + composition) lives at
[`SquidRings1/projet_final-devsecops`](https://github.com/SquidRings1/projet_final-devsecops).
The deploy pipeline (`.github/workflows/deploy.yml`) pushes images to ECR and
dispatches that repo's `apply.yml` to roll the new image.

## Security posture

See [`docs/threat-model.md`](docs/threat-model.md) for the full STRIDE pass.

App-layer:
- Sanctum bearer tokens (api), session auth (admin), bcrypt + cast `'password' => 'hashed'`.
- Form Request validation everywhere — no raw input touches Eloquent.
- Rate-limited auth + join endpoints.
- `SecurityHeaders` middleware: CSP, HSTS, X-Frame-Options, Referrer-Policy.
- CSRF on admin (default), no CSRF on api (token auth).

Container:
- Multi-stage build, only artifacts in runtime image.
- `php:8.4-fpm-alpine` base, no shell needed in runtime.
- `www-data` runs nginx + php-fpm (supervisord is PID 1).
- `expose_php = Off`, `display_errors = Off`, opcache enabled.
- `.dockerignore` excludes `.env`, tests, git, node_modules.
- Trivy scan in CI gates HIGH/CRITICAL CVEs.

CI/CD:
- Per-service workflows (`ci-api`, `ci-admin`).
- `security.yml`: gitleaks, trivy-fs (SARIF → Code Scanning), hadolint.
- Deploy via OIDC (no static AWS keys).
- Third-party actions pinned to commit SHAs.

## CLAUDE.md / docs

- `docs/architecture.md` — component diagram + decision record
- `docs/secrets-contract.md` — what `api/` + `admin/` expect from Secrets Manager
- `docs/threat-model.md` — STRIDE pass per component
- `docs/demo-runbook.md` — full soutenance script with fallbacks
