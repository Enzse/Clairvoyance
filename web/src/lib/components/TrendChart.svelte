<script lang="ts">
	import { linePath, niceScale, tickFormatter } from '$lib/chart';
	import { formatValue } from '$lib/format';
	import { axisLabel } from '$lib/period';
	import type { Frequency, Kpi, PeriodResult } from '$lib/types';
	import StatusBadge from './StatusBadge.svelte';

	/**
	 * One KPI over time: the value as a 2px line, the target as a thin ink line, and hollow
	 * markers where not every unit reported. A crosshair snaps to the nearest period; arrow
	 * keys do the same for keyboard users.
	 */
	let { periods, kpi, grain }: { periods: PeriodResult[]; kpi: Kpi; grain: Frequency } = $props();

	let width = $state(720);
	const height = 260;
	const axisBand = 28;
	// The right margin fits the latest value's label (₱5,823,600 needs more than 1,234)
	const endLabel = $derived.by(() => {
		const last = periods.findLast((p) => p.value !== null);
		return last ? formatValue(kpi, last.value) : '';
	});
	const margin = $derived({ top: 16, right: Math.max(76, Math.ceil(endLabel.length * 8.5) + 18), bottom: 0, left: 52 });

	const plotW = $derived(Math.max(width - margin.left - margin.right, 40));
	const plotH = $derived(height - margin.top - margin.bottom);

	const scale = $derived(
		niceScale(periods.flatMap((p) => [p.value, p.target]).filter((v): v is number => v !== null))
	);
	const step = $derived(periods.length > 1 ? plotW / (periods.length - 1) : 0);
	const x = (i: number) => margin.left + (periods.length > 1 ? i * step : plotW / 2);
	const y = (v: number) => margin.top + (1 - (v - scale.min) / (scale.max - scale.min || 1)) * plotH;

	const valuePath = $derived(linePath(periods.map((p, i) => (p.value === null ? null : { x: x(i), y: y(p.value) }))));
	const targetPath = $derived(linePath(periods.map((p, i) => (p.target === null ? null : { x: x(i), y: y(p.target) }))));
	const lastIndex = $derived(periods.findLastIndex((p) => p.value !== null));
	const lastTargetIndex = $derived(periods.findLastIndex((p) => p.target !== null));

	// Thin out axis labels so they never collide (~56px each)
	const labelEvery = $derived(Math.max(1, Math.ceil(periods.length / Math.max(1, Math.floor(plotW / 56)))));

	const tickText = $derived(tickFormatter(scale));

	let active = $state<number | null>(null);

	function pick(event: PointerEvent) {
		const rect = (event.currentTarget as SVGElement).getBoundingClientRect();
		const px = ((event.clientX - rect.left) / rect.width) * width;
		const i = periods.length > 1 ? Math.round((px - margin.left) / step) : 0;
		active = Math.min(periods.length - 1, Math.max(0, i));
	}

	function onKey(event: KeyboardEvent) {
		const moves: Record<string, number> = { ArrowLeft: -1, ArrowRight: 1, Home: -Infinity, End: Infinity };
		if (!(event.key in moves)) return;
		event.preventDefault();
		const from = active ?? lastIndex;
		active = Math.min(periods.length - 1, Math.max(0, from + moves[event.key]));
	}

	const point = $derived(active === null ? null : periods[active]);
	const tooltipLeft = $derived(active === null ? 0 : x(active));
</script>

