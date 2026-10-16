import { createRouter, createWebHistory } from "vue-router";

// Auth
const routes = [
  /* ----------------------------------------------
     PUBLIC ROUTE
  ------------------------------------------------*/
  {
    path: "/",
    name: "landing-page",
    component: () => import("@/Pages/LandingPage.vue"),
    meta: {
      title: "Smart Trading, Secure & Simplified",
      description: "Access global markets, manage NGN and USD wallets, and trade with Xavier’s secure trading platform.",
      indexable: true,
    },
  },
  { path: "/login", name: "login", component: () => import("@/Pages/Auth/Login.vue") },
  { path: "/register", name: "register", component: () => import("@/Pages/Auth/Register.vue") },
  { path: "/forgot-password", name: "forgot-password", component: () => import("@/Pages/Auth/ForgotPassword.vue") },
  { path: "/reset-password", name: "reset-password", component: () => import("@/Pages/Auth/ResetPassword.vue") },
  { path: "/verify-email", name: "verify-email", component: () => import("@/Pages/Auth/VerifyEmail.vue") },

  /* ----------------------------------------------
     USER AUTH PAGES
  ------------------------------------------------*/
  {
    path: "/welcome",
    name: "welcome",
    component: () => import("@/Pages/Welcome.vue"),
    meta: { requiresAuth: false },
  },
  {
    path: "/dashboard",
    name: "dashboard",
    component: () => import("@/Pages/Dashboard.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/wallet",
    name: "wallet",
    component: () => import("@/Pages/Wallet.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/transactions",
    name: "transactions",
    component: () => import("@/Pages/Transactions.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/portfolio",
    name: "portfolio",
    component: () => import("@/Pages/Portfolio.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/settings",
    name: "settings",
    component: () => import("@/Pages/Settings.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/reports",
    name: "reports",
    component: () => import("@/Pages/Reports.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/support",
    name: "support",
    component: () => import("@/Pages/Support.vue"),
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
    component: () => import("@/Pages/Market/NGXMarket.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/global-stocks",
    name: "global-stocks",
    component: () => import("@/Pages/Market/GlobalStocks.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/crypto",
    name: "crypto",
    component: () => import("@/Pages/Market/CryptoMarket.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/fixed-income",
    name: "fixed-income",
    component: () => import("@/Pages/Market/FixedIncomeMarket.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/fixed-income/:id",
    name: "fixed-income-product",
    component: () => import("@/Pages/FixedIncome/Show.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/fixed-income/:id/invest",
    name: "fixed-income-invest",
    component: () => import("@/Pages/FixedIncome/Invest.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/fixed-income/:id/review",
    name: "fixed-income-review",
    component: () => import("@/Pages/FixedIncome/Review.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/fixed-income/success/:id",
    name: "fixed-income-success",
    component: () => import("@/Pages/FixedIncome/Success.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/fixed-income/investments",
    name: "fixed-income-investments",
    component: () => import("@/Pages/FixedIncome/Investments.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/fixed-income/investments/:id",
    name: "fixed-income-investment-show",
    component: () => import("@/Pages/FixedIncome/InvestmentShow.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/fx-market",
    name: "fx-market",
    component: () => import("@/Pages/Market/FxMarket.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/market-index",
    name: "market-index",
    component: () => import("@/Pages/Market/MarketIndex.vue"),
    meta: { requiresAuth: true },
  },

  /* ----------------------------------------------
     PROFILE (Details + KYC Tab)
  ------------------------------------------------*/
  {
    path: "/profile",
    name: "profile",
    component: () => import("@/Pages/Profile/Index.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/kyc/verify",
    name: "kyc-verify",
    component: () => import("@/Pages/Kyc/Verify.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/kyc/status",
    name: "kyc-status",
    component: () => import("@/Pages/Kyc/KycStatus.vue"),
    meta: { requiresAuth: true },
  },

  /* ----------------------------------------------
     OMS
  ------------------------------------------------*/
  {
    path: "/orders",
    name: "orders",
    component: () => import("@/Pages/Orders.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/watchlist",
    name: "watchlist",
    component: () => import("@/Pages/Watchlist.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/orders/:id",
    name: "order-details",
    component: () => import("@/Pages/OrderDetails.vue"),
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
    component: () => import("@/Pages/Advisory.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/trading/dashboard",
    name: "trading-dashboard",
    component: () => import("@/Pages/Trading/Dashboard.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/crypto/deposit",
    name: "deposit",
    component: () => import("@/Pages/Crypto/Deposit.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/crypto/withdraw",
    name: "withdraw",
    component: () => import("@/Pages/Crypto/Withdraw.vue"),
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
    component: () => import("@/Pages/Admin/KycReview.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/transactions",
    name: "admin-transactions",
    component: () => import("@/Pages/Admin/Transactions.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/orders",
    name: "admin-orders",
    component: () => import("@/Pages/Admin/Orders.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: '/admin/orderbook',
    name: 'admin-orderbook',
    component: () => import('@/Pages/Admin/OrderBook.vue'),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/control-panel",
    name: "admin-control-panel",
    component: () => import("@/Pages/Admin/Control Panel/ControlPanel.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/activity-log",
    name: "admin-activity-log",
    component: () => import("@/Pages/Admin/ActivityLog.vue"),
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
    component: () => import("@/Pages/Admin/AdminNotifications.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/fx-management",
    name: "admin-fx-management",
    component: () => import("@/Pages/Admin/FxManagement.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/advisory-dashboard",
    name: "admin-advisory-dashboard",
    component: () => import("@/Pages/Admin/AdvisoryDashboard.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/crypto-settings",
    name: "admin-crypto-settings",
    component: () => import("@/Pages/Admin/AdminCryptoSettings.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/billing",
    name: "admin-billing",
    component: () => import("@/Pages/Admin/BillingDashboard.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/settlements",
    name: "admin-settlements",
    component: () => import("@/Pages/Admin/SettlementDashboard.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: "/admin/compliance",
    name: "admin-compliance",
    component: () => import("@/Pages/Admin/ComplianceDashboard.vue"),
    meta: { requiresAuth: true, adminOnly: true },
  },

  //FIXED INCOME//
  {
    path: '/admin/fixed-income',
    component: () =>
          import('@/Pages/Admin/FixedIncome/Dashboard.vue'),
      meta: { requiresAuth: true, adminOnly: true },
  },

  {
      path: '/admin/fixed-income/products',
      component: () =>
          import('@/Pages/Admin/FixedIncome/Products/Index.vue'),
      meta: { requiresAuth: true, adminOnly: true },
  },

  {
      path: '/admin/fixed-income/products/create',
      component: () =>
          import('@/Pages/Admin/FixedIncome/Products/Create.vue'),
      meta: { requiresAuth: true, adminOnly: true },
  },

  {
      path: '/admin/fixed-income/products/:id/edit',
      component: () =>
          import('@/Pages/Admin/FixedIncome/Products/Edit.vue'),
      meta: { requiresAuth: true, adminOnly: true },
  },

  {
    path: '/admin/fixed-income/products/:id',
    component: () => import('@/Pages/Admin/FixedIncome/Products/Show.vue'),
    meta: { requiresAuth: true, adminOnly: true },
  },

  {
      path: '/admin/fixed-income/investments',
      component: () =>
          import('@/Pages/Admin/FixedIncome/Investments/Index.vue'),
      meta: { requiresAuth: true, adminOnly: true },
},

{
      path: '/admin/fixed-income/investments/:id',
    component: () =>
          import('@/Pages/Admin/FixedIncome/Investments/Show.vue'),
      meta: { requiresAuth: true, adminOnly: true },
  },

  {
      path: '/admin/fixed-income/reconciliation',
      component: () =>
          import('@/Pages/Admin/FixedIncome/Reconciliation.vue'),
      meta: { requiresAuth: true, adminOnly: true },
  },

  {
    path: '/admin/fixed-income/maturities',
    component: () =>
      import('@/Pages/Admin/FixedIncome/Maturities.vue'),
    meta: { requiresAuth: true, adminOnly: true },
  },

  {
      path: '/admin/fixed-income/reports',
      component: () =>
          import('@/Pages/Admin/FixedIncome/Reports.vue'),
      meta: { requiresAuth: true, adminOnly: true },
    },
    {
      path: "/:pathMatch(.*)*",
      name: "not-found",
      component: () => import("@/Pages/NotFound.vue"),
    },
  ];

/* --------------------------------------------------
   ROUTER
----------------------------------------------------*/
const router = createRouter({
  history: createWebHistory(),
  routes,
});

const pageTitle = (route) => {
  if (route.meta.title) {
    return route.meta.title;
  }

  const routeName = String(route.name || route.path)
    .replace(/([a-z])([A-Z])/g, "$1 $2")
    .replace(/[-_/]+/g, " ")
    .replace(/\s+/g, " ")
    .trim();

  return routeName.replace(/\b\w/g, (character) => character.toUpperCase());
};

const updateMeta = (attribute, key, content) => {
  let element = document.head.querySelector(`meta[${attribute}="${key}"]`);

  if (!element) {
    element = document.createElement("meta");
    element.setAttribute(attribute, key);
    document.head.appendChild(element);
  }

  element.setAttribute("content", content);
};

router.afterEach((to) => {
  const title = pageTitle(to);
  const description = to.meta.description
    || `${title} on Xavier. Manage your investments and access financial markets through our trading platform.`;
  const canonical = new URL(to.path, window.location.origin).toString();
  const robots = to.meta.indexable ? "index,follow" : "noindex,nofollow";

  document.title = `${title} | Xavier`;
  updateMeta("name", "description", description);
  updateMeta("name", "robots", robots);
  updateMeta("property", "og:type", "website");
  updateMeta("property", "og:site_name", "Xavier");
  updateMeta("property", "og:title", `${title} | Xavier`);
  updateMeta("property", "og:description", description);
  updateMeta("property", "og:url", canonical);
  updateMeta("name", "twitter:card", "summary");
  updateMeta("name", "twitter:title", `${title} | Xavier`);
  updateMeta("name", "twitter:description", description);

  let canonicalLink = document.head.querySelector('link[rel="canonical"]');
  if (!canonicalLink) {
    canonicalLink = document.createElement("link");
    canonicalLink.rel = "canonical";
    document.head.appendChild(canonicalLink);
  }
  canonicalLink.href = canonical;

  window.requestAnimationFrame(() => {
    const announcement = document.getElementById("route-announcer");
    if (announcement) {
      announcement.textContent = `${title} page loaded`;
    }
  });
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