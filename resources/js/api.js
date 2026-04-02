import axios from "axios";

const API_BASE = import.meta.env.VITE_API_BASE || "https://app.myxavier.com.ng/api";

const api = axios.create({
  baseURL: API_BASE,
  withCredentials: false, // use Bearer token flow instead of Sanctum session cookies
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest"
  }
});

// attach token to all requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem("xavier_token") || localStorage.getItem("token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  } else {
    delete config.headers.Authorization;
  }

  const userStr = localStorage.getItem("user");
  if (userStr) {
    try {
      const user = JSON.parse(userStr);
      const mode = user.trading_mode || 'live';
      config.params = {
        ...config.params,
        mode
      };
    } catch (e) {
      console.error("API Interceptor: Failed to parse user for mode injection", e);
    }
  }

  return config;
}, (error) => Promise.reject(error));

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      console.warn("Unauthorized: Clearing login and redirecting to login...");
      localStorage.removeItem("xavier_token");
      localStorage.removeItem("token");
      localStorage.removeItem("user");
      window.location.href = "/login";
    }
    return Promise.reject(error);
  }
);

export default api;
