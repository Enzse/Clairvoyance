<script lang="ts">
	import { slide } from 'svelte/transition';
	import { ApiError } from '$lib/api';
	import { detectDateFormat } from '$lib/csv';
	import { joinNames, plural } from '$lib/format';
	import { DATE_FORMATS, type ImportUpload } from '$lib/import.svelte';
	import { term } from '$lib/terms.svelte';
	import type { Kpi } from '$lib/types';
	import { units } from '$lib/units.svelte';
	import Icon from '../Icon.svelte';

	/**
	 * How the file will be read: which columns name the unit, which holds the date, and which KPI
	 * each other column is. Usually all guessed right, so it starts as a short summary; the
	 * details open with "Change", or by themselves when something still needs choosing.
	 */
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
	const matched = $derived(upload.otherColumns.filter((h) => upload.kpiFor[h]));
	const problems = $derived({
		unit: !upload.unitColumns.length,
		date: !upload.dateColumn || !upload.dateFormat,
		kpis: !matched.length
	});
	const ready = $derived(!problems.unit && !problems.date && !problems.kpis);
	const formatName = (f: string) => f.replace('YYYY', 'year').replace('MM', 'month').replace('DD', 'day');

	let changing = $state(false);
	// Once open (by "Change" or because something needed choosing), it stays open
	$effect(() => {
		if (!ready) changing = true;
	});
	let more = $state(false);
</script>

