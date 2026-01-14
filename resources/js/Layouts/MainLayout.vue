<template>
  <div :class="[
    'min-h-screen flex text-white relative transition-colors duration-500', 
    currentView === 'user' ? 'bg-[#0B132B]' : 'bg-[#313753]'
  ]">

    <div 
      v-if="sidebarOpen" 
      @click="sidebarOpen = false"
      class="fixed inset-0 z-40 transition-opacity bg-black/50 backdrop-blur-sm md:hidden"
    ></div>

    <aside :class="[
      'w-64 border-r flex flex-col justify-between transition-all duration-300',
      currentView === 'user' ? 'bg-[#111827] border-[#1F2A44]' : 'bg-[#1a253b] border-[#4d69aa]',
      sidebarOpen ? 'translate-x-0' : '-translate-x-64',
      'md:translate-x-0 fixed md:static inset-y-0 left-0 z-50'
    ]">
      <div>
        <div class="flex items-center justify-center py-6">
          <img src="/images/xavier-logo.png" alt="Logo" class="h-[60px] object-contain" />
        </div>

        <div v-if="hasStaffAccess" class="px-4 mb-4">
          <button @click="toggleAccountMode"
            class="w-full py-2 px-3 text-[10px] font-bold tracking-widest rounded-lg border border-[#00D4FF] text-[#00D4FF] hover:bg-[#00D4FF] hover:text-black transition-all duration-300 uppercase">
            SWITCH TO {{ currentView === 'user' ? 'STAFF MODE' : 'USER MODE' }}
          </button>
        </div>

        <nav class="px-4 mt-4 space-y-1 text-sm">

          <div v-if="currentView === 'user'">
            <div class="px-3 mt-4 mb-1 text-xs tracking-wider text-gray-500 uppercase">OVERVIEW</div>
            <SidebarLink to="/dashboard" :icon="Home">Dashboard</SidebarLink>
            <SidebarLink to="/wallet" :icon="Wallet">Wallet</SidebarLink>
            <SidebarLink to="/portfolio" :icon="PieChart">Portfolio</SidebarLink>

            <div class="px-3 mt-6 mb-1 text-xs tracking-wider text-gray-500 uppercase">MARKETS</div>
            <SidebarLink to="/ngx" :icon="BarChart2">NGX Market</SidebarLink>
            <SidebarLink to="/global-stocks" :icon="Globe">Global Stocks</SidebarLink>
            <SidebarLink to="/crypto" :icon="Bitcoin">Crypto Market</SidebarLink>

            <div class="px-3 mt-6 mb-1 text-xs tracking-wider text-gray-500 uppercase">TRADING</div>
            <SidebarLink to="/orders" :icon="ShoppingCart">Orders</SidebarLink>

            <div class="px-3 mt-6 mb-1 text-xs tracking-wider text-gray-500 uppercase">ACCOUNT</div>
            <SidebarLink to="/reports" :icon="FileSpreadsheet">Generate Report</SidebarLink>
            <SidebarLink to="/profile" :icon="Settings">Settings</SidebarLink>
          </div>

          <div v-if="currentView === 'staff'">
            <div class="mt-4 mb-1 text-xs text-[#818CF8] opacity-70 uppercase tracking-wider px-3 font-semibold">Admin Management</div>
            <SidebarLink to="/admin" :icon="PieChart">Dashboard</SidebarLink>
            <SidebarLink v-if="isAdmin" to="/admin/activity-log" :icon="SquareChartGantt">Activity Log</SidebarLink>
            <SidebarLink v-if="isAdmin || can('manage_system_settings')" to="/admin/reports" :icon="FileSpreadsheet">Generate Report</SidebarLink>

            <div v-if="isAdmin || can('manage_transaction_charges') || can('manage_kyc_settings')" class="mt-6 mb-1 text-xs text-[#818CF8] opacity-70 uppercase tracking-wider px-3 font-semibold">Operations</div>
            <SidebarLink v-if="isAdmin || can('manage_kyc_settings')" to="/admin/users" :icon="Users">User Management</SidebarLink>
            <SidebarLink v-if="isAdmin || can('manage_transaction_charges')" to="/admin/transactions" :icon="ListOrdered">Transactions</SidebarLink>
            <SidebarLink v-if="isAdmin || can('manage_transaction_charges')" to="/admin/orders" :icon="FileText">Orders</SidebarLink>
            <SidebarLink v-if="isAdmin" to="/admin/orderbook" :icon="BarChart2">Order Book</SidebarLink>

            <div v-if="isAdmin || can('manage_kyc_settings')" class="mt-6 mb-1 text-xs text-[#818CF8] opacity-70 uppercase tracking-wider px-3 font-semibold">Compliance</div>
            <SidebarLink v-if="isAdmin || can('manage_kyc_settings')" to="/admin/kyc" :icon="ShieldCheck">KYC Review</SidebarLink>
            
            <div v-if="isAdmin || can('manage_system_settings')" class="mt-6 mb-1 text-xs text-[#818CF8] opacity-70 uppercase tracking-wider px-3 font-semibold">System Settings</div>
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
      <button class="md:hidden mb-4 bg-[#1C2541] p-2 rounded text-white " @click="sidebarOpen = !sidebarOpen">
        ☰
      </button>
      <slot />
    </main>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import axios from "@/lib/axios";
