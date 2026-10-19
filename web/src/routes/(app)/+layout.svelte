<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { ApiError } from '$lib/api';
	import EmptyState from '$lib/components/EmptyState.svelte';
	import TopBar from '$lib/components/TopBar.svelte';
	import VerifyBanner from '$lib/components/VerifyBanner.svelte';
	import { session } from '$lib/session.svelte';
	import { units } from '$lib/units.svelte';

	let { children } = $props();
	let error = $state('');

	$effect(() => {
		if (!session.user) {
			goto(`/login?next=${encodeURIComponent(page.url.pathname + page.url.search)}`, { replaceState: true });
			return;
		}
		if (!session.user.organization) {
			goto('/onboarding', { replaceState: true });
			return;
		}
		units.load().catch((e) => (error = e instanceof ApiError ? e.message : 'Could not load your organisation'));
	});
</script>

{#if session.user?.organization}
	<TopBar />
	<VerifyBanner />
	{#if error}
		<div class="px-4 py-16" role="alert">
			<EmptyState icon="alert" title="Your organisation didn't load">
				<p>{error}</p>
				{#snippet action()}<button class="btn btn-primary" onclick={() => location.reload()}>Try again</button>{/snippet}
			</EmptyState>
		</div>
	{:else if units.loaded}
		{#key session.user.organization.id}
			{@render children()}
		{/key}
	{/if}
{/if}
