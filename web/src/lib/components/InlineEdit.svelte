<script lang="ts">
	import { tick } from 'svelte';
	import { toast } from '$lib/toast.svelte';
	import Tooltip from './Tooltip.svelte';
	import { problem } from '$lib/problem';

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
		tip,
		tipSide = 'top',
		class: className = '',
		inputClass = ''
	}: {
		value: string | null;
		onSave: (value: string) => Promise<void>;
		label: string;
		placeholder?: string;
		optional?: boolean;
		/** Tooltip, e.g. "Rename" */
		tip: string;
		tipSide?: 'top' | 'bottom' | 'right';
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
	<Tooltip text={tip} side={tipSide} class="-mx-1">
	<button
		type="button"
		class="max-w-full cursor-text truncate rounded px-1 text-left hover:bg-accent-wash {value ? '' : 'text-muted'} {className}"
		aria-label="{label}: {value || 'not set'}. Click to change"
		onclick={start}
	>
		{value || placeholder}
	</button>
	</Tooltip>
{/if}
