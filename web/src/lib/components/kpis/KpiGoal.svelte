<script lang="ts">
	import { slide } from 'svelte/transition';
	import { formatValue } from '$lib/format';
	import { ALWAYS, mainTarget, statusBands, withMainTarget, type KpiForm, type TargetRow } from '$lib/kpi-form';
	import { GRAIN_NAMES } from '$lib/period';
	import { CURRENCIES } from '$lib/starter-kpis';
	import { term } from '$lib/terms.svelte';
	import { units } from '$lib/units.svelte';
	import Icon from '../Icon.svelte';
	import OptionCards from '../OptionCards.svelte';
	import StatusMark from '../StatusMark.svelte';

	/**
	 * What counts as good: the direction, the target, how close is "close", and how the figure
	 * is written. Targets that change over time or differ per unit sit under "More targets".
	 */
	let {
		form = $bindable(),
		targets = $bindable(),
		nextKey,
		errors = {}
	}: { form: KpiForm; targets: TargetRow[]; nextKey: () => number; errors?: Record<string, string> } = $props();

	const main = $derived(mainTarget(targets));
	const others = $derived(targets.filter((t) => t !== main));
	const per = $derived(GRAIN_NAMES[form.frequency].noun.toLowerCase());
	const fmt = (n: number) => formatValue(form, n);
	let more = $state(false);

	function setMain(raw: string) {
		const value = raw.trim() === '' ? null : Number(raw);
		if (value === null || Number.isFinite(value)) targets = withMainTarget(targets, value, nextKey());
	}

	// What on target, close and behind mean for this target and tolerance
	const bands = $derived(main ? statusBands(form.direction, main.value, form.tolerance_pct, fmt) : null);
	const errorsOfTargets = $derived(Object.entries(errors).filter(([k]) => k.startsWith('targets')).map(([, m]) => m));
</script>

<div class="space-y-6">
	<OptionCards
		name="k-direction"
		legend="What's good?"
		bind:value={form.direction}
		options={[
			{ value: 'higher_is_better', label: 'Higher', hint: 'More is better: sales, satisfaction.', icon: 'arrow-up' },
			{ value: 'lower_is_better', label: 'Lower', hint: 'Less is better: costs, complaints.', icon: 'arrow-down' },
			{ value: 'none', label: 'Just track it', hint: 'Show the figure, no verdict.', icon: 'minus' }
		]}
	/>

	{#if form.direction !== 'none'}
		<div class="grid gap-5 sm:grid-cols-2" transition:slide={{ duration: 180 }}>
			<div>
				<label class="field-label" for="k-target">Target <span class="font-normal text-muted">per {term.one}, per {per}</span></label>
				<div class="flex items-center rounded-lg ring-1 ring-line-strong focus-within:ring-2 focus-within:ring-chart">
					{#if form.prefix}<span class="pl-3 text-muted">{form.prefix}</span>{/if}
					<input id="k-target" class="num min-w-0 flex-1 bg-transparent px-3 py-2 outline-none" type="number" step="any" placeholder="Optional" value={main?.value ?? ''} oninput={(e) => setMain(e.currentTarget.value)} />
					{#if form.suffix}<span class="pr-3 text-muted">{form.suffix}</span>{/if}
				</div>
				<p class="field-hint">Leave empty to set it later.</p>
			</div>
			<div>
				<label class="field-label" for="k-tol">Close means within <strong class="num text-ink">{form.tolerance_pct}%</strong></label>
				<input id="k-tol" class="mt-2 w-full accent-[var(--accent)]" type="range" min="0" max="50" step="1" bind:value={form.tolerance_pct} />
				<p class="field-hint">of the target</p>
			</div>
		</div>
		{#if bands}
			<ul class="grid gap-1.5 rounded-xl bg-surface-2 p-3 text-sm ring-1 ring-line sm:grid-cols-3" transition:slide={{ duration: 180 }} aria-label="What each status means">
				<li class="flex items-center gap-2"><StatusMark status="green" /> <span><strong class="font-medium">On target</strong> <span class="block text-xs text-ink-2">{bands.good}</span></span></li>
				<li class="flex items-center gap-2"><StatusMark status="amber" /> <span><strong class="font-medium">Close</strong> <span class="block text-xs text-ink-2">{bands.close}</span></span></li>
				<li class="flex items-center gap-2"><StatusMark status="red" /> <span><strong class="font-medium">Behind</strong> <span class="block text-xs text-ink-2">{bands.bad}</span></span></li>
			</ul>
		{/if}
	{/if}

	<fieldset>
		<legend class="field-label">Written as <span class="font-normal text-muted">e.g. {fmt(1234.5678)}</span></legend>
		<div class="flex flex-wrap gap-3">
			<label class="flex items-center gap-2 text-sm text-ink-2">
				Before
				<select class="input h-9 w-20" bind:value={form.prefix}>
					{#each CURRENCIES as c (c)}<option value={c}>{c || 'None'}</option>{/each}
				</select>
			</label>
			<label class="flex items-center gap-2 text-sm text-ink-2">After <input class="input h-9 w-20" maxlength="10" placeholder="%" bind:value={form.suffix} /></label>
			<label class="flex items-center gap-2 text-sm text-ink-2">Decimals <input class="input num h-9 w-16" type="number" min="0" max="6" bind:value={form.decimals} /></label>
		</div>
	</fieldset>

	{#if form.direction !== 'none'}
		<section>
			<button type="button" class="flex items-center gap-1.5 text-sm font-medium text-accent" onclick={() => (more = !more)} aria-expanded={more || others.length > 0}>
				<Icon name="chevron-down" size={14} class="transition-transform {more || others.length ? '' : '-rotate-90'}" />
				More targets{others.length ? ` (${others.length})` : ''}: changing over time, or different for some {term.many}
			</button>
			{#if more || others.length}
				<div class="mt-3 space-y-2" transition:slide={{ duration: 180 }}>
					{#each others as t (t.key)}
						<div class="flex flex-wrap items-center gap-2">
							<select class="input h-9 min-w-0 flex-1" bind:value={t.unit_id} aria-label="Applies to">
								<option value={null}>Every {term.one}</option>
								{#each units.list.filter((u) => u.is_active && u.parent_id !== null) as u (u.id)}<option value={u.id}>{u.name}</option>{/each}
							</select>
							<label class="flex items-center gap-2 text-sm text-ink-2">from <input class="input h-9 w-40" type="date" bind:value={t.effective_from} /></label>
							<input class="input num h-9 w-28 text-right" type="number" step="any" bind:value={t.value} aria-label="Target" />
							<button type="button" class="rounded p-1.5 text-muted hover:bg-bad-wash hover:text-bad-ink" aria-label="Remove this target" onclick={() => (targets = targets.filter((x) => x.key !== t.key))}>
								<Icon name="x" size={15} />
							</button>
						</div>
					{/each}
					<button type="button" class="btn btn-ghost btn-sm -ml-2.5" onclick={() => (targets = [...targets, { key: nextKey(), unit_id: units.list.find((u) => u.is_leaf && u.parent_id !== null)?.id ?? null, effective_from: ALWAYS, value: main?.value ?? 0 }])}>
						<Icon name="plus" size={14} /> Add a target
					</button>
					<p class="field-hint">A target applies from its date until a later one replaces it. Groups add targets up the same way as figures.</p>
				</div>
			{/if}
			{#each errorsOfTargets as message (message)}<p class="field-error">{message}</p>{/each}
		</section>
	{/if}
</div>
