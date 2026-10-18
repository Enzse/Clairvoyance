<script lang="ts">
  import { onMount } from 'svelte';
  import { getAdminUsers, deleteUser, adminCreateUser } from '$lib/services/api';
  import { user as currentUser } from '$lib/stores/auth';
  import type { User } from '$lib/types';

  let users: User[] = [];
  let isLoading = true;
  let error: string | null = null;
  let successMessage: string | null = null;
  let showAddUserModal = false;
  let showUserDetailsModal = false;
  let selectedUser: User | null = null;
  let newUser = { email: '', password: '', role: 'editor' };
  let isSubmitting = false;
  
  // User table enhancements
  let searchTerm = '';
  let sortBy: 'email' | 'role' | 'created_at' = 'email';
  let sortOrder: 'asc' | 'desc' = 'asc';
  let currentPage = 1;
  let itemsPerPage = 10;

  $: filteredUsers = users
    .filter(user => user.id !== $currentUser?.id)
    .filter(user => user.email.toLowerCase().includes(searchTerm.toLowerCase()))
    .sort((a, b) => {
      let aVal: string | number = a[sortBy] || '';
      let bVal: string | number = b[sortBy] || '';
      
      if (sortBy === 'created_at') {
        aVal = a.created_at || '';
        bVal = b.created_at || '';
      }
      
      if (sortOrder === 'asc') {
        return aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
      } else {
        return aVal > bVal ? -1 : aVal < bVal ? 1 : 0;
      }
    });

  $: totalPages = Math.ceil(filteredUsers.length / itemsPerPage);
  $: paginatedUsers = filteredUsers.slice(
    (currentPage - 1) * itemsPerPage,
    currentPage * itemsPerPage
  );

  onMount(async () => {
    await fetchUsers();
  });

  async function fetchUsers() {
    isLoading = true;
    clearMessages();
    try {
      const response = await getAdminUsers();
      if (response.success && response.users) {
        users = response.users;
      } else {
        throw new Error(response.message || 'Failed to fetch users.');
      }
    } catch (e: any) {
      error = e.message;
    } finally {
      isLoading = false;
    }
  }

  async function handleDeleteUser(id: number, email: string) {
    clearMessages();
    if (confirm(`Are you sure you want to delete the user: ${email}? This action cannot be undone.`)) {
      try {
        const response = await deleteUser(id);
        if (response.success) {
          successMessage = 'User deleted successfully.';
          await fetchUsers(); // Refresh the list
        } else {
          throw new Error(response.message || 'Failed to delete user.');
        }
      } catch (e: any) {
        error = e.message;
      }
    }
  }

  function clearMessages() {
    error = null;
    successMessage = null;
  }

  async function handleCreateUser() {
    isSubmitting = true;
    clearMessages();
    try {
      const response = await adminCreateUser(newUser);
      if (response.success) {
        successMessage = 'User created successfully.';
        showAddUserModal = false;
        newUser = { email: '', password: '', role: 'editor' };
        await fetchUsers();
      } else {
        throw new Error(response.message || 'Failed to create user.');
      }
    } catch (e: any) {
      error = e.message;
    } finally {
      isSubmitting = false;
    }
  }

  function handleSort(column: 'email' | 'role' | 'created_at') {
    if (sortBy === column) {
      sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
    } else {
      sortBy = column;
      sortOrder = 'asc';
    }
    currentPage = 1; // Reset to first page when sorting
  }

  function handlePageChange(page: number) {
    currentPage = page;
  }

  function handleUserClick(user: User) {
    selectedUser = user;
    showUserDetailsModal = true;
  }

  function formatDate(dateString: string) {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }
</script>

