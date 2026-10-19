<script lang="ts">
	import { beforeNavigate, goto } from '$app/navigation';
	import { page } from '$app/state';
	import { api, ApiError } from '$lib/api';
	import Icon from '$lib/components/Icon.svelte';
	import { formatValue } from '$lib/format';
	import { href } from '$lib/nav';
	import { align, label, shift, today } from '$lib/period';
	import { toast } from '$lib/toast.svelte';
	import type { EntryForm, EntryRow } from '$lib/types';
	import { units } from '$lib/units.svelte';

	const enterable = $derived(units.enterable());
	const requestedUnit = $derived(Number(page.url.searchParams.get('unit')));
	const unitId = $derived(enterable.some((u) => u.id === requestedUnit) ? requestedUnit : (enterable[0]?.id ?? null));
	const date = $derived(align(page.url.searchParams.get('date') ?? today(), 'monthly'));

	let form = $state<EntryForm | null>(null);
	let previous = $state<EntryForm | null>(null);
	let inputs = $state<Record<number, string>>({});
	let rowErrors = $state<Record<number, string>>({});
	let loading = $state(true);
	let saving = $state(false);
	let error = $state('');
	let request = 0;

	$effect(() => {
		if (unitId !== null) load(unitId, date);
	});

	async function load(unit: number, at: string) {
		const seq = ++request;
		loading = true;
		error = '';
		try {
			const [current, before] = await Promise.all([api.entryForm(unit, at), api.entryForm(unit, shift(at, 'monthly', -1))]);
			if (seq !== request) return;
			form = current;
			previous = before;
			inputs = Object.fromEntries(current.rows.map((r) => [r.kpi.id, r.value === null ? '' : String(r.value)]));
			rowErrors = {};
		} catch (e) {
			if (seq === request) error = e instanceof ApiError ? e.message : 'Could not load the form';
		} finally {
			if (seq === request) loading = false;
		}
	}

	/** "1,250" and " 95.3 " are fine; anything else is not a number. */
	function parse(text: string): number | null | undefined {
		const cleaned = text.trim().replace(/,/g, '');
		if (cleaned === '') return null;
		return /^-?(\d+\.?\d*|\.\d+)$/.test(cleaned) ? Number(cleaned) : undefined;
	}

	const changed = $derived(
		form
			? form.rows.filter((r) => {
					const v = parse(inputs[r.kpi.id] ?? '');
					return v === undefined || v !== r.value;
				})
			: []
	);
	const invalid = $derived(changed.filter((r) => parse(inputs[r.kpi.id] ?? '') === undefined));

	function previousRow(kpiId: number, periodStart: string) {
		const row = previous?.rows.find((r) => r.kpi.id === kpiId);
		return row && row.period_start !== periodStart ? row : null;
	}

	/** "Nov 2025: 97.5%. Last saved 18 Oct 2026 by lee@example.com" */
	function history(row: EntryRow, before: EntryRow | null): string {
		const parts: string[] = [];
		if (before) parts.push(`${before.period_label}: ${formatValue(row.kpi, before.value)}`);
		if (row.updated_at) {
			const [y, m, d] = row.updated_at.slice(0, 10).split('-').map(Number);
			parts.push(`Last saved ${d} ${label(`${y}-${String(m).padStart(2, '0')}-01`, 'monthly')}${row.updated_by ? ` by ${row.updated_by}` : ''}`);
		}
		return parts.join('. ');
	}

	async function save(event: SubmitEvent) {
		event.preventDefault();
		if (!form || !changed.length || invalid.length) return;
		saving = true;
		rowErrors = {};
		const rows = changed;
		try {
			const result = await api.saveValues(
				rows.map((r) => ({ kpi_id: r.kpi.id, unit_id: form!.unit.id, period: r.period_start, value: parse(inputs[r.kpi.id]) ?? null, note: r.note }))
			);
			const count = result.created + result.updated + result.deleted;
			toast(`Saved ${count} ${count === 1 ? 'figure' : 'figures'} for ${form.unit.name}`);
			await load(form.unit.id, date);
		} catch (e) {
			if (e instanceof ApiError && Object.keys(e.details).length) {
				for (const [key, message] of Object.entries(e.details)) {
					const index = Number(key.split('.')[1]);
					if (rows[index]) rowErrors[rows[index].kpi.id] = String(message);
				}
			} else {
				toast(e instanceof ApiError ? e.message : 'Could not save', 'error');
			}
		} finally {
			saving = false;
		}
	}

	function discard() {
		if (form) inputs = Object.fromEntries(form.rows.map((r) => [r.kpi.id, r.value === null ? '' : String(r.value)]));
		rowErrors = {};
	}

	beforeNavigate((nav) => {
		if (changed.length && !saving && !confirm('You have unsaved figures. Leave without saving them?')) nav.cancel();
	});

	const go = (params: { unit?: number | null; date?: string }) => href('/enter', { unit: params.unit ?? unitId, date: params.date ?? date });
	const nextInFuture = $derived(shift(date, 'monthly', 1) > today());

	// Group the unit picker by where each unit sits
	const groups = $derived.by(() => {
		const map = new Map<string, typeof enterable>();
		for (const u of enterable) {
			const key = units.pathLabel(u.id) || 'Units';
			map.set(key, [...(map.get(key) ?? []), u]);
		}
		return [...map.entries()];
	});
</script>

<svelte:window onbeforeunload={(e) => { if (changed.length) e.preventDefault(); }} />
<svelte:head><title>Enter data | Clairvoyance</title></svelte:head>

