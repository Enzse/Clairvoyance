<script lang="ts">
	import { slide } from 'svelte/transition';
	import { ApiError } from '$lib/api';
	import { detectDateFormat } from '$lib/csv';
	import { plural } from '$lib/format';
	import { DATE_FORMATS, type ImportUpload } from '$lib/import.svelte';
	import { term } from '$lib/terms.svelte';
	import type { Kpi } from '$lib/types';
	import { units } from '$lib/units.svelte';
	import Icon, { type IconName } from '../Icon.svelte';

	/** Which columns hold the unit, the date and each KPI, guessed and then checked by the person. */
	let {
		upload,
		kpis,
		isAdmin,
		busy,
		serverError,
		onCheck,
		onRestart
	}: { upload: ImportUpload; kpis: Kpi[]; isAdmin: boolean; busy: boolean; serverError: ApiError | null; onCheck: () => void; onRestart: () => void } = $props();

	const parentChoices = $derived(units.list.filter((u) => !u.is_leaf && u.is_active && (u.access === 'admin' || u.access === 'contributor')));
	const mapped = $derived(upload.otherColumns.filter((h) => upload.kpiFor[h]).length);
	const problems = $derived(
		[
			!upload.unitColumns.length && `Choose the column (or columns) that name the ${term.one}.`,
			!upload.dateColumn && 'Choose the date column.',
			upload.dateColumn && !upload.dateFormat && 'Choose how dates are written.',
			!mapped && 'Match at least one column to a KPI.'
		].filter(Boolean) as string[]
	);
</script>

