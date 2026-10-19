<script lang="ts">
	import { api, ApiError } from '$lib/api';
	import { session, setMe } from '$lib/session.svelte';
	import { CURRENCIES } from '$lib/starter-kpis';

	let { currency: initialCurrency, onDone }: { currency: string; onDone: (currency: string) => void } = $props();

	let name = $state('');
	let currency = $state(initialCurrency);
	let error = $state('');
	let busy = $state(false);

	const others = $derived(session.user?.organizations ?? []);

	async function submit(event: SubmitEvent) {
		event.preventDefault();
		busy = true;
		error = '';
		try {
			setMe(await api.createOrganization(name));
			onDone(currency);
		} catch (e) {
			error = e instanceof ApiError ? (e.field('name') ? `Name ${e.field('name')}` : e.message) : 'Could not create the organisation';
		} finally {
			busy = false;
		}
	}
</script>

<form class="panel -mt-6 p-6 sm:p-8" onsubmit={submit}>
	<label class="field-label" for="org-name">Organisation name</label>
	<!-- svelte-ignore a11y_autofocus -->
	<input id="org-name" class="input text-md" placeholder="e.g. Bean There Coffee" maxlength="255" required autofocus bind:value={name} />

	<label class="field-label mt-6" for="org-currency">Money is shown in</label>
	<select id="org-currency" class="input w-48" bind:value={currency}>
		{#each CURRENCIES as c (c)}<option value={c}>{c || 'No currency symbol'}</option>{/each}
	</select>
	<p class="field-hint">Used for money KPIs such as revenue. Each KPI can be changed later.</p>

	{#if error}<p class="mt-4 text-sm text-bad-ink" role="alert">{error}</p>{/if}

	<div class="mt-8 flex flex-wrap items-center justify-between gap-3">
		{#if others.length}
			<a class="text-sm text-accent hover:underline" href="/">Back to {others[0].name}</a>
		{:else}
			<span class="text-sm text-ink-2">Were you invited? Open the link from your invitation instead.</span>
		{/if}
		<button class="btn btn-primary" disabled={busy || !name.trim()}>{busy ? 'Creating…' : 'Continue'}</button>
	</div>
</form>
