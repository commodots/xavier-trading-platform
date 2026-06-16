<template>
  <div :class="[
    'min-h-screen flex text-white relative transition-colors duration-500',
    currentView === 'user' ? 'bg-[#0B132B]' : 'bg-[#313753]'
  ]">

    <div v-if="sidebarOpen" @click="sidebarOpen = false"
      class="fixed inset-0 z-40 transition-opacity bg-black/50 backdrop-blur-sm md:hidden"></div>

    <aside :class="[
      'w-64 border-r flex flex-col justify-between transition-all duration-300',
      currentView === 'user' ? 'bg-gradient-to-b from-[#0B132B] to-[#111827] border-[#1F2A44]' : 'bg-[#1a253b] border-[#4d69aa]',
      sidebarOpen ? 'translate-x-0' : '-translate-x-64',
      'md:translate-x-0 fixed md:sticky md:top-0 md:h-screen inset-y-0 left-0 z-50 overflow-y-auto'
    ]">
      <div>
        <div class="flex items-center justify-center py-6">
          <img src="/images/xavier-logo.png" alt="Logo" class="h-[60px] object-contain" />
        </div>

        <div v-if="hasStaffAccess" class="px-4 mb-4">
          <button @click="toggleAccountMode"
            class="w-full py-2 px-3 text-[10px] font-bold tracking-widest rounded-lg border border-[#00D4FF] text-[#00D4FF] hover:bg-[#00D4FF] hover:text-black transition-all duration-300 uppercase">
            SWITCH TO {{ currentView === 'user' ? 'STAFF MODE' : 'CLIENT MODE' }}
          </button>
        </div>

        <nav class="px-4 mt-4 space-y-1 text-sm">

          <div v-if="currentView === 'user'">

      <!-- OVERVIEW -->
		  <div class="px-3 mt-6 mb-2 text-[10px] tracking-widest text-gray-400 uppercase">Overview</div>
		  <SidebarLink to="/dashboard" :icon="Home">Dashboard</SidebarLink>
		  <SidebarLink to="/portfolio" :icon="PieChart">Portfolio</SidebarLink>

		  <!-- PRIMARY ACTIONS -->
		  
		  <!-- MARKET DATA -->
		  <div class="px-3 mt-6 mb-2 text-[10px] tracking-widest text-gray-400 uppercase">Holdings</div>
		  <SidebarLink to="/ngx" :icon="BarChart2">NGX</SidebarLink>
		  <SidebarLink to="/global-stocks" :icon="Globe">Global Stocks</SidebarLink>
		  <SidebarLink to="/crypto" :icon="Bitcoin">Crypto</SidebarLink>
		  <SidebarLink to="/fixed-income" :icon="TrendingUp">Fixed Income</SidebarLink>
		  

      <!--MARKET-->
<div class="px-3 mt-6 mb-2 text-[10px] tracking-widest text-gray-400 uppercase">Market</div>
		  <SidebarLink to="/fx-market" :icon="ChartNoAxesCombined">FX Market</SidebarLink>


		  <!-- ACCOUNT -->
		  <div class="px-3 mt-6 mb-2 text-[10px] tracking-widest text-gray-400 uppercase">Account</div>
		  <SidebarLink to="/wallet" :icon="Wallet">Fund Account</SidebarLink>
		  <SidebarLink to="/transactions" :icon="ListOrdered">Transactions</SidebarLink>
		  <SidebarLink to="/reports" :icon="FileSpreadsheet">Reports</SidebarLink>
		  <SidebarLink to="/profile" :icon="Settings">User Settings</SidebarLink>
		  <SidebarLink to="/support" :icon="MessageCircleQuestionMark">Help & Support</SidebarLink>
      <SidebarLink to="/notifications" :icon="Bell">Notifications</SidebarLink>
      
