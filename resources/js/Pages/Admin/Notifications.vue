<template>
  <MainLayout>
    <div class="space-y-8">
      <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
          <h1 class="text-2xl font-semibold">Admin Notifications</h1>
          <p class="text-sm text-gray-400">Send notifications to users based on search criteria and KYC status.</p>
        </div>
      </div>

      <div v-if="currentStep === 1" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <h2 class="mb-4 text-lg font-semibold">Step 1: User Selection</h2>

        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 lg:grid-cols-3">
          <div>
            <label class="block mb-2 text-sm font-medium text-gray-300">Search Users</label>
            <input v-model="searchQuery" type="text" placeholder="Search by name, email..."
              class="w-full bg-[#16213A] border border-[#1f3348] text-sm text-white rounded-lg px-4 py-2 outline-none focus:border-blue-500" />
          </div>

          <div>
            <label class="block mb-2 text-sm font-medium text-gray-300">KYC Status</label>
            <select v-model="kycFilter"
              class="w-full bg-[#16213A] border border-[#1f3348] text-sm text-white rounded-lg px-4 py-2 outline-none focus:border-blue-500">
              <option value="">All KYC Status</option>
              <option value="verified">Verified</option>
              <option value="pending">Pending</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>

          <div class="flex items-end">
            <button @click="searchUsers" :disabled="loadingUsers"
              class="w-full px-4 py-2 text-white transition bg-blue-600 rounded-lg hover:bg-blue-500 disabled:opacity-50">
              {{ loadingUsers ? 'Searching...' : 'Search Users' }}
            </button>
          </div>
        </div>

        <div v-if="users.length > 0" class="mt-4">
          <p class="mb-2 text-sm text-gray-400">{{ users.length }} users found</p>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
                <tr>
                  <th class="px-2 py-3 text-left">Name</th>
                  <th class="px-2 text-left">Email</th>
                  <th class="px-2 text-left">KYC Status</th>
                  <th class="px-2 text-left">Joined</th>
                  <th class="px-2 text-center">Select</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#1f3348]">
                <tr v-for="user in users" :key="user.id" class="hover:bg-[#16213A] transition">
                  <td class="px-2 py-4 text-gray-300">{{ user.name }}</td>
                  <td class="px-2 text-gray-300">{{ user.email }}</td>
                  <td class="px-2">
                    <span :class="kycStatusClass(user.kyc_status)" class="px-2 py-1 text-xs rounded-full">
                      {{ user.kyc_status || 'Not Started' }}
                    </span>
                  </td>
                  <td class="px-2 text-gray-300">{{ formatDate(user.created_at) }}</td>
                  <td class="px-2 text-center">
                    <input type="checkbox" v-model="selectedUsers" :value="user.id" class="rounded" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex justify-end mt-6">
            <button @click="currentStep = 2" :disabled="selectedUsers.length === 0"
              class="px-8 py-2 text-white transition bg-blue-600 rounded-lg hover:bg-blue-500 disabled:opacity-50">
              Next: Compose Notification ({{ selectedUsers.length }} Selected)
            </button>
          </div>
        </div>
      </div>

      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <div v-if="currentStep === 1" class="flex flex-col items-center justify-center py-12 text-center">
            <h2 class="text-lg font-semibold">Compose Notification</h2>
            <p class="text-xs text-gray-600 italic">Please select users to continue.</p>
        </div>

        <div v-else-if="currentStep === 2" class="space-y-4">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">Step 2: Compose Notification</h2>
            <button @click="currentStep = 1" class="text-sm text-blue-400 hover:underline">
              &larr; Back to User Selection
            </button>
          </div>

          <div class="p-3 bg-blue-600/10 border border-blue-500/20 rounded-lg">
            <p class="text-sm text-blue-400">Sending to <strong>{{ selectedUsers.length }}</strong> selected users.</p>
          </div>

          <div>
            <label class="block mb-2 text-sm font-medium text-gray-300">Notification Title</label>
            <input v-model="notificationTitle" type="text" placeholder="Enter notification title"
              class="w-full bg-[#16213A] border border-[#1f3348] text-sm text-white rounded-lg px-4 py-2 outline-none focus:border-blue-500" />
          </div>

          <div>
            <label class="block mb-2 text-sm font-medium text-gray-300">Message</label>
            <textarea v-model="notificationMessage" rows="4" placeholder="Enter your message..."
              class="w-full bg-[#16213A] border border-[#1f3348] text-sm text-white rounded-lg px-4 py-2 outline-none focus:border-blue-500"></textarea>
          </div>

          <div class="flex flex-wrap gap-4">
            <label class="flex items-center">
              <input type="checkbox" v-model="sendEmail" class="mr-2 rounded" />
              <span class="text-sm text-gray-300">Send via Email</span>
            </label>
            <label class="flex items-center">
              <input type="checkbox" v-model="sendMessage" class="mr-2 rounded" />
              <span class="text-sm text-gray-300">Send In-App Message</span>
            </label>
          </div>

          <button @click="sendNotification" :disabled="!canSend || sending"
            class="px-6 py-2 text-white transition bg-green-600 rounded-lg hover:bg-green-500 disabled:opacity-50">
            {{ sending ? 'Sending...' : `Send to ${selectedUsers.length} Users` }}
          </button>
        </div>
      </div>

      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <h2 class="mb-4 text-lg font-semibold">Recent Notifications</h2>
        <div v-if="notifications.length === 0" class="py-8 text-center text-gray-500">
          No notifications sent yet.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
              <tr>
                <th class="px-2 py-3 text-left">Title</th>
                <th class="px-2 text-left">Sent To</th>
                <th class="px-2 text-left">Channels</th>
                <th class="px-2 text-left">Sent At</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#1f3348]">
              <tr v-for="notif in notifications" :key="notif.id" class="hover:bg-[#16213A] transition">
                <td class="px-2 py-4 text-gray-300">{{ notif.title }}</td>
                <td class="px-2 text-gray-300">{{ notif.recipient_count }} users</td>
                <td class="px-2">
                  <div class="flex gap-1">
                    <span v-if="notif.sent_email" class="px-2 py-1 text-xs text-blue-400 rounded bg-blue-600/20">Email</span>
                    <span v-if="notif.sent_message" class="px-2 py-1 text-xs text-green-400 rounded bg-green-600/20">Message</span>
                  </div>
                </td>
                <td class="px-2 text-gray-300">{{ formatDate(notif.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import api from "@/api";
import MainLayout from "@/Layouts/MainLayout.vue";

const currentStep = ref(1);
const searchQuery = ref("");
const kycFilter = ref("");
const users = ref([]);
const selectedUsers = ref([]);
const loadingUsers = ref(false);

const notificationTitle = ref("");
const notificationMessage = ref("");
const sendEmail = ref(false);
const sendMessage = ref(false);
const sending = ref(false);

const notifications = ref([]);

const canSend = computed(() => {
  return selectedUsers.value.length > 0 &&
         (sendEmail.value || sendMessage.value) &&
         notificationTitle.value.trim() &&
         notificationMessage.value.trim();
});

onMounted(() => {
  loadNotifications();
});

async function searchUsers() {
  loadingUsers.value = true;
  try {
    const params = {
      search: searchQuery.value,
      kyc_status: kycFilter.value
    };
    const res = await api.get("/admin/users/search", { params });
    users.value = res.data.data || [];
  } catch (e) {
    console.error("Failed to search users", e);
  } finally {
    loadingUsers.value = false;
  }
}

async function sendNotification() {
  if (!canSend.value) return;

  sending.value = true;
  try {
    const data = {
      user_ids: selectedUsers.value,
      title: notificationTitle.value,
      message: notificationMessage.value,
      send_email: sendEmail.value,
      send_message: sendMessage.value
    };
    await api.post("/admin/notifications/send", data);

    // Reset form
    notificationTitle.value = "";
    notificationMessage.value = "";
    sendEmail.value = false;
    sendMessage.value = false;
    selectedUsers.value = [];
    currentStep.value = 1;

    // Reload notifications
    loadNotifications();
  } catch (e) {
    console.error("Failed to send notification", e);
  } finally {
    sending.value = false;
  }
}

async function loadNotifications() {
  try {
    const res = await api.get("/admin/notifications");
    notifications.value = res.data.data || [];
  } catch (e) {
    console.error("Failed to load notifications", e);
  }
}

function kycStatusClass(status) {
  if (status === 'verified') return 'bg-green-500/10 text-green-400 border border-green-500/20';
  if (status === 'pending') return 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20';
  if (status === 'rejected') return 'bg-red-500/10 text-red-400 border border-red-500/20';
  return 'bg-gray-500/10 text-gray-400';
}

function formatDate(dateStr) {
  if (!dateStr) return "";
  return new Date(dateStr).toLocaleDateString('en-GB', {
    day: '2-digit', month: 'short', year: 'numeric'
  });
}
</script>