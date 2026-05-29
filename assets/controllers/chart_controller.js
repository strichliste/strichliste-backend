import { Controller } from '@hotwired/stimulus';
import {
  Chart,
  LineController,
  BarController,
  LineElement,
  BarElement,
  PointElement,
  CategoryScale,
  LinearScale,
  Tooltip,
} from 'chart.js';

Chart.register(
  LineController,
  BarController,
  LineElement,
  BarElement,
  PointElement,
  CategoryScale,
  LinearScale,
  Tooltip,
);

/*
 * Renders the metrics page's daily activity. Two variants:
 *   - balance: bars (charged green, spent red) + line (net) — straight segments
 *   - users:   single green bar series of distinct users per day
 *
 * Variant is read from `data-chart-variant-value`; defaults to "balance".
 * Data comes from a <script type="application/json"> island identified by
 * `data-chart-data-id-value`.
 */
export default class extends Controller {
  static values = { dataId: String, variant: String };

  connect() {
    const source = document.getElementById(this.dataIdValue);
    if (!source) return;

    // Days come newest-first from the backend; SPA renders newest on the
    // left, so we render in source order (no reverse).
    let days;
    try {
      days = JSON.parse(source.textContent);
    } catch (e) {
      return;
    }
    if (!Array.isArray(days) || days.length === 0) return;

    const labels = days.map((d) => d.date);

    const css = getComputedStyle(document.documentElement);
    const greenText = css.getPropertyValue('--greenText').trim() || '#15803d';
    const redText = css.getPropertyValue('--redText').trim() || '#c62828';
    const text = css.getPropertyValue('--text').trim() || '#343434';
    const surface = css.getPropertyValue('--componentBackgroundLight').trim() || '#fff';

    const reducedMotion =
      window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const variant = this.hasVariantValue ? this.variantValue : 'balance';
    const datasets = variant === 'users'
      ? [{
          label: 'Users',
          data: days.map((d) => d.distinctUsers ?? 0),
          backgroundColor: greenText,
          borderColor: greenText,
          categoryPercentage: 0.8,
          barPercentage: 0.9,
        }]
      : [
          {
            label: 'Charged',
            data: days.map((d) => d.charged / 100),
            backgroundColor: greenText,
            borderColor: greenText,
            order: 2,
            categoryPercentage: 0.8,
            barPercentage: 0.9,
          },
          {
            label: 'Spent',
            data: days.map((d) => d.spent / 100),
            backgroundColor: redText,
            borderColor: redText,
            order: 2,
            categoryPercentage: 0.8,
            barPercentage: 0.9,
          },
          {
            label: 'Net',
            data: days.map((d) => d.balance / 100),
            type: 'line',
            // Use the theme text color so the net line stays visible in dark mode
            // (a hardcoded black line vanished on the dark chart surface).
            borderColor: text,
            backgroundColor: text,
            tension: 0,
            fill: false,
            order: 1,
            pointRadius: 3,
            pointBackgroundColor: surface,
            pointBorderColor: text,
            pointBorderWidth: 1.5,
          },
        ];

    this.chart = new Chart(this.element, {
      type: 'bar',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: reducedMotion ? false : undefined,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: variant === 'users'
              ? { label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y}` }
              : { label: (ctx) => `${ctx.dataset.label}: €${ctx.parsed.y.toFixed(2)}` },
          },
        },
        scales: {
          x: {
            grid: { color: '#ccc', borderDash: [3, 3] },
            ticks: { color: text, maxRotation: 0, autoSkip: true },
          },
          y: {
            grid: { color: '#ccc', borderDash: [3, 3] },
            ticks: { color: text, count: 5 },
          },
        },
      },
    });
  }

  disconnect() {
    if (this.chart) {
      this.chart.destroy();
      this.chart = null;
    }
  }
}
