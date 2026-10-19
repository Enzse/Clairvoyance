<script lang="ts">
	import { codeFrom, type KpiForm } from '$lib/kpi-form';
	import { FREQUENCIES, GRAIN_NAMES } from '$lib/period';

	/** Name, what it measures, how often figures come in, and the spreadsheet code. */
	let {
		form = $bindable(),
		errors = {},
		existing = false,
		focus = false
	}: { form: KpiForm; errors?: Record<string, string>; existing?: boolean; focus?: boolean } = $props();

	// A new KPI's code follows its name until the person edits the code themselves
	let codeTouched = $state(false);
	let editingCode = $state(false);
	$effect(() => {
		if (!existing && !codeTouched) form.code = codeFrom(form.name);
	});
</script>

<div class="space-y-5">
	<div>
		<label class="field-label" for="k-name">Name</label>
		<!-- svelte-ignore a11y_autofocus -->
		<input id="k-name" class="input text-md" autofocus={focus} placeholder="e.g. Revenue, Customer satisfaction" bind:value={form.name} aria-invalid={!!errors.name} />
		{#if errors.name}<p class="field-error">That name {errors.name}</p>{/if}
		<p class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs text-muted">
			Spreadsheet column:
			{#if editingCode || errors.code}
				<input
					class="input h-7 w-48 px-2 font-mono text-xs"
					aria-label="Spreadsheet column code"
					bind:value={form.code}
					oninput={() => (codeTouched = true)}
					aria-invalid={!!errors.code}
				/>
			{:else}
				<code class="rounded bg-surface-2 px-1.5 py-0.5 text-ink-2">{form.code || '…'}</code>
				<button type="button" class="text-accent hover:underline" onclick={() => (editingCode = true)}>change</button>
			{/if}
		</p>
		{#if errors.code}<p class="field-error">That code {errors.code}</p>{/if}
	</div>

	<div>
		<label class="field-label" for="k-desc">What it measures <span class="font-normal text-muted">(optional)</span></label>
		<textarea id="k-desc" class="input" rows="2" placeholder="e.g. Sales income for the month" bind:value={form.description}></textarea>
	</div>

	<fieldset>
		<legend class="field-label">How often figures come in</legend>
		<div class="inline-flex flex-wrap gap-1 rounded-lg bg-surface-2 p-1 ring-1 ring-line">
			{#each FREQUENCIES as f (f)}
				<label class="cursor-pointer rounded-md px-3 py-1.5 text-sm transition-colors {form.frequency === f ? 'bg-surface font-semibold text-ink shadow-sm ring-1 ring-line' : 'text-ink-2 hover:text-ink'}">
					<input type="radio" name="k-frequency" value={f} bind:group={form.frequency} class="sr-only" />
					{GRAIN_NAMES[f].adjective}
				</label>
			{/each}
		</div>
		{#if errors.frequency}<p class="field-error">{errors.frequency}</p>{/if}
	</fieldset>
</div>
