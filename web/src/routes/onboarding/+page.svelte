<script lang="ts">
	import { onMount } from 'svelte';
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import Icon from '$lib/components/Icon.svelte';
	import Logo from '$lib/components/Logo.svelte';
	import { WIDTH } from '$lib/components/PageBand.svelte';
	import StepInvites from '$lib/components/onboarding/StepInvites.svelte';
	import StepKpis from '$lib/components/onboarding/StepKpis.svelte';
	import StepName from '$lib/components/onboarding/StepName.svelte';
	import StepStructure from '$lib/components/onboarding/StepStructure.svelte';
	import { href } from '$lib/nav';
	import { logout, session } from '$lib/session.svelte';
	import { guessCurrency } from '$lib/starter-kpis';
	import { units } from '$lib/units.svelte';
	import { term } from '$lib/terms.svelte';

	/**
	 * Four steps after sign-up: name the organisation, describe its structure, pick starter
	 * KPIs, invite people. The step lives in the URL so a refresh resumes rather than creating
	 * a second organisation. ?new starts over for someone who already has one.
	 */
	const STEPS = ['Name', 'Structure', 'KPIs', 'Team'];
	const step = $derived(Math.min(4, Math.max(1, Number(page.url.searchParams.get('step') ?? 1))));
	const currency = $derived(page.url.searchParams.get('cur') ?? guessCurrency());
	const org = $derived(session.user?.organization ?? null);

	let ready = $state(false);
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	// Judge where the person should be once, on arrival. Re-running this when step 1 creates
	// the organisation would race the move to step 2 and skip the rest of onboarding.
	onMount(() => {
		if (!session.user) {
			goto(href('/login', { next: '/onboarding' }), { replaceState: true });
		} else if (step > 1 && (!org || org.role !== 'admin')) {
			goto('/onboarding', { replaceState: true });
		} else if (step === 1 && org && !page.url.searchParams.has('new')) {
			goto('/', { replaceState: true });
		}
	});

	// Steps after the first work on the new organisation's units
	$effect(() => {
		if (step === 1) {
			ready = true;
		} else if (session.user?.organization) {
			ready = false;
			units.load().then(() => (ready = true));
		}
	});

	// Each step's heading and card share one centred width: short forms narrow, pickers wide
	// Pixel widths (not 'none') so the change between steps animates
	const width = $derived(step === 1 ? 'max-w-[640px]' : step === 4 ? WIDTH.narrow : 'max-w-[1032px]');

	const go = (to: number, extra: Record<string, string> = {}) => goto(href('/onboarding', { step: to, cur: currency, ...extra }));
	const finish = () => goto('/');

	const TITLES: Record<number, { title: string; lead: string }> = $derived({
		1: { title: 'Set up your organisation', lead: 'Name it for the company or team whose KPIs you track. You can change it later.' },
		2: { title: `How is ${org?.name ?? 'it'} organised?`, lead: 'Figures are entered by each branch, department or team, and add up to the whole organisation.' },
		3: { title: 'Choose your first KPIs', lead: `Each one already knows how to add up over time and across ${term.many}. Targets come next, on your scorecard.` },
		4: { title: 'Invite your team', lead: 'Give each person access to what they report on or review. You can also do this later under Manage › People.' }
	});
</script>

<svelte:head><title>{TITLES[step].title} | Clairvoyance</title></svelte:head>

<div class="min-h-screen">
	<header class="bg-navy text-navy-ink">
		<div class="mx-auto flex h-14 max-w-[1080px] items-center justify-between px-4 sm:px-6">
			<Logo onNavy size={26} />
			<button class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-navy-ink-2 hover:bg-navy-2 hover:text-navy-ink" onclick={async () => { await logout(); goto('/login'); }}>
				<Icon name="log-out" size={15} /> Sign out
			</button>
		</div>
	</header>

	<section class="bg-navy text-navy-ink">
		<div class="mx-auto max-w-[1080px] px-4 pt-6 pb-14 sm:px-6">
			<div class="border-t border-navy-2 pt-6"><div class="mx-auto {width} transition-[max-width] duration-300">
				<ol class="grid grid-cols-4 gap-2" aria-label="Setup progress">
					{#each STEPS as name, i (name)}
						{@const n = i + 1}
						<li aria-current={n === step ? 'step' : undefined}>
							<span class="block h-1.5 overflow-hidden rounded-full bg-navy-2">
								<span class="block h-full rounded-full bg-chart transition-[width] duration-500 ease-out" style:width={n <= step ? '100%' : '0%'}></span>
							</span>
							<span class="mt-2 flex items-center gap-1.5 text-xs {n === step ? 'font-semibold text-navy-ink' : 'text-navy-ink-2'}">
								{#if n < step}<Icon name="check" size={12} stroke={3} />{/if}{name}
							</span>
						</li>
					{/each}
				</ol>
				{#key step}
					<div in:fly={{ y: 10 * move, duration: 350, easing: cubicOut }}>
						<p class="mt-8 text-xs font-semibold tracking-wide text-navy-ink-2 uppercase">Step {step} of {STEPS.length}</p>
						<h1 class="mt-1 text-2xl font-semibold tracking-[-0.025em] text-balance">{TITLES[step].title}</h1>
						<p class="mt-2 max-w-[64ch] text-md text-navy-ink-2">{TITLES[step].lead}</p>
					</div>
				{/key}
			</div></div>
		</div>
	</section>

	<main class="mx-auto max-w-[1080px] px-4 pb-20 sm:px-6"><div class="mx-auto {width} transition-[max-width] duration-300">
		{#if ready && session.user}
			{#key step}
				<div in:fly={{ y: 16 * move, duration: 380, delay: 80 * move, easing: cubicOut }}>
					{#if step === 1}
						<StepName {currency} onDone={(cur) => go(2, { cur })} />
					{:else if step === 2}
						<StepStructure onDone={() => go(3)} />
					{:else if step === 3}
						<StepKpis {currency} onDone={() => go(4)} onSkip={() => go(4)} />
					{:else}
						<StepInvites onDone={finish} />
					{/if}
				</div>
			{/key}
		{/if}
	</div></main>
</div>
