<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly, slide } from 'svelte/transition';
	import { ApiError } from '$lib/api';
	import { plural } from '$lib/format';
	import { describeRows, groupIssues } from '$lib/issues';
	import { term } from '$lib/terms.svelte';
	import type { ImportPreview } from '$lib/types';
	import FormAlert from '../auth/FormAlert.svelte';
	import Icon from '../Icon.svelte';

	/** What the import will do, and any problems in the file, before anything is saved. */
	let {
		preview,
		skipInvalid = $bindable(),
		busy,
		serverError,
		onBack,
		onImport,
		onAddMissing
	}: {
		preview: ImportPreview;
		skipInvalid: boolean;
		busy: boolean;
		serverError: ApiError | null;
		onBack: () => void;
		onImport: () => void;
		/** Given when the person may add the units the file names but that don't exist yet */
		onAddMissing?: () => void;
	} = $props();

	const groups = $derived(groupIssues(preview.errors));

	const move = $derived(prefersReducedMotion.current ? 0 : 1);
	const changes = $derived(preview.will_create + preview.will_update);
	const nothing = $derived(changes + preview.new_units.length === 0);
	const blocked = $derived(preview.error_count > 0 && !skipInvalid);
	const tiles = $derived([
		{ name: 'New figures', count: preview.will_create, tone: 'text-good-ink' },
		{ name: 'Changed figures', count: preview.will_update, tone: 'text-accent' },
		{ name: 'Already saved', count: preview.unchanged, tone: 'text-ink-2' },
		{ name: `New ${term.many}`, count: preview.new_units.length, tone: 'text-ink' }
	]);
</script>

<div class="flex items-start gap-4">
	<span class="grid size-11 shrink-0 place-items-center rounded-xl {preview.error_count ? 'bg-warn-wash text-warn-ink' : 'bg-good-wash text-good-ink'}">
		<Icon name={preview.error_count ? 'alert' : 'check'} size={20} stroke={preview.error_count ? 2 : 2.5} />
	</span>
	<div>
		<h2 class="text-lg font-semibold">
			{preview.error_count ? `${plural(preview.error_count, 'problem')} found` : nothing ? 'Everything in this file is already saved' : 'Ready to import'}
		</h2>
		<p class="text-ink-2">{preview.error_count ? 'Fix them in the file and choose it again, or import the rest and skip them.' : 'Nothing has been saved yet. Check the numbers, then import.'}</p>
	</div>
</div>

<dl class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
	{#each tiles as tile, i (tile.name)}
		<div class="rounded-xl bg-surface-2 px-4 py-3 ring-1 ring-line" in:fly={{ y: 8 * move, duration: 300, delay: i * 70 * move, easing: cubicOut }}>
			<dt class="text-sm text-ink-2">{tile.name}</dt>
			<dd class="num mt-0.5 text-2xl font-semibold {tile.count ? tile.tone : 'text-muted'}">{tile.count.toLocaleString('en-US')}</dd>
		</div>
	{/each}
</dl>

{#if preview.new_units.length}
	<details class="mt-4 text-sm">
		<summary class="cursor-pointer text-ink-2">New {term.many} that will be added</summary>
		<ul class="mt-2 space-y-0.5 pl-5">{#each preview.new_units as u (u)}<li class="list-disc">{u}</li>{/each}</ul>
	</details>
{/if}

{#if preview.errors.length}
	<h3 class="mt-7 font-semibold">Problems</h3>
	<ul class="mt-2 max-h-80 divide-y divide-line overflow-auto rounded-xl ring-1 ring-line">
		{#each groups as group (group.column + group.message)}
			<li class="px-4 py-2.5 text-sm">
				<p>{group.message}</p>
				<p class="mt-0.5 text-xs text-muted">{group.column ? `${group.column} column, ` : ''}{describeRows(group.rows)}</p>
			</li>
		{/each}
	</ul>
	{#if preview.error_count > preview.errors.length}<p class="mt-2 text-sm text-ink-2">These are the first {preview.errors.length} of {preview.error_count} problems.</p>{/if}
	{#if preview.unknown_units && onAddMissing}
		<div class="mt-4 flex flex-wrap items-center gap-3 rounded-lg bg-accent-wash/60 px-3.5 py-2.5 ring-1 ring-chart/20" transition:slide={{ duration: 160 }}>
			<p class="min-w-0 flex-1 text-sm">
				{plural(preview.unknown_units, 'name')} in the file {preview.unknown_units === 1 ? "doesn't" : "don't"} match any {term.one}. If they're new, add them; if not, check the spelling in the file.
			</p>
			<button class="btn btn-secondary btn-sm" disabled={busy} onclick={onAddMissing}>Add {preview.unknown_units === 1 ? 'it' : 'them'} and check again</button>
		</div>
	{/if}
	<label class="mt-4 flex items-center gap-2.5 rounded-lg bg-surface-2 px-3.5 py-2.5 ring-1 ring-line" transition:slide={{ duration: 160 }}>
		<input type="checkbox" class="size-4 accent-[var(--accent)]" bind:checked={skipInvalid} /> Import everything else and skip the rows with problems
	</label>
{/if}

<div class="mt-4"><FormAlert message={serverError?.message ?? ''} /></div>

<div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-line pt-5">
	<button class="btn btn-ghost mr-auto" onclick={onBack}><Icon name="chevron-left" size={15} /> Back to columns</button>
	<button class="btn btn-primary" disabled={blocked || busy || nothing} onclick={onImport}>
		{busy ? 'Importing…' : nothing ? 'Nothing to import' : `Import ${plural(changes, 'figure')}`}
	</button>
</div>
