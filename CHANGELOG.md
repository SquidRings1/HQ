# Changelog

All notable changes to the HQ project are documented here. Each entry corresponds to a Git tag and a GitHub Release.

The format roughly follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), with `Added` / `Changed` / `Fixed` / `Removed` sections per version.

---

## 1.9 — Polish

### Changed
- `MobileShimController::register` now uses `phoneVariants()` for the user-existence check, mirroring `login` and `validate-user`. Leading-zero variants of the same phone are treated as the same account at signup time.

### Removed
- Diagnostic try/catch wrapper in `register()` that was leaking the exception class name and source-file path in 500 responses. Defensive null-coalescing on optional array accesses is retained — that was the actual fix for the original 500.

---

## 1.8 — Monitoring stack (dev compose)

### Added
- `docker/grafana/Dockerfile`, `dashboards.yml`, `datasources.yml`, and a cAdvisor JSON dashboard.
- `docker/prometheus/Dockerfile` and `prometheus.yml`.
- Three monitoring services (`cadvisor`, `prometheus`, `grafana`) in `docker-compose.yml` for local-dev observability.
- `.env.example` entries for `GRAFANA_ADMIN_PASSWORD`.

### Notes
- This is the dev-time configuration only. The AWS-side deployment of the same stack lives in the `projet_final-devsecops` infra repo.

---

## 1.7 — Mobile compatibility shim

### Added
- `MobileShimController` with six endpoints matching the production gocyc API contract:
  - `POST /api/register` — phone-based account creation.
  - `POST /api/login` — passwordless lookup by phone, returns a fresh Sanctum token.
  - `POST /api/validate-user` — pre-check phone/email availability. Three modes: `signup`, `login`, legacy-login.
  - `POST /api/get-event` and `POST /api/get-event-detail` — list / detail.
  - `POST /api/add-interested` and `POST /api/v2_add-interested` — join an event.
  - `GET /api/my-join-events` — user's joined events.
- `phoneVariants()` helper providing leading-zero tolerance: `0612345678` and `612345678` match the same user.
- New migration `2026_05_11_180000_add_phone_to_users.php` adds `country_code`, `phone`, `fname`, `lname` columns on the `users` table.
- `{status, message, data}` response envelope with PascalCase fields, matching the mobile client's expected shape.

---

## 1.6 — Force ECS rollout

### Added
- `force-ecs-rollout` job at the end of `deploy.yml` runs `aws ecs update-service --force-new-deployment` against both `dev-service-api` and `dev-service-admin` after each image push.

### Fixed
- Code-only changes (no infra diff) were sitting in ECR but never reaching running containers because the task definition references `:latest` (no `terraform apply` diff). The new rollout step closes that gap — every deploy now: build → push → force a fresh task → ECS pulls `:latest` again.

---

## 1.5 — Cloudflare integration

### Added
- `admin.exegide.com` and `api.exegide.com` proxied through Cloudflare with Flexible SSL. Edge HTTPS, origin HTTP on port 80.
- `bootstrap/app.php` (both apps) calls `$middleware->trustProxies(at: '*')` so Laravel honors `X-Forwarded-Proto: https` from Cloudflare.

### Fixed
- **419 CSRF mismatch on admin login.** Cloudflare Flexible SSL makes the origin see plain HTTP, so without `trustProxies` Laravel emitted HTTP URLs in redirects and set non-`Secure` cookies — the form session was lost on the first POST. Trusting the proxy fixes the scheme detection.
- **CSP blocking Filament UI.** `script-src 'self'` rejected Livewire's inline scripts, Alpine.js's `eval`, and blob: workers. Admin CSP relaxed to `'unsafe-inline' 'unsafe-eval' blob:` for `script-src` and `'self' blob:` for `worker-src`. API CSP stays stricter (no UI).

---

## 1.4 — Cleanup, healthz, manual apply gate

### Added
- `/healthz` route in both `api/` and `admin/` via Laravel 11's `health: '/healthz'` in `bootstrap/app.php`. Lets the Dockerfile `HEALTHCHECK` actually pass.
- `deploy.yml`'s `terraform-apply` job now uses `environment: terraform-apply`, intended to be a dedicated GitHub Environment gate.

### Changed
- `docker/nginx/api.conf` and `docker/nginx/admin.conf` were byte-identical — consolidated to a single `docker/nginx/site.conf` used by both Dockerfiles.
- `docker/php/www.conf`: `pm.max_children` 20 → 8, `pm.start_servers` 4 → 2, `pm.max_spare_servers` 8 → 3. Right-sized for the 256 MB admin container.

### Removed
- `.github/workflows/deploy_fresh.yml` — duplicate of `deploy.yml`, never used.
- The broken `APP_KEY` fallback in `docker/entrypoint.sh` (was writing to `/tmp/genkey` without exporting; superseded by the SSM-injected `APP_KEY`).

---

## 1.3 — CI/CD foundation

### Added
- `deploy.yml` workflow: preflight secret check, matrix build for `api` + `admin`, push to ECR as `:latest` and `:<git-sha>`.
- `terraform-apply` job inside `deploy.yml` that checks out the `projet_final-devsecops` infra repo via a fine-grained PAT (`INFRA_REPO_TOKEN`), then runs `terraform init` + `terraform apply -auto-approve` against the chosen environment.
- Required repo secrets / variables documented in the workflow header: `AWS_SECRET_KEY`, `INFRA_REPO_TOKEN`, `AWS_ACCESS_KEY`, `AWS_REGION`, `ECR_REPO_API`, `ECR_REPO_ADMIN`.

### Notes
- This is the first version where one trigger (a tag or manual dispatch) drives the full chain: build images → push to ECR → apply infrastructure. Before this, the infra repo had to be applied manually from a developer's laptop.

---

## Tags before 1.3

Pre-tagged history exists on `main` but was not formally released. Roughly:
- Initial Laravel api + admin scaffolding
- Initial Dockerfiles + docker-compose for local dev
- First CI workflows (Pint, PHPUnit, gitleaks, Trivy, hadolint)
- Initial Terraform IaC in the infra repo (VPC, RDS, ECS, IAM)
