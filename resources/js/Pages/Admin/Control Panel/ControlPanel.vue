<template>
  <MainLayout>
    <div class="p-6 space-y-6">
      <h1 class="text-2xl font-bold">Control Panel</h1>

      <!-- Tabs -->
      <div class="flex space-x-6 text-sm border-b border-gray-700">
        <button v-if="isAdmin || hasCapability('manage_services')" @click="activeTab = 'service-management'" :class="activeTab === 'service-management'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Service Management
        </button>

        <button v-if="isAdmin || hasCapability('manage_transaction_charges')" @click="activeTab = 'transactions-management'" :class="activeTab === 'transactions-management'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Transactions Management
        </button>

        <button v-if="isAdmin || hasCapability('manage_transaction_charges')" @click="activeTab = 'transactions-charges'" :class="activeTab === 'transactions-charges'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Transactions Charges
        </button>

        <button v-if="isAdmin || hasCapability('manage_kyc_settings')" @click="activeTab = 'kyc-settings'" :class="activeTab === 'kyc-settings'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          KYC Settings
        </button>

        <button v-if="isAdmin || hasCapability('manage_platform_earnings')" @click="activeTab = 'platform-earnings'" :class="activeTab === 'platform-earnings'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Platform Earnings
        </button>
        <button v-if="isAdmin || hasCapability('manage_system_settings')" @click="activeTab = 'staff-access'" :class="activeTab === 'staff-access'
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
import { ref, onMounted, watch, computed } from "vue";
import { useRoute } from 'vue-router';
import MainLayout from "@/Layouts/MainLayout.vue";
import Services from "./Services/Services.vue";
import TransactionTypes from "./TransactionTypes.vue";
import TransactionCharges from "./TransactionCharges.vue";
import KycSettings from "./KycSettings.vue";
import PlatformEarnings from "./PlatformEarnings.vue";
import StaffAccess from "./StaffAccess.vue";
import api from "@/lib/axios";

// Load user from localStorage
let user = {};
try {
  user = JSON.parse(localStorage.getItem("user") || "{}");
} catch {
  user = {};
}

const isAdmin = computed(() => user.role === "admin");
const userPermissions = ref({});

const hasCapability = (capability) => {
  if (isAdmin.value) return true;
  return !!userPermissions.value[capability];
};

const activeTab = ref("service-management");
const route = useRoute();
const loading = ref(true);


const fetchPermissions = async () => {
  try {
    const profileRes = await api.get('/user/profile/show');
    const currentUser = profileRes.data.data;
    userPermissions.value = currentUser.permissions || {};
    // Update localStorage
    let storedUser = JSON.parse(localStorage.getItem("user") || "{}");
    storedUser.permissions = userPermissions.value;
    localStorage.setItem("user", JSON.stringify(storedUser));
  } catch (e) {
    console.error('Failed to fetch permissions', e);
  }
};

const initializePanel = async () => {
  try {
    loading.value = true;
    await Promise.all([
      fetchPermissions(),
      new Promise(resolve => setTimeout(resolve, 500))
    ]);
  } catch (error) {
    console.error("Failed to initialize Control Panel:", error);
  } finally {
    loading.value = false;
  }
};
onMounted(async () => {
  await initializePanel();
  // If a tab query param is provided, open that tab if visible
  const tab = route.query.tab;
  if (tab && typeof tab === 'string') {
    if (isTabVisible(tab)) {
      activeTab.value = tab;
    }
  }
  // Set default to first visible tab
  if (!isTabVisible(activeTab.value)) {
    activeTab.value = getFirstVisibleTab();
  }
});

const isTabVisible = (tab) => {
  const tabCapabilities = {
    'service-management': 'manage_services',
    'transactions-management': 'manage_system_settings',
    'transactions-charges': 'manage_transaction_charges',
    'kyc-settings': 'manage_kyc_settings',
    'platform-earnings': 'manage_platform_earnings',
    'staff-access': 'manage_system_settings'
  };
  const cap = tabCapabilities[tab];
  return isAdmin.value || hasCapability(cap);
};

const getFirstVisibleTab = () => {
  const tabs = ['service-management', 'transactions-management', 'transactions-charges', 'kyc-settings', 'platform-earnings', 'staff-access'];
  for (const tab of tabs) {
    if (isTabVisible(tab)) return tab;
  }
  return 'service-management'; // fallback
};

// React to query changes (e.g., navigation)
watch(() => route.query.tab, (val) => {
  if (val && typeof val === 'string' && isTabVisible(val)) activeTab.value = val;
});
</script>