{#snippet line(ok: boolean, title: string, text: string, hint = '')}
	<li class="flex items-start gap-3 py-2.5">
		<Icon name={ok ? 'check' : 'alert'} size={16} stroke={ok ? 3 : 2} class="mt-0.5 shrink-0 {ok ? 'text-good-ink' : 'text-warn-ink'}" />
		<span class="w-24 shrink-0 text-sm text-ink-2">{title}</span>
		<span class="min-w-0 flex-1 text-sm">
			{text}
			{#if hint}<span class="block text-xs text-warn-ink">{hint}</span>{/if}
		</span>
	</li>
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
	<p class="mt-4 flex flex-wrap items-center gap-2 rounded-lg bg-good-wash px-4 py-2.5 text-sm text-good-ink" transition:slide={{ duration: 160 }}>
		<Icon name="check" size={15} stroke={3} />
		<span class="flex-1">Read the same way as the last file with these columns.</span>
		<button class="font-medium underline-offset-2 hover:underline" onclick={() => upload.suggest()}>Start over</button>
	</p>
{/if}

<!-- How the file will be read -->
<section class="mt-6 rounded-xl ring-1 ring-line" aria-labelledby="reading-heading">
	<div class="flex items-center justify-between gap-3 border-b border-line px-4 py-2.5">
		<h2 id="reading-heading" class="text-sm font-semibold">How the file will be read</h2>
		{#if !changing}<button class="btn btn-ghost btn-sm -mr-2" onclick={() => (changing = true)}>Change</button>{/if}
	</div>
	<ul class="divide-y divide-line px-4">
		{@render line(
			!problems.unit,
			term.One,
			problems.unit
				? `Choose the column that names the ${term.one}`
				: `${upload.unitColumns.join(' › ')}${upload.unitColumns.length > 1 ? ', as a path' : upload.unitMatch === 'code' ? ', matched by code' : ', matched by name'}`
		)}
		{@render line(
			!problems.date,
			'Date',
			!upload.dateColumn
				? 'Choose the column with the dates'
				: !upload.dateFormat
					? `${upload.dateColumn}: choose how dates are written`
					: `${upload.dateColumn}, written ${formatName(upload.dateFormat)} (e.g. ${upload.column(upload.dateColumn)[0] ?? ''})`
		)}
		{@render line(
			!problems.kpis,
			'KPIs',
			matched.length ? joinNames(matched, 4) : 'Match at least one column to a KPI',
			upload.unmatchedFigures.length ? `${joinNames(upload.unmatchedFigures)} ${upload.unmatchedFigures.length === 1 ? "isn't" : "aren't"} matched to a KPI, so ${upload.unmatchedFigures.length === 1 ? 'it' : 'they'} won't be imported` : ''
		)}
	</ul>
</section>

{#if changing}
	<div class="mt-6 space-y-7" transition:slide={{ duration: 200 }}>
		<fieldset>
			<legend class="font-semibold">Which column names the {term.one}?</legend>
			<p class="text-sm text-ink-2">Tick more than one for a path, like Region, then {term.One}.</p>
			<div class="mt-3 flex flex-wrap gap-2">
				{#each upload.nameColumns as h (h)}
					{@const on = upload.unitColumns.includes(h)}
					<button
						type="button"
						class="flex items-center gap-1 rounded-full px-3 py-1 text-sm ring-1 transition-colors {on ? 'bg-accent-wash font-semibold text-ink ring-chart' : 'text-ink-2 ring-line-strong hover:text-ink'}"
						aria-pressed={on}
						onclick={() => upload.toggleUnitColumn(h)}
					>
						{#if on}<span class="num text-chart">{upload.unitColumns.indexOf(h) + 1}</span>{/if}{h}
					</button>
				{:else}
					<p class="text-sm text-warn-ink">No column looks like names. Check the file has a column with the {term.many}' names.</p>
				{/each}
			</div>
			{#if upload.unitColumns.length === 1}
				<label class="mt-4 flex max-w-sm items-center gap-3 text-sm">
					<span class="shrink-0 text-ink-2">Match by</span>
					<select class="input h-9" bind:value={upload.unitMatch}>
						<option value="name">Name</option>
						<option value="code">Code</option>
					</select>
				</label>
			{/if}

			{#if parentChoices.length > 1 || (isAdmin && upload.unitMatch !== 'code')}
				<button type="button" class="mt-4 flex items-center gap-1.5 text-sm font-medium text-accent" onclick={() => (more = !more)} aria-expanded={more || upload.createMissing}>
					<Icon name="chevron-down" size={14} class="transition-transform {more || upload.createMissing ? '' : '-rotate-90'}" /> More options
				</button>
				{#if more || upload.createMissing}
					<div class="mt-3 space-y-4 border-l-2 border-line pl-4" transition:slide={{ duration: 160 }}>
						{#if parentChoices.length > 1}
							<label class="flex max-w-md items-center gap-3 text-sm">
								<span class="shrink-0 text-ink-2">Only look in</span>
								<select class="input h-9" bind:value={upload.parentUnit}>
									{#each parentChoices as u (u.id)}<option value={u.id}>{units.pathLabel(u.id) ? `${units.pathLabel(u.id)} › ` : ''}{u.name}</option>{/each}
								</select>
							</label>
						{/if}
						{#if isAdmin && upload.unitMatch !== 'code'}
							<label class="flex items-start gap-2.5">
								<input type="checkbox" class="mt-0.5 size-4 accent-[var(--accent)]" bind:checked={upload.createMissing} />
								<span>
									<span class="text-sm font-medium">Add {term.many} that don't exist yet</span>
									<span class="block text-sm text-ink-2">Otherwise rows with names the app doesn't know are listed as problems.</span>
								</span>
							</label>
							{#if upload.createMissing}
								<div class="grid gap-3 pl-6 sm:grid-cols-2">
									{#each upload.unitColumns as c (c)}
										<label class="text-sm"><span class="field-label">New names in {c} are a</span><input class="input" bind:value={upload.unitKinds[c]} /></label>
									{/each}
								</div>
							{/if}
						{/if}
					</div>
				{/if}
			{/if}
		</fieldset>

		<fieldset>
			<legend class="font-semibold">When is each row for?</legend>
			<p class="text-sm text-ink-2">A date anywhere in a period counts for that period: 30 June counts for June.</p>
			<div class="mt-3 grid max-w-xl gap-3 sm:grid-cols-2">
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
						{#each DATE_FORMATS as f (f)}<option value={f}>{formatName(f)}</option>{/each}
					</select>
				</label>
			</div>
			{#if upload.dateColumn && !upload.dateFormat}
				<p class="mt-2 text-sm text-warn-ink">Couldn't tell whether the day or the month comes first in {upload.column(upload.dateColumn).slice(0, 2).join(', ')}.</p>
			{/if}
		</fieldset>

		<section>
			<h3 class="font-semibold">Which KPI is in each column?</h3>
			<p class="text-sm text-ink-2">Columns left as "Don't import" are skipped.</p>
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
									<select class="input h-8 text-sm" bind:value={upload.kpiFor[h]} aria-label="KPI for {h}">
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
	</div>
{/if}

{#if serverError}
	<div class="mt-6 rounded-lg bg-bad-wash p-4 text-sm" role="alert" transition:slide={{ duration: 160 }}>
		<p class="font-semibold text-bad-ink">{serverError.message}</p>
		{#each Object.entries(serverError.details) as [field, message] (field)}<p class="mt-1">{field}: {message}</p>{/each}
	</div>
{/if}

<div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-line pt-5">
	<p class="mr-auto text-sm text-ink-2">{ready ? 'Nothing is saved until the next step.' : 'Finish the choices above first.'}</p>
	<button class="btn btn-primary" disabled={!ready || busy} onclick={onCheck}>{busy ? 'Checking…' : 'Check the file'} <Icon name="arrow-right" size={15} /></button>
</div>
