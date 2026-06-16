<template>
  <MainLayout>
    <div class="p-6 space-y-6 text-white">
      <!-- HEADER -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Compliance & KYC Dashboard</h1>
          <p class="text-sm text-gray-400">Review KYC submissions, manage verifications, and monitor risk flags</p>
        </div>
      </div>

      <!-- TABS -->
      <div class="flex gap-2 pb-2 border-b border-gray-700">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key; fetchTab(tab.key)"
          class="px-4 py-2 text-sm rounded-t-lg transition"
          :class="activeTab === tab.key ? 'bg-blue-600 text-white' : 'bg-[#1E293B] text-gray-300 hover:bg-[#2a3a55]'"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- SKELETON LOADING -->
      <SkeletonLoader v-if="loading" type="table" :count="6" />

      <!-- ALL KYC TABLE -->
      <div v-else-if="activeTab === 'all'" class="bg-[#111827] rounded-xl border border-[#1F2A44] overflow-hidden">
        <div class="p-4 border-b border-gray-700 flex items-center justify-between">
          <h3 class="font-semibold">All KYC Submissions</h3>
          <span class="text-xs text-gray-400">{{ pagination.total }} total</span>
        </div>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-gray-400 border-b border-gray-700">
              <th class="py-3 px-4">User</th>
              <th class="py-3 px-4">Email</th>
              <th class="py-3 px-4">ID Type</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4">Submitted</th>
              <th class="py-3 px-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="profile in kycs" :key="profile.id" class="border-b border-gray-800 hover:bg-[#1E293B]">
              <td class="py-3 px-4 capitalize">
                <router-link :to="`/admin/kyc-review/${profile.user_id || profile.user?.id}`" class="text-[#00D4FF] underline hover:no-underline">
                  {{ profile.user?.first_name }} {{ profile.user?.last_name }}
                </router-link>
              </td>
              <td class="py-3 px-4">{{ profile.user?.email || '—' }}</td>
              <td class="py-3 px-4 text-gray-300">{{ formatIdType(profile.id_type) }}</td>
              <td class="py-3 px-4">
                <span class="px-2 py-1 text-xs rounded font-bold" :class="statusClass(profile.status)">
                  {{ profile.status || 'pending' }}
                </span>
              </td>
              <td class="py-3 px-4">{{ formatDate(profile.created_at) }}</td>
              <td class="py-3 px-4">
                <div class="flex gap-2" v-if="profile.status === 'pending'">
                  <button @click="openConfirmModal(profile, 'verified')"
                    class="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700 transition">
                    Approve
                  </button>
                  <button @click="openConfirmModal(profile, 'rejected')"
                    class="px-2 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 transition">
                    Reject
                  </button>
                </div>
                <span v-else class="text-xs text-gray-500">—</span>
              </td>
            </tr>
            <tr v-if="!kycs.length">
              <td colspan="6" class="py-8 text-center text-gray-500">No KYC profiles found.</td>
            </tr>
          </tbody>
        </table>

        <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-700">
          <button class="px-3 py-1 text-xs bg-gray-700 rounded disabled:opacity-40"
            :disabled="pagination.current_page <= 1"
            @click="changePage(pagination.current_page - 1)">Prev</button>
          <span class="text-xs text-gray-400">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <button class="px-3 py-1 text-xs bg-gray-700 rounded disabled:opacity-40"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="changePage(pagination.current_page + 1)">Next</button>
        </div>
      </div>

      <!-- PENDING / VERIFIED / REJECTED KYC TABLE -->
      <div v-else-if="activeTab === 'pending' || activeTab === 'verified' || activeTab === 'rejected'" class="bg-[#111827] rounded-xl border border-[#1F2A44] overflow-hidden">
        <div class="p-4 border-b border-gray-700 flex items-center justify-between">
          <h3 class="font-semibold">{{ tabLabel }}</h3>
          <span class="text-xs text-gray-400">{{ pagination.total }} total</span>
        </div>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-gray-400 border-b border-gray-700">
              <th class="py-3 px-4">User</th>
              <th class="py-3 px-4">Email</th>
              <th class="py-3 px-4">ID Type</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4">Submitted</th>
              <th class="py-3 px-4">Reason / Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="profile in kycs" :key="profile.id" class="border-b border-gray-800 hover:bg-[#1E293B]">
              <td class="py-3 px-4 capitalize">
                <router-link :to="`/admin/kyc-review/${profile.user_id || profile.user?.id}`" class="text-[#00D4FF] underline hover:no-underline">
                  {{ profile.user?.first_name }} {{ profile.user?.last_name }}
                </router-link>
              </td>
              <td class="py-3 px-4">{{ profile.user?.email || '—' }}</td>
              <td class="py-3 px-4 text-gray-300">{{ formatIdType(profile.id_type) }}</td>
              <td class="py-3 px-4">
                <span class="px-2 py-1 text-xs rounded font-bold" :class="statusClass(profile.status)">
                  {{ profile.status || 'pending' }}
                </span>
              </td>
              <td class="py-3 px-4">{{ formatDate(profile.created_at) }}</td>
              <td class="py-3 px-4">
                <div v-if="activeTab === 'pending'" class="flex gap-2">
                  <button @click="openConfirmModal(profile, 'verified')"
                    class="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700 transition">
                    Approve
                  </button>
                  <button @click="openConfirmModal(profile, 'rejected')"
                    class="px-2 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 transition">
                    Reject
                  </button>
                </div>
                <span v-else class="text-xs text-gray-500">—</span>
              </td>
            </tr>
            <tr v-if="!kycs.length">
              <td colspan="6" class="py-8 text-center text-gray-500">No KYC profiles found.</td>
            </tr>
          </tbody>
        </table>

        <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-700">
          <button class="px-3 py-1 text-xs bg-gray-700 rounded disabled:opacity-40"
            :disabled="pagination.current_page <= 1"
            @click="changePage(pagination.current_page - 1)">Prev</button>
          <span class="text-xs text-gray-400">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <button class="px-3 py-1 text-xs bg-gray-700 rounded disabled:opacity-40"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="changePage(pagination.current_page + 1)">Next</button>
        </div>
      </div>

      <!-- RISK FLAGS -->
      <div v-else-if="activeTab === 'flags'" class="bg-[#111827] rounded-xl border border-[#1F2A44] overflow-hidden">
        <div class="p-4 border-b border-gray-700 flex items-center justify-between">
          <h3 class="font-semibold">Risk Flags</h3>
          <div class="flex gap-2">
            <select v-model="filterSeverity" @change="fetchTab('flags')"
              class="px-2 py-1 text-xs bg-[#1E293B] border border-gray-700 rounded text-white">
              <option value="">All Severity</option>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="critical">Critical</option>
            </select>
            <select v-model="filterType" @change="fetchTab('flags')"
              class="px-2 py-1 text-xs bg-[#1E293B] border border-gray-700 rounded text-white">
              <option value="">All Types</option>
              <option value="MULTIPLE_KYC_FAILURES">Multiple KYC Failures</option>
              <option value="MULTIPLE_DEVICES">Multiple Devices</option>
              <option value="SUSPICIOUS_WITHDRAWAL">Suspicious Withdrawal</option>
              <option value="HIGH_DEBT">High Debt</option>
            </select>
          </div>
        </div>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-gray-400 border-b border-gray-700">
              <th class="py-3 px-4">User</th>
              <th class="py-3 px-4">Type</th>
              <th class="py-3 px-4">Severity</th>
              <th class="py-3 px-4">Created</th>
              <th class="py-3 px-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="flag in riskFlags" :key="flag.id" class="border-b border-gray-800 hover:bg-[#1E293B]">
              <td class="py-3 px-4 capitalize">{{ flag.user?.first_name }} {{ flag.user?.last_name }}</td>
              <td class="py-3 px-4">
                <span class="font-mono text-xs">{{ flag.type }}</span>
              </td>
              <td class="py-3 px-4">
                <span class="px-2 py-1 text-xs rounded" :class="severityClass(flag.severity)">
                  {{ flag.severity }}
                </span>
              </td>
              <td class="py-3 px-4">{{ formatDate(flag.created_at) }}</td>
              <td class="py-3 px-4">
                <button @click="dismissFlag(flag)"
                  class="px-2 py-1 text-xs text-white transition bg-green-600 rounded hover:bg-green-700">
                  Dismiss
                </button>
              </td>
            </tr>
            <tr v-if="!riskFlags.length">
              <td colspan="5" class="py-8 text-center text-gray-500">No risk flags found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- CONFIRMATION MODAL -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-[#111827] border border-[#1F2A44] rounded-xl max-w-md w-full p-6 shadow-2xl">
          <h2 class="text-lg font-bold text-white mb-2 capitalize">{{ currentDecision }} KYC</h2>
          <p class="text-gray-400 text-sm mb-6">
            Are you sure you want to set the KYC submission status for
            <span class="capitalize text-white">{{ selectedKyc?.user?.first_name }} {{ selectedKyc?.user?.last_name }}</span>
            to <strong :class="currentDecision === 'verified' ? 'text-green-400' : 'text-red-400'">{{ currentDecision }}</strong>?
            This action cannot be undone.
          </p>

          <div v-if="currentDecision === 'rejected'" class="mb-4">
            <label class="block text-sm text-gray-400 mb-1">Rejection Reason <span class="text-red-400">*</span></label>
            <textarea v-model="rejectionReason" rows="3"
              class="w-full px-3 py-2 text-sm bg-[#1E293B] border border-gray-700 rounded text-white resize-none"
              placeholder="Enter the reason for rejection..."></textarea>
          </div>

          <div class="flex justify-end gap-3">
            <button @click="showModal = false" class="px-4 py-2 text-sm text-gray-400 hover:text-white transition" :disabled="submitting">
              Cancel
            </button>
            <button @click="confirmReview" :disabled="submitting || (currentDecision === 'rejected' && !rejectionReason)"
              :class="[
                'px-4 py-2 text-sm font-bold rounded-lg transition flex items-center gap-2',
                currentDecision === 'verified' ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-red-600 hover:bg-red-700 text-white',
                (currentDecision === 'rejected' && !rejectionReason) ? 'opacity-50 cursor-not-allowed' : ''
              ]">
              <span v-if="submitting" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              Confirm {{ currentDecision }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '@/api';
import MainLayout from '@/Layouts/MainLayout.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';

const tabs = [
  { key: 'all', label: 'All KYC' },
  { key: 'pending', label: 'Pending KYC' },
  { key: 'verified', label: 'Verified KYC' },
  { key: 'rejected', label: 'Rejected KYC' },
  { key: 'flags', label: 'Risk Flags' },
];
const activeTab = ref('all');
const filterSeverity = ref('');
const filterType = ref('');
const loading = ref(false);

const kycs = ref([]);
const riskFlags = ref([]);
const pagination = reactive({ current_page: 1, last_page: 1, total: 0 });

const showModal = ref(false);
const submitting = ref(false);
const selectedKyc = ref(null);
const currentDecision = ref('');
const rejectionReason = ref('');

const tabLabel = computed(() => {
  const found = tabs.find(t => t.key === activeTab.value);
  return found ? found.label + ' Verifications' : 'Verifications';
});

const formatDate = (d) => d ? new Date(d).toLocaleDateString() : '—';

const statusClass = (status) => {
  const map = {
    pending: 'bg-yellow-600/40 text-yellow-300',
    verified: 'bg-green-600/40 text-green-300',
    approved: 'bg-green-600/40 text-green-300',
    rejected: 'bg-red-600/40 text-red-300',
  };
  return map[status] || 'bg-gray-600/40 text-gray-300';
};

const severityClass = (severity) => {
  const map = { low: 'bg-gray-600/40 text-gray-300', medium: 'bg-yellow-600/40 text-yellow-300', high: 'bg-orange-600/40 text-orange-300', critical: 'bg-red-600/40 text-red-300' };
  return map[severity] || 'bg-gray-600/40 text-gray-300';
};

const formatIdType = (type) => {
  if (!type) return 'Not Provided';
  const types = {
    'intl_passport': 'International Passport',
    'national_id': 'National ID',
    'drivers_license': 'Driver\'s License',
    'voters_card': 'Voter\'s Card',
    'nin': 'NIN Slip',
  };
  return types[type] || type.replace('_', ' ').toUpperCase();
};


const parsePaginator = (responseData) => {
  // If wrapped in { success, data: Paginator }, unwrap to get the paginator
  const paginator = responseData?.data && typeof responseData.data === 'object' && 'current_page' in responseData.data
    ? responseData.data
    : responseData;
  return {
    items: paginator?.data ?? [],
    current_page: paginator?.current_page ?? 1,
    last_page: paginator?.last_page ?? 1,
    total: paginator?.total ?? 0,
  };
};

const fetchTab = async (tab, page = 1) => {
  loading.value = true;
  kycs.value = [];
  riskFlags.value = [];
  try {
    let res;
    if (tab === 'flags') {
      res = await api.get('/admin/compliance/risk-flags', {
        params: { page, severity: filterSeverity.value || undefined, type: filterType.value || undefined }
      });
    } else {
      const endpoint = tab === 'all' ? '/admin/kycs' : `/admin/compliance/kyc/${tab}`;
      res = await api.get(endpoint, { params: { page, per_page: 20 } });
    }
    const parsed = parsePaginator(res.data);
    if (tab === 'flags') {
      riskFlags.value = parsed.items;
    } else {
      kycs.value = parsed.items;
    }
    pagination.current_page = parsed.current_page;
    pagination.last_page = parsed.last_page;
    pagination.total = parsed.total;
  } catch (err) {
    console.error('Fetch error:', err);
  } finally {
    loading.value = false;
  }
};

const changePage = (page) => fetchTab(activeTab.value, page);

const dismissFlag = async (flag) => {
  if (!confirm('Dismiss this risk flag?')) return;
  try {
    await api.post(`/admin/compliance/risk-flags/${flag.id}/dismiss`);
    fetchTab('flags', pagination.current_page);
  } catch (err) {
    console.error('Dismiss error:', err);
  }
};

const openConfirmModal = (kyc, decision) => {
  selectedKyc.value = kyc;
  currentDecision.value = decision;
  rejectionReason.value = '';
  showModal.value = true;
};

const confirmReview = async () => {
  if (!selectedKyc.value) return;
  if (currentDecision.value === 'rejected' && !rejectionReason.value) return;

  submitting.value = true;
  try {
    const userId = selectedKyc.value.user_id || selectedKyc.value.user?.id;
    await api.post(`/admin/kycs/${userId}/review`, {
      status: currentDecision.value,
      daily_limit: selectedKyc.value.daily_limit || 50000,
      tier: selectedKyc.value.tier || 1,
      rejection_reason: currentDecision.value === 'rejected' ? rejectionReason.value : null,
    });

    kycs.value = kycs.value.map((k) =>
      (k.user_id === userId || k.user?.id === userId) ? { ...k, status: currentDecision.value } : k
    );

    showModal.value = false;
  } catch (error) {
    console.error('Review failed', error);
    alert('An error occurred while processing the request.');
  } finally {
    submitting.value = false;
    selectedKyc.value = null;
  }
};

onMounted(() => fetchTab('all'));
</script>