<?php
declare(strict_types=1);

/**
 * The model registry (build spec: docs/build-specs/agent-hr.md, "Why the model registry is in
 * this slice"). `agent_profiles.model_id` is NOT NULL REFERENCES model_registry(id), and the
 * registry starts with zero rows, so hiring cannot happen without it.
 *
 * No API keys here: `api_secret_id` points at tenant_secrets, which has no writer yet (the
 * Stripe slice builds it). Register the model; leave api_secret_id NULL; a model without a key
 * can be hired against and cannot be run — exactly the state this slice leaves the world in.
 */

const MODEL_PROVIDERS = ['anthropic', 'openai', 'deepseek', 'zhipu', 'moonshot', 'qwen', 'fireworks', 'local', 'other'];
const MODEL_HARNESSES = ['claude_agent_sdk', 'openai_agents_sdk', 'openai_compatible', 'native', 'hermes'];
const MODEL_STATUSES = ['active', 'deprecated', 'disabled'];

function find_models(PDO $pdo, bool $includeDisabled = true): array
{
    $sql = 'SELECT * FROM mcp_model_registry' . ($includeDisabled ? '' : " WHERE status = 'active'")
         . ' ORDER BY status, display_name';
    return $pdo->query($sql)->fetchAll();
}

function find_model(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM mcp_model_registry WHERE model_id = :id');
    $st->execute(['id' => $id]);
    return ($r = $st->fetch()) === false ? null : $r;
}

/**
 * A model's endpoint URL — a sanctioned base-table read for the model FORM only (owner's
 * decision 10, 2026-09-19; same rule as find_organization_tax_id()).
 *
 * mcp_model_registry does not expose endpoint_url — an internal address is not something every
 * reader of the view, agents included, should see. But the super-admin form edits it, and
 * without the stored value it showed a blank and upsert_model() wrote the blank back: a local
 * or OpenAI-compatible model lost its endpoint the first time anyone changed its price.
 *
 * The join lets it answer only for a model the view already returns. Never select more columns
 * here, and never use it for a list, a search, an export or an MCP tool.
 */
function find_model_endpoint_url(PDO $pdo, int $id): ?string
{
    $st = $pdo->prepare(<<<'SQL'
        SELECT m.endpoint_url
          FROM model_registry m
          JOIN mcp_model_registry v ON v.model_id = m.id
         WHERE m.id = :id
    SQL);
    $st->execute(['id' => $id]);
    $value = $st->fetchColumn();
    return $value === false || $value === null || $value === '' ? null : (string) $value;
}

function upsert_model(PDO $pdo, ?int $id, array $f): array
{
    if ($id === null) {
        $st = $pdo->prepare(<<<'SQL'
            INSERT INTO model_registry
                (model_key, display_name, provider, provider_model_id, harness, endpoint_url,
                 context_window_tokens, price_input_per_mtok, price_output_per_mtok,
                 price_cache_read_per_mtok, price_cache_write_per_mtok, currency, status)
            VALUES
                (:key, :name, :provider, :provider_model_id, :harness, :endpoint,
                 :context, :in_price, :out_price, :cache_read, :cache_write, :currency, :status)
            RETURNING id
        SQL);
    } else {
        $st = $pdo->prepare(<<<'SQL'
            UPDATE model_registry
               SET model_key = :key, display_name = :name, provider = :provider,
                   provider_model_id = :provider_model_id, harness = :harness, endpoint_url = :endpoint,
                   context_window_tokens = :context, price_input_per_mtok = :in_price,
                   price_output_per_mtok = :out_price, price_cache_read_per_mtok = :cache_read,
                   price_cache_write_per_mtok = :cache_write, currency = :currency, status = :status,
                   updated_at = now()
             WHERE id = :id
            RETURNING id
        SQL);
    }
    $params = [
        'key' => $f['model_key'], 'name' => $f['display_name'], 'provider' => $f['provider'],
        'provider_model_id' => $f['provider_model_id'], 'harness' => $f['harness'],
        'endpoint' => $f['endpoint_url'] ?? null, 'context' => $f['context_window_tokens'] ?? null,
        'in_price' => $f['price_input_per_mtok'] ?? '0', 'out_price' => $f['price_output_per_mtok'] ?? '0',
        'cache_read' => $f['price_cache_read_per_mtok'] ?? '0', 'cache_write' => $f['price_cache_write_per_mtok'] ?? '0',
        'currency' => $f['currency'] ?: 'USD', 'status' => $f['status'] ?: 'active',
    ];
    if ($id !== null) {
        $params['id'] = $id;
    }
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? [] : (find_model($pdo, (int) $row['id']) ?? []);
}

function set_model_status(PDO $pdo, int $id, string $status): array
{
    $st = $pdo->prepare('UPDATE model_registry SET status = :status, updated_at = now()
                          WHERE id = :id RETURNING id');
    $st->execute(['status' => $status, 'id' => $id]);
    $row = $st->fetch();
    return $row === false ? [] : (find_model($pdo, (int) $row['id']) ?? []);
}
