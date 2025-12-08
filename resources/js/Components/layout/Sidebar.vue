<template>
  <aside
    :class="[
      'h-screen bg-[#0B132B] text-gray-300 border-r border-[#1F2A44] flex flex-col transition-all duration-300 z-40',
      collapsed ? 'w-20' : 'w-64'
    ]"
  >
    <!-- LOGO + TOGGLE -->
    <div class="flex items-center justify-between p-4 border-b border-[#1F2A44]">
      <img
        v-if="!collapsed"
        src="/images/xavier-logo.png"
        class="h-12 object-contain transition-all duration-300"
      />

      <button @click="$emit('toggle')" class="text-gray-400 hover:text-white transition">
        <Menu class="w-6 h-6" />
      </button>
    </div>

    <!-- MENU ITEMS -->
    <nav class="flex-1 overflow-y-auto py-4 space-y-1">
      <SidebarLink
        v-for="item in filteredMenu"
        :key="item.to"
        :to="item.to"
        :icon="item.icon"
        :collapsed="collapsed"
      >
        {{ item.label }}
      </SidebarLink>
    </nav>

    <!-- FOOTER + LOGOUT -->
    <div class="p-4 border-t border-[#1F2A44]">
      <button
        @click="logout"
        class="w-full flex items-center gap-3 px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white transition"
      >
        <LogOut class="w-5 h-5" />
        <span v-if="!collapsed">Logout</span>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { computed } from "vue";
import { useRouter } from "vue-router";

import SidebarLink from "@/Components/SidebarLink.vue";

// Lucide icons
import {
  Home,
  Wallet,
  User,
  List,
  Settings,
  Users,
  ShieldCheck,
  BarChart2,
  LogOut,
  PieChart,
  ShoppingCart,
  Menu,
} from "lucide-vue-next";

// Props
const props = defineProps({
  collapsed: Boolean,
  icon: {
    type: [Object, Function],
    required: true,
  },
});


// Router
const router = useRouter();

// Load user from localStorage
let user = {};
try {
  user = JSON.parse(localStorage.getItem("user") || "{}");
} catch {
  user = {};
}

// Menu structure
const menu = [
  { label: "Dashboard", to: "/dashboard", icon: Home },
  { label: "Wallet", to: "/wallet", icon: Wallet },
  { label: "Profile", to: "/profile", icon: User },
  { label: "Transactions", to: "/transactions", icon: List },
  { label: "Trade / OMS", to: "/orders", icon: ShoppingCart },
  { label: "Settings", to: "/settings", icon: Settings },

  // Admin only
  { label: "Admin Dashboard", to: "/admin", icon: PieChart, admin: true },
  { label: "Admin Users", to: "/admin/users", icon: Users, admin: true },
  { label: "Admin Transactions", to: "/admin/transactions", icon: List, admin: true },
  { label: "KYC Review", to: "/admin/kyc", icon: ShieldCheck, admin: true },
  { label: "Order Book", to: "/admin/orderbook", icon: BarChart2, admin: true },
];

// Only show admin items if user is admin
const filteredMenu = computed(() => {
  if (user.role === "admin") return menu;
  return menu.filter((m) => !m.admin);
});

// Logout function
const logout = () => {
  localStorage.removeItem("xavier_token");
  localStorage.removeItem("user");
  router.push("/login");
};
</script>

<style scoped>
aside {
  transition: width 0.25s ease-in-out;
}
</style>
