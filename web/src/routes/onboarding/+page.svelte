<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import Icon from '$lib/components/Icon.svelte';
	import Logo from '$lib/components/Logo.svelte';
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

	const go = (to: number, extra: Record<string, string> = {}) => goto(href('/onboarding', { step: to, cur: currency, ...extra }));
	const finish = () => goto('/');

	const TITLES: Record<number, { title: string; lead: string }> = $derived({
		1: { title: 'Set up your organisation', lead: 'Name it for the company or team whose KPIs you track. You can change it later.' },
		2: { title: `How is ${org?.name ?? 'it'} organised?`, lead: 'Figures are entered by each branch, department or team, and add up to the whole organisation.' },
		3: { title: 'Choose your first KPIs', lead: `Each one already knows how to add up over time and across ${term.many}. Targets come next, on your scorecard.` },
		4: { title: 'Invite your team', lead: 'Give each person access to what they report on or review. You can do this later under Admin.' }
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
		<div class="mx-auto max-w-[1080px] px-4 pt-6 pb-12 sm:px-6">
			<ol class="flex flex-wrap gap-x-6 gap-y-2 border-t border-navy-2 pt-6 text-sm" aria-label="Setup progress">
				{#each STEPS as name, i (name)}
					{@const n = i + 1}
					<li class="flex items-center gap-2 {n === step ? 'text-navy-ink' : 'text-navy-ink-2'}" aria-current={n === step ? 'step' : undefined}>
						<span class="grid size-6 place-items-center rounded-full text-xs font-semibold {n < step ? 'bg-navy-2 text-navy-ink' : n === step ? 'bg-surface text-ink' : 'ring-1 ring-navy-2'}">
							{#if n < step}<Icon name="check" size={13} stroke={3} />{:else}{n}{/if}
						</span>
						<span class={n === step ? 'font-semibold' : ''}>{name}</span>
					</li>
				{/each}
			</ol>
			<h1 class="mt-7 text-2xl font-semibold tracking-[-0.025em] text-balance">{TITLES[step].title}</h1>
			<p class="mt-2 max-w-[64ch] text-md text-navy-ink-2">{TITLES[step].lead}</p>
		</div>
	</section>

	<main class="mx-auto max-w-[1080px] px-4 pb-20 sm:px-6">
		{#if ready && session.user}
			{#if step === 1}
				<StepName {currency} onDone={(cur) => go(2, { cur })} />
			{:else if step === 2}
				<StepStructure onDone={() => go(3)} />
			{:else if step === 3}
				<StepKpis {currency} onDone={() => go(4)} onSkip={() => go(4)} />
			{:else}
				<StepInvites onDone={finish} />
			{/if}
		{/if}
	</main>
</div>
