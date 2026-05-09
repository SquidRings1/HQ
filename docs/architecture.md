# Architecture

## Overview

```
                     Internet (HTTPS only)
                            │
                     ┌──────▼──────┐
                     │     ALB     │  ACM cert, WAF managed rules
                     └──────┬──────┘
                ┌───────────┴───────────┐
            /api/* host                  /admin/* host
                │                           │
          ┌─────▼─────┐               ┌─────▼─────┐
          │ ECS Fargate                ECS Fargate │
          │ hq-api task                hq-admin    │   private subnets
          │ (nginx + php-fpm)          (same)      │   IAM task role
          └─────┬─────┘               └─────┬─────┘
                └───────────┬───────────────┘
                            │   :3306 (private SG)
                      ┌─────▼─────┐
                      │    RDS    │   MariaDB, encrypted, backups, no public IP
                      └───────────┘

  Secrets:  AWS Secrets Manager (DB password, APP_KEY, future: Stripe/SAML stubs)
  Images:   ECR with scan-on-push + Trivy gate in CI
  Logs:     stdout → CloudWatch Logs → Grafana / Splunk (Iban)
  CI/CD:    GitHub Actions → OIDC → AWS (no static keys)
```

## Why two services and not one?

The legacy gocyc monolith bundles the mobile API and the admin panel into one
Laravel app. Three problems with that for our DevSecOps target:

1. **Blast radius** — an exploit in admin (Blade XSS, session theft) shouldn't
   give attackers the same process that serves the mobile API.
2. **Dependency surface** — admin needs Blade/views/sessions; the API doesn't.
   Shipping admin code in the API container = bigger Trivy attack surface.
3. **Independent deploys + scaling** — Karl can deploy admin without touching
   the live mobile fleet, and ECS can autoscale them independently.

We accept the cost: two images, two ECS services, slight schema-coupling
(both speak to the same RDS — admin owns migrations, api consumes).

## Why Laravel 12 fresh-write instead of stripping the monolith?

The legacy `EventController` was 5,214 lines and bound to ~70 other models.
Stripping in place would leave dead references everywhere and make security
review impossible. A fresh skeleton with only the demo flow ported is cleaner
to defend at the soutenance and has a smaller attack surface.

## Why MariaDB on RDS instead of a containerized DB?

The spec asks for "Backend MariaDB" container. We exceed it with managed RDS:

- **Encryption at rest** by default (AWS-managed KMS).
- **Automated backups + point-in-time recovery** without us writing logic.
- **No DB credentials in image or compose** — pulled from Secrets Manager at
  task start.
- **Private SG** — only ECS task SG can talk to RDS:3306.

Local dev still uses a `mariadb:11` container for parity.

## Decision records

| # | Decision | Why |
|---|---|---|
| 1 | Two separate Laravel apps in monorepo (`api/`, `admin/`) | Blast radius + independent CI |
| 2 | Single container per service (nginx + php-fpm + supervisord) | Simpler than multi-container, still production-grade |
| 3 | `admin/` owns the schema migrations | One source of truth; api just reads |
| 4 | Sanctum for api (bearer), web sessions for admin | Right tool per surface |
| 5 | RDS over containerized MariaDB | Encryption, backups, no creds in image |
| 6 | OIDC for GH Actions → AWS | No long-lived access keys |
| 7 | Per-service CI workflows with `paths` filter | A CVE in api/ shouldn't block admin/ deploys |
| 8 | Image promoted by SHA, not rebuilt | Same artifact in staging and prod |

## Shared service contracts

These are the contracts between Rayan (HQ) and "Moi" (infra) — change here = coordinate.

- **Health endpoint**: each container exposes `GET /healthz` returning 200 with
  a DB ping. ALB target group uses this.
- **Listen port**: 8080 inside container.
- **Logs**: JSON to stdout (nginx + Laravel both).
- **Migrations**: deploy pipeline runs `php artisan migrate --force` once via
  ECS one-shot task, **before** rolling new admin/api images.
- **Secrets**: see [`secrets-contract.md`](secrets-contract.md).
