<script lang="ts">
	import { onMount } from 'svelte';
	import { api } from '$lib/api';
	import type { Setup } from '$lib/types';
	import Icon from './Icon.svelte';

	/** Getting started, for admins of a new organisation, until everything is done or they hide it. */
	let { shown = $bindable(false) }: { shown?: boolean } = $props();

	let setup = $state<Setup | null>(null);

	onMount(async () => {
		try {
			setup = await api.setup();
		} catch {
			setup = null;
		}
	});

	const items = $derived(
		setup
			? [
					{ done: setup.steps.structure, text: 'Add your branches, departments or teams', hint: 'Skip this if you report as one team.', links: [['Structure', '/admin/units']] },
					{ done: setup.steps.kpis, text: 'Choose the KPIs you track', hint: '', links: [['KPIs', '/admin/kpis']] },
					{ done: setup.steps.targets, text: 'Set targets', hint: 'Status shows on target, close or behind once a KPI has one.', links: [['Targets', '/admin/kpis']] },
					{ done: setup.steps.data, text: 'Enter or import your first figures', hint: '', links: [['Enter data', '/enter'], ['Import a spreadsheet', '/import']] },
					{ done: setup.steps.people, text: 'Invite your team', hint: 'Each person sees and reports for their own part.', links: [['People', '/admin/users']] }
				]
			: []
	);
	const done = $derived(items.filter((i) => i.done).length);
	const visible = $derived(!!setup && !setup.dismissed && done < items.length);
	$effect(() => {
		shown = visible;
	});

	async function hide() {
		setup = await api.dismissSetup();
	}
</script>

{#if visible}
	<section class="panel -mt-5 mb-6 p-5 sm:p-6" aria-labelledby="setup-heading">
		<div class="flex flex-wrap items-center gap-x-6 gap-y-3">
			<h2 id="setup-heading" class="text-md font-semibold">Getting started</h2>
			<div class="flex items-center gap-3 text-sm text-ink-2">
				<span class="h-1.5 w-32 overflow-hidden rounded-full bg-accent-wash" aria-hidden="true">
					<span class="block h-full rounded-full bg-chart" style:width="{(done / items.length) * 100}%"></span>
				</span>
				{done} of {items.length} done
			</div>
			<button class="btn btn-ghost btn-sm ml-auto" onclick={hide}>Hide</button>
		</div>
		<ol class="mt-4 grid gap-2 md:grid-cols-2">
			{#each items as item, i (item.text)}
				<li class="flex gap-3 rounded-lg p-3 {item.done ? '' : 'bg-surface-2'}">
					<span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full text-xs font-semibold {item.done ? 'bg-good text-white' : 'text-ink-2 ring-1 ring-line-strong'}">
						{#if item.done}<Icon name="check" size={12} stroke={3} />{:else}{i + 1}{/if}
					</span>
					<span class="min-w-0">
						<span class="block {item.done ? 'text-ink-2 line-through decoration-line-strong' : 'font-medium'}">{item.text}</span>
						{#if !item.done}
							{#if item.hint}<span class="block text-sm text-ink-2">{item.hint}</span>{/if}
							<span class="mt-1 flex flex-wrap gap-x-4 text-sm">
								{#each item.links as [label, to] (to)}<a class="font-medium text-accent hover:underline" href={to}>{label}</a>{/each}
							</span>
						{/if}
					</span>
				</li>
			{/each}
		</ol>
	</section>
{/if}
