<script lang="ts">
	import { slide } from 'svelte/transition';
	import { api, ApiError } from '$lib/api';
	import { formFromKpi, toBody, type KpiForm, type TargetRow } from '$lib/kpi-form';
	import { problem } from '$lib/problem';
	import { toast } from '$lib/toast.svelte';
	import type { Kpi } from '$lib/types';
	import Icon from '../Icon.svelte';
	import Tooltip from '../Tooltip.svelte';
	import KpiBasics from './KpiBasics.svelte';
	import KpiGoal from './KpiGoal.svelte';
	import KpiReach from './KpiReach.svelte';
	import KpiRollup from './KpiRollup.svelte';
	import { exampleNames, fieldErrors, kpiIcon, weightChoicesFor } from './shared';

	/**
	 * Everything about one KPI, in the same sections as the new-KPI guide. Changes save together
	 * from the bar that appears once anything differs, because the rules change how every
	 * total is worked out.
	 */
	let { kpi, kpis, onClose, onSaved }: { kpi: Kpi; kpis: Kpi[]; onClose: () => void; onSaved: () => Promise<void> } = $props();

	let form = $state<KpiForm>(formFromKpi(kpi));
	let targets = $state<TargetRow[]>([]);
	let savedTargets = $state('[]');
	let errors = $state<Record<string, string>>({});
	let busy = $state(false);
	let seq = 0;
	const nextKey = () => seq++;

	const plain = (rows: TargetRow[]) => JSON.stringify(rows.map(({ unit_id, effective_from, value }) => ({ unit_id, effective_from, value: Number(value) })));

	// Another KPI opened: start from its saved values
	$effect(() => {
		void kpi.id;
		reset();
		api.targets(kpi.id).then((rows) => {
			targets = rows.map((t) => ({ ...t, key: nextKey() }));
			savedTargets = plain(targets);
		});
	});

	function reset() {
		form = formFromKpi(kpi);
		errors = {};
		const kept = JSON.parse(savedTargets) as TargetRow[];
		targets = kept.map((t) => ({ ...t, key: nextKey() }));
	}

	const dirty = $derived(JSON.stringify(form) !== JSON.stringify(formFromKpi(kpi)) || plain(targets) !== savedTargets);

	async function save() {
		busy = true;
		errors = {};
		try {
			await api.updateKpi(kpi.id, toBody(form));
			await api.saveTargets(kpi.id, JSON.parse(plain(targets)));
			savedTargets = plain(targets);
			toast(`Saved ${form.name}`);
			await onSaved();
		} catch (e) {
			if (e instanceof ApiError && Object.keys(e.details).length) errors = fieldErrors(e.details);
			toast(problem(e), 'error');
		} finally {
			busy = false;
		}
	}

</script>

<div class="flex h-full flex-col">
	<header class="flex items-center gap-3 border-b border-line px-5 py-4">
		<span class="grid size-11 shrink-0 place-items-center rounded-lg bg-accent-wash text-accent"><Icon name={kpiIcon(kpi)} size={20} /></span>
		<div class="min-w-0 flex-1">
			<p class="text-xs font-semibold tracking-wide text-muted uppercase">KPI{kpi.is_active ? '' : ' · paused'}</p>
			<h2 class="truncate text-lg font-semibold tracking-[-0.01em]">{kpi.name}</h2>
		</div>
		<Tooltip text="Close" side="bottom">
			<button class="rounded-md p-1.5 text-muted hover:bg-surface-2 hover:text-ink" onclick={onClose} aria-label="Close"><Icon name="x" size={18} /></button>
		</Tooltip>
	</header>

	<div class="flex-1 space-y-8 overflow-x-hidden overflow-y-auto px-5 py-5">
		<section aria-label="Basics"><KpiBasics bind:form {errors} existing /></section>
		<section class="border-t border-line pt-6" aria-labelledby="k-rollup">
			<h3 id="k-rollup" class="mb-4 font-semibold">How it adds up</h3>
			<KpiRollup bind:form weightChoices={weightChoicesFor(kpis, form, kpi.id)} names={exampleNames()} {errors} />
		</section>
		<section class="border-t border-line pt-6" aria-labelledby="k-goal">
			<h3 id="k-goal" class="mb-4 font-semibold">What's good</h3>
			<KpiGoal bind:form bind:targets {nextKey} {errors} />
		</section>
		<section class="border-t border-line pt-6" aria-labelledby="k-reach">
			<h3 id="k-reach" class="mb-4 font-semibold">Who reports it</h3>
			<KpiReach bind:form {errors} />
		</section>
		<section class="border-t border-line pt-6">
			<label class="flex cursor-pointer items-start justify-between gap-4 rounded-lg bg-surface-2 px-3.5 py-3 ring-1 ring-line">
				<span>
					<span class="block text-sm font-medium">{form.is_active ? 'In use' : 'Paused'}</span>
					<span class="block text-xs text-ink-2">Paused, it leaves the scorecard and data entry. Its figures and history are kept.</span>
				</span>
				<input type="checkbox" role="switch" class="peer sr-only" bind:checked={form.is_active} />
				<span class="relative mt-0.5 h-5 w-9 shrink-0 rounded-full bg-line-strong transition-colors peer-checked:bg-chart peer-focus-visible:ring-2 peer-focus-visible:ring-chart after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white after:transition-transform peer-checked:after:translate-x-4" aria-hidden="true"></span>
			</label>
		</section>
	</div>

	{#if dirty}
		<footer class="flex items-center gap-2 border-t border-line bg-surface px-5 py-3" transition:slide={{ duration: 180 }}>
			<span class="mr-auto text-sm text-ink-2">Unsaved changes</span>
			<button class="btn btn-ghost" onclick={reset} disabled={busy}>Undo</button>
			<button class="btn btn-primary" onclick={save} disabled={busy || !form.name.trim()}>{busy ? 'Saving…' : 'Save changes'}</button>
		</footer>
	{/if}
</div>
