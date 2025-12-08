<template>
  <MainLayout>
    <div class="p-6 text-white space-y-8">

      <!-- HEADER -->
      <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold">Users Management</h1>

        <!-- Search -->
        <input
          v-model="search"
          @input="debouncedSearch"
          type="text"
          placeholder="Search users..."
          class="px-4 py-2 w-72 bg-[#1E293B] border border-gray-700 rounded-lg text-sm focus:ring focus:ring-blue-700/40 transition"
        />
      </div>

      <!-- TABS -->
      <div class="flex gap-4 border-b border-gray-700 pb-2">
        <button
          class="px-4 py-2 rounded-t-lg"
          :class="activeTab === 'clients'
            ? 'bg-blue-600 text-white'
            : 'bg-[#1E293B] text-gray-300'"
          @click="activeTab = 'clients'"
        >
          Clients
        </button>

        <button
          class="px-4 py-2 rounded-t-lg"
          :class="activeTab === 'staff'
            ? 'bg-blue-600 text-white'
            : 'bg-[#1E293B] text-gray-300'"
          @click="activeTab = 'staff'"
        >
          Staff
        </button>
      </div>

      <!-- CLIENTS TAB -->
      <div v-if="activeTab === 'clients'"
        class="bg-[#111827] p-6 rounded-xl shadow-lg border border-[#1F2A44]"
      >
        <h2 class="text-xl font-semibold mb-4 text-green-400">Client Accounts</h2>

        <div v-if="loading" class="py-10 text-center text-gray-400 animate-pulse">
          Loading clients...
        </div>

        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-700 text-left text-gray-400">
              <th class="py-2">User</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Status</th>
              <th>Joined</th>
              <th></th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="u in paginatedClients"
              :key="u.id"
              class="border-b border-gray-800 hover:bg-[#1E293B] transition"
            >
              <td class="py-3 flex items-center gap-3">
                <div class="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center text-xs font-bold">
                  {{ initials(u) }}
                </div>
                {{ u.first_name }} {{ u.surname }}
              </td>

              <td>{{ u.email }}</td>
              <td>{{ u.phone }}</td>

              <td>
                <span
                  class="px-2 py-1 text-xs rounded"
                  :class="u.status === 'active'
                    ? 'bg-green-600'
                    : 'bg-red-600'">
                  {{ u.status }}
                </span>
              </td>

              <td>{{ formatDate(u.created_at) }}</td>

              <td>
                <button
                  @click="openUser(u.id)"
                  class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition"
                >
                  View
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- CLIENT PAGINATION -->
        <div class="flex justify-between items-center mt-4 text-sm">
          <button
            class="px-3 py-1 bg-gray-700 rounded disabled:opacity-40"
            :disabled="clientPage <= 1"
            @click="clientPage--"
          >
            Prev
          </button>

          <span>Page {{ clientPage }}</span>

          <button
            class="px-3 py-1 bg-gray-700 rounded disabled:opacity-40"
            :disabled="clientPage >= clientMaxPage"
            @click="clientPage++"
          >
            Next
          </button>
        </div>
      </div>

      <!-- STAFF TAB -->
      <div v-if="activeTab === 'staff'"
        class="bg-[#111827] p-6 rounded-xl shadow-lg border border-[#1F2A44]"
      >
        <h2 class="text-xl font-semibold mb-4 text-blue-400">Staff Accounts</h2>

        <div v-if="loading" class="py-10 text-center text-gray-400 animate-pulse">
          Loading staff...
        </div>

        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-700 text-left text-gray-400">
              <th class="py-2">User</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Joined</th>
              <th></th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="u in paginatedStaff"
              :key="u.id"
              class="border-b border-gray-800 hover:bg-[#1E293B] transition"
            >
              <td class="py-3 flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-xs font-bold">
                  {{ initials(u) }}
                </div>
                {{ u.first_name }} {{ u.surname }}
              </td>

              <td>{{ u.email }}</td>

              <td>
                <span class="px-2 py-1 text-xs rounded bg-purple-700 capitalize">
                  {{ u.role }}
                </span>
              </td>

              <td>
                <span
                  class="px-2 py-1 text-xs rounded"
                  :class="u.status === 'active'
                    ? 'bg-green-600'
                    : 'bg-red-600'">
                  {{ u.status }}
                </span>
              </td>

              <td>{{ formatDate(u.created_at) }}</td>

              <td>
                <button
                  @click="openUser(u.id)"
                  class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition"
                >
                  View
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- STAFF PAGINATION -->
        <div class="flex justify-between items-center mt-4 text-sm">
          <button
            class="px-3 py-1 bg-gray-700 rounded disabled:opacity-40"
            :disabled="staffPage <= 1"
            @click="staffPage--"
          >
            Prev
          </button>

          <span>Page {{ staffPage }}</span>

          <button
            class="px-3 py-1 bg-gray-700 rounded disabled:opacity-40"
            :disabled="staffPage >= staffMaxPage"
            @click="staffPage++"
          >
            Next
          </button>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import axios from "@/lib/axios";
import MainLayout from "@/Layouts/MainLayout.vue";

const router = useRouter();

// GLOBAL STATE
const users = ref([]);
const loading = ref(true);
const search = ref("");

// TABS
const activeTab = ref("clients");

// STAFF ROLES
const staffRoles = ["admin", "accounts", "compliance", "support"];

// FILTERED LISTS
const staff = computed(() => users.value.filter((u) => staffRoles.includes(u.role)));
const clients = computed(() => users.value.filter((u) => !staffRoles.includes(u.role)));

// PAGINATION — CLIENTS
const clientPage = ref(1);
const perPage = 10;
const clientMaxPage = computed(() =>
  Math.ceil(clients.value.length / perPage)
);
const paginatedClients = computed(() =>
  clients.value.slice((clientPage.value - 1) * perPage, clientPage.value * perPage)
);

// PAGINATION — STAFF
const staffPage = ref(1);
const staffMaxPage = computed(() =>
  Math.ceil(staff.value.length / perPage)
);
const paginatedStaff = computed(() =>
  staff.value.slice((staffPage.value - 1) * perPage, staffPage.value * perPage)
);

// INITIALS
const initials = (u) =>
  (u.first_name?.[0] || "").toUpperCase() +
  (u.surname?.[0] || "").toUpperCase();

// DEBOUNCED SEARCH
let timer = null;
const debouncedSearch = () => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    fetchUsers();
  }, 400);
};

// FETCH USERS
const fetchUsers = async () => {
  loading.value = true;

  try {
    const res = await axios.get("/admin/users", {
      params: { q: search.value },
    });

    users.value = res.data.users;

    // Reset pagination on new search
    clientPage.value = 1;
    staffPage.value = 1;

  } catch (err) {
    console.error("USER FETCH ERROR:", err);
  }

  loading.value = false;
};

const openUser = (id) => {
  router.push(`/admin/users/${id}`);
};

const formatDate = (d) => new Date(d).toLocaleDateString();

fetchUsers();
</script>
