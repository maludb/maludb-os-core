<?php
declare(strict_types=1);
/**
 * Hire one of the agents an application from us declares in its maludb-os.json `agents[]` — the plugin's
 * contract (registration.md): a `user`-role agent in the department that owns the application, its
 * job_description as its prompt, an application_access grant at its access_capability, one tool grant per
 * tool it names (read tools on the application's own MCP endpoints, action tools on the kernel's Actions
 * MCP) and its shipped skills assigned at agent scope; the first entry (`expert`) becomes the application's
 * subject matter expert (`applications.sme_agent_member_id`) when it has none.
 *
 * The contract says each entry is a proposal a super-admin confirms in one click; this script IS that click,
 * run by a super-admin (or by the provisioning script for the default applications, K4 — the owner's decision
 * of 2026-09-28 for Projects' Scrum Master, "hired on install"). Running it again reconciles the access grant
 * and the skills and changes nothing else.
 *
 *   php bin/hire_application_agent.php --app <app_key> --agent <agent key>
 *                                      [--by <super-admin email>] [--model <model_key>] [--department <id>]
 *                                      [--app-dir </srv/apps/<app_key>>] [--budget <USD a month>]
 *
 * bin/hire_scrum_master.php is this script with --app projects --agent scrum_master.
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/features/agents/render.php';
require_once dirname(__DIR__) . '/app/features/applications/queries.php';
agents_require_files();

/** --k=v or --k v, from $argv (not getopt, so a wrapper script may prepend its own). */
function hire_cli_options(array $argv): array
{
    $opts = [];
    for ($i = 1, $n = count($argv); $i < $n; $i++) {
        if (!str_starts_with($argv[$i], '--')) { continue; }
        $k = substr($argv[$i], 2);
        if (str_contains($k, '=')) { [$k, $v] = explode('=', $k, 2); $opts[$k] = $v; continue; }
        $opts[$k] = isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '--') ? $argv[++$i] : '';
    }
    return $opts;
}
$opts = hire_cli_options($GLOBALS['argv']);
$appKey = strtolower(trim((string) ($opts['app'] ?? '')));
$agentKey = strtolower(trim((string) ($opts['agent'] ?? '')));
if (!preg_match('/^[a-z][a-z0-9_]{1,31}$/', $appKey) || !preg_match('/^[a-z][a-z0-9_]{1,31}$/', $agentKey)) {
    fwrite(STDERR, "Usage: php bin/hire_application_agent.php --app <app_key> --agent <agent key> [--by <email>] [--model <key>] [--department <id>] [--app-dir <dir>]\n");
    exit(1);
}
$pdo = db();
$appDir = rtrim((string) ($opts['app-dir'] ?? ('/srv/apps/' . $appKey)), '/');

$by = null;
if (isset($opts['by'])) {
    $by = find_member_by_email($pdo, normalize_email((string) $opts['by']));
} else {
    $by = $pdo->query("SELECT * FROM members WHERE business_role = 'super_admin' AND member_kind = 'human' AND status = 'active' ORDER BY id LIMIT 1")->fetch() ?: null;
}
if ($by === null || ($by['business_role'] ?? '') !== 'super_admin') {
    fwrite(STDERR, "The hire is recorded under a super-admin: --by <email>.\n");
    exit(1);
}
$byId = (int) $by['id'];
$_SESSION['member_id'] = $byId;
$_SESSION['member_role'] = $by['role'] ?? 'organizer';
db_apply_context($pdo);