<div class="relative" bind:clientWidth={width}>
	<svg
		width="100%"
		height={height + axisBand}
		viewBox="0 0 {width} {height + axisBand}"
		role="img"
		aria-label="{kpi.name} by period. Use the table view for exact values."
		class="block touch-none select-none"
	>
		<!-- Gridlines and y ticks: solid hairlines, recessive -->
		{#each scale.ticks as tick (tick)}
			<line x1={margin.left} x2={margin.left + plotW} y1={y(tick)} y2={y(tick)} class="stroke-line" stroke-width="1" />
			<text x={margin.left - 10} y={y(tick)} dy="0.32em" text-anchor="end" class="fill-muted num text-xs">{tickText(tick)}</text>
		{/each}

		<!-- X axis labels -->
		{#each periods as p, i (p.period_start)}
			{#if i % labelEvery === 0 || i === periods.length - 1}
				<text x={x(i)} y={height + 18} text-anchor="middle" class="fill-muted text-xs">
					{axisLabel(p.period_start, grain, periods[i - labelEvery]?.period_start)}
				</text>
			{/if}
		{/each}

		<!-- Target -->
		{#if targetPath}
			<path d={targetPath} fill="none" class="stroke-ink-2" stroke-width="1.25" />
			{#if lastTargetIndex >= 0}
				<text x={x(lastTargetIndex) + 8} y={y(periods[lastTargetIndex].target!)} dy="0.32em" class="fill-ink-2 text-xs">Target</text>
			{/if}
		{/if}

		<!-- Value line -->
		<path d={valuePath} fill="none" class="stroke-chart" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

		<!-- Incomplete periods: hollow markers -->
		{#each periods as p, i (p.period_start)}
			{#if p.value !== null && p.reported < p.expected}
				<circle cx={x(i)} cy={y(p.value)} r="4" class="fill-surface stroke-chart" stroke-width="2" />
			{/if}
		{/each}

		<!-- Crosshair -->
		{#if point}
			<line x1={x(active!)} x2={x(active!)} y1={margin.top} y2={height} class="stroke-line-strong" stroke-width="1" />
			{#if point.value !== null}
				<circle cx={x(active!)} cy={y(point.value)} r="5" class="fill-chart stroke-surface" stroke-width="2" />
			{/if}
		{/if}

		<!-- Latest value: end dot with a surface ring, labelled -->
		{#if lastIndex >= 0 && active === null}
			{@const last = periods[lastIndex]}
			<!-- Still hollow when not everyone has reported yet -->
			{#if last.reported >= last.expected}
				<circle cx={x(lastIndex)} cy={y(last.value!)} r="4.5" class="fill-chart stroke-surface" stroke-width="2" />
			{/if}
			<text x={x(lastIndex) + 10} y={y(last.value!)} dy="0.32em" class="fill-ink text-sm font-semibold">{formatValue(kpi, last.value)}</text>
		{/if}

		<!-- Hit area: the whole plot, so the pointer only needs the right x -->
		<rect
			x={margin.left - step / 2}
			y={margin.top}
			width={plotW + step}
			height={plotH}
			fill="transparent"
			tabindex="0"
			role="slider"
			aria-label="Period"
			aria-valuemin={0}
			aria-valuemax={periods.length - 1}
			aria-valuenow={active ?? lastIndex}
			aria-valuetext={point ? `${point.period_label}: ${formatValue(kpi, point.value)}` : undefined}
			class="cursor-crosshair outline-none"
			onpointermove={pick}
			onpointerdown={pick}
			onpointerleave={() => (active = null)}
			onfocus={() => (active = lastIndex >= 0 ? lastIndex : 0)}
			onblur={() => (active = null)}
			onkeydown={onKey}
		/>
	</svg>

	{#if point}
		<div
			class="pointer-events-none absolute top-2 z-10 w-52 rounded-lg bg-surface p-3 shadow-pop"
			style:left="{Math.min(Math.max(tooltipLeft - 104, 0), width - 208)}px"
		>
			<p class="text-sm text-ink-2">{point.period_label}</p>
			<p class="mt-0.5 text-lg font-semibold">{formatValue(kpi, point.value)}</p>
			<dl class="mt-2 space-y-1 text-sm">
				{#if point.target !== null}
					<div class="flex items-center gap-2">
						<span class="h-px w-3 bg-ink-2"></span>
						<dt class="text-ink-2">Target</dt>
						<dd class="ml-auto num">{formatValue(kpi, point.target)}</dd>
					</div>
				{/if}
				<div class="flex justify-between">
					<dt class="text-ink-2">Reports in</dt>
					<dd class="num">{point.reported} of {point.expected}</dd>
				</div>
			</dl>
			{#if kpi.direction !== 'none' && point.value !== null}
				<div class="mt-2 border-t border-line pt-2"><StatusBadge status={point.status} /></div>
			{/if}
		</div>
	{/if}
</div>
