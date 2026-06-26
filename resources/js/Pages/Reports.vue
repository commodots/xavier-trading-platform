<template>
  <MainLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold">Reports & Statements</h1>
        <p class="text-sm text-gray-400">Generate and download your transaction or trading history.</p>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-1">
          <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
            <h2 class="mb-4 text-lg font-medium text-white">Generate New Report</h2>
            
            <div class="space-y-4">
              <div>
                <label class="text-xs tracking-wider text-gray-400 uppercase">Report Type</label>
                <select v-model="form.type" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                  <option value="statement">Statement of Account</option>
                  <option value="trading">Trading Performance Report</option>
                </select>
              </div>

              <div>
                <label class="text-xs tracking-wider text-gray-400 uppercase">Wallet / Account</label>
                <select v-model="form.wallet" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                  <option value="all">All Wallets</option>
                  <option value="NGN">NGN Wallet</option>
                  <option value="USD">USD Wallet</option>
                </select>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="text-xs tracking-wider text-gray-400 uppercase">From</label>
                  <input type="date" v-model="form.start_date" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm" />
                </div>
                <div>
                  <label class="text-xs tracking-wider text-gray-400 uppercase">To</label>
                  <input type="date" v-model="form.end_date" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm" />
                </div>
              </div>

              <div>
                <label class="text-xs tracking-wider text-gray-400 uppercase">Export Format</label>
                <div class="flex gap-4 mt-2">
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="form.format" value="pdf" class="text-blue-500" />
                    <span class="text-sm text-white">PDF</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="form.format" value="csv" class="text-blue-500" />
                    <span class="text-sm text-white">CSV (Excel)</span>
                  </label>
                </div>
              </div>

              <button 
                @click="generateReport"
                :disabled="loading"
                class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-3 rounded-lg font-bold text-white mt-4 hover:opacity-90 disabled:opacity-50 transition"
              >
                {{ loading ? 'Generating...' : 'Generate Report' }}
              </button>
            </div>
          </div>

          <div class="p-4 border bg-blue-900/10 border-blue-500/20 rounded-xl">
            <h4 class="flex items-center gap-2 text-sm font-bold text-blue-400">
              <span>&#x24D8;</span> Note
            </h4>
            <p class="mt-1 text-xs leading-relaxed text-gray-400">
              Reports may take a few moments to compile. You will receive an email once your report is ready for download if it takes longer than 30 seconds.
            </p>
          </div>
        </div>

        <div class="lg:col-span-2">
          <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl overflow-hidden">
            <div class="p-6 border-b border-[#1f3348]">
              <h2 class="text-lg font-medium text-white">Recent Downloads</h2>
            </div>
            
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="text-xs text-gray-400 bg-black/20">
                  <tr>
                    <th class="px-6 py-4 text-left">Report Name</th>
                    <th class="px-6 py-4 text-left">Period</th>
                    <th class="px-6 py-4 text-center">Format</th>
                    <th class="px-6 py-4 text-right">Action</th>
                  </tr>
                </thead>

                <!-- SKELETON LOADER STATE -->
                <tbody v-if="loading" class="divide-y divide-[#1f3348]/40">
                  <tr v-for="i in 4" :key="i">
                    <td class="px-6 py-5">
                      <SkeletonLoader class="h-4 mb-2 w-44 bg-gray-700/60" />
                      <SkeletonLoader class="h-3 bg-gray-800 w-28" />
                    </td>
                    <td class="px-6 py-5">
                      <SkeletonLoader class="h-4 w-36 bg-gray-700/50" />
                    </td>
                    <td class="flex items-center justify-center px-6 py-5 pt-6">
                      <SkeletonLoader class="w-10 h-4 bg-gray-800 rounded-md" />
                    </td>
                    <td class="px-6 py-5 text-right">
                      <SkeletonLoader class="inline-block w-16 h-4 bg-gray-800" />
                    </td>
                  </tr>
                </tbody>

                <!-- DATA RENDER STATE -->
                <tbody v-else class="divide-y divide-[#1f3348]">
                  <tr v-for="report in reportHistory" :key="report.id" class="hover:bg-[#16213A] transition">
                    <td class="px-6 py-4">
                      <div class="font-medium text-white">{{ report.name }}</div>
                      <div class="text-[10px] text-gray-500">{{ report.created_at }}</div>
                    </td>
                    <td class="px-6 py-4 text-gray-400">{{ report.period }}</td>
                    <td class="px-6 py-4 text-center">
                      <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-gray-800 border border-gray-600">
                        {{ report.format }}
                      </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                      <button class="font-medium text-blue-400 hover:text-blue-300">Download</button>
                    </td>
                  </tr>
                  <tr v-if="reportHistory.length === 0">
                    <td colspan="4" class="px-6 py-10 italic text-center text-gray-500">No reports generated yet.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <SuccessModal :show="showSuccessModal" :message="successMessage" @close="showSuccessModal = false" />
    <ErrorModal :show="showErrorModal" :message="errorMessage" @close="showErrorModal = false" />
    <WarningModal :show="showWarningModal" :message="warningMessage" @close="showWarningModal = false" />
  </MainLayout>
</template>

  <script setup>
import { ref, reactive } from 'vue';
import MainLayout from "@/Layouts/MainLayout.vue";
import SuccessModal from "@/Components/SuccessModal.vue";
import ErrorModal from "@/Components/ErrorModal.vue";
import WarningModal from "@/Components/WarningModal.vue";
import api from "@/api";
import SkeletonLoader from "@/Components/SkeletonLoader.vue";

const loading = ref(false);
const reportHistory = ref([]); 
const showSuccessModal = ref(false);
const showErrorModal = ref(false);
const showWarningModal = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const warningMessage = ref('');

const form = reactive({
  type: 'statement',
  wallet: 'all',
  start_date: '',
  end_date: '',
  format: 'pdf'
});

const generateReport = async () => {
  if (!form.start_date || !form.end_date) {
    warningMessage.value = "Please select a date range";
    showWarningModal.value = true;
    return;
  }

  loading.value = true;
  try {
    const params = {
      from: form.start_date,
      to: form.end_date,
      format: form.format,
    };

    const response = await api.get('/reports/account-statement', { params });
    
    if (form.format === 'json') {
      successMessage.value = "Statement loaded successfully";
      showSuccessModal.value = true;
    } else {
      successMessage.value = "Report downloaded successfully";
      showSuccessModal.value = true;
    }
  } catch (e) {
    console.error(e);
    errorMessage.value = "Error generating report";
    showErrorModal.value = true;
  } finally {
    loading.value = false;
  }
};
</script>
