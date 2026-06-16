<template>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-gray-400 border-b border-gray-700">
          <th class="py-3 px-4">User</th>
          <th class="py-3 px-4 hidden sm:table-cell">Email</th>
          <th class="py-3 px-4">Account</th>
          <th class="py-3 px-4">Subscription</th>
          <th class="py-3 px-4 hidden md:table-cell">Role</th>
          <th class="py-3 px-4 hidden lg:table-cell">Joined</th>
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
            <div class="flex items-center justify-center w-8 h-8 text-xs font-bold bg-gray-600 rounded-full shrink-0">
              {{ initials(user) }}
            </div>
            <div class="min-w-0">
              <span class="truncate block">{{ user.first_name }} {{ user.last_name }}</span>
              <span class="text-xs text-gray-500 truncate block sm:hidden">{{ user.email }}</span>
            </div>
          </td>
          <td class="py-3 px-4 truncate max-w-[200px] hidden sm:table-cell">{{ user.email }}</td>
          <td class="py-3 px-4">
            <span
              class="px-2 py-1 text-xs rounded whitespace-nowrap"
              :class="user.is_suspended ? 'bg-red-500/20 text-red-400' : 'bg-green-500/20 text-green-400'"
            >
              {{ user.is_suspended ? 'Suspended' : 'Active' }}
            </span>
          </td>
          <td class="py-3 px-4">
            <span
              class="px-2 py-1 text-xs rounded whitespace-nowrap"
              :class="subscriptionClass(user.subscription_status)"
            >
              {{ subscriptionStatusLabel(user.subscription_status) }}
            </span>
          </td>
          <td class="py-3 px-4 hidden md:table-cell">
            <div class="flex flex-wrap gap-1">
              <span
                v-for="role in userRoles(user).slice(0, 2)"
                :key="role"
                class="px-1.5 py-0.5 text-[10px] rounded bg-purple-500/20 text-purple-300 whitespace-nowrap capitalize"
              >
                {{ role }}
              </span>
              <span v-if="!userRoles(user).length" class="text-xs text-gray-500">—</span>
            </div>
          </td>
          <td class="py-3 px-4 whitespace-nowrap hidden lg:table-cell">{{ formatDate(user.created_at) }}</td>
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

const userRoles = (user) => {
  if (Array.isArray(user.roles) && user.roles.length) {
    return user.roles.map(r => typeof r === 'string' ? r : r.name || r);
  }
  if (user.role) return [user.role];
  return [];
};

const subscriptionClass = (status) => {
  switch (status) {
    case 'active':    return 'bg-green-500/20 text-green-400';
    case 'trial':     return 'bg-yellow-500/20 text-yellow-400';
    case 'suspended': return 'bg-red-500/20 text-red-400';
    case 'inactive':  return 'bg-gray-500/20 text-gray-400';
    default:          return 'bg-gray-600/30 text-gray-500';
  }
};

const subscriptionStatusLabel = (status) => {
  switch (status) {
    case 'active':    return 'Active Sub';
    case 'trial':     return 'Trial';
    case 'suspended': return 'Suspended';
    case 'inactive':  return 'Inactive';
    default:          return 'None';
  }
};
</script>