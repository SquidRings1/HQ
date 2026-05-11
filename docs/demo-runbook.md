# Demo runbook — HQ portion

Covers the local stack, admin, api, and CI walkthrough. Observability and
infra panels are owned by other team members and demoed in their own slots.

## Prep (the night before)

1. `cd HQ && docker compose down -v && docker compose build --no-cache && docker compose up -d`
2. Verify: `curl -fsS http://localhost:8080/healthz` (api) + `curl -fsS http://localhost:8081/healthz` (admin)
3. Open browser tabs:
   - <http://localhost:8081/admin/login> (admin panel)
   - <http://localhost:8082> (Adminer — fallback proof of DB state)
4. Open one terminal with the API curl commands ready (see below).
5. Open the HQ repo in another tab — for showing CI runs.

## Live demo

### Act 1 — the local stack
- `docker compose ps` → 4 containers (api, admin, db, adminer).
- `docker compose exec api ps` → nginx + php-fpm workers as `www-data`,
  supervisord as PID 1.
- `docker compose exec api id` → `uid=33(www-data)`. Talk: "even if PHP code
  has an RCE, attacker is www-data, not root".

### Act 2 — admin creates an event
- Login as `admin@hq.local` / `AdminDemo123!`.
- Show 5 failed logins → 6th rate-limited (HTTP 429). Talk: `throttle:admin-login`.
- Create a new event "EPITA soutenance ride" — date today + 1.
- Show CSRF protection: posting without token returns 419.
  ```
  curl -X POST http://localhost:8081/admin/events -d 'name=hack' -i | head -1
  # → 419 Page Expired
  ```

### Act 3 — mobile user joins via API
```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"rider@hq.local","password":"RiderDemo123!"}' | jq -r .token)

# List events
curl -s http://localhost:8080/api/events | jq .

# Join the new event
curl -s -X POST http://localhost:8080/api/events/<ID>/join \
  -H "Authorization: Bearer $TOKEN" | jq .

# Confirm
curl -s http://localhost:8080/api/me/events \
  -H "Authorization: Bearer $TOKEN" | jq .
```
- Refresh admin event page → participant appears with timestamp.
- Try joining the same event twice → 409 Conflict. Talk: unique constraint
  on `(event_id, user_id)` + check inside `lockForUpdate()` transaction.
- Hit `/join` 30× in a row → 429. Talk: per-user `throttle:join`.

### Act 4 — CI/CD pipeline
- Open a PR or push a small commit.
- Walk through the workflow runs:
  - `ci-api` / `ci-admin` (test + build + Trivy)
  - `security` (gitleaks, trivy-fs, hadolint)
- Show one Trivy finding being suppressed via `ignore-unfixed: true`. Talk:
  "we gate on fixable HIGH/CRITICAL only — otherwise CI noise dominates".
- Show OIDC role assumption in `deploy.yml` — no static AWS keys.

## Questions to expect (and your answers)

> "Why two services and not one?"
See `architecture.md` § "Why two services". Blast radius + independent
deploys + smaller attack surface per container.

> "Why is `docker-compose` different from production?"
Compose is the dev shim. ECS task defs are the prod orchestrator. Same image,
different env vars. The image is environment-agnostic.

> "What's the worst thing an attacker who pwns the API container can do?"
- Read DB rows reachable to the app user (no admin tables).
- Cannot pivot to admin (separate ECS service, separate task role).
- Cannot read AWS secrets beyond the ones explicitly granted to api task role.
- Cannot escape container without a kernel CVE (no root, no shell).

> "What if Trivy finds a CRITICAL in `php:8.4-fpm-alpine`?"
Build fails. Bump base image, rebuild. If no fix is available, accept and
document with a CVE ignore + expiry, or pin to a different base image.
Decision is logged in PR description.

> "Why MariaDB and not Postgres?"
Spec asked for MariaDB. Postgres would be an equally valid choice
(Laravel-agnostic).

> "What's the SDLC for a new feature?"
1. Branch off `main`. 2. PR triggers `ci-api` or `ci-admin` + `security`.
3. Required reviewer + passing checks gate merge. 4. Merge to `main` →
nothing deploys (deploys are tag-gated). 5. Tag `v0.X.0` →
`deploy.yml` runs → image to ECR → infra repo's `apply.yml` rolls ECS.

## Fallbacks

- **AWS down**: do the whole demo locally with `docker compose`. The HQ side
  doesn't depend on cloud being up.
- **Internet flaky during CI demo**: have a recent passing CI run already open
  in a tab. Walk through that.
- **`docker compose up` fails on the demo laptop**: have a backup laptop with
  prebuilt images already running.
