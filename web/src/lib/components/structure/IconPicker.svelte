<script lang="ts">
	import { toast } from '$lib/toast.svelte';
	import type { Unit } from '$lib/types';
	import { iconFor, UNIT_ICONS } from '$lib/unit-icons';
	import Icon from '../Icon.svelte';
	import Popover from '../Popover.svelte';
	import { problem, saveUnit } from './save';

	/** The unit's icon tile; clicking it opens a grid of icons to choose from. */
	let { unit, isGroup, size = 'md' }: { unit: Unit; isGroup: boolean; size?: 'md' | 'lg' } = $props();

	const current = $derived(iconFor(unit, isGroup));
	const tone = $derived(
		unit.parent_id === null ? 'bg-navy text-navy-ink' : isGroup ? 'bg-accent-wash text-accent' : 'bg-surface-2 text-ink-2 ring-1 ring-line'
	);

	async function pick(name: string | null, close: () => void) {
		close();
		try {
			await saveUnit(unit, { icon: name });
		} catch (e) {
			toast(problem(e), 'error');
		}
	}
</script>

<Popover
	label="Choose an icon for {unit.name}"
	width={252}
	triggerClass="grid shrink-0 place-items-center rounded-lg transition-transform hover:scale-105 {tone} {size === 'lg' ? 'size-11' : 'size-8'}"
>
	{#snippet trigger()}
		<Icon name={current} size={size === 'lg' ? 20 : 16} />
	{/snippet}
	{#snippet children(close)}
		<div class="p-2.5">
			<div class="grid grid-cols-6 gap-1">
				{#each UNIT_ICONS as option (option.name)}
					<button
						type="button"
						class="grid size-9 place-items-center rounded-md {option.name === current ? 'bg-accent-wash text-accent ring-1 ring-chart' : 'text-ink-2 hover:bg-surface-2 hover:text-ink'}"
						title={option.label}
						aria-label={option.label}
						aria-pressed={option.name === current}
						onclick={() => pick(option.name, close)}
					>
						<Icon name={option.name} size={17} />
					</button>
				{/each}
			</div>
			{#if unit.icon}
				<button type="button" class="mt-2 w-full rounded-md px-2 py-1.5 text-left text-sm text-ink-2 hover:bg-surface-2" onclick={() => pick(null, close)}>
					Use the default
				</button>
			{/if}
		</div>
	{/snippet}
</Popover>
