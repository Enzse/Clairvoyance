<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { scale } from 'svelte/transition';
	import { api, ApiError } from '$lib/api';
	import { exportRows } from '$lib/csv';
	import { downloadCsv } from '$lib/download';
	import { plural } from '$lib/format';
	import { shift } from '$lib/period';
	import { session } from '$lib/session.svelte';
	import { term } from '$lib/terms.svelte';
	import { toast } from '$lib/toast.svelte';
	import FormAlert from './auth/FormAlert.svelte';
	import Icon from './Icon.svelte';

	/**
	 * Downloads the figures of a unit and everything inside it as a CSV. The file has the shape
	 * the importer reads, so it can be edited in a spreadsheet and imported back.
	 */
	let { open = $bindable(false), unitId, unitName, date }: { open?: boolean; unitId: number; unitName: string; date: string } = $props();

	let dialog: HTMLDialogElement;
	const month = (d: string) => d.slice(0, 7);
	const back = (months: number) => month(shift(`${month(date)}-01`, 'monthly', -months));
	const RANGES = $derived([
		{ label: 'Last 3 months', from: back(2) },
		{ label: 'Last 12 months', from: back(11) },
		{ label: 'This year', from: `${date.slice(0, 4)}-01` }
	]);
	let to = $state('');
	let from = $state('');
	let busy = $state(false);
	let error = $state('');
	const isOrg = $derived(unitName === session.user?.organization?.name);
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	// Each time it opens: the twelve months up to the period on screen
	$effect(() => {
		if (open && !dialog.open) {
			to = month(date);
			from = back(11);
			error = '';
			dialog.showModal();
		}
		if (!open && dialog.open) dialog.close();
	});

	function lastDay(yyyyMm: string): string {
		const [y, m] = yyyyMm.split('-').map(Number);
		return `${yyyyMm}-${String(new Date(Date.UTC(y, m, 0)).getUTCDate()).padStart(2, '0')}`;
	}

	async function download(event: SubmitEvent) {
		event.preventDefault();
		busy = true;
		error = '';
		try {
			const data = await api.exportValues(unitId, `${from}-01`, lastDay(to));
			if (!data.values.length) {
				error = `There are no figures for ${unitName} between those months.`;
				return;
			}
			const rows = exportRows(data);
			downloadCsv(rows, `${[session.user?.organization?.name, isOrg ? null : unitName, from, to].filter(Boolean).join(' ')}.csv`);
			toast(`Downloaded ${plural(rows.length - 1, 'row')} with ${plural(data.values.length, 'figure')}`);
			open = false;
		} catch (e) {
			error = e instanceof ApiError ? e.message : 'Could not export';
		} finally {
			busy = false;
		}
	}
</script>

<dialog bind:this={dialog} class="m-auto w-[min(520px,calc(100vw-32px))] overflow-hidden rounded-2xl bg-surface p-0 text-ink shadow-pop" aria-label="Export figures" onclose={() => (open = false)}>
	{#if open}
		<form class="p-6" onsubmit={download} in:scale={{ start: 0.97, duration: 220 * move, easing: cubicOut }}>
			<div class="flex items-start gap-4">
				<span class="grid size-11 shrink-0 place-items-center rounded-xl bg-accent-wash text-accent"><Icon name="download" size={20} /></span>
				<div class="min-w-0 flex-1">
					<h2 class="text-xl font-semibold tracking-[-0.015em]">Export figures</h2>
					<p class="mt-1 text-ink-2">
						Every figure for <strong class="font-medium text-ink">{unitName}</strong>{isOrg ? '' : ' and everything inside it'}, one row per {term.one} and
						period. Edit it in any spreadsheet and import it back.
					</p>
				</div>
				<button type="button" class="rounded-md p-1.5 text-muted hover:bg-surface-2 hover:text-ink" onclick={() => (open = false)} aria-label="Close"><Icon name="x" size={18} /></button>
			</div>

			<div class="mt-6 flex flex-wrap gap-2" role="group" aria-label="Quick ranges">
				{#each RANGES as range (range.label)}
					{@const on = from === range.from && to === month(date)}
					<button
						type="button"
						class="rounded-full px-3 py-1 text-sm ring-1 transition-colors {on ? 'bg-accent-wash font-medium text-accent ring-chart' : 'ring-line-strong hover:bg-surface-2'}"
						aria-pressed={on}
						onclick={() => { from = range.from; to = month(date); }}
					>
						{range.label}
					</button>
				{/each}
			</div>
			<div class="mt-4 grid gap-4 sm:grid-cols-2">
				<div>
					<label class="field-label" for="export-from">From</label>
					<input id="export-from" class="input" type="month" required max={to} bind:value={from} />
				</div>
				<div>
					<label class="field-label" for="export-to">To</label>
					<input id="export-to" class="input" type="month" required min={from} bind:value={to} />
				</div>
			</div>
			<div class="mt-4"><FormAlert message={error} /></div>
			<div class="mt-6 flex justify-end gap-2">
				<button type="button" class="btn btn-ghost" onclick={() => (open = false)}>Cancel</button>
				<button class="btn btn-primary" disabled={busy || !from || !to}><Icon name="download" size={15} /> {busy ? 'Preparing…' : 'Download CSV'}</button>
			</div>
		</form>
	{/if}
</dialog>
