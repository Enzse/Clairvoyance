<script lang="ts">
	import { page } from '$app/state';
	import { api, ApiError } from '$lib/api';
	import BreakdownBars from '$lib/components/BreakdownBars.svelte';
	import ChangeHistory from '$lib/components/ChangeHistory.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import Masthead from '$lib/components/Masthead.svelte';
	import StatusBadge from '$lib/components/StatusBadge.svelte';
	import KpiAbout from '$lib/components/kpi-detail/KpiAbout.svelte';
	import KpiHero from '$lib/components/kpi-detail/KpiHero.svelte';
	import TrendChart from '$lib/components/TrendChart.svelte';
	import { trimLeading } from '$lib/chart';
	import { formatValue } from '$lib/format';
	import { describeChange } from '$lib/summary';
	import { contextParams, href, readContext } from '$lib/nav';
	import { FREQUENCIES, GRAIN_NAMES } from '$lib/period';
	import { STATUS } from '$lib/status';
	import type { Breakdown, Frequency, Kpi, Series } from '$lib/types';
	import { units } from '$lib/units.svelte';
	import { term, wordFor } from '$lib/terms.svelte';

	const kpiId = $derived(Number(page.params.id));
	const ctx = $derived(readContext(page.url));
	const unitId = $derived(ctx.unit ?? units.home()?.id ?? null);
	const unit = $derived(units.get(unitId));
	const isLeaf = $derived(unitId !== null && units.isLeaf(unitId));

	let kpis = $state<Kpi[]>([]);
	let series = $state<Series | null>(null);
	let breakdown = $state<Breakdown | null>(null);
	let loading = $state(true);
	let error = $state('');
	let view = $state<'chart' | 'table'>('chart');
	let request = 0;

	const kpi = $derived(kpis.find((k) => k.id === kpiId) ?? series?.kpi ?? null);
	// A KPI can't be shown finer than it is reported: a yearly KPI on "Month" shows yearly
	const grain = $derived<Frequency>(
		kpi && FREQUENCIES.indexOf(kpi.frequency) > FREQUENCIES.indexOf(ctx.grain) ? kpi.frequency : ctx.grain
	);
	const weight = $derived(kpis.find((k) => k.id === kpi?.weight_kpi_id));

	$effect(() => {
		if (unitId !== null) load(kpiId, unitId, ctx.date, ctx.grain);
	});

	async function load(id: number, unit: number, date: string | null, requested: Frequency) {
		const seq = ++request;
		loading = true;
		error = '';
		try {
			if (!kpis.length) kpis = await api.kpis(true);
			const def = kpis.find((k) => k.id === id);
			if (!def) throw new ApiError(404, 'This KPI does not exist.');
			const g = FREQUENCIES.indexOf(def.frequency) > FREQUENCIES.indexOf(requested) ? def.frequency : requested;
			const [s, b] = await Promise.all([
				api.series(id, unit, g, date),
				units.isLeaf(unit) ? Promise.resolve(null) : api.breakdown(id, unit, g, date)
			]);
			if (seq !== request) return;
			series = s;
			breakdown = b;
		} catch (e) {
			if (seq === request) error = e instanceof ApiError ? e.message : 'Could not load this KPI';
		} finally {
			if (seq === request) loading = false;
		}
	}

	const current = $derived(series?.periods.at(-1) ?? null);
	const previous = $derived(series && series.periods.length > 1 ? series.periods.at(-2)! : null);

	const self = (params: { unit?: number | null; date?: string | null; grain?: Frequency }) =>
		href(`/kpis/${kpiId}`, contextParams({ unit: unitId, date: ctx.date, grain: ctx.grain, ...params }));
	const unitHref = (id: number) => self({ unit: id });
	const periodHref = (date: string, g: Frequency) => self({ date, grain: g });

	function reading(): string {
		if (!kpi || !current) return '';
		if (current.value === null) return `${unit?.name} has no ${kpi.name.toLowerCase()} figure for ${current.period_label} yet.`;
		let text = `${formatValue(kpi, current.value)} in ${current.period_label}`;
		if (current.status && current.target !== null) text += `, ${STATUS[current.status].label.toLowerCase()} (target ${formatValue(kpi, current.target)})`;
		return text + '.' + describeChange(kpi, series?.change ?? null, previous, grain, term.many);
	}
</script>

<svelte:head><title>{kpi?.name ?? 'KPI'} | Clairvoyance</title></svelte:head>

