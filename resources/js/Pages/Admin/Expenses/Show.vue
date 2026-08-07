<template>
  <MainLayout>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-white">Expense Details</h1>
      <div class="flex gap-2">
        <button type="button" :disabled="!resolvedExpense.id" @click="$router.push(`/admin/expenses/${resolvedExpense.id}/edit`)" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm disabled:opacity-50">Edit</button>
        <button type="button" @click="$router.push('/admin/expenses')" class="px-4 py-2 bg-gray-700 text-white rounded-lg text-sm">Back</button>
      </div>
    </div>

    <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
      <SkeletonLoader type="card" :count="4" class="opacity-40" />
    </div>

    <div v-else class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Expense Number</p>
          <p class="text-white font-mono">{{ resolvedExpense.expense_no || 'N/A' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Category</p>
          <p class="text-white">{{ resolvedExpense.category?.name || 'N/A' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Vendor</p>
          <p class="text-white">{{ resolvedExpense.vendor?.name || 'N/A' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Amount</p>
          <p class="text-white font-mono text-lg">{{ resolvedExpense.currency || 'NGN' }} {{ formatNumber(resolvedExpense.amount) }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Expense Date</p>
          <p class="text-white">{{ formatDate(resolvedExpense.expense_date) }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Payment Method</p>
          <p class="text-white capitalize">{{ resolvedExpense.payment_method }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Status</p>
          <span :class="getStatusClass(resolvedExpense.status)" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize inline-block">
            {{ resolvedExpense.status || 'N/A' }}
          </span>
        </div>
        <div v-if="resolvedExpense.reference">
          <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Reference</p>
          <p class="text-white">{{ resolvedExpense.reference }}</p>
        </div>
        <div v-if="resolvedExpense.invoice_number">
          <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Invoice Number</p>
          <p class="text-white">{{ resolvedExpense.invoice_number }}</p>
        </div>
      </div>

      <div v-if="resolvedExpense.description" class="mt-6">
        <p class="text-xs text-gray-400 uppercase tracking-wider mb-2">Description</p>
        <p class="text-white whitespace-pre-wrap">{{ resolvedExpense.description }}</p>
      </div>
    </div>
  </div>
</MainLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import MainLayout from '@/Layouts/MainLayout.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import api from '@/api';

const props = defineProps({
  expense: Object,
});

const route = useRoute();
const expenseData = ref({});
const resolvedExpense = computed(() => props.expense || expenseData.value || {});
const loading = ref(true);

const loadExpense = async () => {
  const expenseId = route.params.id;
  if (!expenseId) {
    loading.value = false;
    return;
  }

  loading.value = true;

  try {
    const response = await api.get(`/admin/expenses/${expenseId}`);
    expenseData.value = response.data || {};
  } catch (error) {
    console.error('Failed to load expense details:', error);
    expenseData.value = {};
  } finally {
    loading.value = false;
  }
};

watch(
  () => props.expense?.id || route.params.id,
  (id) => {
    if (props.expense?.id) {
      expenseData.value = props.expense;
      loading.value = false;
      return;
    }

    loadExpense();
  },
  { immediate: true }
);

const formatNumber = (num) => {
  return Number(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (value) => {
  if (!value) return 'N/A';
  return new Date(value).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
};

const getStatusClass = (status) => {
  const map = {
    draft: 'bg-gray-900/50 text-gray-400',
    approved: 'bg-blue-900/50 text-blue-400',
    paid: 'bg-green-900/50 text-green-400',
    cancelled: 'bg-red-900/50 text-red-400',
  };
  return map[status] || 'bg-gray-900/50 text-gray-400';
};
</script>