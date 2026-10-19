<script lang="ts">
	import type { Snippet } from 'svelte';

	/** The line at the bottom of a sign-in style card, e.g. "New to Clairvoyance? Create an account". */
	let { children }: { children: Snippet } = $props();
</script>

<p class="mt-8 border-t border-line pt-5 text-center text-sm text-ink-2">{@render children()}</p>
