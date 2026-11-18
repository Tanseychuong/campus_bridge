/*
 * A simple horizontal bar chart drawn with SVG, plus the same numbers as a table
 * (for screen readers and anyone who prefers exact figures).
 * data: [{ label: 'Tech', value: 3 }, ...]
 */
export function BarChart({ title, description, data, unit }) {
    const max = Math.max(1, ...data.map((row) => row.value));
    const rowHeight = 30;
    const labelWidth = 150;
    const chartWidth = 300;
    const summary = `${title}: ${data.map((row) => `${row.label} ${row.value}`).join(', ')}`;

    return (
        <figure className="card chart">
            <figcaption>
                <h3 className="chart-title">{title}</h3>
                <p className="muted small">{description}</p>
            </figcaption>
            <svg className="chart-svg" viewBox={`0 0 ${labelWidth + chartWidth + 40} ${data.length * rowHeight}`} role="img" aria-label={summary}>
                {data.map((row, index) => {
                    const y = index * rowHeight;
                    const barWidth = (row.value / max) * chartWidth;
                    return (
                        <g key={row.label}>
                            <text x={labelWidth - 8} y={y + 19} textAnchor="end" className="chart-label">{row.label}</text>
                            <rect x={labelWidth} y={y + 6} width={Math.round(barWidth * 10) / 10} height="18" rx="4" className="chart-bar">
                                <title>{`${row.label}: ${row.value} ${unit}`}</title>
                            </rect>
                            <text x={labelWidth + barWidth + 6} y={y + 19} className="chart-value">{row.value}</text>
                        </g>
                    );
                })}
            </svg>
            <details className="chart-table">
                <summary>View as table</summary>
                <table>
                    <thead><tr><th scope="col">Category</th><th scope="col">{unit.charAt(0).toUpperCase() + unit.slice(1)}</th></tr></thead>
                    <tbody>
                        {data.map((row) => <tr key={row.label}><td>{row.label}</td><td>{row.value}</td></tr>)}
                    </tbody>
                </table>
            </details>
        </figure>
    );
}
