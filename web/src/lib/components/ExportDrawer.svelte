<script lang="ts">
	import { api, ApiError } from '$lib/api';
	import { exportRows, writeCsv } from '$lib/csv';
	import { plural } from '$lib/format';
	import { shift } from '$lib/period';
	import { session } from '$lib/session.svelte';
	import { toast } from '$lib/toast.svelte';
	import Drawer from './Drawer.svelte';
	import Icon from './Icon.svelte';

	/**
	 * Downloads the figures of a unit and everything inside it as a CSV. The file has the shape
	 * the importer reads, so it can be edited in a spreadsheet and imported back.
	 */
	let { open = $bindable(false), unitId, unitName, date }: { open?: boolean; unitId: number; unitName: string; date: string } = $props();

	const month = (d: string) => d.slice(0, 7);
	let to = $state('');
	let from = $state('');
	let busy = $state(false);
	let error = $state('');

	// Each time it opens: the twelve months up to the period on screen
	$effect(() => {
		if (open) {
			to = month(date);
			from = month(shift(`${month(date)}-01`, 'monthly', -11));
			error = '';
		}
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
			const blob = new Blob([writeCsv(rows)], { type: 'text/csv;charset=utf-8' });
			const link = document.createElement('a');
			link.href = URL.createObjectURL(blob);
			link.download = `${[session.user?.organization?.name, unitName === session.user?.organization?.name ? null : unitName, from, to].filter(Boolean).join(' ')}.csv`;
			link.click();
			URL.revokeObjectURL(link.href);
			toast(`Downloaded ${plural(rows.length - 1, 'row')} with ${plural(data.values.length, 'figure')}`);
			open = false;
		} catch (e) {
			error = e instanceof ApiError ? e.message : 'Could not export';
		} finally {
			busy = false;
		}
	}
</script>

<Drawer bind:open title="Export figures">
	<form onsubmit={download}>
		<p class="text-ink-2">
			A CSV of every figure entered for <strong class="text-ink">{unitName}</strong>{unitName === session.user?.organization?.name ? '' : ' and the units inside it'}:
			one row per unit and period, one column per KPI. Open it in any spreadsheet. You can edit it and import it back.
		</p>
		<div class="mt-6 grid gap-4 sm:grid-cols-2">
			<div>
				<label class="field-label" for="export-from">From</label>
				<input id="export-from" class="input" type="month" required max={to} bind:value={from} />
			</div>
			<div>
				<label class="field-label" for="export-to">To</label>
				<input id="export-to" class="input" type="month" required min={from} bind:value={to} />
			</div>
		</div>
		{#if error}<p class="mt-4 text-sm text-bad-ink" role="alert">{error}</p>{/if}
		<button class="btn btn-primary mt-6" disabled={busy || !from || !to}>
			<Icon name="download" size={15} />
			{busy ? 'Preparing…' : 'Download CSV'}
		</button>
	</form>
</Drawer>
