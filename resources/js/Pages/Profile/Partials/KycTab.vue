<template>
  <div class="bg-[#0f172a] p-6 rounded-lg border border-gray-700 space-y-4">

    <h2 class="text-xl font-semibold mb-4">KYC Information</h2>

    <div v-if="kyc.status === 'pending'" class="bg-yellow-900/30 border border-yellow-700 text-yellow-400 p-4 rounded">
      Your verification is currently pending review.
    </div>
    <div v-if="kyc.status === 'approved'" class="bg-green-900/30 border border-green-700 text-green-400 p-4 rounded">
      ✓ Identity Verified (Level: {{ kyc.level }})
    </div>
    <div v-if="kyc.status === 'rejected'" class="bg-red-900/30 border border-red-700 text-red-400 p-4 rounded">
      Verification Rejected: {{ kyc.rejection_reason }}
    </div>

    <div v-if="kyc.status === 'none' || kyc.status === 'rejected'" class="grid gap-4">
      <div>
        <label class="block text-gray-400 text-sm mb-1">ID Type</label>
        <select v-model="form.id_type" class="w-full bg-[#16213A] border border-gray-700 rounded p-2 text-white">
          <option value="bvn">BVN</option>
          <option value="nin">NIN</option>
          <option value="passport">International Passport</option>
        </select>
      </div>
      <div>
        <label class="block text-gray-400 text-sm mb-1">ID Number</label>
        <input v-model="form.id_number" type="text"
          class="w-full bg-[#16213A] border border-gray-700 rounded p-2 text-white">
      </div>
      <button @click="submitKyc" class="bg-blue-600 text-white px-6 py-2 rounded">Submit for Review</button>
    </div>

    <div v-else class="grid gap-4 opacity-70">
      <p><span class="text-gray-400">ID Type:</span> {{ kyc.id_type }}</p>
      <p><span class="text-gray-400">ID Number:</span> ****{{ kyc.id_number?.slice(-4) }}</p>
    </div>
  </div>
</template>

<script setup>
import { reactive } from "vue";
import axios from "axios";

const props = defineProps({ kyc: Object });

const token = localStorage.getItem("xavier_token");

const form = reactive({
  bvn: props.kyc?.bvn ?? "",
  id_type: props.kyc?.id_type ?? "",
  id_number: props.kyc?.id_number ?? "",
  id_document: null,
});

const handleFile = (e) => {
  form.id_document = e.target.files[0];
};

const submitKyc = async () => {
  const fd = new FormData();

  fd.append('id_type', form.id_type);
  fd.append('id_value', form.id_number);
  if (form.id_document) {
    fd.append('id_front', form.id_document);
  }

  try {
    await axios.post("/api/user/kyc/submit", fd, {
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "multipart/form-data",
      },
    });
    alert("KYC submitted");
    location.reload();
  } catch (error) {
    console.error("KYC Submission failed", error.response?.data);
    alert("Submission failed.");
  }
};
</script>
