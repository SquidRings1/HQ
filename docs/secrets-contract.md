# Secrets contract

This is the list of environment variables each service expects at runtime.
The infra repo (`projet_final-devsecops`) provisions matching entries in AWS
Secrets Manager and wires them into the ECS task definitions via
`secrets:` references.

**Nothing here ships in the image.** All values are injected at container start.

## Common to both services

| Var | Source | Notes |
|---|---|---|
| `APP_ENV` | task def env | `production` for prod, `staging` for staging |
| `APP_DEBUG` | task def env | `false` in any non-local env |
| `APP_KEY` | Secrets Manager | `base64:` + 32 random bytes — rotate yearly |
| `APP_URL` | task def env | Public URL behind the ALB |
| `LOG_CHANNEL` | task def env | `stderr` (CloudWatch picks it up) |
| `LOG_LEVEL` | task def env | `info` in prod |
| `DB_CONNECTION` | task def env | `mysql` |
| `DB_HOST` | task def env | RDS endpoint (output from infra) |
| `DB_PORT` | task def env | `3306` |
| `DB_DATABASE` | task def env | `hq` |
| `DB_USERNAME` | Secrets Manager | App-scoped DB user, not root |
| `DB_PASSWORD` | Secrets Manager | Rotated by Secrets Manager policy |

## API only

| Var | Source | Notes |
|---|---|---|
| `SANCTUM_STATEFUL_DOMAINS` | task def env | Empty in prod (mobile = stateless) |
| `DB_OWNER` | task def env | `false` — api never runs migrations |

## Admin only

| Var | Source | Notes |
|---|---|---|
| `SESSION_DRIVER` | task def env | `database` (sticky sessions are not needed) |
| `SESSION_DOMAIN` | task def env | `.<your-domain>` |
| `SESSION_SECURE_COOKIE` | task def env | `true` |
| `DB_OWNER` | task def env | `true` — admin runs migrations |
| `RUN_MIGRATIONS` | task def env | `true` |

## How rotation works

- **`DB_PASSWORD`**: AWS Secrets Manager Lambda rotation, every 30 days.
  ECS task picks up the new value on next task start (rolling deployment).
- **`APP_KEY`**: manual rotation, yearly. Rolling rotation requires keeping
  the previous key in `APP_PREVIOUS_KEYS` so cookies/sessions decrypt during
  the cutover. Plan a maintenance window if not.

## What's NOT in this contract (handled elsewhere)

- TLS certs — ACM, attached to ALB by infra.
- IAM credentials — never. ECS task role + OIDC handle all AWS access.
- Stripe / SAML / external API keys — not used in the demo. Add to Secrets
  Manager and update this table when wired back in.
