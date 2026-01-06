<template>
  <div class="min-h-screen flex bg-[#0B132B] text-white relative">

    <div 
      v-if="sidebarOpen" 
      @click="sidebarOpen = false"
      class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 md:hidden transition-opacity"
    ></div>

    <aside :class="[
      'bg-[#111827] w-64 border-r border-[#1F2A44] flex flex-col justify-between transition-all duration-300',
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
            <div class="mt-4 mb-1 text-xs text-gray-500">OVERVIEW</div>
            <SidebarLink to="/dashboard" :icon="Home">Dashboard</SidebarLink>
            <SidebarLink to="/wallet" :icon="Wallet">Wallet</SidebarLink>
            <SidebarLink to="/portfolio" :icon="PieChart">Portfolio</SidebarLink>

            <div class="mt-6 mb-1 text-xs text-gray-500">MARKETS</div>
            <SidebarLink to="/ngx" :icon="BarChart2">NGX Market</SidebarLink>
            <SidebarLink to="/global-stocks" :icon="Globe">Global Stocks</SidebarLink>
            <SidebarLink to="/crypto" :icon="Bitcoin">Crypto Market</SidebarLink>

            <div class="mt-6 mb-1 text-xs text-gray-500">TRADING</div>
            <SidebarLink to="/orders" :icon="ShoppingCart">Orders</SidebarLink>

            <div class="mt-6 mb-1 text-xs text-gray-500">ACCOUNT</div>
            <SidebarLink to="/reports" :icon="FileSpreadsheet">Generate Report</SidebarLink>
            <SidebarLink to="/profile" :icon="Settings">Settings</SidebarLink>
          </div>

          <div v-if="currentView === 'staff'" class="mt-6">
            <div class="mb-1 text-xs text-gray-500">ADMIN MANAGEMENT</div>
            <SidebarLink to="/admin" :icon="PieChart">Dashboard</SidebarLink>
            <SidebarLink to="/admin/users" :icon="Users">Users</SidebarLink>
            <SidebarLink to="/admin/transactions" :icon="ListOrdered">Transactions</SidebarLink>
            <SidebarLink to="/admin/kyc" :icon="ShieldCheck">KYC Review</SidebarLink>
            <SidebarLink to="/admin/orderbook" :icon="BarChart2">Order Book</SidebarLink>
            <SidebarLink to="/admin/activity-log" :icon="SquareChartGantt">Activity Log</SidebarLink>
            <SidebarLink to="/admin/control-panel" :icon="MonitorCog">Control Panel</SidebarLink>
          </div>

          <hr class="border-[#1F2A44] my-4">

          <button @click="logout"
            class="flex items-center gap-3 w-full text-left px-3 py-2 rounded-lg hover:bg-[#1C2541] text-red-400 mt-4">
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
import { ref, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  Home, Wallet, PieChart, BarChart2, Globe, Bitcoin, 
  ShoppingCart, LogOut, Users, ShieldCheck, 
  ListOrdered, Settings, MonitorCog, FileSpreadsheet, SquareChartGantt
} from "lucide-vue-next";

import SidebarLink from "@/Components/SidebarLink.vue";

const router = useRouter();
const route = useRoute();
const sidebarOpen = ref(false);
const year = new Date().getFullYear();

// ---- SAFE USER PARSE ----
const getUser = () => {
  try {
    const raw = localStorage.getItem("user");
    return raw && raw !== "undefined" ? JSON.parse(raw) : null;
  } catch (e) {
    return null;
  }
};

const user = getUser();

// Logic: Check if user has ANY staff/admin roles
const hasStaffAccess = computed(() => {
    const staffRoles = ['admin', 'staff', 'compliance', 'manager', 'support', 'accounts'];
    if (!user) return false;

    const role = user.role?.toLowerCase();
    const hasRoleInString = typeof role === 'string' && staffRoles.includes(role);

    let hasRoleInArray = false;
    if (Array.isArray(user.roles)) {
      hasRoleInArray = user.roles.some(r => {
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
        if (user.role?.toLowerCase() === 'admin') {
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