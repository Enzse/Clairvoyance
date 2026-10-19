<script lang="ts" generics="T extends string">
	import Icon, { type IconName } from './Icon.svelte';

	/** A choice shown as a row of cards, each with a label and a line about what it means. */
	let {
		name,
		legend,
		options,
		value = $bindable(),
		error
	}: {
		name: string;
		legend: string;
		options: { value: T; label: string; hint: string; icon?: IconName }[];
		value: T;
		error?: string;
	} = $props();
</script>

<fieldset>
	<legend class="field-label">{legend}</legend>
	<div class="grid gap-2 {options.length === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-3'}">
		{#each options as option (option.value)}
			{@const on = value === option.value}
			<label
				class="relative flex cursor-pointer flex-col rounded-xl px-3.5 py-3 ring-1 transition-all
					{on ? 'bg-accent-wash ring-2 ring-chart' : 'ring-line-strong hover:bg-surface-2 hover:ring-ink-2/40'}"
			>
				<input type="radio" {name} value={option.value} bind:group={value} class="sr-only" />
				<span class="flex items-center gap-2 font-medium">
					{#if option.icon}<Icon name={option.icon} size={15} class={on ? 'text-accent' : 'text-muted'} />{/if}
					{option.label}
				</span>
				<span class="mt-0.5 text-sm text-ink-2">{option.hint}</span>
				{#if on}<span class="absolute top-2.5 right-2.5 text-accent"><Icon name="check" size={14} stroke={3} /></span>{/if}
			</label>
		{/each}
	</div>
	{#if error}<p class="field-error">{error}</p>{/if}
</fieldset>
