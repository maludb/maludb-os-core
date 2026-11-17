import { ApiError, apiGet } from "@/lib/api";
import Link from "@/components/kit/Link";
import { nodeHealth } from "@/lib/schemas/estate";
import NodeHealthCharts from "./NodeHealthCharts";

const WINDOW_LABEL: Record<number, string> = { 24: "24 h", 168: "7 d", 720: "30 d" };

/**
 * The Health card of location-view: a node's numbers over time (db/195 samples, as the watching agents read
 * them) and its open alerts. Server-side like every read (lib/api.ts): GET /locations/health.php, not a screen
 * view. The window travels in the page's own query (?health=24|168|720), so a range is a link, not a fetch.
 */
export default async function NodeHealthCard({ locationId, hours, path }: { locationId: number; hours: number; path: string }) {
  let data;
  try {
    const body = await apiGet<{ data: unknown }>(`/locations/health.php?id=${locationId}&hours=${hours}`);
    data = nodeHealth.parse(body.data);
  } catch (err) {
    if (!(err instanceof ApiError)) throw err;
    return (
      <div className="card" id="location-health-card">
        <div className="card-header"><h5 className="card-title">Health</h5></div>
        <div className="card-body text-muted fs-13">{err.message}</div>
      </div>
    );
  }

  return (
    <div className="card" id="location-health-card">
      <div className="card-header d-flex align-items-center justify-content-between">
        <h5 className="card-title mb-0">Health</h5>
        <div className="btn-group btn-group-sm" role="group" aria-label="Window">
          {data.windows.map((w) => (
            <Link key={w} href={`${path}?health=${w}`} id={`location-health-${w}`}
                  className={`btn ${w === data.hours ? "btn-primary" : "btn-light-brand"}`}
                  aria-current={w === data.hours ? "true" : undefined}>
              {WINDOW_LABEL[w] ?? `${w} h`}
            </Link>
          ))}
        </div>
      </div>
      <div className="card-body">
        {data.alerts.length > 0 && (
          <ul className="list-unstyled mb-3" id="location-health-alerts">
            {data.alerts.map((a) => {
              const c = a.severity === "critical" ? "danger" : "warning";
              return (
                <li key={a.id} className="d-flex align-items-center gap-2 mb-1 fs-13">
                  <span className={`badge bg-soft-${c} text-${c}`}>{a.severity}</span>
                  <span>{a.title}</span>
                  {a.ts !== null && <span className="text-muted fs-12">since {a.ts}</span>}
                </li>
              );
            })}
          </ul>
        )}
        {data.panels.length === 0 ? (
          <p className="text-muted fs-13 mb-0" id="location-health-empty">
            No samples in this window yet: a node records one every {Math.round(data.sampled_every_s / 60)} minutes after its first report.
          </p>
        ) : (
          <NodeHealthCharts panels={data.panels} from={data.from} to={data.to} hours={data.hours} sampledEverySeconds={data.sampled_every_s} />
        )}
      </div>
    </div>
  );
}
