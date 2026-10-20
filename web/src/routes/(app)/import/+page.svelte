<script lang="ts">
	import { onMount } from 'svelte';
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly, scale } from 'svelte/transition';
	import { api, ApiError } from '$lib/api';
	import DataTabs from '$lib/components/DataTabs.svelte';
	import EmptyState from '$lib/components/EmptyState.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import ImportCheck from '$lib/components/import/ImportCheck.svelte';
	import ImportColumns from '$lib/components/import/ImportColumns.svelte';
	import ImportDrop from '$lib/components/import/ImportDrop.svelte';
	import PageBand, { WIDTH } from '$lib/components/PageBand.svelte';
	import { plural } from '$lib/format';
	import { ImportUpload } from '$lib/import.svelte';
	import { session } from '$lib/session.svelte';
	import { term } from '$lib/terms.svelte';
	import type { ImportPreview, ImportResult, Kpi } from '$lib/types';
	import { units } from '$lib/units.svelte';

	/**
	 * Uploading a spreadsheet in four steps: choose the file, match its columns, check what will
	 * change, import. Everything is checked on the server before anything is saved.
	 */
	const STEPS = ['Choose a file', 'Match columns', 'Check', 'Import'];
	const isAdmin = $derived(session.user?.organization?.role === 'admin');
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	let step = $state(0);
	let kpis = $state<Kpi[]>([]);
	const upload = new ImportUpload(session.user?.organization?.id ?? 0, () => kpis);
	let fileError = $state('');
	let preview = $state<ImportPreview | null>(null);
	let result = $state<ImportResult | null>(null);
	let skipInvalid = $state(false);
	let busy = $state(false);
	let serverError = $state<ApiError | null>(null);

	onMount(async () => {
		kpis = await api.kpis();
		upload.parentUnit = units.home()?.id ?? null;
	});

	async function pick(file: File | undefined) {
		if (!file) return;
		busy = true;
		fileError = await upload.read(file);
		busy = false;
		if (!fileError) step = 1;
	}

	async function check() {
		if (!upload.file) return;
		busy = true;
		serverError = null;
		try {
			preview = await api.previewImport(upload.file, upload.mapping(isAdmin));
			skipInvalid = false;
			step = 2;
		} catch (e) {
			serverError = e instanceof ApiError ? e : new ApiError(0, 'Could not check the file');
		} finally {
			busy = false;
		}
	}

	async function commit() {
		if (!upload.file || !preview) return;
		busy = true;
		serverError = null;
		try {
			result = await api.commitImport(upload.file, upload.mapping(isAdmin), skipInvalid);
			upload.remember();
			if (result.units_created) await units.load(true);
			step = 3;
		} catch (e) {
			serverError = e instanceof ApiError ? e : new ApiError(0, 'The import failed');
		} finally {
			busy = false;
		}
	}

	/** Turns on adding the units the file names that don't exist yet, and checks again. */
	function addMissing() {
		upload.createMissing = true;
		check();
	}

	function restart() {
		step = 0;
		upload.file = null;
		preview = null;
		result = null;
		serverError = null;
		fileError = '';
	}
</script>

<svelte:head><title>Add data | Clairvoyance</title></svelte:head>

<PageBand
	title="Add data"
	lead="Upload a spreadsheet with one row per {term.one} and date, and one column per KPI. Uploading the same file again updates figures instead of duplicating them."
	width="form"
>
	<DataTabs />
	{#if units.enterable().length}
	<ol class="mt-6 grid grid-cols-4 gap-2" aria-label="Steps">
		{#each STEPS as name, i (name)}
			<li aria-current={i === step ? 'step' : undefined}>
				<span class="block h-1.5 overflow-hidden rounded-full bg-navy-2">
					<span class="block h-full rounded-full bg-chart transition-[width] duration-500 ease-out" style:width={i <= step ? '100%' : '0%'}></span>
				</span>
				<span class="mt-2 flex items-center gap-1.5 text-xs {i === step ? 'font-semibold text-navy-ink' : 'text-navy-ink-2'}">
					{#if i < step}<Icon name="check" size={12} stroke={3} />{/if}{name}
				</span>
			</li>
		{/each}
	</ol>
	{/if}
</PageBand>

<main class="mx-auto max-w-[1240px] px-4 pb-16 sm:px-6"><div class="mx-auto {WIDTH.form}">
	{#if !units.enterable().length}
		<div class="-mt-5">
			<EmptyState icon="lock" title="Nothing for you to upload yet">
				<p>An administrator can let you enter figures for the {term.many} you report for. Until then you can look at the scorecard.</p>
				{#snippet action()}<a class="btn btn-secondary" href="/">Open the scorecard</a>{/snippet}
			</EmptyState>
		</div>
	{:else}
	<div class="panel -mt-5 overflow-hidden p-5 sm:p-7">
		{#key step}
			<div in:fly={{ x: 24 * move, duration: 300, easing: cubicOut }}>
				{#if step === 0}
					<ImportDrop {kpis} error={fileError} {busy} onFile={pick} />
				{:else if step === 1}
					<ImportColumns {upload} {kpis} {isAdmin} {busy} {serverError} onCheck={check} onRestart={restart} />
				{:else if step === 2 && preview}
					<ImportCheck
						{preview}
						bind:skipInvalid
						{busy}
						{serverError}
						onBack={() => (step = 1)}
						onImport={commit}
						onAddMissing={isAdmin && upload.unitMatch !== 'code' && !upload.createMissing ? addMissing : undefined}
					/>
				{:else if step === 3 && result}
					<div class="flex flex-col items-center py-8 text-center">
						<span class="grid size-16 place-items-center rounded-full bg-good-wash text-good-ink" in:scale={{ start: 0.4, duration: 400, delay: 150 * move, easing: cubicOut }}>
							<Icon name="check" size={30} stroke={2.5} />
						</span>
						<h2 class="mt-5 text-xl font-semibold tracking-[-0.015em]">Imported {upload.file?.name}</h2>
						<p class="mt-2 max-w-md text-ink-2">
							{plural(result.values_created, 'new figure')}, {plural(result.values_updated, 'changed figure')}, {result.unchanged.toLocaleString('en-US')} already saved{result.units_created ? `, ${plural(result.units_created, `new ${term.one}`, `new ${term.many}`)}` : ''}{result.skipped_errors ? `. Skipped ${plural(result.skipped_errors, 'row')} with problems` : ''}.
						</p>
						<p class="mt-1 text-sm text-muted">Next time, a file with the same columns is matched the same way.</p>
						<div class="mt-6 flex flex-wrap justify-center gap-3">
							<a class="btn btn-primary" href="/">Open the scorecard</a>
							<button class="btn btn-secondary" onclick={restart}>Import another file</button>
						</div>
					</div>
				{/if}
			</div>
		{/key}
	</div>
	{/if}
</div></main>
