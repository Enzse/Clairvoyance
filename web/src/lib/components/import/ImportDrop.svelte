<script lang="ts">
	import { templateRows } from '$lib/csv';
	import { downloadCsv } from '$lib/download';
	import { label, today, align } from '$lib/period';
	import { session } from '$lib/session.svelte';
	import { term } from '$lib/terms.svelte';
	import { toast } from '$lib/toast.svelte';
	import type { Kpi } from '$lib/types';
	import { units } from '$lib/units.svelte';
	import FormAlert from '../auth/FormAlert.svelte';
	import Icon from '../Icon.svelte';

	/** Choosing a file, with a ready-made template for anyone starting from scratch. */
	let { kpis, error, busy, onFile }: { kpis: Kpi[]; error: string; busy: boolean; onFile: (file: File | undefined) => void } = $props();

	let dragging = $state(false);
	const month = align(today(), 'monthly');

	function template() {
		const rows = templateRows(
			units.enterable().map((u) => ({ id: u.id, name: u.name, code: u.code, path: units.path(u.id).filter((p) => p.parent_id !== null).map((p) => p.name) })),
			kpis.filter((k) => k.is_active),
			month
		);
		downloadCsv(rows, `${session.user?.organization?.name ?? 'Clairvoyance'} template ${month.slice(0, 7)}.csv`);
		toast(`Downloaded a template with ${rows.length - 1} ${rows.length === 2 ? term.one : term.many}`);
	}
</script>

<label
	class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-14 text-center transition-all
		{dragging ? 'scale-[1.01] border-chart bg-accent-wash' : 'border-line-strong hover:border-chart/60 hover:bg-surface-2'} {busy ? 'pointer-events-none opacity-60' : ''}"
	ondragover={(e) => { e.preventDefault(); dragging = true; }}
	ondragleave={() => (dragging = false)}
	ondrop={(e) => { e.preventDefault(); dragging = false; onFile(e.dataTransfer?.files[0]); }}
>
	<span class="grid size-14 place-items-center rounded-2xl bg-accent-wash text-accent transition-transform {dragging ? '-translate-y-1' : ''}">
		<Icon name="upload" size={26} />
	</span>
	<span class="mt-4 text-md font-semibold">{busy ? 'Reading the file…' : dragging ? 'Drop it here' : 'Drop a spreadsheet here, or choose one'}</span>
	<span class="mt-1 text-sm text-ink-2">Excel (.xlsx) or CSV, up to 5 MB. Only the first sheet is read.</span>
	<input class="sr-only" type="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" onchange={(e) => onFile(e.currentTarget.files?.[0])} />
</label>
<div class="mt-3"><FormAlert message={error} /></div>

<div class="mt-6 flex flex-wrap items-center gap-4 rounded-xl bg-surface-2 p-4 ring-1 ring-line">
	<span class="grid size-10 shrink-0 place-items-center rounded-lg bg-surface text-ink-2 ring-1 ring-line"><Icon name="table" size={18} /></span>
	<div class="min-w-0 flex-1 basis-56">
		<p class="font-medium">Starting from scratch?</p>
		<p class="text-sm text-ink-2">
			Download a template for {label(month, 'monthly')}: one row per {term.one} you report for, one column per KPI. Fill it in and upload it here.
		</p>
	</div>
	<button class="btn btn-secondary" onclick={template} disabled={!kpis.length}><Icon name="download" size={15} /> Download template</button>
</div>