<!--WATCHLIST-->
      <div class="px-3 mt-4 mb-2 text-[10px] tracking-widest text-gray-400 uppercase">Watchlist</div>
		  <SidebarLink to="/watchlist" :icon="Star">Watchlist</SidebarLink>

		  <!-- INSIGHTS -->
		  <div class="px-3 mt-6 mb-2 text-[10px] tracking-widest text-gray-400 uppercase">Insights</div>
		  <SidebarLink to="/advisory" :icon="Gem">Advisory</SidebarLink>		  

		</div>

          <div v-if="currentView === 'staff'">
            <div class="mt-4 mb-1 text-xs text-[#818CF8] opacity-70 uppercase tracking-wider px-3 font-semibold">Admin
              Management</div>
            <SidebarLink to="/admin" :icon="PieChart">Dashboard</SidebarLink>
            <SidebarLink v-if="isAdmin" to="/admin/activity-log" :icon="SquareChartGantt">Activity Log</SidebarLink>
            <SidebarLink v-if="isAdmin" to="/admin/audit-logs" :icon="ShieldCheck">Audit Logs</SidebarLink>
            <SidebarLink v-if="isAdmin || can('manage_system_settings')" to="/admin/reports" :icon="FileSpreadsheet">
              Generate Report</SidebarLink>
            <SidebarLink v-if="isAdmin || can('manage_system_settings')" to="/admin/notifications" :icon="Bell">
              Notifications</SidebarLink>

            <SidebarLink v-if="isAdmin" to="/admin/fx-dashboard" :icon="DollarSign">FX Dashboard</SidebarLink>

            <SidebarLink v-if="isAdmin" to="/admin/advisory-dashboard" :icon="Newspaper">Advisory Content</SidebarLink>

            <SidebarLink v-if="isAdmin" to="/admin/crypto-settings" :icon="Bitcoin">Crypto Settings</SidebarLink>


            <div v-if="isAdmin || can('manage_transaction_charges') || can('manage_kyc_settings')"
              class="mt-6 mb-1 text-xs text-[#818CF8] opacity-70 uppercase tracking-wider px-3 font-semibold">Operations
            </div>
            <SidebarLink v-if="isAdmin || can('manage_kyc_settings')" to="/admin/users" :icon="Users">User Management
            </SidebarLink>
            <SidebarLink v-if="isAdmin || can('manage_transaction_charges')" to="/admin/transactions"
              :icon="ListOrdered">Transactions</SidebarLink>
            <SidebarLink v-if="isAdmin || can('manage_transaction_charges')" to="/admin/orders" :icon="FileText">Orders
            </SidebarLink>
            <SidebarLink v-if="isAdmin" to="/admin/orderbook" :icon="BarChart2">Order Book</SidebarLink>

            <div v-if="isAdmin"
              class="mt-6 mb-1 text-xs text-[#818CF8] opacity-70 uppercase tracking-wider px-3 font-semibold">Finance
            </div>
            <SidebarLink v-if="isAdmin" to="/admin/billing" :icon="CreditCard">Billing Dashboard</SidebarLink>
            <SidebarLink v-if="isAdmin" to="/admin/settlements" :icon="ArrowLeftRight">Settlements Dashboard</SidebarLink>

            <div v-if="isAdmin || can('manage_kyc_settings')"
              class="mt-6 mb-1 text-xs text-[#818CF8] opacity-70 uppercase tracking-wider px-3 font-semibold">Compliance
            </div>
            <SidebarLink v-if="isAdmin || can('manage_kyc_settings')" to="/admin/compliance" :icon="ShieldAlert">Compliance Dashboard
            </SidebarLink>

            <div v-if="isAdmin || can('manage_system_settings')"
              class="mt-6 mb-1 text-xs text-[#818CF8] opacity-70 uppercase tracking-wider px-3 font-semibold">System
              Settings</div>
            <SidebarLink to="/admin/control-panel" :icon="MonitorCog">Control Panel</SidebarLink>
          </div>

          <hr class="border-[#1F2A44] my-4">

          <button @click="logout"
            class="flex items-center gap-3 w-full text-left px-3 py-2 rounded-lg hover:bg-[#1C2541] text-red-400 mt-4 transition-colors">
            <LogOut class="w-5 h-5" />
            Logout
          </button>

        </nav>
      </div>

      <div class="px-4 py-4 border-t border-[#1F2A44] text-xs text-gray-400">
        © {{ year }} Xavier
      </div>
    </aside>

    <main class="flex-1 p-6 overflow-y-auto bg-[#0B132B]">
      <div class="flex items-center justify-between p-1 md:px-6 bg-[#0B132B]/95 backdrop-blur z-30 sticky top-0">
        <button class="md:hidden mb-4 bg-[#1C2541] p-2 rounded text-white " @click="sidebarOpen = !sidebarOpen">
          ☰
        </button>

        <div class="hidden md:block"></div>
        <div class="flex items-center gap-4">
          <NotificationBell v-if="currentView === 'user'" />
          <DemoToggle v-if="currentView === 'user'" :initialMode="user?.trading_mode || 'live'" />
        </div>
      </div>
      <div class="flex-1 p-4 pb-20 overflow-y-auto md:p-6">
        <slot />
      </div>

    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "@/api";