import {
  Home, Wallet, PieChart, BarChart2, Globe, Bitcoin,
  ShoppingCart, LogOut, Users, ShieldCheck,
  ListOrdered, Settings, MonitorCog, FileSpreadsheet, SquareChartGantt, FileText
} from "lucide-vue-next";

import SidebarLink from "@/Components/SidebarLink.vue";

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

const isAdmin = computed(() => user.value?.role === "admin");
const userPermissions = ref(user.value?.permissions || {});

const can = (capability) => {
  if (isAdmin.value) return true;
  return !!userPermissions.value[capability];
};

const fetchPermissions = async () => {
  if (user.value?.role === "admin") return;
  try {
    const profileRes = await axios.get('/user/profile/show');
    const currentUser = profileRes.data.data;
    userPermissions.value = currentUser.permissions || {};
    user.value.permissions = userPermissions.value;
    // Update localStorage
    let storedUser = JSON.parse(localStorage.getItem("user") || "{}");
    storedUser.permissions = userPermissions.value;
    localStorage.setItem("user", JSON.stringify(storedUser));
  } catch (e) {
    console.error('Failed to fetch permissions', e);
  }
};

onMounted(fetchPermissions);

// Logic: Check if user has ANY staff/admin roles
const hasStaffAccess = computed(() => {
    const staffRoles = ['admin', 'staff', 'compliance', 'manager', 'support', 'accounts'];
    if (!user.value) return false;
    const role = user.value.role?.toLowerCase();
    const hasRoleInString = typeof role === 'string' && staffRoles.includes(role);

    let hasRoleInArray = false;
    if (Array.isArray(user.value.roles)) {
      hasRoleInArray = user.value.roles.some(r => {
        const roleName = (typeof r === 'string' ? r : r.name)?.toLowerCase();
        return staffRoles.includes(roleName);
      });
    }
    return hasRoleInArray || hasRoleInString;
});

// INITIALIZATION LOGIC FOR BUG FIX
const getInitialView = () => {
    // 1. If user previously selected a view in this session, keep it
    const saved = localStorage.getItem("active_view");
    if (saved) return saved;

    if (hasStaffAccess.value) {
        // 2. If specifically 'admin', land on staff dashboard
        if (user.value?.role?.toLowerCase() === 'admin') {
            return 'staff';
        }
        // 3. If Manager/Staff/Others, land on user dashboard first
        return 'user';
    }

    // 4. Pure users
    return 'user';
};

const currentView = ref(getInitialView());

const toggleAccountMode = () => {
    currentView.value = currentView.value === 'user' ? 'staff' : 'user';
    localStorage.setItem("active_view", currentView.value);
    
    // Redirect to relevant dashboard after toggle
    if (currentView.value === 'staff') {
        router.push("/admin");
    } else {
        router.push("/dashboard");
    }
};

const logout = () => {
  localStorage.removeItem("xavier_token");
  localStorage.removeItem("user");
  localStorage.removeItem("active_view");
  router.push("/login");
};
</script>
