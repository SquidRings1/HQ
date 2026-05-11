# Architecture

## Two services

```
                            ┌────────────┐
                            │  /admin/*  │  ──►  admin/  (Laravel 12, web sessions)
                            │            │       Blade UI, AdminUser guard
       Reverse proxy /      │            │       owns the DB schema (migrations)
       ALB in prod   ──────►┤            │
                            │  /api/*    │  ──►  api/    (Laravel 12, Sanctum tokens)
                            │            │       JSON only, mobile / external
                            └────────────┘

                                    │
                                    ▼  shared schema
                            ┌────────────┐
                            │  MariaDB   │   (mariadb container in dev,
                            │            │    RDS in prod — same engine)
                            └────────────┘
```

Both services run the same image type (nginx + php-fpm-alpine + supervisord),
only differ by the `Dockerfile` build stage that pulls their respective `api/`
or `admin/` source tree.

## Why two services and not one

- **Blast radius** — an exploit in admin (Blade XSS, session theft) shouldn't
  give the same process the mobile API runs in.
- **Dependency surface** — admin needs Blade/views/sessions; the API doesn't.
  Shipping admin code in the API container = bigger Trivy attack surface.
- **Independent deploys + scaling** — admin can be redeployed without touching
  the live mobile fleet, and ECS can autoscale them independently.

The cost: two images, two services, slight schema-coupling (both speak to the
same DB — admin owns migrations, api consumes).

## Dev orchestrator vs prod orchestrator

The `docker-compose.yml` is the dev shim — it spins up `mariadb` + `admin` +
`api` + `adminer` for local work. **It is not used in production.**

In prod, ECS task definitions take compose's place: same images, different env
vars (`APP_ENV=production`, `DB_HOST=<rds-endpoint>`, `DB_PASSWORD` from
Secrets Manager, no Adminer). The image itself is environment-agnostic — it
reads everything from runtime env, so the same artifact ships from `compose
up` to `terraform apply`.

This split is intentional: keeps the Docker image free of env-specific code,
keeps the dev loop trivial, keeps the prod orchestration with the team owning
the cloud (Haris).

## Decision records

| # | Decision | Why |
|---|---|---|
| 1 | Two separate Laravel apps in monorepo (`api/`, `admin/`) | Blast radius + independent CI |
| 2 | Single container per service (nginx + php-fpm + supervisord) | Simpler than multi-container, still production-grade |
| 3 | `admin/` owns the schema migrations | One source of truth; api just reads |
| 4 | Sanctum for api (bearer), web sessions for admin | Right tool per surface |
| 5 | `docker-compose.yml` is dev-only; prod uses ECS task defs | Same image, env-injected config; no env-specific code in image |
| 6 | Per-service CI workflows with `paths` filter | A CVE in api/ shouldn't block admin/ deploys |
| 7 | Image promoted by SHA, not rebuilt per env | Same artifact in dev/staging/prod; reproducibility |
| 8 | Third-party GitHub Actions pinned to commit SHA | Supply chain — tags can be moved, SHAs cannot |

## Hand-offs (HQ-side contracts the rest of the team consumes)

- **Health endpoint**: each container exposes `GET /healthz` returning 200.
  Used by Docker `HEALTHCHECK` locally and by ALB target group in prod.
- **Listen port**: 8080 inside container.
- **Logs**: JSON to stdout (nginx access via `log_format json`, Laravel via
  `LOG_CHANNEL=stderr`).
- **Migrations**: only admin runs them (`DB_OWNER=true`). In prod, this is a
  one-shot ECS task triggered before rolling new images.
