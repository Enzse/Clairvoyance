<script lang="ts" module>
	/**
	 * Page widths. The band and the page body use the same one, centred, so the heading lines
	 * up with the content under it. Forms read best narrow; tables and charts use the full width.
	 */
	export const WIDTH = {
		wide: 'max-w-[1240px]',
		form: 'max-w-[1000px]',
		narrow: 'max-w-[760px]'
	} as const;
	export type PageWidth = keyof typeof WIDTH;
</script>

<script lang="ts">
	import type { Snippet } from 'svelte';

	/** The navy band at the top of a page: a title, one line about the page, then optional controls. */
	let { title, lead, width = 'wide', children }: { title: string; lead?: string; width?: PageWidth; children?: Snippet } = $props();
</script>

<section class="bg-navy text-navy-ink">
	<div class="mx-auto max-w-[1240px] px-4 pt-5 pb-9 sm:px-6">
		<div class="border-t border-navy-2 pt-6">
			<div class="mx-auto {WIDTH[width]}">
				<h1 class="text-2xl font-semibold tracking-[-0.025em]">{title}</h1>
				{#if lead}<p class="mt-2 max-w-[68ch] text-md text-navy-ink-2">{lead}</p>{/if}
				{#if children}<div class="mt-6">{@render children()}</div>{/if}
			</div>
		</div>
	</div>
</section>
