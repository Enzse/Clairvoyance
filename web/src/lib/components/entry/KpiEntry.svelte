<script lang="ts">
	import { slide } from 'svelte/transition';
	import { parseFigure, periodName, rowKey } from '$lib/entry';
	import { formatValue } from '$lib/format';
	import { label } from '$lib/period';
	import type { EntryRow, Kpi } from '$lib/types';
	import Icon from '../Icon.svelte';

	/**
	 * One KPI's figures for the month: a single field, or one per week or day for KPIs
	 * reported more often than monthly.
	 */
	let {
		kpi,
		rows,
		inputs = $bindable(),
		notes = $bindable(),
		errors,
		canEdit
	}: { kpi: Kpi; rows: EntryRow[]; inputs: Record<string, string>; notes: Record<string, string>; errors: Record<string, string>; canEdit: boolean } = $props();

	const several = $derived(rows.length > 1);
	// A note box opens on request, or straight away when there already is a note
	let noting = $state(false);
	const noteKey = $derived(rowKey(rows[0]));
	const showNote = $derived(!several && (noting || !!notes[noteKey]));
	const filled = $derived(rows.filter((r) => parseFigure(inputs[rowKey(r)] ?? '') != null).length);
	const complete = $derived(filled === rows.length);

	function messageFor(row: EntryRow): string {
		const key = rowKey(row);
		const value = parseFigure(inputs[key] ?? '');
		if (errors[key]) return errors[key];
		if (value === undefined) return 'Enter a number, like 1250 or 95.5';
		return value === null && notes[key]?.trim() ? 'Add the figure the note is about, or clear the note' : '';
	}

	/** "May 2026: 1,200. Last saved 18 Oct by lee@example.com" */
	function history(row: EntryRow): string {
		const parts: string[] = [];
		if (row.previous_value !== null) parts.push(`${row.previous_label}: ${formatValue(kpi, row.previous_value)}`);
		if (row.updated_at) {
			const [y, m, d] = row.updated_at.slice(0, 10).split('-').map(Number);
			parts.push(`Last saved ${d} ${label(`${y}-${String(m).padStart(2, '0')}-01`, 'monthly')}${row.updated_by ? ` by ${row.updated_by}` : ''}`);
		}
		return parts.join('. ');
	}
</script>

{#snippet field(row: EntryRow, compact: boolean)}
	{@const key = rowKey(row)}
	{@const parsed = parseFigure(inputs[key] ?? '')}
	{@const message = messageFor(row)}
	<div class="relative">
		{#if kpi.prefix}<span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted">{kpi.prefix}</span>{/if}
		<input
			id="kpi-{key}"
			class="input num text-right {compact ? 'h-9' : 'text-md'} {kpi.prefix ? 'pl-7' : ''} {kpi.suffix ? 'pr-8' : ''} {parsed !== row.value && parsed !== undefined ? 'bg-accent-wash' : ''}"
			inputmode="decimal"
			autocomplete="off"
			disabled={!canEdit}
			bind:value={inputs[key]}
			placeholder={compact && row.previous_value !== null ? formatValue(kpi, row.previous_value) : ''}
			title={compact ? history(row) : undefined}
			aria-label={several ? `${kpi.name}, ${periodName(row)}` : undefined}
			aria-invalid={!!message}
			aria-describedby={message ? `err-${key}` : undefined}
		/>
		{#if kpi.suffix}<span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-muted">{kpi.suffix}</span>{/if}
	</div>
	{#if message}<p id="err-{key}" class="field-error">{message}</p>{/if}
{/snippet}

<div class="px-5 py-4 sm:px-6">
	<div class="grid gap-x-6 gap-y-2 {several ? '' : 'sm:grid-cols-[minmax(0,1fr)_220px]'}">
		<div class="min-w-0">
			<div class="flex flex-wrap items-center gap-2">
				{#if several}
					<span class="font-semibold">{kpi.name}</span>
				{:else}
					<label for="kpi-{rowKey(rows[0])}" class="font-semibold">{kpi.name}</label>
				{/if}
				{#if several || kpi.frequency !== 'monthly'}<span class="text-sm text-muted">{several ? `${filled} of ${rows.length} ${kpi.frequency === 'weekly' ? 'weeks' : 'days'}` : rows[0].period_label}</span>{/if}
				{#if complete}<Icon name="check" size={14} stroke={3} class="text-good-ink" />{/if}
			</div>
			{#if kpi.description}<p class="text-sm text-ink-2">{kpi.description}</p>{/if}
			{#if !several}<p class="mt-1 text-sm text-muted">{history(rows[0])}</p>{/if}
		</div>
		{#if !several}
			<div>
				{@render field(rows[0], false)}
				{#if canEdit && !showNote}
					<button type="button" class="mt-1.5 flex items-center gap-1 text-xs text-muted hover:text-accent" onclick={() => (noting = true)}>
						<Icon name="note" size={12} /> Add a note
					</button>
				{/if}
			</div>
		{/if}
	</div>
	{#if showNote}
		<div class="mt-3" transition:slide={{ duration: 160 }}>
			<label class="sr-only" for="note-{noteKey}">Note on {kpi.name}</label>
			<div class="flex items-start gap-2">
				<Icon name="note" size={14} class="mt-2.5 shrink-0 text-muted" />
				<!-- svelte-ignore a11y_autofocus -->
				<textarea
					id="note-{noteKey}"
					class="input min-h-0 flex-1 py-1.5 text-sm {(notes[noteKey] ?? '') !== (rows[0].note ?? '') ? 'bg-accent-wash' : ''}"
					rows="2"
					maxlength="500"
					placeholder="Why the figure is what it is, e.g. closed for two days for repairs"
					disabled={!canEdit}
					autofocus={noting && !notes[noteKey]}
					bind:value={notes[noteKey]}
				></textarea>
			</div>
		</div>
	{/if}
	{#if several}
		<div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2.5 sm:grid-cols-4 {kpi.frequency === 'daily' ? 'lg:grid-cols-7' : 'lg:grid-cols-5'}">
			{#each rows as row (row.period_start)}
				<label class="block">
					<span class="mb-1 block text-xs text-ink-2">{periodName(row)}</span>
					{@render field(row, true)}
				</label>
			{/each}
		</div>
	{/if}
</div>
