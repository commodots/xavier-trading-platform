<template>
  <MainLayout>
    <div class="p-6 space-y-6">
      <h1 class="text-2xl font-bold">Settings</h1>

      <!-- Tabs -->
      <div class="border-b border-gray-700 flex space-x-6 text-sm">
        <button
          @click="activeTab = 'personal'"
          :class="activeTab === 'personal'
            ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
            : 'text-gray-400 pb-2'"
        >
          Personal Details
        </button>

        <button
          @click="activeTab = 'kyc'"
          :class="activeTab === 'kyc'
            ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
            : 'text-gray-400 pb-2'"
        >
          KYC Verification
        </button>
        <button
          @click="activeTab = 'security'"
          :class="activeTab === 'security'
            ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
            : 'text-gray-400 pb-2'"
        >
          Security
        </button>
        <button
          @click="activeTab = 'accounts'"
          :class="activeTab === 'accounts'
            ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
            : 'text-gray-400 pb-2'"
        >
          Linked Accounts
        </button>
        <button
          @click="activeTab = 'notifications'"
          :class="activeTab === 'notifications'
            ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
            : 'text-gray-400 pb-2'"
        >
          Notifications
        </button>
        <button
          @click="activeTab = 'help'"
          :class="activeTab === 'help'
            ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
            : 'text-gray-400 pb-2'"
        >
          Help & Support
        </button>
      </div>

      <div>
        <PersonalTab v-if="activeTab === 'personal'" :user="user" />
        <KycTab v-if="activeTab === 'kyc'" :kyc="kyc" />
        <SettingsTab v-if="activeTab === 'security'" :user="user" />
        <LinkedAccountsTab v-if="activeTab === 'accounts'" />
        <NotificationsTab v-if="activeTab === 'notifications'" />
        <SupportTab v-if="activeTab === 'help'" />
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
import axios from "axios";


const activeTab = ref("personal");

const user = ref({});
const kyc = ref({});

onMounted(async () => {
  const token = localStorage.getItem("xavier_token");

  const me = await axios.get("/api/user/profile/show", {
    headers: { Authorization: `Bearer ${token}` },
  });
  user.value = me.data.data || {};

  const k = await axios.get("/api/user/kyc/show", {
    headers: { Authorization: `Bearer ${token}` },
  });
  kyc.value = k.data.data || {};
});
</script>
