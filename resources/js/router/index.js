import { createRouter, createWebHistory } from "vue-router";

// Auth
import Login from "@/Pages/Auth/Login.vue";
import Register from "@/Pages/Auth/Register.vue";
import ForgotPassword from "@/Pages/Auth/ForgotPassword.vue";
import ResetPassword from "@/Pages/Auth/ResetPassword.vue";
import VerifyEmail from "@/Pages/Auth/VerifyEmail.vue";

//Landing page
import LandingPage from "@/Pages/LandingPage.vue";

// Main User Pages
import Welcome from "@/Pages/Welcome.vue";
import Dashboard from "@/Pages/Dashboard.vue";
import Wallet from "@/Pages/Wallet.vue";
import Transactions from "@/Pages/Transactions.vue";
import Portfolio from "@/Pages/Portfolio.vue";
import NgxMarket from "@/Pages/Market/NgxMarket.vue";
import GlobalMarket from "@/Pages/Market/GlobalStocks.vue";
import CryptoMarket from "@/Pages/Market/CryptoMarket.vue";
import FixedIncomeMarket from "@/Pages/Market/FixedIncomeMarket.vue";
import FxMarket from "@/Pages/Market/FxMarket.vue";
import Profile from "@/Pages/Profile/Index.vue";
import Settings from "@/Pages/Settings.vue";
import Reports from "@/Pages/Reports.vue";
import Support from "@/Pages/Support.vue";
import Withdraw from "@/Pages/Crypto/Withdraw.vue";
import Deposit from "@/Pages/Crypto/Deposit.vue";
import MarketIndex from "@/Pages/Market/MarketIndex.vue";
// OMS
import Orders from "@/Pages/Orders.vue";
import OrderDetails from "@/Pages/OrderDetails.vue";
import Watchlist from "@/Pages/Watchlist.vue";
import Advisory from "@/Pages/Advisory.vue";
import TradingDashboard from "@/Pages/Trading/Dashboard.vue";

// Admin
import AdminKycReview from "@/Pages/Admin/KycReview.vue";
import AdminTransactions from "@/Pages/Admin/Transactions.vue";
import AdminOrders from "@/Pages/Admin/Orders.vue";
import ControlPanel from "@/Pages/Admin/Control Panel/ControlPanel.vue";
import AdminActivityLog from "@/Pages/Admin/ActivityLog.vue";
import AdminNotifications from "@/Pages/Admin/AdminNotifications.vue";
import AdminFxManagement from "@/Pages/Admin/FxManagement.vue";
import AdminAdvisoryDashboard from "@/Pages/Admin/AdvisoryDashboard.vue";
import AdminCryptoSettings from "@/Pages/Admin/AdminCryptoSettings.vue";
import BillingDashboard from "@/Pages/Admin/BillingDashboard.vue";
import SettlementDashboard from "@/Pages/Admin/SettlementDashboard.vue";
import ComplianceDashboard from "@/Pages/Admin/ComplianceDashboard.vue";

