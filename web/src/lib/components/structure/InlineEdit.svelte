<script lang="ts">
	import { tick } from 'svelte';
	import { toast } from '$lib/toast.svelte';
	import { problem } from './save';

	/**
	 * Text that turns into a field when clicked. Enter or clicking away saves, Escape cancels.
	 * An empty value is allowed when `optional` (e.g. a unit's kind).
	 */
	let {
		value,
		onSave,
		label,
		placeholder = '',
		optional = false,
		class: className = '',
		inputClass = ''
	}: {
		value: string | null;
		onSave: (value: string) => Promise<void>;
		label: string;
		placeholder?: string;
		optional?: boolean;
		class?: string;
		inputClass?: string;
	} = $props();

	let editing = $state(false);
	let draft = $state('');
	let saving = $state(false);
	let input = $state<HTMLInputElement>();

	async function start(event: MouseEvent) {
		event.stopPropagation();
		draft = value ?? '';
		editing = true;
		await tick();
		input?.focus();
		input?.select();
	}

	async function commit() {
		if (!editing || saving) return;
		const next = draft.trim();
		if (next === (value ?? '') || (!next && !optional)) {
			editing = false;
			return;
		}
		saving = true;
		try {
			await onSave(next);
			editing = false;
		} catch (e) {
			toast(problem(e), 'error');
			input?.focus();
		} finally {
			saving = false;
		}
	}

	function onkeydown(event: KeyboardEvent) {
		if (event.key === 'Enter') {
			event.preventDefault();
			commit();
		} else if (event.key === 'Escape') {
			event.stopPropagation();
			editing = false;
		}
	}
</script>

{#if editing}
	<input
		bind:this={input}
		bind:value={draft}
		class="-mx-1 min-w-0 rounded px-1 outline-none ring-2 ring-chart {inputClass}"
		aria-label={label}
		{placeholder}
		disabled={saving}
		onblur={commit}
		{onkeydown}
		onclick={(e) => e.stopPropagation()}
	/>
{:else}
	<button
		type="button"
		class="-mx-1 max-w-full cursor-text truncate rounded px-1 text-left hover:bg-accent-wash {value ? '' : 'text-muted'} {className}"
		title="Click to change"
		aria-label="{label}: {value || 'not set'}. Click to change"
		onclick={start}
	>
		{value || placeholder}
	</button>
{/if}
