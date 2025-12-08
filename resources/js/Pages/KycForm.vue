<template>
  <MainLayout>
    <div>
      <h1 class="text-2xl font-semibold mb-4">🪪 KYC Verification</h1>

      <div class="bg-[#0F1724] border border-[#1f3348] p-6 rounded-xl max-w-lg">
        <form @submit.prevent="submitKyc">
          <div class="mb-4">
            <label class="text-sm text-gray-400">ID Type</label>
            <select v-model="idType" class="w-full mt-1 bg-transparent border border-gray-600 rounded-lg px-3 py-2">
              <option value="NIN">NIN</option>
              <option value="BVN">BVN</option>
              <option value="INTL_PASSPORT">International Passport</option>
            </select>
          </div>

          <div class="mb-4">
            <label class="text-sm text-gray-400">ID Value</label>
            <input
              v-model="idValue"
              type="text"
              placeholder="Enter ID number"
              class="w-full bg-transparent border border-gray-600 rounded-lg px-3 py-2"
            />
          </div>

          <div class="mb-4">
            <label class="text-sm text-gray-400">Upload ID Photo</label>
            <input type="file" @change="handleFile('photo', $event)" class="w-full mt-2" />
          </div>

          <div class="mb-4">
            <label class="text-sm text-gray-400">Upload Supporting Document</label>
            <input type="file" @change="handleFile('document', $event)" class="w-full mt-2" />
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-2 rounded-lg font-semibold hover:opacity-90"
          >
            {{ loading ? "Submitting..." : "Submit KYC" }}
          </button>
        </form>

        <p v-if="message" class="mt-3 text-center text-sm text-yellow-400">{{ message }}</p>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref } from "vue";
import axios from "axios";
import MainLayout from "@/layouts/MainLayout.vue";

const idType = ref("");
const idValue = ref("");
const photo = ref(null);
const documentFile = ref(null);
const message = ref("");
const loading = ref(false);

const handleFile = (type, e) => {
  if (type === "photo") photo.value = e.target.files[0];
  else documentFile.value = e.target.files[0];
};

const submitKyc = async () => {
  loading.value = true;
  message.value = "";
  const token = localStorage.getItem("xavier_token");
  const form = new FormData();
  form.append("id_type", idType.value);
  form.append("id_value", idValue.value);
  if (photo.value) form.append("photo", photo.value);
  if (documentFile.value) form.append("document", documentFile.value);

  try {
    const res = await axios.post("http://127.0.0.1:8000/api/profile/kyc", form, {
      headers: { Authorization: `Bearer ${token}`, "Content-Type": "multipart/form-data" },
    });
    message.value = res.data.message;
  } catch {
    message.value = "❌ Error submitting KYC.";
  } finally {
    loading.value = false;
  }
};
</script>
