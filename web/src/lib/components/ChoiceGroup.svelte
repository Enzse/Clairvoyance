<script lang="ts" generics="T extends string">
	/** Radio options as stacked rows with a one-line explanation each. */
	let {
		name,
		legend,
		options,
		value = $bindable(),
		error
	}: {
		name: string;
		legend: string;
		options: { value: T; label: string; hint: string }[];
		value: T;
		error?: string;
	} = $props();
</script>

<fieldset>
	<legend class="field-label">{legend}</legend>
	<div class="overflow-hidden rounded-lg ring-1 {error ? 'ring-bad' : 'ring-line-strong'}">
		{#each options as option, i (option.value)}
			<label class="flex cursor-pointer items-start gap-3 px-3.5 py-2.5 {i ? 'border-t border-line' : ''} {value === option.value ? 'bg-accent-wash' : 'hover:bg-surface-2'}">
				<input type="radio" {name} value={option.value} bind:group={value} class="mt-1 accent-[var(--accent)]" />
				<span>
					<span class="block font-medium">{option.label}</span>
					<span class="block text-sm text-ink-2">{option.hint}</span>
				</span>
			</label>
		{/each}
	</div>
	{#if error}<p class="field-error">{error}</p>{/if}
</fieldset>
