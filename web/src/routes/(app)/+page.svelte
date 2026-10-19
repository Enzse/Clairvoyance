<script lang="ts">
	import { page } from '$app/state';
	import { api, ApiError } from '$lib/api';
	import ChangeCell from '$lib/components/ChangeCell.svelte';
	import ExportDrawer from '$lib/components/ExportDrawer.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import Masthead from '$lib/components/Masthead.svelte';
	import Sparkline from '$lib/components/Sparkline.svelte';
	import StatusBadge from '$lib/components/StatusBadge.svelte';
	import SetupChecklist from '$lib/components/SetupChecklist.svelte';
	import StatusMatrix from '$lib/components/StatusMatrix.svelte';
	import { formatValue } from '$lib/format';
	import { contextParams, href, readContext } from '$lib/nav';
	import { summarize } from '$lib/summary';
	import type { Frequency, Result, Scorecard } from '$lib/types';
	import { session } from '$lib/session.svelte';
	import { cap, term, wordFor } from '$lib/terms.svelte';
	import { units } from '$lib/units.svelte';

	const ctx = $derived(readContext(page.url));
	const unitId = $derived(ctx.unit ?? units.home()?.id ?? null);
	const unit = $derived(units.get(unitId));
	const isLeaf = $derived(unitId !== null && units.isLeaf(unitId));
	const childUnits = $derived(unitId === null ? [] : units.children(unitId).filter((u) => u.is_active));

	let card = $state<Scorecard | null>(null);
	let cells = $state(new Map<string, Result>());
	let loading = $state(true);
	let error = $state('');
	let checklistShown = $state(false);
	let exporting = $state(false);
	let request = 0;

	$effect(() => {
		if (unitId !== null) load(unitId, ctx.date, ctx.grain);
	});

	async function load(unit: number, date: string | null, grain: Frequency) {
		const seq = ++request;
		loading = true;
		error = '';
		try {
			const next = await api.scorecard(unit, date, grain);
			const map = new Map<string, Result>();
			if (!units.isLeaf(unit)) {
				const breakdowns = await Promise.all(next.kpis.map((row) => api.breakdown(row.kpi.id, unit, row.grain, row.period_start)));
				for (const b of breakdowns) for (const r of b.units) map.set(`${r.unit.id}:${b.kpi.id}`, r);
			}
			if (seq !== request) return;
			card = next;
			cells = map;
		} catch (e) {
			if (seq === request) error = e instanceof ApiError ? e.message : 'Could not load the scorecard';
		} finally {
			if (seq === request) loading = false;
		}
	}

	const unitHref = (id: number) => href('/', contextParams({ unit: id, date: ctx.date, grain: ctx.grain }));
	const periodHref = (date: string, grain: Frequency) => href('/', contextParams({ unit: unitId, date, grain }));
	const kpiHref = (kpiId: number, unit = unitId) => href(`/kpis/${kpiId}`, contextParams({ unit, date: card?.date, grain: ctx.grain }));

	const canEnter = $derived(isLeaf && (unit?.access === 'admin' || unit?.access === 'contributor') && unit?.is_active);
</script>

<svelte:head><title>{unit?.name ?? 'Scorecard'} | Clairvoyance</title></svelte:head>