const routes = [
  /* ----------------------------------------------
     PUBLIC ROUTE
  ------------------------------------------------*/
  { path: "/", name: "landing-page", component: LandingPage },
  { path: "/login", name: "login", component: Login },
  { path: "/register", name: "register", component: Register },
  { path: "/forgot-password", name: "forgot-password", component: ForgotPassword },
  { path: "/reset-password", name: "reset-password", component: ResetPassword },
  { path: "/verify-email", name: "verify-email", component: VerifyEmail },

  /* ----------------------------------------------
     USER AUTH PAGES
  ------------------------------------------------*/
  {
    path: "/welcome",
    name: "welcome",
    component: Welcome,
    meta: { requiresAuth: false },
  },
  {
    path: "/dashboard",
    name: "dashboard",
    component: Dashboard,
    meta: { requiresAuth: true },
  },
  {
    path: "/wallet",
    name: "wallet",
    component: Wallet,
    meta: { requiresAuth: true },
  },
  {
    path: "/transactions",
    name: "transactions",
    component: Transactions,
    meta: { requiresAuth: true },
  },
  {
    path: "/portfolio",
    name: "portfolio",
    component: Portfolio,
    meta: { requiresAuth: true },
  },
  {
    path: "/settings",
    name: "settings",
    component: Settings,
    meta: { requiresAuth: true },
  },
  {
    path: "/reports",
    name: "reports",
    component: Reports,
    meta: { requiresAuth: true },
  },
  {
    path: "/support",
    name: "support",
    component: Support,
    meta: { requiresAuth: true },
  },

  /* ----------------------------------------------
     MARKETS
  ------------------------------------------------*/
  {
    path: "/market/ngx/:symbol",
    name: "ngx-stock",
    component: () => import("@/Pages/Market/Stock.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/ngx",
    name: "ngx",
    component: NgxMarket,
    meta: { requiresAuth: true },
  },
  {
    path: "/global-stocks",
    name: "global-stocks",
    component: GlobalMarket,
    meta: { requiresAuth: true },
  },
  {
    path: "/crypto",
    name: "crypto",
    component: CryptoMarket,
    meta: { requiresAuth: true },
  },
  {
    path: "/fixed-income",
    name: "fixed-income",
    component: FixedIncomeMarket,
    meta: { requiresAuth: true },
  },
  {
    path: "/fx-market",
    name: "fx-market",
    component: FxMarket,
    meta: { requiresAuth: true },
  },
  {
    path: "/market-index",
    name: "market-index",
    component: MarketIndex,
    meta: { requiresAuth: true },
  },

  /* ----------------------------------------------
     PROFILE (Details + KYC Tab)
  ------------------------------------------------*/
  {
    path: "/profile",
    name: "profile",
    component: Profile,
    meta: { requiresAuth: true },
  },

  /* ----------------------------------------------
     OMS
  ------------------------------------------------*/
  {
    path: "/orders",
    name: "orders",
    component: Orders,
    meta: { requiresAuth: true },
  },
  {
    path: "/watchlist",
    name: "watchlist",
    component: Watchlist,
    meta: { requiresAuth: true },
  },
  {
    path: "/orders/:id",
    name: "order-details",
    component: OrderDetails,
    meta: { requiresAuth: true },
  },
  {
    path: "/notifications",
    name: "notifications",
    component: () => import("@/Components/Notifications/NotificationPage.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/advisory",
    name: "advisory",
    component: Advisory,
    meta: { requiresAuth: true },
  },
  {
    path: "/trading/dashboard",
    name: "trading-dashboard",
    component: TradingDashboard,
    meta: { requiresAuth: true },
  },
  {
    path: "/crypto/deposit",
    name: "deposit",
    component: Deposit,
    meta: { requiresAuth: true },
  },
  {
    path: "/crypto/withdraw",
    name: "withdraw",
    component: Withdraw,
    meta: { requiresAuth: true },
  },

  /* ----------------------------------------------
    ADMIN PAGES
  ------------------------------------------------*/
  {
    path: "/admin",
    name: "admin-dashboard",
    component: () => import("@/Pages/Admin/Dashboard.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/users",
    name: "admin-users",
    component: () => import("@/Pages/Admin/users/UserManagement.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/users/:id",
    name: "admin-user-detail",
    component: () => import("@/Pages/Admin/UserDetail.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/kyc",
    redirect: "/admin/compliance",
  },
  {
    path: "/admin/kyc-review/:id",
    name: "admin-kyc-review",
    component: AdminKycReview,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/transactions",
    name: "admin-transactions",
    component: AdminTransactions,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/orders",
    name: "admin-orders",
    component: AdminOrders,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: '/admin/orderbook',
    name: 'admin-orderbook',
    component: () => import('@/Pages/Admin/OrderBook.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: "/admin/control-panel",
    name: "admin-control-panel",
    component: ControlPanel,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/activity-log",
    name: "admin-activity-log",
    component: AdminActivityLog,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/expenses",
    name: "admin-expenses",
    component: () => import("@/Pages/Admin/Expenses/Index.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/expenses/create",
    name: "admin-expenses-create",
    component: () => import("@/Pages/Admin/Expenses/Create.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/expenses/:id",
    name: "admin-expenses-show",
    component: () => import("@/Pages/Admin/Expenses/Show.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/expenses/:id/edit",
    name: "admin-expenses-edit",
    component: () => import("@/Pages/Admin/Expenses/Edit.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/departments",
    name: "admin-departments",
    component: () => import("@/Pages/Admin/Departments/Index.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    // Categories & vendors now live as tabs inside the Expenses page
    
    path: "/admin/expense-categories",
    redirect: "/admin/expenses?tab=categories",
  },
  {
    path: "/admin/vendors",
    redirect: "/admin/expenses?tab=vendors",
  },
  {
    path: "/admin/audit-logs",
    name: "admin-audit-logs",
    component: () => import("@/Pages/Admin/AuditLogs.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/reports",
    component: () => import("@/Pages/Admin/Reports/Index.vue"),
    meta: { requiresAuth: true, adminOnly: true },
    children: [
      {
        path: "",
        name: "admin-reports-dashboard",
        component: () => import("@/Pages/Admin/Reports/ExecutiveDashboard.vue"),
      },
      {
        path: "users",
        name: "admin-reports-users",
        component: () => import("@/Pages/Admin/Reports/Users.vue"),
      },
      {
        path: "financial",
        name: "admin-reports-financial",
        component: () => import("@/Pages/Admin/Reports/Financial.vue"),
      },
      {
        path: "investments",
        name: "admin-reports-investments",
        component: () => import("@/Pages/Admin/Reports/Investments.vue"),
      },
      {
        path: "wallet-withdrawals",
        name: "admin-reports-wallet",
        component: () => import("@/Pages/Admin/Reports/WithdrawalsWallet.vue"),
      },
      {
        path: "referrals",
        name: "admin-reports-referrals",
        component: () => import("@/Pages/Admin/Reports/ReferralsSubscriptions.vue"),
      },
      {
        path: "system",
        name: "admin-reports-system",
        component: () => import("@/Pages/Admin/Reports/System.vue"),
      },
      {
        path: "revenue",
        name: "reports-revenue",
        component: () => import("@/Pages/Admin/Reports/Revenue.vue"),
      },
      {
        path: "profit-loss",
        name: "reports-profit-loss",
        component: () => import("@/Pages/Admin/Reports/ProfitLoss.vue"),
      },
      {
        path: "financial-summary",
        name: "reports-financial-summary",
        component: () => import("@/Pages/Admin/Reports/FinancialSummary.vue"),
      },
      {
        path: "executive-dashboard",
        redirect: "/admin/reports",
      },
      {
        path: "expenses",
        name: "reports-expenses",
        component: () => import("@/Pages/Admin/Reports/Expenses.vue"),
      },
      {
        path: "downloads",
        name: "reports-downloads",
        component: () => import("@/Pages/Admin/Reports/Downloads.vue"),
      },
      {
        path: "roi",
        name: "reports-roi",
        component: () => import("@/Pages/Admin/Reports/ROI.vue"),
      },
      {
        path: "maturity",
        name: "reports-maturity",
        component: () => import("@/Pages/Admin/Reports/Maturity.vue"),
      },
      {
        path: "investment-plans",
        name: "reports-investment-plans",
        component: () => import("@/Pages/Admin/Reports/InvestmentPlans.vue"),
      },
      {
        path: "subscriptions",
        name: "reports-subscriptions",
        component: () => import("@/Pages/Admin/Reports/Subscriptions.vue"),
      },
      {
        path: "kyc",
        name: "reports-kyc",
        component: () => import("@/Pages/Admin/Reports/KYC.vue"),
      },
      {
        path: "login-history",
        name: "reports-login-history",
        component: () => import("@/Pages/Admin/Reports/LoginHistory.vue"),
      },
    ],
  },
  {
    path: "/admin/notifications",
    name: "admin-notifications",
    component: AdminNotifications,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/fx-management",
    name: "admin-fx-management",
    component: AdminFxManagement,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/advisory-dashboard",
    name: "admin-advisory-dashboard",
    component: AdminAdvisoryDashboard,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/crypto-settings",
    name: "admin-crypto-settings",
    component: AdminCryptoSettings,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/billing",
    name: "admin-billing",
    component: BillingDashboard,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/settlements",
    name: "admin-settlements",
    component: SettlementDashboard,
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/compliance",
    name: "admin-compliance",
    component: ComplianceDashboard,
    meta: { requiresAuth: true, adminOnly: true },
  },

  //FIXED INCOME//
  {
    path: '/admin/fixed-income',
    component: () =>
        import('@/Pages/Admin/FixedIncome/Dashboard.vue')
},

{
    path: '/admin/fixed-income/products',
    component: () =>
        import('@/Pages/Admin/FixedIncome/Products/Index.vue')
},

{
    path: '/admin/fixed-income/products/create',
    component: () =>
        import('@/Pages/Admin/FixedIncome/Products/Create.vue')
},

{
    path: '/admin/fixed-income/products/:id',
    component: () =>
        import('@/Pages/Admin/FixedIncome/Products/Show.vue')
},

{
    path: '/admin/fixed-income/products/:id/edit',
    component: () =>
        import('@/Pages/Admin/FixedIncome/Products/Edit.vue')
},

{
    path: '/admin/fixed-income/investments',
    component: () =>
        import('@/Pages/Admin/FixedIncome/Investments/Index.vue')
},

{
    path: '/admin/fixed-income/investments/:id',
    component: () =>
        import('@/Pages/Admin/FixedIncome/Investments/Show.vue')
},

{
    path: '/admin/fixed-income/reconciliation',
    component: () =>
        import('@/Pages/Admin/FixedIncome/Reconciliation.vue')
},

{
    path: '/admin/fixed-income/reports',
    component: () =>
        import('@/Pages/Admin/FixedIncome/Reports.vue')
}, 
];

/* --------------------------------------------------
   ROUTER
----------------------------------------------------*/
const router = createRouter({
  history: createWebHistory(),
  routes,
});

/* --------------------------------------------------
   NAVIGATION GUARDS
----------------------------------------------------*/
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem("xavier_token");

  let user = {};
  try {
    const stored = localStorage.getItem("user");
    user = stored ? JSON.parse(stored) : {};
  } catch {
    user = {};
  }

  if (to.meta.requiresAuth && !token) {
    return next("/login");
  }

  if (to.path === '/' && token) {
    return next('/dashboard');
  }

  if (to.meta.adminOnly) {
    const staffRoles = ['admin', 'super-admin', 'staff', 'compliance', 'manager', 'support', 'accounts'];

    let hasStaff = false;
    if (user && typeof user.role === 'string' && staffRoles.includes(user.role)) {
      hasStaff = true;
    }

    if (!hasStaff && Array.isArray(user.roles)) {
      hasStaff = user.roles.some(r => {
        if (typeof r === 'string') return staffRoles.includes(r);
        if (r && r.name) return staffRoles.includes(r.name);
        return false;
      });
    }

    if (!hasStaff) return next('/dashboard');
  }

  next();
});

export default router;