<template>
  <MainLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold">Reports & Statements</h1>
        <p class="text-sm text-gray-400">Generate and download your transaction or trading history.</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-6">
          <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
            <h2 class="text-lg font-medium mb-4 text-white">Generate New Report</h2>
            
            <div class="space-y-4">
              <div>
                <label class="text-xs text-gray-400 uppercase tracking-wider">Report Type</label>
                <select v-model="form.type" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                  <option value="statement">Statement of Account</option>
                  <option value="trading">Trading Performance Report</option>
                </select>
              </div>

              <div>
                <label class="text-xs text-gray-400 uppercase tracking-wider">Wallet / Account</label>
                <select v-model="form.wallet" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                  <option value="all">All Wallets</option>
                  <option value="NGN">NGN Wallet</option>
                  <option value="USD">USD Wallet</option>
                </select>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="text-xs text-gray-400 uppercase tracking-wider">From</label>
                  <input type="date" v-model="form.start_date" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm" />
                </div>
                <div>
                  <label class="text-xs text-gray-400 uppercase tracking-wider">To</label>
                  <input type="date" v-model="form.end_date" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm" />
                </div>
              </div>

              <div>
                <label class="text-xs text-gray-400 uppercase tracking-wider">Export Format</label>
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

          <div class="bg-blue-900/10 border border-blue-500/20 p-4 rounded-xl">
            <h4 class="text-blue-400 text-sm font-bold flex items-center gap-2">
              <span>&#x24D8;</span> Note
            </h4>
            <p class="text-xs text-gray-400 mt-1 leading-relaxed">
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
                <thead class="bg-black/20 text-gray-400 text-xs">
                  <tr>
                    <th class="px-6 py-4 text-left">Report Name</th>
                    <th class="px-6 py-4 text-left">Period</th>
                    <th class="px-6 py-4 text-left">Format</th>
                    <th class="px-6 py-4 text-right">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#1f3348]">
                  <tr v-for="report in reportHistory" :key="report.id" class="hover:bg-[#16213A] transition">
                    <td class="px-6 py-4">
                      <div class="text-white font-medium">{{ report.name }}</div>
                      <div class="text-[10px] text-gray-500">{{ report.created_at }}</div>
                    </td>
                    <td class="px-6 py-4 text-gray-400">{{ report.period }}</td>
                    <td class="px-6 py-4 text-center">
                      <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-gray-800 border border-gray-600">
                        {{ report.format }}
                      </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                      <button class="text-blue-400 hover:text-blue-300 font-medium">Download</button>
                    </td>
                  </tr>
                  <tr v-if="reportHistory.length === 0">
                    <td colspan="4" class="px-6 py-10 text-center text-gray-500 italic">No reports generated yet.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import MainLayout from "@/Layouts/MainLayout.vue";
import api from "@/api";

const loading = ref(false);
const reportHistory = ref([]); 

const form = reactive({
  type: 'statement',
  wallet: 'all',
  start_date: '',
  end_date: '',
  format: 'pdf'
});

const generateReport = async () => {
  if (!form.start_date || !form.end_date) {
    alert("Please select a date range");
    return;
  }

  loading.value = true;
  try {
    const response = await api.post('/reports/generate', form);
    alert("Report generation started! Check history in a moment.");
    refreshHistory();
  } catch (e) {
    console.error(e);
    alert("Error generating report");
  } finally {
    loading.value = false;
  }
};
</script>