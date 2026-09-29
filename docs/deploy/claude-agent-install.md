# Installing the Claude Agent SDK harness on an office VM

The second harness, beside `hermes-install.md`. Everything is idempotent. Node 24 is already a
prerequisite of the platform (the React front end runs on it), so nothing new is needed but the
pinned CLI.

```bash
# 1. The Claude Code CLI, pinned, root-owned, read-only to the agent user. Bump the version ONLY
#    after mcp/claude_conformance passes on it (and then re-run the evals).
sudo install -d -m 0755 -o root -g root /opt/claude-agent
sudo npm install --prefix /opt/claude-agent @anthropic-ai/claude-code@2.1.278 --no-fund --no-audit
sudo install -d -m 0755 /opt/claude-agent/bin
sudo ln -sfn /opt/claude-agent/node_modules/@anthropic-ai/claude-code/bin/claude.exe /opt/claude-agent/bin/claude
sudo chmod -R go-w /opt/claude-agent
/opt/claude-agent/bin/claude --version        # must print the pinned version

# 2. The launcher's binary allow-list now has two entries; reinstall it.
sudo install -o root -g root -m 0755 docs/deploy/bos-agent-exec /usr/local/sbin/bos-agent-exec

# 3. Conformance, BEFORE any agent is pointed at it. Green is the licence to proceed.
cd /var/www/mcp/claude_conformance && python3 run_conformance.py --claude /opt/claude-agent/bin/claude

# 4. Restart the runner; /health must now list both harnesses.
sudo systemctl restart certstudy-agent-runner
curl -s http://127.0.0.1:8815/health       # {"ok":true,"running":[],"harnesses":["claude_agent_sdk","hermes"]}
```

No migration and no new secret: the harness authenticates to the **ledger proxy**, not to
Anthropic. `ANTHROPIC_API_KEY` in the agent's environment is the run's own proxy key, and the real
provider key stays in `/etc/business-os/runner.env` where only the runner can read it.

## Why `--bare` is not optional

`--bare` makes `ANTHROPIC_API_KEY` (or an `apiKeyHelper` given through `--settings`) the **only**
way the CLI can authenticate: OAuth and the OS keychain are never read. That is CLAUDE.md's
"API keys only — no claude.ai/Pro/Max login in the product" enforced by the binary rather than by
our remembering. Conformance C1 proves it with an OAuth credentials file deliberately planted in
the agent's `CLAUDE_CONFIG_DIR`: the run refuses and makes no upstream call.

It also skips hooks, plugins, LSP, auto-memory and `CLAUDE.md` auto-discovery — each of which
would otherwise put text into the agent that the platform did not put there.

## No Anthropic account is involved

The agent process runs as `bos-agent` under `systemd-run` with `IPAddressDeny=any` +
`IPAddressAllow=localhost`. It cannot reach `api.anthropic.com` even if it were given a key: every
model call goes to the ledger proxy on 127.0.0.1, which is what puts it in the prompt ledger before
it leaves the box.

## Upgrading

1. `sudo npm install --prefix /opt/claude-agent @anthropic-ai/claude-code@<new version>`
2. `python3 run_conformance.py` — the suite is the upgrade test. If a check fails, the CLI changed
   something we depend on; read `mcp/claude_conformance/README.md` for what each one is for.
3. Only then run the evals.

`agent_runs.sdk_version` records the CLI version and the pinned npm version on every run, so which
release produced a given piece of work is always answerable.
