<script setup>
import { onMounted, ref } from 'vue';
import api from '@/api';
import MainLayout from "@/Layouts/MainLayout.vue";
import SkeletonLoader from '@/Components/SkeletonLoader.vue'
import { fixedIncomeLabel } from '@/lib/fixedIncomeFormatters'

const investments = ref([]);
const loading = ref(true);
const error = ref('')

onMounted(
  async () => {
    try {
      investments.value = (await api.get('/admin/fixed-income/investments',
        {
          params:
          {
            execution_mode: 'automated',
            status: 'failed'
          }
        })).data.data?.data || []
    }
    catch (exception) {
      error.value = exception.response?.data?.message || 'Unable to load reconciliation queue.'
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
      <h1 class="mt-2 text-3xl font-semibold text-white">
        Provider reconciliation
      </h1>
      <p class="mt-1 text-gray-400">
        Automated investments requiring provider review.
      </p>
    </div>
    <p v-if="error" class="text-red-400">{{ error }}</p>
    <SkeletonLoader v-if="loading" type="table" :count="6" class="opacity-40" />
    <div v-else class="overflow-x-auto rounded-xl border border-[#1f3348] bg-[#0F1724]">
      <table class="min-w-full text-left text-sm">
        <thead class="text-gray-500">
          <tr>
            <th class="p-4">Reference</th>
            <th class="p-4">Provider</th>
            <th class="p-4">Status</th>
            <th class="p-4">Provider reference</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in investments" :key="item.id" class="border-t border-[#1f3348] text-white">
            <td class="p-4">{{ item.reference }}</td>
            <td class="p-4">{{ item.provider || '-' }}</td>
            <td class="p-4">{{ fixedIncomeLabel(item.status) }}</td>
            <td class="p-4">{{ item.provider_reference || '-' }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="!investments.length" class="p-8 text-center text-gray-500">No failed automated executions.</p>
    </div>
  </div>
  </MainLayout>
</template>
