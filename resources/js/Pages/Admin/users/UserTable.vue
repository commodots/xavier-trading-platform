<template>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-gray-400 border-b border-gray-700">
          <th class="py-3 px-4">User</th>
          <th class="py-3 px-4">Email</th>
          <th class="py-3 px-4">Phone</th>
          <th class="py-3 px-4">Status</th>
          <th class="py-3 px-4">Tier</th>
          <th class="py-3 px-4">Joined</th>
          <th class="py-3 px-4"></th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="user in users"
          :key="user.id"
          class="border-b border-gray-800 hover:bg-[#1E293B] transition cursor-pointer"
          @click="$emit('view', user)"
        >
          <td class="flex items-center gap-3 py-3 px-4 capitalize">
            <div class="flex items-center justify-center w-8 h-8 text-xs font-bold bg-gray-600 rounded-full">
              {{ initials(user) }}
            </div>
            {{ user.first_name }} {{ user.last_name }}
          </td>
          <td class="py-3 px-4">{{ user.email }}</td>
          <td class="py-3 px-4">{{ user.phone || '—' }}</td>
          <td class="py-3 px-4">
            <span
              class="px-2 py-1 text-xs rounded"
              :class="user.is_suspended ? 'bg-red-600' : 'bg-green-600'"
            >
              {{ user.is_suspended ? 'Suspended' : 'Active' }}
            </span>
          </td>
          <td class="py-3 px-4">
            <span class="px-2 py-1 text-xs rounded bg-blue-600/40 text-blue-300">
              {{ user.tier || 'free' }}
            </span>
          </td>
          <td class="py-3 px-4">{{ formatDate(user.created_at) }}</td>
          <td class="py-3 px-4 text-right">
            <button
              @click.stop="$emit('view', user)"
              class="px-3 py-1 text-xs text-white transition bg-blue-600 rounded hover:bg-blue-700"
            >
              View
            </button>
          </td>
        </tr>
        <tr v-if="!users.length">
          <td colspan="7" class="py-8 text-center text-gray-500">No users found.</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineProps({
  users: { type: Array, default: () => [] },
});
defineEmits(['view']);

const initials = (u) =>
  (u.first_name?.[0] || '').toUpperCase() +
  (u.last_name?.[0] || '').toUpperCase();

const formatDate = (d) => new Date(d).toLocaleDateString();
</script>