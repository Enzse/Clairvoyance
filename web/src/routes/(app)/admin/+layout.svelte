<script lang="ts">
	import { page } from '$app/state';
	import EmptyState from '$lib/components/EmptyState.svelte';
	import { session } from '$lib/session.svelte';

	let { children } = $props();
	const isAdmin = $derived(session.user?.organization?.role === 'admin');

	const tabs = $derived([
		{ href: '/admin/units', label: 'Structure', hint: 'Your units and how they’re grouped' },
		{ href: '/admin/kpis', label: 'KPIs', hint: 'What you measure, and targets' },
		{ href: '/admin/users', label: 'People', hint: 'Who can see and enter what' },
		{ href: '/admin/settings', label: 'Settings', hint: 'Name, wording, deleting' }
	]);
</script>

<section class="bg-navy text-navy-ink">
	<div class="mx-auto max-w-[1240px] px-4 pt-5 sm:px-6">
		<div class="border-t border-navy-2 pt-6">
			<h1 class="text-2xl font-semibold tracking-[-0.025em]">Manage {session.user?.organization?.name}</h1>
		</div>
		{#if isAdmin}
		<nav class="mt-5 flex gap-1 overflow-x-auto" aria-label="Manage">
			{#each tabs as tab (tab.href)}
				{@const active = page.url.pathname.startsWith(tab.href)}
				<a
					href={tab.href}
					aria-current={active ? 'page' : undefined}
					class="rounded-t-lg px-4 pt-2.5 pb-3 whitespace-nowrap {active ? 'bg-plane font-semibold text-ink' : 'text-navy-ink-2 hover:text-navy-ink'}"
				>
					{tab.label}
					<span class="block text-xs font-normal {active ? 'text-ink-2' : 'text-navy-ink-2/80'}">{tab.hint}</span>
				</a>
			{/each}
		</nav>
		{:else}
			<div class="pb-9"></div>
		{/if}
	</div>
</section>

<main class="mx-auto max-w-[1240px] px-4 py-8 sm:px-6">
	{#if isAdmin}
		{@render children()}
	{:else}
		<div class="-mt-12">
			<EmptyState icon="shield" title="Manage is for administrators">
				<p>Administrators set up the structure, KPIs and people of {session.user?.organization?.name}. Ask one of them if something needs changing.</p>
				{#snippet action()}<a class="btn btn-secondary" href="/">Back to the scorecard</a>{/snippet}
			</EmptyState>
		</div>
	{/if}
</main>
