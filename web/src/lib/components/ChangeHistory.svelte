<script lang="ts">
	import { api, ApiError } from '$lib/api';
	import { formatValue } from '$lib/format';
	import type { Kpi, ValueChange } from '$lib/types';

	/** Who changed this KPI's figures in the unit and the units inside it, newest first. */
	let { kpi, unitId, showUnit }: { kpi: Kpi; unitId: number; showUnit: boolean } = $props();

	const SHOWN = 8;
	let changes = $state<ValueChange[] | null>(null);
	let error = $state('');
	let all = $state(false);

	$effect(() => {
		changes = null;
		all = false;
		api.history(kpi.id, unitId).then(
			(found) => (changes = found),
			(e) => (error = e instanceof ApiError ? e.message : 'Could not load the history')
		);
	});

	const visible = $derived(changes ? (all ? changes : changes.slice(0, SHOWN)) : []);
	const when = (at: string) =>
		new Date(at.replace(' ', 'T')).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
</script>

<section class="panel mt-6 min-w-0 p-5 sm:p-6" aria-labelledby="history-heading">
	<h2 id="history-heading" class="text-md font-semibold">Recent changes</h2>
	<p class="mt-1 text-sm text-ink-2">Every figure entered, corrected or deleted, and who did it.</p>
	{#if error}
		<p class="mt-4 text-sm text-bad-ink">{error}</p>
	{:else if changes && !changes.length}
		<p class="mt-4 text-sm text-ink-2">No figures have been entered yet.</p>
	{:else if changes}
		<div class="mt-4 overflow-x-auto">
			<table class="w-full min-w-[560px] text-left text-sm">
				<thead class="text-ink-2">
					<tr class="border-b border-line">
						<th scope="col" class="py-2 pr-4 font-medium">When</th>
						{#if showUnit}<th scope="col" class="py-2 pr-4 font-medium">Unit</th>{/if}
						<th scope="col" class="py-2 pr-4 font-medium">Period</th>
						<th scope="col" class="py-2 pr-4 font-medium">Change</th>
						<th scope="col" class="py-2 font-medium">By</th>
					</tr>
				</thead>
				<tbody>
					{#each visible as c (c.id)}
						<tr class="border-b border-line last:border-b-0">
							<td class="py-2 pr-4 whitespace-nowrap text-ink-2">{when(c.changed_at)}</td>
							{#if showUnit}<td class="py-2 pr-4">{c.unit_name}</td>{/if}
							<td class="py-2 pr-4 whitespace-nowrap">{c.period_label}</td>
							<td class="num py-2 pr-4 whitespace-nowrap">
								{#if c.old_value === null}
									<span class="text-ink-2">Entered</span> {formatValue(kpi, c.new_value)}
								{:else if c.new_value === null}
									<span class="text-ink-2">Deleted</span> <s class="text-ink-2">{formatValue(kpi, c.old_value)}</s>
								{:else if c.old_value === c.new_value}
									<span class="text-ink-2">Note changed</span>
								{:else}
									{formatValue(kpi, c.old_value)} <span class="text-ink-2" aria-label="to">→</span> {formatValue(kpi, c.new_value)}
								{/if}
							</td>
							<td class="py-2">
								{c.changed_by ?? 'A deleted account'}
								{#if c.import_file}<span class="text-ink-2"> · import of {c.import_file}</span>{/if}
							</td>
						</tr>
					{/each}
				</tbody>
			</table>
		</div>
		{#if changes.length > SHOWN && !all}
			<button class="btn btn-ghost btn-sm mt-3 -ml-2.5" onclick={() => (all = true)}>Show all {changes.length}</button>
		{/if}
	{:else}
		<p class="mt-4 text-sm text-ink-2" aria-live="polite">Loading…</p>
	{/if}
</section>