import {
  Home, Wallet, PieChart, BarChart2, Globe, Bitcoin,
  ShoppingCart, LogOut, Users, ShieldCheck, ShieldAlert,
  ListOrdered, Settings, MonitorCog, FileSpreadsheet, SquareChartGantt, FileText, MessageCircleQuestionMark, TrendingUp, Bell, DollarSign, Gem, Newspaper, ChartNoAxesCombined, Store,
  CreditCard, ArrowLeftRight
} from "lucide-vue-next";

import SidebarLink from "@/Components/SidebarLink.vue";
import DemoToggle from "@/Components/DemoToggle.vue";
import NotificationBell from "@/Components/Notifications/NotificationBell.vue";
import { Star } from "lucide-vue-next";
const router = useRouter();
const route = useRoute();
const sidebarOpen = ref(false);
const year = new Date().getFullYear();

const getUser = () => {
  try {
    const raw = localStorage.getItem("user");
    return raw && raw !== "undefined" ? JSON.parse(raw) : null;
  } catch (e) {
    return null;
  }
};

const user = ref(getUser());

const isAdmin = computed(() => {
  const role = (user.value?.role || '').toLowerCase();
  return role === "admin" ||
    (user.value?.roles && user.value.roles.some(r => (typeof r === 'string' ? r : r.name)?.toLowerCase() === 'admin')) ||
    user.value?.permissions?.manage_system_settings === true;
});

const userPermissions = ref(user.value?.permissions || []);

const can = (capability) => {
  if (isAdmin.value) return true;
  if (!userPermissions.value) return false;


  if (Array.isArray(userPermissions.value)) {
    return userPermissions.value.some(p => {
      const pName = typeof p === 'string' ? p : p.name;
      return pName === capability;
    });
  }


  return !!userPermissions.value[capability];
};

const fetchPermissions = async () => {
  if (isAdmin.value) return;
  try {
    const profileRes = await api.get('/user/profile/show');
    const currentUser = profileRes.data.data;

    // Default to an empty array to prevent undefined errors
    user.value = {
      ...user.value,
      ...currentUser,
      permissions: currentUser.permissions || []
    };

    // Update localStorage 
    localStorage.setItem("user", JSON.stringify(user.value));
  } catch (e) {
    console.error('Failed to fetch permissions', e);
  }
};

onMounted(fetchPermissions);

// Logic: Check if user has ANY staff/admin roles
const hasStaffAccess = computed(() => {
  if (!user.value) return false;
  const role = (user.value.role || '').toLowerCase();
  const staffRoles = ['admin', 'staff', 'compliance', 'manager', 'support', 'accounts'];
  
  const hasPermission = user.value.permissions && typeof user.value.permissions === 'object' 
    ? Object.values(user.value.permissions).some(v => v === true) 
    : false;

  return staffRoles.includes(role) || hasPermission ||
    (user.value.roles?.some(r => staffRoles.includes((typeof r === 'string' ? r : r.name)?.toLowerCase())));
});

// INITIALIZATION LOGIC FOR BUG FIX
const getInitialView = () => {
  // 1. If user previously selected a view in this session, keep it
  const saved = localStorage.getItem("active_view");
  if (saved) return saved;
  // Default to staff mode if they have access, otherwise user mode
  return hasStaffAccess.value ? 'staff' : 'user';
};

const currentView = ref(getInitialView());

// Synchronize currentView with route changes to ensure sidebar and dashboard match
watch(() => route.path, (path) => {
  currentView.value = path.startsWith('/admin') ? 'staff' : 'user';
  localStorage.setItem("active_view", currentView.value);
}, { immediate: true });

const toggleAccountMode = () => {
  router.push(currentView.value === 'user' ? '/admin' : '/dashboard');
};

const logout = async () => {
  try {
    // Tell Laravel to delete the token
    await api.post('/logout');
  } catch (e) {
    console.error("Backend logout failed, clearing local state anyway.");
  } finally {
    //Clear all local data
    localStorage.removeItem("xavier_token");
    localStorage.removeItem("user");
    localStorage.removeItem("active_view");

    // Redirect to landing page
    router.push("/");
  }
};
</script>