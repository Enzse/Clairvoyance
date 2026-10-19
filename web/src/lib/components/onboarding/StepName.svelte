<script lang="ts">
	import { api, ApiError } from '$lib/api';
	import { session, setMe } from '$lib/session.svelte';
	import { CURRENCIES } from '$lib/starter-kpis';
	import FormAlert from '../auth/FormAlert.svelte';
	import Icon from '../Icon.svelte';

	/** Naming the organisation and choosing the currency money KPIs start with. */
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
	<input id="org-name" class="input h-12 text-md" placeholder="e.g. Bean There Coffee" maxlength="255" required autofocus bind:value={name} />

	<!-- How it will look at the top of the structure -->
	<div class="mt-3 flex items-center gap-3 rounded-xl bg-surface-2 px-3 py-2.5 ring-1 ring-line" aria-hidden="true">
		<span class="grid size-9 place-items-center rounded-lg bg-navy text-navy-ink"><Icon name="building" size={17} /></span>
		<span class="leading-tight">
			<span class="block font-semibold {name.trim() ? '' : 'text-muted'}">{name.trim() || 'Your organisation'}</span>
			<span class="block text-xs text-muted">adds up everything below it</span>
		</span>
	</div>

	<fieldset class="mt-7">
		<legend class="field-label">Money is shown in</legend>
		<div class="flex flex-wrap gap-2">
			{#each CURRENCIES as c (c)}
				{@const on = currency === c}
				<label class="cursor-pointer rounded-full px-3.5 py-1.5 text-sm ring-1 transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-chart {on ? 'bg-accent-wash font-semibold text-accent ring-chart' : 'ring-line-strong hover:bg-surface-2'}">
					<input type="radio" name="currency" class="sr-only" value={c} bind:group={currency} />
					{c || 'No symbol'}
				</label>
			{/each}
		</div>
		<p class="field-hint">For money KPIs such as revenue. Each KPI can be changed later.</p>
	</fieldset>

	<div class="mt-5"><FormAlert message={error} /></div>

	<div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5">
		{#if others.length}
			<a class="text-sm text-accent hover:underline" href="/">Back to {others[0].name}</a>
		{:else}
			<span class="text-sm text-ink-2">Were you invited? Open the link from your invitation instead.</span>
		{/if}
		<button class="btn btn-primary" disabled={busy || !name.trim()}>{busy ? 'Creating…' : 'Continue'} <Icon name="arrow-right" size={15} /></button>
	</div>
</form>
