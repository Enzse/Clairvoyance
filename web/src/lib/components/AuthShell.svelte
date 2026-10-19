<script lang="ts">
	import type { Snippet } from 'svelte';
	import Logo from './Logo.svelte';

	/** The split screen behind sign-in, sign-up and invitation pages. */
	let { children }: { children: Snippet } = $props();
</script>

<div class="grid min-h-screen lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
	<aside class="relative hidden flex-col justify-between overflow-hidden bg-navy p-12 text-navy-ink lg:flex">
		<Logo onNavy />
		<div class="relative max-w-md">
			<p class="text-2xl font-semibold tracking-[-0.02em] text-balance">Your organisation's KPIs, against target, in one place.</p>
			<p class="mt-4 text-md text-navy-ink-2">
				Branches, departments and teams record their figures each period. Clairvoyance adds them up the right way and shows
				what's on target, what's behind, and who hasn't reported yet.
			</p>
		</div>
		<!-- A quiet echo of the status matrix: three rows of reports coming in -->
		<svg class="absolute -right-10 bottom-28 opacity-[0.18]" width="360" height="132" viewBox="0 0 360 132" aria-hidden="true">
			{#each [0, 1, 2] as row (row)}
				{#each [0, 1, 2, 3, 4, 5, 6, 7] as col (col)}
					<rect x={col * 44} y={row * 44} width="36" height="36" rx="7" fill="currentColor" opacity={(row * 8 + col) % 5 === 3 ? 0.35 : 1} />
				{/each}
			{/each}
		</svg>
		<p class="relative text-sm text-navy-ink-2">Free to set up. Invite your team when you're ready.</p>
	</aside>
	<main class="flex items-center justify-center px-5 py-12">
		<div class="w-full max-w-[380px]">
			<div class="mb-10 lg:hidden"><Logo /></div>
			{@render children()}
		</div>
	</main>
</div>
