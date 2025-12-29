<template>
  <MainLayout>
    <div class="space-y-6">

      <h1 class="text-xl font-bold text-white">KYC Review</h1>

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
              v-for="k in kycs"
              :key="k.id"
              class="border-b border-[#1F2A44] hover:bg-[#1C2541] transition"
            >
              <td class="py-2">{{ k.user.name }}</td>
              <td class="py-2">{{ k.id_type }}</td>

              <td class="py-2">
                <a :href="k.document_url" target="_blank" class="text-[#00D4FF] underline">
                  View
                </a>
              </td>

              <td class="py-2">
                <span
                  :class="[
                    'px-2 py-1 rounded text-xs',
                    k.status === 'pending'
                      ? 'bg-yellow-600/20 text-yellow-400'
                      : k.status === 'approved'
                      ? 'bg-green-600/20 text-green-400'
                      : 'bg-red-600/20 text-red-400',
                  ]"
                >
                  {{ k.status }}
                </span>
              </td>

              <td class="py-2">
                <div class="flex gap-2">

                  <button
                    class="px-3 py-1 bg-green-600/20 text-green-400 rounded text-xs"
                    @click="review(k.id, 'approved')"
                    v-if="k.status === 'pending'"
                  >
                    Approve
                  </button>

                  <button
                    class="px-3 py-1 bg-red-600/20 text-red-400 rounded text-xs"
                    @click="review(k.id, 'rejected')"
                    v-if="k.status === 'pending'"
                  >
                    Reject
                  </button>

                </div>
              </td>

            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import MainLayout from "@/layouts/MainLayout.vue";
import api from "@/api";

const kycs = ref([]);

onMounted(async () => {
  let res = await api.get("/admin/kycs");
  kycs.value = res.data.data;
});

const review = async (id, decision) => {
  if (!confirm(`Are you sure you want to ${decision} this KYC?`)) return;

  await api.post(`/admin/kycs/${id}/review`, { status: decision });
  kycs.value = kycs.value.map((k) =>
    k.id === id ? { ...k, status: decision } : k
  );
};
</script>
