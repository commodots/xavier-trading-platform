<template>
  <div class="bg-[#111827] rounded-xl p-6 border border-[#1F2A44]">
    <h2 class="text-lg font-semibold mb-4">{{ title }}</h2>
    <canvas ref="canvas"></canvas>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import Chart from "chart.js/auto";

const props = defineProps({
  title: String,
  labels: Array,
  data: Array,
});

const canvas = ref(null);

onMounted(() => {
  new Chart(canvas.value, {
    type: "line",
    data: {
      labels: props.labels,
      datasets: [{
        data: props.data,
        borderColor: "#00D4FF",
        backgroundColor: "rgba(0,212,255,0.2)",
        borderWidth: 2,
        tension: 0.4,
      }],
    },
    options: {
      scales: {
        x: { ticks: { color: "#94A3B8" } },
        y: { ticks: { color: "#94A3B8" } }
      },
      plugins: { legend: { display: false } }
    }
  });
});
</script>
