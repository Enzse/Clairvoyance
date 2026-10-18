<script lang="ts">
	import { linePath, trimLeading } from '$lib/chart';

	/**
	 * Trend in the de-emphasis ink with the current period in the accent (stat-tile contract).
	 * Starts at the first period with data. Decorative next to the row's numbers; the full
	 * chart lives on the KPI page.
	 */
	let { values: all, width = 112, height = 30, label }: { values: (number | null)[]; width?: number; height?: number; label: string } = $props();

	const values = $derived(trimLeading(all, (v) => v !== null));

	const pad = 4;
	const present = $derived(values.filter((v): v is number => v !== null));
	const geometry = $derived.by(() => {
		if (!present.length) return null;
		const lo = Math.min(...present);
		const hi = Math.max(...present);
		const span = hi - lo || 1;
		const step = values.length > 1 ? (width - 2 * pad) / (values.length - 1) : 0;
		const points = values.map((v, i) =>
			v === null ? null : { x: pad + i * step, y: hi === lo ? height / 2 : pad + (1 - (v - lo) / span) * (height - 2 * pad) }
		);
		return { path: linePath(points), last: points[points.length - 1] };
	});
</script>

{#if geometry}
	<svg {width} {height} viewBox="0 0 {width} {height}" role="img" aria-label={label} class="overflow-visible">
		<path d={geometry.path} fill="none" class="stroke-muted" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
		{#if geometry.last}
			<circle cx={geometry.last.x} cy={geometry.last.y} r="3.5" class="fill-chart stroke-surface" stroke-width="2" />
		{/if}
	</svg>
{:else}
	<span class="text-sm text-muted">No data</span>
{/if}
