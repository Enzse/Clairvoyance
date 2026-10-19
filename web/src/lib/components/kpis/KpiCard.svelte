<script lang="ts">
	import { formatValue, joinNames } from '$lib/format';
	import { shortRules } from '$lib/kpi-form';
	import { GRAIN_NAMES } from '$lib/period';
	import { term } from '$lib/terms.svelte';
	import type { Kpi } from '$lib/types';
	import { units } from '$lib/units.svelte';
	import Icon from '../Icon.svelte';
	import { kpiIcon } from './shared';

	/** One KPI at a glance: what it is, how it adds up, its target and who reports it. */
	let { kpi, kpis, selected = false, fresh = false, onclick }: { kpi: Kpi; kpis: Kpi[]; selected?: boolean; fresh?: boolean; onclick: () => void } = $props();

	const weightName = $derived(kpis.find((k) => k.id === kpi.weight_kpi_id)?.name);
	const reach = $derived(
		kpi.scope_unit_ids.length
			? `Only ${joinNames(kpi.scope_unit_ids.map((id) => units.get(id)?.name ?? '?'))}`
			: `Every ${term.one}`
	);
</script>

<button
	class="group flex h-full w-full flex-col rounded-xl bg-surface p-4 text-left shadow-sm ring-1 transition-[box-shadow,transform] duration-200 hover:-translate-y-0.5 hover:shadow-pop focus-visible:ring-2 focus-visible:ring-chart motion-reduce:hover:translate-y-0
		{selected ? 'ring-2 ring-chart' : 'ring-line'} {fresh ? 'animate-[flash_1.6s_ease-out]' : ''} {kpi.is_active ? '' : 'opacity-70'}"
	aria-pressed={selected}
	{onclick}
>
	<div class="flex w-full items-start gap-3">
		<span class="grid size-9 shrink-0 place-items-center rounded-lg {kpi.is_active ? 'bg-accent-wash text-accent' : 'bg-surface-2 text-muted'}">
			<Icon name={kpi.is_active ? kpiIcon(kpi) : 'pause'} size={17} />
		</span>
		<div class="min-w-0 flex-1">
			<h3 class="truncate font-semibold">{kpi.name}</h3>
			<p class="text-xs text-muted">{GRAIN_NAMES[kpi.frequency].adjective}{kpi.is_active ? '' : ' · paused'}</p>
		</div>
		<Icon name="chevron-right" size={16} class="mt-1 text-muted transition-transform group-hover:translate-x-0.5" />
	</div>

	<p class="mt-3 text-sm text-ink-2">{shortRules(kpi, term.many, weightName)}</p>

	<div class="mt-auto flex flex-wrap items-center gap-2 pt-4 text-xs">
		{#if kpi.direction === 'none'}
			<span class="rounded-full bg-surface-2 px-2.5 py-1 text-ink-2 ring-1 ring-line">Tracked, no target</span>
		{:else if kpi.target != null}
			<span class="inline-flex items-center gap-1 rounded-full bg-good-wash px-2.5 py-1 font-medium text-good-ink">
				<Icon name={kpi.direction === 'higher_is_better' ? 'arrow-up' : 'arrow-down'} size={12} />
				Target {formatValue(kpi, kpi.target)}
			</span>
		{:else}
			<span class="rounded-full border border-dashed border-line-strong px-2.5 py-1 text-ink-2">No target yet</span>
		{/if}
		{#if kpi.unit_target_count}
			<span class="text-muted">+{kpi.unit_target_count} for some {term.many}</span>
		{/if}
		<span class="ml-auto inline-flex min-w-0 items-center gap-1 text-muted"><Icon name="users" size={12} /> <span class="truncate">{reach}</span></span>
	</div>
</button>
