Pointer interaction 🧪

By default, line and area series are highlighted when the pointer hovers directly over the SVG element (the line stroke or area fill). This can make it difficult to interact with thin lines.

Enabling experimentalFeatures.enablePositionBasedPointerInteraction switches to a pointer-position-based detection that determines the closest series to the cursor. For area series, it detects whether the pointer is inside the filled area. For line series (without area), it finds the series whose curve is closest to the pointer's vertical position.

This uses the same curve interpolation as the rendered line (for example, monotoneX, catmullRom), so the hit detection matches the visual shape.

<LineChart
  {...data}
  experimentalFeatures={{ enablePositionBasedPointerInteraction: true }}
/>


Pie Chart 


Highlight

Add the highlightScope property to a pie series to control highlighting. See Highlighting for details.

Use the [data-faded=true] and [data-highlighted=true] CSS selectors to style arcs when they are faded or highlighted.

CSS works well for changing color, stroke-width, or opacity. To change the size of an arc when highlighted or faded, use the highlighted and faded properties to override innerRadius, outerRadius, or cornerRadius.

Use additionalRadius to add to outerRadius instead of setting an absolute value. A negative value shrinks the arc.

import { PieChart } from '@mui/x-charts/PieChart';
import { desktopOS, valueFormatter } from './webUsageStats';

export default function PieActiveArc() {
  return (
    <PieChart
      series={[
        {
          data: desktopOS,
          highlightScope: { fade: 'global', highlight: 'item' },
          faded: { innerRadius: 30, additionalRadius: -30, color: 'gray' },
          valueFormatter,
        },
      ]}
      height={200}
      width={200}
    />
  );
}

// Data derived from https://gs.statcounter.com/os-market-share/desktop/worldwide/2023
// And https://gs.statcounter.com/os-market-share/mobile/worldwide/2023
// And https://gs.statcounter.com/platform-market-share/desktop-mobile-tablet/worldwide/2023
// For the month of December 2023

export const desktopOS = [
  {
    label: 'Windows',
    value: 72.72,
  },
  {
    label: 'OS X',
    value: 16.38,
  },
  {
    label: 'Linux',
    value: 3.83,
  },
  {
    label: 'Chrome OS',
    value: 2.42,
  },
  {
    label: 'Other',
    value: 4.65,
  },
];

export const mobileOS = [
  {
    label: 'Android',
    value: 70.48,
  },
  {
    label: 'iOS',
    value: 28.8,
  },
  {
    label: 'Other',
    value: 0.71,
  },
];

export const platforms = [
  {
    label: 'Mobile',
    value: 59.12,
  },
  {
    label: 'Desktop',
    value: 40.88,
  },
];

const normalize = (v, v2) => Number.parseFloat(((v * v2) / 100).toFixed(2));

export const mobileAndDesktopOS = [
  ...mobileOS.map((v) => ({
    ...v,
    label: v.label === 'Other' ? 'Other (Mobile)' : v.label,
    value: normalize(v.value, platforms[0].value),
  })),
  ...desktopOS.map((v) => ({
    ...v,
    label: v.label === 'Other' ? 'Other (Desktop)' : v.label,
    value: normalize(v.value, platforms[1].value),
  })),
];

export const valueFormatter = (item) => `${item.value}%`;