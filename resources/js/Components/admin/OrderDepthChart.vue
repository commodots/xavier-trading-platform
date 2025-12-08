<template>
  <div>
    <apexchart v-if="ready" type="area" height="220" :options="options" :series="series" />
    <div v-else class="text-gray-400 text-sm">Loading depth chart...</div>
  </div>
</template>

<script setup>
import { computed, watch, ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
  bids: { type: Array, default: () => [] },
  asks: { type: Array, default: () => [] }
});

const ready = ref(true);

// build cumulative series from bids/asks
function toCumulative(sideArray, ascending = false) {
  // sideArray: [{price,amount}, ...]
  const arr = sideArray
    .slice()
    .sort((a, b) => ascending ? a.price - b.price : b.price - a.price)
    .map((r) => ({ price: r.price, amount: r.amount }));
  let cum = 0;
  return arr.map((r) => {
    cum += Number(r.amount);
    return { x: r.price, y: cum };
  }).reverse(); // nicer orientation
}

const series = ref([
  { name: 'Asks (cumulative)', data: [] },
  { name: 'Bids (cumulative)', data: [] }
]);

const options = {
  chart: {
    id: 'depth',
    toolbar: { show: false },
    animations: { enabled: true }
  },
  dataLabels: { enabled: false },
  stroke: { curve: 'stepline' },
  fill: { opacity: 0.4 },
  xaxis: { type: 'numeric', title: { text: 'Price' } },
  yaxis: { title: { text: 'Cumulative Quantity' } },
  colors: ['#FF6B6B', '#4ADE80'],
  legend: { show: true }
};

function rebuild() {
  const askData = toCumulative(props.asks, true); // ascending
  const bidData = toCumulative(props.bids, false); // descending
  series.value = [
    { name: 'Asks (cumulative)', data: askData },
    { name: 'Bids (cumulative)', data: bidData }
  ];
}

watch(() => [props.bids, props.asks], rebuild, { immediate: true, deep: true });

// expose apexchart component to app-level resolution
// (app must register vue3-apexcharts globally or import before use)
</script>