// The application must be registered (the catalog entry + the installer's registration).
$st = $pdo->prepare("SELECT * FROM applications WHERE app_key = :k AND status <> 'retired'");
$st->execute(['k' => $appKey]);
$app = $st->fetch();
if ($app === false) {
    fwrite(STDERR, "No application with app_key '{$appKey}' is registered in the kernel.\n");
    exit(1);
}
$declaration = json_decode((string) @file_get_contents($appDir . '/maludb-os.json'), true) ?: [];
$agents = $declaration['agents'] ?? [];
if (isset($declaration['expert']) && $agents === []) {                // the old one-entry form
    $agents = [['key' => 'expert'] + (array) $declaration['expert']];
}
$agentDecl = null;
$isFirst = false;
foreach (array_values((array) $agents) as $i => $a) {
    if (($a['key'] ?? '') === $agentKey) { $agentDecl = $a; $isFirst = $i === 0; }
}
if ($agentDecl === null) {
    fwrite(STDERR, "maludb-os.json at {$appDir} declares no '{$agentKey}' agent.\n");
    exit(1);
}
$jobDescription = (string) @file_get_contents($appDir . '/' . ($agentDecl['job_description'] ?? "os/{$agentKey}.md"));
if ($jobDescription === '') {
    fwrite(STDERR, "No job description at {$appDir}/" . ($agentDecl['job_description'] ?? "os/{$agentKey}.md") . ".\n");
    exit(1);
}

// What the kernel knows about each kind of agent an application ships. Anything else: the job description's
// first heading names it, no duties.
$appName = (string) $app['name'];
$profiles = [
    'expert' => [
        'name' => $appName . ' Expert', 'job_title' => $appName . ' application expert', 'role_key' => $appKey . '_expert',
        'description' => 'Answers what people and other agents ask about ' . $appName . ' from its own tools and memory, and does a few things on request.',
        'duties' => [],
    ],
    'scrum_master' => [
        'name' => 'Scrum Master', 'job_title' => 'Scrum Master (' . $appName . ')', 'role_key' => 'scrum_master',
        'description' => 'Keeps the sprints honest in ' . $appName . ': hygiene every morning, the sprint report at close, nudges as comments — never a move it was not asked for.',
        'duties' => [[
            'name' => 'Sprint hygiene',
            'instructions' => "Every morning, for each active project you may see: list the hygiene findings (stale in-progress issues, unassigned issues in the active sprint, unestimated stories in the next sprint, blocked issues and who owns the blocker) and the sprint's burndown. Leave one comment per issue that needs a nudge, addressed to its assignee or, unassigned, to the product owner; never reassign or move an issue yourself. If a sprint ends today, draft the sprint report as a comment on the sprint's closing issue or an escalation to the scrum master, and never close the sprint yourself.",
            'schedule_cron' => '30 7 * * 1-5',
        ]],
    ],
];
$heading = preg_match('/^#\s+(.+)$/m', $jobDescription, $m) ? trim($m[1]) : ucfirst(str_replace('_', ' ', $agentKey));
$profile = $profiles[$agentKey] ?? [
    'name' => $heading, 'job_title' => $heading . ' (' . $appName . ')', 'role_key' => $agentKey,
    'description' => 'Ships with ' . $appName . ': ' . $heading . '.', 'duties' => [],
];

// Where: --department, else the application's owner department, else the Front Office.
$dept = null;
if (isset($opts['department'])) {
    $st = $pdo->prepare('SELECT * FROM departments WHERE id = :id AND archived_at IS NULL');
    $st->execute(['id' => (int) $opts['department']]);
    $dept = $st->fetch() ?: null;
}
if ($dept === null && ($app['owner_department_id'] ?? null) !== null) {
    $st = $pdo->prepare('SELECT * FROM departments WHERE id = :id AND archived_at IS NULL');
    $st->execute(['id' => (int) $app['owner_department_id']]);
    $dept = $st->fetch() ?: null;
}
if ($dept === null) {
    $dept = $pdo->query("SELECT * FROM departments WHERE system_key = 'front_office'")->fetch() ?: null;
}
if ($dept === null) {
    fwrite(STDERR, "No department to hire into.\n");
    exit(1);
}

$domain = $pdo->query("SELECT split_part(email, '@', 2) FROM members WHERE member_kind = 'agent' AND email LIKE '%@agents.%' ORDER BY id LIMIT 1")->fetchColumn();
if ($domain === false || $domain === '') {
    $domain = 'agents.' . substr((string) $by['email'], strpos((string) $by['email'], '@') + 1);
}
$agentEmail = ($agentKey === 'scrum_master' ? 'scrum-master' : $appKey . '-' . str_replace('_', '-', $agentKey)) . '@' . $domain;
$existing = find_member_by_email($pdo, $agentEmail);

