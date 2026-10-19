<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly } from 'svelte/transition';
	import type { Status } from '$lib/types';
	import Icon from '../Icon.svelte';
	import ProgressRing from '../ProgressRing.svelte';
	import StatusMark from '../StatusMark.svelte';

	/** An illustration of a scorecard filling in, for the sign-in screens. The figures are made up. */
	const ROWS: { name: string; value: string; status: Status; bar: number }[] = [
		{ name: 'Sales', value: '₱52,400', status: 'green', bar: 92 },
		{ name: 'Customer satisfaction', value: '91%', status: 'green', bar: 84 },
		{ name: 'Complaints', value: '7', status: 'amber', bar: 58 },
		{ name: 'On-time delivery', value: '83%', status: 'red', bar: 41 }
	];
	const move = $derived(prefersReducedMotion.current ? 0 : 1);
</script>

<div class="relative w-[340px]" aria-hidden="true">
	<div class="animate-[float_7s_ease-in-out_infinite] rounded-2xl bg-surface p-5 text-ink shadow-pop motion-reduce:animate-none">
		<div class="flex items-center gap-3">
			<ProgressRing value={4} total={5} from={0} size={46} />
			<div>
				<p class="text-sm font-semibold">October</p>
				<p class="text-xs text-ink-2">4 of 5 branches have reported</p>
			</div>
		</div>
		<ul class="mt-4 space-y-2.5">
			{#each ROWS as row, i (row.name)}
				<li class="flex items-center gap-2.5" in:fly={{ x: 12 * move, duration: 400, delay: 300 + i * 140 * move, easing: cubicOut }}>
					<StatusMark status={row.status} size={13} />
					<span class="min-w-0 flex-1">
						<span class="flex justify-between gap-2 text-xs"><span class="truncate text-ink-2">{row.name}</span><span class="num font-semibold">{row.value}</span></span>
						<span class="mt-1 block h-1 overflow-hidden rounded-full bg-surface-2">
							<span class="block h-full rounded-full {row.status === 'green' ? 'bg-good' : row.status === 'amber' ? 'bg-warn' : 'bg-bad'}" style:width="{row.bar}%"></span>
						</span>
					</span>
				</li>
			{/each}
		</ul>
	</div>
	<div
		class="absolute -bottom-5 -left-8 flex animate-[float_7s_ease-in-out_-3.5s_infinite] items-center gap-2 rounded-full bg-surface px-3 py-1.5 text-xs font-medium text-ink shadow-pop motion-reduce:animate-none"
		in:fly={{ y: 10 * move, duration: 400, delay: 1100 * move, easing: cubicOut }}
	>
		<span class="grid size-5 place-items-center rounded-full bg-good-wash text-good-ink"><Icon name="check" size={11} stroke={3} /></span>
		Airport just reported
	</div>
</div>
