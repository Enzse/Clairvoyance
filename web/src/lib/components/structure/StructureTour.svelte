<script lang="ts">
	import { session } from '$lib/session.svelte';
	import { term } from '$lib/terms.svelte';
	import Icon, { type IconName } from '../Icon.svelte';
	import Tour, { type TourStep } from '../Tour.svelte';

	/** The introduction to Manage › Structure: what it is, what a unit is, and how to work with it. */
	let { open = $bindable(false), onClose }: { open?: boolean; onClose?: () => void } = $props();

	const org = $derived(session.user?.organization?.name ?? 'Your organisation');

	const steps: TourStep[] = $derived([
		{
			title: 'This is your structure',
			body: `It shows how ${org} is organised: who reports figures, and how those figures add up to the whole organisation.`,
			art: treeArt
		},
		{
			title: 'Everything here is a unit',
			body: `A unit is any part of your organisation: a ${term.one}, a department, a region. Units at the bottom report their own figures. Units with others inside them are groups, and add those figures up for you.`,
			art: unitArt
		},
		{
			title: 'Change things in place',
			body: 'Click a name to rename it, the small text under it to change its term, or the icon to pick another. "Add inside" adds units; type a name and press Enter, or paste a list to add many at once.',
			art: editArt
		},
		{
			title: 'Group them as you grow',
			body: `"New group" makes a group, like a region, then lets you move ${term.many} into it. Open any unit's details to move it, give it a code for spreadsheet imports, or switch it off.`,
			art: groupArt
		}
	]);
</script>

{#snippet tile(icon: IconName, tone: string)}
	<span class="grid size-7 shrink-0 place-items-center rounded-md {tone}"><Icon name={icon} size={14} /></span>
{/snippet}

{#snippet row(icon: IconName, tone: string, name: string, sub: string, indent = 0)}
	<div class="flex items-center gap-2 rounded-lg bg-surface px-2 py-1.5 shadow-sm ring-1 ring-line" style:margin-left="{indent * 22}px">
		{@render tile(icon, tone)}
		<span class="leading-tight">
			<span class="block text-sm font-medium">{name}</span>
			<span class="block text-[11px] text-muted">{sub}</span>
		</span>
	</div>
{/snippet}

{#snippet treeArt()}
	<div class="w-64 space-y-1.5">
		{@render row('building', 'bg-navy text-navy-ink', org, 'adds up everything')}
		{@render row('folder', 'bg-accent-wash text-accent', 'North region', 'Group · adds up 2', 1)}
		{@render row('store', 'bg-surface-2 text-ink-2 ring-1 ring-line', 'Downtown', `${term.One} · reports figures`, 2)}
		{@render row('store', 'bg-surface-2 text-ink-2 ring-1 ring-line', 'Airport', `${term.One} · reports figures`, 2)}
	</div>
{/snippet}

{#snippet unitArt()}
	<div class="flex items-center gap-4">
		<div class="space-y-1.5">
			{#each [['Downtown', '120'], ['Airport', '95'], ['Harbour', '80']] as [name, value] (name)}
				<div class="flex items-center gap-2 rounded-lg bg-surface px-2 py-1.5 shadow-sm ring-1 ring-line">
					{@render tile('pin', 'bg-surface-2 text-ink-2 ring-1 ring-line')}
					<span class="w-16 text-sm font-medium">{name}</span>
					<span class="num text-sm text-ink-2">{value}</span>
				</div>
			{/each}
		</div>
		<Icon name="arrow-right" size={20} class="text-muted" />
		<div class="flex flex-col items-center rounded-xl bg-surface px-4 py-3 shadow-sm ring-1 ring-line">
			{@render tile('folder', 'bg-accent-wash text-accent')}
			<span class="mt-1.5 text-xs text-muted">Group adds up</span>
			<span class="num text-xl font-semibold">295</span>
		</div>
	</div>
{/snippet}

{#snippet editArt()}
	<div class="w-72 pt-6">
		<div class="flex items-center gap-2 rounded-lg bg-accent-wash px-2 py-2 ring-1 ring-chart/40">
			<span class="relative">
				{@render tile('coffee', 'bg-surface-2 text-ink-2 ring-1 ring-line')}
				<span class="absolute -top-7 left-1/2 -translate-x-1/2 rounded bg-navy px-1.5 py-0.5 text-[10px] whitespace-nowrap text-navy-ink">Change icon</span>
			</span>
			<span class="leading-tight">
				<span class="block rounded px-1 text-sm font-medium ring-2 ring-chart">Downtown<span class="animate-pulse">|</span></span>
				<span class="block px-1 text-[11px] text-muted">{term.One} · reports figures</span>
			</span>
			<span class="ml-auto flex items-center gap-1 rounded-md px-1.5 py-1 text-xs text-ink-2 ring-1 ring-line"><Icon name="plus" size={12} /> Add inside</span>
		</div>
		<div class="mt-2 ml-9 flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-muted ring-1 ring-line-strong">
			<Icon name="plus" size={13} /> Airport<span class="animate-pulse text-ink">|</span>
		</div>
	</div>
{/snippet}

{#snippet groupArt()}
	<div class="flex items-center gap-4">
		<div class="space-y-1.5">
			<span class="flex items-center gap-1.5 rounded-lg bg-surface px-3 py-1.5 text-sm font-medium shadow-sm ring-1 ring-line-strong"><Icon name="folder" size={14} /> New group</span>
			<span class="flex items-center gap-1.5 rounded-lg bg-surface px-3 py-1.5 text-sm shadow-sm ring-1 ring-line"><Icon name="sliders" size={14} /> Details</span>
		</div>
		<Icon name="arrow-right" size={20} class="text-muted" />
		<div class="w-44 space-y-1.5">
			{@render row('map', 'bg-accent-wash text-accent', 'North region', 'Group · adds up 2')}
			{@render row('store', 'bg-surface-2 text-ink-2 ring-1 ring-line', 'Downtown', 'moved in', 1)}
			{@render row('store', 'bg-surface-2 text-ink-2 ring-1 ring-line', 'Airport', 'moved in', 1)}
		</div>
	</div>
{/snippet}

<Tour bind:open {steps} label="How Structure works" {onClose} />
