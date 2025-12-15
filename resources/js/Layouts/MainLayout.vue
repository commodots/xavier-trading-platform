<template>
  <div class="min-h-screen flex bg-[#0B132B] text-white">

    <!-- Sidebar -->
    <aside
      :class="[
        'bg-[#111827] w-64 border-r border-[#1F2A44] flex flex-col justify-between transition-all',
        sidebarOpen ? 'translate-x-0' : '-translate-x-64',
        'md:translate-x-0 fixed md:static inset-y-0 left-0 z-50'
      ]"
    >
      <div>
        <!-- Logo -->
        <div class="flex items-center justify-center py-6">
          <img src="/images/xavier-logo.png" alt="Logo" class="h-[60px] object-contain" />
        </div>

        <!-- Sidebar Menu -->
        <nav class="px-4 mt-4 space-y-1 text-sm">

          <!-- ==================== USER MENU ==================== -->
          <div v-if="!isAdmin">

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
            <SidebarLink to="/profile" :icon="User">Profile</SidebarLink>
            <SidebarLink to="/settings" :icon="Settings">Settings</SidebarLink>
          </div>


          <!-- ==================== ADMIN MENU ==================== -->
          <div v-if="isAdmin" class="mt-6">
            <div class="mb-1 text-xs text-gray-500">ADMIN</div>

            <SidebarLink to="/admin" :icon="PieChart">Dashboard</SidebarLink>
            <SidebarLink to="/admin/users" :icon="Users">Users</SidebarLink>
            <SidebarLink to="/admin/transactions" :icon="ListOrdered">Transactions</SidebarLink>
            <SidebarLink to="/admin/kyc" :icon="ShieldCheck">KYC Review</SidebarLink>
            <SidebarLink to="/admin/orderbook" :icon="BarChart2">Order Book</SidebarLink>
            <SidebarLink to="/admin/control-panel" :icon="MonitorCog">Control Panel</SidebarLink>
          </div>

          <!-- Logout -->
          <button
            @click="logout"
            class="flex items-center gap-3 w-full text-left px-3 py-2 rounded-lg hover:bg-[#1C2541] text-red-400 mt-4"
          >
            <LogOut class="w-5 h-5" />
            Logout
          </button>

        </nav>
      </div>

      <!-- Footer -->
      <div class="px-4 py-4 border-t border-[#1F2A44] text-xs text-gray-400">
        © {{ year }} Xavier
      </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-6 overflow-y-auto">
      <button
        class="md:hidden mb-4 bg-[#1C2541] p-2 rounded"
        @click="sidebarOpen = !sidebarOpen"
      >
        ☰
      </button>

      <slot />
    </main>

  </div>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import {
  Home,
  Wallet,
  PieChart,
  BarChart2,
  Globe,
  Bitcoin,
  ShoppingCart,
  User,
  LogOut,
  Users,
  ShieldCheck,
  ListOrdered,
  Settings,
  MonitorCog
} from "lucide-vue-next";

import SidebarLink from "@/Components/SidebarLink.vue";

const router = useRouter();
const sidebarOpen = ref(false);

// ---- SAFE USER PARSE ----
let user = {};
try {
  const raw = localStorage.getItem("user");
  user = raw && raw !== "undefined" ? JSON.parse(raw) : {};
} catch (e) {
  user = {};
}

const isAdmin = user.role === "admin";

const year = new Date().getFullYear();

const logout = () => {
  localStorage.removeItem("xavier_token");
  localStorage.removeItem("user");
  router.push("/login");
};
</script>

<style scoped>
aside {
  transition: transform 0.25s ease-in-out;
}
</style>
