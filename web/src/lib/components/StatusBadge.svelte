<script lang="ts">
	import { STATUS } from '$lib/status';
	import type { Status } from '$lib/types';
	import StatusMark from './StatusMark.svelte';

	let { status, empty = 'No target' }: { status: Status | null; empty?: string } = $props();
</script>

<span class="inline-flex items-center gap-1.5 text-sm whitespace-nowrap {status ? 'text-ink' : 'text-muted'}">
	<StatusMark {status} />
	{status ? STATUS[status].label : empty}
</span>
