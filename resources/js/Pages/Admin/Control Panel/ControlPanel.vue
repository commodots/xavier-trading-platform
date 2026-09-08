<template>
  <MainLayout>
    <div class="p-6 space-y-6">
      <h1 class="text-2xl font-bold">Control Panel</h1>

      <!-- Tabs -->
      <div class="flex space-x-6 text-sm border-b border-gray-700">
        <button v-if="canViewTab('operations')" @click="activeTab = 'operations'" :class="activeTab === 'operations'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Operations
        </button>

        

        <button v-if="canViewTab('service-management')" @click="activeTab = 'service-management'" :class="activeTab === 'service-management'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Service Management
        </button>

        <button v-if="canViewTab('transactions-management')" @click="activeTab = 'transactions-management'" :class="activeTab === 'transactions-management'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Transactions Management
        </button>

        <button v-if="canViewTab('transactions-charges')" @click="activeTab = 'transactions-charges'" :class="activeTab === 'transactions-charges'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Transactions Charges
        </button>

        <button v-if="canViewTab('kyc-settings')" @click="activeTab = 'kyc-settings'" :class="activeTab === 'kyc-settings'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          KYC Settings
        </button>

        <button v-if="canViewTab('staff-access')" @click="activeTab = 'staff-access'" :class="activeTab === 'staff-access'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Staff Access
        </button>
        <button v-if="canViewTab('trial-period-toggle')" @click="activeTab = 'trial-period-toggle'" :class="activeTab === 'trial-period-toggle'
          ? 'border-b-2 border-blue-500 text-blue-400 pb-2'
          : 'text-gray-400 pb-2'">
          Trial Period Toggle
        </button>

      </div>

      <div>
        <SkeletonLoader v-if="loading" type="card" :count="3" class="grid gap-4 md:grid-cols-3 opacity-40" />

        <div v-else>
          <Operations v-if="activeTab === 'operations'" />
       
          <Services v-if="activeTab === 'service-management'" />
          <TransactionTypes v-if="activeTab === 'transactions-management'" />
          <TransactionCharges v-if="activeTab === 'transactions-charges'" />
          <KycSettings v-if="activeTab === 'kyc-settings'"/>
          <StaffAccess v-if="activeTab === 'staff-access'"/>
          <TrialPeriodToggle v-if="activeTab === 'trial-period-toggle'"/>
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
import StaffAccess from "./StaffAccess.vue";
import TrialPeriodToggle from "./TrialPeriodToggle.vue";
import Operations from "./Operations.vue";
import SkeletonLoader from "@/Components/SkeletonLoader.vue";
import api from "@/api";

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

const tabCapabilities = {
  operations: 'manage_system_settings',
  'market-data': 'manage_system_settings',
  'service-management': 'manage_services',
  'transactions-management': 'manage_system_settings',
  'transactions-charges': 'manage_transaction_charges',
  'kyc-settings': 'manage_kyc_settings',
  'staff-access': 'manage_system_settings',
  'trial-period-toggle': 'manage_system_settings',
};

const canViewTab = (tab) => isAdmin.value || hasCapability(tabCapabilities[tab]);

const activeTab = ref("service-management");
const route = useRoute();
const loading = ref(true);


const fetchPermissions = async () => {
  try {
    const profileRes = await api.get('/user/profile/show');
    const currentUser = profileRes.data.data || {};
    if (!currentUser || !currentUser.permissions) {
      throw new Error('Failed to fetch user permissions');
    }
    userPermissions.value = currentUser.permissions || {};
    // Update localStorage
    const storedUser = JSON.parse(localStorage.getItem("user") || "{}");
    storedUser.permissions = userPermissions.value;
    localStorage.setItem("user", JSON.stringify(storedUser));
  } catch (e) {
    console.error('Failed to fetch permissions', e);
    if (e instanceof Error) {
      // Handle any specific error types here
    } else {
      throw e;
    }
  } finally {
    // Ensure userPermissions is always defined
    userPermissions.value = userPermissions.value || {};
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
  if (tab === 'fx-rates') return isAdmin.value;
  return canViewTab(tab);
};

const getFirstVisibleTab = () => {
  const tabs = ['operations', 'market-data', 'service-management', 'transactions-management', 'transactions-charges', 'kyc-settings', 'staff-access'];
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
