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
          <label class="block text-sm font-medium text-gray-400 mb-2">Status *</label>
          <select v-model="form.status" required class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none">
            <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
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
import { reactive, onMounted, ref, defineProps } from 'vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import api from '@/api';

const props = defineProps({
  expense: Object,
});

const form = reactive({
  expense_category_id: props.expense?.expense_category_id || '',
  vendor_id: props.expense?.vendor_id || '',
  amount: props.expense?.amount || '',
  currency: props.expense?.currency || 'NGN',
  expense_date: props.expense?.expense_date || '',
  payment_method: props.expense?.payment_method || 'bank_transfer',
  status: props.expense?.status || 'draft',
  reference: props.expense?.reference || '',
  invoice_number: props.expense?.invoice_number || '',
  description: props.expense?.description || '',
});

const categories = ref([]);
const vendors = ref([]);
const loading = ref(true);
const paymentMethods = ['cash', 'bank_transfer', 'card', 'wallet', 'other'];
const statuses = ['draft', 'approved', 'paid', 'cancelled'];

onMounted(async () => {
  loading.value = true;
  try {
    const [catsRes, vendsRes] = await Promise.all([
      api.get('/admin/expense-categories'),
      api.get('/admin/vendors'),
    ]);
    categories.value = catsRes.data;
    vendors.value = vendsRes.data;
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
  try {
    const payload = {
      ...form,
      amount: Number(form.amount.replace(/,/g, '')),
    };
    await api.put(`/admin/expenses/${props.expense.id}`, payload);
    window.location.href = '/admin/expenses';
  } catch (e) {
    console.error('Failed to update expense:', e);
  }
};
</script>