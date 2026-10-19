<script lang="ts">
	import { onMount } from 'svelte';
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly, scale } from 'svelte/transition';
	import { api, ApiError } from '$lib/api';
	import { plural } from '$lib/format';
	import { guideSteps, newlyFinished, type GuideStep } from '$lib/setup-guide';
	import { session } from '$lib/session.svelte';
	import { term } from '$lib/terms.svelte';
	import { toast } from '$lib/toast.svelte';
	import type { Setup, SetupStep } from '$lib/types';
	import Icon from './Icon.svelte';
	import ProgressRing from './ProgressRing.svelte';

	/**
	 * Getting started, for admins of a new organisation: one step at a time, with why it matters
	 * and what to do next. Steps that can wait can be skipped. Coming back after finishing a
	 * step, the ring catches up and the guide says what's been done since.
	 */
	let { shown = $bindable(false) }: { shown?: boolean } = $props();

	let setup = $state<Setup | null>(null);
	let selected = $state<SetupStep | null>(null);
	let justDone = $state<string[]>([]);
	let ringFrom = $state(0);
	let busy = $state(false);

	const org = $derived(session.user?.organization?.name ?? 'your organisation');
	const steps = $derived(setup ? guideSteps(setup, term.one, term.many, !!session.user?.email_delivery) : []);
	const finished = $derived(steps.filter((s) => s.state !== 'todo').length);
	const firstTodo = $derived(steps.find((s) => s.state === 'todo'));
	const current = $derived(steps.find((s) => s.key === selected) ?? firstTodo ?? steps[0]);
	const index = $derived(steps.findIndex((s) => s.key === current?.key));
	const nextTodo = $derived(steps.find((s, i) => i > index && s.state === 'todo') ?? (firstTodo?.key !== current?.key ? firstTodo : undefined));
	const visible = $derived(!!setup && !setup.dismissed);
	$effect(() => {
		shown = visible;
	});
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	// What was done at the last visit, so the guide can say what's new
	const storageKey = () => `clairvoyance.setup.${session.user?.organization?.id}`;
	function remembered(): { seen: string[]; progress: number } | null {
		try {
			return JSON.parse(localStorage.getItem(storageKey()) ?? 'null');
		} catch {
			return null;
		}
	}
	function remember() {
		try {
			localStorage.setItem(storageKey(), JSON.stringify({ seen: steps.filter((s) => s.state === 'done').map((s) => s.key), progress: finished }));
		} catch {
			// Private browsing: the guide simply won't say what's new
		}
	}

	onMount(async () => {
		try {
			setup = await api.setup();
		} catch {
			return;
		}
		const before = remembered();
		ringFrom = before ? Math.min(before.progress, finished) : finished;
		justDone = before ? newlyFinished(steps, before.seen).map((s) => s.title) : [];
		remember();
	});

	async function skip(step: GuideStep, skipped: boolean) {
		busy = true;
		try {
			setup = await api.skipSetupStep(step.key, skipped);
			selected = skipped ? null : step.key;
			remember();
		} catch (e) {
			toast(e instanceof ApiError ? e.message : 'Could not save', 'error');
		} finally {
			busy = false;
		}
	}

	async function close(message: string) {
		try {
			setup = await api.dismissSetup();
			toast(message);
		} catch (e) {
			toast(e instanceof ApiError ? e.message : 'Could not hide the guide', 'error');
		}
	}

	const quoted = (titles: string[]) => titles.map((t) => `“${t}”`).join(' and ');
	const status = (s: GuideStep) =>
		s.state === 'done' ? s.progress || 'Done' : s.state === 'skipped' ? 'Skipped' : s.key === firstTodo?.key ? 'Up next' : 'To do';
</script>

