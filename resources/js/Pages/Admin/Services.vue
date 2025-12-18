<template>
  <MainLayout>
    <div class="p-6 space-y-6 text-white">
      <h1 class="text-2xl font-bold">Service Management</h1>

      <div v-for="s in services" :key="s.id" class="bg-[#111827] p-4 rounded border border-gray-700">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="font-semibold">{{ s.name }}</h3>
            <p class="text-xs text-gray-400">{{ s.type }}</p>
          </div>

          <button @click="activate(s.id)" class="px-3 py-1 text-xs rounded"
            :class="s.is_active ? 'bg-indigo-600' : 'bg-green-600'">
            {{ s.is_active ? 'Currently Active' : 'Set Active' }}
          </button>
        </div>

        <div class="mt-4 space-y-2">
          <div v-for="c in s.connections" :key="c.id" class="text-xs p-2 bg-[#1E293B] rounded">
            {{ c.mode.toUpperCase() }} - {{ c.base_url }}
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import MainLayout from "@/Layouts/MainLayout.vue";

const services = ref([]);

const load = async () => {
  // 🛑 Call the protected route defined in Step 4
  const res = await axios.get("/admin/services");
  services.value = res.data.services;
};

const activate = async (id) => {
  // 🛑 Call the toggleService endpoint
  await axios.post(`/admin/services/${id}/activate`);

  // Refresh the list to show the newly active service
  load();
};

onMounted(load); 
</script>