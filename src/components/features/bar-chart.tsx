"use client";

/**
 * Single-series bar chart. One hue (brand blue) because each chart shows one
 * measure; the title names it, so no legend is needed. Hover shows a tooltip,
 * and a table view gives the same numbers without relying on the chart.
 */
import {
  Bar,
  BarChart as RBarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import type { Datum } from "@/lib/analytics";

const BAR = "#2747bd";
const AXIS = "#64748b";
const GRID = "#e2e6ef";

export function BarChart({
  title,
  description,
  data,
  layout = "horizontal-bars",
  unit,
}: {
  title: string;
  description: string;
  data: Datum[];
  layout?: "horizontal-bars" | "columns";
  unit: string;
}) {
  const horizontal = layout === "horizontal-bars";
  const height = horizontal ? Math.max(180, data.length * 34 + 40) : 240;
  const id = title.toLowerCase().replace(/[^a-z0-9]+/g, "-");

  return (
    <figure className="card p-5" aria-labelledby={`${id}-title`}>
      <figcaption>
        <h3 id={`${id}-title`} className="font-bold">
          {title}
        </h3>
        <p className="text-muted mt-0.5 text-xs">{description}</p>
      </figcaption>
      <div
        className="mt-4"
        style={{ height }}
        role="img"
        aria-label={`${title}: ${data.map((d) => `${d.label} ${d.value}`).join(", ")}`}
      >
        <ResponsiveContainer width="100%" height="100%">
          <RBarChart
            data={data}
            layout={horizontal ? "vertical" : "horizontal"}
            margin={{ top: 4, right: 16, bottom: 4, left: horizontal ? 8 : -16 }}
            barCategoryGap={horizontal ? 6 : 8}
          >
            <CartesianGrid
              stroke={GRID}
              vertical={!horizontal}
              horizontal={horizontal ? false : true}
            />
            {horizontal ? (
              <>
                <XAxis
                  type="number"
                  allowDecimals={false}
                  tick={{ fill: AXIS, fontSize: 12 }}
                  axisLine={false}
                  tickLine={false}
                />
                <YAxis
                  type="category"
                  dataKey="label"
                  width={130}
                  tick={{ fill: "#334155", fontSize: 12 }}
                  axisLine={false}
                  tickLine={false}
                />
              </>
            ) : (
              <>
                <XAxis
                  dataKey="label"
                  tick={{ fill: AXIS, fontSize: 11 }}
                  axisLine={{ stroke: GRID }}
                  tickLine={false}
                  interval={0}
                  angle={-30}
                  textAnchor="end"
                  height={50}
                />
                <YAxis
                  allowDecimals={false}
                  tick={{ fill: AXIS, fontSize: 12 }}
                  axisLine={false}
                  tickLine={false}
                />
              </>
            )}
            <Tooltip
              cursor={{ fill: "rgba(39, 71, 189, 0.06)" }}
              formatter={(value) => [`${value} ${unit}`, ""]}
              separator=""
              contentStyle={{ borderRadius: 12, border: `1px solid ${GRID}`, fontSize: 13 }}
            />
            <Bar
              dataKey="value"
              fill={BAR}
              radius={horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]}
              maxBarSize={22}
              isAnimationActive={false}
            />
          </RBarChart>
        </ResponsiveContainer>
      </div>
      <details className="mt-3 text-sm">
        <summary className="text-brand-700 cursor-pointer font-semibold">View as table</summary>
        <table className="mt-2 w-full text-left text-sm">
          <thead>
            <tr className="border-line text-muted border-b">
              <th scope="col" className="py-1.5 font-semibold">
                Category
              </th>
              <th scope="col" className="py-1.5 text-right font-semibold">
                {unit}
              </th>
            </tr>
          </thead>
          <tbody>
            {data.map((d) => (
              <tr key={d.label} className="border-line/60 border-b">
                <td className="py-1.5">{d.label}</td>
                <td className="py-1.5 text-right tabular-nums">{d.value}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </details>
    </figure>
  );
}
