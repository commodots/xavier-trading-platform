<template>
  <MainLayout>
    <div class="space-y-6">
      <h1 class="text-xl font-bold text-white">KYC Review</h1>

      <div class="mb-4">
        <label class="text-white mr-2">Filter by Status:</label>
        <select v-model="statusFilter" class="bg-[#1F2A44] text-white p-2 rounded border border-[#1F2A44]">
          <option value="">All</option>
          <option value="pending">Pending</option>
          <option value="verified">Verified</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>

      <div class="bg-[#111827] rounded-xl p-6 border border-[#1F2A44]">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-gray-400 text-left border-b border-[#1F2A44]">
              <th class="py-2">User</th>
              <th class="py-2">ID Type</th>
              <th class="py-2">Document</th>
              <th class="py-2">Status</th>
              <th class="py-2">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="k in filteredKycs"
              :key="k.id"
              class="border-b border-[#1F2A44] hover:bg-[#1C2541] transition"
            >
              <td class="py-2 capitalize text-white">{{ k.user?.first_name }} {{ k.user?.last_name }}</td>
              
              <td class="py-2 text-gray-300">{{ formatIdType(k.id_type) }}</td>

              <td class="py-2">
                <router-link :to="`/admin/kyc-review/${k.user?.id}`" class="text-[#00D4FF] underline">
                  View Docs
                </router-link>
              </td>

              <td class="py-2">
                <span
                  :class="[
                    'px-2 py-1 rounded text-xs uppercase font-bold',
                    k.status === 'pending'
                      ? 'bg-yellow-600/20 text-yellow-400'
                      : (k.status === 'approved' || k.status === 'verified')
                      ? 'bg-green-600/20 text-green-400'
                      : 'bg-red-600/20 text-red-400',
                  ]"
                >
                  {{ k.status || 'Pending' }}
                </span>
              </td>

              <td class="py-2">
                <div class="flex gap-2">
                  <button
                    class="px-3 py-1 bg-green-600/20 text-green-400 rounded text-xs hover:bg-green-600/40 transition"
                    @click="openConfirmModal(k, 'verified')"
                    v-if="k.status === 'pending'"
                  >
                    Approve
                  </button>

                  <button
                    class="px-3 py-1 bg-red-600/20 text-red-400 rounded text-xs hover:bg-red-600/40 transition"
                    @click="openConfirmModal(k, 'rejected')"
                    v-if="k.status === 'pending'"
                  >
                    Reject
                  </button>
                </div>
              </td>
            </tr>
            
            <tr v-if="filteredKycs.length === 0">
              <td colspan="5" class="py-8 text-center text-gray-500">
                No KYC records found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-[#111827] border border-[#1F2A44] rounded-xl max-w-md w-full p-6 shadow-2xl">
          <h2 class="text-lg font-bold text-white mb-2 capitalize">
            {{ currentDecision }} KYC
          </h2>
          <p class="text-gray-400 text-sm mb-6">
            Are you sure you want to set the KYC submission status for 
            <span class="capitalize text-white">{{ selectedKyc?.user?.first_name }} {{ selectedKyc?.user?.last_name }}</span> to <strong :class="currentDecision === 'verified' ? 'text-green-400' : 'text-red-400'">{{ currentDecision }}</strong> ? 
            This action cannot be undone.
          </p>
          
          <div class="flex justify-end gap-3">
            <button 
              @click="showModal = false" 
              class="px-4 py-2 text-sm text-gray-400 hover:text-white transition"
              :disabled="submitting"
            >
              Cancel
            </button>
            <button 
              @click="confirmReview" 
              :class="[
                'px-4 py-2 text-sm font-bold rounded-lg transition flex items-center gap-2',
                currentDecision === 'verified' ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-red-600 hover:bg-red-700 text-white'
              ]"
              :disabled="submitting"
            >
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
import { ref, onMounted, computed } from "vue";
import MainLayout from "@/Layouts/MainLayout.vue";
import api from "@/api";

const kycs = ref([]);
const showModal = ref(false);
const submitting = ref(false);
const selectedKyc = ref(null);
const currentDecision = ref("");
const statusFilter = ref("");

onMounted(async () => {
  try {
    let res = await api.get("/admin/kycs", { params: { per_page: 10000 } });
    kycs.value = res.data.data.data || [];
  } catch (error) {
    console.error("Failed to load KYC data", error);
  }
});

const filteredKycs = computed(() => {
  if (!statusFilter.value) return kycs.value;
  if (statusFilter.value === 'verified') {
    return kycs.value.filter(k => k.status === 'verified' || k.status === 'approved');
  }
  return kycs.value.filter(k => k.status === statusFilter.value);
});

/**
 * Maps short ID keys to full display names 
 */
const formatIdType = (type) => {
  // SAFETY CHECK: If type is null or undefined, return a placeholder
  if (!type) return 'Not Provided';

  const types = {
    'intl_passport': 'International Passport',
    'national_id': 'National ID Card',
    'drivers_license': 'Driver\'s License',
    'voters_card': 'Voter\'s Card',
    'nin': 'NIN Slip'
  };
  
  return types[type] || type.replace('_', ' ').toUpperCase();
};

const openConfirmModal = (kyc, decision) => {
  selectedKyc.value = kyc;
  currentDecision.value = decision;
  showModal.value = true;
};

const confirmReview = async () => {
  if (!selectedKyc.value) return;
  
  submitting.value = true;
  try {
    // The backend uses user_id in the URL for reviewKyc
    const userId = selectedKyc.value.user_id; 
    const decision = currentDecision.value;

    await api.post(`/admin/kycs/${userId}/review`, { 
        status: decision,
        daily_limit: selectedKyc.value.daily_limit || 0,
        tier: selectedKyc.value.tier || 1
    });

    // Update the local state so the UI reflects the change instantly
    kycs.value = kycs.value.map((k) =>
      k.user_id === userId ? { ...k, status: decision } : k
    );
    
    showModal.value = false;
  } catch (error) {
    console.error("Review failed", error);
    alert("An error occurred while processing the request.");
  } finally {
    submitting.value = false;
    selectedKyc.value = null;
  }
};
</script>