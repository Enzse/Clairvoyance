<script lang="ts">
	import InviteList from '$lib/components/InviteList.svelte';

	let { onDone }: { onDone: () => void } = $props();
	let created = $state(0);
</script>

<div class="panel -mt-6 max-w-3xl p-6 sm:p-8">
	<InviteList onCreated={(n) => (created += n)} />
	<div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-line pt-5">
		<button class="btn btn-primary" onclick={onDone}>{created ? 'Go to your scorecard' : 'Skip and go to your scorecard'}</button>
	</div>
</div>