$modelKey = (string) ($opts['model'] ?? '');
$st = $pdo->prepare("SELECT * FROM model_registry WHERE status = 'active' AND harness = 'claude_agent_sdk' AND (:k = '' OR model_key = :k2) ORDER BY id LIMIT 1");
$st->execute(['k' => $modelKey, 'k2' => $modelKey]);
$model = $st->fetch();
if ($model === false) {
    fwrite(STDERR, "No active model on the claude_agent_sdk harness" . ($modelKey !== '' ? " with key {$modelKey}" : '') . ".\n");
    exit(1);
}

// Its tools: the application's own MCP endpoints, and the kernel's Actions MCP for the application's actions.
$endpoints = [];
$st = $pdo->prepare("SELECT e.id, e.name FROM application_endpoints e WHERE e.application_id = :a AND e.kind = 'mcp' AND e.status = 'active'");
$st->execute(['a' => (int) $app['id']]);
foreach ($st->fetchAll() as $e) { $endpoints[$e['name']] = (int) $e['id']; }
$actions = $pdo->query("SELECT e.id FROM application_endpoints e JOIN applications a ON a.id = e.application_id WHERE a.name = 'Business OS' AND e.kind = 'mcp' AND e.name = 'Actions MCP'")->fetchColumn();
if ($actions !== false) { $endpoints['Actions MCP'] = (int) $actions; }
$tools = [];
foreach ((array) ($agentDecl['tool_grants'] ?? []) as $endpoint => $names) {
    if (!isset($endpoints[$endpoint])) { fwrite(STDERR, "note: no '{$endpoint}' endpoint for the tools it declares — not granted.\n"); continue; }
    foreach ((array) $names as $name) { $tools[] = ['application_endpoint_id' => $endpoints[$endpoint], 'tool_name' => (string) $name, 'constraints' => []]; }
}

$tz = (string) ($pdo->query("SELECT timezone FROM business_settings WHERE id = 1")->fetchColumn() ?: 'UTC');
$duties = array_map(static fn (array $d): array => $d + ['timezone' => $tz], $profile['duties']);
$budget = number_format((float) ($opts['budget'] ?? 20), 2, '.', '');

$fields = [
    'email' => $agentEmail, 'name' => $profile['name'], 'job_title' => $profile['job_title'], 'description' => $profile['description'],
    'department_id' => (int) $dept['id'],
    'manager_member_id' => $dept['manager_member_id'] !== null ? (int) $dept['manager_member_id'] : $byId,
    'model_id' => (int) $model['id'], 'monthly_budget_amount' => $budget, 'budget_currency' => 'USD',
    'home_location_id' => $dept['home_location_id'] !== null ? (int) $dept['home_location_id'] : null,
    'role_key' => $profile['role_key'], 'agent_kind' => 'subagent', 'phone_number' => null,
    'job_description' => $jobDescription, 'parameters' => [], 'tools' => $tools, 'duties' => $duties,
];
if ($existing !== null) {
    $memberId = (int) $existing['id'];
    echo "Already hired: {$agentEmail} (member {$memberId}) — reconciling its access and skills.\n";
} else {
    $agent = hire_agent($pdo, $fields, $byId);
    $memberId = (int) $agent['agent_member_id'];
    log_activity($pdo, 'agent.hire', 'member', $memberId, [
        'source' => 'cron',
        'after' => ['name' => $profile['name'], 'department' => $dept['name'], 'model' => $model['model_key'], 'tools' => count($tools),
                    'application' => $appKey, 'agent_key' => $agentKey, 'by' => 'bin/hire_application_agent.php'],
    ]);
    echo "Hired: {$profile['name']} (member {$memberId}) in {$dept['name']} on {$model['display_name']}, " . count($tools) . ' tools, ' . count($duties) . " duties.\n";
}

