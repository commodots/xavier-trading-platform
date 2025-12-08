<template>
  <MainLayout>
    <div>
      <h1 class="text-2xl font-semibold mb-4">🛡 Admin Panel</h1>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
          <h2 class="font-semibold mb-3">Users</h2>
          <ul class="space-y-2">
            <li v-for="u in users" :key="u.id" class="border-b border-[#1f3348] pb-2">
              {{ u.name }} — {{ u.email }}
            </li>
          </ul>
        </div>

        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
          <h2 class="font-semibold mb-3">Pending KYC</h2>
          <ul class="space-y-2">
            <li v-for="k in kycs" :key="k.id" class="border-b border-[#1f3348] pb-2">
              {{ k.user?.name }} - {{ k.status }}
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
import MainLayout from "@/layouts/MainLayout.vue";

const users = ref([]);
const kycs = ref([]);

const token = localStorage.getItem("xavier_token");

onMounted(async () => {
  const [usersRes, kycsRes] = await Promise.all([
    axios.get("http://127.0.0.1:8000/api/admin/users", { headers: { Authorization: `Bearer ${token}` } }),
    axios.get("http://127.0.0.1:8000/api/admin/kycs", { headers: { Authorization: `Bearer ${token}` } }),
  ]);
  users.value = usersRes.data.data.data;
  kycs.value = kycsRes.data.data.data;
});
</script>
