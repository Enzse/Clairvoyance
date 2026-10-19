<script lang="ts">
	import Icon from '../Icon.svelte';

	/**
	 * A password field with a show/hide button. For a new password, `rule` shows the length
	 * requirement and ticks it off as it's met.
	 */
	let {
		value = $bindable(''),
		id = 'password',
		autocomplete = 'current-password',
		rule = false,
		error = ''
	}: { value?: string; id?: string; autocomplete?: 'current-password' | 'new-password'; rule?: boolean; error?: string } = $props();

	let shown = $state(false);
	const long = $derived(value.length >= 8);
</script>

<div class="relative">
	<input
		{id}
		class="input pr-10"
		type={shown ? 'text' : 'password'}
		{autocomplete}
		minlength={rule ? 8 : undefined}
		required
		bind:value
		aria-invalid={!!error}
		aria-describedby={rule || error ? `${id}-hint` : undefined}
	/>
	<button
		type="button"
		class="absolute top-1/2 right-1.5 -translate-y-1/2 rounded-md p-1.5 text-muted hover:bg-surface-2 hover:text-ink"
		aria-label={shown ? 'Hide password' : 'Show password'}
		aria-pressed={shown}
		onclick={() => (shown = !shown)}
	>
		<Icon name={shown ? 'eye-off' : 'eye'} size={16} />
	</button>
</div>
{#if error}
	<p id="{id}-hint" class="field-error">{error}</p>
{:else if rule}
	<p id="{id}-hint" class="mt-1.5 flex items-center gap-1.5 text-sm transition-colors {long ? 'text-good-ink' : 'text-muted'}">
		<Icon name={long ? 'check' : 'minus'} size={13} stroke={long ? 3 : 2} /> At least 8 characters
	</p>
{/if}
