<?php
declare(strict_types=1);

/**
 * A node's health over time (the Health card of location-view; app/features/estate/health.php). GET ?id=&hours=
 * (24, 168 or 720; held to the nearest). JSON only: the Next.js server reads it, the browser never does. Gate:
 * insider, then an operator or a member of the IT department (health_reader()). Reads only.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/estate/render.php';
require_once dirname(__DIR__, 2) . '/app/features/estate/health.php';

require_insider();
estate_require_files();

$pdo = db();
if (!health_reader($pdo, current_member())) {
    deny('A node\'s health is for the estate\'s operators and the IT department.');
}
$id = request_integer('id');
$location = $id === null ? null : find_location($pdo, $id);
if ($location === null || !in_array($location['kind'], NODE_KINDS, true)) {
    json_error('not_found', 'No such node.', 404);
}

respond_screen(node_health_answer($pdo, $id, request_integer('hours') ?? HEALTH_WINDOWS_H[0]));
