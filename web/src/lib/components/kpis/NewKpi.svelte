<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly, scale } from 'svelte/transition';
	import { api, ApiError } from '$lib/api';
	import { blankForm, fromStarter, stepOfError, toBody, type KpiForm, type TargetRow } from '$lib/kpi-form';
	import { problem } from '$lib/problem';
	import { guessCurrency, STARTER_KPIS } from '$lib/starter-kpis';
	import { toast } from '$lib/toast.svelte';
	import type { Kpi } from '$lib/types';
	import Icon from '../Icon.svelte';
	import KpiBasics from './KpiBasics.svelte';
	import KpiGoal from './KpiGoal.svelte';
	import KpiReach from './KpiReach.svelte';
	import KpiRollup from './KpiRollup.svelte';
	import { exampleNames, fieldErrors, weightChoicesFor } from './shared';

	/** Making a KPI in four short steps: what, how it adds up, what's good, who reports it. */
	let { open = $bindable(false), kpis, onCreated }: { open?: boolean; kpis: Kpi[]; onCreated: (kpi: Kpi) => void } = $props();

	const STEPS = [
		{ title: 'What do you measure?', lead: 'Name it, or start from a common one.' },
		{ title: 'How does it add up?', lead: 'Decide once, and every total and average comes out right.' },
		{ title: "What's good?", lead: 'A target turns a number into on target, close or behind.' },
		{ title: 'Who reports it?', lead: 'Everyone, or only the parts of the organisation it concerns.' }
	];

	let dialog: HTMLDialogElement;
	let step = $state(0);
	let direction = $state(1);
	let form = $state<KpiForm>(blankForm());
	let targets = $state<TargetRow[]>([]);
	let errors = $state<Record<string, string>>({});
	let busy = $state(false);
	let seq = 0;
	const nextKey = () => seq++;
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	$effect(() => {
		if (open && !dialog.open) {
			step = 0;
			form = blankForm();
			targets = [];
			errors = {};
			dialog.showModal();
		}
		if (!open && dialog.open) dialog.close();
	});

	// Examples not added yet; money ones use the currency the organisation already uses
	const examples = $derived(STARTER_KPIS.filter((s) => !kpis.some((k) => k.code === s.key)).slice(0, 8));
	const currency = $derived(kpis.find((k) => k.prefix)?.prefix ?? guessCurrency());
	function useExample(key: string) {
		const starter = STARTER_KPIS.find((s) => s.key === key)!;
		const weight = starter.weightKey ? kpis.find((k) => k.code === starter.weightKey)?.id ?? null : null;
		form = { ...form, ...fromStarter(starter, currency, weight) };
		errors = {};
	}

	function go(to: number) {
		if (to > step && step === 0 && !form.name.trim()) {
			errors = { name: 'is needed' };
			return;
		}
		errors = {};
		direction = to > step ? 1 : -1;
		step = to;
	}

	async function create() {
		busy = true;
		errors = {};
		try {
			const kpi = await api.createKpi(toBody(form));
			if (targets.length) {
				try {
					await api.saveTargets(kpi.id, targets.map(({ unit_id, effective_from, value }) => ({ unit_id, effective_from, value: Number(value) })));
				} catch (e) {
					toast(`${kpi.name} was added, but its target wasn't: ${problem(e)}`, 'error');
				}
			}
			open = false;
			onCreated(kpi);
		} catch (e) {
			if (e instanceof ApiError && Object.keys(e.details).length) {
				// Back to the step with the problem, showing what's wrong there
				const to = stepOfError(e.details);
				direction = to > step ? 1 : -1;
				step = to;
				errors = fieldErrors(e.details);
			} else {
				toast(problem(e, 'Could not add the KPI'), 'error');
			}
		} finally {
			busy = false;
		}
	}
</script>

<dialog
	bind:this={dialog}
	class="m-auto max-h-[min(860px,calc(100dvh-32px))] w-[min(680px,calc(100vw-32px))] flex-col overflow-hidden rounded-2xl bg-surface p-0 text-ink shadow-pop open:flex"
	aria-label="New KPI"
	onclose={() => (open = false)}
>
	{#if open}
		<div class="flex min-h-0 flex-1 flex-col" in:scale={{ start: 0.97, duration: 220 * move, easing: cubicOut }}>
			<header class="border-b border-line px-6 pt-5 pb-4">
				<div class="flex items-center justify-between gap-4">
					<ol class="flex gap-1.5" aria-label="Steps">
						{#each STEPS as s, i (s.title)}
							<li class="h-1.5 w-10 rounded-full transition-colors duration-300 {i <= step ? 'bg-chart' : 'bg-line-strong'}" aria-current={i === step ? 'step' : undefined}>
								<span class="sr-only">{s.title}</span>
							</li>
						{/each}
					</ol>
					<button class="rounded-md p-1.5 text-muted hover:bg-surface-2 hover:text-ink" onclick={() => (open = false)} aria-label="Close">
						<Icon name="x" size={18} />
					</button>
				</div>
				<p class="mt-4 text-xs font-semibold tracking-wide text-muted uppercase">New KPI · {step + 1} of {STEPS.length}</p>
				<h2 class="mt-1 text-xl font-semibold tracking-[-0.015em]">{STEPS[step].title}</h2>
				<p class="mt-1 text-ink-2">{STEPS[step].lead}</p>
			</header>

			<div class="min-h-0 flex-1 overflow-x-hidden overflow-y-auto px-6 py-5">
				{#key step}
					<div in:fly={{ x: 30 * direction * move, duration: 260, easing: cubicOut }}>
						{#if step === 0}
							{#if examples.length}
								<p class="field-label">Start from an example</p>
								<div class="mb-6 flex flex-wrap gap-2">
									{#each examples as s (s.key)}
										<button type="button" class="rounded-full px-3 py-1 text-sm ring-1 transition-colors {form.code === s.key ? 'bg-accent-wash font-medium ring-chart' : 'ring-line-strong hover:bg-surface-2'}" onclick={() => useExample(s.key)}>
											{s.name}
										</button>
									{/each}
								</div>
							{/if}
							<KpiBasics bind:form {errors} focus />
						{:else if step === 1}
							<KpiRollup bind:form weightChoices={weightChoicesFor(kpis, form, null)} names={exampleNames()} {errors} />
						{:else if step === 2}
							<KpiGoal bind:form bind:targets {nextKey} {errors} />
						{:else}
							<KpiReach bind:form {errors} />
						{/if}
					</div>
				{/key}
			</div>

			<footer class="flex items-center justify-between gap-3 border-t border-line px-6 py-4">
				{#if step > 0}<button class="btn btn-ghost" onclick={() => go(step - 1)}>Back</button>{:else}<span></span>{/if}
				{#if step < STEPS.length - 1}
					<button class="btn btn-primary" onclick={() => go(step + 1)}>Next <Icon name="arrow-right" size={15} /></button>
				{:else}
					<button class="btn btn-primary" disabled={busy} onclick={create}>{busy ? 'Adding…' : `Add ${form.name.trim() || 'KPI'}`}</button>
				{/if}
			</footer>
		</div>
	{/if}
</dialog>
