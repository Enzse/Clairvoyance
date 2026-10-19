<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import { api, ApiError } from '$lib/api';
	import { formatValue, timeAgo } from '$lib/format';
	import type { Kpi, ValueChange } from '$lib/types';
	import Icon from './Icon.svelte';
	import Avatar from './people/Avatar.svelte';

	/** Who changed this KPI's figures in the unit and the units inside it, newest first. */
	let { kpi, unitId, showUnit }: { kpi: Kpi; unitId: number; showUnit: boolean } = $props();

	const SHOWN = 8;
	let changes = $state<ValueChange[] | null>(null);
	let error = $state('');
	let all = $state(false);
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	$effect(() => {
		changes = null;
		all = false;
		api.history(kpi.id, unitId).then(
			(found) => (changes = found),
			(e) => (error = e instanceof ApiError ? e.message : 'Could not load the history')
		);
	});

	const visible = $derived(changes ? (all ? changes : changes.slice(0, SHOWN)) : []);
	const exact = (at: string) => new Date(at.replace(' ', 'T')).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
</script>

<section class="panel mt-6 min-w-0 p-5 sm:p-6" aria-labelledby="history-heading">
	<div class="flex items-start gap-3">
		<span class="grid size-9 shrink-0 place-items-center rounded-lg bg-accent-wash text-accent"><Icon name="history" size={17} /></span>
		<div>
			<h2 id="history-heading" class="text-md font-semibold">Recent changes</h2>
			<p class="text-sm text-ink-2">Every figure entered, corrected or deleted, and who did it.</p>
		</div>
	</div>
	{#if error}
		<p class="mt-4 text-sm text-bad-ink">{error}</p>
	{:else if changes && !changes.length}
		<p class="mt-4 rounded-lg border border-dashed border-line-strong px-4 py-3 text-sm text-ink-2">No figures have been entered yet.</p>
	{:else if changes}
		<ol class="mt-5 space-y-1">
			{#each visible as c, i (c.id)}
				{@const who = c.changed_by ?? 'A deleted account'}
				<li class="flex items-start gap-3 rounded-lg px-2 py-2 hover:bg-surface-2" in:fly={{ y: 6 * move, duration: 240, delay: Math.min(i, 8) * 30 * move, easing: cubicOut }}>
					<Avatar name={who} email={who} size="sm" />
					<div class="min-w-0 flex-1 text-sm">
						<p>
							<span class="font-medium">{who}</span>
							{#if c.old_value === null}
								entered <strong class="num font-semibold">{formatValue(kpi, c.new_value)}</strong>
							{:else if c.new_value === null}
								deleted <s class="num text-ink-2">{formatValue(kpi, c.old_value)}</s>
							{:else if c.old_value === c.new_value}
								changed the note
							{:else}
								changed <span class="num text-ink-2">{formatValue(kpi, c.old_value)}</span> <span class="text-muted" aria-label="to">→</span> <strong class="num font-semibold">{formatValue(kpi, c.new_value)}</strong>
							{/if}
						</p>
						<p class="text-xs text-muted">
							{showUnit ? `${c.unit_name} · ` : ''}{c.period_label}{c.import_file ? ` · from ${c.import_file}` : ''}
						</p>
					</div>
					<time class="shrink-0 text-xs text-muted" datetime={c.changed_at.replace(' ', 'T')} title={exact(c.changed_at)}>{timeAgo(c.changed_at)}</time>
				</li>
			{/each}
		</ol>
		{#if changes.length > SHOWN && !all}
			<button class="btn btn-ghost btn-sm mt-3 -ml-2.5" onclick={() => (all = true)}>Show all {changes.length}</button>
		{/if}
	{:else}
		<p class="mt-4 text-sm text-ink-2" aria-live="polite">Loading…</p>
	{/if}
</section>
