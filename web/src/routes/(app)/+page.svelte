<script lang="ts">
	import { page } from '$app/state';
	import { fade } from 'svelte/transition';
	import { api, ApiError } from '$lib/api';
	import EmptyState from '$lib/components/EmptyState.svelte';
	import ExportDialog from '$lib/components/ExportDialog.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import Masthead from '$lib/components/Masthead.svelte';
	import KpiTable from '$lib/components/scorecard/KpiTable.svelte';
	import ScoreSummary from '$lib/components/scorecard/ScoreSummary.svelte';
	import GettingStarted from '$lib/components/GettingStarted.svelte';
	import StatusMatrix from '$lib/components/StatusMatrix.svelte';
	import { contextParams, href, readContext } from '$lib/nav';
	import { filterRows, summarize, type RowFilter } from '$lib/summary';
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
	let filter = $state<RowFilter>('all');
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
			// A filter that matches nothing in the new period would leave an empty list
			if (!filterRows(next.kpis, filter).length) filter = 'all';
		} catch (e) {
			if (seq === request) error = e instanceof ApiError ? e.message : 'Could not load the scorecard';
		} finally {
			if (seq === request) loading = false;
		}
	}

	const unitHref = (id: number) => href('/', contextParams({ unit: id, date: ctx.date, grain: ctx.grain }));
	const periodHref = (date: string, grain: Frequency) => href('/', contextParams({ unit: unitId, date, grain }));
	const kpiHref = (kpiId: number, unit = unitId) => href(`/kpis/${kpiId}`, contextParams({ unit, date: card?.date, grain: ctx.grain }));

	// Something here or below that this person can enter figures for
	const canEnter = $derived(unitId !== null && units.enterable().some((u) => units.path(u.id).some((p) => p.id === unitId)));
</script>

<svelte:head><title>{unit?.name ?? 'Scorecard'} | Clairvoyance</title></svelte:head>

{#if unitId === null}
	<main class="mx-auto max-w-[1240px] px-4 py-16 sm:px-6">
		<EmptyState icon="lock" title="Nothing to show you yet">
			<p>
				You've joined {session.user?.organization?.name}, but an administrator still needs to give you access to the {term.many} you
				report for or review. Once they do, your scorecard appears here.
			</p>
			<p class="mt-4 text-sm text-muted">Signed in as {session.user?.email}</p>
		</EmptyState>
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
				{#if session.user?.organization?.role === 'admin'}<GettingStarted bind:shown={checklistShown} />{/if}

				{#if card.kpis.length}
					<div class={checklistShown ? 'mt-6' : '-mt-5'}><ScoreSummary rows={card.kpis} bind:filter /></div>
					<section class="panel mt-4 overflow-hidden" aria-labelledby="kpis-heading">
						<KpiTable rows={card.kpis} bind:filter kpiHref={(id) => kpiHref(id)}>
							{#snippet actions()}
								<button class="btn btn-secondary btn-sm" onclick={() => (exporting = true)}><Icon name="download" size={14} /> Export</button>
								{#if canEnter}
									<a class="btn btn-primary btn-sm" href={href('/enter', { unit: isLeaf ? unitId : null, date: card!.date })}>
										<Icon name="pencil" size={14} /> Add {card!.kpis[0].period_label} data
									</a>
								{/if}
							{/snippet}
						</KpiTable>
					</section>
				{:else}
					<section class="panel overflow-hidden {checklistShown ? 'mt-6' : '-mt-5'}" aria-labelledby="kpis-heading">
						<div class="flex flex-col items-center px-6 py-14 text-center" in:fade={{ duration: 250 }}>
							<span class="grid size-14 place-items-center rounded-2xl bg-accent-wash text-accent"><Icon name="chart" size={26} /></span>
							<h2 id="kpis-heading" class="mt-4 text-lg font-semibold tracking-[-0.01em]">No KPIs yet</h2>
							{#if session.user?.organization?.role === 'admin'}
								<p class="mt-1.5 max-w-[46ch] text-ink-2">
									KPIs are what you measure, like revenue or customer satisfaction. Add the first one and this scorecard comes to life.
								</p>
								<a class="btn btn-primary mt-5" href="/admin/kpis?new=1"><Icon name="plus" size={15} /> Add your first KPI</a>
							{:else}
								<p class="mt-1.5 max-w-[46ch] text-ink-2">Once an administrator adds the KPIs {session.user?.organization?.name} tracks, they appear here.</p>
							{/if}
						</div>
					</section>
				{/if}

				<!-- Units × KPIs -->
				{#if !isLeaf && childUnits.length && card.kpis.length}
					<section class="panel mt-6 p-5 sm:p-6" aria-labelledby="units-heading">
						<div class="mb-4 flex items-start gap-3">
							<span class="grid size-9 shrink-0 place-items-center rounded-lg bg-accent-wash text-accent"><Icon name="layers" size={17} /></span>
							<div>
								<h2 id="units-heading" class="font-semibold">{cap(wordFor(childUnits))} in {unit?.name}</h2>
								<p class="text-sm text-ink-2">Each one's own figure for {card.kpis[0].period_label}. Click a name to open its scorecard, or a cell for that KPI's detail.</p>
							</div>
						</div>
						<StatusMatrix
							rows={childUnits}
							kpis={card.kpis.map((r) => r.kpi)}
							cell={(u, k) => cells.get(`${u}:${k}`)}
							{unitHref}
							cellHref={(u, k) => kpiHref(k, u)}
						/>
					</section>
				{/if}
			</div>
		{/if}
	</main>

	{#if card && unit}
		<ExportDialog bind:open={exporting} unitId={unitId} unitName={unit.name} date={card.date} />
	{/if}
{/if}
