<template>
  <MainLayout>
    <div class="max-w-5xl mx-auto p-6 space-y-6">
      
      <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Settings</h1>
        <p class="text-sm text-gray-400 mt-1">Configure your personal preferences, security keys, and verification parameters.</p>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex space-x-6 text-sm border-b border-gray-800 overflow-x-auto scrollbar-none">
        <button 
          v-for="tab in tabItems" 
          :key="tab.id"
          @click="activeTab = tab.id" 
          class="pb-3 font-medium transition-all duration-200 whitespace-nowrap border-b-2"
          :class="activeTab === tab.id
            ? 'border-[#00D4FF] text-[#00D4FF]'
            : 'border-transparent text-gray-400 hover:text-gray-200'"
        >
          {{ tab.label }}
        </button>
      </div>

      <div>
        <template v-if="user && user.id">
          <PersonalTab v-if="activeTab === 'personal'" :user="user" @refresh="fetchUserData" />
          <KycTab v-if="activeTab === 'kyc'" :kyc="user.kyc || {}" />
          <SettingsTab v-if="activeTab === 'security'" :user="user" @refresh="fetchUserData" />
          <LinkedAccountsTab v-if="activeTab === 'accounts'" :accounts="user.linked_accounts || []" @refresh="fetchUserData" />
          <NotificationsTab v-if="activeTab === 'notifications'" :user="user" />
        </template>

        <div v-else class="bg-[#0f172a] p-6 rounded-xl space-y-6 border border-gray-800 max-w-3xl mx-auto">
          <div class="flex items-center space-x-4">
            <SkeletonLoader class="w-20 h-20 rounded-full bg-gray-800" />
            <div class="space-y-2">
              <SkeletonLoader class="h-5 w-44 bg-gray-700/60" />
              <SkeletonLoader class="h-3 w-32 bg-gray-800" />
              <SkeletonLoader class="h-3 w-28 bg-gray-800" />
            </div>
          </div>
          
          <SkeletonLoader class="h-8 w-full rounded-lg bg-gray-800/40" />

          <div class="space-y-4 pt-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-for="i in 4" :key="i" class="space-y-1">
                <SkeletonLoader class="h-3 w-16 bg-gray-800" />
                <SkeletonLoader class="h-9 w-full rounded-lg bg-gray-800/50" />
              </div>
            </div>
            <div class="space-y-1">
              <SkeletonLoader class="h-3 w-24 bg-gray-800" />
              <SkeletonLoader class="h-16 w-full rounded-lg bg-gray-800/50" />
            </div>
          </div>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import MainLayout from "@/Layouts/MainLayout.vue";
import PersonalTab from "./Partials/PersonalTab.vue";
import KycTab from "./Partials/KycTab.vue";
import SettingsTab from "./Partials/SettingsTab.vue";
import LinkedAccountsTab from "./Partials/LinkedAccountsTab.vue";
import NotificationsTab from "./Partials/NotificationsTab.vue";
import SkeletonLoader from "@/Components/SkeletonLoader.vue";
import api from "@/api";

const activeTab = ref("personal");
const user = ref({});
const loading = ref(true);

const tabItems = [
  { id: "personal", label: "Personal Details" },
  { id: "kyc", label: "KYC Verification" },
  { id: "security", label: "Security" },
  { id: "accounts", label: "Linked Accounts" },
  { id: "notifications", label: "Notifications" }
];

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

<style scoped>
/* Custom utility rule to hide standard scrollbar lines on tab container layout */
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
