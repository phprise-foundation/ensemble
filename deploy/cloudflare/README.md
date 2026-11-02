# deploy/cloudflare — Ensemble on Cloudflare Containers

A Worker plus one Durable Object (`EnsembleContainer`) that starts the
production image (repo `Dockerfile`) and proxies every request to it. A cron
trigger pings `/api/health` every minute so the runner loop stays alive.

## One-time setup (manual)

1. `npm ci` inside `deploy/cloudflare`.
2. Authenticate `npx wrangler login` (or export `CLOUDFLARE_API_TOKEN` +
   `CLOUDFLARE_ACCOUNT_ID`; the release workflow uses those two secrets).
3. Create the secrets — they are injected into the container on start
   (`src/index.ts` allowlist):
   `npx wrangler secret put DATABASE_URL`,
   `npx wrangler secret put APP_ENCRYPTION_KEY`,
   `npx wrangler secret put BOOTSTRAP_ADMIN_LOGIN`,
   `npx wrangler secret put BOOTSTRAP_ADMIN_PASSWORD`
   and one secret per provider key used by agents (`DEEPSEEK_API_KEY`,
   `SILICONFLOW_API_KEY`, …).
4. Provision an external PostgreSQL and the DNS record:
   `npx wrangler deploy` prints the `workers.dev` URL; for
   `ensemble.phprise.org` add a Custom Domain in the dashboard or
   `npx wrangler domains attach <route>`.

`wrangler deploy` builds the image with the local Docker engine and pushes it
to the Cloudflare managed registry; the release workflow does this on every
`v*` tag. The Docker Hub image (`esdrasschonevald/ensemble`) is the same
Dockerfile for self-hosted users.

## Roles

The container entrypoint honors `ENSEMBLE_ROLE` (`web`, `runner`, `all`).
Single container deployments keep `all` (default). Splitting the runner into a
second container with its own durable object is a post-v1 option (one runner
loop per database until RFC-2026-0002).