{#if unitId !== null}
	<Masthead {unitId} periodStart={current?.period_start ?? null} {grain} {unitHref} {periodHref}>
		{#snippet title()}{kpi?.name ?? ''}{/snippet}
		{#if series}<p>{reading()}</p>{/if}
	</Masthead>

	<main class="mx-auto max-w-[1240px] px-4 pb-16 sm:px-6">
		{#if error}
			<div class="panel -mt-5 flex items-start gap-3 p-5 text-bad-ink" role="alert">
				<Icon name="alert" size={18} class="mt-0.5 shrink-0" />
				<p>{error} <a class="text-accent underline" href={href('/', contextParams({ unit: unitId }))}>Back to the scorecard</a></p>
			</div>
		{:else if series && kpi && current}
			<div class="transition-opacity duration-150 {loading ? 'opacity-55' : ''}" aria-busy={loading}>
				<section class="panel -mt-5 p-5 sm:p-7" aria-labelledby="trend-heading">
					<KpiHero {kpi} where={unit?.name ?? ''} {current} {previous} change={series.change} missing={series.missing} bind:view />

					<div class="mt-6">
						{#if view === 'chart'}
							<TrendChart periods={trimLeading(series.periods, (p) => p.value !== null)} {kpi} {grain} />
							<p class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-ink-2">
								<span class="inline-flex items-center gap-2"><span class="h-0.5 w-4 rounded bg-chart"></span>{kpi.name}</span>
								<span class="inline-flex items-center gap-2"><span class="h-px w-4 bg-ink-2"></span>Target</span>
								{#if series.periods.some((p) => p.value !== null && p.reported < p.expected)}
									<span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full ring-2 ring-chart ring-inset"></span>Some reports missing</span>
								{/if}
							</p>
						{:else}
							<div class="overflow-x-auto">
								<table class="w-full text-left">
									<thead class="text-sm text-ink-2">
										<tr class="border-b border-line">
											<th scope="col" class="py-2 pr-4 font-medium">{GRAIN_NAMES[grain].noun}</th>
											<th scope="col" class="py-2 pr-4 text-right font-medium">Value</th>
											<th scope="col" class="py-2 pr-4 text-right font-medium">Target</th>
											<th scope="col" class="py-2 pr-4 font-medium">Status</th>
											<th scope="col" class="py-2 text-right font-medium">Reports in</th>
										</tr>
									</thead>
									<tbody>
										{#each [...series.periods].reverse() as p (p.period_start)}
											<tr class="border-b border-line last:border-b-0">
												<th scope="row" class="py-2.5 pr-4 font-normal">{p.period_label}</th>
												<td class="num py-2.5 pr-4 text-right font-medium">{formatValue(kpi, p.value)}</td>
												<td class="num py-2.5 pr-4 text-right text-ink-2">{formatValue(kpi, p.target)}</td>
												<td class="py-2.5 pr-4">{#if kpi.direction !== 'none' && p.value !== null}<StatusBadge status={p.status} />{/if}</td>
												<td class="num py-2.5 text-right {p.reported < p.expected ? '' : 'text-muted'}">{p.reported} of {p.expected}</td>
											</tr>
										{/each}
									</tbody>
								</table>
							</div>
						{/if}
					</div>
				</section>

				<div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
					{#if breakdown && !isLeaf}
						<section class="panel min-w-0 p-5 sm:p-6" aria-labelledby="units-heading">
							<div class="mb-4 flex items-start gap-3">
								<span class="grid size-9 shrink-0 place-items-center rounded-lg bg-accent-wash text-accent"><Icon name="layers" size={17} /></span>
								<div>
									<h2 id="units-heading" class="text-md font-semibold">By {wordFor(breakdown.units.map((r) => r.unit), false)}, {breakdown.period_label}</h2>
									<p class="text-sm text-ink-2">Click one to see this KPI for it alone.</p>
								</div>
							</div>
							<BreakdownBars rows={breakdown.units} {kpi} hrefFor={unitHref} />
						</section>
					{:else}
						<!-- Nothing inside this unit to break down: its history takes the space -->
						<ChangeHistory {kpi} {unitId} showUnit={false} class="h-fit" />
					{/if}

					<KpiAbout {kpi} weightName={weight?.name} target={current.target} />
				</div>

				{#if breakdown && !isLeaf}<ChangeHistory {kpi} {unitId} showUnit />{/if}
			</div>
		{/if}
	</main>
{/if}
