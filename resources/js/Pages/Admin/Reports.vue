<template>
  <MainLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-white">Admin Reports & Statements</h1>
        <p class="text-sm text-gray-400">Generate and download administrative reports on users, transactions, trading, compliance, and system data.</p>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-1">
          <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
            <h2 class="mb-4 text-lg font-medium text-white">Generate New Report</h2>

            <div class="space-y-4">
              <div>
                <label class="text-xs tracking-wider text-gray-400 uppercase">Report Category</label>
                <select v-model="form.category" @change="resetSubcategory" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                  <option value="transactions">Transactions</option>
                  <option value="compliance">Compliance</option>
                </select>
              </div>

              <div v-if="form.category === 'transactions'">
                <label class="text-xs tracking-wider text-gray-400 uppercase">Sub-Category</label>
                <select v-model="form.subcategory" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                  <option value="deposits">Deposits</option>
                  <option value="withdrawals">Withdrawals</option>
                  <option value="audit_trail">Audit Trail</option>
                </select>
              </div>

              <div v-if="form.category === 'compliance'">
                <label class="text-xs tracking-wider text-gray-400 uppercase">Sub-Category</label>
                <select v-model="form.subcategory" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                  <option value="audit_trail">Audit Trail</option>
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
                    <span class="text-sm text-white">CSV</span>
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
              <h2 class="text-lg font-medium text-white">Report Preview</h2>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="text-xs text-gray-400 bg-black/20">
                  <tr>
                    <th class="px-6 py-4 text-left">Date</th>
                    <th class="px-6 py-4 text-left">Reference</th>
                    <th class="px-6 py-4 text-left">User</th>
                    <th class="px-6 py-4 text-right">Amount</th>
                    <th class="px-6 py-4 text-center">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#1f3348]">
                  <tr v-if="!reportData || reportData.length === 0">
                    <td colspan="5" class="px-6 py-10 italic text-center text-gray-500">No report generated yet. Select filters and click Generate Report.</td>
                  </tr>
                  <tr v-for="row in reportData" :key="row.id" class="hover:bg-[#16213A] transition">
                    <td class="px-6 py-4 text-gray-300">{{ row.created_at || 'N/A' }}</td>
                    <td class="px-6 py-4 text-gray-300">{{ row.reference || 'N/A' }}</td>
                    <td class="px-6 py-4 text-gray-300">{{ row.user?.name || 'N/A' }}</td>
                    <td class="px-6 py-4 text-right text-white font-medium">{{ (row.amount || 0).toFixed(2) }}</td>
                    <td class="px-6 py-4 text-center">
                      <span :class="{
                        'text-green-400': row.status === 'completed',
                        'text-yellow-400': row.status === 'pending',
                        'text-red-400': row.status === 'failed'
                      }" class="text-xs font-medium">
                        {{ row.status || 'Pending' }}
                      </span>
                    </td>
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
import { ref, reactive, onMounted } from 'vue';
import MainLayout from "@/Layouts/MainLayout.vue";
import SuccessModal from "@/Components/SuccessModal.vue";
import ErrorModal from "@/Components/ErrorModal.vue";
import WarningModal from "@/Components/WarningModal.vue";
import api from "@/api";
import axios from 'axios';

const loading = ref(false);
const reportHistory = ref([]);
const showSuccessModal = ref(false);
const showErrorModal = ref(false);
const showWarningModal = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const warningMessage = ref('');
const reportData = ref([]);

const form = reactive({
  category: 'transactions',
  subcategory: 'deposits',
  start_date: '',
  end_date: '',
  format: 'pdf'
});

const resetSubcategory = () => {
  const defaults = {
    transactions: 'deposits',
    compliance: 'audit_trail'
  };
  form.subcategory = defaults[form.category] || 'deposits';
};


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
      format: 'json',
    };

    let endpoint = '/admin/reports/deposits';
    if (form.subcategory === 'withdrawals') {
      endpoint = '/admin/reports/withdrawals';
    } else if (form.subcategory === 'audit_trail') {
      endpoint = '/admin/reports/audit-trail';
    }

    const response = await api.get(endpoint, { params });
    reportData.value = response.data;
    successMessage.value = "Report loaded successfully";
    showSuccessModal.value = true;
  } catch (e) {
    console.error(e);
    errorMessage.value = "Error generating report";
    showErrorModal.value = true;
  } finally {
    loading.value = false;
  }
};

const downloadReport = async () => {
  try {
    const params = {
      from: form.start_date,
      to: form.end_date,
      format: form.format,
    };

    let endpoint = '/admin/reports/deposits';
    if (form.subcategory === 'withdrawals') {
      endpoint = '/admin/reports/withdrawals';
    } else if (form.subcategory === 'audit_trail') {
      endpoint = '/admin/reports/audit-trail';
    }

    const response = await axios.get(endpoint, { 
      params: params,
      responseType: 'blob'
    });
    
    const blob = new Blob([response.data], { 
      type: form.format === 'pdf' ? 'application/pdf' : 'text/csv'
    });
    
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `report-${form.subcategory}-${form.start_date}-to-${form.end_date}.${form.format}`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    
    successMessage.value = "Report downloaded successfully";
    showSuccessModal.value = true;
  } catch (downloadError) {
    console.error('Download error:', downloadError);
    errorMessage.value = "Error downloading report";
    showErrorModal.value = true;
  }
};
</script>