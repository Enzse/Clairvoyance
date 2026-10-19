<script lang="ts">
	import type { Snippet } from 'svelte';
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly, slide } from 'svelte/transition';
	import { formatValue } from '$lib/format';
	import { filterRows, type RowFilter } from '$lib/summary';
	import type { ScorecardRow } from '$lib/types';
	import ChangeCell from '../ChangeCell.svelte';
	import Icon from '../Icon.svelte';
	import { kpiIcon } from '../kpis/shared';
	import Sparkline from '../Sparkline.svelte';
	import StatusBadge from '../StatusBadge.svelte';

	/** Every KPI for the period, one row each; the whole row opens the KPI. */
	let {
		rows,
		filter = $bindable(),
		kpiHref,
		actions
	}: { rows: ScorecardRow[]; filter: RowFilter; kpiHref: (kpiId: number) => string; actions?: Snippet } = $props();

	const shown = $derived(filterRows(rows, filter));
	const move = $derived(prefersReducedMotion.current ? 0 : 1);
	const FILTER_WORDS: Record<RowFilter, string> = { all: '', green: 'on target', amber: 'close', red: 'behind', missing: 'still waiting for figures' };
</script>

<div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5 pb-4">
	<div class="flex items-center gap-3">
		<span class="grid size-9 shrink-0 place-items-center rounded-lg bg-accent-wash text-accent"><Icon name="target" size={17} /></span>
		<div>
			<h2 id="kpis-heading" class="font-semibold">KPIs</h2>
			<p class="text-sm text-ink-2">{rows.length} tracked{rows.length ? `, for ${rows[0].period_label}` : ''}. Click one for its trend and breakdown.</p>
		</div>
	</div>
	{#if actions}<div class="flex flex-wrap justify-end gap-2">{@render actions()}</div>{/if}
</div>

{#if filter !== 'all'}
	<div class="flex items-center gap-2 border-t border-line bg-surface-2 px-5 py-2 text-sm" transition:slide={{ duration: 160 }}>
		<Icon name="sliders" size={14} class="text-muted" />
		<span class="text-ink-2">Showing {shown.length} {shown.length === 1 ? 'KPI' : 'KPIs'} {FILTER_WORDS[filter]}</span>
		<button class="ml-auto font-medium text-accent hover:underline" onclick={() => (filter = 'all')}>Show all</button>
	</div>
{/if}

<div class="overflow-x-auto">
	<table class="w-full text-left">
		<thead class="text-sm text-ink-2">
			<tr class="border-y border-line bg-surface-2">
				<th scope="col" class="py-2.5 pr-3 pl-5 font-medium">KPI</th>
				<th scope="col" class="px-3 py-2.5 font-medium">Status</th>
				<th scope="col" class="px-3 py-2.5 text-right font-medium">Value</th>
				<th scope="col" class="hidden px-3 py-2.5 text-right font-medium sm:table-cell">Target</th>
				<th scope="col" class="hidden px-3 py-2.5 font-medium md:table-cell">Change</th>
				<th scope="col" class="hidden px-3 py-2.5 font-medium lg:table-cell">Last 12 periods</th>
				<th scope="col" class="hidden py-2.5 pr-5 pl-3 text-right font-medium sm:table-cell">Reports</th>
			</tr>
		</thead>
		<tbody>
			{#each shown as row, i (row.kpi.id)}
				<!-- The whole row opens the KPI: its link covers the row -->
				<tr class="group relative cursor-pointer border-b border-line last:border-b-0 hover:bg-surface-2" in:fly={{ y: 6 * move, duration: 240, delay: Math.min(i, 10) * 30 * move, easing: cubicOut }}>
					<th scope="row" class="py-3.5 pr-3 pl-5 font-normal">
						<span class="flex items-center gap-3">
							<span class="grid size-8 shrink-0 place-items-center rounded-lg bg-accent-wash text-accent"><Icon name={kpiIcon(row.kpi)} size={15} /></span>
							<span class="min-w-0">
								<a class="block font-semibold text-ink group-hover:text-accent group-hover:underline after:absolute after:inset-0" href={kpiHref(row.kpi.id)}>{row.kpi.name}</a>
								<span class="block text-sm text-muted">{row.period_label}</span>
							</span>
						</span>
					</th>
					<td class="px-3 py-3.5">
						{#if row.kpi.direction === 'none'}
							<span class="text-sm text-muted">Tracked only</span>
						{:else}
							<StatusBadge status={row.value === null ? null : row.status} empty={row.value === null ? 'No data' : 'No target'} />
						{/if}
					</td>
					<td class="px-3 py-3.5 text-right text-lg font-semibold whitespace-nowrap">{formatValue(row.kpi, row.value)}</td>
					<td class="num hidden px-3 py-3.5 text-right whitespace-nowrap text-ink-2 sm:table-cell">{formatValue(row.kpi, row.target)}</td>
					<td class="hidden px-3 py-3.5 text-sm whitespace-nowrap md:table-cell"><span class="relative z-10"><ChangeCell {row} /></span></td>
					<td class="hidden px-3 py-3.5 lg:table-cell">
						<Sparkline values={row.sparkline.map((p) => p.value)} label="{row.kpi.name}, last {row.sparkline.length} periods" />
					</td>
					<td class="num hidden py-3.5 pr-5 pl-3 text-right whitespace-nowrap sm:table-cell {row.reported < row.expected ? 'text-ink' : 'text-muted'}">
						{row.reported}/{row.expected}
					</td>
				</tr>
			{/each}
		</tbody>
	</table>
</div>
