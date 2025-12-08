<template>
  <MainLayout>
    <div class="max-w-5xl mx-auto space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">👤 Profile</h1>
          <p class="text-gray-400 text-sm">Manage your account details and complete your KYC.</p>
        </div>
        <button
          @click="logout"
          class="bg-red-500/80 hover:bg-red-500 text-white text-sm px-4 py-2 rounded-lg"
        >
          Logout
        </button>
      </div>

      <!-- Tabs -->
      <div class="flex space-x-6 border-b border-[#1F2A44]">
        <button
          v-for="tab in tabs"
          :key="tab"
          @click="activeTab = tab"
          class="pb-2 text-sm font-semibold transition border-b-2"
          :class="activeTab === tab
            ? 'border-[#00D4FF] text-[#00D4FF]'
            : 'border-transparent text-gray-400 hover:text-white'"
        >
          {{ tab }}
        </button>
      </div>

      <!-- Tab Content -->
      <div v-if="activeTab === 'Personal Info'" class="bg-[#0F1724] border border-[#1F2A44] rounded-xl p-6">
        <div class="flex items-center gap-6 mb-6">
          <img
            :src="user.avatar || '/images/avatar-placeholder.png'"
            alt="User avatar"
            class="w-24 h-24 rounded-full border border-[#1F2A44] object-cover"
          />
          <div>
            <h2 class="text-xl font-semibold">{{ user.first_name }} {{ user.surname }}</h2>
            <p class="text-gray-400 text-sm">{{ user.email }}</p>
            <p class="text-gray-400 text-sm">📱 {{ user.mobile }}</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div>
            <label class="text-gray-400 block mb-1">Date of Birth</label>
            <div class="bg-[#1C1F2E] p-2 rounded-lg">{{ user.dob || '—' }}</div>
          </div>
          <div>
            <label class="text-gray-400 block mb-1">Address</label>
            <div class="bg-[#1C1F2E] p-2 rounded-lg">{{ user.address || '—' }}</div>
          </div>
          <div>
            <label class="text-gray-400 block mb-1">State</label>
            <div class="bg-[#1C1F2E] p-2 rounded-lg">{{ user.state || '—' }}</div>
          </div>
          <div>
            <label class="text-gray-400 block mb-1">Country</label>
            <div class="bg-[#1C1F2E] p-2 rounded-lg">{{ user.country || '—' }}</div>
          </div>
        </div>
      </div>

      <div v-if="activeTab === 'KYC & Verification'" class="bg-[#0F1724] border border-[#1F2A44] rounded-xl p-6">
        <form @submit.prevent="updateKyc" class="space-y-5">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-gray-400 block mb-2 text-sm">BVN / NIN</label>
              <input
                v-model="kyc.id_value"
                type="text"
                placeholder="Enter BVN or NIN"
                class="w-full bg-transparent border border-gray-600 rounded-lg px-4 py-2 focus:border-[#00D4FF] outline-none"
              />
            </div>
            <div>
              <label class="text-gray-400 block mb-2 text-sm">ID Type</label>
              <select
                v-model="kyc.id_type"
                class="w-full bg-transparent border border-gray-600 rounded-lg px-4 py-2 focus:border-[#00D4FF] outline-none"
              >
                <option value="">Select ID Type</option>
                <option value="nin">National ID</option>
                <option value="bvn">BVN</option>
                <option value="passport">Passport</option>
                <option value="driver_license">Driver’s License</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-gray-400 block mb-2 text-sm">Upload ID Front</label>
              <input type="file" @change="handleFile($event, 'id_front')" class="text-sm text-gray-300" />
            </div>
            <div>
              <label class="text-gray-400 block mb-2 text-sm">Upload ID Back</label>
              <input type="file" @change="handleFile($event, 'id_back')" class="text-sm text-gray-300" />
            </div>
          </div>

          <div>
            <label class="text-gray-400 block mb-2 text-sm">Proof of Address</label>
            <input type="file" @change="handleFile($event, 'proof')" class="text-sm text-gray-300" />
          </div>

          <button
            type="submit"
            class="bg-gradient-to-r from-[#0047AB] to-[#00D4FF] px-4 py-2 rounded-lg text-white font-semibold hover:opacity-90 transition"
          >
            {{ loading ? "Updating..." : "Update KYC" }}
          </button>

          <p v-if="message" class="text-sm text-yellow-400 mt-2 text-center">{{ message }}</p>
        </form>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import MainLayout from "@/layouts/MainLayout.vue";

const activeTab = ref("Personal Info");
const tabs = ["Personal Info", "KYC & Verification"];
const user = ref({});
const kyc = ref({ id_type: "", id_value: "", files: {} });
const message = ref("");
const loading = ref(false);

onMounted(() => {
  const storedUser = localStorage.getItem("user");
  if (storedUser) user.value = JSON.parse(storedUser);
});

const handleFile = (event, field) => {
  const file = event.target.files[0];
  if (file) kyc.value.files[field] = file;
};

const updateKyc = async () => {
  loading.value = true;
  message.value = "";
  try {
    const token = localStorage.getItem("xavier_token");
    const formData = new FormData();
    formData.append("id_type", kyc.value.id_type);
    formData.append("id_value", kyc.value.id_value);
    Object.entries(kyc.value.files).forEach(([key, file]) => formData.append(key, file));

    const response = await axios.post("http://127.0.0.1:8000/api/kyc/update", formData, {
      headers: { Authorization: `Bearer ${token}`, "Content-Type": "multipart/form-data" },
    });

    message.value = response.data.message || "✅ KYC updated successfully!";
  } catch (error) {
    message.value = "❌ Failed to update KYC.";
  } finally {
    loading.value = false;
  }
};

const logout = () => {
  localStorage.removeItem("xavier_token");
  localStorage.removeItem("user");
  window.location.href = "/login";
};
</script>

<style scoped>
label {
  font-weight: 500;
}
</style>
