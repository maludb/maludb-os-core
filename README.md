# MaluDB OS Core

The **kernel** of a Business OS: a hosted platform that manages a company's **AI agent workforce**,
the **super-admins** who run it, and **single sign-on** for every business application — and runs
no business application itself.

Business applications (HR, projects, reservations, scheduling, …) are separate products with their
own repositories, database, MCP servers and expert agent. They plug into this kernel through a
published integration contract and are signed in by it.

> **Status:** active development. This repository started a clean public history on 2026-09-29.

---

## Contents

- [What the kernel does](#what-the-kernel-does)
- [Architecture](#architecture)
- [Repository layout](#repository-layout)
- [Tech stack](#tech-stack)
- [Getting started](#getting-started)
- [Services and scheduled jobs](#services-and-scheduled-jobs)
- [Configuration and secrets](#configuration-and-secrets)
- [Roles and access](#roles-and-access)
- [Agents](#agents)
- [Integrating an application](#integrating-an-application)
- [Development conventions](#development-conventions)
- [Documentation map](#documentation-map)
- [Security](#security)
- [License](#license)

---

## What the kernel does

| Area | What it provides |
|---|---|
| **Estate** | One location tree — building → office (a VM) → desk (an enrolled desktop) — and the applications that reside there. |
| **Departments and people** | Org chart, department membership and administration, the directory of people, invitations. |
| **Agent HR** | Hiring, versioning and activating agents; prompts, skills, tool grants, duties, delegation; each agent has a human manager. |
| **Agent runtime** | Runs agents through per-model harnesses behind one interface; every model call is recorded in a prompt ledger. |
| **Applications** | Catalog and registry of applications, their roles and rights, scopes (sites or departments), and who holds what. |
| **Single sign-on** | `/launch/<id>` mints a 60-second, single-use hand-off token and signed claims and sends the browser to the application. |
| **Directory API** | Bearer-token API through which applications read (and, if allowed, change) people, departments and access. |
| **Approvals** | Sensitive agent and application actions pause for a super-admin's decision. |
| **AI Ops** | Runs, traces, evals, run verdicts, skill library, token spend and monthly **ledger statements** (dollar costs; no books). |
| **Assistants and messaging** | A personal assistant per person; agents message one another along the org tree; Telegram, SMS and email channels. |
| **Services for applications** | An application can text a member or call another application's shared tool through the kernel, without ever holding a provider key. |

## Architecture

Three names per company, one kernel:

```
 www.<domain>   public website (out of scope here)
 os.<domain>    THIS platform — super-admins and agents only
 app.<domain>   sign-in + launcher, for every human
 <app>.<domain> one name per application (own Apache / PostgreSQL / PHP / HTMX, own MCP, own expert agent)
```

```
 browser ──► Apache :80 ──► Next.js/React (web/, :3000)
                 │                 │  server components + actions only
                 │                 ▼
                 │          PHP JSON API (html/, 127.0.0.1:8080)  ──►  PostgreSQL 17 (certstudy)
                 │                 ▲
                 └─ /mcp/* ────────┼──►  MCP servers (Python, mcp/)
                                   │        records · activity · actions · memory
                                   │
                     agent runner (:8815/8816) ──► harnesses (Hermes, Claude Agent SDK, system_one)
                                   │
                                   └──► ledger proxy ──► model providers
                     MaluDB (activity memory, port 8000) ◄── activity ingest (every minute)
```

Key points:

- **React is the only UI.** `web/` reads through server components and writes through server actions, both via
  `web/lib/api.ts`. **The browser never talks to PHP.**
- **PHP is a localhost-only JSON API.** The public port exposes only an allow-list: `/api/v1/{health,me,members,org-graph}`,
  the `/mcp/*` proxies and a voice webhook. See `docs/deploy/apache-react-cutover.conf`.
- **Two faces on one codebase.** `OS_HOST` (super-admins and agents) and `APP_HOST` (sign-in, launcher, settings); leaving
  both empty gives a single face.
- **Memory-first.** Every state change is logged to `activity_log` and shipped to MaluDB; agents read records and activity
  through MCP servers.

## Repository layout

```
app/         PHP application code: bootstrap, auth, db, http helpers, and app/features/<feature>/ (handlers, queries, presenters)
html/        PHP endpoints (the web root of the internal :8080 vhost), incl. html/api/v1/ (directory, ledger, runs, agents, notify, apps)
web/         Next.js 15 / React 19 front end (the only UI); web/lib/api.ts is the sole path to PHP
mcp/         Python MCP servers, the agent runner (mcp/agent_runner/), harnesses, smoke tests (mcp/smoke/), action registries
db/          numbered, additive SQL migrations (000 → latest)
bin/         CLI tools: installer, agent hiring, token minting, channel setup, cron jobs (bin/cron/)
skills/      skills shipped with the kernel's own agents
config/      config/.env.example (the real config/.env is gitignored)
docs/        requirements, build plan, integration design, per-feature specs (docs/build-specs/), deployment files (docs/deploy/)
landing/     static landing page for the bare domain
```

## Tech stack

- **PHP 8.3** — vanilla, feature-oriented page controllers with procedural PDO queries (no framework); Composer dependencies in `composer.json`.
- **PostgreSQL 17** — row-level security, security-barrier views, additive migrations.
- **Next.js 15 / React 19 / TypeScript** — Bootstrap 5.3 “nxl” look for day-to-day screens.
- **Python 3 (FastMCP, asyncpg, uvicorn)** — MCP servers and the agent runner (`mcp/requirements.txt`).
- **MaluDB** — activity memory (`maludb_core` Postgres extension plus its API service).
- **Apache 2.4** reverse proxy, **systemd** services, **cron** for the few scheduled PHP jobs.

## Getting started

These steps assume Ubuntu with Apache, PHP 8.3 (with `pdo_pgsql`), PostgreSQL 17, Node 20+ and Python 3.11+.
Production hosts are provisioned from the files in `docs/deploy/`; treat this as a development outline.

```bash
git clone https://github.com/maludb/maludb-os-core.git /var/www
cd /var/www

# 1. PHP dependencies
composer install

# 2. Configuration — fill in every blank value
cp config/.env.example config/.env
cp web/.env.example web/.env.local

# 3. Database: create the `certstudy` database and its roles, then run the migrations in order
sudo -u postgres createdb certstudy
for f in db/*.sql; do sudo -u postgres psql -v ON_ERROR_STOP=1 -d certstudy -f "$f"; done
#   (read db/000_extensions_roles.sql first: it creates the app_rw / app_records_ro / app_activity_ro roles
#    and expects you to set their passwords; a few files are documented as one-off and are not additive)

# 4. First organizer (super-admin)
php bin/bootstrap_organizer.php --email you@example.com --name "Your Name"   # prints a generated password once

# 5. Front end
cd web && npm ci && npm run build && npm start     # serves :3000

# 6. MCP servers and agent runner
python3 -m venv mcp/venv && mcp/venv/bin/pip install -r mcp/requirements.txt
```

Then install the Apache vhosts (`docs/deploy/apache-react-cutover.conf`, `apache-mcp-proxy.conf`), the systemd units
(`docs/deploy/*.service`), and the cron table (`docs/deploy/crontab.example`). For the agent runner see
`docs/deploy/runner.env.example`, `hermes-install.md` and `claude-agent-install.md`.

**Migrations are numbered and additive**: never edit an applied file, add the next number. Views keep
`WITH (security_barrier = true)`, and new columns are appended last.

### Testing

- `php bin/test_app_services.php` — application services (SMS, application reads).
- `mcp/smoke_actions.py run mcp/smoke/<n>.json` — write-path smoke tests. **They write real rows** named `SMOKE <run>`; run them
  only against a development database.
- `mcp/agent_runner/tests/` — runner unit tests.

## Services and scheduled jobs

| systemd unit | Port | Purpose |
|---|---|---|
| `certstudy-web` | 3000 | Next.js front end |
| `certstudy-records-mcp` | 8811 | record-memory MCP (PostgreSQL) |
| `certstudy-activity-mcp` | 8812 | activity-memory MCP (MaluDB) |
| `certstudy-actions-mcp` | 8813 | action MCP: state-changing tools, approval hook in front |
| `certstudy-memory-mcp` | 8814 | agent memory MCP |
| `certstudy-agent-runner` | 8815/8816 | agent runner and ledger proxy |
| `certstudy-channels` | — | Telegram / SMS / email channel worker |
| `certstudy-activity-ingest.timer` | — | ships `activity_log` to MaluDB every minute |

Cron (`docs/deploy/crontab.example`): invitation-expiry report, outbound-email flush, approval expiry. Agents' scheduled duties
are fired by the runner's scheduler, not by cron.

## Configuration and secrets

- `config/.env` (PHP and tooling) and `web/.env.local` are **gitignored** — never commit them. Copy from the `*.example` files.
- Provider keys (Anthropic, OpenRouter, …) live in the runner's own environment file (`docs/deploy/runner.env.example`), never in `config/.env`.
- Tenant secrets stored by the platform are encrypted with `SECRETS_KEY`. **Never replace it; rotation adds a version.**
- API keys only: no consumer-account logins are used for model access.
- Session transcripts (`claude-log/`) and `.env` backups are gitignored because they may contain pasted secrets.

## Roles and access

Exactly three business roles: `super_admin`, `dept_admin`, `user`. “External” is a flag on a user, not a role, and agents are always
`user`. A dept-admin administers only the departments they are flagged admin of or manage; elsewhere they are an ordinary user.
Only super-admins may use the `os.` face, decide approvals, and grant application access. Application users are not OS users.

## Agents

- **Harness per model** behind one runner interface. Built: `hermes`, `claude_agent_sdk`, and `system_one` (JEV-driven playbooks).
- **Five standing departments** in every tenant: Front Office (root of the org chart), HR, Accounting, Audit, IT.
- **Every application has an expert agent and every department a lead**, proposed by the platform and confirmed by a person.
- **Prompt ledger:** every model call is recorded with full context, response, tokens, latency and cost, and linked to the activity log.
- **Evals** run on demand or on schedule by an Auditor agent, are graded by JEV, and *advise* — they never block an agent. An eval run may not change anything.
- **Approvals** pause sensitive actions until a super-admin decides.
- **Skills** are versioned in a library (AI Ops → Skills) and read by agents through the records MCP.

## Integrating an application

An application from us:

1. lives in its own repository (installed by convention at `/srv/apps/<catalog_key>`), with its own PostgreSQL database, PHP/HTMX UI and MCP servers;
2. declares itself in a `maludb-os.json` manifest — roles and rights, scopes, shared and consumed tools, its expert agent;
3. accepts the kernel's **hand-off token** at its `sso_path` and verifies the signed claims;
4. reads people and access through the **directory API**, and receives a change feed;
5. runs its agents through the kernel's **chat endpoint** and never holds a model key;
6. exports token accounting through the kernel's **ledger period export**.

The installer is `bin/app_install.php plan|apply <repository>` (`plan` is read-only; `apply` is run by a person with root) and
`bin/install_default_applications.sh`, which installs the three default applications straight from GitHub. The contract is documented
in `docs/business-os-integration.md` and the `kernel-*.md` specs in `docs/build-specs/`.

### Repositories

Everything that is part of the OS lives under [github.com/maludb](https://github.com/maludb). The kernel is `maludb-os-core`;
every application is `maludb-os-<catalog_key>`, where `catalog_key` is the key its `maludb-os.json` declares and the directory it
installs to under `/srv/apps/`.

| Repository | What it is | Installed |
|---|---|---|
| [maludb-os-core](https://github.com/maludb/maludb-os-core) | The kernel (this repository) | always |
| [maludb-os-integration](https://github.com/maludb/maludb-os-integration) | The Claude Code plugin that fits an application to the kernel (`os-integration`, `os-adopt`, `os-install`) | — |
| [maludb-os-hr](https://github.com/maludb/maludb-os-hr) | HR — employment, pay records, leave, reviews; the one application that changes the directory | by default |
| [maludb-os-projects](https://github.com/maludb/maludb-os-projects) | Projects — backlogs, sprints, kanban; agents as assignees | by default |
| [maludb-os-helpdesk](https://github.com/maludb/maludb-os-helpdesk) | Help Desk — issues and requests from employees, customers and agents, worked to resolution | by default |
| [maludb-os-cidery](https://github.com/maludb/maludb-os-cidery) | Cidery — inventory, receiving and production for a cidery; an `htmx-php-builder` product adopted onto the kernel behind `OS_ENABLED` | on request |
| [maludb-os-txtschedules](https://github.com/maludb/maludb-os-txtschedules) | txtSchedules — restaurant staff scheduling | on request |
| [maludb-os-reservations](https://github.com/maludb/maludb-os-reservations) | Reservations (ZozoCal) — an existing product adopted onto the kernel (branch `os-adoption`) | on request |
| [maludb-os-htmx-php-guidelines](https://github.com/maludb/maludb-os-htmx-php-guidelines) | The Claude Code plugin (`htmx-php-builder`) that builds these applications — the stack, design system, PHP patterns and the OS-ready conventions | — |
| [maludb-os-gl](https://github.com/maludb/maludb-os-gl) | General Ledger — chart of accounts, journal, financial statements, AR, AP, cash management; the kernel's AI statement becomes bills (planned 2026-10-04, private) | on request |
| [maludb-os-consultant-tracking](https://github.com/maludb/maludb-os-consultant-tracking) | Consultant Tracking — professional-services time, expenses with receipts, AI hosting and model-usage pass-through, T&M and fixed-bid invoicing for a technical/AI consultancy; the accounting system reads it through K7 (planned 2026-10-04, private; catalog key `consultant_tracking`, DNS label `consulting`) | on request |
| [maludb-os-spaces](https://github.com/maludb/maludb-os-spaces) | Spaces — the collaborative workspace: spaces holding pages built from Notion's block model, a wiki with verification, databases with views, and channels with threads (Notion + Slack), with OS agents as members, mentioned and DM'd (planned and approved 2026-10-05; the fourth default) | by default |
| [maludb-os-knowledge](https://github.com/maludb/maludb-os-knowledge) | Knowledge — knowledge bases built from uploaded documents, guidelines and focus areas into a MaluDB knowledge graph, asked through an ask-me-anything window, a token API with keys and the kernel's agents; siblings read it through K7 (planned 2026-10-05, private; awaiting the owner's decisions) | on request |

## Development conventions

- **Read the docs first, in order:** `docs/business-os-integration.md`, `docs/business-os-requirements.md`, `docs/business-os-build-plan.md`. `CLAUDE.md` holds the standing project rules for AI-assisted work.
- **Every slice ships whole:** screens (no modals, full-page create/edit, works at 375px), handlers, activity logging, action-manifest entries and MCP tools.
- **Every state-changing handler:** `require_post()`, `verify_csrf()`, an authorization check, and `log_activity()`.
- **Reads** go through whitelist presenters in `app/features/<feature>/present.php`; never serialize raw rows.
- **Writes** report through `emit_action_status()` and `json_mode_finish()`.
- **No new PHP templates.** `app/views/` holds only mail bodies.
- **Public (no-login) pages** go on the public list in `web/middleware.ts`, never on Apache's allow-list.
- **Commit style:** one commit per finished step, on `main`.

## Documentation map

| Topic | Where |
|---|---|
| Kernel boundary and integration design | `docs/business-os-integration.md` |
| Requirements and build plan | `docs/business-os-requirements.md`, `docs/business-os-build-plan.md` |
| Per-feature specs and the slice template | `docs/build-specs/` (start at `README.md`, `react-slice-template.md`) |
| Kernel specs (sign-on, directory API, ledger, chat, scopes, roles, services) | `docs/build-specs/kernel-*.md` |
| Agent runtime, evals, skills | `docs/build-specs/agent-*.md`, `eval-*.md`, `system-one-harness.md`, `skill-library.md` |
| React migration record | `docs/react-migration-plan.md`, `docs/react-cutover-runbook.md` |
| Deployment files | `docs/deploy/` |
| Action manifest and MCP tool surface | `docs/business-os-action-manifest.md`, `docs/business-os-mcp-tool-surface.md` |

## Security

Please report vulnerabilities privately to the maintainers rather than opening a public issue. Never post credentials, tokens or
tenant data in issues or pull requests.

## License

[MIT](./LICENSE) © 2026 ehonour
