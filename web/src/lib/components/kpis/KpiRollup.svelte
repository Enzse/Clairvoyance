<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { fly } from 'svelte/transition';
	import { formatValue } from '$lib/format';
	import { EXAMPLE, rollupExample, type KpiForm } from '$lib/kpi-form';
	import { GRAIN_NAMES } from '$lib/period';
	import { term } from '$lib/terms.svelte';
	import type { Kpi } from '$lib/types';
	import OptionCards from '../OptionCards.svelte';

	/**
	 * The two rules that make every total right: how figures add up over a longer period, and
	 * across units. A worked example underneath recalculates as the choices change.
	 */
	let {
		form = $bindable(),
		weightChoices,
		names,
		errors = {}
	}: { form: KpiForm; weightChoices: Kpi[]; names: [string, string]; errors?: Record<string, string> } = $props();

	const adjective = $derived(GRAIN_NAMES[form.frequency].adjective.toLowerCase());
	const longer = $derived(form.frequency === 'yearly' ? 'several years' : form.frequency === 'quarterly' ? 'a year' : 'a quarter');
	const example = $derived(rollupExample(form.time_aggregation, form.unit_aggregation));
	const show = (n: number) => formatValue({ prefix: form.prefix, suffix: form.suffix, decimals: Number.isInteger(n) ? 0 : 1 }, n);
	const periods = $derived(form.frequency === 'monthly' ? ['Jan', 'Feb', 'Mar'] : ['1st', '2nd', '3rd']);
	const noun = $derived(GRAIN_NAMES[form.frequency].noun.toLowerCase());
</script>

<div class="space-y-6">
	<OptionCards
		name="k-time"
		legend="Over {longer}, the {adjective} figures are…"
		bind:value={form.time_aggregation}
		options={[
			{ value: 'sum', label: 'Added up', hint: 'Things that pile up: sales, visits, absences.', icon: 'plus' },
			{ value: 'average', label: 'Averaged', hint: 'Rates and scores: satisfaction, conversion.', icon: 'percent' },
			{ value: 'last', label: 'The latest one', hint: 'Counts at a moment: headcount, stock.', icon: 'calendar' }
		]}
	/>

	<div>
		<OptionCards
			name="k-units"
			legend="When several {term.many} are combined, their figures are…"
			bind:value={form.unit_aggregation}
			error={errors.weight_kpi_id}
			options={[
				{ value: 'sum', label: 'Added up', hint: `The whole is the sum of its ${term.many}.`, icon: 'plus' },
				{ value: 'average', label: 'Averaged', hint: `Each ${term.one} counts equally.`, icon: 'percent' },
				{ value: 'weighted_average', label: 'Weighted', hint: `Bigger ${term.many} count more.`, icon: 'layers' }
			]}
		/>
		{#if form.unit_aggregation === 'weighted_average'}
			<label class="mt-3 block" transition:fly={{ y: -4, duration: 160 }}>
				<span class="field-label">Weighted by</span>
				<select class="input" bind:value={form.weight_kpi_id}>
					<option value={null}>Choose a {adjective} KPI, e.g. number of customers…</option>
					{#each weightChoices as k (k.id)}<option value={k.id}>{k.name}</option>{/each}
				</select>
				{#if !weightChoices.length}<p class="field-hint">Add a {adjective} KPI to weight by first, such as number of customers.</p>{/if}
			</label>
		{/if}
	</div>

	<label class="flex items-start gap-2.5">
		<input type="checkbox" class="mt-0.5 size-4 shrink-0 accent-[var(--accent)]" bind:checked={form.missing_is_zero} />
		<span>
			<span class="text-sm font-medium">A {noun} with no figure counts as 0</span>
			<span class="block text-sm text-ink-2">
				For things that only happen when they're entered, like sales or complaints. Leave it off when a missing figure just hasn't come in yet, like a satisfaction score.
			</span>
		</span>
	</label>

	<section class="rounded-xl bg-surface-2 p-4 ring-1 ring-line" aria-label="Worked example">
		<p class="text-xs font-semibold tracking-wide text-muted uppercase">For example</p>
		<table class="mt-2 w-full text-sm">
			<tbody>
				{#each [{ name: names[0], months: EXAMPLE.a, result: example.a, weight: EXAMPLE.weights[0] }, { name: names[1], months: EXAMPLE.b, result: example.b, weight: EXAMPLE.weights[1] }] as { name, months, result, weight } (name)}
					<tr>
						<th scope="row" class="py-1 pr-3 text-left font-medium">
							{name}
							{#if form.unit_aggregation === 'weighted_average'}<span class="block text-xs font-normal text-muted">weight {weight}</span>{/if}
						</th>
						{#each months as m, i (i)}<td class="num py-1 pr-3 text-ink-2"><span class="text-xs text-muted">{periods[i]}</span> {show(m)}</td>{/each}
						<td class="py-1 text-muted">→</td>
						<td class="num py-1 text-right font-semibold">{#key result}<span class="inline-block" in:fly={{ y: -6, duration: 250, easing: cubicOut }}>{show(result)}</span>{/key}</td>
					</tr>
				{/each}
				<tr class="border-t border-line">
					<th scope="row" colspan="5" class="pt-2 text-left font-medium">Together</th>
					<td class="num pt-2 text-right text-lg font-semibold text-accent">
						{#key example.total}<span class="inline-block" in:fly={{ y: -8, duration: 300, easing: cubicOut }}>{show(example.total)}</span>{/key}
					</td>
				</tr>
			</tbody>
		</table>
	</section>
</div>
