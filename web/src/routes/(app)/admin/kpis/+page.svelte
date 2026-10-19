<script lang="ts">
	import { onMount } from 'svelte';
	import { api, ApiError } from '$lib/api';
	import ChoiceGroup from '$lib/components/ChoiceGroup.svelte';
	import Drawer from '$lib/components/Drawer.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import { describeRules, formatValue } from '$lib/format';
	import { align, FREQUENCIES, GRAIN_NAMES } from '$lib/period';
	import { toast } from '$lib/toast.svelte';
	import type { Kpi, Target } from '$lib/types';
	import { units } from '$lib/units.svelte';

	let kpis = $state<Kpi[]>([]);
	let loaded = $state(false);

	onMount(load);
	async function load() {
		kpis = await api.kpis(true);
		loaded = true;
	}

	const leafKinds = $derived([...new Set(units.list.filter((u) => u.is_leaf && u.kind).map((u) => u.kind!))].sort());

	// ---- Editor
	type Form = Omit<Kpi, 'id' | 'description' | 'applies_to_kind' | 'weight_kpi_id'> & { description: string; applies_to_kind: string; weight_kpi_id: number | '' };

	const blank = (): Form => ({
		code: '', name: '', description: '', frequency: 'monthly', time_aggregation: 'sum', unit_aggregation: 'sum',
		weight_kpi_id: '', direction: 'higher_is_better', tolerance_pct: 10, applies_to_kind: '', decimals: 0, prefix: '', suffix: '', is_active: true
	});

	let open = $state(false);
	let editing = $state<Kpi | null>(null);
	let form = $state<Form>(blank());
	let codeTouched = $state(false);
	let targets = $state<(Target & { key: number })[]>([]);
	let error = $state<ApiError | null>(null);
	let busy = $state(false);
	let keySeq = 0;

	function startNew() {
		editing = null;
		form = blank();
		codeTouched = false;
		targets = [];
		error = null;
		open = true;
	}

	async function startEdit(kpi: Kpi) {
		editing = kpi;
		form = { ...kpi, description: kpi.description ?? '', applies_to_kind: kpi.applies_to_kind ?? '', weight_kpi_id: kpi.weight_kpi_id ?? '' };
		codeTouched = true;
		error = null;
		targets = [];
		open = true;
		targets = (await api.targets(kpi.id)).map((t) => ({ ...t, key: keySeq++ }));
	}

	$effect(() => {
		if (!codeTouched) form.code = form.name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '').replace(/^(\d)/, 'k_$1').slice(0, 64);
	});

	const weightChoices = $derived(kpis.filter((k) => k.id !== editing?.id && k.is_active && k.frequency === form.frequency && k.unit_aggregation !== 'weighted_average'));

	function addTarget(unitId: number | null) {
		targets = [...targets, { key: keySeq++, unit_id: unitId, effective_from: align(new Date().toISOString().slice(0, 10), 'yearly'), value: 0 }];
	}

	async function save(event: SubmitEvent) {
		event.preventDefault();
		busy = true;
		error = null;
		const body: Partial<Kpi> = {
			...form,
			description: form.description || null,
			applies_to_kind: form.applies_to_kind || null,
			weight_kpi_id: form.unit_aggregation === 'weighted_average' && form.weight_kpi_id !== '' ? Number(form.weight_kpi_id) : null
		};
		try {
			const saved = editing ? await api.updateKpi(editing.id, body) : await api.createKpi(body);
			try {
				await api.saveTargets(saved.id, targets.map(({ unit_id, effective_from, value }) => ({ unit_id, effective_from, value: Number(value) })));
			} catch (e) {
				editing = saved;
				throw e;
			}
			toast(`Saved ${saved.name}`);
			open = false;
			await load();
		} catch (e) {
			error = e instanceof ApiError ? e : new ApiError(0, 'Could not save');
		} finally {
			busy = false;
		}
	}

	const sampleValue = $derived(formatValue({ decimals: form.decimals, prefix: form.prefix, suffix: form.suffix }, 1234.5678));
	const unitWord = $derived(form.applies_to_kind ? form.applies_to_kind.toLowerCase() : 'reporting unit');
	const fieldError = (name: string) => (error?.field(name) ? `This ${error.field(name)}` : undefined);
