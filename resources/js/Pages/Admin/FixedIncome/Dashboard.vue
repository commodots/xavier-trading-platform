<script setup>
import { ref, onMounted } from 'vue';
import api from '@/api';
import MainLayout from "@/Layouts/MainLayout.vue";

const loading = ref(true)
const dashboard = ref({})

const loadDashboard = async () => {
    loading.value = true

    try {
        const response = await api.get(
            '/admin/fixed-income/dashboard'
        )

        dashboard.value =
            response.data.data || {}
    } finally {
        loading.value = false
    }
}

onMounted(loadDashboard)
</script>

<template>
  <MainLayout>
    <div class="space-y-6">

        <div>
            <h1 class="text-2xl font-semibold text-white">
                Fixed Income
            </h1>

            <p class="text-gray-400 mt-1">
                Fixed Income investment dashboard
            </p>
        </div>

        <div
            class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4"
        >
            <div
                v-for="card in [
                    ['Total Invested', dashboard.total_invested],
                    ['Active', dashboard.active_investments],
                    ['Pending Execution', dashboard.pending_execution],
                    ['Maturing', dashboard.maturing],
                    ['Matured', dashboard.matured],
                    ['Expected Interest', dashboard.total_expected_interest],
                    ['Interest Paid', dashboard.total_interest_paid],
                    ['Failed', dashboard.failed_executions]
                ]"
                :key="card[0]"
                class="rounded-xl p-5 bg-[#111827] border border-[#1F2A44]"
            >
                <div class="text-sm text-gray-400">
                    {{ card[0] }}
                </div>

                <div class="text-2xl font-semibold text-white mt-2">
                    {{ card[1] ?? 0 }}
                </div>
            </div>
        </div>

    </div>
  </MainLayout>
</template>