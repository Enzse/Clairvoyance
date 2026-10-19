<script lang="ts">
	import { onMount } from 'svelte';
	import { api, ApiError } from '$lib/api';
	import Icon from '$lib/components/Icon.svelte';
	import { plural } from '$lib/format';
	import { AREAS, STARTER_KPIS, type StarterKpi } from '$lib/starter-kpis';
	import type { Kpi } from '$lib/types';
	import { term } from '$lib/terms.svelte';
	import { units } from '$lib/units.svelte';

	/**
	 * Pick from common business KPIs. With departments or locations, each chosen KPI can be
	 * limited to the units that report it (revenue → Sales only).
	 */
	let { currency, onDone, onSkip }: { currency: string; onDone: () => void; onSkip: () => void } = $props();

	const root = $derived(units.home());
	const topUnits = $derived(root ? units.children(root.id) : []);

	let existing = $state<Kpi[]>([]);
	let selected = $state(new Set<string>(['revenue', 'expenses', 'customer_satisfaction']));
	let scope = $state<Record<string, number | ''>>({});
	let error = $state('');
	let busy = $state(false);

	onMount(async () => {
		existing = await api.kpis(true);
		if (existing.length) selected = new Set();
	});

	const existingCodes = $derived(new Set(existing.map((k) => k.code)));

	function toggle(key: string) {
		const next = new Set(selected);
		if (next.has(key)) next.delete(key);
		else next.add(key);
		selected = next;
	}

	/** Weight KPIs are created before the KPIs weighted by them. */
	function ordered(): StarterKpi[] {
		const chosen = STARTER_KPIS.filter((k) => selected.has(k.key) && !existingCodes.has(k.key));
		return [...chosen.filter((k) => !k.weightKey), ...chosen.filter((k) => k.weightKey)];
	}

	async function submit(event: SubmitEvent) {
		event.preventDefault();
		busy = true;
		error = '';
		const ids = new Map(existing.map((k) => [k.code, k.id]));
		try {
			for (const t of ordered()) {
				const weightId = t.weightKey ? ids.get(t.weightKey) : undefined;
				const kpi = await api.createKpi({
					code: t.key,
					name: t.name,
					description: t.description,
					frequency: 'monthly',
					time_aggregation: t.time_aggregation,
					// Without its weight KPI, a weighted average falls back to a plain one
					unit_aggregation: t.unit_aggregation === 'weighted_average' && !weightId ? 'average' : t.unit_aggregation,
					weight_kpi_id: weightId ?? null,
					direction: t.direction,
					tolerance_pct: t.tolerance_pct,
					decimals: t.decimals,
					prefix: t.money ? currency : '',
					suffix: t.suffix ?? '',
					scope_unit_ids: scope[t.key] ? [Number(scope[t.key])] : []
				});
				ids.set(kpi.code, kpi.id);
			}
			onDone();
		} catch (e) {
			error = e instanceof ApiError ? Object.values(e.details)[0]?.toString() ?? e.message : 'Could not create the KPIs';
			existing = await api.kpis(true);
		} finally {
			busy = false;
		}
	}

	const count = $derived(ordered().length);
</script>

<form class="panel -mt-6 p-6 sm:p-8" onsubmit={submit}>
	{#if existing.length}
		<p class="mb-6 rounded-lg bg-surface-2 p-4 text-sm text-ink-2">
			{plural(existing.length, 'KPI')} already set up. Add more here, or continue.
		</p>
	{/if}

	<div class="grid gap-x-8 gap-y-7 md:grid-cols-2">
		{#each AREAS as area (area)}
			<fieldset>
				<legend class="mb-2 font-semibold">{area}</legend>
				<div class="space-y-2">
					{#each STARTER_KPIS.filter((k) => k.area === area) as t (t.key)}
						{@const done = existingCodes.has(t.key)}
						{@const on = selected.has(t.key) || done}
						<div class="rounded-lg ring-1 {on ? 'bg-accent-wash ring-chart' : 'ring-line hover:bg-surface-2'} {done ? 'opacity-70' : ''}">
							<label class="flex cursor-pointer items-start gap-3 p-3">
								<input type="checkbox" class="mt-1 size-4 accent-[var(--accent)]" checked={on} disabled={done} onchange={() => toggle(t.key)} />
								<span class="min-w-0">
									<span class="block font-medium">{t.name}{t.money && currency ? ` (${currency})` : t.suffix ? ` (${t.suffix})` : ''}</span>
									<span class="block text-sm text-ink-2">{t.description}</span>
									<span class="mt-0.5 block text-xs text-muted">{done ? 'Already added' : t.summary.replace('{many}', term.many)}</span>
								</span>
							</label>
							{#if on && !done && topUnits.length > 1}
								<label class="flex items-center gap-2 border-t border-line px-3 py-2 text-sm text-ink-2">
									Reported by
									<select class="input h-8 flex-1 py-0 text-sm" bind:value={scope[t.key]}>
										<option value="">Everyone</option>
										{#each topUnits as u (u.id)}<option value={u.id}>{u.name} only</option>{/each}
									</select>
								</label>
							{/if}
						</div>
					{/each}
				</div>
			</fieldset>
		{/each}
	</div>

	<p class="mt-6 flex items-start gap-2 text-sm text-ink-2">
		<Icon name="pencil" size={14} class="mt-0.5 shrink-0 text-muted" />
		Need something else? Create your own KPIs under Admin › KPIs once you're set up.
	</p>

	{#if error}<p class="mt-4 text-sm text-bad-ink" role="alert">{error}</p>{/if}

	<div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-line pt-5">
		<button type="button" class="btn btn-ghost" onclick={onSkip}>Skip for now</button>
		<button class="btn btn-primary" disabled={busy}>
			{busy ? 'Adding…' : count ? `Add ${plural(count, 'KPI')}` : 'Continue'}
		</button>
	</div>
</form>
