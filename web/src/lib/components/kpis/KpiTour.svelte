<script lang="ts">
	import { term } from '$lib/terms.svelte';
	import Icon from '../Icon.svelte';
	import StatusMark from '../StatusMark.svelte';
	import Tour, { type TourStep } from '../Tour.svelte';

	/** The introduction to Manage › KPIs: what a KPI is, how it adds up, targets, and editing. */
	let { open = $bindable(false), onClose }: { open?: boolean; onClose?: () => void } = $props();

	const steps: TourStep[] = $derived([
		{
			title: 'These are your KPIs',
			body: `A KPI is one number you track to see how things are going, like sales, attendance or complaints. Each ${term.one} reports it, and the scorecard shows how everyone is doing.`,
			art: cardsArt
		},
		{
			title: 'Say once how it adds up',
			body: `Some numbers are added up (sales), some are averaged (satisfaction), some only the latest counts (headcount). Choose once, and every total across months and ${term.many} comes out right.`,
			art: rollupArt
		},
		{
			title: "Set what's good",
			body: 'Say whether higher or lower is better and give a target. Each figure then shows as on target, close or behind, and you choose how close counts as close.',
			art: goalArt
		},
		{
			title: 'Add and change them any time',
			body: `"New KPI" walks you through it in four short steps, or start from a common example. Click any KPI to change it, limit it to some ${term.many}, or pause it. Its history is kept.`,
			art: editArt
		}
	]);
</script>

{#snippet card(icon: 'coins' | 'percent' | 'hash', name: string, chip: string, tilt = '')}
	<div class="w-40 rounded-xl bg-surface p-3 shadow-sm ring-1 ring-line {tilt}">
		<span class="grid size-7 place-items-center rounded-md bg-accent-wash text-accent"><Icon name={icon} size={14} /></span>
		<p class="mt-2 text-sm font-semibold">{name}</p>
		<span class="mt-2 inline-flex items-center gap-1 rounded-full bg-good-wash px-2 py-0.5 text-[11px] font-medium text-good-ink">{chip}</span>
	</div>
{/snippet}

{#snippet cardsArt()}
	<div class="flex items-center -space-x-6">
		{@render card('coins', 'Sales', 'Target 50,000', '-rotate-3')}
		{@render card('percent', 'Satisfaction', 'Target 90%', 'relative z-10 -translate-y-2')}
		{@render card('hash', 'Complaints', 'Target ≤ 5', 'rotate-3')}
	</div>
{/snippet}

{#snippet rollupArt()}
	<div class="grid grid-cols-3 gap-2 text-center text-sm">
		{#each [['Added up', '120 + 80', '200'], ['Averaged', '(90 + 70) ÷ 2', '80'], ['Latest', 'Jan 40 · Feb 42', '42']] as [rule, sum, total] (rule)}
			<div class="rounded-xl bg-surface px-3 py-2.5 shadow-sm ring-1 ring-line">
				<p class="text-xs font-medium text-accent">{rule}</p>
				<p class="num mt-1 text-[11px] text-muted">{sum}</p>
				<p class="num text-lg font-semibold">{total}</p>
			</div>
		{/each}
	</div>
{/snippet}

{#snippet goalArt()}
	<div class="w-64 space-y-1.5">
		{#each [['green', 'Downtown', '104', 'On target'], ['amber', 'Airport', '93', 'Close'], ['red', 'Harbour', '71', 'Behind']] as [status, name, value, label] (name)}
			<div class="flex items-center gap-2 rounded-lg bg-surface px-3 py-1.5 shadow-sm ring-1 ring-line">
				<StatusMark status={status as 'green' | 'amber' | 'red'} />
				<span class="flex-1 text-sm font-medium">{name}</span>
				<span class="num text-sm">{value}</span>
				<span class="w-16 text-right text-[11px] text-muted">{label}</span>
			</div>
		{/each}
		<p class="pt-1 text-center text-[11px] text-muted">Target 100 · close within 10%</p>
	</div>
{/snippet}

{#snippet editArt()}
	<div class="flex items-center gap-4">
		<div class="space-y-1.5">
			<span class="flex items-center gap-1.5 rounded-lg bg-navy px-3 py-1.5 text-sm font-medium text-navy-ink shadow-sm"><Icon name="plus" size={14} /> New KPI</span>
			<div class="flex gap-1">
				{#each [0, 1, 2, 3] as i (i)}<span class="h-1.5 w-6 rounded-full {i < 2 ? 'bg-chart' : 'bg-line-strong'}"></span>{/each}
			</div>
		</div>
		<Icon name="arrow-right" size={20} class="text-muted" />
		<div class="w-44 rounded-xl bg-surface p-3 shadow-sm ring-2 ring-chart">
			<p class="text-sm font-semibold">Sales</p>
			<p class="mt-1 text-[11px] text-muted">Added up over time and across {term.many}</p>
			<span class="mt-2 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] text-ink-2 ring-1 ring-line"><Icon name="pause" size={11} /> Pause</span>
		</div>
	</div>
{/snippet}

<Tour bind:open {steps} label="How KPIs work" {onClose} />
