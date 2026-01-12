<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
    <div class="bg-[#1C1F2E] rounded-2xl p-6 w-full max-w-lg border border-[#1F2A44]">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">Assign Role — {{ localUser.email || 'user' }}</h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-white">✖</button>
      </div>

      <div class="space-y-4">
        <div>
          <label class="text-sm text-gray-400">Assign roles (multiple allowed)</label>
          <div class="grid grid-cols-2 gap-2 mt-2">
            <label v-for="r in roles" :key="r" class="flex items-center gap-2">
              <input type="checkbox" :value="r" v-model="selected" class="w-4 h-4" />
              <span class="text-sm">{{ r }}</span>
            </label>
          </div>
        </div>

        <div v-if="note" class="text-xs text-yellow-300">
          {{ note }}
        </div>

        <div class="flex justify-end gap-3 mt-4">
          <button @click="$emit('close')" class="px-3 py-2 text-sm bg-transparent border border-gray-600 rounded">Cancel</button>
          <button @click="save" :disabled="saving || saved" class="px-4 py-2 rounded bg-[#00D4FF] text-black font-semibold">
            {{ saved ? 'Saved' : saving ? 'Saving...' : 'Save Role' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import api from '@/api';
const props = defineProps({
  user: { type: Object, required: true }
});
const emit = defineEmits(['close','role-updated']);

const roles = ['user','admin','accounts','compliance','manager','support'];
const selected = ref((props.user && props.user.roles && props.user.roles.length)
  ? props.user.roles.map(r => (typeof r === 'string' ? r : r.name))
  : [props.user?.role || 'user']
);
const saving = ref(false);
const saved = ref(false);
const note = ref('Admins can access the admin dashboard and user management.');

const localUser = ref(props.user || {});


watch(selected, (newSelection) => {
  if (Array.isArray(newSelection) && newSelection.includes('admin')) {
    note.value = 'Full system access. Can manage users and settings.';
  } else if (Array.isArray(newSelection) && newSelection.length === 1 && newSelection[0] === 'user') {
    note.value = 'Standard client access only.';
  } else if (Array.isArray(newSelection) && newSelection.length > 0) {
    note.value = `Staff access: Limited to ${newSelection.join(', ')} permissions.`;
  } else {
    note.value = '';
  }
});

watch(() => props.user, (v) => {
  localUser.value = v || {};
 
  if (v && Array.isArray(v.roles) && v.roles.length) {
    selected.value = v.roles.map(r => (typeof r === 'string' ? r : r.name));
  } else if (v && v.role) {
    selected.value = [v.role];
  } else {
    selected.value = ['user'];
  }
}, { immediate: true });

const save = async () => {
  if (!localUser.value.id) {
    alert('Missing user id');
    return;
  }
  saving.value = true;
  try {
    const res = await api.post(`/admin/users/${localUser.value.id}/role`, { roles: selected.value });

    if (res.data && res.data.success) {
      saved.value = true;
      emit('role-updated', res.data.roles || selected.value);
      setTimeout(() => {
        emit('close');
      }, 3000);
    } else {
      alert(res.data.message || 'Failed to update role');
    }
  } catch (err) {
    console.error(err);
    alert(err.response?.data?.message || 'Server error');
  } finally {
    saving.value = false;
  }
};
</script>

<style scoped>
/* Modal specific adjustments */
</style>
