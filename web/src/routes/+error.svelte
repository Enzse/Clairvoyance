<script lang="ts">
	import { page } from '$app/state';
	import ErrorScreen from '$lib/components/ErrorScreen.svelte';
	import { session } from '$lib/session.svelte';

	/** Shown for an address that isn't a page, or when a page fails to load. */
	const missing = $derived(page.status === 404);
</script>

<svelte:head><title>{missing ? 'Page not found' : 'Something went wrong'} | Clairvoyance</title></svelte:head>

<ErrorScreen code={String(page.status)} title={missing ? "This page doesn't exist" : 'Something went wrong'}>
	{#if missing}
		<p>The link may be old or mistyped. Everything else is where you left it.</p>
	{:else}
		<p>{page.error?.message ?? 'The page could not be shown.'} Try again, and if it keeps happening, let your administrator know.</p>
	{/if}
	{#snippet actions()}
		<a class="btn btn-primary" href="/">{session.user ? 'Go to your scorecard' : 'Sign in'}</a>
		{#if missing}
			<button class="btn btn-secondary" onclick={() => history.back()}>Go back</button>
		{:else}
			<button class="btn btn-secondary" onclick={() => location.reload()}>Try again</button>
		{/if}
	{/snippet}
</ErrorScreen>
