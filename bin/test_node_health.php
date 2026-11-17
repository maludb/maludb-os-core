<?php
declare(strict_types=1);
/**
 * Proof of a node's health over time, the read behind the Health card of location-view
 * (app/features/estate/health.php; db/195 samples): the windows, the bucketing, the panels, the answer for a
 * node with samples and an alert, and who may read it. The handler html/locations/health.php is the gate and
 * this answer; the page itself is the web front end's.
 *
 *   php bin/test_node_health.php
 *
 * Makes one server node named SMOKE NH <run>, deletes its samples and alerts and retires it at the end.
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/features/estate/health.php';
$pdo = db();
$admin = $pdo->query("SELECT * FROM members WHERE business_role = 'super_admin' AND status = 'active' AND member_kind = 'human' ORDER BY id LIMIT 1")->fetch();
$_SESSION['member_id'] = (int) $admin['id'];
db_apply_context($pdo);
$fails = 0;
$ok = static function (bool $c, string $label) use (&$fails): void { if (!$c) { $fails++; } echo ($c ? '  ok   ' : '  FAIL ') . $label . "\n"; };
$run = date('md-His');

echo "== the windows\n";
$ok(health_window_hours(null) === 24 && health_window_hours(0) === 24 && health_window_hours(24) === 24, 'none, zero and a day are a day');
$ok(health_window_hours(25) === 168 && health_window_hours(168) === 168 && health_window_hours(169) === 720, 'past a day a week, past a week thirty days');
$ok(health_window_hours(9999) === 720 && health_window_hours(-5) === 24, 'never past thirty days; a negative is a day');

echo "== the buckets\n";
$to = 1_800_000_000_000;
$from = $to - 720 * 3600 * 1000;
$samples = [];
for ($t = $from + 1; $t <= $to; $t += 600_000) {   // thirty days of ten-minute samples
    $samples[] = [$t, ['load1' => 2, 'disk./' => 50.0, 'disk./srv' => ($t - $from) < 360 * 3600 * 1000 ? 10 : 30, 'note' => 'text', 'flag' => true]];
}
$b = health_bucket($samples, $from, $to);
$ok(count($samples) > 4000 && count($b['load1']) <= HEALTH_MAX_POINTS && count($b['load1']) >= HEALTH_MAX_POINTS - 1, 'over 4,000 samples become at most ' . HEALTH_MAX_POINTS . ' points (' . count($b['load1']) . ')');
$ok(array_filter($b['load1'], static fn ($p) => $p[1] !== 2.0) === [] && array_filter($b['disk./'], static fn ($p) => $p[1] !== 50.0) === [], 'a steady value stays itself');
$srv = array_column($b['disk./srv'], 1);
$ok(min($srv) === 10.0 && max($srv) === 30.0 && count(array_filter($srv, static fn ($v) => $v > 10.0 && $v < 30.0)) <= 1, 'a step stays a step: at most the one bucket across it is a mean');
$ok(!isset($b['note']) && !isset($b['flag']), 'text and booleans are not numbers here (the samples carry booleans as 0/1)');
$ts = array_column($b['load1'], 0);
$sorted = $ts; sort($sorted);
$ok($ts === $sorted && $ts[0] >= $from && end($ts) <= $to, 'points in time order, within the window');
$ok(health_bucket([], $from, $to) === [], 'no samples, no series');

echo "== the panels\n";
$p = health_fill_panels(['disk./srv' => [[1, 3.0]], 'disk./' => [[1, 4.0]], 'load1' => [[1, 1.0]], 'sec.sudo' => [[1, 2.0]], 'pg.wal_mb' => []]);
$keys = array_column($p, 'key');
$ok($keys === ['resources', 'security'], 'only the panels with a series, in order (an empty pg series draws nothing): ' . implode(',', $keys));
$res = array_column($p[0]['series'], 'key');
$ok($res === ['load1', 'disk./', 'disk./srv'], 'disk.* is one series per mount, sorted, after load: ' . implode(',', $res));
$ok($p[0]['series'][1]['label'] === 'Disk used /' && $p[0]['series'][1]['unit'] === 'pct', 'a mount\'s series is labelled with the mount, in percent');
$ok(health_fill_panels([]) === [], 'nothing sent, no panel');
$ok(array_filter(array_merge(...array_map(static fn ($x) => $x['series'], health_panels())), static fn ($s) => !in_array($s['unit'], ['pct', 'load', 's', 'MB', 'days', 'count', 'xid'], true)) === [], 'every series has a unit the card knows');

echo "== a node's answer\n";
$pdo->prepare("INSERT INTO locations (name, kind, is_always_on, presence, last_seen_at, enrolled_at, enrolled_by_member_id, platform, siting, node_role)
               VALUES (:n, 'server', true, 'online', now(), now(), :by, 'physical_os', 'onsite', 'database-server')")->execute(['n' => "SMOKE NH {$run}", 'by' => (int) $admin['id']]);
$nodeId = (int) $pdo->lastInsertId();
$ins = $pdo->prepare("INSERT INTO estate_node_samples (location_id, ts, numbers) VALUES (:id, now() - make_interval(mins => :m), :n)");
foreach ([5, 15, 25, 60 * 30, 60 * 24 * 10] as $m) {   // three in the last day, one a day and a bit ago, one ten days ago
    $ins->execute(['id' => $nodeId, 'm' => $m, 'n' => json_encode(['load1' => 0.5, 'mem.available_pct' => 40, 'disk./' => 71, 'pg.connections_pct' => 12, 'pg.xid_age' => 150000000])]);
}
alert_raise($pdo, $nodeId, 'disk_full', '/', 'warning', "SMOKE NH {$run}: / is 71% full", null);

$day = node_health_answer($pdo, $nodeId, 24);
$ok($day['hours'] === 24 && $day['sample_count'] === 3 && $day['location_id'] === $nodeId, 'a day: the three samples of the last day');
$ok(array_column($day['panels'], 'key') === ['resources', 'postgres'], 'resources and PostgreSQL, nothing it did not send');
$pg = $day['panels'][1]['series'];
$ok(array_column($pg, 'unit') === ['pct', 'xid'], 'connections in percent and the xid age on its own scale');
$ok($day['to'] - $day['from'] === 24 * 3600 * 1000 && $day['sampled_every_s'] === NODE_SAMPLE_EVERY_S && $day['windows'] === HEALTH_WINDOWS_H, 'the window, the cadence and the choices travel');
$ok(count($day['alerts']) === 1 && $day['alerts'][0]['kind'] === 'disk_full' && $day['alerts'][0]['severity'] === 'warning', 'its open alert travels with it');
$week = node_health_answer($pdo, $nodeId, 100);
$month = node_health_answer($pdo, $nodeId, 720);
$ok($week['hours'] === 168 && $week['sample_count'] === 4 && $month['sample_count'] === 5, 'a week has four samples, thirty days all five (100 hours asked is a week)');
$ok(is_string(json_encode($month)), 'the answer encodes as JSON');
$ok(array_filter(array_merge(...array_map(static fn ($s) => array_column($s['points'], 1), array_merge(...array_column($month['panels'], 'series')))), static fn ($v) => !is_float($v)) === [], 'every value is a number');

echo "== who reads it\n";
$ok(health_reader($pdo, $admin), 'an operator (a super-admin) reads it');
$ok(!health_reader($pdo, null) && !health_reader($pdo, ['id' => (int) $admin['id'], 'status' => 'suspended', 'business_role' => 'super_admin']), 'nobody, and a suspended operator, do not');
$it = $pdo->query("SELECT m.* FROM members m JOIN department_members dm ON dm.member_id = m.id JOIN departments d ON d.id = dm.department_id
                    WHERE d.system_key = 'it' AND m.status = 'active' AND m.business_role <> 'super_admin' ORDER BY m.id LIMIT 1")->fetch();
if ($it !== false) {
    $ok(health_reader($pdo, $it), "a member of IT reads it ({$it['member_kind']} {$it['id']})");
} else {
    echo "  --   no IT member who is not a super-admin here; skipped\n";
}
$plain = $pdo->query("SELECT m.* FROM members m WHERE m.status = 'active' AND m.business_role NOT IN ('super_admin') AND NOT EXISTS (
                        SELECT 1 FROM department_members dm JOIN departments d ON d.id = dm.department_id WHERE dm.member_id = m.id AND d.system_key = 'it')
                      ORDER BY m.id LIMIT 1")->fetch();
if ($plain !== false) {
    $ok(!health_reader($pdo, $plain), "an insider outside IT does not ({$plain['member_kind']} {$plain['id']})");
} else {
    echo "  --   no member outside IT here; skipped\n";
}

$pdo->prepare('DELETE FROM estate_node_samples WHERE location_id = :id')->execute(['id' => $nodeId]);
$pdo->prepare('DELETE FROM estate_alerts WHERE location_id = :id')->execute(['id' => $nodeId]);
node_retire($pdo, $nodeId);
$pdo->prepare("UPDATE locations SET name = name || ' retired' WHERE id = :id")->execute(['id' => $nodeId]);
echo $fails === 0 ? "\nPASS\n" : "\nFAIL ({$fails})\n";
exit($fails === 0 ? 0 : 1);
