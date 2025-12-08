<template>
  <apexchart
    type="line"
    height="40"
    :options="options"
    :series="[{ data }]"
  />
</template>

<script setup>
import VueApexCharts from "vue3-apexcharts";

const apexchart = VueApexCharts;

defineProps({
  data: Array,
});

const options = {
  chart: { sparkline: { enabled: true } },
  stroke: { width: 2, curve: "smooth" },
  colors: ["#00D4FF"],
  tooltip: { enabled: false },
};
</script>
