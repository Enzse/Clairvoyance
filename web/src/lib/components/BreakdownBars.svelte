<script lang="ts">
	import { formatValue } from '$lib/format';
	import { cap, wordFor } from '$lib/terms.svelte';
	import { niceScale, tickFormatter } from '$lib/chart';
	import type { Breakdown, Kpi } from '$lib/types';
	import StatusBadge from './StatusBadge.svelte';

	/**
	 * One row per unit, with its target as a tick. The form follows the KPI:
	 *  - additive KPIs (summed across units) are magnitudes: bars growing from zero;
	 *  - averages and rates are judged against target: dots on a scale fitted to the data,
	 *    since bars from zero would make 94.6% and 96.8% look identical.
	 * One colour throughout (a single series); status is carried by the badge in each row.
	 */
	let { rows, kpi, hrefFor }: { rows: Breakdown['units']; kpi: Kpi; hrefFor: (unitId: number) => string } = $props();

	let sort = $state<'value' | 'name'>('value');

	const bars = $derived(kpi.unit_aggregation === 'sum');
	const scale = $derived(
		niceScale(rows.flatMap((r) => [r.value, r.target]).filter((v): v is number => v !== null), { count: 4, includeZero: bars })
	);
	const pct = (v: number) => ((v - scale.min) / (scale.max - scale.min || 1)) * 100;
	const tick = $derived(tickFormatter(scale));
	const sorted = $derived(
		sort === 'name'
			? [...rows].sort((a, b) => a.unit.name.localeCompare(b.unit.name))
			: [...rows].sort((a, b) => (b.value ?? -Infinity) - (a.value ?? -Infinity))
	);
	const hasTargets = $derived(rows.some((r) => r.target !== null));
</script>

<div class="mb-2 flex items-center justify-end gap-2 text-sm text-ink-2">
	{#if hasTargets}
		<span class="mr-auto inline-flex items-center gap-2"><span class="h-3.5 w-0.5 bg-ink-2"></span>Target</span>
	{/if}
	<label class="inline-flex items-center gap-2">
		Sort by
		<select class="input h-8 w-auto py-0 text-sm" bind:value={sort}>
			<option value="value">Value, highest first</option>
			<option value="name">Name</option>
		</select>
	</label>
</div>

<div class="overflow-x-auto">
	<table class="w-full text-left">
		<thead class="text-sm text-ink-2">
			<tr class="border-b border-line">
				<th scope="col" class="w-[28%] py-2 pr-3 font-medium">{cap(wordFor(rows.map((r) => r.unit), false))}</th>
				<th scope="col" class="py-2 pr-6 font-normal">
					<span class="sr-only">Chart</span>
					<div class="relative h-4" aria-hidden="true">
						{#each scale.ticks as t (t)}
							<span class="num absolute -translate-x-1/2 text-xs text-muted" style:left="{pct(t)}%">{tick(t)}</span>
						{/each}
					</div>
				</th>
				<th scope="col" class="w-24 py-2 pr-4 text-right font-medium">Value</th>
				{#if hasTargets}<th scope="col" class="hidden w-20 py-2 pr-4 text-right font-medium sm:table-cell">Target</th>{/if}
				{#if kpi.direction !== 'none'}<th scope="col" class="hidden w-28 py-2 pr-4 font-medium md:table-cell">Status</th>{/if}
				<th scope="col" class="hidden w-16 py-2 text-right font-medium sm:table-cell">Reports</th>
			</tr>
		</thead>
		<tbody>
			{#each sorted as r (r.unit.id)}
				{@const zero = pct(Math.max(scale.min, Math.min(0, scale.max)))}
				{@const at = r.value === null ? null : pct(r.value)}
				<!-- The whole row opens the unit: its link covers the row -->
				<tr class="group relative cursor-pointer border-b border-line last:border-b-0 hover:bg-surface-2">
					<th scope="row" class="py-2.5 pr-4 font-normal">
						<a class="font-medium group-hover:text-accent group-hover:underline after:absolute after:inset-0" href={hrefFor(r.unit.id)}>{r.unit.name}</a>
						{#if r.unit.kind}<span class="block text-xs text-muted">{r.unit.kind}</span>{/if}
					</th>
					<td class="py-2.5 pr-6">
						<div class="relative h-7" aria-hidden="true">
							{#each scale.ticks as t (t)}
								<span class="absolute inset-y-0 w-px bg-line" style:left="{pct(t)}%"></span>
							{/each}
							{#if at !== null}
								{#if bars}
									<div
										class="absolute top-1.5 h-4 bg-chart {r.value! >= 0 ? 'rounded-r-[4px]' : 'rounded-l-[4px]'}"
										style:left="{Math.min(zero, at)}%"
										style:width="{Math.abs(at - zero)}%"
									></div>
								{/if}
							{/if}
							{#if r.target !== null}
								<span class="absolute top-0.5 h-6 w-0.5 -translate-x-1/2 rounded-full bg-ink-2" style:left="{pct(r.target)}%"></span>
							{/if}
							{#if at !== null && !bars}
								<span class="absolute top-1/2 size-3 -translate-1/2 rounded-full bg-chart ring-2 ring-surface" style:left="{at}%"></span>
							{/if}
						</div>
					</td>
					<td class="py-2.5 pr-4 text-right font-semibold whitespace-nowrap">{formatValue(kpi, r.value)}</td>
					{#if hasTargets}<td class="num hidden py-2.5 pr-4 text-right whitespace-nowrap text-ink-2 sm:table-cell">{formatValue(kpi, r.target)}</td>{/if}
					{#if kpi.direction !== 'none'}
						<td class="hidden py-2.5 pr-4 md:table-cell">
							<StatusBadge status={r.value === null ? null : r.status} empty={r.value === null ? 'No data' : 'No target'} />
						</td>
					{/if}
					<td class="num hidden py-2.5 text-right whitespace-nowrap sm:table-cell {r.reported < r.expected ? 'text-ink' : 'text-muted'}">{r.reported}/{r.expected}</td>
				</tr>
			{/each}
		</tbody>
	</table>
</div>
