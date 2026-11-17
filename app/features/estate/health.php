<?php
declare(strict_types=1);

/**
 * A node's health over time: the numbers the watching agents follow (db/195 estate_node_samples), drawn for a
 * person. MaluOS docs/phase14-watching-agents.md left it open ("the samples table makes them possible; nothing
 * draws them yet"); this is the read behind the Health card of a server's or an inference node's location page
 * (GET /locations/health.php). Reads only; nothing here writes, and nothing new is kept.
 *
 * The answer is the samples of a window (a day, a week, thirty days), averaged into at most HEALTH_MAX_POINTS
 * buckets so thirty days of ten-minute rows stay light, grouped into four fixed panels. A key a node never sent
 * draws nothing: no zero is invented, and a panel with no series is left out.
 */
require_once dirname(__DIR__, 2) . '/features/estate/nodes.php';

const HEALTH_WINDOWS_H = [24, 168, 720];
const HEALTH_MAX_POINTS = 300;

/**
 * The panels and their series, in drawing order. A key ending in '*' is a prefix: one series per key the node
 * sent under it (disk.<mount>). unit: 'pct' draws on 0–100; anything else is a plain count or measure. The card
 * draws one chart per unit within a panel, so a series is only ever drawn against others of its own scale.
 * @return list<array{key:string,label:string,series:list<array{key:string,label:string,unit:string}>}>
 */
function health_panels(): array
{
    return [
        ['key' => 'resources', 'label' => 'Resources', 'series' => [
            ['key' => 'load1', 'label' => 'Load (1 min)', 'unit' => 'load'],
            ['key' => 'mem.available_pct', 'label' => 'Memory available', 'unit' => 'pct'],
            ['key' => 'disk.*', 'label' => 'Disk used', 'unit' => 'pct'],
            ['key' => 'inodes_pct', 'label' => 'Inodes used', 'unit' => 'pct'],
        ]],
        ['key' => 'postgres', 'label' => 'PostgreSQL', 'series' => [
            ['key' => 'pg.connections_pct', 'label' => 'Connections used', 'unit' => 'pct'],
            ['key' => 'pg.longest_transaction_s', 'label' => 'Longest transaction', 'unit' => 's'],
            ['key' => 'pg.longest_lock_wait_s', 'label' => 'Longest lock wait', 'unit' => 's'],
            ['key' => 'pg.lock_waiters', 'label' => 'Lock waiters', 'unit' => 'count'],
            ['key' => 'pg.idle_in_transaction', 'label' => 'Idle in transaction', 'unit' => 'count'],
            ['key' => 'pg.dead_pct_max', 'label' => 'Worst dead-tuple share', 'unit' => 'pct'],
            ['key' => 'pg.xid_age', 'label' => 'Transaction-id age', 'unit' => 'xid'],
            ['key' => 'pg.wal_mb', 'label' => 'WAL size', 'unit' => 'MB'],
            ['key' => 'pg.size_mb', 'label' => 'Databases size', 'unit' => 'MB'],
            ['key' => 'pg.replication_lag_mb', 'label' => 'Replication lag', 'unit' => 'MB'],
        ]],
        ['key' => 'security', 'label' => 'Security', 'series' => [
            ['key' => 'sec.failed_logins', 'label' => 'Failed logins', 'unit' => 'count'],
            ['key' => 'sec.failed_source_count', 'label' => 'Failed-login sources', 'unit' => 'count'],
            ['key' => 'sec.new_addresses', 'label' => 'Logins from new addresses', 'unit' => 'count'],
            ['key' => 'sec.sudo', 'label' => 'sudo uses', 'unit' => 'count'],
            ['key' => 'sec.listening', 'label' => 'Listening ports', 'unit' => 'count'],
        ]],
        ['key' => 'upkeep', 'label' => 'Upkeep', 'series' => [
            ['key' => 'updates.pending', 'label' => 'Updates pending', 'unit' => 'count'],
            ['key' => 'updates.security', 'label' => 'Security updates', 'unit' => 'count'],
            ['key' => 'failed_units', 'label' => 'Failed units', 'unit' => 'count'],
            ['key' => 'reboot_days', 'label' => 'Days a reboot has waited', 'unit' => 'days'],
        ]],
    ];
}

/** The window asked for, held to a day, a week or thirty days: the nearest one at or above it, thirty days at most. */
function health_window_hours(?int $hours): int
{
    foreach (HEALTH_WINDOWS_H as $h) {
        if ($hours !== null && $hours <= $h) {
            return $h;
        }
    }
    return $hours === null ? HEALTH_WINDOWS_H[0] : HEALTH_WINDOWS_H[count(HEALTH_WINDOWS_H) - 1];
}

