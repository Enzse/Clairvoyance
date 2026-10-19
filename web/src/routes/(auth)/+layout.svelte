<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import AuthShell from '$lib/components/AuthShell.svelte';
	import { landingFor, session } from '$lib/session.svelte';

	let { children } = $props();

	// Already signed in: carry on to where they were going
	$effect(() => {
		if (session.user) goto(landingFor(session.user, page.url.searchParams.get('next')), { replaceState: true });
	});
</script>

<AuthShell>{@render children()}</AuthShell>
