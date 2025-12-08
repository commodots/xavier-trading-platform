import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import "./style.css"; // Tailwind
import axios from "axios";
import VueApexCharts from "vue3-apexcharts";
const app = createApp(App);

app.config.globalProperties.$axios = axios;
axios.defaults.baseURL = "/api";
const token = localStorage.getItem("xavier_token");

if (token) {
    axios.defaults.headers.common["Authorization"] = `Bearer ${token}`;
}
app.component('apexchart', VueApexCharts);
app.use(router);
app.mount("#app");
