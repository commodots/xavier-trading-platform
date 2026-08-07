<template>
  <MainLayout>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-white">Expenses</h1>
      <button type="button" @click="$router.push('/admin/expenses/create')" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm">Add Expense</button>
    </div>

    <!-- Filters -->
    <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-4">
        <input v-model="filters.search" type="text" placeholder="Search expenses..." class="bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none" />
        <select v-model="filters.status" class="bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none capitalize">
          <option value="">All Status</option>
          <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
        </select>
        <select v-model="filters.category" class="bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none">
          <option value="">All Categories</option>
          <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
        </select>
        <select v-model="filters.vendor" class="bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none">
          <option value="">All Vendors</option>
          <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.name }}</option>
        </select>
        <select v-model="filters.currency" class="bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none">
          <option value="">Currency</option>
          <option value="USD">USD</option>
          <option value="EUR">EUR</option>
          <option value="GBP">GBP</option>
          <option value="NGN">NGN</option>
          <option value="CAD">CAD</option>
          <option value="AED">AED</option>
        </select>
        <input v-model="filters.from" type="date" class="bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none" />
        <input v-model="filters.to" type="date" class="bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none" />
      </div>
      <div class="mt-4 flex gap-2">
        <button @click="applyFilters" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm">Apply</button>
        <button @click="resetFilters" class="px-4 py-2 bg-gray-700 text-white rounded-lg text-sm">Reset</button>
      </div>
    </div>

    <!-- Table -->
    <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl overflow-hidden p-4">
      <SkeletonLoader type="table" :count="6" class="opacity-40" />
    </div>

    <div v-else class="bg-[#0F1724] border border-[#1f3348] rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-[#1f3348]">
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Expense No</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Category</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Vendor</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-right text-gray-400 uppercase">Amount</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Date</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Status</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="expense in expenses.data" :key="expense.id" class="border-b border-[#1f3348] hover:bg-[#16213A]">
            <td class="px-4 py-3 text-white">{{ expense.expense_no }}</td>
            <td class="px-4 py-3 text-white">{{ expense.category?.name || 'N/A' }}</td>
            <td class="px-4 py-3 text-white">{{ expense.vendor?.name || 'N/A' }}</td>
            <td class="px-4 py-3 text-right text-white font-mono">{{ formatAmount(expense) }}</td>
            <td class="px-4 py-3 text-white">{{ formatDateOnly(expense.expense_date) }}</td>
            <td class="px-4 py-3">
              <span :class="getStatusClass(expense.status)" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">
                {{ expense.status }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <button type="button" @click="$router.push(`/admin/expenses/${expense.id}`)" class="text-xs text-blue-400 hover:text-blue-300">View</button>
                <button type="button" @click="$router.push(`/admin/expenses/${expense.id}/edit`)" class="text-xs text-green-400 hover:text-green-300">Edit</button>
              </div>
            </td>
          </tr>
          <tr v-if="expenses.data.length === 0">
            <td :colspan="7" class="px-4 py-8 text-center text-gray-500">No expenses found</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="expenses.last_page > 1" class="flex items-center justify-between">
      <span class="text-xs text-gray-500">Showing {{ expenses.from }} - {{ expenses.to }} of {{ expenses.total }}</span>
      <div class="flex gap-1">
        <button @click="changePage(expenses.current_page - 1)" :disabled="expenses.current_page <= 1" class="px-2 py-1 text-xs rounded hover:bg-[#16213A] disabled:opacity-50">Previous</button>
        <span class="px-2 py-1 text-xs text-white">{{ expenses.current_page }}</span>
        <button @click="changePage(expenses.current_page + 1)" :disabled="expenses.current_page >= expenses.last_page" class="px-2 py-1 text-xs rounded hover:bg-[#16213A] disabled:opacity-50">Next</button>
      </div>
    </div>
  </div>
</MainLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import api from '@/api';


const expenses = ref({ data: [], current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
const categories = ref([]);
const vendors = ref([]);
const loading = ref(true);
const statuses = ['draft', 'approved', 'paid', 'cancelled'];
const filters = ref({ search: '', status: '', category: '', vendor: '', currency: '', from: '', to: '' });

const formatNumber = (num) => {
  return Number(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatAmount = (expense) => {
  const currency = expense.currency || 'NGN';
  return `${currency} ${formatNumber(expense.amount)}`;
};

const formatDateOnly = (value) => {
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

const fetchExpenses = async () => {
  loading.value = true;
  try {
    const params = new URLSearchParams();
    Object.entries(filters.value).forEach(([key, value]) => {
      if (value) params.append(key, value);
    });
    
    const res = await api.get(`/admin/expenses?${params}`);
    expenses.value = res.data;
  } catch (e) {
    console.error('Failed to load expenses:', e);
  } finally {
    loading.value = false;
  }
};

const applyFilters = () => {
  fetchExpenses();
};

const resetFilters = () => {
  filters.value = { search: '', status: '', category: '', vendor: '', currency: '', from: '', to: '' };
  fetchExpenses();
};

const changePage = (page) => {
  if (page < 1 || page > expenses.value.last_page) return;
  filters.value.page = page;
  fetchExpenses();
};

onMounted(async () => {
  try {
    const [catsRes, vendsRes] = await Promise.all([
      api.get('/admin/expense-categories'),
      api.get('/admin/vendors'),
    ]);
    categories.value = catsRes.data;
    vendors.value = vendsRes.data;
  } catch (e) {
    console.error('Failed to load filters:', e);
  }
  fetchExpenses();
});
</script>