{#snippet heading(icon: IconName, title: string, lead: string)}
	<div class="flex items-start gap-3">
		<span class="grid size-9 shrink-0 place-items-center rounded-lg bg-accent-wash text-accent"><Icon name={icon} size={17} /></span>
		<div>
			<h2 class="font-semibold">{title}</h2>
			<p class="text-sm text-ink-2">{lead}</p>
		</div>
	</div>
{/snippet}

<div class="flex flex-wrap items-center gap-3">
	<span class="grid size-10 place-items-center rounded-lg bg-surface-2 text-ink-2 ring-1 ring-line"><Icon name="file" size={18} /></span>
	<p class="min-w-0 flex-1">
		<span class="block truncate font-semibold">{upload.file?.name}</span>
		<span class="text-sm text-ink-2">{plural(upload.headers.length, 'column')}{upload.sample.length >= 500 ? ', first 500 rows previewed' : `, ${plural(upload.sample.length, 'row')}`}</span>
	</p>
	<button class="btn btn-ghost btn-sm" onclick={onRestart}>Choose another file</button>
</div>

{#if upload.remembered}
	<div class="mt-4 flex flex-wrap items-center gap-3 rounded-lg bg-good-wash px-4 py-2.5 text-sm" transition:slide={{ duration: 160 }}>
		<Icon name="check" size={15} stroke={3} class="text-good-ink" />
		<span class="flex-1 text-good-ink">Matched the same way as the last file with these columns.</span>
		<button class="font-medium text-good-ink underline-offset-2 hover:underline" onclick={() => upload.suggest()}>Match again</button>
	</div>
{/if}

<div class="mt-7 grid gap-8 lg:grid-cols-2">
	<fieldset>
		<legend class="contents">{@render heading('pin', `Which ${term.one} is each row for?`, 'Tick the columns that name it. Several make a path, like Region, then Branch.')}</legend>
		<div class="mt-3 flex flex-wrap gap-2 pl-12">
			{#each upload.headers.filter((h) => h !== upload.dateColumn) as h (h)}
				{@const on = upload.unitColumns.includes(h)}
				<button
					type="button"
					class="flex items-center gap-1 rounded-full px-3 py-1 text-sm ring-1 transition-colors {on ? 'bg-accent-wash font-semibold text-ink ring-chart' : 'text-ink-2 ring-line-strong hover:text-ink'}"
					aria-pressed={on}
					onclick={() => upload.toggleUnitColumn(h)}
				>
					{#if on}<span class="num text-chart">{upload.unitColumns.indexOf(h) + 1}</span>{/if}{h}
				</button>
			{/each}
		</div>
		<div class="space-y-4 pl-12">
			{#if upload.unitColumns.length === 1}
				<label class="mt-4 block max-w-xs">
					<span class="field-label">Match {term.many} by</span>
					<select class="input" bind:value={upload.unitMatch}>
						<option value="name">Name</option>
						<option value="code">Code</option>
					</select>
				</label>
			{/if}
			{#if parentChoices.length > 1}
				<label class="mt-4 block max-w-xs">
					<span class="field-label">They are all in</span>
					<select class="input" bind:value={upload.parentUnit}>
						{#each parentChoices as u (u.id)}<option value={u.id}>{units.pathLabel(u.id) ? `${units.pathLabel(u.id)} › ` : ''}{u.name}</option>{/each}
					</select>
				</label>
			{/if}
			{#if isAdmin && upload.unitMatch !== 'code'}
				<label class="mt-4 flex items-start gap-2.5">
					<input type="checkbox" class="mt-0.5 size-4 accent-[var(--accent)]" bind:checked={upload.createMissing} />
					<span>
						<span class="font-medium">Add {term.many} that don't exist yet</span>
						<span class="block text-sm text-ink-2">Otherwise rows for names the app doesn't know are listed as problems.</span>
					</span>
				</label>
				{#if upload.createMissing}
					<div class="grid gap-3 pl-6 sm:grid-cols-2" transition:slide={{ duration: 160 }}>
						{#each upload.unitColumns as c (c)}
							<label><span class="field-label">New names in {c} are a</span><input class="input" bind:value={upload.unitKinds[c]} /></label>
						{/each}
					</div>
				{/if}
			{/if}
		</div>
	</fieldset>

	<fieldset>
		<legend class="contents">{@render heading('calendar', 'When is each row for?', "Dates move to the start of each KPI's period, so 30 June counts for June.")}</legend>
		<div class="mt-3 grid gap-3 pl-12 sm:grid-cols-2">
			<label>
				<span class="field-label">Date column</span>
				<select class="input" bind:value={upload.dateColumn} onchange={() => (upload.dateFormat = detectDateFormat(upload.column(upload.dateColumn)) ?? '')}>
					<option value="">Choose…</option>
					{#each upload.headers.filter((h) => !upload.unitColumns.includes(h)) as h (h)}<option value={h}>{h}</option>{/each}
				</select>
			</label>
			<label>
				<span class="field-label">Written as</span>
				<select class="input" bind:value={upload.dateFormat} aria-invalid={!!upload.dateColumn && !upload.dateFormat}>
					<option value="">Choose…</option>
					{#each DATE_FORMATS as f (f)}<option value={f}>{f.replace('YYYY', 'year').replace('MM', 'month').replace('DD', 'day')}</option>{/each}
				</select>
			</label>
		</div>
		{#if upload.dateColumn}
			<p class="mt-2 pl-12 text-sm {upload.dateFormat ? 'text-muted' : 'text-warn-ink'}">
				{upload.dateFormat ? 'For example' : "Couldn't tell whether the day or the month comes first in"} {upload.column(upload.dateColumn).slice(0, 2).join(', ')}
			</p>
		{/if}
	</fieldset>
</div>

<section class="mt-9">
	{@render heading('chart', 'Which KPI is in each column?', 'Columns left as "Don\'t import" are ignored.')}
	<div class="mt-3 overflow-x-auto rounded-xl ring-1 ring-line">
		<table class="w-full text-left">
			<thead class="bg-surface-2 text-sm text-ink-2">
				<tr><th class="px-4 py-2 font-medium">Column</th><th class="px-4 py-2 font-medium">Sample values</th><th class="px-4 py-2 font-medium">KPI</th></tr>
			</thead>
			<tbody>
				{#each upload.otherColumns as h (h)}
					<tr class="border-t border-line {upload.kpiFor[h] ? '' : 'text-ink-2'}">
						<th scope="row" class="px-4 py-2 font-medium">{h}</th>
						<td class="num max-w-64 truncate px-4 py-2 text-sm text-ink-2">{upload.column(h).slice(0, 3).join(', ')}</td>
						<td class="w-64 px-4 py-2">
							<select class="input h-8 text-sm {upload.kpiFor[h] ? 'ring-chart/50' : ''}" bind:value={upload.kpiFor[h]} aria-label="KPI for {h}">
								<option value="">Don't import</option>
								{#each kpis as k (k.id)}<option value={k.code}>{k.name}</option>{/each}
							</select>
						</td>
					</tr>
				{/each}
			</tbody>
		</table>
	</div>
</section>

{#if serverError}
	<div class="mt-6 rounded-lg bg-bad-wash p-4 text-sm" role="alert" transition:slide={{ duration: 160 }}>
		<p class="font-semibold text-bad-ink">{serverError.message}</p>
		{#each Object.entries(serverError.details) as [field, message] (field)}<p class="mt-1">{field}: {message}</p>{/each}
	</div>
{/if}

<div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-line pt-5">
	{#if problems.length}<p class="mr-auto text-sm text-ink-2">{problems[0]}</p>{:else}<p class="mr-auto text-sm text-ink-2">{plural(mapped, 'KPI column')} ready to check.</p>{/if}
	<button class="btn btn-primary" disabled={problems.length > 0 || busy} onclick={onCheck}>{busy ? 'Checking…' : 'Check the file'} <Icon name="arrow-right" size={15} /></button>
</div>