/**
 * Who sees a node's health: an operator (estate_operator(), a super-admin), or a member of the IT department
 * (its people and its watching agents). The Security panel's counts are not every insider's.
 */
function health_reader(PDO $pdo, ?array $member): bool
{
    if ($member === null || ($member['status'] ?? '') !== 'active') {
        return false;
    }
    if (estate_operator($member)) {
        return true;
    }
    $st = $pdo->prepare("SELECT 1 FROM department_members dm JOIN departments d ON d.id = dm.department_id
                          WHERE dm.member_id = :m AND d.system_key = 'it' LIMIT 1");
    $st->execute(['m' => (int) $member['id']]);
    return $st->fetchColumn() !== false;
}

/**
 * Samples as [epoch_ms, numbers] averaged into at most $max buckets of equal time over [$fromMs, $toMs]. A
 * bucket's value for a key is the mean of the samples in it that carry the key; a bucket's time is the mean of
 * those samples' times. Only ints and floats pass: anything else in a sample is ignored. Pure, for the test.
 * @param list<array{0:int,1:array<string,mixed>}> $samples in time order
 * @return array<string,list<array{0:int,1:float}>> key => points in time order
 */
function health_bucket(array $samples, int $fromMs, int $toMs, int $max = HEALTH_MAX_POINTS): array
{
    $span = max(1, $toMs - $fromMs);
    $width = (int) ceil($span / max(1, $max));
    $acc = [];   // key => bucket => [sum_t, sum_v, n]
    foreach ($samples as [$t, $numbers]) {
        $b = min($max - 1, max(0, intdiv($t - $fromMs, $width)));
        foreach ($numbers as $k => $v) {
            if (!is_int($v) && !is_float($v)) {
                continue;
            }
            $c = $acc[$k][$b] ?? [0, 0.0, 0];
            $acc[$k][$b] = [$c[0] + $t, $c[1] + $v, $c[2] + 1];
        }
    }
    $out = [];
    foreach ($acc as $k => $buckets) {
        ksort($buckets);
        foreach ($buckets as [$st, $sv, $n]) {
            $out[(string) $k][] = [(int) round($st / $n), round($sv / $n, 2)];
        }
    }
    return $out;
}

/**
 * The panels of a node's window: health_panels() filled from the bucketed series, a prefix key expanded to one
 * series per key the node sent (sorted), a series with no points and a panel with no series left out.
 * @param array<string,list<array{0:int,1:float}>> $series health_bucket()'s answer
 */
function health_fill_panels(array $series): array
{
    $panels = [];
    foreach (health_panels() as $p) {
        $out = [];
        foreach ($p['series'] as $s) {
            if (str_ends_with($s['key'], '*')) {
                $prefix = substr($s['key'], 0, -1);
                $keys = array_values(array_filter(array_map('strval', array_keys($series)), static fn (string $k) => str_starts_with($k, $prefix)));
                sort($keys, SORT_STRING);
                foreach ($keys as $k) {
                    $out[] = ['key' => $k, 'label' => $s['label'] . ' ' . substr($k, strlen($prefix)), 'unit' => $s['unit'], 'points' => $series[$k]];
                }
            } elseif (($series[$s['key']] ?? []) !== []) {
                $out[] = $s + ['points' => $series[$s['key']]];
            }
        }
        if ($out !== []) {
            $panels[] = ['key' => $p['key'], 'label' => $p['label'], 'series' => $out];
        }
    }
    return $panels;
}

/** A node's health answer for the window: its panels and its open alerts. */
function node_health_answer(PDO $pdo, int $nodeId, int $hours): array
{
    $hours = health_window_hours($hours);
    $st = $pdo->prepare('SELECT (extract(epoch FROM ts) * 1000)::bigint AS t, numbers FROM estate_node_samples
                          WHERE location_id = :id AND ts > now() - make_interval(hours => :h) ORDER BY ts');
    $st->execute(['id' => $nodeId, 'h' => $hours]);
    $samples = [];
    foreach ($st->fetchAll() as $r) {
        $n = json_decode((string) $r['numbers'], true);
        if (is_array($n)) {
            $samples[] = [(int) $r['t'], $n];
        }
    }
    $toMs = (int) round(microtime(true) * 1000);
    $fromMs = $toMs - $hours * 3600 * 1000;
    return [
        'location_id' => $nodeId,
        'hours' => $hours,
        'windows' => HEALTH_WINDOWS_H,
        'sampled_every_s' => NODE_SAMPLE_EVERY_S,
        'from' => $fromMs,
        'to' => $toMs,
        'sample_count' => count($samples),
        'panels' => health_fill_panels(health_bucket($samples, $fromMs, $toMs)),
        'alerts' => array_map('present_alert', estate_alert_rows($pdo, $nodeId, true)),
    ];
}
