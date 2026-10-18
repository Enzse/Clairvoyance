<script lang="ts">
  import { authError, register } from '$lib/stores/auth';
  import { goto } from '$app/navigation';
  let email = '';
  let password = '';
  let role = 'editor';
  let loading = false;

  async function handleSubmit(event: Event) {
    event.preventDefault();
    loading = true;
    const ok = await register(email, password, role);
    loading = false;
    if (ok) goto('/auth/login');
  }
</script>

<div class="min-h-screen bg-white flex flex-col justify-center items-center -mt-6">
  <div class="w-full max-w-md">
    <div class="text-center ">
      <div class="flex items-center justify-center">
        <img src="/clairvoyance-logo.png" alt="Clairvoyance Logo" class="h-16 mt-2" />
        <h1 class="text-4xl font-bold text-gray-800">CLAIRVOYANCE</h1>
      </div>
      <p class="text-gray-500">Create your account to get started.</p>
    </div>

    <form class="bg-white p-8 rounded-lg space-y-6" on:submit={handleSubmit}>
      
      <div class="relative">
        <label for="email" class="text-sm font-medium text-gray-700 sr-only">Email</label>
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
        </div>
        <input 
          id="email" 
          class="block w-full pl-10 pr-4 py-3 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
          type="email" 
          placeholder="Email" 
          bind:value={email} 
          required 
        />
      </div>

      <div class="relative">
        <label for="password" class="text-sm font-medium text-gray-700 sr-only">Password</label>
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
        <input 
          id="password" 
          class="block w-full pl-10 pr-4 py-3 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
          type="password" 
          placeholder="Password (min. 8 characters)"
          bind:value={password}
          minlength="8"
          required
        />
      </div>
      

      {#if $authError}
        <div class="flex items-center text-red-600 text-sm">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
          <span>{$authError}</span>
        </div>
      {/if}

      <button 
        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-full shadow-sm text-sm font-medium text-white bg-blue-900 hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed" 
        type="submit" 
        disabled={loading}
      >
        {loading ? 'Creating account...' : 'Create Account'}
      </button>

      <div class="text-sm text-center text-gray-600">
        Already have an account?
        <a href="/auth/login" class="font-medium text-blue-900 hover:text-blue-800">
          Sign in
        </a>
      </div>
    </form>
  </div>
</div>