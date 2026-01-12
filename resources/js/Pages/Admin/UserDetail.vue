<template>
  <MainLayout>
    <div class="space-y-6 text-white">

      <!-- HEADER -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">User Details</h1>
          <p class="text-sm text-gray-400">Full profile, activity & admin actions</p>
        </div>

        <div class="flex items-center gap-3">
          <button @click="goBack" class="px-3 py-2 rounded bg-[#1C2541] text-sm">
            ← Back
          </button>

          <button v-if="isAdmin" 
            @click="openRoleModal"
            class="px-3 py-2 text-sm text-white bg-blue-600 rounded"
            :disabled="loading"
          >
            Assign Role
          </button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="py-10 text-center text-gray-400">Loading user…</div>

      <!-- Error -->
      <div
        v-if="error"
        class="p-4 text-red-300 border border-red-600 rounded bg-red-600/10"
      >
        {{ error }}
      </div>

      <!-- MAIN CONTENT -->
      <div v-if="!loading && !error">

        <!-- USER CARD -->
        <div class="bg-[#0F172A] p-6 rounded-xl border border-[#1F2A44] grid grid-cols-1 md:grid-cols-3 gap-6 mb-4">

          <!-- Left -->
          <div class="flex items-start col-span-2 gap-4">
            <div class="w-20 h-20 rounded-full bg-[#111827] flex items-center justify-center text-2xl font-bold">
              {{ initials }}
            </div>

            <div>
              <div class="text-xl font-semibold capitalize">{{ fullName }}</div>

              <div class="text-sm text-gray-300">
               Email: {{ viewedUser.email || "No email" }}
              </div>

              <div class="text-sm text-gray-300">
                Phone: {{ viewedUser.phone || "N/A" }}
              </div>

              <div class="flex items-center gap-2 mt-2">
                <template v-if="Array.isArray(viewedUser.roles) && viewedUser.roles.length">
                  <span v-for="r in viewedUser.roles.map(x => typeof x === 'string' ? x : x.name)" :key="r" class="px-2 py-1 text-xs bg-purple-700 rounded capitalize">
                    {{ r }}
                  </span>
                </template>
                <template v-else>
                  <span class="px-2 py-1 text-xs bg-gray-700 rounded capitalize">{{ viewedUser.role || 'user' }}</span>
                </template>
              </div>

              <div class="mt-2 text-xs text-gray-400">
                Joined: {{ formatDate(viewedUser.created_at) }}
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div v-if="isAdmin"  class="flex flex-col gap-3">

            <button
              @click="toggleStatus"
              :disabled="togglingStatus"
              :class="viewedUser.status === 'active' ? 'bg-red-600' : 'bg-green-600'"
              class="px-4 py-2 text-white rounded"
            >
              {{ togglingStatus ? "Updating..." : (viewedUser.status === "active" ? "Disable Account" : "Enable Account") }}
            </button>

            <button @click="resetPassword" class="px-4 py-2 text-white bg-gray-700 rounded">
              Reset Password
            </button>

            <button @click="openRoleModal" class="px-4 py-2 rounded bg-[#0047AB] text-white">
              Manage Role
            </button>
          </div>
        </div>

        <!-- WALLET + KYC -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

          <!-- Wallet -->
          <div class="bg-[#111827] p-6 rounded-xl border border-[#1F2A44]">
            <h3 class="mb-4 font-semibold">Wallet Balances</h3>

            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-gray-300">NGN</span>
                <span class="font-bold">₦{{ pretty(wallet.ngn) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-300">USD</span>
                <span class="font-bold">${{ pretty(wallet.usd) }}</span>
              </div>
            </div>
          </div>

          <!-- KYC -->
          <div class="bg-[#111827] p-6 rounded-xl border border-[#1F2A44]">
            <h3 class="mb-4 font-semibold">KYC Information</h3>

            <div v-if="kyc">
              <div class="text-sm text-gray-300">
                Status:
                <span
                  class="px-2 py-1 ml-2 text-xs rounded"
                  :class="{
                    'bg-green-600': kyc.status === 'verified',
                    'bg-yellow-600 text-black': kyc.status === 'pending',
                    'bg-red-600': kyc.status === 'rejected'
                  }"
                >
                  {{ kyc.status }}
                </span>
              </div>

              <div class="mt-3 space-y-1 text-sm text-gray-300">
                <div><strong>BVN:</strong> {{ kyc.bvn || "—" }}</div>
                <div><strong>ID Type:</strong> {{ kyc.id_type || "—" }}</div>
                <div><strong>ID Number:</strong> {{ kyc.id_number || "—" }}</div>
              </div>

              <button
                @click="goKycReview"
                class="px-3 py-2 mt-4 text-sm text-white bg-blue-600 rounded"
              >
                Review KYC
              </button>
            </div>

            <div v-else class="text-sm text-gray-400">No KYC submitted</div>
          </div>

        </div>

        <!-- TRANSACTIONS -->
        <div class="bg-[#111827] p-4 rounded-xl border border-[#1F2A44] mt-6">
          <div class="flex items-center justify-between mb-3">
            <h4 class="font-semibold">Recent Transactions</h4>
            <div class="text-xs text-gray-400">Latest 20</div>
          </div>

          <table class="w-full text-sm">
            <thead class="text-left text-xs text-gray-400 border-b border-[#1F2A44]">
              <tr>
                <th class="py-2">Date</th>
                <th>Type</th>
                <th>Description</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Status</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="transactions.length === 0">
                <td colspan="5" class="py-6 text-center text-gray-500">
                  No transactions
                </td>
              </tr>

              <tr
                v-for="t in transactions"
                :key="t.id"
                class="border-b border-[#1F2A44]"
              >
                <td class="py-2">{{ formatDate(t.created_at) }}</td>
                <td>{{ t.type }}</td>
                <td>{{ t.asset || t.description || "—" }}</td>
                <td class="text-right">{{ pretty(t.amount) }}</td>
                <td class="text-right">
                  <span
                    class="px-2 py-1 text-xs rounded"
                    :class="{
                      'bg-green-600': t.status === 'completed',
                      'bg-yellow-600 text-black': t.status === 'pending'
                    }"
                  >
                    {{ t.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>

      <!-- ROLE MODAL -->
      <RoleModal
        v-if="showRoleModal"
        :user="viewedUser"
        @close="showRoleModal = false"
        @role-updated="onRoleUpdated"
      />
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import axios from "@/lib/axios";
import MainLayout from "@/Layouts/MainLayout.vue";
import RoleModal from "@/Components/admin/RoleModal.vue";

const user = ref({});
try {
  user.value = JSON.parse(localStorage.getItem("user") || "{}");
} catch {
  user.value = {};
}

const viewedUser = ref({});

const isAdmin = computed(() => user.value.role === "admin" || user.value.roles?.includes('admin'));

// ROUTING
const route = useRoute();
const router = useRouter();

// STATE
const loading = ref(true);
const error = ref("");
const wallet = ref({ ngn: 0, usd: 0 });
const kyc = ref(null);
const transactions = ref([]);
const showRoleModal = ref(false);
const togglingStatus = ref(false);

// COMPUTED
const fullName = computed(() => {
  const f = viewedUser.value.first_name || "";
  const l = viewedUser.value.last_name || "";
  const name = `${f} ${l}`.trim();
  return name || viewedUser.value.email || "Unnamed user";
});

const initials = computed(() => {
  const f = viewedUser.value.first_name?.[0] || "";
  const l = viewedUser.value.last_name?.[0] || "";
  return (f + l).toUpperCase() || "U";
});

const formatDate = (d) => (d ? new Date(d).toLocaleString() : "—");

const pretty = (n) => Number(n || 0).toLocaleString();

// LOAD DATA
const loadData = async () => {
  loading.value = true;

  try {
    const id = route.params.id;
    const res = await axios.get(`/admin/users/${id}`);

    viewedUser.value = res.data.user;
    wallet.value = res.data.wallet;
    transactions.value = res.data.transactions || [];
    kyc.value = res.data.user.kyc || null;

  } catch (err) {
    error.value = "Failed to load user.";
    console.error(err);

  } finally {
    loading.value = false;
  }
};

onMounted(loadData);

// ACTIONS
const toggleStatus = async () => {
  togglingStatus.value = true;
  try {
    const res = await axios.post(`/admin/users/${viewedUser.value.id}/toggle-status`);
    viewedUser.value.status = res.data.status;
  } catch (e) {
    alert("Unable to update status.");
  }
  togglingStatus.value = false;
};

const resetPassword = () => alert("Reset password coming soon");

const openRoleModal = () => (showRoleModal.value = true);

const onRoleUpdated = (roles) => {

  viewedUser.value.roles = Array.isArray(roles) ? roles : [roles];
  viewedUser.value.role = viewedUser.value.roles.includes('admin') ? 'admin' : viewedUser.value.roles[0];
  showRoleModal.value = false;
};

const goBack = () => router.push("/admin/users");
const goKycReview = () => router.push(`/admin/kyc-review/${viewedUser.value.id}`);
</script>
