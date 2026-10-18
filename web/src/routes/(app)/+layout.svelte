<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { ApiError } from '$lib/api';
	import TopBar from '$lib/components/TopBar.svelte';
	import { session } from '$lib/session.svelte';
	import { units } from '$lib/units.svelte';

	let { children } = $props();
	let error = $state('');

	$effect(() => {
		if (!session.user) {
			goto(`/login?next=${encodeURIComponent(page.url.pathname + page.url.search)}`, { replaceState: true });
			return;
		}
		units.load().catch((e) => (error = e instanceof ApiError ? e.message : 'Could not load your units'));
	});
</script>

{#if session.user}
	<TopBar />
	{#if error}
		<p class="mx-auto max-w-[1240px] px-6 py-10 text-bad-ink" role="alert">{error}</p>
	{:else if units.loaded}
		{@render children()}
	{/if}
{/if}
