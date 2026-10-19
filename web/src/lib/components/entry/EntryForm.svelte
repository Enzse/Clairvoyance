<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { fly } from 'svelte/transition';
	import { beforeNavigate, goto } from '$app/navigation';
	import { api, ApiError } from '$lib/api';
	import { byKpi, parseFigure, rowKey } from '$lib/entry';
	import { href } from '$lib/nav';
	import { label } from '$lib/period';
	import { problem } from '$lib/problem';
	import { toast } from '$lib/toast.svelte';
	import type { EntryForm, Unit } from '$lib/types';
	import ConfirmDialog from '../ConfirmDialog.svelte';
	import Icon from '../Icon.svelte';
	import KpiEntry from './KpiEntry.svelte';

	/** One unit's figures for a month. After saving, it offers the next unit still to do. */
	let { unitId, date, next, onSaved }: { unitId: number; date: string; next?: Unit; onSaved: () => void } = $props();

	let form = $state<EntryForm | null>(null);
	let inputs = $state<Record<string, string>>({});
	let notes = $state<Record<string, string>>({});
	let errors = $state<Record<string, string>>({});
	let loading = $state(true);
	let saving = $state(false);
	let error = $state('');
	let request = 0;

	$effect(() => {
		load(unitId, date);
	});

	async function load(unit: number, at: string) {
		const seq = ++request;
		loading = true;
		error = '';
		try {
			const current = await api.entryForm(unit, at);
			if (seq !== request) return;
			form = current;
			inputs = Object.fromEntries(current.rows.map((r) => [rowKey(r), r.value === null ? '' : String(r.value)]));
			notes = Object.fromEntries(current.rows.map((r) => [rowKey(r), r.note ?? '']));
			errors = {};
		} catch (e) {
			if (seq === request) error = problem(e, 'Could not load the form');
		} finally {
			if (seq === request) loading = false;
		}
	}

	const changed = $derived(
		form
			? form.rows.filter((r) => {
					const v = parseFigure(inputs[rowKey(r)] ?? '');
					return v === undefined || v !== r.value || (notes[rowKey(r)] ?? '').trim() !== (r.note ?? '');
				})
			: []
	);
	// Not a number, or a note on a figure left empty
	const invalid = $derived(changed.filter((r) => { const v = parseFigure(inputs[rowKey(r)] ?? ''); return v === undefined || (v === null && !!notes[rowKey(r)]?.trim()); }));
	const filled = $derived(form ? form.rows.filter((r) => r.value !== null).length : 0);
	const total = $derived(form?.rows.length ?? 0);
	const month = $derived(label(date, 'monthly'));

	async function save(event: SubmitEvent) {
		event.preventDefault();
		if (!form || !changed.length || invalid.length) return;
		saving = true;
		errors = {};
		const rows = changed;
		try {
			const result = await api.saveValues(
				rows.map((r) => ({ kpi_id: r.kpi.id, unit_id: form!.unit.id, period: r.period_start, value: parseFigure(inputs[rowKey(r)]) ?? null, note: notes[rowKey(r)]?.trim() || null }))
			);
			const count = result.created + result.updated + result.deleted;
			toast(`Saved ${count} ${count === 1 ? 'figure' : 'figures'} for ${form.unit.name}`);
			await load(form.unit.id, date);
			onSaved();
		} catch (e) {
			if (e instanceof ApiError && Object.keys(e.details).length) {
				for (const [key, message] of Object.entries(e.details)) {
					const row = rows[Number(key.split('.')[1])];
					if (row) errors[rowKey(row)] = String(message);
				}
			} else {
				toast(problem(e, 'Could not save'), 'error');
			}
		} finally {
			saving = false;
		}
	}

	function discard() {
		if (form) {
			inputs = Object.fromEntries(form.rows.map((r) => [rowKey(r), r.value === null ? '' : String(r.value)]));
			notes = Object.fromEntries(form.rows.map((r) => [rowKey(r), r.note ?? '']));
		}
		errors = {};
	}

	// Leaving with unsaved figures asks first; closing the tab is left to the browser's own warning
	let asking = $state(false);
	let destination = '';
	let leaveAnyway = false;
	beforeNavigate((nav) => {
		if (leaveAnyway || saving || !changed.length || nav.type === 'leave' || !nav.to) return;
		nav.cancel();
		destination = nav.to.url.pathname + nav.to.url.search;
		asking = true;
	});
	function leave() {
		leaveAnyway = true;
		asking = false;
		goto(destination);
	}
</script>

<svelte:window onbeforeunload={(e) => { if (changed.length) e.preventDefault(); }} />

{#if error}
	<div class="panel -mt-5 flex gap-3 p-5 text-bad-ink" role="alert"><Icon name="alert" size={18} class="mt-0.5" />{error}</div>
{:else if form}
	<form class="panel -mt-5 transition-opacity {loading ? 'opacity-55' : ''}" onsubmit={save} novalidate>
		{#if total}
			<div class="flex items-center gap-3 border-b border-line px-5 py-3 sm:px-6">
				<span class="h-1.5 flex-1 overflow-hidden rounded-full bg-line-strong">
					<span class="block h-full rounded-full transition-[width] duration-500 {filled === total ? 'bg-good' : 'bg-chart'}" style:width="{(filled / total) * 100}%"></span>
				</span>
				<span class="num text-sm text-ink-2">{filled === total ? 'All in' : `${filled} of ${total} saved`}</span>
			</div>
		{:else}
			<p class="p-6 text-ink-2">No KPIs apply to {form.unit.name}.</p>
		{/if}

		<div class="divide-y divide-line">
			{#each byKpi(form.rows) as group (group.kpi.id)}
				<KpiEntry kpi={group.kpi} rows={group.rows} bind:inputs bind:notes {errors} canEdit={form.can_edit} />
			{/each}
		</div>

		{#if form.can_edit && total}
			<div class="sticky bottom-0 flex flex-wrap items-center gap-3 rounded-b-xl border-t border-line bg-surface/95 px-5 py-3 backdrop-blur sm:px-6">
				{#if changed.length}
					<p class="text-sm text-ink-2" aria-live="polite">{changed.length} unsaved {changed.length === 1 ? 'change' : 'changes'}</p>
					<button type="button" class="btn btn-ghost ml-auto" disabled={saving} onclick={discard}>Discard</button>
					<button class="btn btn-primary" disabled={invalid.length > 0 || saving}>{saving ? 'Saving…' : `Save ${month}`}</button>
				{:else}
					<p class="text-sm text-ink-2" aria-live="polite">{filled === total ? `${form.unit.name} is done for ${month}.` : 'Everything typed in is saved.'}</p>
					{#if next}
						<a class="btn btn-primary ml-auto" href={href('/enter', { unit: next.id, date })} in:fly={{ x: -8, duration: 220, easing: cubicOut }}>
							Next: {next.name} <Icon name="arrow-right" size={15} />
						</a>
					{/if}
				{/if}
			</div>
		{:else if !form.can_edit}
			<p class="border-t border-line px-6 py-4 text-sm text-ink-2">You can view these figures but not change them.</p>
		{/if}
	</form>
{/if}

<ConfirmDialog bind:open={asking} title="Leave without saving?" icon="alert" tone="bg-warn-wash text-warn-ink" confirmLabel="Leave without saving" cancelLabel="Keep editing" onConfirm={leave}>
	<p>{changed.length === 1 ? 'One figure you typed hasn’t' : `${changed.length} figures you typed haven’t`} been saved yet.</p>
</ConfirmDialog>
