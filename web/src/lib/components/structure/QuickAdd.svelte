<script lang="ts">
	import { api } from '$lib/api';
	import { units } from '$lib/units.svelte';
	import Icon from '../Icon.svelte';
	import { problem } from './save';

	/**
	 * Adds units inside a parent: type a name, press Enter, keep typing. Pasting several lines
	 * adds one per line.
	 */
	let {
		parentId,
		kind,
		label,
		placeholder,
		onAdded,
		onClose
	}: {
		parentId: number;
		kind: string | null;
		label: string;
		placeholder: string;
		onAdded: (ids: number[]) => void;
		onClose: () => void;
	} = $props();

	let name = $state('');
	let error = $state('');
	let busy = $state(false);
	let input = $state<HTMLInputElement>();

	$effect(() => {
		input?.focus();
	});

	async function add(names: string[]) {
		const fresh = [...new Set(names.map((n) => n.trim()).filter(Boolean))];
		if (!fresh.length) return;
		busy = true;
		error = '';
		const ids: number[] = [];
		try {
			for (const n of fresh) ids.push((await api.createUnit({ parent_id: parentId, name: n, kind })).id);
			name = '';
		} catch (e) {
			error = problem(e, 'Could not add');
		} finally {
			busy = false;
			if (ids.length) {
				await units.load(true);
				onAdded(ids);
			}
			input?.focus();
		}
	}

	function onkeydown(event: KeyboardEvent) {
		if (event.key === 'Enter') {
			event.preventDefault();
			add([name]);
		} else if (event.key === 'Escape') {
			onClose();
		}
	}

	function onpaste(event: ClipboardEvent) {
		const text = event.clipboardData?.getData('text') ?? '';
		if (text.includes('\n')) {
			event.preventDefault();
			add(text.split(/\r?\n/));
		}
	}
</script>

<div class="py-1">
	<div class="flex items-center gap-2 rounded-lg px-2.5 py-1 ring-1 ring-line-strong focus-within:ring-2 focus-within:ring-chart">
		<Icon name="plus" size={15} class="shrink-0 text-muted" />
		<input
			bind:this={input}
			class="min-w-0 flex-1 bg-transparent py-1 text-sm outline-none placeholder:text-muted"
			aria-label={label}
			{placeholder}
			disabled={busy}
			bind:value={name}
			{onkeydown}
			{onpaste}
		/>
		{#if name.trim()}
			<button class="btn btn-primary btn-sm" disabled={busy} onclick={() => add([name])}>{busy ? 'Adding…' : 'Add'}</button>
		{/if}
		<button class="rounded p-1 text-muted hover:text-ink" aria-label="Stop adding" onclick={onClose}><Icon name="x" size={14} /></button>
	</div>
	{#if error}<p class="mt-1 px-2 text-sm text-bad-ink" role="alert">{error}</p>{/if}
</div>