// Its access to the application, at the declared capability (the contract's application_access grant): the role
// whose capability that is — an application's `member` role for write, `viewer` for read, when it has them.
$capability = in_array($agentDecl['access_capability'] ?? 'write', ['read', 'write', 'admin'], true) ? $agentDecl['access_capability'] : 'write';
$st = $pdo->prepare('SELECT id FROM application_access WHERE application_id = :a AND member_id = :m AND revoked_at IS NULL');
$st->execute(['a' => (int) $app['id'], 'm' => $memberId]);
if ($st->fetchColumn() === false) {
    $st = $pdo->prepare('SELECT role_key, is_admin FROM application_roles WHERE application_id = :a AND withdrawn_at IS NULL ORDER BY sort_order');
    $st->execute(['a' => (int) $app['id']]);
    $roles = $st->fetchAll();
    $wanted = $capability === 'read' ? ['viewer', 'reader', 'read'] : ($capability === 'admin' ? [] : ['member', 'user', 'write']);
    $roleKey = null;
    foreach ($roles as $r) {
        if ($capability === 'admin' ? (bool) $r['is_admin'] : in_array($r['role_key'], $wanted, true)) { $roleKey = $r['role_key']; break; }
    }
    if ($roleKey === null && $roles !== [] && $capability !== 'admin') {
        $roleKey = $roles[$capability === 'read' ? 0 : min(1, count($roles) - 1)]['role_key'];   // the lowest, or the next one up
    }
    $grant = grant_application_access($pdo, (int) $app['id'], ['member_id' => $memberId, 'capability' => $capability, 'roles' => $roleKey !== null ? [$roleKey] : [],
        'note' => 'Shipped with ' . $appName . ' (' . $agentKey . ')'], $byId);
    log_activity($pdo, 'application_access.grant', 'application', (int) $app['id'], ['source' => 'cron',
        'after' => ['member_id' => $memberId, 'capability' => $capability, 'role_key' => $roleKey, 'grant_id' => (int) ($grant['application_access_id'] ?? 0)]]);
    echo "Access: {$capability}" . ($roleKey !== null ? " as {$roleKey}" : '') . ".\n";
} else {
    echo "Access: already granted.\n";
}

// The first declared agent is the application's expert, when it has none.
if ($isFirst && ($app['sme_agent_member_id'] ?? null) === null) {
    if (set_application_expert($pdo, (int) $app['id'], $memberId)) {
        log_activity($pdo, 'application.sme_set', 'application', (int) $app['id'], ['source' => 'cron',
            'before' => ['sme_agent_member_id' => null], 'after' => ['sme_agent_member_id' => $memberId]]);
        echo "Expert: {$profile['name']} is now the expert on {$appName}.\n";
    }
} elseif ($isFirst) {
    echo "Expert: {$appName} already has one (member " . (int) $app['sme_agent_member_id'] . ").\n";
}

// Its skills: the ones the declaration names for it (imported at application scope by the installer), at agent scope too.
$assigned = 0;
foreach ((array) ($agentDecl['skills'] ?? []) as $path) {
    $skill = basename((string) $path);
    $st = $pdo->prepare("SELECT 1 FROM skill_assignments WHERE skill_name = :s AND scope_kind = 'agent' AND agent_member_id = :m AND revoked_at IS NULL");
    $st->execute(['s' => $skill, 'm' => $memberId]);
    if ($st->fetchColumn() !== false) { continue; }
    $pdo->prepare("INSERT INTO skill_assignments (skill_name, scope_kind, agent_member_id, note, assigned_by)
                   VALUES (:s, 'agent', :m, :note, :by)")->execute(['s' => $skill, 'm' => $memberId, 'note' => "Shipped with {$appName} for its {$profile['name']}", 'by' => $byId]);
    log_activity($pdo, 'skill.assign', 'member', $memberId, ['source' => 'cron', 'after' => ['skill_name' => $skill, 'scope_kind' => 'agent']]);
    $assigned++;
}
echo "Skills: " . count((array) ($agentDecl['skills'] ?? [])) . " declared, {$assigned} newly assigned.\n";
