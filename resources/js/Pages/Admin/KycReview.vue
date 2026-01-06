<template>
  <MainLayout>
    <div class="max-w-4xl mx-auto pb-20">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-white">KYC Review #{{ id }}</h1>
          <p class="text-gray-400 text-sm">Reviewing identity documents for verification.</p>
        </div>
        <button @click="$router.back()" class="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-700 transition">
          ← Back to List
        </button>
      </div>

      <div v-if="loading" class="py-20 text-center">
        <div class="animate-spin inline-block w-8 h-8 border-4 border-[#00D4FF] border-t-transparent rounded-full mb-4"></div>
        <p class="text-gray-400">Fetching document high-res copies...</p>
      </div>

      <div v-else class="space-y-6">
        <div class="bg-[#111827] p-6 rounded-xl border border-[#1F2A44]">
          <h2 class="mb-4 text-lg font-semibold text-white border-b border-[#1F2A44] pb-2">User Details</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-8 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-400">Full Name:</span>
              <span class="text-white font-medium">{{ kyc.user?.first_name }} {{ kyc.user?.last_name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Email:</span>
              <span class="text-white font-medium">{{ kyc.user?.email }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Phone Number:</span>
              <span class="text-white font-medium">{{ kyc.phone || 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Current Status:</span>
              <span :class="['font-bold px-2 py-0.5 rounded text-xs', statusColorClass(kyc.status)]">
                {{ kyc.status?.toUpperCase() }}
              </span>
            </div>
          </div>
        </div>

        <div class="bg-[#111827] p-6 rounded-xl border border-[#1F2A44]">
          <h2 class="mb-6 text-lg font-semibold text-white border-b border-[#1F2A44] pb-2">Submitted Documents</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="space-y-2">
              <p class="text-sm font-medium text-gray-300">Government Issued ID</p>
              <div class="aspect-video bg-[#0B132B] rounded-lg overflow-hidden border border-[#1F2A44] flex items-center justify-center">
                <img v-if="kyc.id_card" :src="kyc.id_card" class="max-h-full w-auto object-contain cursor-zoom-in" @click="openImage(kyc.id_card)" />
                <span v-else class="text-gray-600 italic text-xs">No ID uploaded</span>
              </div>
            </div>

            <div class="space-y-2">
              <p class="text-sm font-medium text-gray-300">Live Selfie / Face Match</p>
              <div class="aspect-video bg-[#0B132B] rounded-lg overflow-hidden border border-[#1F2A44] flex items-center justify-center">
                <img v-if="kyc.selfie" :src="kyc.selfie" class="max-h-full w-auto object-contain cursor-zoom-in" @click="openImage(kyc.selfie)" />
                <span v-else class="text-gray-600 italic text-xs">No selfie uploaded</span>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-[#111827] p-6 rounded-xl border border-[#1F2A44] flex items-center justify-between">
          <div>
             <p class="text-xs text-gray-500 italic">Caution: Approving this will upgrade the user's transaction limits.</p>
          </div>
          <div class="flex gap-4">
            <button @click="updateStatus('rejected')" class="px-6 py-2 bg-transparent border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition-all font-bold">
              Reject Submission
            </button>
            <button @click="updateStatus('verified')" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all font-bold shadow-lg shadow-green-900/20">
              Approve & Verify
            </button>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "@/api";
import { useRoute, useRouter } from 'vue-router';
import MainLayout from "@/Layouts/MainLayout.vue";

const kyc = ref({ user: {}, status: 'pending' });
const loading = ref(true);
const route = useRoute();
const router = useRouter();
const id = route.params.id;

onMounted(async () => {
  try {
    const res = await api.get(`/admin/kyc/${id}`);
    kyc.value = res.data;
  } catch (e) {
    console.error("Error loading KYC:", e);
    alert("Record not found or server error.");
  } finally {
    loading.value = false;
  }
});

const updateStatus = async (status) => {
  let rejectionReason = null;
  if (status === 'rejected') {
    rejectionReason = prompt('Please enter the reason for rejection:');
    if (!rejectionReason) return; 
  }

  try {
    const payload = {
      status,
      daily_limit: kyc.value.daily_limit || 50000, // Default fallback
      tier: kyc.value.tier || 1,
      rejection_reason: rejectionReason
    };

    await api.post(`/admin/kycs/${id}/review`, payload);
    alert(`User KYC has been marked as ${status}`);
    router.push("/admin/kyc");
  } catch (err) {
    alert("Failed to update KYC status.");
  }
};

const openImage = (url) => window.open(url, '_blank');

const statusColorClass = (status) => {
  return {
    pending: "bg-yellow-500/10 text-yellow-500",
    verified: "bg-green-500/10 text-green-500",
    rejected: "bg-red-500/10 text-red-500",
  }[status] || "bg-gray-500/10 text-gray-500";
};
</script>