<section class="bg-navy text-navy-ink">
	<div class="mx-auto max-w-[1240px] px-4 pt-5 pb-9 sm:px-6">
		<div class="border-t border-navy-2 pt-6">
			<h1 class="text-2xl font-semibold tracking-[-0.025em]">Enter data</h1>
			<p class="mt-2 text-md text-navy-ink-2">Figures for one unit and one month. Leave a field empty if you have no figure for it.</p>
		</div>
		{#if unitId !== null}
			<div class="mt-6 flex max-w-[920px] flex-wrap items-end gap-3">
				<label class="min-w-64 flex-1">
					<span class="mb-1.5 block text-sm text-navy-ink-2">Unit</span>
					<select class="input" value={unitId} onchange={(e) => goto(go({ unit: Number(e.currentTarget.value) }))}>
						{#each groups as [group, list] (group)}
							<optgroup label={group}>
								{#each list as u (u.id)}<option value={u.id}>{u.name}</option>{/each}
							</optgroup>
						{/each}
					</select>
				</label>
				<div>
					<span class="mb-1.5 block text-sm text-navy-ink-2">Month</span>
					<div class="flex h-9 items-center rounded-lg bg-navy-2/70">
						<a class="p-2 text-navy-ink-2 hover:text-navy-ink" href={go({ date: shift(date, 'monthly', -1) })} aria-label="Previous month">
							<Icon name="chevron-left" size={18} />
						</a>
						<span class="min-w-[84px] text-center font-semibold" aria-live="polite">{label(date, 'monthly')}</span>
						{#if nextInFuture}
							<span class="p-2 opacity-35" aria-hidden="true"><Icon name="chevron-right" size={18} /></span>
						{:else}
							<a class="p-2 text-navy-ink-2 hover:text-navy-ink" href={go({ date: shift(date, 'monthly', 1) })} aria-label="Next month">
								<Icon name="chevron-right" size={18} />
							</a>
						{/if}
					</div>
				</div>
			</div>
		{/if}
	</div>
</section>

<main class="mx-auto max-w-[1240px] px-4 pb-28 sm:px-6"><div class="max-w-[920px]">
	{#if unitId === null}
		<div class="panel -mt-5 p-6">
			<p class="font-semibold">There are no units you can enter data for</p>
			<p class="mt-1 text-ink-2">An administrator can make you a contributor for the units you report on.</p>
		</div>
	{:else if error}
		<div class="panel -mt-5 flex gap-3 p-5 text-bad-ink" role="alert"><Icon name="alert" size={18} class="mt-0.5" />{error}</div>
	{:else if form}
		<form class="panel -mt-5 transition-opacity {loading ? 'opacity-55' : ''}" onsubmit={save} novalidate>
			{#if form.rows.length === 0}
				<p class="p-6 text-ink-2">No KPIs apply to {form.unit.name}.</p>
			{/if}
			{#each form.rows as row, i (row.kpi.id)}
				{@const before = previousRow(row.kpi.id, row.period_start)}
				{@const parsed = parse(inputs[row.kpi.id] ?? '')}
				{@const message = rowErrors[row.kpi.id] ?? (parsed === undefined ? 'Enter a number, like 1250 or 95.5' : '')}
				<div class="grid gap-x-6 gap-y-2 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_220px] sm:px-6 {i ? 'border-t border-line' : ''}">
					<div>
						<label for="kpi-{row.kpi.id}" class="font-semibold">{row.kpi.name}</label>
						{#if row.kpi.frequency !== 'monthly'}<span class="ml-2 text-sm text-muted">{row.period_label}</span>{/if}
						{#if row.kpi.description}<p class="text-sm text-ink-2">{row.kpi.description}</p>{/if}
						<p class="mt-1 text-sm text-muted">{history(row, before)}</p>
					</div>
					<div>
						<div class="relative">
							{#if row.kpi.prefix}<span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted">{row.kpi.prefix}</span>{/if}
							<input
								id="kpi-{row.kpi.id}"
								class="input num text-right text-md {row.kpi.prefix ? 'pl-7' : ''} {row.kpi.suffix ? 'pr-8' : ''} {parsed !== row.value && parsed !== undefined ? 'bg-accent-wash' : ''}"
								inputmode="decimal"
								autocomplete="off"
								disabled={!form.can_edit}
								bind:value={inputs[row.kpi.id]}
								aria-invalid={!!message}
								aria-describedby={message ? `err-${row.kpi.id}` : undefined}
							/>
							{#if row.kpi.suffix}<span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-muted">{row.kpi.suffix}</span>{/if}
						</div>
						{#if message}<p id="err-{row.kpi.id}" class="field-error">{message}</p>{/if}
					</div>
				</div>
			{/each}

			{#if form.can_edit && form.rows.length}
				<div class="sticky bottom-0 flex items-center gap-3 rounded-b-xl border-t border-line bg-surface/95 px-5 py-3 backdrop-blur sm:px-6">
					<p class="text-sm text-ink-2" aria-live="polite">
						{changed.length ? `${changed.length} unsaved ${changed.length === 1 ? 'change' : 'changes'}` : 'No changes'}
					</p>
					<button type="button" class="btn btn-ghost ml-auto" disabled={!changed.length || saving} onclick={discard}>Discard</button>
					<button class="btn btn-primary" disabled={!changed.length || invalid.length > 0 || saving}>
						{saving ? 'Saving…' : `Save ${label(date, 'monthly')}`}
					</button>
				</div>
			{:else if !form.can_edit}
				<p class="border-t border-line px-6 py-4 text-sm text-ink-2">You can view these figures but not change them.</p>
			{/if}
		</form>
	{/if}
</div></main>
