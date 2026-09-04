<script setup>
import { onMounted, ref } from 'vue';
import api from '@/api';
import MainLayout from "@/Layouts/MainLayout.vue";

const rows = ref([]);
const loading = ref(true);
const error = ref('')

onMounted(
  async () => {
    try {
      rows.value = (await api.get('/admin/fixed-income/reports/investments')).data.data || []
    }
    catch (exception) {
      error.value = exception.response?.data?.message || 'Unable to load report.'
    }
    finally {
      loading.value = false
    }
  })

</script>

<template>
  <MainLayout>
  <div class="space-y-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-cyan-400">Reporting</p>
      <h1 class="mt-2 text-3xl font-semibold text-white">Fixed Income report</h1>
    </div>
    <p v-if="error" class="text-red-400">{{ error }}</p>
    <p v-if="loading" class="text-gray-400">Loading report...</p>
    <div v-else class="overflow-x-auto rounded-xl border border-[#1f3348] bg-[#0F1724]">
      <table class="min-w-full text-left text-sm">
        <thead class="text-gray-500">
          <tr>
            <th class="p-4">Reference</th>
            <th class="p-4">Investor</th>
            <th class="p-4">Product</th>
            <th class="p-4">Principal</th>
            <th class="p-4">Expected interest</th>
            <th class="p-4">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.reference" class="border-t border-[#1f3348] text-white">
            <td class="p-4">{{ row.reference }}</td>
            <td class="p-4">{{ row.investor || '-' }}</td>
            <td class="p-4">{{ row.product || '-' }}</td>
            <td class="p-4">{{ row.principal }} {{ row.currency }}</td>
            <td class="p-4">{{ row.expected_interest }}</td>
            <td class="p-4 capitalize">{{ row.status?.replace('_', ' ') }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="!rows.length" class="p-8 text-center text-gray-500">No investments match this report.</p>
    </div>
  </div>
</MainLayout>
</template>