<div class="min-h-screen bg-gray-50">
  <div class="container mx-auto p-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">User Management</h1>
        <p class="text-gray-600 mt-1">Manage user accounts and permissions</p>
        <div class="mt-2 flex items-center text-sm text-amber-600 bg-amber-50 px-3 py-2 rounded-lg">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <span>Role changes must be done via CLI scripts for security. Use <code class="bg-amber-100 px-1 rounded">php promote_user.php &lt;email&gt;</code> or <code class="bg-amber-100 px-1 rounded">php demote_user.php &lt;email&gt;</code></span>
        </div>
      </div>
    </div>

    <!-- Messages -->
    {#if error}
      <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6" role="alert">
        <div class="flex">
          <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
          </svg>
          <span class="font-medium">Error:</span>
          <span class="ml-1">{error}</span>
        </div>
      </div>
    {/if}

    {#if successMessage}
      <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6" role="alert">
        <div class="flex">
          <svg class="w-5 h-5 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
          </svg>
          <span class="font-medium">Success:</span>
          <span class="ml-1">{successMessage}</span>
        </div>
      </div>
    {/if}

    <!-- User Management Section -->
    <div class="bg-white rounded-lg shadow">
      <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex justify-between items-center">
          <h3 class="text-lg font-medium text-gray-900">Users</h3>
          <div class="flex items-center space-x-4">
            <!-- Search -->
            <div class="relative">
              <input
                type="text"
                placeholder="Search users..."
                bind:value={searchTerm}
                class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
              <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
              </svg>
            </div>
            <!-- Items per page -->
            <select bind:value={itemsPerPage} class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value={5}>5 per page</option>
              <option value={10}>10 per page</option>
              <option value={25}>25 per page</option>
              <option value={50}>50 per page</option>
            </select>
            <button 
              on:click={() => showAddUserModal = true} 
              class="text-sm bg-blue-900 hover:bg-blue-800 cursor-pointer text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
              </svg>
              Create User
            </button>
          </div>
        </div>
      </div>

      {#if isLoading}
        <div class="p-6 text-center">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <p class="mt-2 text-gray-500">Loading users...</p>
        </div>
      {:else}
        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th 
                  class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  on:click={() => handleSort('email')}
                >
                  <div class="flex items-center">
                    Email
                    {#if sortBy === 'email'}
                      <svg class="ml-1 w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"></path>
                      </svg>
                    {/if}
                  </div>
                </th>
                <th 
                  class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  on:click={() => handleSort('role')}
                >
                  <div class="flex items-center">
                    Role
                    {#if sortBy === 'role'}
                      <svg class="ml-1 w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"></path>
                      </svg>
                    {/if}
                  </div>
                </th>
                <th 
                  class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                  on:click={() => handleSort('created_at')}
                >
                  <div class="flex items-center">
                    Created
                    {#if sortBy === 'created_at'}
                      <svg class="ml-1 w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"></path>
                      </svg>
                    {/if}
                  </div>
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              {#each paginatedUsers as user (user.id)}
                <tr class="hover:bg-gray-50 cursor-pointer" on:click={() => handleUserClick(user)}>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                        <span class="text-sm font-medium text-gray-600">{user.email.charAt(0).toUpperCase()}</span>
                      </div>
                      <div class="ml-3">
                        <div class="text-sm font-medium text-gray-900">{user.email}</div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {user.role === 'admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'}">
                      {user.role}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {user.created_at ? formatDate(user.created_at) : 'N/A'}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex items-center space-x-3">
                      <!-- View Details Button -->
                      <button 
                        class="text-blue-600 hover:text-blue-900 flex items-center gap-1"
                        on:click|stopPropagation={() => handleUserClick(user)}
                        title="View user details"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <span class="text-xs">View</span>
                      </button>
                      
                      <!-- Delete Button -->
                      <button 
                        class="text-red-600 hover:text-red-900 disabled:text-gray-400 disabled:cursor-not-allowed flex items-center gap-1"
                        on:click|stopPropagation={() => handleDeleteUser(user.id, user.email)}
                        disabled={user.id === $currentUser?.id}
                        aria-label="Delete user {user.email}"
                        title={user.id === $currentUser?.id ? 'Cannot delete your own account' : 'Delete user'}
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        <span class="text-xs">Delete</span>
                      </button>
                    </div>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        {#if totalPages > 1}
          <div class="px-6 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700">
                Showing {((currentPage - 1) * itemsPerPage) + 1} to {Math.min(currentPage * itemsPerPage, filteredUsers.length)} of {filteredUsers.length} results
              </div>
              <div class="flex items-center space-x-2">
                <button
                  on:click={() => handlePageChange(currentPage - 1)}
                  disabled={currentPage === 1}
                  class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Previous
                </button>
                
                {#each Array(Math.min(5, totalPages)) as _, i}
                  {@const page = i + 1}
                  <button
                    on:click={() => handlePageChange(page)}
                    class="px-3 py-1 text-sm border rounded-md {page === currentPage ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50'}"
                  >
                    {page}
                  </button>
                {/each}
                
                <button
                  on:click={() => handlePageChange(currentPage + 1)}
                  disabled={currentPage === totalPages}
                  class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Next
                </button>
              </div>
            </div>
          </div>
        {/if}
      {/if}
    </div>
  </div>
</div>

<!-- Add User Modal -->
{#if showAddUserModal}
<div class="fixed inset-0 bg-[rgba(0,0,0,0.5)] bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center z-50">
  <div class="relative mx-auto p-6 border w-full max-w-md shadow-lg rounded-lg bg-white">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-medium text-gray-900">Add New User</h3>
      <button 
        on:click={() => showAddUserModal = false}
        class="text-gray-400 hover:text-gray-600"
        aria-label="Close modal"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>
    
    <form on:submit|preventDefault={handleCreateUser}>
      <div class="space-y-4">
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input 
            type="email" 
            id="email" 
            bind:value={newUser.email} 
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
            required
            placeholder="user@example.com"
          >
        </div>
        
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input 
            type="password" 
            id="password" 
            bind:value={newUser.password}
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            minlength="8"
            required
            placeholder="Min. 8 characters"
          >
        </div>
        
      </div>
      
      <div class="flex justify-end gap-3 mt-6">
        <button 
          type="button" 
          on:click={() => showAddUserModal = false} 
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
          disabled={isSubmitting}
        >
          Cancel
        </button>
        <button 
          type="submit" 
          class="px-4 py-2 text-sm font-medium text-white bg-blue-900 cursor-pointer hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50"
          disabled={isSubmitting}
        >
          {isSubmitting ? 'Creating...' : 'Create User'}
        </button>
      </div>
    </form>
  </div>
</div>
{/if}

<!-- User Details Modal -->
{#if showUserDetailsModal && selectedUser}
<div class="fixed inset-0 bg-[rgba(0,0,0,0.5)] bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center z-50">
  <div class="relative mx-auto p-6 border w-full max-w-lg shadow-lg rounded-lg bg-white">
    <div class="flex justify-between items-center mb-6">
      <h3 class="text-lg font-medium text-gray-900">User Details</h3>
      <button 
        on:click={() => showUserDetailsModal = false}
        class="text-gray-400 hover:text-gray-600"
        aria-label="Close modal"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>
    
    <div class="space-y-6">
      <!-- User Avatar and Basic Info -->
      <div class="flex items-center space-x-4">
        <div class="w-20 h-20 bg-blue-900 rounded-full flex items-center justify-center shadow-lg">
          <span class="text-2xl font-bold text-white">{selectedUser?.email?.charAt(0).toUpperCase() || '?'}</span>
        </div>
        <div class="flex-1">
          <h4 class="text-2xl font-semibold text-gray-900">{selectedUser?.email || 'Unknown User'}</h4>
          <div class="flex items-center gap-3 mt-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {selectedUser?.role === 'admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'}">
              {#if selectedUser?.role === 'admin'}
                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
              {:else}
                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd"></path>
                </svg>
              {/if}
              {selectedUser?.role || 'unknown'}
            </span>
            {#if selectedUser?.id === $currentUser?.id}
              <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                You
              </span>
            {/if}
          </div>
        </div>
      </div>
      
      <!-- User Information Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="shadow-sm p-4 rounded-lg ">
          <div class="flex items-center">
            <svg class="w-5 h-5 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
            </svg>
            <h5 class="text-sm font-medium text-gray-700">User ID</h5>
          </div>
          <p class="text-lg font-semibold text-gray-900 mt-1">{selectedUser?.id || 'N/A'}</p>
        </div>
        
        <div class="shadow-sm p-4 rounded-lg ">
          <div class="flex items-center">
            <svg class="w-5 h-5 text-purple-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h5 class="text-sm font-medium text-gray-700">Member Since</h5>
          </div>
          <p class="text-lg font-semibold text-gray-900 mt-1">{selectedUser?.created_at ? formatDate(selectedUser.created_at) : 'N/A'}</p>
        </div>
        
        <div class="shadow-sm p-4 rounded-lg ">
          <div class="flex items-center">
            <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
            <h5 class="text-sm font-medium text-gray-700">Email Address</h5>
          </div>
          <p class="text-lg font-semibold text-gray-900 mt-1 break-all">{selectedUser?.email || 'N/A'}</p>
        </div>
        
        <div class="shadow-sm p-4 rounded-lg ">
          <div class="flex items-center">
            <svg class="w-5 h-5 text-orange-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h5 class="text-sm font-medium text-gray-700">Account Status</h5>
          </div>
          <p class="text-lg font-semibold text-gray-900 mt-1">Active</p>
        </div>
      </div>
      
      <!-- Role Information -->
      <div class="bg-gray-50 p-4 rounded-lg">
        <h5 class="text-sm font-medium text-gray-700 mb-2 flex items-center">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          Role Information
        </h5>
        <p class="text-sm text-gray-600">
          {#if selectedUser?.role === 'admin'}
            This user has administrative privileges and can manage other users, view system statistics, and access all features of the application.
          {:else}
            This user has editor privileges and can create and manage dashboards, KPIs, and data entries. They can also share their dashboards with other users.
          {/if}
        </p>
      </div>
      
      <!-- Actions -->
      <div class="flex justify-between items-center pt-4 border-t border-gray-200">
        <div class="text-xs text-gray-500">
          {#if selectedUser?.id === $currentUser?.id}
            This is your account
          {:else}
            User account details
          {/if}
        </div>
        <div class="flex space-x-3">
          <button 
            on:click={() => showUserDetailsModal = false}
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
          >
            Close
          </button>
          {#if selectedUser?.id && selectedUser.id !== $currentUser?.id}
            <button 
              on:click={() => {
                if (selectedUser) {
                  showUserDetailsModal = false;
                  handleDeleteUser(selectedUser.id, selectedUser.email || 'Unknown User');
                }
              }}
              class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
            >
              Delete User
            </button>
          {/if}
        </div>
      </div>
    </div>
  </div>
</div>
{/if}