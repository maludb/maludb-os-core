# Business OS — CLAUDE.md

This repo is a fork of members-vip (a cert-study membership app) being converted into a
**Business OS**: a hosted business platform + desktop companion with an AI agent workforce.
The original app still runs at 10.120.0.170:/var/www (SSH creds in `.env`, never committed;
remote shells land in /home/maludb — always `cd /var/www` in remote commands).

## The OS is a kernel (decided 2026-09-22) — read this before anything else

The owner's decision after reviewing the built platform: **too many business applications were built into
the operating system.** The Business OS manages the AI agents, the super-admins who administer them
directly, and single sign-on — and runs no business application. Three names per company:
`www.<domain>` (public site, out of scope), `os.<domain>` (this platform: estate, departments, the
directory of people, Agent HR, applications, approvals, AI Ops, skills, settings — **super-admins and
agents only**), `app.<domain>` (sign-in + the launcher, for every human), and one name per application
(`hr.<domain>`, `reservations.<domain>`: separate Apache/PostgreSQL/PHP/HTMX apps, own DB, own MCP, own
expert, signed in by the kernel with a 60-second single-use **hand-off token**). **Every business module
below was removed, tables and all, on 2026-09-22 (db/133, A1 done)**; the Contacts/CRM exemplar goes with
CRM and **Applications is the exemplar slice**; the kernel's command bar and assistant service go; the
desktop companion is deferred; the Accounting agents are kept and re-versioned around the token ledger
and its **period export** (the kernel keeps token accounting with dollar costs only — no books; the
accounting system is an optional application). **HR is the first application** — not before the kernel's
sign-on (A3) and directory API (A4) exist. The design and the exact inventory of the cut:
`docs/business-os-integration.md` (live: https://claude.ai/code/artifact/7f923c54-5189-41ee-bdb0-95cd6610ccfa).
The application-side contract: the `maludb-os-integration` plugin **0.7.0** (`~/maludb-os-integration`, skills `os-integration`, `os-adopt`, `os-install`;
reference `scoped-applications.md` for multi-site and multi-department applications; **`shared-schema.md` (2026-10-05): the estate has ONE data model in many
databases** — Phase 1 surveys the siblings' `db/*.sql`, reuses the canonical table verbatim (extend by appending), reads data another application owns through
K7 instead of copying it, and every table is still created by the application's own migrations because installations hold different applications;
`htmx-php-builder` 0.7.0 applies it in `new-app` Phase 1 and `os-application`). **`htmx-php-builder` 0.8.0 (2026-10-09): the record picker** — a form chooses a RECORD (a supplier, an item, a person: anything with its own list screen) through one searchable modal, never a `<select>` of every row; the second exemption to the no-modal rule beside `hx-confirm`; `design-system/references/record-picker.md`, PHP in `examples/php/record-picker/`; the plan `~/htmx-php-builder/docs/record-picker-plan.md` (D1–D12 approved); built in ProcessCore's purchase order (the exemplar, 99d978e) and the cidery (c4e1c64), the other applications retrofitted by workers the same day. Build order: build plan phase 7, Part A
(A1 the cut → A2 hosts → A3 sign-on → A4 directory API → A5 ledger → A6 chat endpoint → A7 owed items → A8 HR).
**A8 HR lives in its own repository, `/srv/apps/hr`** (its own CLAUDE.md; design `docs/hr-design.md` there; **complete 2026-09-23**:
application 39, catalog `hr` kind `ours`, vhost `hr.subello.com` + :8180, endpoints 15–18, expert agent 48, `mcp/registries/hr.json`
here). Applications from us install at `/srv/apps/<catalog_key>`; nothing of HR is built in this repo.
**Repositories (organized 2026-10-04):** everything of the OS is under `github.com/maludb` — the kernel `maludb-os-core` (this repo, public), the plugin
`maludb-os-integration` (public), and one repository per application named `maludb-os-<catalog_key>`: `maludb-os-hr`, `maludb-os-projects`,
`maludb-os-helpdesk` (the three defaults, public), `maludb-os-txtschedules` and `maludb-os-reservations` (ZozoCal, adopted; its OS work is the
`os-adoption` branch) (private); **`maludb-os-pro-appointments` (Pro Appointments — ZozoCal-Professional ADOPTED 2026-10-09, private, clone `/srv/apps/pro_appointments`; the `os-adopt` route re-fitted from the Reservations adoption: a clean history, S1 and S3–S13 fixed, the adapter, `app_roles` on `html/api/mcp/kernel.php`, the switcher; 124 proof checks, installer `plan` clean; catalog key `pro_appointments`, label `appointments`; every decision taken as recommended and awaiting the owner — `docs/os-adoption.md` there; owes the rest of the contract: activity memory, MCP servers for agents, JSON mode, an expert);** `maludb-os-cidery` (adopted, another session) and `maludb-os-gl` (**General Ledger — planned 2026-10-04**, private,
`/srv/apps/gl/docs/gl-design.md`: the optional accounting application — chart, journal, statements, AR, AP, cash tie-out; the kernel's AI
statement becomes one bill per provider; **Phase 0 complete 2026-10-04** — schema db/001–016 proven by 151 checks, the kit by 15; K13 built here as db/167; **Phase 1 approved, Phase 2 (the shell, 251 checks), slice 1 (the chart and fiscal time, 219) and slice 2 (journal core, THE EXEMPLAR, 161) built 2026-10-05 — the handoff point: slices 3–9 are Sonnet 5.5's**; owes K14 an installer guard (`grant_standing_departments: false` in maludb-os.json → `plan` warns on `--grant-standing-departments`), K15 the Accounting agents' grants, H1 an HR share); `maludb-os-consultant-tracking` (**Consultant Tracking — planned 2026-10-04**, private, catalog key `consultant_tracking` — the installer admits only `[a-z0-9_]` — DNS label `consulting`, clone `/srv/apps/consultant_tracking`, plan `docs/consultant-tracking-design.md` there: professional-services time, expenses with receipts, AI usage and hosting pass-through allocated from the kernel's AI statement, T&M and fixed-bid invoicing; it bills and never keeps books — the ledger and HR read its K7 shares; owes K17 a catalog seed, K18 the `people` reader rule, K19 a cost tag on runs; G1/H2/P1 owed by GL, HR, Projects; ports 8185/8831/8832; **plan approved 2026-10-04, D1–D16 all the recommendations; D16 revised 2026-10-05 to the GL division: K17 (db/168), Phase 0 (171 + 15 checks), Phase 1 (approved), Phase 2 (247), slice 1 (259), slice 2 (173) and slice 3 time THE EXEMPLAR (195) built and proven by the planning model 2026-10-05 — the handoff point; slices 4–9 built by Sonnet 5.5 workers the same day (223, 331, 407, 411, 379, 258 checks) — PHASE 3 COMPLETE, 78/78 screens, 108/108 actions; the planning model closed schema defects the workers found in db/016–018; Phase 4 (the records MCP with 48 tools incl. the six shares and app_roles, the activity MCP with 6; the registry wrapper validated with this kernel's application_actions.py; 583 checks) built by a Sonnet worker the same day; `bin/app_install.php plan` reads it clean (60 steps, two agents proposed) — Phase 5 is the owner's: apply, DNS/TLS, the hires, the end-to-end proof**). `maludb-os-spaces` (**Spaces — planned and APPROVED 2026-10-05, D1–D16 all the recommendations**, public, the FOURTH DEFAULT install (K21 done), catalog key `spaces`, DNS label `spaces`, clone `/srv/apps/spaces`, plan `docs/spaces-design.md` there: Notion and Slack in one — spaces, pages from Notion's block model verbatim, a wiki with verification, databases with relations/rollups and six views, channels/DMs with threads; OS agents as members, a mention or DM = one chat-endpoint turn as that agent; expert + Librarian; proposed as the fourth default install; K20 built here as db/169 and K21 in `bin/install_default_applications.sh` (2026-10-05); owes K22 chat attachments/streaming, K23 the kernel's agent inbox as DMs; HD1/P2 sibling cards; ports 8186/8833/8834; **Phase 0 complete 2026-10-05 (db/001–017, 281 checks; the kit 18) and Phase 1 written the same day (57 + 6 tools, 58 screens, 118 actions, 32 approvals, ten specs) — APPROVED 2026-10-05; Phase 2 the shell built and proven the same day (312 checks, php -S and Apache); slices 1 (226), 2 (271), 3 the block editor THE FIRST EXEMPLAR (232) and 4 channels THE SECOND EXEMPLAR (238) built and proven 2026-10-05 — the handoff point; slices 5–9 built by Sonnet 5.5 workers 2026-10-05/06 (367, 294, 244, 342, 278 checks; db/019–023 fix schema defects; the planning model decided their open questions) — PHASE 3 COMPLETE 2026-10-06, 58/58 screens, 118/118 actions; Phase 4 BUILT by a Sonnet worker 2026-10-06 (records MCP 62 tools incl. app_roles and the two shares pages_index/page_markdown over a signed loopback door, activity MCP 6, the registry wrapper validated by the kernel's own loader, 443 checks); **Phase 5 APPLIED by the owner 2026-10-06: application 61, `subello_spaces`, 8 units, 4 roles, registry 118, the admin and five department grants, agents 68 (expert) and 69 (the librarian — owes a rename in Agent HR and its Monday duty); the public page's `auth_kind` is `none` (the kernel admits no `token`); owed: DNS/TLS, `#spaces-admin`, the end-to-end proof of design §10 (pushed to GitHub 2026-10-07)**; **K26 built 2026-10-06 (db/173): the kernel sends a K7 provider `X-OS-Consumer` and `X-OS-Consumer-Agent` and strips a consumer-supplied `as_agent`; Spaces' shares read the header now;** slices 5–9 and Phases 4–5 Sonnet 5.5's**). `maludb-os-knowledge` (**Knowledge — planned and approved 2026-10-05; a GENERAL product (owner: the content does not matter — the fire codes of Chicago and Cook County were only an example for development and specs; licences are not a gate)**, private, catalog key `knowledge`, DNS label `knowledge`, clone `/srv/apps/knowledge`, plan `docs/knowledge-design.md` there: upload documents, get a knowledge base you can ask — a source is cut deterministically into **sections** (the unit of citation, one MaluDB document each), five typed guidelines (extraction, vocabulary, exclusion, answering, audience) compile into the base's namespace in a **MaluDB memory database of its own**, focus areas are MaluDB projects, and the base is asked through an ask-me-anything window that cites the exact words it relies on (the engine verifies every quote and withholds what it cannot) or declines, a token API with keys per base, the kernel's agents and K7 shares (`ask`, `search`, `bases_index`) for bases shared with a sibling; expert + Archivist; on request, not a default; **K25 (db/170) and K24 (db/171) built here; M1 (`POST /v1/memory/answer`) built in the MaluDB API on branch `feat/memory-answer-m1` awaiting the owner's merge; Phase 0 complete (schema db/001–015, engine, sectioner, kit); Phase 1 APPROVED by the owner 2026-10-06; Phase 2 the shell BUILT and proven the same day (`tests/phase2/run.sh`: 163 checks under php -S, 165 under a real Apache); NEXT slice 1, then the exemplars (slice 2 sectioning, slice 5 ask) by the planning model; K26 built in the kernel 2026-10-06 (db/173: the headers and `shares[].timeout_seconds`, Knowledge's `ask`/`search` declare 60)**; the laws-and-codes design is the Extended structured-documents module (`docs/structured-documents-extended.md` there); ports 8187/8835/8836). `maludb-os-inventory` (**Inventory — planned and APPROVED 2026-10-05, D1–D16: the minimum connectors (D7), no invoice document (D10), the Buyer set in config (D12), the Spaces division (D16), the rest the recommendations**, private, catalog key `inventory`, DNS label `inventory`, clone `/srv/apps/inventory`, plan `docs/inventory-design.md` there: sales inventory for a retailer that holds some stock and drop-ships the rest, modelled on mattress retail — a catalog in Shopify's and GS1's words (product → variants with SKU, GTIN, MPN and every seller's identifier; bundles; retail/MAP/cost with history), own stock by location on a transaction ledger with balances by trigger, **sources** (v1 connectors, D7: `shopify` public `products.json`, `woocommerce` Store API, `jsonld`, `feed` CSV/XLSX over HTTPS/SFTP, `manual`; Extended on the same interface: `ebay`, `amazon` PA-API as a reference only, `walmart`, `inventory_feed` = another installation of itself; EDI 846/850) read under a crawl policy — robots, one request a second, an honest user-agent, blocked means blocked, never a login/captcha/proxy — with every offer **snapshotted on change** and matched to the catalog by GTIN → supplier SKU → MPN+size → marketplace id → a person → an accepted proposal, never across sizes; **Find** (own stock by location, supplier offers ranked by cost and lead time, references); orders with a fulfilment choice per line (stock or drop-ship), a drafted PO per supplier placed by a person (`money_out` pauses agents), the supplier's secure link (acknowledge, decline, tracking) and the customer's; payments recorded, no books; five roles with cost as the only wall, scope none; expert + Stock Buyer (06:30 morning note); five K7 shares for the ledger (G2), Help Desk (HD3), Spaces (S1); K27 built here as db/172 (the catalog row, category check widened with `inventory`; `APPLICATION_CATEGORIES` widened); owes K28 texts to non-members; **Phase 0 complete 2026-10-05 (db/001–015, 69 tables, 422 checks; the kit 42; the five connectors against fixtures 511; the installer's plan clean; the survey: thirteen Shopify brands throttle products.json for a non-browser agent, every one has an open sitemap — jsonld over sitemaps is the realistic door; Layla's Woo Store API open) ; **Phase 1 written the same day (83 + 7 tools, 108 screens, 132 actions, 26 approvals, ten slice specs each manifest row claimed once by 52 checks, db/016–017 → schema proof 517) — APPROVED by the owner 2026-10-09 and the repository pushed; PHASE 2 THE SHELL BUILT and proven the same day** (`tests/phase2/run.sh` 327 checks under php -S, 330 under Apache: the menu of nine groups gated by rights, the tab bar, the bell, the switcher, the command bar through the kernel, My settings / Notifications / Tokens / My trail, the attachment door, 38 placeholders; the installer's plan clean, 57 steps; **a kit defect found and fixed there — `mirror_apply_member()` blanked job title, phone and time zone on every hand-off until the feed's next pass; Spaces and GL carry the same lines and owe the fix**; NEXT slices 1–4 by the planning model, then the handoff); found: the kernel's `application_endpoints.auth_kind` admits no `token` (Spaces' manifest declares it and will be refused at apply), an approval binds only once the registry exists, the hire script grants shipped agents by capability; ports 8188/8837/8838, scratch 8601–8607; the Spaces division with slice 3 sources/connectors/matching as the exemplar). `maludb-os-processcore` (**ProcessCore — planned and APPROVED 2026-10-08, D1–D19 all the recommendations; BUILD ORDER STEPS 0–8 COMPLETE the same day — step 9 the install is the owner's**, private, catalog key `processcore`, DNS label `processcore`, clone `/srv/apps/processcore`, plan `docs/processcore-design.md` there, the record `docs/processcore-progress.md`: a base manufacturing application for converting processes with the cidery's flow — receive lots → production orders → **runs** on equipment (the unit of execution and the exemplar, `docs/build-specs/runs.md`: lots in with one primary, lots out as product / co-product / scrap / rework, consumables explicit or backflushed, readings graded by the product's specs, fit warnings that never stop, posting that creates the output lots by class sequence with the item's → the primary input's → the row's attributes, the ledger with weight, lineage by weight fraction, yield) → packaging → release → shipments with the BOL and packing list → period reports over a generic line map (D7) — and the beverage layers taken off (batches/vessels, TTB, tax state, kegs, tank view); **industry is data, not code**: a profile = `db/profiles/<name>/seed.sql` + skills + `PROCESS_PROFILE`, steel the default (coils by heat with mill test reports, slit / cut to length / shear / blank / level / pack, pieces AND weight everywhere, a typed attribute dictionary per item class, theoretical weight and the gauge table, equipment capabilities as warnings); a fork (`maludb-os-<key>`) for an industry that needs screens; the schema db/002–016 + 020 rewritten in place (D2); the cut, the foundation, the runs exemplar and period reports by the planning model, the six surviving feature groups and the surfaces (records MCP 86 tools incl. 22 resolvers and the four K7 shares `shipments_by_period` / `inventory_valuation` / `open_orders` / `finished_stock_available`, activity MCP 12, actions server 72 tools, docs/04–05, registry 137 actions 133 built, expert + Shift Planner with a 06:30 duty, four skills) by Sonnet 5.5 workers in worktrees, merged; **`scripts/prove-all.sh` rebuilds `processcore_dev` and runs thirteen proofs**; found and fixed there: PHP's clock follows the organization's time zone setting as the database does; K29 BUILT in the kernel (db/174, category `manufacturing`); **the kernel's `bin/hire_application_agent.php` now reads `agents[].duty`/`duties[]` from a manifest (2026-10-08) — the Inventory Buyer's and GL Bookkeeper's declared duties were hired with none before**; ports 8189/8839/8840; **NEXT (the owner's): `bin/app_install.php plan` reads clean (24 steps) → `apply`, DNS/TLS, hire `expert` and `shift_planner`, the end-to-end proof; the application's commits since step 3 are not pushed**). `maludb-os-doccloud` (**DocCloud — planned and APPROVED 2026-10-09, D1–D16 and three open questions all the recommendations; K31 built as db/176; NEXT Phase 0**, private, catalog key `doccloud`, DNS label `doccloud`, clone `/srv/apps/doccloud`, plan `docs/doccloud-design.md` there: what Dropbox, Box and Nextcloud are to a business — libraries (personal, group, shared) of folders and documents with versions and de-duplicated blobs, uploads in resumable 8 MB parts; **groups** (the estate's first: custom or a department mirror) and visibility by ownership + the library's base level + inherited additive access rows + restrict, four levels (view, upload, edit, manage; an `upload` holder sees only their own), guests by name, links and file requests (expiring, paused for agents), the admin everything logged; tags (managed + free, auto-tag rules); keyword search on Spaces' canonical `search_index`, visibility as a per-row gate (the scale rule: no visible set per statement); **a dedicated document graph in a MaluDB memory database of its own** (`<tenant>_doccloud_memory`) with three policies — `none` / `index` (default, `remember`, no model) / `full` (queued model extraction under a monthly text budget via K24) — every engine result judged by the wall; **the default AMA never searches document content** (the owner's rule; a cited answer over a folder is Knowledge's through K7); expert + Filing Clerk (Monday 06:50); four K7 shares `folders_index` / `documents_index` / `document_text` / `document_link`; K31 the catalog row built (db/176, category `storage`), owes M10 a `document_ids[]` pre-filter and M11 an async ingest route upstream; ports 8190/8841/8842, scratch 8701–8707; the Spaces division — slice 2 uploads and slice 5 the graph the exemplars). `maludb-os-sales-crm` (**Sales CRM — planned and APPROVED 2026-10-09, D1–D16 and (a)–(c) all the recommendations; K32 BUILT (db/177), PHASE 0 COMPLETE and PHASE 1 WRITTEN the same day — schema db/001–017 proven by 173 checks, the kit by 71; the tool surface (72 + 7 tools, five shares), the action manifest (84 screens, 121 actions, 33 paused) and the registry, three build specs, ten slice specs (`deals-board` and `activities-reminders` the exemplars), 55 consistency checks green; APPROVED BY THE OWNER as written the same day — Phase 2 the shell in progress, then slices 1–4, the handoff to Sonnet 5.5**, private, catalog key `sales_crm` (the installer admits only `[a-z0-9_]`; the repository keeps the owner's hyphenated name), DNS label `crm`, clone `/srv/apps/sales_crm`, plan `docs/sales-crm-design.md` there: prospecting and CRM — **leads** converted into account + contact (+ deal), Salesforce's way; **accounts** and **contacts** NEW CANONICAL tables (the GL's `customers` is the billing party, read through K7 once a deal is won); several **pipelines** with stages carrying probability, rotting days and required fields; **deals** on a **kanban board** in Projects' shape (the columns partial polled, SortableJS the enhancement over `POST /deals/{deal}/move`, Pipedrive's four next-activity colours, column totals, Won/Lost drop targets) with the list and the forecast as views of the same query; ONE **activities** table (call/email/meeting/task/deadline/lunch, NEW CANONICAL) with the **reminder** as its columns (`remind_at`, `reminded_at`, `snoozed_until`; the worker delivers in-app + email + opt-in K6 text; done asks for the next step) and a **server-rendered calendar** (month/week/day/agenda as the cidery's schedule; `who=` me/colleague/team; txtSchedules' `calendar_feeds` for the ICS); email logged by a BCC mailbox at MaluMail on Help Desk's `mailboxes`/`inbound_email`/`outbound_email` and sent with templates and merge fields; campaigns, static and smart lists, bulk actions, CSV import with rollback, duplicates and merge; nine reports; open visibility by default with a manager's restriction; five roles (Viewer, Sales Rep `user`, Marketing, Sales Manager, CRM admin); expert + Sales Assistant (weekdays 06:30 morning note, `next_step_propose`); five K7 shares `accounts_index` / `contacts_index` / `deals_won` / `deals_open_by_account` / `account_timeline_summary`; K32 the catalog row built (db/177, category `crm`, area Sales & Service), K28 texts to a contact; G3/G4, HD5, INV3, CT4, SP4 owed by the siblings; the plugin's `shared-schema.md` gains the new canonical tables; ports 8191/8843/8844, scratch 8801–8807; the Spaces division with slices 3 (the board) and 4 (the calendar) as the exemplars).  `maludb-os-shareholder-intelligence` (**Shareholder Intelligence — planned and APPROVED 2026-10-09, D1–D16 and (a)–(d) all the recommendations; K34 BUILT as db/178 the same day; PHASE 0 COMPLETE the same day — the kit (73 checks without a kernel), db/001–016 proven against the reference files anonymised (142 checks), `maludb-os.json`, the two agents, ten skills, `deploy/`, the installer's plan clean; the owner's 186 real files (two years of one issuer's SPR, NOBO, share-range and register files) confirmed the connectors — db/017, the proof 154 checks, THE REAL RECONCILIATION TIES (Cede on the agent's extract = the SPR total, four quarter-ends); NEXT Phase 1 for the owner's approval**, private, catalog key `shareholder_intelligence`, DNS label `shareholders`, clone `/srv/apps/shareholder_intelligence`, plan `docs/shareholder-intelligence-design.md` there: shareholder surveillance for a publicly traded company (or an advisory firm serving several, each issuer restrictable to named members) — the weekly DTC **Security Position Report**, Broadridge's **NOBO list** and the transfer agent's **register** UPLOADED (never fetched), parsed by vendor connectors against their own control totals (the owner's KineticIntel2 fixtures: the weekly participant grid, the NOBO job file's seven name-and-address lines and CLIENTS section, STC's position detail), one row per participant per date, holders matched across lists by a scoring matcher with proposals (≥0.95 auto, logged), the OBO remainder derived, a dated **reconciliation** (outstanding = Cede + DRS + certificated + plan; Cede = the SPR total) whose failure is a data defect before anything else; the public record FETCHED through Inventory's `sources` model under a declared user-agent and policy — EDGAR (the index, the XBRL cover share count, full-text watch for ATM/ELOC/convertible terms, 13F from the quarterly data sets, 13D/13G XML, Forms 3/4/5 and 144 XML, filing text kept for Knowledge), FINRA short interest (revisions as rows) and daily off-exchange short volume (with FINRA's caveat), the SEC's fails-to-deliver, the three Reg SHO threshold lists, DTCC's participant directory (seeded classes retail / prime / market maker / lending), prices (stooq keyless by default); cost to borrow typed until a licensed key; Form SHO and SLATE tables created empty for 2028; **nineteen signal rules as SQL functions, each with an evidence class (established / contested / lore / data defect) printed on the signal**, a person disposes with a reason and an explaining event, `signal_outcomes` for the Auditor; four roles (Viewer never sees a natural person's name — the PII wall in SQL; Analyst `user`; IR Manager; SI admin); nothing leaves the business (Reg FD a wall); expert + Surveillance Analyst (market days 07:00 ET morning note, proposals, the Friday pack as a draft); five K7 shares `filings_index` / `filing_text` / `ownership_summary` / `signals_open` / `events_index`; owes K34 the catalog row with category `investor_relations` widened into BOTH checks (db/178), K35 an installer warning on a settings value required at install (the EDGAR user-agent); KN2 owed by Knowledge; ports 8192/8845/8846, scratch 8901–8907; the Spaces division with slice 2 (the SPR) and slice 7 (the signals) as the exemplars; the research of 2026-10-09 found that no open-source parser of an SPR or a NOBO list exists and that the incumbents (Q4, Nasdaq IR Intelligence at ~$51–56k/yr for issuers ≥ $750M) are analyst retainers a small cap cannot buy). Local clones: `/srv/apps/<key>` (and `~/ZozoCal-Restaurant` for Reservations), each with `origin` on GitHub.
A new application from us gets a `maludb-os-<key>` repository in the org before anything else; the README's "Repositories" table is the list.
`maludb-os-cidery` joined 2026-10-04 (the cidery, an `htmx-php-builder` product adopted behind `OS_ENABLED`, public; local clone `/srv/apps/cidery`; **equipment scheduling APPROVED 2026-10-08, D1–D13 all the recommendations** — `docs/15-equipment-schedule-plan.md` there; step 1 BUILT the same day: `docs/16-equipment-schedule-design.md` + `db/023_equipment_schedule.sql` (equipment beside vessels, one bookings table with the vessel plan migrated in and kept as a view, a double-booking switch in `client_settings.settings`), proven by `scripts/prove-equipment-schedule.sh` on a scratch copy of `subello_cidery` (39 checks); **steps 2–7 BUILT the same day** (`docs/17-equipment-schedule-progress.md`: Setup → Equipment, `/reservations`, the switch, the order's Equipment plan with cascades, `/schedule/` timeline + month replacing the Vessel calendar, the order page's inputs / processing time / outputs, records tools `equipment_schedule` + `equipment_free`, the actions, both registries rebuilt); APPLIED by the owner 2026-10-08 (db/023 on `subello_cidery`, the registry refreshed, `cidery-records-mcp` restarted); the expert's eleven new tool grants reconciled by `bin/hire_application_agent.php --app cidery --agent expert --reconcile-tools` — the option added that day: a re-run grants what the declaration names and the agent lacks, nothing revoked; cidery commits not pushed) and the
builder plugin moved to `maludb-os-htmx-php-guidelines` (plugin name still `htmx-php-builder`; local clone `~/htmx-php-builder`).
**The installer learned two manifest keys and the actions server two registry shapes on 2026-10-04 (the cidery adoption):** `database.provision`
(an idempotent script run instead of `db/*.sql`, with `DB_NAME`/`DB_RW_ROLE`/… in its environment, on every apply), `runtime.python` (`dir`, `venv`,
`requirements` when not `mcp/venv`); an action's endpoint may carry `{name}` path parameters (resolved like any entity, substituted into the path) and
`fixed` fields; a resolver may answer an envelope (`rows`/`candidates`/`results`/`items`). Plugin 0.6.0 documents them (`registration.md`).
**The installer's `mail` step (2026-10-05):** when a manifest names `MALUMAIL_API_KEY`, `MAIL_FROM`, `MAIL_FROM_NAME` (required or optional) and the
application's `config/.env` lacks them, `apply` writes them — the key from `~/.malumail` of the person installing (one line, the key, or env-style
lines; the sudo user's home, then `$HOME`, then the kernel owner's), else the kernel's own `config/.env`; the sender from the same, else
`no-reply@<domain>` and the application's name; in place when the line is there and empty; the key is never printed; no key anywhere is a note,
not a stop. Documented in the plugin's `os-install` skill and runbook and `registration.md` (`env.optional`).
**A9 Projects — the second application, complete 2026-09-28** (`/srv/apps/projects`, own repo and CLAUDE.md; design `docs/projects-design.md` there, §13 the
record): application 50, catalog `projects`, vhost `projects.subello.com` + :8181, MCP 8823/8824, endpoints 20–23, expert agent 59, Scrum Master agent 58,
`mcp/registries/projects.json` here. **The installer is built (C4/K3):** `bin/app_install.php plan|apply <repository>` — idempotent over every step of the
plugin's runbook; `plan` is read-only, `apply` is root and the owner's to run here (the classifier refuses it, like `deploy.sh`);
`bin/install_default_applications.sh` (K4) installs the defaults — HR, Projects, Help Desk, Spaces (K21, 2026-10-05) — from `https://github.com/maludb/maludb-os-<key>.git` unless given a directory, with agents hired and standing departments granted;
`bin/hire_application_agent.php --app <key> --agent <key>` hires any agent an application's `maludb-os.json` declares. **Fixed 2026-09-28:** the
actions server asked the approval hook about an action's KEY, not its log event, so no application action ever paused — `mcp/application_actions.py`. `hr.subello.com` resolves
to this host through `/etc/hosts` until the owner's DNS and proxy entries exist.
Sections below that describe the removed modules are history: the code, tables, tools and screens are gone.

## Read these first, in order

0. `docs/business-os-integration.md` — the kernel boundary and the integration design (2026-09-22).
1. `docs/business-os-requirements.md` — what we are building (vision, modules, agent HR,
   locations, agent runtime/telemetry/evals). Live review copy:
   https://claude.ai/code/artifact/ac7f0526-7abf-4734-b72e-390206c3f2aa
2. `docs/business-os-build-plan.md` — how and in what order (7 phases, model tiering,
   sprint state with done-notes). Live copy:
   https://claude.ai/code/artifact/a6a07be9-ecb1-4a8e-89ea-b940a2601d92

**Sync rule:** the requirements exist in three copies (live doc, `docs/business-os-requirements.md`,
`html/requirements.html`), the build plan in two (live doc, `docs/business-os-build-plan.md`) and the
integration design in two (live doc above, `docs/business-os-integration.md`).
Any edit to one must be applied to the others in the same session.

3. `docs/react-migration-plan.md` — how the front end moved from HTMX to one Next.js/React app
   (done: cut over 2026-09-19). Its "State" section is the record of what was built and why;
   `docs/react-cutover-runbook.md` is what the cut-over did and how to go back.

## React is the front end (cut over 2026-09-19)

- **The HTMX UI is gone.** One Next.js/React app at `web/` is the only UI; `app/views/` holds
  nothing but the mail bodies (`emails/`). No new PHP templates, ever. Feature work on
  build-plan phases 3–6 resumes on the React slice template (`docs/build-specs/react-slice-template.md`).
- **PHP is a localhost-only JSON API** (Apache `127.0.0.1:8080`). The public port serves the
  React app plus an allow-list of PHP endpoints that things other than our own pages call:
  `/api/v1/{health,me,members,org-graph}` (bearer tokens), the `/mcp/*` proxies,
  `/voice/retell-inbound.php`. Anything new that an outside system must
  reach has to be added to that list (`docs/deploy/apache-react-cutover.conf`).
- **Reads get one JSON branch** fed by whitelist presenters in `app/features/<f>/present.php` —
  never serialize raw rows or `view()` data. **Write handlers report through
  `emit_action_status()`** and `json_mode_finish()` (`app/http.php`) answers from that; a
  handler that does not report answers 501 to React. Handlers still call `view()` for HTML
  they used to send: a missing screen template renders as nothing (a missing MAIL template
  still throws). Removing those dead HTML tails is ordinary tidying, not a feature.
- **The browser never talks to PHP.** Server components read and server actions write through
  `web/lib/api.ts`. Handler paths never move — the actions MCP server POSTs to them, in JSON
  mode, and returns `record_id` with every create.
- **Agents' updates are partial**: an `*_update` tool sends the record and what changes;
  `app/partial_update.php` fills the rest from the base-table row (never a view — views hide
  columns). Only under an action token.
- **A page for someone with no account** (the signing page `/sign/<token>`; public forms next) lives in the bare
  `web/app/(app)/(public)/` layout, goes on the PUBLIC list in `web/middleware.ts` — never on Apache's allow-list —
  and reads token-checked PHP endpoints. Its POST is CSRF-protected exactly like the login form's: `apiPost()` opens
  PHP's anonymous session for the token. Exemplar: `html/sign/`, `docs/build-specs/signatures.md`.
- **Click-around (2026-09-27):** a name is a link, one route map (`web/lib/routes.ts`), every crumb links, a detail
  page shows "Back to …" from the `?back=` the opening link carried (`withBack(href, here)`, `PageHeader back=`), forms
  land on the record, an aggregate row opens its filtered list. Rules R1–R6 in the slice template; the sweep is
  `docs/build-specs/click-around.md` (step 0 built; steps 1–7 owed).
- Day-to-day screens keep the **Bootstrap 5.3 nxl look** (no modals, full-page create/edit,
  375px); the Agent View aesthetic is for `/` and `/view/...` only.
- Write-path regression: `mcp/smoke_actions.py run mcp/smoke/<n>.json` (it WRITES, as member 1;
  everything it makes is named `SMOKE <run>`). Way back from the cut-over: tag `pre-cutover`,
  `~/cutover-backups/`.
- **A handler born after the cut-over has no template**, and `json_mode_finish()` takes a
  refusal's words from the rendered page — so a template-less handler must say its own:
  `emit_action_status(false, ['errors' => …]); respond_invalid($errors);`. Exemplar:
  `html/team/invitations/` (the first post-cut-over slice, 2026-09-19).

## Two faces on one codebase (A2, 2026-09-22)

`OS_HOST` (`os.<domain>`, super-admins and agents) and `APP_HOST` (`app.<domain>`, everyone's sign-in,
`/launcher`, `/settings`) — `config/.env` for PHP, the `certstudy-web` drop-in for React; both empty = one face.
`web/lib/face.ts` + `web/middleware.ts` decide the face by Host and stamp `x-face`; `app/bootstrap.php`
`os_face_gate()` refuses a non-super-admin under the os name (403; `/api/v1/session.php` and `/logout.php`
exempt; action-token callers not judged). The bare name (`subello.com`, `www.`) is a static landing page, `/var/www/landing/` on its own
Apache vhost, a public page (what Subello is, sign in, the two doors); should the bare name or `www.` reach React, the middleware
serves the same file (`web/public/landing` → `/var/www/landing`) and never sends it to `app.` (2026-09-25); any other name
reaching React is sent to `app.` with a temporary 307 — never a cached 308. Spec:
`docs/build-specs/kernel-hosts.md`. DNS and proxy entries for the two names are the owner's.
**Default application (2026-09-26):** on the app face `/` → `/home` (`web/app/home/route.ts` ← `html/home.php`, `app_home_destination()`): a non-super-admin goes into their default application (`members.default_application_id`/`default_scope_id`, db/142, set by them or an admin via `member_default_application_set`), else the only one they hold, else `/launcher`; the launcher never redirects. Spec: `docs/build-specs/kernel-default-application.md`.
**Sign-on (A3, 2026-09-22):** `/launch/<id>` → `html/launch.php` mints the 60-second hand-off token and signed claims
(`app/auth.php`, `app/features/applications/sso.php`) and sends the browser to the application's `sso_path`; logout
posts the notice to `sso_logout_path`. Both paths are fields on the application (db/135). Spec: `docs/build-specs/kernel-sign-on.md`.
**Directory API (A4, 2026-09-22):** `html/api/v1/directory/*.php` on the internal port only, bearer = the application's
token (`mcp_access_tokens.scope = 'application'`, minted on the application's Overview, db/136); writes need
`X-Acting-Member` + `applications.directory_writes` and log with source `application`. Spec: `docs/build-specs/kernel-directory-api.md`.
**Ledger statement (A5, 2026-09-22):** `ai_periods` + `ai_usage_postings` = the month's statement (db/137; open until a super-admin
closes it; late calls flagged into the open month); screen `/ai/spend/statements`; exports = CSV/JSON download, `html/api/v1/ledger/periods.php`
(application token), MCP `ledger_period`. Document `os.ledger-period/1`. Spec: `docs/build-specs/kernel-ledger-statement.md`.
**Chat endpoint (A6, 2026-09-22):** `html/api/v1/agents/chat.php?agent=expert|<id>` (internal port, application token + `X-Acting-Member`)
runs ONE turn of the application's agent as a `chat` run on the runner and answers reply + actions; `GET ?run=` for a long one.
db/138. Spec: `docs/build-specs/kernel-chat-endpoint.md`.
**Scoped applications (C1, 2026-09-25):** an application declares `scope_kind` (none/location/department) and its own roles
(`application_roles`, each amounting to a capability, one `is_admin`); `application_scopes` = the sites (location kind `site`: a
place the business trades from, not a machine) or departments it serves; a grant names a scope and a role and goes to a member,
a department or a site's residents. Application users are not OS users — nothing by default. `sso_member_holding()` is the one
holding that claims (`role`, `scopes`, `scope`), the change feed (`scopes[]`, `access[]`) and run facts share. db/141. Spec:
`docs/build-specs/kernel-scoped-applications.md`. C2 done (plugin 0.3.0, 2026-09-25); C4 the installer built 2026-09-28 (`bin/app_install.php`). Next: C3 (ZozoCal adopted).
**Roles and rights (C5, 2026-09-27):** an application publishes its roles and the rights each gives through its records MCP
(`app_roles`, `os.app-roles/1`); the kernel reads them as itself (`mint_kernel_token()`, `application_roles_refresh`, super) — withdrawn,
never deleted; a grant gives a SET of roles (`application_access_roles`; `role_key` = the highest); granting, changing and revoking
application access is the super-admin's alone; claims, feed `access[]` and run facts carry `roles`/`rights`. db/145; plugin 0.4.0
`roles-and-rights.md`; HR db/012 (Employee, Manager, Payroll, HR Admin). Spec: `docs/build-specs/kernel-application-roles.md`.
**Services for applications (K6/K7, 2026-09-28, db/161; spec `docs/build-specs/kernel-app-services.md`, proof `bin/test_app_services.php`, plugin 0.5.0 `sms-and-reads.md`):**
`POST /api/v1/notify/sms.php` — an application texts a member (their verified phone, a grant, not opted out, 30/day) from the business's
notification number (`bin/notify_endpoint_set.php`; none set → 503 `no_sender`), sent by `certstudy-channels`; the application never holds a
Twilio key. `POST /api/v1/apps/read.php` — a consumer calls a provider's shared tool (`maludb-os.json` `shares[]` / `reads[]`, recorded by the
installer) only over a connection a super-admin approved (`bin/app_connection.php`), per-site tools only at a site both serve (the provider
gets its own `scope_id`). First share: Reservations' `covers_by_service`.
**The application switcher (K31, 2026-10-09; spec `docs/build-specs/kernel-app-switcher.md`, proof `bin/test_app_switcher.php`):** `GET /api/v1/apps/mine.php` (application token + `X-Acting-Member`) answers the launcher's own rows for the acting person with `key`, `launch_path` and `current`, `os_url` for a super-admin — the feed behind the Helpdesk button and the applications dropdown in every application's header (both plugins 0.9.0; the application appends the path to its `OS_LAUNCHER_URL`).
**Owed items (A7, 2026-09-22):** endpoints of an `ours` application attached with the run token; `html/api/v1/runs/facts.php`;
`mcp/registries/<app_key>.json` → tools on the Actions MCP with `html/approvals/hook.php` in front; `application_catalog.kind = 'ours'`
+ `application_catalog_save`; the Claude harness carries skills inline and as `--plugin-dir`. db/139. Spec: `docs/build-specs/kernel-owed-items.md`.

**Personal assistants and agent messaging (2026-09-27/28; spec `docs/build-specs/assistants-and-messaging.md`, live copy
https://claude.ai/code/artifact/188ca1ea-efb5-480b-a9af-48339339f946):** a person's assistant (`agent_profiles.principal_member_id`) is an
orchestrator that always delegates; the roster is a TREE (db/154: one parent per orchestrator, no cycles, depth ≤ 5; an assistant reaches only
its person's departments; `agent_runs.delegation_reason`); agents message along the tree (db/155, `message_send`, `inbox_read`, the runner's
message loop wakes a recipient; escalations to a person with an assistant go to the assistant); a person reaches their assistant on Telegram
and SMS (db/156, `certstudy-channels` worker, `/channels/twilio/sms` webhook) and by email to the assistant's own MaluMail mailbox
(`seamus@subello.com`; MaluMail's mailbox MCP — `malumail_mcp()` — reads and sends, the OS holds no mailbox password; in by the signed
webhook `/channels/malumail/mail` plus a once-a-minute poll of unread mail; replies thread); proposed leads (db/157,
`/agents/org`). Seamus (34) is Edward's. MaluMail's host is reached by ssh (credentials in `/home/maludb/.env` — read single keys, never
source it); production changes there need the owner's permission route.

## The fourteen modules (built 2026-09-19/20 — REMOVED by the cut, A1, 2026-09-22)

All fourteen once-stubbed modules were built and are now gone (db/133): Approvals, Time, Documents, Calendar, Helpdesk,
Shared inbox, Content, Inventory, People, Signatures, Portal & Forms, AI Ops, Reports, My Work.
**The record:** the done-notes in `docs/react-migration-plan.md` ("State") and its last section,
"The fourteen modules — what is owed to the owner" (every OPEN default, what is unbuilt, what
SMOKE data was left); one spec per module in `docs/build-specs/<module>.md`; the owner's twenty
answers in `docs/build-specs/stub-modules-decisions.md`. Rules that outlive the run:
- **Migrations are additive** (views: keep `WITH (security_barrier = true)`, append columns
  last, check grants afterwards). **A check in a view that depends only on the caller goes in a scalar subquery — `WHERE (SELECT app_is_super_admin()) OR …` — so it runs once per statement, never once per row; visibility functions are PL/pgSQL, one query each (db/160: the per-row chain cost the agent page a second).** Highest so far: db/178 (K34 — the Shareholder Intelligence catalog row under Finance, category `investor_relations` widened into BOTH checks and `APPLICATION_CATEGORIES`, 2026-10-09; 177 K32 — the Sales CRM catalog row, category `crm`, 2026-10-09; 176 K31 — the DocCloud catalog row, category `storage`, 2026-10-09; 175 K30 — `applications.category` admits `inventory` and `manufacturing`, 2026-10-09: K27/K29 widened only the catalog's check and the ProcessCore apply failed at register; 174 K29 — the ProcessCore catalog row, category `manufacturing`; 173 K26 — `application_shares.timeout_seconds`; the consumer's identity crosses to a K7 provider in the headers `X-OS-Consumer` / `X-OS-Consumer-Agent`, built 2026-10-06; 172 K27 — the Inventory catalog row and the category check widened with `inventory`; 171 K24 — an application's model credential at the ledger proxy; 170 K25 — the Knowledge catalog row; 169 K20 — the Spaces catalog row; 168 K17 — the Consultant Tracking catalog row; 167 the General Ledger catalog row; 162–166 the Claude subscription auth; 161 K6/K7 — applications text a member through the kernel and read another application's shared tool over a super-admin-approved connection; db/160 the per-row visibility functions in PL/pgSQL, one query each, and the ledger + runs views testing the caller once per statement — the agent page 1.6 s → 0.4 s, nothing about who sees what changed; 159 externals with a grant can launch — app_can_launch() + mcp_launcher_applications; 158 the Projects catalog entry; 157 lead proposals; 156 channels; 155 agent messages; 154 the assistant tree; 153 skill_kinds — a skill or a runbook, by name; 152 app_record_label — a record's name by kind for the activity trail; 151 click-around view ids — model_id on mcp_agents, the escalation recipient, department_ids on the team directory, the access scope's target; 150 the JEV prompt writer's evidence; 149 IT department, 146–148 system_one; TWO files are numbered 145 — application roles and rights, and system_one — both applied; 144 JEV evals, 143 nav, 142 default application, 141 scoped applications); db/133 was the kernel cut, NOT additive (130: the application inventory — `application_catalog`,
  business areas = `nav_groups`, an application's expert agent and application-scope skills; 131–132: Assets).
- **Smokes** (`mcp/smoke/*.json`) write real rows named `SMOKE <run>`, mail only to a FRESH
  `example.invalid` address per run (MaluMail suppresses one after its first bounce), never call
  a tool with an empty variable, never `save` a variable named `run` or `code`. **Never re-run
  `16-people.json` casually** — each run posts a real pay run to the ledger; `18-portal-forms`
  trips its own rate limit; `13-inbox`'s fixture sender is suppressed.
- **Public (no-login) pages** go in `web/middleware.ts`'s PUBLIC list, never the Apache
  allow-list — the browser still never talks to PHP; a token is the authority (`/sign`, `/f`).
- **`require_module()` admits any dept-admin** to a module's screens. Business-wide sensitive
  data (pay, prompts, books) uses `require_module_grant()` or the people rule instead.
- **Manifest parameter notes**: no comma or semicolon inside a parameter's parentheses, and no
  note after the last parameter — the registry builder turns either into a phantom parameter.
- My Work's screen and the `my_work` tool share ONE gatherer, the SQL function `app_my_work()`
  (db/118): a new module that gives people work adds a section there, nowhere else.

## Build discipline (non-negotiable)

- The htmx-php-builder plugin skills still govern PHP: `php-patterns` before any PHP,
  `php-session-auth` for auth, `mcp-servers` for MCP work, `chat-actions` for the command
  bar/action manifest. `design-system` governs the *look and rules* of React screens.
  `new-screen` and the HTMX response patterns retired with the HTMX UI — do not use them.
- **Checkpoint gate:** no new feature PHP until the user approves the complete schema +
  MCP tool surface + action manifest together (build plan, phase 1 / milestone M1).
- Every slice ships whole: screens (no modals, works at 375px) + handlers + activity logging
  + action-manifest entries + MCP tools for its questions. Every state-changing handler:
  `require_post()` + `verify_csrf()` + authorization + `log_activity()`.
- Worker-model slices replicate the Contacts/CRM exemplar from a build spec in
  `docs/build-specs/`; ambiguity stops the slice and escalates — never improvise.
- Schema changes are numbered migrations in `db/`, run in order as postgres.

## Environment facts (this server)

- Ubuntu, Apache with two vhosts in `/etc/apache2/sites-enabled/000-default.conf` (copy:
  `docs/deploy/apache-react-cutover.conf`): public `*:80` → the React app on :3000 + the PHP
  allow-list + the MCP reverse proxy; internal `127.0.0.1:8080` → PHP (`html/`). PHP 8.3,
  PostgreSQL 17. Outside proxy: `subello.com` → :80. (`agentview.subello.com` → :3000 was the
  staging name; retired 2026-09-19 — the web app redirects it to `subello.com` until its proxy
  entry is removed.)
  `certstudy-web` gets `API_BASE_URL=http://127.0.0.1:8080` from a systemd drop-in.
- App DB: `certstudy` (roles `app_rw`, `app_records_ro`, `app_activity_ro`). App secrets:
  `config/.env` (gitignored; group www-data must keep read access).
- (Retired 2026-09-22 with the cut: `certstudy-pdf`, `certstudy-inbox-poll`, `certstudy-assistant`.) Tenant secrets: `app/secrets.php` / `mcp/secrets_store.py`, key
  `SECRETS_KEY` in `config/.env` — never replace it, rotation ADDS a version.
- Services (systemd): `certstudy-records-mcp` (8811), `certstudy-activity-mcp` (8812),
  `certstudy-actions-mcp` (8813), `certstudy-memory-mcp` (8814), `certstudy-agent-runner` (8815/8816), all from `mcp/venv`;
  `certstudy-activity-ingest.timer` runs `mcp/activity_ingest.py` every minute.
- Cron: installed for `www-data` from `docs/deploy/crontab.example`.
- First organizer is member #1 (the project owner's account).

## MaluDB (activity memory) — how it actually works here

- MaluDB = `maludb_core` Postgres extension + the MaluDB API service
  (`maludb-api.service`, port 8000, code at `/home/maludb/maludb-python-api-server`).
- **The `maludb` database is only the extension's bootstrap — never a tenant.** Each tenant
  gets its own memory database: ours is `certstudy_memory` (schema+role `certstudy_mem`,
  executor access granted via `maludb_core.grant_memory_access`).
- API tokens are minted by proving the tenant's Postgres login: `POST /v1/tokens`.
  Our token + role creds live in `config/.env` (`MALUDB_*` keys).
- `mcp/activity_ingest.py` ships `activity_log` rows to `POST /v1/episodes` (kind `activity`),
  checkpointed in `activity_ingest_state` (db/016) under an advisory lock. Activity memory
  cannot be backfilled — never disable logging or the timer.

## Roles (revised 2026-09-17 — three, and only three)

- `members.business_role` ∈ `super_admin` | `dept_admin` | `user`. **External is a flag**
  (`members.is_external`), not a role: the accountant and portal customers are users whose
  reach is what is granted or shared. Agents are always `user` (constraint).
- A **dept-admin** administers the departments they are flagged admin of
  (`department_members.is_admin`) plus any they are the named manager of — inside those,
  `app_can_see()` is true with no module grant; **outside them they are an ordinary user**
  and need grants, so a no-department record is never open to every dept-admin.
- A human belongs to many departments; administering one does not imply administering
  another they merely belong to.
- Helpers: `app_is_super_admin()`, `app_is_dept_admin()`, `app_is_admin()`,
  `app_admin_department_ids()`, `app_is_admin_of(dept)`, `app_can_admin_member(member)`.
  `app_can_admin_member()` is the people rule (grants, tokens, contact details, time, leave).
- Doc vocabulary: gate `super` = super-admin only; `admin` = super-admin or dept-admin within
  their departments; `mod:x` = either of those, or a holder of module grant x.

## Agent decisions already made (don't relitigate)

- **2026-09-22, the kernel** (see the top of this file): super-admins only on `os.`; a house-signed hand-off
  token, no OIDC; HR owns employment and changes the directory through the kernel's directory API, never
  shared tables; the agents that run an application ship with it and are hired and run by the kernel — an
  application never holds a model key; its command bar runs its expert through the kernel's chat endpoint;
  approvals stay in the kernel, decided by super-admins; no money-out actions in applications; token
  accounting only, exported per period; the public website is out of scope.

- Agents run harness-per-model behind one agent-runner interface; harness registry maps
  model → SDK (Claude → Claude Agent SDK, OpenAI → OpenAI Agents SDK, local → OpenAI-compatible).
  **Two are built: `hermes` and `claude_agent_sdk`** (2026-09-20; the owner declined the other two,
  which have no agent hired on them). Hiring or activating onto an unbuilt harness is refused by
  name — `/health` on the runner says which exist. Adding one is a class, a renderer and a tuple
  entry: if it needs the platform to change, the interface was wrong.
- **API keys only** — no claude.ai/Pro/Max login in the product (Agent SDK terms require prior
  Anthropic approval for that). **One owner-authorised exception (2026-09-29, this install only, OFF by default):** a
  Claude Max login for agents on the **Claude Code harness only** (`model_registry.auth_mode = 'claude_subscription'`, the
  "… · Max plan" rows), behind `ALLOW_CLAUDE_SUBSCRIPTION=1` + the setup-token in the runner's env file, set by
  `docs/deploy/set-claude-subscription.sh`. The owner accepted the terms risk (spec `docs/build-specs/claude-subscription-auth.md`);
  the token never reaches an agent (the ledger proxy swaps it in); calls are ledgered as notional, never as spend; Hermes is NOT
  supported (it impersonates Claude Code) and no other tenant may use it. Never enable it elsewhere or by default.
- **Scheduled evals are allowed** (owner, 2026-09-27 — reverses the 2026-09-20 "on demand only, no schedules or alerts"):
  an **Auditor** agent (Audit department — audits agent work) runs eval schedules, samples real runs and checks the evidence;
  a **Sysadmin** agent (IT department) does ALL ongoing monitoring read-only (health, disk, backups, versions, redacted logs,
  guardrails) — the Installation agent only installs. Both are `system_one` agents: a new built-in harness for JEV-driven
  playbooks, shadow (report-only) first. Findings still ADVISE — nothing suspends or blocks an agent. BUILT 2026-09-27 (db/145–148,
  `mcp/agent_runner/system_one/`; Auditor = agent 49, Sysadmin = agent 50, both in shadow). Spec: `docs/build-specs/system-one-harness.md`.
- **The JEV prompt writer** (2026-09-27; `docs/build-specs/jev-prompt-writer.md`): agent 53, Audit, Claude harness, DRAFTS the
  system_one agents' JEV questions (trace/case checks, `LOG_QUESTIONS`, thresholds) — people apply them. Skills in the repo's
  `skills/` (TypeSafe's `typesafe-ai` vendored unchanged, `typesafe-jev-docs`, `jev-prompts-for-system-one`); `bin/hire_jev_prompt_writer.php`.
  db/150 `app_reads_judgement_evidence()` opens the judgement views to it alone (prompt ledger stays closed).
- **The skill library** (2026-09-27; `docs/build-specs/skill-library.md`): AI Ops → Skills (`/ai/skills`, super) creates skills and
  new versions (never an edit in place) and enables/disables versions (`skill_save`, `skill_set_enabled`); agents READ it through
  Records MCP `skill_library` / `skill_read` when granted — the Claude harness persona cuts a skill at 6,000 characters and has no file tool.
- **Evals are graded by JEV** (2026-09-26, db/144; `docs/build-specs/eval-jev-grader.md`): TypeSafe's System One model through
  OpenRouter (`typesafe/jev-1.13`, pinned), reached only through the ledger proxy's `/openrouter/api/v1/systemone` route with the
  eval run's judge key; a case's `checks` are typed questions (noul/choice/score) with pass rules; one call per trial plus an
  injection guard; unsure → the case awaits a person (excluded from the score); `rubric_llm` kept for what cannot be decomposed.
  `OPENROUTER_API_KEY` lives in `/etc/business-os/runner.env` with every provider key — never in `config/.env`.
- Every model call is recorded in the prompt ledger (full context, response, token counts,
  latency, cost, linked to activity_log). Evals are run ON DEMAND by a person — weighing a model or
  prompt change, or looking into a degradation — and they advise: a missing eval never blocks activation
  (2026-09-20; design input `docs/evaluation-engineering-production.md`). What is mandatory is the evidence
  evals will need: every model call in the ledger (the assistant's too, `mcp/ledger_writer.py`), every tool
  event (`agent_run_events`, db/122) and a person's verdict on a run (`run_verdicts`, db/124). A hire goes
  live on its own version 1 (db/096). **The eval runner is built** (2026-09-20, db/125–126): three trials a
  case, all must pass; a judge from another model family; the score covers only what has been graded. **An
  eval run may not change anything** — a write is recorded and never sent (`mcp/actions_server.py`), and the
  runner checks after every trial that nothing did. Specs: `docs/build-specs/eval-runner.md`, `eval-evidence.md`.
- **The estate** (2026-09-17): one location tree — building (the Proxmox host) → office (a VM
  where departments work and agents run) → desk (an enrolled desktop, where people and their
  agents work remotely). **Minimum platform = one office VM** (Postgres 17 + MaluDB + this
  platform) **plus one desk**; the building is optional and only recorded once a host carries
  several offices. That minimum is what the tenant provisioning script produces. **Applications** (accounting, calendar, CRM, mail, the platform
  itself) reside at a location, are owned by a department, expose endpoints that carry only a
  *reference* to a tenant secret, and are reachable only with a live access grant.
- **Five standing departments** exist in every tenant, seeded and undeletable (renameable):
  Front Office (leadership and front of house — CEO, COO, reception), HR (people and agents),
  Accounting (books + token-usage metering; the prompt ledger is the AI subledger, posted per
  period as an expense), Audit (the eval suites with their goals and thresholds, and the on-demand runs — no schedules or automatic alerts since 2026-09-20), and **IT** (added 2026-09-27, db/149: the machines, the platform and the applications
  on it — the Sysadmin watches, and the **application installation agent** "Installer" (agent 51, `claude_agent_sdk`, role `installer`,
  the plugin's three skills, tools on the kernel's own MCPs, registrations paused by policy for its approver) is hired there on install by
  `bin/hire_installation_agent.php` — it never holds a token or root: it plans, a person approves, it applies through the kernel and writes the files root must install). **The Front Office is the
  root of the org chart** (2026-09-18, db/074): every other department reports to it, a
  department saved with no parent is parented there by trigger, and it seeds at an onsite
  office — a department may work anywhere, but the front of the business sits on a machine we run.
- **23 built-in modules** (2026-09-17), each registered as its own application row at the
  Office: dashboard, CRM, sales, books (double-entry GL + bank reconciliation), expenses,
  projects, calendar, time, tickets, inbox (shared mailboxes), documents, signatures,
  inventory (products + stock + POs), people (employment, pay runs, leave), agent HR,
  content, portal & forms, reports & dashboards, estate, applications, AI ops, approvals,
  team & access. Module grant = what admits a member to a built-in application;
  `application_access` = what admits them to an external system. One permission system.
  **A 24th, Assets, was added 2026-09-21** (db/131–132, `docs/build-specs/assets.md`): one register,
  physical and technical (PostgreSQL 17 and MaluDB are seeded), every asset in a department and at a
  location. **Its URL is `/asset-register/`, never `/assets/`** — that is the static files directory.
  An asset grants nothing; depreciation is posted by a person, never on a schedule, and is proven
  only by `bin/test_asset_depreciation.php` (rolled back) — never by posting to the real books.
- **Lists of named things are cards** (the owner's preference, 2026-09-21): `web/components/kit/RecordCard.tsx`,
  rule in `docs/build-specs/react-slice-template.md`. Ledgers and line items stay tables.
- **The vision, as the owner stated it 2026-09-21** (requirements, "How a business gets it, and what it
  does first"; build plan phase 7 — nothing of it is built): for an SMB **or an independent AI agency**
  (own installation, may resell — every installation is always its own VM and database). An
  **installation agent we provide** installs on the business's server or ours and then monitors for the
  owner — **nothing leaves the server**. Setup order: machines → desktop → locations, departments and
  applications in any order → experts. **Three kinds of application**: built-in module (turn on), an application from us (a separate
  Apache/PHP/HTMX memory-first app the agent installs on the same server, own database + memory + MCP
  servers, ships its skills, **the platform signs people in**; each is its own Apache vhost at its own DNS name —
  by name, or by port behind the reverse proxy in front: `reservations.subello.com` → :81, `crm.subello.com`
  → :82 — with its MCP servers proxied under that name; the platform keeps the main name and :80), anyone else's (through MCP, provided a
  server exists). **Every application has an expert and every department a lead agent** (expert and
  orchestrator in one, reporting to the human manager) — **proposed by the platform, confirmed by a person
  in one click**; a missing one is a gap, never a refusal. Minimum platform stays one office VM plus one desk.
  **The integration contract for an application from us is the Claude Code plugin `maludb-os-integration`**
  (`~/maludb-os-integration`, its own repo; skill `os-integration`, four references written from this code
  2026-09-21). What the platform owes to honour it is build plan phase 7, item 7.
- Out of scope by decision: statutory payroll (tax tables, filing) and warehouse
  management/manufacturing (bins, lots, serials, BOMs).

## Useful commands

- **Fresh install on a new Ubuntu 24.04 host: `docs/install.md`** (written and proven 2026-10-06: all 154 migrations apply clean on
  an empty database; `bin/bootstrap_organizer.php` now makes a SUPER-ADMIN — before that day it made a plain user, which locked a fresh
  install out of `os.`; `config/.env.example` documents every key; `mcp/registries/*.json` are install-specific and gitignored).
- **On a MaluDB hosting VM: `docs/install-on-maludb-hosting.md`** (written 2026-10-07; the README's prompt "Use the installation
  instructions in https://github.com/maludb/maludb-os-core.git to install the MaluDB Business OS Core" lands there): Ubuntu 24.04,
  PostgreSQL 17 and MaluDB core are present; the existing application in `/var/www` is moved to `/var/www-previous-<date>` and its
  config file's database connection and MaluDB schema become the kernel's `MALUDB_MEMORY_*`; the installer interviews the owner for
  the domain and reminds them of the DNS A records (bare, `app.`, `os.`, `hr.`, `helpdesk.`, `spaces.`, `projects.`); the rest is
  `install.md` §3–§13 by reference.
- **Dockerized install: `github.com/maludb/maludb-os-docker`** (2026-10-07; local clone `~/maludb-os-docker`): `host-install.sh` runs this
  runbook as one systemd container `bos` on a MaluDB hosting VM — the kernel at `/var/www` is in the IMAGE (`OS_CORE_REF`), PostgreSQL and the
  MaluDB API stay on the host as `pg-host`, secrets/config/`/srv/apps` on volumes, Help Desk and Spaces applied by `bos-init` at every boot.
  The same repository is the Claude Code plugin `maludb-os-docker` (skills `os-docker`, `os-docker-new-app`, `os-docker-change-app`,
  `os-docker-kernel`; `bos-app.sh plan|apply|update|status|logs|list` wraps `app_install.php` with the container's fixes — `DB_HOST=pg-host`,
  empty ports, a migration ledger). Fixed there the same day: `bos-init` applies the kernel's new migrations at every boot (it was once
  only), and under `https` it writes no loopback `/etc/hosts` line for an application's name (the runner dials the registered
  `https://<label>.<domain>/mcp/…`; a loopback line sent that to 127.0.0.1:443) — agents reach an application only through the owner's DNS/TLS.
- Migrations: `sudo -u postgres psql -v ON_ERROR_STOP=1 -d certstudy -f db/NNN_*.sql`
- Service check: `systemctl status 'certstudy-*'`
- MCP smoke test: mint token `php bin/mint_mcp_token.php --email ... --label ...`, then POST
  an MCP initialize to `http://localhost/mcp/records` with `Authorization: Bearer <token>`.
- Ingestion check: `sudo systemctl start certstudy-activity-ingest.service && journalctl -u certstudy-activity-ingest -n 2`
