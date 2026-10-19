<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { fly } from 'svelte/transition';
	import { api } from '$lib/api';
	import { pluralize } from '$lib/format';
	import { problem } from '$lib/problem';
	import { setMe } from '$lib/session.svelte';
	import { term } from '$lib/terms.svelte';
	import { toast } from '$lib/toast.svelte';
	import Icon from '../Icon.svelte';

	/** The organisation's word for the parts that report. A suggestion saves on click; anything else can be typed. */
	const SUGGESTIONS = ['branch', 'store', 'department', 'team', 'office', 'site', 'school', 'clinic'];

	let custom = $state(SUGGESTIONS.includes(term.one) ? '' : term.one);
	let hovered = $state<string | null>(null);
	let saving = $state(false);

	// The sentence shows whichever word is being considered
	const preview = $derived((hovered ?? custom.trim().toLowerCase()) || term.one);
	const customDiffers = $derived(!!custom.trim() && custom.trim().toLowerCase() !== term.one);

	async function choose(word: string) {
		if (word === term.one || saving) return;
		saving = true;
		try {
			setMe(await api.updateOrganization({ unit_label: word }));
			toast(`Saved. The app now says “${term.many}”`);
		} catch (e) {
			toast(problem(e, 'Could not save'), 'error');
		} finally {
			saving = false;
		}
	}

	async function saveCustom(event: SubmitEvent) {
		event.preventDefault();
		await choose(custom.trim().toLowerCase());
	}
</script>

<div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Suggested words">
	{#each SUGGESTIONS as word (word)}
		{@const on = term.one === word}
		<button
			type="button"
			role="radio"
			aria-checked={on}
			class="flex items-center gap-1.5 rounded-full px-3 py-1 text-sm ring-1 transition-colors {on ? 'bg-accent-wash font-medium text-accent ring-chart' : 'ring-line-strong hover:bg-surface-2'}"
			onclick={() => {
				custom = '';
				choose(word);
			}}
			onmouseenter={() => (hovered = word)}
			onmouseleave={() => (hovered = null)}
			onfocus={() => (hovered = word)}
			onblur={() => (hovered = null)}
		>
			{#if on}<Icon name="check" size={13} stroke={3} />{/if}{word}
		</button>
	{/each}
</div>

<form class="mt-3 flex flex-wrap items-center gap-2" onsubmit={saveCustom}>
	<input class="input h-9 min-w-0 flex-1 basis-48" placeholder="Something else, e.g. clinic, ward, outlet" maxlength="40" bind:value={custom} aria-label="Your own word" />
	{#if customDiffers}
		<button class="btn btn-secondary btn-sm" disabled={saving} transition:fly={{ x: -6, duration: 150 }}>Use “{custom.trim().toLowerCase()}”</button>
	{/if}
</form>

<p class="mt-4 rounded-lg bg-surface-2 px-3.5 py-2.5 text-sm text-ink-2 ring-1 ring-line" aria-live="polite">
	For example:
	{#key preview}<span class="inline-block font-medium text-ink" in:fly={{ y: -4, duration: 200, easing: cubicOut }}>“2 of 3 {pluralize(preview)} have reported this month”</span>{/key}
</p>
