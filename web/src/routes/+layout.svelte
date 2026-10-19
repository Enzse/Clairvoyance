<script lang="ts">
	import '../app.css';
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { setUnauthorizedHandler } from '$lib/api';
	import ErrorScreen from '$lib/components/ErrorScreen.svelte';
	import Toasts from '$lib/components/Toasts.svelte';
	import { loadSession, session } from '$lib/session.svelte';
	import { units } from '$lib/units.svelte';

	let { children } = $props();

	// When the session expires mid-use, send the person to sign in and bring them back after
	setUnauthorizedHandler(() => {
		if (!session.user) return;
		session.user = null;
		units.reset();
		const next = page.url.pathname + page.url.search;
		goto(`/login?next=${encodeURIComponent(next)}`);
	});

	loadSession();

	let retrying = $state(false);
	async function retry() {
		retrying = true;
		await loadSession();
		retrying = false;
	}
</script>

{#if session.unreachable}
	<ErrorScreen title="Can't reach Clairvoyance">
		<p>The server isn't answering. Check your internet connection; if it's fine, the site may be down for a moment.</p>
		{#snippet actions()}
			<button class="btn btn-primary" disabled={retrying} onclick={retry}>{retrying ? 'Trying…' : 'Try again'}</button>
		{/snippet}
	</ErrorScreen>
{:else if session.loaded}
	{@render children()}
{/if}

<Toasts />