</script>

<svelte:head><title>KPIs | Clairvoyance</title></svelte:head>

<div class="flex flex-wrap items-end justify-between gap-4">
	<div>
		<h2 class="text-lg font-semibold">KPIs</h2>
		<p class="mt-1 max-w-[70ch] text-ink-2">Each KPI decides once how its figures combine over time and across units, so it shows the same number everywhere.</p>
	</div>
	<button class="btn btn-primary" onclick={startNew}><Icon name="plus" size={15} /> New KPI</button>
</div>

{#if loaded}
	<div class="panel mt-5 overflow-x-auto">
		<table class="w-full min-w-[760px] text-left">
			<thead class="bg-surface-2 text-sm text-ink-2">
				<tr class="border-b border-line">
					<th scope="col" class="px-5 py-2.5 font-medium">KPI</th>
					<th scope="col" class="px-3 py-2.5 font-medium">Reported</th>
					<th scope="col" class="px-3 py-2.5 font-medium">Combined</th>
					<th scope="col" class="px-3 py-2.5 font-medium">Judged</th>
					<th scope="col" class="px-5 py-2.5"><span class="sr-only">Actions</span></th>
				</tr>
			</thead>
			<tbody>
				{#each kpis as kpi (kpi.id)}
					<tr class="border-b border-line align-top last:border-b-0 hover:bg-surface-2 {kpi.is_active ? '' : 'text-muted'}">
						<th scope="row" class="px-5 py-3 font-normal">
							<span class="font-semibold">{kpi.name}</span>
							{#if !kpi.is_active}<span class="ml-1.5 rounded bg-surface-2 px-1.5 text-xs ring-1 ring-line">Inactive</span>{/if}
							<span class="block font-mono text-xs text-muted">{kpi.code}</span>
						</th>
						<td class="px-3 py-3 text-sm">{GRAIN_NAMES[kpi.frequency].adjective}{kpi.applies_to_kind ? `, ${kpi.applies_to_kind} only` : ''}</td>
						<td class="max-w-80 px-3 py-3 text-sm text-ink-2">{describeRules(kpi, kpis.find((k) => k.id === kpi.weight_kpi_id)?.name.toLowerCase())}</td>
						<td class="px-3 py-3 text-sm">
							{kpi.direction === 'none' ? 'Tracked only' : kpi.direction === 'higher_is_better' ? 'Higher is better' : 'Lower is better'}
							{#if kpi.direction !== 'none'}<span class="block text-ink-2">Close within {kpi.tolerance_pct}%</span>{/if}
						</td>
						<td class="px-5 py-3 text-right"><button class="btn btn-ghost btn-sm" onclick={() => startEdit(kpi)}>Edit</button></td>
					</tr>
				{:else}
					<tr><td colspan="5" class="px-5 py-10 text-center text-ink-2">No KPIs yet. Define the first one to start collecting figures.</td></tr>
				{/each}
			</tbody>
		</table>
	</div>
{/if}

<Drawer bind:open title={editing ? `Edit ${editing.name}` : 'New KPI'} width={620}>
	<form id="kpi-form" class="space-y-6" onsubmit={save}>
		<div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_200px]">
			<div>
				<label class="field-label" for="k-name">Name</label>
				<input id="k-name" class="input" required bind:value={form.name} aria-invalid={!!fieldError('name')} />
				{#if fieldError('name')}<p class="field-error">{fieldError('name')}</p>{/if}
			</div>
			<div>
				<label class="field-label" for="k-code">Code</label>
				<input id="k-code" class="input font-mono" required bind:value={form.code} oninput={() => (codeTouched = true)} aria-invalid={!!fieldError('code')} />
				<p class={fieldError('code') ? 'field-error' : 'field-hint'}>{fieldError('code') ?? 'Matches spreadsheet columns on import.'}</p>
			</div>
		</div>
		<div>
			<label class="field-label" for="k-desc">What it measures <span class="font-normal text-muted">(optional)</span></label>
			<textarea id="k-desc" class="input" rows="2" bind:value={form.description}></textarea>
		</div>

		<div class="grid gap-4 sm:grid-cols-2">
			<div>
				<label class="field-label" for="k-freq">Reported</label>
				<select id="k-freq" class="input" bind:value={form.frequency} aria-invalid={!!fieldError('frequency')}>
					{#each FREQUENCIES as f (f)}<option value={f}>{GRAIN_NAMES[f].adjective}</option>{/each}
				</select>
				{#if fieldError('frequency')}<p class="field-error">{fieldError('frequency')}</p>{/if}
			</div>
			<div>
				<label class="field-label" for="k-kind">By</label>
				<select id="k-kind" class="input" bind:value={form.applies_to_kind}>
					<option value="">Every reporting unit</option>
					{#each leafKinds as k (k)}<option value={k}>Each {k.toLowerCase()} only</option>{/each}
				</select>
			</div>
		</div>

		<ChoiceGroup
			name="time"
			legend="For a longer period, the {GRAIN_NAMES[form.frequency].adjective.toLowerCase()} figures are…"
			bind:value={form.time_aggregation}
			options={[
				{ value: 'sum', label: 'Added up', hint: 'Things that accumulate: sales, absences, visits.' },
				{ value: 'average', label: 'Averaged', hint: 'Rates and scores: conversion rate, customer satisfaction.' },
				{ value: 'last', label: 'The latest one', hint: 'Counts at a point in time: headcount, open tickets, inventory.' }
			]}
		/>

		<div>
			<ChoiceGroup
				name="units"
				legend="Across units, figures are…"
				bind:value={form.unit_aggregation}
				error={fieldError('weight_kpi_id')}
				options={[
					{ value: 'sum', label: 'Added up', hint: 'The company total is the sum of its branches or teams.' },
					{ value: 'average', label: 'Averaged, each unit counting equally', hint: 'A small branch counts as much as a large one.' },
					{ value: 'weighted_average', label: 'Averaged, weighted by another KPI', hint: 'Bigger units count more, e.g. satisfaction weighted by number of customers.' }
				]}
			/>
			{#if form.unit_aggregation === 'weighted_average'}
				<label class="mt-3 block pl-7">
					<span class="field-label">Weighted by</span>
					<select class="input" bind:value={form.weight_kpi_id}>
						<option value="">Choose a {GRAIN_NAMES[form.frequency].adjective.toLowerCase()} KPI…</option>
						{#each weightChoices as k (k.id)}<option value={k.id}>{k.name}</option>{/each}
					</select>
				</label>
			{/if}
		</div>

		<div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_160px]">
			<ChoiceGroup
				name="direction"
				legend="Against its target"
				bind:value={form.direction}
				options={[
					{ value: 'higher_is_better', label: 'Higher is better', hint: 'On target at or above it.' },
					{ value: 'lower_is_better', label: 'Lower is better', hint: 'On target at or below it.' },
					{ value: 'none', label: 'Track only', hint: 'Show the figure without a status.' }
				]}
			/>
			{#if form.direction !== 'none'}
				<div>
					<label class="field-label" for="k-tol">Close means within</label>
					<div class="relative">
						<input id="k-tol" class="input num pr-8" type="number" min="0" step="0.5" bind:value={form.tolerance_pct} />
						<span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-muted">%</span>
					</div>
					<p class="field-hint">of the target</p>
				</div>
			{/if}
		</div>

		<fieldset>
			<legend class="field-label">Shown as</legend>
			<div class="grid grid-cols-3 gap-3">
				<label><span class="mb-1 block text-sm text-ink-2">Before</span><input class="input" maxlength="10" placeholder="₱" bind:value={form.prefix} /></label>
				<label><span class="mb-1 block text-sm text-ink-2">After</span><input class="input" maxlength="10" placeholder="%" bind:value={form.suffix} /></label>
				<label><span class="mb-1 block text-sm text-ink-2">Decimals</span><input class="input num" type="number" min="0" max="6" bind:value={form.decimals} /></label>
			</div>
			<p class="field-hint">Looks like {sampleValue}</p>
		</fieldset>

		{#if editing}
			<label class="flex items-start gap-2.5">
				<input type="checkbox" class="mt-0.5 size-4 accent-[var(--accent)]" bind:checked={form.is_active} />
				<span><span class="font-medium">Active</span><span class="block text-sm text-ink-2">Inactive KPIs leave the scorecard and data entry. Their figures are kept.</span></span>
			</label>
		{/if}

		<section class="border-t border-line pt-6" aria-labelledby="targets-heading">
			<h3 id="targets-heading" class="font-semibold">Targets</h3>
			<p class="mt-1 text-sm text-ink-2">
				Set per {unitWord} per {GRAIN_NAMES[form.frequency].noun.toLowerCase()}. Parent units and longer periods combine targets the same way as the
				figures. A target applies from its start date until a later one replaces it.
			</p>
			{#each [null, 'unit'] as scope (scope)}
				{@const list = targets.filter((t) => (scope === null ? t.unit_id === null : t.unit_id !== null))}
				<div class="mt-4">
					<p class="text-sm font-medium">{scope === null ? `Every ${unitWord}` : 'Specific units'}</p>
					{#each list as t (t.key)}
						<div class="mt-2 flex items-center gap-2">
							{#if scope !== null}
								<select class="input min-w-0 flex-1" bind:value={t.unit_id} aria-label="Unit">
									{#each units.list.filter((u) => u.is_active) as u (u.id)}<option value={u.id}>{u.name}</option>{/each}
								</select>
							{/if}
							<label class="flex items-center gap-2 text-sm text-ink-2">From <input class="input w-40" type="date" bind:value={t.effective_from} /></label>
							<input class="input num w-28 text-right" type="number" step="any" bind:value={t.value} aria-label="Target value" />
							<button type="button" class="rounded p-1.5 text-muted hover:bg-bad-wash hover:text-bad-ink" aria-label="Remove target" onclick={() => (targets = targets.filter((x) => x.key !== t.key))}>
								<Icon name="x" size={15} />
							</button>
						</div>
					{/each}
					<button type="button" class="btn btn-ghost btn-sm mt-2 -ml-2.5" onclick={() => addTarget(scope === null ? null : (units.list.find((u) => u.is_leaf)?.id ?? null))}>
						<Icon name="plus" size={14} /> Add {scope === null ? 'a target' : 'a unit target'}
					</button>
				</div>
			{/each}
			{#if error && Object.keys(error.details).some((k) => k.startsWith('targets'))}
				<p class="field-error">{Object.entries(error.details).filter(([k]) => k.startsWith('targets')).map(([k, m]) => `Row ${Number(k.split('.')[1]) + 1}: ${m}`).join(' ')}</p>
			{/if}
		</section>

		{#if error && !Object.keys(error.details).length}<p class="text-sm text-bad-ink" role="alert">{error.message}</p>{/if}
	</form>
	{#snippet footer()}
		<button class="btn btn-secondary" type="button" onclick={() => (open = false)}>Cancel</button>
		<button class="btn btn-primary" form="kpi-form" disabled={busy}>{busy ? 'Saving…' : editing ? 'Save changes' : 'Create KPI'}</button>
	{/snippet}
</Drawer>
