<script lang="ts">
	import { api, ApiError } from '$lib/api';
	import { session } from '$lib/session.svelte';
	import { toast } from '$lib/toast.svelte';

	let name = $state(session.user?.name ?? '');
	let current = $state('');
	let next = $state('');
	let nameBusy = $state(false);
	let passwordBusy = $state(false);
	let passwordError = $state<ApiError | null>(null);

	async function saveName(event: SubmitEvent) {
		event.preventDefault();
		nameBusy = true;
		try {
			const user = await api.updateAccount({ name: name.trim() || null });
			if (session.user) session.user.name = user.name;
			toast('Name saved');
		} catch (e) {
			toast(e instanceof ApiError ? e.message : 'Could not save', 'error');
		} finally {
			nameBusy = false;
		}
	}

	async function savePassword(event: SubmitEvent) {
		event.preventDefault();
		passwordBusy = true;
		passwordError = null;
		try {
			await api.updateAccount({ current_password: current, new_password: next });
			current = next = '';
			toast('Password changed');
		} catch (e) {
			passwordError = e instanceof ApiError ? e : new ApiError(0, 'Could not change the password');
		} finally {
			passwordBusy = false;
		}
	}
</script>

<svelte:head><title>Account | Clairvoyance</title></svelte:head>

<section class="bg-navy text-navy-ink">
	<div class="mx-auto max-w-[1240px] px-4 pt-5 pb-12 sm:px-6">
		<div class="border-t border-navy-2 pt-6">
			<h1 class="text-2xl font-semibold tracking-[-0.025em]">Your account</h1>
			<p class="mt-2 text-md text-navy-ink-2">{session.user?.email}</p>
		</div>
	</div>
</section>

<main class="mx-auto max-w-[1240px] px-4 pb-16 sm:px-6">
	<div class="-mt-6 grid max-w-3xl gap-6">
		<form class="panel p-6" onsubmit={saveName}>
			<h2 class="font-semibold">Name</h2>
			<p class="mt-1 text-sm text-ink-2">Shown to people in your organisations.</p>
			<div class="mt-4 flex flex-wrap gap-3">
				<input class="input max-w-sm flex-1" aria-label="Name" autocomplete="name" bind:value={name} />
				<button class="btn btn-secondary" disabled={nameBusy || name === (session.user?.name ?? '')}>Save name</button>
			</div>
		</form>

		<form class="panel p-6" onsubmit={savePassword}>
			<h2 class="font-semibold">Password</h2>
			<div class="mt-4 grid max-w-xl gap-4 sm:grid-cols-2">
				<div>
					<label class="field-label" for="current-password">Current password</label>
					<input id="current-password" class="input" type="password" autocomplete="current-password" required bind:value={current} aria-invalid={!!passwordError?.field('current_password')} />
					{#if passwordError?.field('current_password')}<p class="field-error">That {passwordError.field('current_password')}</p>{/if}
				</div>
				<div>
					<label class="field-label" for="new-password">New password</label>
					<input id="new-password" class="input" type="password" autocomplete="new-password" minlength="8" required bind:value={next} aria-invalid={!!passwordError?.field('new_password')} />
					<p class={passwordError?.field('new_password') ? 'field-error' : 'field-hint'}>At least 8 characters.</p>
				</div>
			</div>
			<button class="btn btn-secondary mt-5" disabled={passwordBusy || !current || next.length < 8}>{passwordBusy ? 'Changing…' : 'Change password'}</button>
		</form>

		<section class="panel p-6">
			<h2 class="font-semibold">Organisations</h2>
			<ul class="mt-3 divide-y divide-line">
				{#each session.user?.organizations ?? [] as o (o.id)}
					<li class="flex items-center justify-between py-2.5">
						<span class="font-medium">{o.name}</span>
						<span class="text-sm text-ink-2">{o.role === 'admin' ? 'Administrator' : 'Member'}</span>
					</li>
				{/each}
			</ul>
			<a class="btn btn-ghost btn-sm mt-3 -ml-2.5" href="/onboarding?new=1">Create a new organisation</a>
		</section>
	</div>
</main>
