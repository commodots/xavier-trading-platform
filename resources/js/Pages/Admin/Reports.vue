<template>
  <MainLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-white">Admin Reports & Statements</h1>
        <p class="text-sm text-gray-400">Generate and download administrative reports on users, transactions, trading, compliance, and system data.</p>
      </div>

      <!-- Tab Navigation -->
      <div class="flex gap-1 bg-[#0F1724] border border-[#1f3348] rounded-xl p-1 w-fit">
        <button @click="activeTab = 'reports'" :class="activeTab === 'reports' ? 'bg-[#0047AB] text-white' : 'text-gray-400 hover:text-white'" class="px-4 py-2 rounded-lg text-sm font-medium transition">Generate Reports</button>
        <button @click="activeTab = 'send'" :class="activeTab === 'send' ? 'bg-[#0047AB] text-white' : 'text-gray-400 hover:text-white'" class="px-4 py-2 rounded-lg text-sm font-medium transition">Send Report to Users</button>
      </div>

      <!-- Generate Reports Tab -->
      <div v-if="activeTab === 'reports'" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
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
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="form.format" value="excel" class="text-blue-500" />
                    <span class="text-sm text-white">Excel</span>
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
            <div class="p-6 border-b border-[#1f3348] flex items-center justify-between">
              <h2 class="text-lg font-medium text-white">Report Preview</h2>
              <div v-if="hasData && !form.sendToUser" class="flex gap-2">
                <button @click="downloadReport('pdf')" class="px-3 py-1.5 bg-red-600 text-white text-xs rounded-lg hover:bg-red-700">PDF</button>
                <button @click="downloadReport('csv')" class="px-3 py-1.5 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700">CSV</button>
                <button @click="downloadReport('excel')" class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700">Excel</button>
              </div>
            </div>

            <div class="overflow-x-auto">
              <!-- Audit Trail Table -->
              <table v-if="form.subcategory === 'audit_trail'" class="w-full text-sm">
                <thead class="text-xs text-gray-400 bg-black/20">
                  <tr>
                    <th class="px-6 py-4 text-left">Timestamp</th>
                    <th class="px-6 py-4 text-left">User</th>
                    <th class="px-6 py-4 text-left">Action</th>
                    <th class="px-6 py-4 text-left">Entity</th>
                    <th class="px-6 py-4 text-left">IP</th>
                    <th class="px-6 py-4 text-center">Details</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#1f3348]">
                  <tr v-if="!hasData">
                    <td colspan="6" class="px-6 py-10 italic text-center text-gray-500">No report generated yet. Select filters and click Generate Report.</td>
                  </tr>
                  <tr v-for="row in auditRows" :key="row.id" class="hover:bg-[#16213A] transition">
                    <td class="px-6 py-4 text-gray-300 text-xs">{{ row.created_at || 'N/A' }}</td>
                    <td class="px-6 py-4 text-gray-300">{{ row.user?.name || 'System' }}</td>
                    <td class="px-6 py-4">
                      <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-900/30 text-blue-400 border border-blue-500/30">{{ row.action }}</span>
                    </td>
                    <td class="px-6 py-4 text-gray-300 text-xs">{{ row.entity_type }} #{{ row.entity_id }}</td>
                    <td class="px-6 py-4 text-gray-400 text-xs font-mono">{{ row.ip_address || 'N/A' }}</td>
                    <td class="px-6 py-4 text-center">
                      <button @click="viewAuditDetail(row)" class="text-blue-400 hover:text-blue-300 text-xs font-medium">View</button>
                    </td>
                  </tr>
                </tbody>
              </table>

              <!-- Deposits / Withdrawals Table -->
              <table v-else class="w-full text-sm">
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
                  <tr v-if="!hasData">
                    <td colspan="5" class="px-6 py-10 italic text-center text-gray-500">No report generated yet. Select filters and click Generate Report.</td>
                  </tr>
                  <tr v-for="row in transactionRows" :key="row.id" class="hover:bg-[#16213A] transition">
                    <td class="px-6 py-4 text-gray-300">{{ formatDate(row.created_at) }}</td>
                    <td class="px-6 py-4 text-gray-300 font-mono text-xs">{{ row.reference || 'N/A' }}</td>
                    <td class="px-6 py-4 text-gray-300">
                      <div>{{ row.user?.name || 'N/A' }}</div>
                      <div class="text-[10px] text-gray-500">{{ row.user?.email || '' }}</div>
                    </td>
                    <td class="px-6 py-4 text-right text-white font-medium">{{ Number(row.amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}</td>
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

      <!-- Send Report to Users Tab -->
      <div v-if="activeTab === 'send'" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Send to Specific User -->
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
          <h2 class="mb-4 text-lg font-medium text-white">Send to Specific User</h2>
          <div class="space-y-4">
            <div>
              <label class="text-xs tracking-wider text-gray-400 uppercase">Search User</label>
              <input type="text" v-model="userSearchQuery" @input="searchUsers" placeholder="Type user name or email..." class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm" />
              <div v-if="userSearchResults.length > 0" class="mt-1 bg-[#16213A] border border-gray-700 rounded-lg overflow-hidden">
                <div v-for="user in userSearchResults" :key="user.id" @click="selectUser(user)" class="px-3 py-2 text-white text-sm hover:bg-[#1f3348] cursor-pointer border-b border-gray-700 last:border-b-0">
                  {{ user.name }} ({{ user.email }})
                </div>
              </div>
              <div v-if="selectedUser" class="mt-2 flex items-center justify-between bg-[#1f3348] rounded-lg px-3 py-2">
                <span class="text-white text-sm">{{ selectedUser.name }}</span>
                <button @click="clearSelectedUser" class="text-red-400 text-xs hover:text-red-300">Remove</button>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="text-xs tracking-wider text-gray-400 uppercase">From</label>
                <input type="date" v-model="sendForm.start_date" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm" />
              </div>
              <div>
                <label class="text-xs tracking-wider text-gray-400 uppercase">To</label>
                <input type="date" v-model="sendForm.end_date" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm" />
              </div>
            </div>
            <div>
              <label class="text-xs tracking-wider text-gray-400 uppercase">Report Type</label>
              <select v-model="sendForm.reportType" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                <option value="account_statement">Account Statement</option>
                <option value="trading_performance">Trading Performance</option>
              </select>
            </div>
            <div>
              <label class="text-xs tracking-wider text-gray-400 uppercase">Export Format</label>
              <div class="flex gap-4 mt-2">
                <label class="flex items-center gap-2 cursor-pointer"><input type="radio" v-model="sendForm.format" value="pdf" class="text-blue-500" /><span class="text-sm text-white">PDF</span></label>
                <label class="flex items-center gap-2 cursor-pointer"><input type="radio" v-model="sendForm.format" value="csv" class="text-blue-500" /><span class="text-sm text-white">CSV</span></label>
                <label class="flex items-center gap-2 cursor-pointer"><input type="radio" v-model="sendForm.format" value="excel" class="text-blue-500" /><span class="text-sm text-white">Excel</span></label>
              </div>
            </div>
            <div>
              <label class="text-xs tracking-wider text-gray-400 uppercase">Message (optional)</label>
              <textarea v-model="sendForm.adminMessage" rows="2" placeholder="Add a personal message..." class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm"></textarea>
            </div>
            <button @click="sendReportToSpecificUser" :disabled="loading || !selectedUser" class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-3 rounded-lg font-bold text-white mt-4 hover:opacity-90 disabled:opacity-50 transition">
              {{ loading ? 'Sending...' : 'Send Report to User' }}
            </button>
          </div>
        </div>

        <!-- Send to All Users -->
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
          <h2 class="mb-4 text-lg font-medium text-white">Send to All Users</h2>
          <p class="text-sm text-gray-400 mb-4">Generate and send monthly account statements or trading performance reports to all platform users at once.</p>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="text-xs tracking-wider text-gray-400 uppercase">From</label>
                <input type="date" v-model="bulkForm.start_date" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm" />
              </div>
              <div>
                <label class="text-xs tracking-wider text-gray-400 uppercase">To</label>
                <input type="date" v-model="bulkForm.end_date" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm" />
              </div>
            </div>
            <div>
              <label class="text-xs tracking-wider text-gray-400 uppercase">Report Type</label>
              <select v-model="bulkForm.reportType" class="w-full mt-1 bg-[#16213A] border border-gray-700 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                <option value="account_statement">Account Statement</option>
                <option value="trading_performance">Trading Performance</option>
              </select>
            </div>
            <div>
              <label class="text-xs tracking-wider text-gray-400 uppercase">Export Format</label>
              <div class="flex gap-4 mt-2">
                <label class="flex items-center gap-2 cursor-pointer"><input type="radio" v-model="bulkForm.format" value="pdf" class="text-blue-500" /><span class="text-sm text-white">PDF</span></label>
                <label class="flex items-center gap-2 cursor-pointer"><input type="radio" v-model="bulkForm.format" value="csv" class="text-blue-500" /><span class="text-sm text-white">CSV</span></label>
                <label class="flex items-center gap-2 cursor-pointer"><input type="radio" v-model="bulkForm.format" value="excel" class="text-blue-500" /><span class="text-sm text-white">Excel</span></label>
              </div>
            </div>
            <div class="p-3 border bg-yellow-900/10 border-yellow-500/20 rounded-lg">
              <p class="text-xs text-yellow-400">This will generate and send reports to <strong>all registered users</strong>. This may take some time depending on the number of users.</p>
            </div>
            <button @click="sendReportToAllUsers" :disabled="loading" class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-3 rounded-lg font-bold text-white mt-4 hover:opacity-90 disabled:opacity-50 transition">
              {{ loading ? 'Sending to All Users...' : 'Send Report to All Users' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Audit Detail Modal -->
    <div v-if="showAuditModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4">
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl max-w-3xl w-full max-h-[90vh] flex flex-col shadow-2xl">
        <div class="p-6 border-b border-[#1f3348] flex items-center justify-between">
          <h3 class="text-lg font-bold text-white">Audit Trail Details</h3>
          <button @click="showAuditModal = false" class="text-gray-400 hover:text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>
        <div class="p-6 overflow-auto flex-1 space-y-4">
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="text-gray-400">Timestamp:</span><span class="text-white ml-2">{{ selectedAudit?.created_at }}</span></div>
            <div><span class="text-gray-400">Action:</span><span class="text-blue-400 ml-2 font-medium">{{ selectedAudit?.action }}</span></div>
            <div><span class="text-gray-400">Entity:</span><span class="text-white ml-2">{{ selectedAudit?.entity_type }} #{{ selectedAudit?.entity_id }}</span></div>
            <div><span class="text-gray-400">User:</span><span class="text-white ml-2">{{ selectedAudit?.user?.name || 'System' }}</span></div>
            <div><span class="text-gray-400">IP Address:</span><span class="text-white ml-2 font-mono">{{ selectedAudit?.ip_address || 'N/A' }}</span></div>
            <div><span class="text-gray-400">User Agent:</span><span class="text-white ml-2 text-xs truncate">{{ selectedAudit?.user_agent || 'N/A' }}</span></div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <h4 class="text-sm font-bold text-gray-300 mb-2 uppercase tracking-wider">Old Values</h4>
              <div v-if="selectedAudit?.old_values && Object.keys(selectedAudit.old_values).length > 0" class="bg-[#16213A] border border-[#1f3348] rounded-lg p-3">
                <div v-for="(val, key) in selectedAudit.old_values" :key="key" class="flex justify-between py-1 border-b border-[#1f3348] last:border-b-0">
                  <span class="text-gray-400 text-xs">{{ key }}:</span>
                  <span class="text-white text-xs ml-2 text-right">{{ formatValue(val) }}</span>
                </div>
              </div>
              <div v-else class="bg-[#16213A] border border-[#1f3348] rounded-lg p-4 text-center text-gray-500 text-sm">No old values recorded</div>
            </div>
            <div>
              <h4 class="text-sm font-bold text-gray-300 mb-2 uppercase tracking-wider">New Values</h4>
              <div v-if="selectedAudit?.new_values && Object.keys(selectedAudit.new_values).length > 0" class="bg-[#16213A] border border-[#1f3348] rounded-lg p-3">
                <div v-for="(val, key) in selectedAudit.new_values" :key="key" class="flex justify-between py-1 border-b border-[#1f3348] last:border-b-0">
                  <span class="text-gray-400 text-xs">{{ key }}:</span>
                  <span class="text-white text-xs ml-2 text-right">{{ formatValue(val) }}</span>
                </div>
              </div>
              <div v-else class="bg-[#16213A] border border-[#1f3348] rounded-lg p-4 text-center text-gray-500 text-sm">No new values recorded</div>
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
import { ref, reactive, computed } from 'vue';
import MainLayout from "@/Layouts/MainLayout.vue";
import SuccessModal from "@/Components/SuccessModal.vue";
import ErrorModal from "@/Components/ErrorModal.vue";
import WarningModal from "@/Components/WarningModal.vue";
import api from "@/api";

const activeTab = ref('reports');
const loading = ref(false);
const showSuccessModal = ref(false);
const showErrorModal = ref(false);
const showWarningModal = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const warningMessage = ref('');
const reportData = ref([]);
const showAuditModal = ref(false);
const selectedAudit = ref(null);
const userSearchQuery = ref('');
const userSearchResults = ref([]);
const selectedUser = ref(null);

const form = reactive({
  category: 'transactions',
  subcategory: 'deposits',
  start_date: '',
  end_date: '',
  format: 'pdf',
  sendToUser: false,
  reportType: 'account_statement',
  adminMessage: '',
});

const sendForm = reactive({
  start_date: '',
  end_date: '',
  format: 'pdf',
  reportType: 'account_statement',
  adminMessage: '',
});

const bulkForm = reactive({
  start_date: '',
  end_date: '',
  format: 'pdf',
  reportType: 'account_statement',
  adminMessage: '',
});

const hasData = computed(() => {
  if (form.subcategory === 'audit_trail') {
    return auditRows.value.length > 0;
  }
  return transactionRows.value.length > 0;
});

const transactionRows = computed(() => {
  const data = reportData.value;
  if (!data) return [];
  if (data.data) return data.data;
  if (Array.isArray(data)) return data;
  return [];
});

const auditRows = computed(() => {
  const data = reportData.value;
  if (!data) return [];
  if (data.data) return data.data;
  if (Array.isArray(data)) return data;
  return [];
});

const resetSubcategory = () => {
  const defaults = { transactions: 'deposits', compliance: 'audit_trail' };
  form.subcategory = defaults[form.category] || 'deposits';
};

const clearSelectedUser = () => {
  selectedUser.value = null;
  userSearchQuery.value = '';
  userSearchResults.value = [];
};

const searchUsers = async () => {
  if (userSearchQuery.value.length < 2) {
    userSearchResults.value = [];
    return;
  }
  try {
    const response = await api.get('/admin/reports/search-users', { params: { query: userSearchQuery.value } });
    userSearchResults.value = response.data.users || [];
  } catch (e) {
    console.error('User search error:', e);
  }
};

const selectUser = (user) => {
  selectedUser.value = user;
  userSearchQuery.value = '';
  userSearchResults.value = [];
};

const generateReport = async () => {
  if (!form.start_date || !form.end_date) {
    warningMessage.value = "Please select a date range";
    showWarningModal.value = true;
    return;
  }

  if (form.sendToUser && !selectedUser.value) {
    warningMessage.value = "Please select a user to send the report to";
    showWarningModal.value = true;
    return;
  }

  loading.value = true;
  try {
    if (form.sendToUser) {
      // Send report to user via API
      const response = await api.post('/admin/reports/send-to-user', {
        user_id: selectedUser.value.id,
        from: form.start_date,
        to: form.end_date,
        format: form.format,
        report_type: form.reportType,
        wallet: 'all',
        message: form.adminMessage || null,
      });
      successMessage.value = response.data.message || "Report sent to user successfully";
      showSuccessModal.value = true;
      reportData.value = [];
      return;
    }

    const params = { from: form.start_date, to: form.end_date, format: 'json' };
    let endpoint = '/admin/reports/deposits';
    if (form.subcategory === 'withdrawals') endpoint = '/admin/reports/withdrawals';
    else if (form.subcategory === 'audit_trail') endpoint = '/admin/reports/audit-trail';

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

const downloadReport = async (format) => {
  loading.value = true;
  try {
    const params = { from: form.start_date, to: form.end_date, format: format };
    let endpoint = '/admin/reports/deposits';
    if (form.subcategory === 'withdrawals') endpoint = '/admin/reports/withdrawals';
    else if (form.subcategory === 'audit_trail') endpoint = '/admin/reports/audit-trail';

    const response = await api.get(endpoint, { params, responseType: 'blob' });
    
    const mimeTypes = { pdf: 'application/pdf', csv: 'text/csv', excel: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' };
    const blob = new Blob([response.data], { type: mimeTypes[format] || 'application/octet-stream' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    const ext = format === 'excel' ? 'xlsx' : format;
    link.download = `report-${form.subcategory}-${form.start_date}-to-${form.end_date}.${ext}`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    
    successMessage.value = "Report downloaded successfully";
    showSuccessModal.value = true;
  } catch (e) {
    console.error('Download error:', e);
    errorMessage.value = "Error downloading report";
    showErrorModal.value = true;
  } finally {
    loading.value = false;
  }
};

const viewAuditDetail = (audit) => {
  selectedAudit.value = audit;
  showAuditModal.value = true;
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  try {
    const date = new Date(dateString);
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = date.getHours();
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    const formattedHours = hours % 12 || 12;
    return `${month}/${day} ${formattedHours}:${minutes} ${ampm}`;
  } catch (e) {
    return dateString;
  }
};

const formatValue = (val) => {
  if (val === null || val === undefined) return 'null';
  if (typeof val === 'object') return JSON.stringify(val);
  return String(val);
};

const sendReportToSpecificUser = async () => {
  if (!sendForm.start_date || !sendForm.end_date) {
    warningMessage.value = "Please select a date range";
    showWarningModal.value = true;
    return;
  }
  if (!selectedUser.value) {
    warningMessage.value = "Please select a user";
    showWarningModal.value = true;
    return;
  }

  loading.value = true;
  try {
    const response = await api.post('/admin/reports/send-to-user', {
      user_id: selectedUser.value.id,
      from: sendForm.start_date,
      to: sendForm.end_date,
      format: sendForm.format,
      report_type: sendForm.reportType,
      wallet: 'all',
      message: sendForm.adminMessage || null,
    });
    successMessage.value = response.data.message || "Report sent successfully";
    showSuccessModal.value = true;
    clearSelectedUser();
  } catch (e) {
    console.error(e);
    errorMessage.value = "Error sending report";
    showErrorModal.value = true;
  } finally {
    loading.value = false;
  }
};

const sendReportToAllUsers = async () => {
  if (!bulkForm.start_date || !bulkForm.end_date) {
    warningMessage.value = "Please select a date range";
    showWarningModal.value = true;
    return;
  }

  loading.value = true;
  try {
    const response = await api.post('/admin/reports/send-to-all-users', {
      from: bulkForm.start_date,
      to: bulkForm.end_date,
      format: bulkForm.format,
      report_type: bulkForm.reportType,
      wallet: 'all',
      message: bulkForm.adminMessage || null,
    });
    successMessage.value = response.data.message || "Report sent to all users";
    showSuccessModal.value = true;
  } catch (e) {
    console.error(e);
    errorMessage.value = "Error sending report to all users";
    showErrorModal.value = true;
  } finally {
    loading.value = false;
  }
};
</script>
