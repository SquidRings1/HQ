# Soutenance demo runbook

30-minute slot. Goal: prove the whole pipeline + every member's piece works
end-to-end, then field 10 minutes of questions.

## Prep (the night before)

1. `cd HQ && docker compose down -v && docker compose build --no-cache && docker compose up -d`
2. Verify: `curl -fsS http://localhost:8080/healthz` (api) + `curl -fsS http://localhost:8081/healthz` (admin).
3. Open three browser tabs:
   - <http://localhost:8081/admin/login> (admin panel)
   - <http://localhost:8082> (Adminer — DB browser, fallback proof)
   - Grafana dashboard URL (Iban's setup, on AWS)
4. Open one terminal with the API curl commands ready (see below).
5. Open the GitHub repos in two more tabs (HQ + infra) — for showing CI runs.

## Live demo (15 min)

### Act 1 — the local stack (3 min)
- Show `docker compose ps` → 4 containers (api, admin, db, adminer).
- `docker compose exec api ps` → show nginx + php-fpm running as `www-data`,
  supervisord as PID 1.
- `docker compose exec api id` → `uid=33(www-data)`. Talk: "even if PHP code
  has an RCE, attacker is www-data, not root".

### Act 2 — admin creates an event (3 min)
- Login as `admin@hq.local` / `AdminDemo123!`.
- Show 5 failed logins → 6th rate-limited (HTTP 429). Talk: `throttle:admin-login`.
- Create a new event "EPITA soutenance ride" — date today + 1.
- Show that without CSRF token, the form 419s. Briefly demonstrate via curl:
  ```
  curl -X POST http://localhost:8081/admin/events -d 'name=hack' -i | head -1
  # → 419 Page Expired
  ```

### Act 3 — mobile user joins via API (4 min)
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
- Try to join 30 times in a row → throttled. Talk: `throttle:join`.
- Try to join the same event twice → 409 Conflict. Talk: unique constraint
  on `(event_id, user_id)` + check inside `lockForUpdate()` transaction.

### Act 4 — CI/CD pipeline (3 min)
- Open a PR or push a small commit.
- Walk through the workflow runs:
  - `ci-api` / `ci-admin` (test + build + Trivy)
  - `security` (gitleaks, trivy-fs, hadolint)
- Show one Trivy finding being suppressed via `ignore-unfixed: true`. Talk:
  "we gate on fixable HIGH/CRITICAL only — otherwise CI noise dominates".
- Show OIDC role assumption in `deploy.yml` — no AWS keys.

### Act 5 — observability (2 min, hand to Iban)
- Iban shows Grafana: ECS CPU/mem, RDS connections, ALB 5xx rate.
- Splunk panel: failed admin logins → trigger one live, watch it appear.

## Questions you should expect (and your answers)

> "Why two services and not one?"
See `docs/architecture.md` § "Why two services". Blast radius + independent
deploys + smaller attack surface per container.

> "Why fresh-write instead of refactor?"
Original `EventController` was 5,214 lines bound to 70 models. Stripping
would leave dead refs everywhere — security review becomes impossible. Fresh
write with the demo flow is auditable and small.

> "What's the worst thing an attacker who pwns the API container can do?"
- Read DB rows reachable to the app user (no admin tables).
- Cannot pivot to admin (separate ECS service, separate task role).
- Cannot read AWS secrets beyond the ones explicitly granted to api task role.
- Cannot escape container without a kernel CVE (no root, no shell).

> "What if the Trivy scan finds a CRITICAL in `php:8.4-fpm-alpine`?"
Build fails. We bump the base image and rebuild. If no fix is available, we
either accept and document with a CVE ignore + expiry, or pin to a different
base image. Decision is logged in PR description.

> "Why MariaDB and not Postgres?"
Spec asked for MariaDB. Postgres would be an equally valid choice
(Laravel-agnostic).

> "How do you rotate `DB_PASSWORD`?"
Secrets Manager 30-day rotation Lambda. ECS task picks up the new value on
next start (rolling deploy). We don't need to touch app code.

> "What's the SDLC for a new feature?"
1. Branch off `main`. 2. PR triggers `ci-api` or `ci-admin` + `security`.
3. Required reviewer + passing checks gate merge. 4. Merge to `main` →
nothing deploys (deploys are tag-gated). 5. Tag `v0.X.0` →
`deploy.yml` runs → image to ECR → infra repo's `apply.yml` rolls ECS.

## Fallbacks if AWS is misbehaving

- **AWS down**: do the whole demo locally with `docker compose`. The architecture
  diagram + Iban's recorded Grafana screenshots cover the cloud half.
- **Internet flaky during CI demo**: have a recent passing CI run already open
  in a tab. Walk through that.
- **`docker compose up` fails on the demo laptop**: have a backup laptop with
  prebuilt images already running.
