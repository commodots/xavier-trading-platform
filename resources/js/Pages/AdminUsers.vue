<template>
  <MainLayout>
    <div>
      <h1 class="text-2xl font-semibold mb-4">🛡 Admin Panel</h1>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
          <h2 class="font-semibold mb-3">Users</h2>
          <div v-if="loading" class="space-y-2">
            <SkeletonLoader v-for="i in 5" :key="i" class="h-6 w-full rounded bg-gray-800" />
          </div>
          <ul v-else class="space-y-2">
            <li v-for="u in users" :key="u.id" class="border-b border-[#1f3348] pb-2">
              {{ u.name }} — {{ u.email }}
            </li>
            <li v-if="users.length === 0" class="py-4 text-center text-gray-400 text-sm">
              No users found
            </li>
          </ul>
        </div>

        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
          <h2 class="font-semibold mb-3">Pending KYC</h2>
          <div v-if="loading" class="space-y-2">
            <SkeletonLoader v-for="i in 5" :key="i" class="h-6 w-full rounded bg-gray-800" />
          </div>
          <ul v-else class="space-y-2">
            <li v-for="k in kycs" :key="k.id" class="border-b border-[#1f3348] pb-2">
              {{ k.user?.name }} - {{ k.status }}
            </li>
            <li v-if="kycs.length === 0" class="py-4 text-center text-gray-400 text-sm">
              No pending KYC
            </li>
          </ul>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import MainLayout from "@/Layouts/MainLayout.vue";
import SkeletonLoader from "@/Components/SkeletonLoader.vue";

const users = ref([]);
const kycs = ref([]);
const loading = ref(true);

const token = localStorage.getItem("xavier_token");

onMounted(async () => {
  loading.value = true;
  try {
    const [usersRes, kycsRes] = await Promise.all([
      axios.get("http://127.0.0.1:8000/api/admin/users", { headers: { Authorization: `Bearer ${token}` } }),
      axios.get("http://127.0.0.1:8000/api/admin/kycs", { headers: { Authorization: `Bearer ${token}` } }),
    ]);
    users.value = usersRes.data.data.data;
    kycs.value = kycsRes.data.data.data;
  } catch (error) {
    console.error("Failed to load admin data", error);
  } finally {
    loading.value = false;
  }
});
</script>
