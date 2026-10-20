<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion, Tween } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import { formatDelta, formatValue } from '$lib/format';
	import { GRAIN_NAMES } from '$lib/period';
	import { term } from '$lib/terms.svelte';
	import type { Change, Kpi, PeriodResult, Series } from '$lib/types';
	import HoverCard from '../HoverCard.svelte';
	import Icon from '../Icon.svelte';
	import { kpiIcon } from '../kpis/shared';
	import ProgressRing from '../ProgressRing.svelte';
	import StatusBadge from '../StatusBadge.svelte';

	/**
	 * The KPI's figure for the period: the value counting up, then target, change and reports.
	 * Hovering the reports says who's still to come; hovering a partial change, who it leaves out.
	 */
	let {
		kpi,
		where,
		current,
		previous,
		change,
		missing,
		view = $bindable()
	}: {
		kpi: Kpi;
		where: string;
		current: PeriodResult;
		previous: PeriodResult | null;
		change: Change | null;
		missing: Series['missing'];
		view: 'chart' | 'table';
	} = $props();

	const LISTED = 8;
	const tile = 'rounded-xl bg-surface-2 px-4 py-3 ring-1 ring-line';
	const hoverable = 'transition-shadow hover:ring-line-strong';
	const partial = $derived(change?.basis === 'like_for_like' && change.left_out.length > 0);
	const unitOf = $derived(GRAIN_NAMES[kpi.frequency].noun.toLowerCase());

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

	<div class="grid min-w-0 flex-1 basis-80 grid-cols-2 gap-3 {tiles > 1 ? 'sm:grid-cols-3' : ''}">
		{#if kpi.direction !== 'none'}
			<div class={tile} in:fly={{ y: 8 * move, duration: 300, easing: cubicOut }}>
				<p class="flex items-center gap-1.5 text-sm text-ink-2"><Icon name="target" size={13} /> Target</p>
				<p class="num mt-1 text-lg font-semibold">{formatValue(kpi, current.target)}</p>
			</div>
		{/if}
		<div in:fly={{ y: 8 * move, duration: 300, delay: 70 * move, easing: cubicOut }}>
			{#if partial && change}
				<HoverCard class="{tile} {hoverable} h-full">
					{@render since()}
					{#snippet card()}
						<p class="text-ink-2">
							Only the {change.units_compared} {term.many} that reported both {previous?.period_label} and {current.period_label} are compared, so a missing report doesn't look like a drop.
						</p>
						<p class="mt-2"><span class="text-ink-2">Not counted:</span> {change.left_out.join(', ')}</p>
					{/snippet}
				</HoverCard>
			{:else}
				<div class="{tile} h-full">{@render since()}</div>
			{/if}
		</div>
		<div class="col-span-2 sm:col-span-1" in:fly={{ y: 8 * move, duration: 300, delay: 140 * move, easing: cubicOut }}>
			{#if missing.length}
				<HoverCard class="{tile} {hoverable} flex h-full items-center" align="end">
					{@render reports()}
					{#snippet card()}
						<p class="text-xs font-medium text-muted">Still to come for {current.period_label}</p>
						<ul class="mt-2 space-y-1">
							{#each missing.slice(0, LISTED) as m (m.unit_id)}
								<li class="flex items-baseline justify-between gap-3">
									<span class="truncate">{m.name}</span>
									{#if m.expected > 1}<span class="num shrink-0 text-xs text-ink-2">{m.reported} of {m.expected} {unitOf}s</span>{/if}
								</li>
							{/each}
						</ul>
						{#if missing.length > LISTED}<p class="mt-1.5 text-xs text-ink-2">and {missing.length - LISTED} more</p>{/if}
					{/snippet}
				</HoverCard>
			{:else}
				<div class="{tile} flex h-full items-center">{@render reports()}</div>
			{/if}
		</div>
	</div>

	{#snippet since()}
		<span class="block text-sm text-ink-2">Since {previous?.period_label ?? 'last time'}</span>
		{#if change}
			<span class="num mt-1 flex items-center gap-1 text-lg font-semibold {tone}">
				{#if change.value !== 0}<Icon name={change.value > 0 ? 'arrow-up' : 'arrow-down'} size={15} stroke={2.5} />{/if}
				{formatDelta(kpi, change.value)}
			</span>
			{#if change.basis === 'like_for_like'}<span class="block text-xs text-muted">{change.units_compared} of {change.units_total} {term.many} that reported both</span>{/if}
		{:else}
			<span class="mt-1 block text-lg text-muted">–</span>
		{/if}
	{/snippet}

	{#snippet reports()}
		<span class="flex items-center gap-3">
			<ProgressRing value={current.reported} total={Math.max(current.expected, 1)} from={0} size={40} />
			<span>
				<span class="block text-sm text-ink-2">Reports in</span>
				<span class="block text-sm font-medium">{current.reported === current.expected ? 'Everyone' : `${current.expected - current.reported} still to come`}</span>
			</span>
		</span>
	{/snippet}

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
