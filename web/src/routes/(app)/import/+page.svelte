<script lang="ts">
	import { onMount } from 'svelte';
	import { api, ApiError } from '$lib/api';
	import Icon from '$lib/components/Icon.svelte';
	import { detectDateFormat, parseCsv, profileColumn, suggestKpi, suggestUnitColumns } from '$lib/csv';
	import { plural } from '$lib/format';
	import { href } from '$lib/nav';
	import { session } from '$lib/session.svelte';
	import type { DateFormat, ImportMapping, ImportPreview, ImportResult, Kpi } from '$lib/types';
	import { units } from '$lib/units.svelte';

	const STEPS = ['Choose a file', 'Match columns', 'Check', 'Import'];
	const FORMATS: DateFormat[] = ['YYYY-MM-DD', 'DD/MM/YYYY', 'MM/DD/YYYY', 'YYYY-MM'];
	const isAdmin = $derived(session.user?.role === 'admin');

	let step = $state(0);
	let kpis = $state<Kpi[]>([]);
	let file = $state<File | null>(null);
	let headers = $state<string[]>([]);
	let sample = $state<string[][]>([]);
	let fileError = $state('');
	let dragging = $state(false);

	// Mapping
	let unitColumns = $state<string[]>([]);
	let unitMatch = $state<ImportMapping['unit_match']>('path');
	let unitKinds = $state<Record<string, string>>({});
	let parentUnit = $state<number | null>(null);
	let createMissing = $state(false);
	let dateColumn = $state('');
	let dateFormat = $state<DateFormat | ''>('');
	let kpiFor = $state<Record<string, string>>({}); // header -> kpi code or ''

	let preview = $state<ImportPreview | null>(null);
	let result = $state<ImportResult | null>(null);
	let skipInvalid = $state(false);
	let busy = $state(false);
	let serverError = $state<ApiError | null>(null);

	onMount(async () => {
		kpis = await api.kpis();
		parentUnit = units.home()?.id ?? null;
	});

	async function pick(chosen: File | undefined) {
		fileError = '';
		if (!chosen) return;
		if (chosen.size > 5 * 1024 * 1024) {
			fileError = 'That file is over 5 MB. Split it into smaller files.';
			return;
		}
		const parsed = parseCsv(await chosen.text());
		if (!parsed.headers.length) {
			fileError = 'That file is empty.';
			return;
		}
		file = chosen;
		headers = parsed.headers;
		sample = parsed.rows;
		suggest();
		step = 1;
	}

	const column = (h: string) => sample.map((r) => r[headers.indexOf(h)] ?? '');

	function suggest() {
		const profiles = headers.map((h) => profileColumn(column(h)));
		unitColumns = suggestUnitColumns(headers, profiles);
		unitMatch = unitColumns.length > 1 ? 'path' : 'name';
		unitKinds = Object.fromEntries(unitColumns.map((c) => [c, c.replace(/_name$/i, '').replace(/_/g, ' ')]));
		const dates = headers.filter((h, i) => profiles[i].dateLike);
		dateColumn = dates.find((h) => /date/i.test(h)) ?? dates[0] ?? '';
		dateFormat = dateColumn ? (detectDateFormat(column(dateColumn)) ?? '') : '';
		kpiFor = Object.fromEntries(headers.map((h) => [h, suggestKpi(h, kpis)?.code ?? '']));
	}

	function toggleUnitColumn(h: string) {
		unitColumns = unitColumns.includes(h) ? unitColumns.filter((c) => c !== h) : headers.filter((c) => c === h || unitColumns.includes(c));
		unitKinds[h] ??= h.replace(/_name$/i, '').replace(/_/g, ' ');
		if (unitColumns.length > 1) unitMatch = 'path';
	}

	const otherColumns = $derived(headers.filter((h) => !unitColumns.includes(h) && h !== dateColumn));
	const mappedCount = $derived(otherColumns.filter((h) => kpiFor[h]).length);
	const problems = $derived(
		[
			!unitColumns.length && 'Choose the column (or columns) that name the unit.',
			!dateColumn && 'Choose the date column.',
			dateColumn && !dateFormat && 'Choose how dates are written.',
			!mappedCount && 'Match at least one column to a KPI.'
		].filter(Boolean) as string[]
	);

	function mapping(): ImportMapping {
		return {
			unit_columns: unitColumns,
			unit_match: unitColumns.length > 1 ? 'path' : unitMatch,
			unit_kinds: unitColumns.map((c) => unitKinds[c] ?? c),
			parent_unit_id: parentUnit ?? undefined,
			create_missing_units: isAdmin && createMissing,
			date_column: dateColumn,
			date_format: dateFormat as DateFormat,
			columns: Object.fromEntries(otherColumns.filter((h) => kpiFor[h]).map((h) => [h, kpiFor[h]]))
		};
	}

	async function check() {
		if (!file || problems.length) return;
		busy = true;
		serverError = null;
		try {
			preview = await api.previewImport(file, mapping());
			skipInvalid = false;
			step = 2;
		} catch (e) {
			serverError = e instanceof ApiError ? e : new ApiError(0, 'Could not check the file');
		} finally {
			busy = false;
		}
	}

	async function commit() {
		if (!file || !preview) return;
		busy = true;
		serverError = null;
		try {
			result = await api.commitImport(file, mapping(), skipInvalid);
			if (result.units_created) await units.load(true);
			step = 3;
		} catch (e) {
			serverError = e instanceof ApiError ? e : new ApiError(0, 'The import failed');
		} finally {
			busy = false;
		}
	}

	function restart() {
		step = 0;
		file = null;
		preview = null;
		result = null;
		serverError = null;
	}

	const parentChoices = $derived(units.list.filter((u) => !u.is_leaf && u.is_active && (u.access === 'admin' || u.access === 'contributor')));
