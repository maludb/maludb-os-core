import { z } from "zod";

/** The contract with app/features/estate/present.php — change both together. */
const id = z.number().int();
const named = z.object({ id, name: z.string() });

export const locationRow = z.object({
  id,
  name: z.string(),
  kind: z.string(),
  parent_name: z.string().nullable(),
  parent_location_id: id.nullable().default(null),
  presence: z.string(),
  last_seen_at: z.string().nullable(),
  siting: z.string().nullable(),
  owner_name: z.string().nullable(),
  owner_member_id: id.nullable().default(null),
  specs_summary: z.string().nullable(),
  resident_count: id,
  status: z.string(),
  address: z.string().nullable().optional(),
  timezone: z.string().nullable().optional(),
  serving_application_count: id.optional(),
});
export type LocationRow = z.infer<typeof locationRow>;

export const locationsList = z.object({
  locations: z.array(locationRow),
  pagination: z.object({ page: id, total_pages: id, total: id }),
  filters: z.object({ kind: z.string(), parent: z.string(), online: z.boolean(), retired: z.boolean() }),
  can: z.object({ edit: z.boolean() }),
});

export const locationView = z.object({
  location: locationRow.extend({
    parent_id: id.nullable(),
    description: z.string().nullable(),
    owner_member_id: id.nullable(),
    office_manager_member_id: id.nullable(),
    office_manager_name: z.string().nullable(),
    allows_agents: z.boolean(),
    allows_humans: z.boolean(),
    spec_rows: z.array(z.object({ label: z.string(), value: z.string() })),
  }),
  residents: z.array(z.object({ id, name: z.string(), kind: z.string(), is_primary: z.boolean(), is_office_manager: z.boolean() })),
  departments: z.array(named),
  applications: z.array(z.object({ id, name: z.string(), category_display: z.string(), status: z.string() })),
  serving_applications: z.array(z.object({ id, name: z.string(), scope_id: id, live_grant_count: id })).default([]),
  options: z.object({
    residents: z.array(z.object({ id, name: z.string(), kind: z.string() })),
    departments: z.array(named),
    agents: z.array(named),
    buildings: z.array(named),
  }),
  can: z.object({ retire: z.boolean(), delete: z.boolean(), health: z.boolean().default(false) }),
  delete_blockers: z.array(z.string()),
});

/** The Health card of location-view — app/features/estate/health.php (GET /locations/health.php). */
const healthPoint = z.tuple([z.number(), z.number()]);
export const nodeHealth = z.object({
  location_id: id,
  hours: id,
  windows: z.array(id),
  sampled_every_s: id,
  from: z.number(),
  to: z.number(),
  sample_count: id,
  panels: z.array(z.object({
    key: z.string(),
    label: z.string(),
    series: z.array(z.object({ key: z.string(), label: z.string(), unit: z.string(), points: z.array(healthPoint) })),
  })),
  alerts: z.array(z.object({ id, ts: z.string().nullable(), kind: z.string(), key: z.string(), severity: z.string(), title: z.string() })),
});
export type NodeHealth = z.infer<typeof nodeHealth>;

export const locationFormData = z.object({
  location: z.object({
    id: id.nullable(),
    name: z.string(),
    kind: z.string(),
    siting: z.string().nullable(),
    parent_location_id: id.nullable(),
    owner_member_id: id.nullable(),
    description: z.string().nullable(),
    platform: z.string().nullable(),
    operating_system: z.string().nullable(),
    os_version: z.string().nullable(),
    ssh_access: z.boolean().nullable(),
    root_access: z.boolean().nullable(),
    external_ref: z.string().nullable(),
    hostname: z.string().nullable(),
    ip_address: z.string().nullable(),
    cpu_cores: id.nullable(),
    memory_mb: id.nullable(),
    storage_gb: id.nullable(),
    is_always_on: z.boolean(),
    address: z.string().nullable().default(null),
    timezone: z.string().nullable().default(null),
  }),
  options: z.object({
    parents: z.object({ office: z.array(named), desk: z.array(named) }),
    owners: z.array(named),
    platforms: z.array(z.object({ value: z.string(), label: z.string() })),
  }),
  can: z.object({ delete: z.boolean() }),
  delete_blockers: z.array(z.string()),
});
export type LocationFormData = z.infer<typeof locationFormData>;
