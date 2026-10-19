<script lang="ts">
	import '../app.css';
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { setUnauthorizedHandler } from '$lib/api';
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
</script>

{#if session.loaded}
	{@render children()}
{/if}

<Toasts />
