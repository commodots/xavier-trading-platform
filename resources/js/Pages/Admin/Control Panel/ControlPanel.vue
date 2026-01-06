<template>
  <MainLayout>
    <div class="p-6 space-y-6">
      <h1 class="text-2xl font-bold">Control Panel</h1>

      <!-- Tabs -->
      <div class="flex space-x-6 text-sm border-b border-gray-700">
        <button @click="activeTab = 'service-management'" :class="activeTab === 'service-management'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Service Management
        </button>

        <button @click="activeTab = 'transactions-management'" :class="activeTab === 'transactions-management'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Transactions Management
        </button>

        <button @click="activeTab = 'transactions-charges'" :class="activeTab === 'transactions-charges'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Transactions Charges
        </button>

        <button @click="activeTab = 'kyc-settings'" :class="activeTab === 'kyc-settings'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          KYC Settings
        </button>

        <button @click="activeTab = 'platform-earnings'" :class="activeTab === 'platform-earnings'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Platform Earnings
        </button>
        <button @click="activeTab = 'staff-access'" :class="activeTab === 'staff-access'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Staff Access
        </button>
      </div>

      <div>
        <div v-if="loading" class="text-white animate-pulse">
          Loading Control Panel...
        </div>

        <div v-else>
          <Services v-if="activeTab === 'service-management'" />
          <TransactionTypes v-if="activeTab === 'transactions-management'" />
          <TransactionCharges v-if="activeTab === 'transactions-charges'" />
          <KycSettings v-if="activeTab === 'kyc-settings'"/>
          <PlatformEarnings v-if="activeTab === 'platform-earnings'"/>
          <StaffAccess v-if="activeTab === 'staff-access'"/>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import { useRoute } from 'vue-router';
import MainLayout from "@/Layouts/MainLayout.vue";
import Services from "./Services/Services.vue";
import TransactionTypes from "./TransactionTypes.vue";
import TransactionCharges from "./TransactionCharges.vue";
import KycSettings from "./KycSettings.vue";
import PlatformEarnings from "./PlatformEarnings.vue";
import StaffAccess from "./StaffAccess.vue";
import api from "@/lib/axios";


const activeTab = ref("service-management");
const route = useRoute();
const loading = ref(true);


const initializePanel = async () => {
  try {
    loading.value = true;
    await new Promise(resolve => setTimeout(resolve, 500));
  } catch (error) {
    console.error("Failed to initialize Control Panel:", error);
  } finally {
    loading.value = false;
  }
};
onMounted(async () => {
  await initializePanel();
  // If a tab query param is provided, open that tab
  const tab = route.query.tab;
  if (tab && typeof tab === 'string') {
    activeTab.value = tab;
  }
});

// React to query changes (e.g., navigation)
watch(() => route.query.tab, (val) => {
  if (val && typeof val === 'string') activeTab.value = val;
});
</script>
