<script lang="ts">
	import { avatarTone, initials } from './people';

	/** A round badge with someone's initials, in a colour that stays the same for them. */
	let { name, email, size = 'md' }: { name?: string | null; email: string; size?: 'sm' | 'md' | 'lg' } = $props();
</script>

<span
	class="grid shrink-0 place-items-center rounded-full font-semibold {avatarTone(email)} {size === 'lg' ? 'size-12 text-base' : size === 'sm' ? 'size-7 text-xs' : 'size-9 text-sm'}"
	aria-hidden="true"
>
	{initials(name, email)}
</span>
