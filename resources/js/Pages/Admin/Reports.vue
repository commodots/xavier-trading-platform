<template>
  <MainLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-white">Admin Reports & Statements</h1>
        <p class="text-sm text-gray-400">Generate and download administrative reports on users, transactions, trading, compliance, and system data.</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-6">
          <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
            <h2 class="text-lg font-medium mb-4 text-white">Generate New Report</h2>

            <div class="space-y-4">
              <div>
                <label class="text-xs text-gray-400 uppercase tracking-wider">Report Category</label>
                <select v-model="form.category" @change="resetSubcategory" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                  <option value="users">Users</option>
                  <option value="transactions">Transactions</option>
                  <option value="trading">Trading</option>
                  <option value="compliance">Compliance</option>
                  <option value="system">System</option>
                </select>
              </div>

              <div v-if="form.category === 'users'">
                <label class="text-xs text-gray-400 uppercase tracking-wider">Sub-Category</label>
                <select v-model="form.subcategory" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                  <option value="registrations">Registrations</option>
                  <option value="kyc_status">KYC Status</option>
                  <option value="activity">Wallet Balances</option>
                </select>
              </div>

              <div v-if="form.category === 'transactions'">
                <label class="text-xs text-gray-400 uppercase tracking-wider">Sub-Category</label>
                <select v-model="form.subcategory" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                  <option value="deposits">Deposits & Withdrawals</option>
                  <option value="fees">Fees</option>
                  <option value="withdrawals">Reconciliation</option>
                </select>
              </div>

              <div v-if="form.category === 'trading'">
                <label class="text-xs text-gray-400 uppercase tracking-wider">Sub-Category</label>
                <select v-model="form.subcategory" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                  <option value="orders">Orders</option>
                  <option value="trades">Trades</option>
                  <option value="performance">Market Volume</option>
                </select>
              </div>

              <div v-if="form.category === 'compliance'">
                <label class="text-xs text-gray-400 uppercase tracking-wider">Sub-Category</label>
                <select v-model="form.subcategory" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                  <option value="kyc_reviews">KYC Logs</option>
                  <option value="suspicious_activity">AML Flags</option>
                  <option value="audit_trail">Audit Trail</option>
                </select>
              </div>

              <div v-if="form.category === 'system'">
                <label class="text-xs text-gray-400 uppercase tracking-wider">Sub-Category</label>
                <select v-model="form.subcategory" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                  <option value="error_logs">Service Health</option>
                  <option value="system_health">OMS Metrics</option>
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
                    <th class="px-6 py-4 text-left">Category</th>
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
                    <td class="px-6 py-4 text-gray-400 capitalize">{{ report.category }}</td>
                    <td class="px-6 py-4 text-gray-400">{{ report.period }}</td>
                    <td class="px-6 py-4 text-center">
                      <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-gray-800 border border-gray-600 text-white">
                        {{ report.format }}
                      </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                      <button class="text-blue-400 hover:text-blue-300 font-medium">Download</button>
                    </td>
                  </tr>
                  <tr v-if="reportHistory.length === 0">
                    <td colspan="5" class="px-6 py-10 text-center text-gray-500 italic">No reports generated yet.</td>
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
import { ref, reactive, onMounted } from 'vue';
import MainLayout from "@/Layouts/MainLayout.vue";
import api from "@/api";

const loading = ref(false);
const reportHistory = ref([]);

const form = reactive({
  category: 'users',
  subcategory: 'registrations',
  start_date: '',
  end_date: '',
  format: 'pdf'
});

// Fix 1: Reset subcategory when main category changes
const resetSubcategory = () => {
  const defaults = {
    users: 'registrations',
    transactions: 'deposits',
    trading: 'orders',
    compliance: 'kyc_reviews',
    system: 'error_logs'
  };
  form.subcategory = defaults[form.category];
};

const generateReport = async () => {
  if (!form.start_date || !form.end_date) {
    alert("Please select a date range");
    return;
  }

  loading.value = true;
  try {
    const response = await api.post('/admin/reports/generate', form);
    alert("Report generation started! Check history in a moment.");
    // Wait a second then refresh history
    setTimeout(refreshHistory, 1000);
  } catch (e) {
    console.error(e);
    alert("Error generating report");
  } finally {
    loading.value = false;
  }
};

const refreshHistory = async () => {
  try {
    const response = await api.get('/admin/reports/history');
    reportHistory.value = response.data.reports || [];
  } catch (e) {
    console.error("Failed to fetch history:", e);
  }
};

</script>