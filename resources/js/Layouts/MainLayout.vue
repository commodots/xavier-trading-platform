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
      sidebarOpen ? 'translate-x-0' : '-translate-x-[75vw]',
      'md:translate-x-0 absolute md:relative inset-y-0 left-0 z-50',
      !sidebarOpen && 'hidden md:block'
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

      <!-- OVERVIEW (always expanded) -->
		  <div class="px-3 mt-6 mb-2 text-[10px] tracking-widest text-gray-400 uppercase">Overview</div>
		  <SidebarLink to="/dashboard" :icon="Home" :active-path="activePath">Dashboard</SidebarLink>
		  <SidebarLink to="/portfolio" :icon="PieChart" :active-path="activePath">Portfolio</SidebarLink>

		  <!-- HOLDINGS (collapsible — one option shown, hover to expand) -->
		  <SidebarHoverSection title="Holdings" group-key="user-holdings" :items="holdingsItems" :active-path="activePath"
        header-class="px-3 mt-6 mb-2 text-[10px] tracking-widest text-gray-400 uppercase" />

      <!-- MARKET (single item — stays expanded) -->
      <div class="px-3 mt-6 mb-2 text-[10px] tracking-widest text-gray-400 uppercase">Market</div>
		  <SidebarLink to="/fx-market" :icon="ChartNoAxesCombined" :active-path="activePath">FX Market</SidebarLink>

		  <!-- ACCOUNT (collapsible — one option shown, hover to expand) -->
		  <SidebarHoverSection title="Account" group-key="user-account" :items="accountItems" :active-path="activePath"
        header-class="px-3 mt-6 mb-2 text-[10px] tracking-widest text-gray-400 uppercase" />

      <!-- WATCHLIST (single item — stays expanded) -->
      <div class="px-3 mt-4 mb-2 text-[10px] tracking-widest text-gray-400 uppercase">Watchlist</div>
		  <SidebarLink to="/watchlist" :icon="Star" :active-path="activePath">Watchlist</SidebarLink>

		  <!-- INSIGHTS (single item — stays expanded) -->
		  <div class="px-3 mt-6 mb-2 text-[10px] tracking-widest text-gray-400 uppercase">Insights</div>
		  <SidebarLink to="/advisory" :icon="Gem" :active-path="activePath">Advisory</SidebarLink>

		</div>

          <div v-if="currentView === 'staff'">
            <SidebarHoverSection title="Admin Management" group-key="staff-admin-management" :items="adminManagementItems" :active-path="activePath"
              header-class="mt-4 mb-1 text-xs text-[#818CF8] opacity-70 uppercase tracking-wider px-3 font-semibold" />

            <SidebarHoverSection title="Fixed Income" group-key="staff-fixed-income" :items="fixedIncomeItems" :active-path="activePath"
              header-class="mt-6 mb-1 text-xs text-[#818CF8] opacity-70 uppercase tracking-wider px-3 font-semibold" />

            <SidebarHoverSection title="Operations" group-key="staff-operations" :items="operationsItems" :active-path="activePath"
              header-class="mt-6 mb-1 text-xs text-[#818CF8] opacity-70 uppercase tracking-wider px-3 font-semibold" />

            <SidebarHoverSection title="Finance" group-key="staff-finance" :items="financeItems" :active-path="activePath"
              header-class="mt-6 mb-1 text-xs text-[#818CF8] opacity-70 uppercase tracking-wider px-3 font-semibold" />

            <div v-if="isAdmin || can('manage_kyc_settings')"
              class="mt-6 mb-1 text-xs text-[#818CF8] opacity-70 uppercase tracking-wider px-3 font-semibold">Compliance
            </div>
            <SidebarLink v-if="isAdmin || can('manage_kyc_settings')" to="/admin/compliance" :icon="ShieldAlert" :active-path="activePath">Compliance Dashboard
            </SidebarLink>

            <div v-if="isAdmin || can('manage_system_settings')"
              class="mt-6 mb-1 text-xs text-[#818CF8] opacity-70 uppercase tracking-wider px-3 font-semibold">System
              Settings</div>
            <SidebarLink to="/admin/control-panel" :icon="MonitorCog" :active-path="activePath">Control Panel</SidebarLink>
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

    <main class="flex-1 bg-[#0B132B]">
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
      <div class="p-4 pb-20 md:p-6">
        <slot />
      </div>

    </main>

    <SessionTimeoutModal
      :show="showWarning"
      :countdown="countdown"
      @stay="stayLoggedIn"
      @logout="executeLogout"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "@/api";
import { useIdleTimeout } from "@/composables/useIdleTimeout";
import SessionTimeoutModal from "@/Components/SessionTimeoutModal.vue";
import {
  Home, Wallet, PieChart, BarChart2, Globe, Bitcoin,
  ShoppingCart, LogOut, Users, ShieldCheck, ShieldAlert,
  ListOrdered, Settings, MonitorCog, FileSpreadsheet, SquareChartGantt, FileText, MessageCircleQuestionMark, TrendingUp, Bell, DollarSign, Gem, Newspaper, ChartNoAxesCombined, Store,
  CreditCard, ArrowLeftRight, History, Download, Receipt, RefreshCw, Calendar
} from "lucide-vue-next";

import SidebarLink from "@/Components/SidebarLink.vue";
import SidebarHoverSection from "@/Components/SidebarHoverSection.vue";
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
  const roles = user.value?.roles || [];
  const hasAdminRole = role === "super-admin" || "admin" ||
    (user.value?.roles && user.value.roles.some(r => (typeof r === 'string' ? r : r.name)?.toLowerCase() === 'admin')) ||
    user.value?.permissions?.manage_system_settings === true;
  const hasSuperAdminRole = role === "super-admin" ||
    (user.value?.roles && user.value.roles.some(r => (typeof r === 'string' ? r : r.name)?.toLowerCase() === 'super-admin'));
  
  return hasAdminRole || hasSuperAdminRole;
});

