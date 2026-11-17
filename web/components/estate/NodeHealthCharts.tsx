"use client";

import {
  Chart as ChartJS,
  Legend,
  LinearScale,
  LineController,
  LineElement,
  PointElement,
  Tooltip,
  type ChartOptions,
} from "chart.js";
import { useEffect, useState } from "react";
import { Line } from "react-chartjs-2";
import type { NodeHealth } from "@/lib/schemas/estate";

// Only what a line over time needs; a linear x axis in epoch ms keeps a date adapter out of the bundle.
ChartJS.register(LineController, LineElement, PointElement, LinearScale, Tooltip, Legend);

type Series = NodeHealth["panels"][number]["series"][number];

const PALETTE = ["#3454d1", "#25b865", "#e49e3d", "#d13b4c", "#17a2b8", "#6f42c1", "#8a8f98", "#c2185b"];
const UNIT: Record<string, { name: string; suffix: string }> = {
  pct: { name: "percent", suffix: "%" },
  load: { name: "load", suffix: "" },
  s: { name: "seconds", suffix: " s" },
  MB: { name: "MB", suffix: " MB" },
  days: { name: "days", suffix: " d" },
  count: { name: "count", suffix: "" },
  xid: { name: "transaction ids", suffix: "" },
};

function tick(ms: number, hours: number): string {
  const d = new Date(ms);
  return hours <= 24
    ? d.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" })
    : d.toLocaleDateString(undefined, { month: "short", day: "numeric" });
}

/** The shell's skin (components/shell/Shell.tsx toggles `app-skin-dark` on <html>), followed live. */
function useDarkSkin(): boolean {
  const [dark, setDark] = useState(false);
  useEffect(() => {
    const el = document.documentElement;
    const read = () => setDark(el.classList.contains("app-skin-dark"));
    read();
    const obs = new MutationObserver(read);
    obs.observe(el, { attributes: true, attributeFilter: ["class"] });
    return () => obs.disconnect();
  }, []);
  return dark;
}

/**
 * A series' points with a break wherever the node went quiet: a gap wider than three buckets (or three samples,
 * whichever is wider) gets a null, so an offline day is a hole in the line, not a straight line across it.
 */
function withGaps(points: [number, number][], gapMs: number): { x: number; y: number | null }[] {
  const out: { x: number; y: number | null }[] = [];
  points.forEach(([x, y], i) => {
    const prev = points[i - 1];
    if (prev !== undefined && x - prev[0] > gapMs) out.push({ x: prev[0] + 1, y: null });
    out.push({ x, y });
  });
  return out;
}

/** A panel's series by unit, in first-seen order: each unit is its own chart, so no series is drawn off its scale. */
function byUnit(series: Series[]): [string, Series[]][] {
  const groups = new Map<string, Series[]>();
  for (const s of series) groups.set(s.unit, [...(groups.get(s.unit) ?? []), s]);
  return [...groups.entries()];
}

/** The charts of the Health card (NodeHealthCard): per panel, one small line chart per unit. Percentages draw on 0–100. */
export default function NodeHealthCharts({ panels, from, to, hours, sampledEverySeconds }: {
  panels: NodeHealth["panels"]; from: number; to: number; hours: number; sampledEverySeconds: number;
}) {
  const dark = useDarkSkin();
  const gapMs = 3 * Math.max(sampledEverySeconds * 1000, (to - from) / 300);
  const text = dark ? "rgba(240, 237, 232, 0.7)" : "#5b6472";
  const grid = dark ? "rgba(240, 237, 232, 0.08)" : "rgba(0, 0, 0, 0.08)";

  const chart = (series: Series[], unit: string) => {
    const suffix = UNIT[unit]?.suffix ?? "";
    const options: ChartOptions<"line"> = {
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      interaction: { mode: "nearest", axis: "x", intersect: false },
      elements: { point: { radius: 0, hitRadius: 6 }, line: { borderWidth: 2, tension: 0.2 } },
      scales: {
        x: { type: "linear", min: from, max: to, grid: { color: grid }, ticks: { color: text, maxTicksLimit: 6, callback: (v) => tick(Number(v), hours) } },
        y: unit === "pct"
          ? { min: 0, max: 100, grid: { color: grid }, ticks: { color: text, callback: (v) => `${v}%` } }
          : { beginAtZero: true, grid: { color: grid }, ticks: { color: text, precision: 0 } },
      },
      plugins: {
        legend: { position: "bottom", labels: { color: text, boxWidth: 10, font: { size: 11 } } },
        tooltip: {
          callbacks: {
            title: (items) => (items[0] ? new Date(Number(items[0].parsed.x)).toLocaleString() : ""),
            label: (item) => `${series[item.datasetIndex]?.label ?? ""}: ${item.parsed.y}${suffix}`,
          },
        },
      },
    };
    const data = {
      datasets: series.map((s, i) => ({
        label: s.label,
        data: withGaps(s.points, gapMs),
        spanGaps: false,
        borderColor: PALETTE[i % PALETTE.length],
        backgroundColor: PALETTE[i % PALETTE.length],
      })),
    };
    return <Line options={options} data={data} />;
  };

  return (
    <>
      {panels.map((p) => (
        <section key={p.key} id={`location-health-panel-${p.key}`} className="mb-3">
          <h6 className="fs-13 mb-2">{p.label}</h6>
          <div className="row g-3">
            {byUnit(p.series).map(([unit, series]) => (
              <div className="col-lg-6" key={unit}>
                <div style={{ position: "relative", height: 180 }} role="img"
                     aria-label={`${p.label}, ${UNIT[unit]?.name ?? unit}: ${series.map((s) => s.label).join(", ")}, over the last ${hours} hours`}>
                  {chart(series, unit)}
                </div>
              </div>
            ))}
          </div>
        </section>
      ))}
    </>
  );
}
