<template>
  <MainLayout>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-white">Edit Expense</h1>
    </div>

    <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
      <SkeletonLoader type="card" :count="4" class="opacity-40" />
    </div>

    <form v-else @submit.prevent="submit" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="block text-sm font-medium text-gray-400 mb-2">Expense Category *</label>
          <select v-model="form.expense_category_id" required class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none">
            <option value="">Select Category</option>
            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }} ({{ category.code }})</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-400 mb-2">Vendor</label>
          <select v-model="form.vendor_id" class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none">
            <option value="">Select Vendor</option>
            <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.name }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-400 mb-2">Department</label>
          <select v-model="form.department_id" class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none">
            <option value="">Select Department</option>
            <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-400 mb-2">Amount *</label>
          <input v-model="form.amount" type="text" inputmode="decimal" @input="formatAmountInput" required class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-400 mb-2">Currency</label>
          <select v-model="form.currency" class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none uppercase">
            <option value="USD">USD</option>
            <option value="EUR">EUR</option>
            <option value="GBP">GBP</option>
            <option value="NGN">NGN</option>
            <option value="CAD">CAD</option>
            <option value="AED">AED</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-400 mb-2">Expense Date *</label>
          <input v-model="form.expense_date" type="date" required class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-400 mb-2">Payment Method *</label>
          <select v-model="form.payment_method" required class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none">
            <option v-for="method in paymentMethods" :key="method" :value="method">{{ method }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-400 mb-2">Reference</label>
          <input v-model="form.reference" type="text" class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-400 mb-2">Invoice Number</label>
          <input v-model="form.invoice_number" type="text" class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none" />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-400 mb-2">Description *</label>
        <textarea v-model="form.description" rows="4" required class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none"></textarea>
      </div>

      <div class="flex gap-3">
        <button type="submit" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm">Update Expense</button>
        <button type="button" @click="$router.push('/admin/expenses')" class="px-4 py-2 bg-gray-700 text-white rounded-lg text-sm">Cancel</button>
      </div>
    </form>
  </div>
</MainLayout>
</template>

<script setup>
import { reactive, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import api from '@/api';

const route = useRoute();
const expenseId = route.params.id;

// Paid expenses can never be edited here — the backend rejects it and the
// Show page hides the Edit button for paid records.
const form = reactive({
  expense_category_id: '',
  vendor_id: '',
  department_id: '',
  amount: '',
  currency: 'NGN',
  expense_date: '',
  payment_method: 'bank_transfer',
  reference: '',
  invoice_number: '',
  description: '',
});

const categories = ref([]);
const vendors = ref([]);
const departments = ref([]);
const loading = ref(true);
const saving = ref(false);
const paymentMethods = ['cash', 'bank_transfer', 'card', 'wallet', 'other'];

const hydrate = (expense) => {
  form.expense_category_id = expense.expense_category_id || '';
  form.vendor_id = expense.vendor_id || '';
  form.department_id = expense.department_id || '';
  form.amount = String(expense.amount ?? '');
  form.currency = expense.currency || 'NGN';
  form.expense_date = expense.expense_date ? String(expense.expense_date).split('T')[0] : '';
  form.payment_method = expense.payment_method || 'bank_transfer';
  form.reference = expense.reference || '';
  form.invoice_number = expense.invoice_number || '';
  form.description = expense.description || '';
};

onMounted(async () => {
  loading.value = true;
  try {
    const [expenseRes, catsRes, vendsRes, deptsRes] = await Promise.all([
      api.get(`/admin/expenses/${expenseId}`),
      api.get('/admin/expense-categories'),
      api.get('/admin/vendors'),
      api.get('/admin/departments'),
    ]);
    hydrate(expenseRes.data || {});
    categories.value = catsRes.data;
    vendors.value = vendsRes.data;
    departments.value = deptsRes.data;
  } catch (e) {
    console.error('Failed to load data:', e);
  } finally {
    loading.value = false;
  }
});

const formatAmountInput = (event) => {
  const value = event.target.value.replace(/[^\d.]/g, '');
  const parts = value.split('.');
  const integerPart = parts[0].replace(/^0+(?=\d)/, '') || '0';
  const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

  if (parts.length > 2) {
    form.amount = `${formattedInteger}.${parts.slice(1).join('').replace(/[^\d]/g, '')}`;
    return;
  }

  if (parts[1] !== undefined) {
    form.amount = `${formattedInteger}.${parts[1].slice(0, 2)}`;
    return;
  }

  form.amount = formattedInteger;
};

const submit = async () => {
  saving.value = true;
  try {
    const payload = {
      ...form,
      amount: Number(form.amount.replace(/,/g, '')),
      department_id: form.department_id || null,
      vendor_id: form.vendor_id || null,
    };
    await api.put(`/admin/expenses/${expenseId}`, payload);
    window.location.href = `/admin/expenses/${expenseId}`;
  } catch (e) {
    console.error('Failed to update expense:', e);
    alert(e.response?.data?.message || 'Failed to update expense.');
  } finally {
    saving.value = false;
  }
};
</script>