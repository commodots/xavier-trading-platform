<template>
  <MainLayout>
    <div class="p-6 space-y-6">
      <h1 class="text-2xl font-bold">Settings</h1>

      <!-- Tabs -->
      <div class="flex space-x-6 text-sm border-b border-gray-700">
        <button @click="activeTab = 'personal'" :class="activeTab === 'personal'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Personal Details
        </button>

        <button @click="activeTab = 'kyc'" :class="activeTab === 'kyc'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          KYC Verification
        </button>
        <button @click="activeTab = 'security'" :class="activeTab === 'security'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Security
        </button>
        <button @click="activeTab = 'accounts'" :class="activeTab === 'accounts'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Linked Accounts
        </button>
        <button @click="activeTab = 'notifications'" :class="activeTab === 'notifications'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Notifications
        </button>
        <button @click="activeTab = 'help'" :class="activeTab === 'help'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Help & Support
        </button>
      </div>

      <div>
        <template v-if="user.id">
          <PersonalTab v-if="activeTab === 'personal'" :user="user" @refresh="fetchUserData" />
          <KycTab v-if="activeTab === 'kyc'" :kyc="user.kyc || {}" />
          <SettingsTab v-if="activeTab === 'security'" :user="user" @refresh="fetchUserData" />

          <LinkedAccountsTab v-if="activeTab === 'accounts'" :accounts="user.linked_accounts || []"
            @refresh="fetchUserData" />

          <NotificationsTab v-if="activeTab === 'notifications'" :user="user" />
          <SupportTab v-if="activeTab === 'help'" />
        </template>

        <div v-else class="text-white animate-pulse">
          Loading settings...
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import MainLayout from "@/layouts/MainLayout.vue";
import PersonalTab from "./Partials/PersonalTab.vue";
import KycTab from "./Partials/KycTab.vue";
import SettingsTab from "./Partials/SettingsTab.vue";
import LinkedAccountsTab from "./Partials/LinkedAccountsTab.vue";
import NotificationsTab from "./Partials/NotificationsTab.vue";
import SupportTab from "./Partials/SupportTab.vue";
import api from "@/lib/axios";


const activeTab = ref("personal");
const user = ref({});
const loading = ref(true);

const fetchUserData = async () => {
  try {
    const response = await api.get("/user/profile/show");
    user.value = response.data.data;
  } catch (error) {
    console.error("Failed to load user profile:", error);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchUserData);
</script>
