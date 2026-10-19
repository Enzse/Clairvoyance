<script lang="ts">
	import { page } from '$app/state';
	import { api, ApiError } from '$lib/api';
	import BreakdownBars from '$lib/components/BreakdownBars.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import Masthead from '$lib/components/Masthead.svelte';
	import StatusBadge from '$lib/components/StatusBadge.svelte';
	import TrendChart from '$lib/components/TrendChart.svelte';
	import { trimLeading } from '$lib/chart';
	import { describeRules, formatDelta, formatValue } from '$lib/format';
	import { contextParams, href, readContext } from '$lib/nav';
	import { GRAIN_NAMES } from '$lib/period';
	import { FREQUENCIES } from '$lib/period';
	import { STATUS } from '$lib/status';
	import type { Breakdown, Frequency, Kpi, Series } from '$lib/types';
	import { units } from '$lib/units.svelte';

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
	const delta = $derived(current?.value != null && previous?.value != null ? current.value - previous.value : null);

	const self = (params: { unit?: number | null; date?: string | null; grain?: Frequency }) =>
		href(`/kpis/${kpiId}`, contextParams({ unit: unitId, date: ctx.date, grain: ctx.grain, ...params }));
	const unitHref = (id: number) => self({ unit: id });
	const periodHref = (date: string, g: Frequency) => self({ date, grain: g });

	function reading(): string {
		if (!kpi || !current) return '';
		if (current.value === null) return `${unit?.name} has no ${kpi.name.toLowerCase()} figure for ${current.period_label} yet.`;
		let text = `${formatValue(kpi, current.value)} in ${current.period_label}`;
		if (current.status && current.target !== null) text += `, ${STATUS[current.status].label.toLowerCase()} (target ${formatValue(kpi, current.target)})`;
		text += '.';
		if (delta !== null && previous) {
			text += delta === 0 ? ` Unchanged from ${previous.period_label}.` : ` ${delta > 0 ? 'Up' : 'Down'} ${formatDelta(kpi, Math.abs(delta)).slice(1)} from ${previous.period_label}.`;
		}
		return text;
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
				<section class="panel -mt-5 p-5 sm:p-6" aria-labelledby="trend-heading">
					<div class="flex flex-wrap items-start gap-x-10 gap-y-4">
						<div>
							<h2 id="trend-heading" class="text-sm text-ink-2">{unit?.name}, {current.period_label}</h2>
							<p class="mt-1 text-[48px] leading-none font-semibold tracking-[-0.03em]">{formatValue(kpi, current.value)}</p>
						</div>
						<dl class="flex flex-wrap gap-x-8 gap-y-3 pt-1">
							{#if kpi.direction !== 'none'}
								<div>
									<dt class="text-sm text-ink-2">Status</dt>
									<dd class="mt-1.5"><StatusBadge status={current.value === null ? null : current.status} empty={current.value === null ? 'No data' : 'No target'} /></dd>
								</div>
							{/if}
							<div>
								<dt class="text-sm text-ink-2">Target</dt>
								<dd class="num mt-1 text-md">{formatValue(kpi, current.target)}</dd>
							</div>
							<div>
								<dt class="text-sm text-ink-2">Reports in</dt>
								<dd class="num mt-1 text-md">{current.reported} of {current.expected}</dd>
							</div>
						</dl>
						<div class="ml-auto flex rounded-lg bg-surface-2 p-0.5 ring-1 ring-line" role="group" aria-label="View">
							{#each [['chart', 'Chart'], ['table', 'Table']] as [value, text] (value)}
								<button
									class="rounded-md px-3 py-1 text-sm {view === value ? 'bg-surface font-semibold text-ink shadow-sm ring-1 ring-line' : 'text-ink-2 hover:text-ink'}"
									aria-pressed={view === value}
									onclick={() => (view = value as 'chart' | 'table')}
								>
									{text}
								</button>
							{/each}
						</div>
					</div>

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
							<h2 id="units-heading" class="text-md font-semibold">By unit, {breakdown.period_label}</h2>
							<p class="mt-1 mb-4 text-sm text-ink-2">Select a unit to see this KPI for it alone.</p>
							<BreakdownBars rows={breakdown.units} {kpi} hrefFor={unitHref} />
						</section>
					{:else}
						<div></div>
					{/if}

					<aside class="panel h-fit min-w-0 p-5 sm:p-6" aria-labelledby="about-heading">
						<h2 id="about-heading" class="text-md font-semibold">How it's calculated</h2>
						{#if kpi.description}<p class="mt-2 text-ink-2">{kpi.description}</p>{/if}
						<dl class="mt-4 space-y-3 text-sm">
							<div>
								<dt class="text-ink-2">Reported</dt>
								<dd class="mt-0.5">
									{GRAIN_NAMES[kpi.frequency].adjective}{kpi.applies_to_kind ? `, by each ${kpi.applies_to_kind.toLowerCase()}` : ', by each reporting unit'}{kpi.scope_unit_ids.length
										? ` in ${kpi.scope_unit_ids.map((id) => units.get(id)?.name ?? '?').join(', ')}`
										: ''}
								</dd>
							</div>
							<div>
								<dt class="text-ink-2">Combined</dt>
								<dd class="mt-0.5">{describeRules(kpi, weight?.name.toLowerCase())}</dd>
							</div>
							<div>
								<dt class="text-ink-2">Status</dt>
								<dd class="mt-0.5">
									{#if kpi.direction === 'none'}
										Tracked without a status.
									{:else}
										{kpi.direction === 'higher_is_better' ? 'Higher is better.' : 'Lower is better.'} Within {kpi.tolerance_pct}% of target counts as close.
									{/if}
								</dd>
							</div>
						</dl>
					</aside>
				</div>
			</div>
		{/if}
	</main>
{/if}