const isSuperAdmin = computed(() => {
  const role = (user.value?.role || '').toLowerCase();
  const roles = user.value?.roles || [];
  return role === "super-admin" ||
    (user.value?.roles && user.value.roles.some(r => (typeof r === 'string' ? r : r.name)?.toLowerCase() === 'super-admin'));
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

// --------------------------------------------------------------
// Collapsible sidebar sections
// (multi-item groups collapse to their active option and expand on hover;
//  access functions mirror the previous v-if guards on each link)
// --------------------------------------------------------------
const holdingsItems = [
  { label: "NGX", to: "/ngx", icon: BarChart2 },
  { label: "Global Stocks", to: "/global-stocks", icon: Globe },
  { label: "Crypto", to: "/crypto", icon: Bitcoin },
  { label: "Fixed Income", to: "/fixed-income", icon: TrendingUp },
];

const accountItems = [
  { label: "Fund Account", to: "/wallet", icon: Wallet },
  { label: "Transactions", to: "/transactions", icon: ListOrdered },
  { label: "Reports", to: "/reports", icon: FileSpreadsheet },
  { label: "User Settings", to: "/profile", icon: Settings },
  { label: "Help & Support", to: "/support", icon: MessageCircleQuestionMark },
  { label: "Notifications", to: "/notifications", icon: Bell },
];

const adminManagementItems = computed(() => [
  { label: "Dashboard", to: "/admin", icon: PieChart },
  { label: "Activity Log", to: "/admin/activity-log", icon: SquareChartGantt, access: () => isAdmin.value },
  { label: "Audit Logs", to: "/admin/audit-logs", icon: ShieldCheck, access: () => isAdmin.value },
  { label: "Generate Reports", to: "/admin/reports", icon: FileSpreadsheet },
  { label: "Notifications", to: "/admin/notifications", icon: Bell, access: () => isAdmin.value || can("manage_system_settings") },
  { label: "FX Management", to: "/admin/fx-management", icon: DollarSign, access: () => isAdmin.value },
  { label: "Advisory Content", to: "/admin/advisory-dashboard", icon: Newspaper, access: () => isAdmin.value },
  { label: "Crypto Settings", to: "/admin/crypto-settings", icon: Bitcoin, access: () => isAdmin.value },
]);

const fixedIncomeItems = computed(() => [
  { label: "Dashboard", to: "/admin/fixed-income", icon: TrendingUp, access: () => isAdmin.value },
  { label: "Products", to: "/admin/fixed-income/products", icon: FileText, access: () => isAdmin.value },
  { label: "Investments", to: "/admin/fixed-income/investments", icon: ListOrdered, access: () => isAdmin.value },
  { label: "Maturities", to: "/admin/fixed-income/maturities", icon: Calendar, access: () => isAdmin.value },
  { label: "Reconciliation", to: "/admin/fixed-income/reconciliation", icon: RefreshCw, access: () => isAdmin.value },
  { label: "Reports", to: "/admin/fixed-income/reports", icon: FileSpreadsheet, access: () => isAdmin.value },
]);

const operationsItems = computed(() => [
  { label: "User Management", to: "/admin/users", icon: Users, access: () => isAdmin.value || can("manage_kyc_settings") },
  { label: "Transactions", to: "/admin/transactions", icon: ListOrdered, access: () => isAdmin.value || can("manage_transaction_charges") },
  { label: "Orders", to: "/admin/orders", icon: FileText, access: () => isAdmin.value || can("manage_transaction_charges") },
  { label: "Order Book", to: "/admin/orderbook", icon: BarChart2, access: () => isAdmin.value },
]);

const financeItems = computed(() => [
  { label: "Billing Dashboard", to: "/admin/billing", icon: CreditCard, access: () => isAdmin.value },
  { label: "Settlements Dashboard", to: "/admin/settlements", icon: ArrowLeftRight, access: () => isAdmin.value },
  { label: "Expenses", to: "/admin/expenses", icon: Receipt, access: () => isAdmin.value },
]);

// --------------------------------------------------------------
// Active sidebar entry
// Resolves the single sidebar option that represents the current page so only
// one option is highlighted at a time (deepest route prefix wins).
// --------------------------------------------------------------
const sidebarItems = computed(() => {
  if (currentView.value === "user") {
    return [
      { to: "/dashboard" },
      { to: "/portfolio" },
      ...holdingsItems,
      { to: "/fx-market" },
      ...accountItems,
      { to: "/watchlist" },
      { to: "/advisory" },
    ];
  }
  return [
    ...adminManagementItems.value,
    ...fixedIncomeItems.value,
    ...operationsItems.value,
    ...financeItems.value,
    { to: "/admin/compliance" },
    { to: "/admin/control-panel" },
  ];
});

const activePath = computed(() => {
  const path = route.path;
  const matches = sidebarItems.value
    .filter((item) => !item.access || item.access())
    .filter((item) => path === item.to || path.startsWith(`${item.to}/`))
    .sort((a, b) => b.to.length - a.to.length);
  return matches.length > 0 ? matches[0].to : null;
});

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
  const staffRoles = ['super-admin','admin', 'staff', 'compliance', 'manager', 'support', 'accounts'];
  
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


const { showWarning, countdown, stayLoggedIn, executeLogout } = useIdleTimeout();

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
