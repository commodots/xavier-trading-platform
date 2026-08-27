<template>
  <MainLayout>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-white">Expenses</h1>
      <button type="button" @click="$router.push('/admin/expenses/create')" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm">Add Expense</button>
    </div>

    <!-- Summary -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Total Expenses</p>
        <p class="text-lg font-bold text-white">{{ formatAmountValue(summary.total) }}</p>
      </div>
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Draft</p>
        <p class="text-lg font-bold text-gray-300">{{ formatAmountValue(summary.draft) }}</p>
      </div>
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Approved</p>
        <p class="text-lg font-bold text-blue-400">{{ formatAmountValue(summary.approved) }}</p>
      </div>
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Paid</p>
        <p class="text-lg font-bold text-green-400">{{ formatAmountValue(summary.paid) }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-8 gap-4">
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
        <select v-model="filters.department" class="bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none">
          <option value="">All Departments</option>
          <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
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
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Date</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Category</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Vendor</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Department</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-right text-gray-400 uppercase">Amount</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Currency</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Payment</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Status</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="expense in expenses.data" :key="expense.id" class="border-b border-[#1f3348] hover:bg-[#16213A]">
            <td class="px-4 py-3 text-white">{{ expense.expense_no }}</td>
            <td class="px-4 py-3 text-white">{{ formatDateOnly(expense.expense_date) }}</td>
            <td class="px-4 py-3 text-white">{{ expense.category?.name || 'N/A' }}</td>
            <td class="px-4 py-3 text-white">{{ expense.vendor?.name || 'N/A' }}</td>
            <td class="px-4 py-3 text-white">{{ expense.department?.name || 'N/A' }}</td>
            <td class="px-4 py-3 text-right text-white font-mono">{{ formatNumber(expense.amount) }}</td>
            <td class="px-4 py-3 text-white">{{ expense.currency || 'NGN' }}</td>
            <td class="px-4 py-3 text-white capitalize">{{ expense.payment_method || 'N/A' }}</td>
            <td class="px-4 py-3">
              <span :class="getStatusClass(expense.status)" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">
                {{ expense.status }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-2 flex-wrap">
                <button type="button" @click="$router.push(`/admin/expenses/${expense.id}`)" class="text-xs text-blue-400 hover:text-blue-300">View</button>
                <button v-if="expense.status !== 'paid'" type="button" @click="$router.push(`/admin/expenses/${expense.id}/edit`)" class="text-xs text-green-400 hover:text-green-300">Edit</button>
                <button v-if="expense.status === 'draft'" type="button" @click="runAction(expense, 'approve')" class="text-xs text-teal-400 hover:text-teal-300">Approve</button>
                <button v-if="expense.status === 'approved'" type="button" @click="runAction(expense, 'pay')" class="text-xs text-yellow-400 hover:text-yellow-300">Pay</button>
                <button v-if="expense.status !== 'paid' && expense.status !== 'cancelled'" type="button" @click="runAction(expense, 'cancel')" class="text-xs text-red-400 hover:text-red-300">Cancel</button>
              </div>
            </td>
          </tr>
          <tr v-if="expenses.data.length === 0">
            <td colspan="10" class="px-4 py-8 text-center text-gray-500">No expenses found</td>
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
const departments = ref([]);
const loading = ref(true);
const summary = ref({ total: 0, draft: 0, approved: 0, paid: 0 });
const statuses = ['draft', 'approved', 'paid', 'cancelled'];
const filters = ref({ search: '', status: '', category: '', vendor: '', department: '', currency: '', from: '', to: '' });

const formatNumber = (num) => {
  return Number(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatAmountValue = (num) => {
  return Number(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
    expenses.value = res.data.expenses || res.data;
    summary.value = res.data.summary || summary.value;
  } catch (e) {
    console.error('Failed to load expenses:', e);
  } finally {
    loading.value = false;
  }
};

const runAction = async (expense, action) => {
  const endpoints = {
    approve: `/admin/expenses/${expense.id}/approve`,
    pay: `/admin/expenses/${expense.id}/pay`,
    cancel: `/admin/expenses/${expense.id}/cancel`,
  };
  if (!confirm(`Confirm ${action} of ${expense.expense_no}?`)) return;
  try {
    await api.post(endpoints[action]);
    fetchExpenses();
  } catch (e) {
    console.error(`Failed to ${action} expense:`, e);
    alert(e.response?.data?.message || `Failed to ${action} expense.`);
  }
};

const applyFilters = () => {
  fetchExpenses();
};

const resetFilters = () => {
  filters.value = { search: '', status: '', category: '', vendor: '', department: '', currency: '', from: '', to: '' };
  fetchExpenses();
};

const changePage = (page) => {
  if (page < 1 || page > expenses.value.last_page) return;
  filters.value.page = page;
  fetchExpenses();
};

onMounted(async () => {
  try {
    const [catsRes, vendsRes, deptsRes] = await Promise.all([
      api.get('/admin/expense-categories'),
      api.get('/admin/vendors'),
      api.get('/admin/departments'),
    ]);
    categories.value = catsRes.data;
    vendors.value = vendsRes.data;
    departments.value = deptsRes.data;
  } catch (e) {
    console.error('Failed to load filters:', e);
  }
  fetchExpenses();
});
</script>