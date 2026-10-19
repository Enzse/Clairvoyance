<script lang="ts" module>
	import type { Snippet } from 'svelte';

	export interface TourStep {
		title: string;
		body: string;
		/** An illustration above the text */
		art?: Snippet;
	}

	/** Whether a tour was finished or closed in this browser before. */
	export function tourSeen(key: string): boolean {
		try {
			return localStorage.getItem(`clairvoyance.tour.${key}`) === 'seen';
		} catch {
			return false;
		}
	}

	export function markTourSeen(key: string): void {
		try {
			localStorage.setItem(`clairvoyance.tour.${key}`, 'seen');
		} catch {
			// Private browsing: it will show again next time
		}
	}
</script>

<script lang="ts">
	import { cubicOut } from 'svelte/easing';
	import { prefersReducedMotion } from 'svelte/motion';
	import { fly, scale } from 'svelte/transition';
	import Icon from './Icon.svelte';

	/**
	 * A short step-by-step introduction in a modal: an illustration, a title and a few words
	 * per step. Arrow keys move between steps; Escape or the last button closes it.
	 */
	let { open = $bindable(false), steps, label, onClose }: { open?: boolean; steps: TourStep[]; label: string; onClose?: () => void } = $props();

	let dialog: HTMLDialogElement;
	let index = $state(0);
	let direction = $state(1);
	const step = $derived(steps[index]);
	const last = $derived(index === steps.length - 1);
	const move = $derived(prefersReducedMotion.current ? 0 : 1);

	$effect(() => {
		if (open && !dialog.open) {
			index = 0;
			dialog.showModal();
		}
		if (!open && dialog.open) dialog.close();
	});

	function go(to: number) {
		if (to < 0 || to >= steps.length) return;
		direction = to > index ? 1 : -1;
		index = to;
	}

	function close() {
		open = false;
		onClose?.();
	}

	function onkeydown(event: KeyboardEvent) {
		if (event.key === 'ArrowRight') go(index + 1);
		if (event.key === 'ArrowLeft') go(index - 1);
	}
</script>

<dialog
	bind:this={dialog}
	class="m-auto w-[min(540px,calc(100vw-32px))] overflow-hidden rounded-2xl bg-surface p-0 text-ink shadow-pop"
	aria-label={label}
	onclose={() => open && close()}
	onclick={(e) => e.target === dialog && close()}
	{onkeydown}
>
	{#if open}
		<div in:scale={{ start: 0.96, duration: 220 * move, easing: cubicOut }}>
			<button class="absolute top-3 right-3 z-10 rounded-md p-1.5 text-muted hover:bg-surface hover:text-ink" onclick={close} aria-label="Close">
				<Icon name="x" size={18} />
			</button>
			{#key index}
				<div in:fly={{ x: 36 * direction * move, duration: 260, easing: cubicOut }}>
					{#if step.art}
						<div class="grid min-h-48 place-items-center border-b border-line bg-surface-2 px-6 py-7" aria-hidden="true">{@render step.art()}</div>
					{/if}
					<div class="px-6 pt-5" aria-live="polite">
						<p class="text-xs font-semibold tracking-wide text-muted uppercase">{index + 1} of {steps.length}</p>
						<h2 class="mt-1 text-xl font-semibold tracking-[-0.015em]">{step.title}</h2>
						<p class="mt-2 text-ink-2">{step.body}</p>
					</div>
				</div>
			{/key}
			<footer class="flex items-center justify-between gap-4 px-6 pt-6 pb-5">
				<div class="flex gap-1.5">
					{#each steps as s, i (s.title)}
						<button
							class="h-2 rounded-full transition-all duration-300 {i === index ? 'w-6 bg-chart' : 'w-2 bg-line-strong hover:bg-muted'}"
							aria-label="Step {i + 1}: {s.title}"
							aria-current={i === index ? 'step' : undefined}
							onclick={() => go(i)}
						></button>
					{/each}
				</div>
				<div class="flex gap-2">
					{#if index > 0}<button class="btn btn-ghost" onclick={() => go(index - 1)}>Back</button>{/if}
					<!-- svelte-ignore a11y_autofocus -->
					<button class="btn btn-primary" autofocus onclick={() => (last ? close() : go(index + 1))}>
						{last ? 'Got it' : 'Next'}
						{#if !last}<Icon name="arrow-right" size={15} />{/if}
					</button>
				</div>
			</footer>
		</div>
	{/if}
</dialog>
