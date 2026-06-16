<template>
  <div class="bg-[#111827] p-4 rounded-xl border border-[#1F2A44]">
    <div class="flex flex-wrap items-center gap-3">
      <div class="relative flex-1 min-w-[200px]">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input
          :value="search"
          @input="$emit('update:search', $event.target.value); $emit('search')"
          @keyup.enter="$emit('search')"
          type="text"
          placeholder="Search by name, email, or phone..."
          class="w-full pl-10 pr-4 py-2.5 bg-[#1E293B] border border-gray-700 rounded-lg text-sm text-white placeholder-gray-400 focus:ring focus:ring-blue-700/40 transition"
        />
      </div>

      <select
        :value="status"
        @change="$emit('update:status', $event.target.value); $emit('search')"
        class="px-4 py-2.5 bg-[#1E293B] border border-gray-700 rounded-lg text-sm text-white focus:ring focus:ring-blue-700/40 transition"
      >
        <option value="">All Account Status</option>
        <option value="active">Active</option>
        <option value="suspended">Suspended</option>
      </select>

      <select
        :value="subscription"
        @change="$emit('update:subscription', $event.target.value); $emit('search')"
        class="px-4 py-2.5 bg-[#1E293B] border border-gray-700 rounded-lg text-sm text-white focus:ring focus:ring-blue-700/40 transition"
      >
        <option value="">All Subscriptions</option>
        <option value="active">Active Subscription</option>
        <option value="trial">Trial</option>
      </select>

      <button
        @click="$emit('search')"
        class="px-5 py-2.5 text-sm font-medium text-white transition bg-blue-600 rounded-lg hover:bg-blue-700"
      >
        Search
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  search: { type: String, default: "" },
  status: { type: String, default: "" },
  subscription: { type: String, default: "" },
});
defineEmits(["update:search", "update:status", "update:subscription", "search"]);
</script>