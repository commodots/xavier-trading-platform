<template>
  <MainLayout>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-white">Expenses</h1>

    <!-- Tabs: Expenses / Categories / Vendors / Departments -->
    <div class="flex gap-1 bg-[#0F1724] border border-[#1f3348] rounded-xl p-1 w-fit">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        @click="switchTab(tab.key)"
        class="px-4 py-2 text-sm transition-colors rounded-lg"
        :class="activeTab === tab.key ? 'bg-[#0047AB] text-white' : 'text-gray-400 hover:text-white'"
      >
        {{ tab.label }}
      </button>
    </div>

    <component :is="activePanel" />
  </div>
</MainLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import MainLayout from '@/Layouts/MainLayout.vue';
import ListTab from './ListTab.vue';
import CategoriesTab from '@/Pages/Admin/ExpenseCategories/Index.vue';
import VendorsTab from '@/Pages/Admin/Vendors/Index.vue';
import DepartmentsTab from '@/Pages/Admin/Departments/Index.vue';

const tabs = [
  { key: 'expenses', label: 'Expenses' },
  { key: 'categories', label: 'Categories' },
  { key: 'vendors', label: 'Vendors' },
  { key: 'departments', label: 'Departments' },
];

const VALID_TABS = tabs.map((tab) => tab.key);

const panels = {
  expenses: ListTab,
  categories: CategoriesTab,
  vendors: VendorsTab,
  departments: DepartmentsTab,
};

const route = useRoute();
const router = useRouter();

// The active tab is reflected in the ?tab= query so it survives reloads and
// deep links (/admin/expenses?tab=categories).
const activeTab = ref(VALID_TABS.includes(route.query.tab) ? route.query.tab : 'expenses');
const activePanel = computed(() => panels[activeTab.value]);

watch(
  () => route.query.tab,
  (tab) => {
    if (VALID_TABS.includes(tab)) {
      activeTab.value = tab;
    }
  }
);

const switchTab = (key) => {
  if (activeTab.value === key) return;
  activeTab.value = key;
  // Replace so switching tabs doesn't pollute browser history.
  router.replace({ query: { ...route.query, tab: key } });
};
</script>
