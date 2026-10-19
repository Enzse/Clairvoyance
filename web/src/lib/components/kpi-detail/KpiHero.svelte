<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion, Tween } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import { formatDelta, formatValue } from '$lib/format';
	import { term } from '$lib/terms.svelte';
	import type { Change, Kpi, PeriodResult } from '$lib/types';
	import Icon from '../Icon.svelte';
	import { kpiIcon } from '../kpis/shared';
	import ProgressRing from '../ProgressRing.svelte';
	import StatusBadge from '../StatusBadge.svelte';

	/** The KPI's figure for the period: the value counting up, then target, change and reports. */
	let {
		kpi,
		where,
		current,
		previous,
		change,
		view = $bindable()
	}: { kpi: Kpi; where: string; current: PeriodResult; previous: PeriodResult | null; change: Change | null; view: 'chart' | 'table' } = $props();

	const move = $derived(prefersReducedMotion.current ? 0 : 1);
	const shown = new Tween(0, { duration: 900, easing: cubicOut });
	$effect(() => {
		shown.set(current.value ?? 0, move ? undefined : { duration: 0 });
	});

	const tone = $derived(change?.direction === 'better' ? 'text-good-ink' : change?.direction === 'worse' ? 'text-bad-ink' : 'text-ink-2');
	const tiles = $derived(1 + (kpi.direction !== 'none' ? 1 : 0));
</script>

<div class="flex flex-wrap items-start gap-x-8 gap-y-5">
	<div class="flex min-w-0 items-start gap-4">
		<span class="grid size-12 shrink-0 place-items-center rounded-xl bg-accent-wash text-accent"><Icon name={kpiIcon(kpi)} size={22} /></span>
		<div>
			<h2 id="trend-heading" class="text-sm text-ink-2">{where}, {current.period_label}</h2>
			<p class="num mt-1 text-[44px] leading-none font-semibold tracking-[-0.03em]">
				{current.value === null ? '–' : formatValue(kpi, shown.current)}
			</p>
			{#if kpi.direction !== 'none'}
				<div class="mt-2.5"><StatusBadge status={current.value === null ? null : current.status} empty={current.value === null ? 'No data' : 'No target'} /></div>
			{/if}
		</div>
	</div>

	<dl class="grid min-w-0 flex-1 basis-80 grid-cols-2 gap-3 {tiles > 1 ? 'sm:grid-cols-3' : ''}">
		{#if kpi.direction !== 'none'}
			<div class="rounded-xl bg-surface-2 px-4 py-3 ring-1 ring-line" in:fly={{ y: 8 * move, duration: 300, easing: cubicOut }}>
				<dt class="flex items-center gap-1.5 text-sm text-ink-2"><Icon name="target" size={13} /> Target</dt>
				<dd class="num mt-1 text-lg font-semibold">{formatValue(kpi, current.target)}</dd>
			</div>
		{/if}
		<div class="rounded-xl bg-surface-2 px-4 py-3 ring-1 ring-line" in:fly={{ y: 8 * move, duration: 300, delay: 70 * move, easing: cubicOut }}>
			<dt class="text-sm text-ink-2">Since {previous?.period_label ?? 'last time'}</dt>
			{#if change}
				<dd class="num mt-1 flex items-center gap-1 text-lg font-semibold {tone}">
					{#if change.value !== 0}<Icon name={change.value > 0 ? 'arrow-up' : 'arrow-down'} size={15} stroke={2.5} />{/if}
					{formatDelta(kpi, change.value)}
				</dd>
				{#if change.basis === 'like_for_like'}<dd class="text-xs text-muted">{change.units_compared} of {change.units_total} {term.many} that reported both</dd>{/if}
			{:else}
				<dd class="mt-1 text-lg text-muted">–</dd>
			{/if}
		</div>
		<div class="col-span-2 flex items-center gap-3 rounded-xl bg-surface-2 px-4 py-3 ring-1 ring-line sm:col-span-1" in:fly={{ y: 8 * move, duration: 300, delay: 140 * move, easing: cubicOut }}>
			<ProgressRing value={current.reported} total={Math.max(current.expected, 1)} from={0} size={40} />
			<div>
				<dt class="text-sm text-ink-2">Reports in</dt>
				<dd class="text-sm font-medium">{current.reported === current.expected ? 'Everyone' : `${current.expected - current.reported} still to come`}</dd>
			</div>
		</div>
	</dl>

	<div class="flex rounded-lg bg-surface-2 p-0.5 ring-1 ring-line" role="group" aria-label="View">
		{#each [['chart', 'Chart', 'chart'], ['table', 'Table', 'table']] as [value, text, icon] (value)}
			<button
				class="flex items-center gap-1.5 rounded-md px-3 py-1 text-sm transition-colors {view === value ? 'bg-surface font-semibold text-ink shadow-sm' : 'text-ink-2 hover:text-ink'}"
				aria-pressed={view === value}
				onclick={() => (view = value as 'chart' | 'table')}
			>
				<Icon name={icon as 'chart' | 'table'} size={13} />{text}
			</button>
		{/each}
	</div>
</div>
