<script lang="ts">
	import type { Snippet } from 'svelte';
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import ScorecardArt from './auth/ScorecardArt.svelte';
	import Icon, { type IconName } from './Icon.svelte';
	import Logo from './Logo.svelte';

	/**
	 * The split screen behind sign-in, sign-up and invitation pages: what Clairvoyance does on
	 * the left, the page's card on the right.
	 */
	let { children }: { children: Snippet } = $props();

	const FEATURES: { icon: IconName; text: string }[] = [
		{ icon: 'layers', text: 'Figures add up the right way, from each branch to the whole organisation' },
		{ icon: 'target', text: 'See at a glance what is on target, close or behind' },
		{ icon: 'users', text: 'Everyone sees and reports only their part' }
	];
	const move = $derived(prefersReducedMotion.current ? 0 : 1);
</script>

<div class="grid min-h-screen bg-plane lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
	<aside class="relative hidden flex-col overflow-hidden bg-navy p-12 text-navy-ink lg:flex">
		<!-- A soft glow behind the illustration -->
		<div class="pointer-events-none absolute -right-24 bottom-0 size-[480px] rounded-full bg-chart/25 blur-3xl"></div>
		<Logo onNavy />
		<div class="relative mt-16 max-w-md">
			<p class="text-[1.75rem] leading-tight font-semibold tracking-[-0.02em] text-balance">Your organisation's KPIs, against target, in one place.</p>
			<ul class="mt-8 space-y-4">
				{#each FEATURES as f, i (f.icon)}
					<li class="flex items-center gap-3.5 text-navy-ink-2" in:fly={{ x: -10 * move, duration: 400, delay: 100 + i * 100 * move, easing: cubicOut }}>
						<span class="grid size-9 shrink-0 place-items-center rounded-xl bg-navy-2 text-navy-ink"><Icon name={f.icon} size={17} /></span>
						{f.text}
					</li>
				{/each}
			</ul>
		</div>
		<div class="relative mt-auto hidden justify-end pt-10 pr-4 pb-6 [@media(min-height:760px)]:flex">
			<ScorecardArt />
		</div>
	</aside>

	<main class="flex flex-col items-center justify-center px-4 py-10 sm:px-6">
		<div class="mb-8 lg:hidden"><Logo /></div>
		<div class="w-full max-w-[420px]" in:fly={{ y: 14 * move, duration: 380, easing: cubicOut }}>
			<div class="rounded-2xl bg-surface p-7 shadow-pop ring-1 ring-line sm:p-9">
				{@render children()}
			</div>
		</div>
	</main>
</div>
