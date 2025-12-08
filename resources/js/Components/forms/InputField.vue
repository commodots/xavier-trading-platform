<template>
  <div>
    <label class="text-gray-400 text-sm">{{ label }}</label>
    <input
      :type="type"
      v-model="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      class="w-full mt-1 bg-transparent border border-gray-600 rounded-lg px-4 py-2"
    />
  </div>
</template>

<script setup>
defineProps({
  label: String,
  modelValue: [String, Number],
  type: { type: String, default: "text" }
});

defineEmits(["update:modelValue"]);
</script>