{#if visible && setup && current}
	<section class="panel -mt-5 mb-6 overflow-hidden" aria-labelledby="guide-heading">
		{#if setup.complete}
			<div class="flex flex-col items-center px-6 py-10 text-center" in:scale={{ start: 0.96, duration: 300 * move, easing: cubicOut }}>
				<ProgressRing value={steps.length} total={steps.length} from={ringFrom} size={72} />
				<h2 id="guide-heading" class="mt-5 text-xl font-semibold tracking-[-0.015em]">{org} is ready</h2>
				<p class="mt-2 max-w-[56ch] text-ink-2">
					{plural(setup.counts.kpis, 'KPI')}, {plural(setup.counts.values, 'figure')} so far{setup.counts.members > 1 ? `, ${plural(setup.counts.members, 'person', 'people')}` : ''}. From
					here on, the scorecard fills in as your {term.many} report each period.
				</p>
				<button class="btn btn-primary mt-6" onclick={() => close('All set. The guide is closed.')}>Close the guide</button>
			</div>
		{:else}
			<header class="flex flex-wrap items-center gap-4 border-b border-line px-5 py-4 sm:px-6">
				<ProgressRing value={finished} total={steps.length} from={ringFrom} />
				<div class="min-w-0 flex-1">
					<h2 id="guide-heading" class="text-md font-semibold">Get {org} ready</h2>
					<p class="text-sm text-ink-2" aria-live="polite">
						{#if justDone.length}
							<span class="inline-flex items-center gap-1.5 font-medium text-good-ink" in:fly={{ y: 6 * move, duration: 400 }}>
								<Icon name="check" size={14} stroke={3} /> Nice, {quoted(justDone)} {justDone.length === 1 ? 'is' : 'are'} done.
							</span>
						{:else}
							{plural(steps.length - finished, 'step')} to go. Each takes a minute or two.
						{/if}
					</p>
				</div>
				<button class="btn btn-ghost btn-sm" onclick={() => close('Guide hidden. You can bring it back from Admin › Settings.')}>Hide</button>
			</header>

			<div class="grid md:grid-cols-[minmax(0,290px)_minmax(0,1fr)]">
				<ol class="border-b border-line p-2.5 md:border-r md:border-b-0" aria-label="Steps">
					{#each steps as s, i (s.key)}
						{@const active = s.key === current.key}
						<li>
							<button
								class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors {active ? 'bg-accent-wash' : 'hover:bg-surface-2'}"
								aria-current={active ? 'step' : undefined}
								onclick={() => (selected = s.key)}
							>
								<span
									class="grid size-7 shrink-0 place-items-center rounded-full text-xs font-semibold transition-colors
										{s.state === 'done' ? 'bg-good text-white' : s.state === 'skipped' ? 'bg-surface-2 text-muted ring-1 ring-line' : active ? 'bg-chart text-white' : 'text-ink-2 ring-1 ring-line-strong'}"
								>
									{#if s.state === 'done'}
										<span in:scale={{ start: 0.4, duration: 350 * move, easing: cubicOut }}><Icon name="check" size={14} stroke={3} /></span>
									{:else if s.state === 'skipped'}
										<Icon name="minus" size={14} stroke={2.5} />
									{:else}
										{i + 1}
									{/if}
								</span>
								<span class="min-w-0">
									<span class="block truncate text-sm {s.state === 'todo' ? 'font-medium text-ink' : 'text-ink-2'}">{s.title}</span>
									<span class="block truncate text-xs {s.key === firstTodo?.key && !active ? 'text-accent' : 'text-muted'}">{status(s)}</span>
								</span>
							</button>
						</li>
					{/each}
				</ol>

				<div class="min-w-0 p-5 sm:p-7">
					{#key current.key}
						<div in:fly={{ x: 14 * move, duration: 260, easing: cubicOut }}>
							<p class="text-xs font-semibold tracking-wide text-muted uppercase">Step {index + 1} of {steps.length}</p>
							<h3 class="mt-1.5 text-lg font-semibold tracking-[-0.01em]">{current.title}</h3>
							<p class="mt-2 max-w-[60ch] text-ink-2">{current.why}</p>

							{#if current.state === 'done'}
								<p class="mt-4 inline-flex items-center gap-2 rounded-full bg-good-wash px-3 py-1 text-sm font-medium text-good-ink">
									<Icon name="check" size={14} stroke={3} /> Done{current.progress ? `: ${current.progress}` : ''}
								</p>
							{:else if current.state === 'skipped'}
								<p class="mt-4 inline-flex items-center gap-2 rounded-full bg-surface-2 px-3 py-1 text-sm text-ink-2 ring-1 ring-line">
									Skipped for now. Come back to it whenever you like.
								</p>
							{/if}

							<div class="mt-6 flex flex-wrap items-center gap-3">
								{#each current.actions as action, j (action.href)}
									<a class="btn {current.state === 'todo' && j === 0 ? 'btn-primary' : 'btn-secondary'}" href={action.href}>
										{action.label}
										{#if current.state === 'todo' && j === 0}<Icon name="chevron-right" size={15} />{/if}
									</a>
								{/each}
								{#if current.state === 'todo' && current.skip}
									<button class="btn btn-ghost" disabled={busy} onclick={() => skip(current, true)}>{current.skip}</button>
								{:else if current.state === 'skipped'}
									<button class="btn btn-ghost" disabled={busy} onclick={() => skip(current, false)}>Don't skip it</button>
								{/if}
							</div>

							{#if nextTodo}
								<button class="mt-6 inline-flex items-center gap-1.5 border-t border-line pt-4 text-sm text-ink-2 hover:text-ink" onclick={() => (selected = nextTodo.key)}>
									Next: <span class="font-medium text-ink">{nextTodo.title}</span>
									<Icon name="chevron-right" size={14} />
								</button>
							{/if}
						</div>
					{/key}
				</div>
			</div>
		{/if}
	</section>
{/if}
