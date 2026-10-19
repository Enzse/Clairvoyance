<script lang="ts">
	import { slide } from 'svelte/transition';
	import Icon from '../Icon.svelte';

	/** A problem with the form as a whole, under the fields. */
	let { message }: { message: string } = $props();
</script>

{#if message}
	<p class="flex items-start gap-2.5 rounded-lg bg-bad-wash px-3.5 py-2.5 text-sm text-bad-ink" role="alert" transition:slide={{ duration: 160 }}>
		<Icon name="alert" size={16} class="mt-0.5 shrink-0" />{message}
	</p>
{/if}
