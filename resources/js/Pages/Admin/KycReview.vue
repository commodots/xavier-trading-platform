<template>
  <MainLayout>
    <div class="max-w-4xl mx-auto">

      <!-- HEADER -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">KYC Review</h1>
        <button @click="$router.back()" class="text-gray-300 hover:text-white">
          ← Back
        </button>
      </div>

      <!-- LOADING -->
      <div v-if="loading" class="text-center py-10 text-gray-400">
        Loading KYC data...
      </div>

      <!-- MAIN CONTENT -->
      <div v-else class="space-y-6">

        <!-- USER INFO -->
        <div class="bg-[#1C1F2E] p-6 rounded-xl border border-[#2A314A]">
          <h2 class="text-lg font-semibold mb-4">User Information</h2>

          <div class="grid grid-cols-2 gap-4 text-sm">
            <p><strong>Name:</strong> {{ kyc.user.first_name }} {{ kyc.user.last_name }}</p>
            <p><strong>Email:</strong> {{ kyc.user.email }}</p>
            <p><strong>Phone:</strong> {{ kyc.phone }}</p>
            <p><strong>Status:</strong> 
              <span :class="statusColor(kyc.status)">
                {{ kyc.status.toUpperCase() }}
              </span>
            </p>
          </div>
        </div>

        <!-- DOCUMENTS -->
        <div class="bg-[#1C1F2E] p-6 rounded-xl border border-[#2A314A]">
          <h2 class="text-lg font-semibold mb-4">Uploaded Documents</h2>

          <div class="grid grid-cols-2 gap-6">
            <div>
              <p class="mb-2 font-medium">ID Card</p>
              <img :src="kyc.id_card" class="rounded-lg border border-gray-700" />
            </div>

            <div>
              <p class="mb-2 font-medium">Selfie</p>
              <img :src="kyc.selfie" class="rounded-lg border border-gray-700" />
            </div>
          </div>
        </div>

        <!-- ACTIONS -->
        <div class="bg-[#1C1F2E] p-6 rounded-xl border border-[#2A314A] flex justify-between">
          <button
            @click="updateStatus('rejected')"
            class="bg-red-600 px-4 py-2 rounded-lg hover:bg-red-700"
          >
            Reject
          </button>

          <button
            @click="updateStatus('verified')"
            class="bg-green-600 px-4 py-2 rounded-lg hover:bg-green-700"
          >
            Approve
          </button>
        </div>

      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import MainLayout from "@/Layouts/MainLayout.vue";

const kyc = ref({});
const loading = ref(true);
const route = useRoute();
const id = route.params.id;

onMounted(async () => {
  const res = await axios.get(`/api/admin/kyc/${id}`);
  kyc.value = res.data.data;
  loading.value = false;
});

const updateStatus = async (status) => {
  await axios.post(`/api/admin/kyc-review/${id}`, { status });

  alert(`KYC ${status}`);
  window.location.href = "/admin/kyc";
};

const statusColor = (status) => {
  return {
    pending: "text-yellow-400",
    verified: "text-green-400",
    rejected: "text-red-400",
  }[status];
};
</script>
