<script lang="ts" module>
	/** Must match AccountService::TERMS_VERSION in the API, which sign-up records */
	export const UPDATED = '20 October 2026';
	export const CONTACT = 'support@clairvoyance.solutions';
</script>

<script lang="ts">
	import type { Snippet } from 'svelte';
	import { session } from '$lib/session.svelte';
	import Logo from './Logo.svelte';

	/** A plain reading page for the Terms and the Privacy Policy, open to everyone. */
	let { title, children }: { title: string; children: Snippet } = $props();
</script>

<svelte:head><title>{title} | Clairvoyance</title></svelte:head>

<div class="min-h-screen bg-plane">
	<header class="mx-auto flex max-w-[720px] items-center justify-between px-4 pt-8 sm:px-6">
		<a href="/" aria-label="Clairvoyance home"><Logo /></a>
		<a class="text-sm font-medium text-accent hover:underline" href={session.user ? '/' : '/register'}>
			{session.user ? 'Back to Clairvoyance' : 'Create an account'}
		</a>
	</header>
	<main class="mx-auto max-w-[720px] px-4 py-8 sm:px-6">
		<article class="legal rounded-2xl bg-surface p-7 ring-1 ring-line sm:p-10">
			<h1 class="text-2xl font-semibold tracking-[-0.02em]">{title}</h1>
			<p class="mt-1 text-sm text-muted">Last updated {UPDATED}</p>
			{@render children()}
		</article>
		<nav class="mt-6 flex justify-center gap-5 text-sm text-ink-2" aria-label="Legal">
			<a class="hover:text-ink hover:underline" href="/terms">Terms of Service</a>
			<a class="hover:text-ink hover:underline" href="/privacy">Privacy Policy</a>
			<a class="hover:text-ink hover:underline" href="mailto:{CONTACT}">{CONTACT}</a>
		</nav>
	</main>
</div>

<style>
	.legal :global(h2) {
		margin-top: 2rem;
		font-size: 1.0625rem;
		font-weight: 600;
	}
	.legal :global(p),
	.legal :global(ul) {
		margin-top: 0.75rem;
		line-height: 1.65;
		color: var(--ink-2);
	}
	.legal :global(ul) {
		list-style: disc;
		padding-left: 1.25rem;
	}
	.legal :global(li + li) {
		margin-top: 0.375rem;
	}
	.legal :global(strong) {
		color: var(--ink);
		font-weight: 600;
	}
	.legal :global(a) {
		color: var(--accent);
		text-decoration: underline;
	}
</style>
