<template>
  <div class="flex flex-wrap items-center gap-4 bg-[#111827] p-4 rounded-xl border border-[#1F2A44]">
    <input
      :value="search"
      @input="$emit('update:search', $event.target.value)"
      @keyup.enter="$emit('search')"
      type="text"
      placeholder="Search by name, email, or phone..."
      class="px-4 py-2 w-72 bg-[#1E293B] border border-gray-700 rounded-lg text-sm text-white placeholder-gray-400 focus:ring focus:ring-blue-700/40 transition"
    />
    <select
      :value="status"
      @change="$emit('update:status', $event.target.value); $emit('search')"
      class="px-4 py-2 bg-[#1E293B] border border-gray-700 rounded-lg text-sm text-white focus:ring focus:ring-blue-700/40 transition"
    >
      <option value="">All Status</option>
      <option value="active">Active</option>
      <option value="suspended">Suspended</option>
    </select>
    <select
      :value="subscription"
      @change="$emit('update:subscription', $event.target.value); $emit('search')"
      class="px-4 py-2 bg-[#1E293B] border border-gray-700 rounded-lg text-sm text-white focus:ring focus:ring-blue-700/40 transition"
    >
      <option value="">All Subscriptions</option>
      <option value="trial">Trial</option>
      <option value="paying">Paying</option>
    </select>
    <button
      @click="$emit('search')"
      class="px-4 py-2 text-sm text-white transition bg-blue-600 rounded-lg hover:bg-blue-700"
    >
      Search
    </button>
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