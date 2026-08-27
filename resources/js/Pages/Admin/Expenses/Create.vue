<template>
  <MainLayout>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button type="button" @click="goBack" class="flex items-center justify-between gap-1 p-2 text-white transition-colors bg-gray-700 rounded-lg hover:bg-gray-600" aria-label="Go back"> 
          <ArrowLeft class="w-4 h-4" />
          <span>Back</span>
        </button>
        <h1 class="text-2xl font-bold text-white">Create Expense</h1>
      </div>
    </div>

    <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
      <SkeletonLoader type="card" :count="4" class="opacity-40" />
    </div>

    <form v-else @submit.prevent="submit" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 space-y-6">
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
          <label class="block mb-2 text-sm font-medium text-gray-400">Expense Category *</label>
          <select v-model="form.expense_category_id" required class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none">
            <option value="">Select Category</option>
            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }} ({{ category.code }})</option>
          </select>
        </div>

        <div>
          <label class="block mb-2 text-sm font-medium text-gray-400">Vendor</label>
          <select v-model="form.vendor_id" class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none">
            <option value="">Select Vendor</option>
            <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.name }}</option>
          </select>
        </div>

        <div>
          <label class="block mb-2 text-sm font-medium text-gray-400">Department</label>
          <select v-model="form.department_id" class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none">
            <option value="">Select Department</option>
            <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
          </select>
        </div>

        <div>
          <label class="block mb-2 text-sm font-medium text-gray-400">Amount *</label>
          <input v-model="form.amount" type="text" inputmode="decimal" @input="formatAmountInput" required class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none" />
        </div>

        <div>
          <label class="block mb-2 text-sm font-medium text-gray-400">Currency</label>
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
          <label class="block mb-2 text-sm font-medium text-gray-400">Expense Date *</label>
          <input v-model="form.expense_date" type="date" required class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none" />
        </div>

        <div>
          <label class="block mb-2 text-sm font-medium text-gray-400">Payment Method *</label>
          <select v-model="form.payment_method" required class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none capitalize">
            <option v-for="method in paymentMethods" :key="method" :value="method">{{ method }}</option>
          </select>
        </div>

        <div>
          <label class="block mb-2 text-sm font-medium text-gray-400">Reference</label>
          <input v-model="form.reference" type="text" class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none" />
        </div>

        <div>
          <label class="block mb-2 text-sm font-medium text-gray-400">Invoice Number</label>
          <input v-model="form.invoice_number" type="text" class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none" />
        </div>
      </div>

      <div>
        <label class="block mb-2 text-sm font-medium text-gray-400">Description *</label>
        <textarea v-model="form.description" rows="4" required class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none"></textarea>
      </div>

      <div class="flex gap-3">
        <button type="submit" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm">Save Expense</button>
        <button type="button" @click="$router.push('/admin/expenses')" class="px-4 py-2 text-sm text-white bg-gray-700 rounded-lg">Cancel</button>
      </div>
    </form>
  </div>
</MainLayout>
</template>

<script setup>
import { reactive, onMounted, ref } from 'vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import api from '@/api';
import { useRouter } from 'vue-router';
import { ArrowLeft } from 'lucide-vue-next';

const router = useRouter();

// Return to wherever the user came from
const goBack = () => {
  if (window.history.state?.back) {
    router.back();
  } else {
    router.push('/admin/expenses');
  }
};

const form = reactive({
  expense_category_id: '',
  vendor_id: '',
  department_id: '',
  amount: '',
  currency: 'NGN',
  expense_date: new Date().toISOString().split('T')[0],
  payment_method: 'bank_transfer',
  reference: '',
  invoice_number: '',
  description: '',
});

const categories = ref([]);
const vendors = ref([]);
const departments = ref([]);
const loading = ref(true);
const paymentMethods = ['cash', 'bank_transfer', 'card', 'wallet', 'other'];

onMounted(async () => {
  loading.value = true;
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
      department_id: form.department_id || null,
      vendor_id: form.vendor_id || null,
    };
    const response = await api.post('/admin/expenses', payload);
    // Redirect to the Show page of the newly created expense (status is always draft).
    window.location.href = `/admin/expenses/${response.data.id}`;
  } catch (e) {
    console.error('Failed to create expense:', e);
    alert(e.response?.data?.message || 'Failed to create expense.');
  }
};
</script>