</script>

<svelte:head><title>Import | Clairvoyance</title></svelte:head>

<section class="bg-navy text-navy-ink">
	<div class="mx-auto max-w-[1240px] px-4 pt-5 pb-9 sm:px-6">
		<div class="border-t border-navy-2 pt-6">
			<h1 class="text-2xl font-semibold tracking-[-0.025em]">Import from a spreadsheet</h1>
			<p class="mt-2 max-w-[68ch] text-md text-navy-ink-2">
				One row per unit and date, one column per KPI, saved as CSV. Importing the same file again updates figures instead of
				duplicating them.
			</p>
		</div>
		<ol class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm">
			{#each STEPS as name, i (name)}
				<li class="flex items-center gap-2 {i === step ? 'text-navy-ink' : 'text-navy-ink-2'}" aria-current={i === step ? 'step' : undefined}>
					<span class="grid size-6 place-items-center rounded-full text-xs font-semibold {i < step ? 'bg-navy-2 text-navy-ink' : i === step ? 'bg-surface text-ink' : 'ring-1 ring-navy-2'}">
						{#if i < step}<Icon name="check" size={13} stroke={3} />{:else}{i + 1}{/if}
					</span>
					<span class={i === step ? 'font-semibold' : ''}>{name}</span>
				</li>
			{/each}
		</ol>
	</div>
</section>

<main class="mx-auto max-w-[1240px] px-4 pb-16 sm:px-6"><div class="max-w-[1040px]">
	<div class="panel -mt-5 p-5 sm:p-7">
		{#if step === 0}
			<label
				class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-14 text-center transition-colors {dragging ? 'border-chart bg-accent-wash' : 'border-line-strong hover:bg-surface-2'}"
				ondragover={(e) => { e.preventDefault(); dragging = true; }}
				ondragleave={() => (dragging = false)}
				ondrop={(e) => { e.preventDefault(); dragging = false; pick(e.dataTransfer?.files[0]); }}
			>
				<Icon name="upload" size={28} class="text-muted" />
				<span class="mt-3 text-md font-semibold">Drop a CSV file here, or choose one</span>
				<span class="mt-1 text-sm text-ink-2">Up to 5 MB. In Excel or Google Sheets: File, Save as / Download, CSV.</span>
				<input class="sr-only" type="file" accept=".csv,text/csv" onchange={(e) => pick(e.currentTarget.files?.[0])} />
			</label>
			{#if fileError}<p class="mt-3 text-sm text-bad-ink" role="alert">{fileError}</p>{/if}

		{:else if step === 1}
			<div class="flex flex-wrap items-baseline justify-between gap-2">
				<p><Icon name="file" size={16} class="mr-1.5 inline text-muted" /><span class="font-semibold">{file?.name}</span>
					<span class="text-ink-2">{plural(headers.length, 'column')}{sample.length >= 500 ? ', first 500 rows previewed' : `, ${plural(sample.length, 'row')}`}</span></p>
				<button class="btn btn-ghost btn-sm" onclick={restart}>Choose another file</button>
			</div>

			<div class="mt-7 grid gap-8 lg:grid-cols-2">
				<fieldset>
					<legend class="text-md font-semibold">Which unit is each row for?</legend>
					<p class="mt-1 text-sm text-ink-2">Tick the columns that name the unit. Several columns make a path, like Region, then Division, then School.</p>
					<div class="mt-3 flex flex-wrap gap-2">
						{#each headers.filter((h) => h !== dateColumn) as h (h)}
							{@const on = unitColumns.includes(h)}
							<button
								type="button"
								class="rounded-full px-3 py-1 text-sm ring-1 {on ? 'bg-accent-wash font-semibold text-ink ring-chart' : 'text-ink-2 ring-line-strong hover:text-ink'}"
								aria-pressed={on}
								onclick={() => toggleUnitColumn(h)}
							>
								{#if on}<span class="num mr-1 text-chart">{unitColumns.indexOf(h) + 1}</span>{/if}{h}
							</button>
						{/each}
					</div>
					{#if unitColumns.length === 1}
						<label class="mt-4 block max-w-xs">
							<span class="field-label">Match units by</span>
							<select class="input" bind:value={unitMatch}>
								<option value="name">Name</option>
								<option value="code">Unit code</option>
							</select>
						</label>
					{/if}
					{#if parentChoices.length > 1}
						<label class="mt-4 block max-w-xs">
							<span class="field-label">Units are under</span>
							<select class="input" bind:value={parentUnit}>
								{#each parentChoices as u (u.id)}<option value={u.id}>{units.pathLabel(u.id) ? `${units.pathLabel(u.id)} › ` : ''}{u.name}</option>{/each}
							</select>
						</label>
					{/if}
					{#if isAdmin && unitMatch !== 'code'}
						<label class="mt-4 flex items-start gap-2.5">
							<input type="checkbox" class="mt-0.5 size-4 accent-[var(--accent)]" bind:checked={createMissing} />
							<span>
								<span class="font-medium">Add units that don't exist yet</span>
								<span class="block text-sm text-ink-2">Otherwise rows for unknown units are reported as problems.</span>
							</span>
						</label>
						{#if createMissing}
							<div class="mt-3 grid gap-3 pl-6 sm:grid-cols-2">
								{#each unitColumns as c (c)}
									<label><span class="field-label">New units from {c} are a</span><input class="input" bind:value={unitKinds[c]} /></label>
								{/each}
							</div>
						{/if}
					{/if}
				</fieldset>

				<fieldset>
					<legend class="text-md font-semibold">When is each row for?</legend>
					<p class="mt-1 text-sm text-ink-2">Dates are moved to the start of each KPI's period, so 30 June counts for June.</p>
					<div class="mt-3 grid gap-3 sm:grid-cols-2">
						<label>
							<span class="field-label">Date column</span>
							<select class="input" bind:value={dateColumn} onchange={() => (dateFormat = detectDateFormat(column(dateColumn)) ?? '')}>
								<option value="">Choose…</option>
								{#each headers.filter((h) => !unitColumns.includes(h)) as h (h)}<option value={h}>{h}</option>{/each}
							</select>
						</label>
						<label>
							<span class="field-label">Written as</span>
							<select class="input" bind:value={dateFormat} aria-invalid={!!dateColumn && !dateFormat}>
								<option value="">Choose…</option>
								{#each FORMATS as f (f)}<option value={f}>{f}</option>{/each}
							</select>
						</label>
					</div>
					{#if dateColumn}
						<p class="mt-2 text-sm {dateFormat ? 'text-muted' : 'text-warn-ink'}">
							{dateFormat ? 'For example' : "Couldn't tell whether the day or the month comes first in"} {column(dateColumn).slice(0, 2).join(', ')}
						</p>
					{/if}
				</fieldset>
			</div>

			<div class="mt-9">
				<h2 class="text-md font-semibold">Which KPI is in each column?</h2>
				<p class="mt-1 text-sm text-ink-2">Columns left as "Don't import" are ignored.</p>
				<div class="mt-3 overflow-x-auto rounded-lg ring-1 ring-line">
					<table class="w-full text-left">
						<thead class="bg-surface-2 text-sm text-ink-2">
							<tr><th class="px-4 py-2 font-medium">Column</th><th class="px-4 py-2 font-medium">Sample values</th><th class="px-4 py-2 font-medium">KPI</th></tr>
						</thead>
						<tbody>
							{#each otherColumns as h (h)}
								<tr class="border-t border-line">
									<th scope="row" class="px-4 py-2 font-medium">{h}</th>
									<td class="max-w-64 truncate px-4 py-2 text-sm text-ink-2">{column(h).slice(0, 3).join(', ')}</td>
									<td class="w-64 px-4 py-2">
										<select class="input h-8 text-sm" bind:value={kpiFor[h]} aria-label="KPI for {h}">
											<option value="">Don't import</option>
											{#each kpis as k (k.id)}<option value={k.code}>{k.name}</option>{/each}
										</select>
									</td>
								</tr>
							{/each}
						</tbody>
					</table>
				</div>
			</div>

			{#if serverError}
				<div class="mt-6 rounded-lg bg-bad-wash p-4 text-sm" role="alert">
					<p class="font-semibold text-bad-ink">{serverError.message}</p>
					{#each Object.entries(serverError.details) as [field, message] (field)}<p class="mt-1">{field}: {message}</p>{/each}
				</div>
			{/if}

			<div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-line pt-5">
				{#if problems.length}<p class="mr-auto text-sm text-ink-2">{problems[0]}</p>{/if}
				<button class="btn btn-primary" disabled={problems.length > 0 || busy} onclick={check}>{busy ? 'Checking…' : 'Check the file'}</button>
			</div>

		{:else if step === 2 && preview}
			{@const blocked = preview.error_count > 0 && !skipInvalid}
			{@const changes = preview.will_create + preview.will_update}
			<h2 class="text-lg font-semibold">
				{preview.error_count
					? `${plural(preview.error_count, 'problem')} found`
					: preview.will_create + preview.will_update + preview.new_units.length === 0
						? 'Everything in this file is already saved'
						: 'Ready to import'}
			</h2>
			<dl class="mt-5 grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-line ring-1 ring-line sm:grid-cols-4">
				{#each [['New figures', preview.will_create], ['Changed figures', preview.will_update], ['Unchanged', preview.unchanged], ['New units', preview.new_units.length]] as [name, count] (name)}
					<div class="bg-surface px-4 py-3">
						<dt class="text-sm text-ink-2">{name}</dt>
						<dd class="mt-0.5 text-xl font-semibold">{Number(count).toLocaleString('en-US')}</dd>
					</div>
				{/each}
			</dl>
			{#if preview.new_units.length}
				<details class="mt-4 text-sm">
					<summary class="cursor-pointer text-ink-2">Units that will be added</summary>
					<ul class="mt-2 space-y-0.5 pl-5">{#each preview.new_units as u (u)}<li class="list-disc">{u}</li>{/each}</ul>
				</details>
			{/if}

			{#if preview.errors.length}
				<h3 class="mt-7 font-semibold">Problems</h3>
				<p class="text-sm text-ink-2">Fix these in the file and choose it again, or import everything else and skip them.</p>
				<div class="mt-3 max-h-80 overflow-auto rounded-lg ring-1 ring-line">
					<table class="w-full text-left text-sm">
						<thead class="sticky top-0 bg-surface-2 text-ink-2"><tr><th class="px-4 py-2 font-medium">Row</th><th class="px-4 py-2 font-medium">Column</th><th class="px-4 py-2 font-medium">Problem</th></tr></thead>
						<tbody>
							{#each preview.errors as issue, i (i)}
								<tr class="border-t border-line"><td class="num px-4 py-2">{issue.row}</td><td class="px-4 py-2 text-ink-2">{issue.column ?? ''}</td><td class="px-4 py-2">{issue.message}</td></tr>
							{/each}
						</tbody>
					</table>
				</div>
				{#if preview.error_count > preview.errors.length}<p class="mt-2 text-sm text-ink-2">Showing the first {preview.errors.length}.</p>{/if}
				<label class="mt-4 flex items-center gap-2.5"><input type="checkbox" class="size-4 accent-[var(--accent)]" bind:checked={skipInvalid} /> Import the rest and skip these problems</label>
			{/if}

			{#if serverError}<p class="mt-4 text-sm text-bad-ink" role="alert">{serverError.message}</p>{/if}

			<div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-line pt-5">
				<button class="btn btn-secondary mr-auto" onclick={() => (step = 1)}>Back to columns</button>
				<button class="btn btn-primary" disabled={blocked || busy || changes + preview.new_units.length === 0} onclick={commit}>
					{busy ? 'Importing…' : changes || preview.new_units.length ? `Import ${plural(changes, 'figure')}` : 'Nothing to import'}
				</button>
			</div>

		{:else if step === 3 && result}
			<div class="flex items-start gap-4">
				<span class="grid size-10 shrink-0 place-items-center rounded-full bg-good-wash text-good-ink"><Icon name="check" size={20} stroke={2.5} /></span>
				<div>
					<h2 class="text-lg font-semibold">Imported {file?.name}</h2>
					<p class="mt-1 text-ink-2">
						{plural(result.values_created, 'new figure')}, {plural(result.values_updated, 'changed figure')}, {result.unchanged.toLocaleString('en-US')} unchanged{result.units_created ? `, ${plural(result.units_created, 'new unit')}` : ''}{result.skipped_errors ? `. Skipped ${plural(result.skipped_errors, 'problem')}` : ''}.
					</p>
					<div class="mt-5 flex gap-3">
						<a class="btn btn-primary" href={href('/', {})}>Open the scorecard</a>
						<button class="btn btn-secondary" onclick={restart}>Import another file</button>
					</div>
				</div>
			</div>
		{/if}
	</div>
</div></main>
