# Threat model — STRIDE per HQ component

Scope: the HQ repo (api service, admin service, container image, CI/CD).
Infra threats (RDS, VPC, IAM) live in the infra repo's threat model.

| Letter | Threat | Examples |
|---|---|---|
| S | Spoofing | Forged tokens, stolen cookies |
| T | Tampering | Modified DB rows, modified images |
| R | Repudiation | "I didn't do that" / no audit trail |
| I | Information disclosure | Leaked PII, dumped DB |
| D | Denial of service | Resource exhaustion, account lockout abuse |
| E | Elevation of privilege | User → admin, admin → root |

---

## Component: API service (`hq-api`)

| STRIDE | Threat | Mitigation |
|---|---|---|
| S | Forged Sanctum token | Tokens are 60-byte random + DB-backed; revoke on logout |
| S | Brute-force login | `throttle:auth` (10/min/IP); failed attempts logged |
| T | Mass assignment via `/auth/register` | `$fillable` allow-list on `User`; validated request |
| T | SQL injection | Eloquent prepared statements only; no `DB::raw($input)` anywhere |
| R | No record of who joined what | `event_participants` carries `joined_at`; access logs in stdout |
| I | PII in error responses | `APP_DEBUG=false` in prod; custom JSON exception renderer |
| I | Token in URL | Tokens go in `Authorization` header only, never query string |
| D | Spam-join an event | `throttle:join` (20/min/user); capacity check inside `lockForUpdate()` transaction |
| E | Regular user accesses admin endpoints | Admin endpoints don't exist in this image at all |

## Component: Admin service (`hq-admin`)

| STRIDE | Threat | Mitigation |
|---|---|---|
| S | Stolen admin session cookie | `SESSION_SECURE_COOKIE=true`, `httponly=1`, `samesite=Lax` |
| S | Cross-site request forgery | Laravel CSRF middleware (default in `web` group) |
| T | XSS via event description → admin browser | Blade `{{ }}` auto-escapes; CSP `script-src 'self'` blocks injected JS |
| T | Click-jacking the delete button | `X-Frame-Options: DENY` + `frame-ancestors 'none'` |
| R | Anonymous admin actions | `last_login_at` + access logs; future: action audit log |
| I | Wide listing of mobile users | Admin can only list participants of events, not all users |
| D | Login flood | `throttle:admin-login` (5/min/IP) |
| E | Mobile user object accidentally usable as admin | `admin_users` is a separate table + separate `AdminUser` model + separate `admins` provider |

## Component: Container image

| STRIDE | Threat | Mitigation |
|---|---|---|
| T | Image poisoned with miner | Built only from our `Dockerfile`; pinned base `php:8.4-fpm-alpine`; Trivy gate in CI; ECR scan-on-push |
| T | Tampered image after build | `provenance: true` + SBOM in build action; pull-by-digest in prod task def |
| I | Secrets baked in image | `.dockerignore` excludes `.env`; CI rejects images that contain `APP_KEY=base64:` literal |
| E | Container escape via root process | App processes (nginx, php-fpm) run as `www-data`; supervisord is PID 1 but doesn't expose ports |
| E | Writable rootfs | Prod task def uses `readonlyRootFilesystem: true` (set by infra) |

## Component: CI/CD pipeline

| STRIDE | Threat | Mitigation |
|---|---|---|
| S | Forked PR runs with our secrets | `pull_request_target` not used; secrets gated behind branch protection + required reviewers |
| T | Compromised third-party action | All third-party actions pinned to commit SHA, not tag; `permissions:` minimised per workflow |
| R | Untraceable deploy | Every deploy is a tagged git commit + ECR image tag = same SHA |
| I | Leaked secret in PR | `gitleaks` runs on every PR; PR fails if a secret pattern is found |
| D | Workflow runaway | `timeout-minutes` per job; `concurrency` group prevents runaway parallel deploys |
| E | Static AWS keys | Replaced with OIDC role assumption — no long-lived AWS creds in any GitHub secret |

## Out of scope for HQ (handled elsewhere)

- RDS, VPC, IAM, Secrets Manager — infra repo's threat model.
- Grafana/Splunk dashboards, alerting, log retention — observability owner.
- DDoS at the AWS edge, physical security of AWS — AWS's responsibility.
