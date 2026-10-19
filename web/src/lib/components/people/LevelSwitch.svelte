<script lang="ts">
	import type { Membership } from '$lib/types';
	import Icon from '../Icon.svelte';

	/** Views or Enters data, as a two-way switch. */
	let { value, onchange, compact = false }: { value: Membership['role']; onchange: (value: Membership['role']) => void; compact?: boolean } = $props();

	const LEVELS = [
		{ value: 'viewer', label: 'Views', icon: 'eye' },
		{ value: 'contributor', label: 'Enters data', icon: 'pencil' }
	] as const;
</script>

<div class="flex shrink-0 rounded-lg bg-surface-2 p-0.5 ring-1 ring-line" role="radiogroup" aria-label="What they can do">
	{#each LEVELS as level (level.value)}
		{@const on = value === level.value}
		<button
			type="button"
			role="radio"
			aria-checked={on}
			class="flex items-center gap-1.5 rounded-md transition-colors {compact ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-sm'} {on ? 'bg-surface font-medium text-ink shadow-sm' : 'text-ink-2 hover:text-ink'}"
			onclick={() => on || onchange(level.value)}
		>
			<Icon name={level.icon} size={compact ? 11 : 13} class={on ? 'text-accent' : ''} />{level.label}
		</button>
	{/each}
</div>