{#if unitId === null}
	<main class="mx-auto max-w-[1240px] px-4 py-16 sm:px-6">
		<div class="panel mx-auto max-w-xl p-8">
			<h1 class="text-lg font-semibold">You don't have access to anything yet</h1>
			<p class="mt-2 text-ink-2">
				An administrator needs to give {session.user?.email} access to the {term.many} you report for or review. Once they do, your
				scorecard appears here.
			</p>
		</div>
	</main>
{:else}
	<Masthead {unitId} periodStart={card?.date ?? null} grain={ctx.grain} {unitHref} {periodHref}>
		{#snippet title()}{unit?.name}{/snippet}
		{#if card}
			<p>{summarize(card.kpis)}</p>
		{:else if !error}
			<p class="opacity-0">Loading</p>
		{/if}
	</Masthead>

	<main class="mx-auto max-w-[1240px] px-4 pb-16 sm:px-6">
		{#if error}
			<div class="panel -mt-5 flex items-start gap-3 p-5 text-bad-ink" role="alert">
				<Icon name="alert" size={18} class="mt-0.5 shrink-0" />
				<p>{error}</p>
			</div>
		{:else if card}
			<div class="transition-opacity duration-150 {loading ? 'opacity-55' : ''}" aria-busy={loading}>
				<!-- Report card -->
				{#if session.user?.organization?.role === 'admin'}<SetupChecklist bind:shown={checklistShown} />{/if}
				<section class="panel overflow-hidden {checklistShown ? '' : '-mt-5'}" aria-labelledby="kpis-heading">
					<div class="flex items-center justify-between gap-4 px-5 pt-5 pb-3">
						<h2 id="kpis-heading" class="text-md font-semibold">KPIs</h2>
						<div class="flex flex-wrap justify-end gap-2">
							{#if card.kpis.length}
								<button class="btn btn-secondary btn-sm" onclick={() => (exporting = true)}><Icon name="download" size={14} /> Export</button>
							{/if}
							{#if canEnter}
								<a class="btn btn-primary btn-sm" href={href('/enter', { unit: unitId, date: card.date })}>
									<Icon name="pencil" size={14} /> Add {card.kpis[0]?.period_label ?? ''} data
								</a>
							{/if}
						</div>
					</div>
					{#if card.kpis.length === 0}
						<p class="px-5 pb-6 text-ink-2">
							No KPIs are defined yet.
							{#if session.user?.organization?.role === 'admin'}<a class="text-accent hover:underline" href="/admin/kpis">Define the first KPI</a>.{/if}
						</p>
					{:else}
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
									{#each card.kpis as row (row.kpi.id)}
										<tr class="group border-b border-line last:border-b-0 hover:bg-surface-2">
											<th scope="row" class="py-3.5 pr-3 pl-5 font-normal">
												<a class="font-semibold text-ink group-hover:text-accent hover:underline" href={kpiHref(row.kpi.id)}>{row.kpi.name}</a>
												<span class="block text-sm text-muted">{row.period_label}</span>
											</th>
											<td class="px-3 py-3.5">
												{#if row.kpi.direction === 'none'}
													<span class="text-sm text-muted">Tracked only</span>
												{:else}
													<StatusBadge status={row.value === null ? null : row.status} empty={row.value === null ? 'No data' : 'No target'} />
												{/if}
											</td>
											<td class="px-3 py-3.5 text-right text-lg font-semibold whitespace-nowrap">{formatValue(row.kpi, row.value)}</td>
											<td class="num hidden px-3 sm:table-cell py-3.5 text-right whitespace-nowrap text-ink-2">{formatValue(row.kpi, row.target)}</td>
											<td class="hidden px-3 md:table-cell py-3.5 text-sm whitespace-nowrap"><ChangeCell {row} /></td>
											<td class="hidden px-3 py-3.5 lg:table-cell">
												<Sparkline values={row.sparkline.map((p) => p.value)} label="{row.kpi.name}, last {row.sparkline.length} periods" />
											</td>
											<td class="num hidden py-3.5 sm:table-cell pr-5 pl-3 text-right whitespace-nowrap {row.reported < row.expected ? 'text-ink' : 'text-muted'}">
												{row.reported}/{row.expected}
											</td>
										</tr>
									{/each}
								</tbody>
							</table>
						</div>
					{/if}
				</section>

				<!-- Units × KPIs -->
				{#if !isLeaf && childUnits.length && card.kpis.length}
					<section class="panel mt-6 p-5" aria-labelledby="units-heading">
						<h2 id="units-heading" class="text-md font-semibold">{cap(wordFor(childUnits))} in {unit?.name}</h2>
						<p class="mt-1 mb-4 text-sm text-ink-2">Each one's own figure for {card.kpis[0].period_label}. Select a name to open its scorecard, or a cell for that KPI's detail.</p>
						<StatusMatrix
							rows={childUnits}
							kpis={card.kpis.map((r) => r.kpi)}
							cell={(u, k) => cells.get(`${u}:${k}`)}
							{unitHref}
							cellHref={(u, k) => kpiHref(k, u)}
						/>
					</section>
				{:else if isLeaf && !canEnter}
					<p class="mt-6 text-sm text-ink-2">{unit?.name} reports its own figures; there are no units under it.</p>
				{/if}
			</div>
		{/if}
	</main>

	{#if card && unit}
		<ExportDrawer bind:open={exporting} unitId={unitId} unitName={unit.name} date={card.date} />
	{/if}
{/if}
