<script lang="ts">
	import { formatValue } from '$lib/format';
	import { STATUS } from '$lib/status';
	import type { Kpi, Result, Unit } from '$lib/types';
	import StatusMark from './StatusMark.svelte';

	/**
	 * Units × KPIs at one period: the "who is behind on what" view. Each cell is a tile tinted
	 * by status with the status shape and the value, separated by a 3px surface gap.
	 */
	let {
		rows,
		kpis,
		cell,
		unitHref,
		cellHref
	}: {
		rows: Unit[];
		kpis: Kpi[];
		cell: (unitId: number, kpiId: number) => Result | undefined;
		unitHref: (unitId: number) => string;
		cellHref: (unitId: number, kpiId: number) => string;
	} = $props();

	let sort = $state<'name' | 'behind'>('name');

	const tone: Record<string, string> = { green: 'bg-good-wash', amber: 'bg-warn-wash', red: 'bg-bad-wash' };

	function badness(unitId: number): number {
		return kpis.reduce((score, k) => {
			const s = cell(unitId, k.id)?.status;
			return score + (s === 'red' ? 100 : s === 'amber' ? 1 : 0);
		}, 0);
	}

	const sorted = $derived(
		sort === 'name' ? rows : [...rows].sort((a, b) => badness(b.id) - badness(a.id) || a.name.localeCompare(b.name))
	);

	function describe(unit: Unit, kpi: Kpi, r: Result | undefined): string {
		if (!r || r.reported === 0) return `${unit.name}, ${kpi.name}: no data`;
		const status = r.status ? `, ${STATUS[r.status].label.toLowerCase()}` : '';
		return `${unit.name}, ${kpi.name}: ${formatValue(kpi, r.value)}${status}, ${r.reported} of ${r.expected} reports`;
	}
</script>

<div class="mb-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-ink-2">
	{#each Object.entries(STATUS) as [key, meta] (key)}
		<span class="inline-flex items-center gap-1.5"><StatusMark status={key as keyof typeof STATUS} />{meta.label}</span>
	{/each}
	<span class="inline-flex items-center gap-1.5"><span class="num rounded bg-surface px-1 text-xs text-ink ring-1 ring-line-strong">4/5</span>Reports in, when some are missing</span>
	<label class="ml-auto inline-flex items-center gap-2">
		Sort by
		<select class="input h-8 w-auto py-0 text-sm" bind:value={sort}>
			<option value="name">Name</option>
			<option value="behind">Most behind first</option>
		</select>
	</label>
</div>

<div class="-mx-1 overflow-x-auto pb-1">
	<table class="w-full border-separate border-spacing-[3px] text-left">
		<thead>
			<tr>
				<th scope="col" class="sticky left-0 z-10 min-w-44 bg-surface px-2 pb-2 align-bottom text-sm font-medium text-ink-2">Unit</th>
				{#each kpis as kpi (kpi.id)}
					<th scope="col" class="min-w-28 px-2 pb-2 align-bottom text-sm leading-tight font-medium text-ink-2">{kpi.name}</th>
				{/each}
			</tr>
		</thead>
		<tbody>
			{#each sorted as unit (unit.id)}
				<tr>
					<th scope="row" class="sticky left-0 z-10 bg-surface px-2 py-1 font-normal">
						<a class="group block rounded-md py-1" href={unitHref(unit.id)}>
							<span class="block font-medium text-ink group-hover:text-accent group-hover:underline">{unit.name}</span>
							{#if unit.kind}<span class="block text-xs text-muted">{unit.kind}</span>{/if}
						</a>
					</th>
					{#each kpis as kpi (kpi.id)}
						{@const r = cell(unit.id, kpi.id)}
						{@const empty = !r || r.reported === 0}
						<td class="p-0">
							<a
								href={cellHref(unit.id, kpi.id)}
								aria-label={describe(unit, kpi, r)}
								class="relative flex h-12 items-center gap-2 rounded-md px-3 hover:ring-2 hover:ring-line-strong hover:ring-inset {empty
									? 'bg-surface-2'
									: r?.status
										? tone[r.status]
										: 'bg-surface-2'}"
							>
								{#if empty}
									<span class="text-sm text-muted">No data</span>
								{:else}
									{#if r!.status}<StatusMark status={r!.status} size={13} />{/if}
									<span class="num text-base {r!.status ? 'text-ink' : 'text-ink-2'}">{formatValue(kpi, r!.value)}</span>
									{#if r!.reported < r!.expected}
										<span class="num ml-auto rounded bg-surface px-1 text-xs text-ink ring-1 ring-line-strong">{r!.reported}/{r!.expected}</span>
									{/if}
								{/if}
							</a>
						</td>
					{/each}
				</tr>
			{/each}
		</tbody>
	</table>
</div>
