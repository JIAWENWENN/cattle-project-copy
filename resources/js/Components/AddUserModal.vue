<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, Plus, Edit3, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    show: Boolean,
    availableRoles: Array,
    editingUser: Object  // ← CRITICAL: Receives user data for editing
});

const emit = defineEmits(['close']);

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: 'Admin',
    status: 'active'
});

const isCustomRoleMode = ref(false);
const customRoleValue = ref('');
const isEditMode = ref(false);
const editingRoleIndex = ref(-1);
const customRoles = ref([]);
const hiddenRoles = ref([]);

const roleLabelMap = {
    'assistant manager': 'Assistant Manager',
    'livestock': 'Sr.Assistant Livestock',
    'livestock manager': 'Livestock Manager',
    'act livestock manager': 'ACT. Livestock Manager',
    'penyelia': 'Penyelia Security',
    'security': 'Sr.Assistant Security',
    'supervisor': 'Livestock Supervisor',
};

const normalizeRoleKey = (role) => String(role || '')
    .toLowerCase()
    .replace(/_/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const displayRoleLabel = (role) => {
    const key = normalizeRoleKey(role);
    return roleLabelMap[key] || role;
};

// ↓↓↓ CRITICAL: Check if we're editing
const isEditing = computed(() => !!props.editingUser);

// ↓↓↓ CRITICAL: Watch for editingUser changes and pre-fill form
watch(() => props.editingUser, (user) => {
    if (user) {
        // EDIT MODE: Pre-fill form with user data
        form.name = user.name;
        form.email = user.email;
        form.password = '';  // Don't show password
        form.role = user.role;
        form.status = user.status;
    } else {
        // ADD MODE: Reset form
        form.reset();
    }
}, { immediate: true });

const roleOptions = computed(() => {
    const dbRoles = props.availableRoles || [];
    const allRoles = [...new Set([...dbRoles, ...customRoles.value])];
    const visibleRoles = allRoles.filter(role => !hiddenRoles.value.includes(role));
    return visibleRoles.length > 0 ? visibleRoles.sort() : ['Admin'];
});

const switchToNewMode = () => {
    isCustomRoleMode.value = true;
    isEditMode.value = false;
    editingRoleIndex.value = -1;
    customRoleValue.value = '';
};

const switchToEditMode = () => {
    isCustomRoleMode.value = true;
    isEditMode.value = true;
    const currentRole = form.role;
    editingRoleIndex.value = roleOptions.value.indexOf(currentRole);
    customRoleValue.value = currentRole;
};

const deleteCurrentRole = () => {
    const currentRole = form.role;

    if (!currentRole) {
        alert('Please select a role to delete');
        return;
    }

    if (roleOptions.value.length <= 1) {
        alert('At least one role must remain');
        return;
    }

    if (!confirm(`Delete role "${currentRole}" from this list?`)) {
        return;
    }

    const customIndex = customRoles.value.indexOf(currentRole);
    if (customIndex > -1) {
        customRoles.value.splice(customIndex, 1);
    }

    if (!hiddenRoles.value.includes(currentRole)) {
        hiddenRoles.value.push(currentRole);
    }

    const nextRole = roleOptions.value.find(role => role !== currentRole) || 'Admin';
    form.role = nextRole;

    if (isCustomRoleMode.value) {
        cancelCustomMode();
    }
};

const cancelCustomMode = () => {
    isCustomRoleMode.value = false;
    isEditMode.value = false;
    editingRoleIndex.value = -1;
    customRoleValue.value = '';
};

const applyCustomRole = () => {
    const trimmedValue = customRoleValue.value.trim();

    if (!trimmedValue) {
        alert('Please enter a role name');
        return;
    }

    if (isEditMode.value && editingRoleIndex.value > -1) {
        const oldRole = roleOptions.value[editingRoleIndex.value];
        const customIndex = customRoles.value.indexOf(oldRole);

        if (customIndex > -1) {
            customRoles.value.splice(customIndex, 1);
        }

        if (!customRoles.value.includes(trimmedValue)) {
            customRoles.value.push(trimmedValue);
        }

        if (!hiddenRoles.value.includes(oldRole)) {
            hiddenRoles.value.push(oldRole);
        }

        form.role = trimmedValue;
    } else {
        if (!roleOptions.value.includes(trimmedValue)) {
            customRoles.value.push(trimmedValue);
        }
        form.role = trimmedValue;
    }

    cancelCustomMode();
};

// ↓↓↓ CRITICAL: Submit function handles BOTH create and update
const submit = () => {
    if (isCustomRoleMode.value && customRoleValue.value.trim()) {
        applyCustomRole();
    }

    if (isEditing.value) {
        // UPDATE EXISTING USER
        console.log('Updating user ID:', props.editingUser.id);
        form.put(route('users.update', props.editingUser.id), {
            onSuccess: () => {
                form.reset();
                cancelCustomMode();
                emit('close');
            },
            onError: (errors) => {
                console.error('Update errors:', errors);
            }
        });
    } else {
        // CREATE NEW USER
        console.log('Creating new user');
        form.post(route('users.store'), {
            onSuccess: () => {
                form.reset();
                cancelCustomMode();
                emit('close');
            },
            onError: (errors) => {
                console.error('Create errors:', errors);
            }
        });
    }
};

const close = () => {
    form.reset();
    cancelCustomMode();
    emit('close');
};
</script>

<template>
    <Transition name="modal">
        <div v-if="show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 backdrop-blur-sm" @click.self="close">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md transform transition-all" @click.stop>

                <!-- Header - Title changes based on mode -->
                <div class="flex justify-between items-center p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">{{ isEditing ? 'Edit User' : 'Add New User' }}</h3>
                    <button @click="close" class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-1 rounded transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Body -->
                <form @submit.prevent="submit">
                    <div class="p-6 space-y-5">

                        <!-- Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-2">Full Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="Enter full name"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2F4F4F] focus:border-transparent text-sm"
                                required
                            />
                            <div v-if="form.errors.name" class="text-red-600 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-2">Email Address</label>
                            <input
                                v-model="form.email"
                                type="email"
                                placeholder="name@company.com"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2F4F4F] focus:border-transparent text-sm"
                                required
                            />
                            <div v-if="form.errors.email" class="text-red-600 text-xs mt-1">{{ form.errors.email }}</div>
                        </div>

                        <!-- Password - Label and requirement changes based on mode -->
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-2">
                                {{ isEditing ? 'New Password (optional)' : 'Initial Password' }}
                            </label>
                            <input
                                v-model="form.password"
                                type="password"
                                :placeholder="isEditing ? 'Leave blank to keep current password' : 'Create a strong password'"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2F4F4F] focus:border-transparent text-sm"
                                :required="!isEditing"
                                minlength="8"
                            />
                            <p class="text-xs text-gray-500 mt-1">
                                {{ isEditing ? 'Leave blank to keep current password' : 'Must be at least 8 characters long' }}
                            </p>
                            <div v-if="form.errors.password" class="text-red-600 text-xs mt-1">{{ form.errors.password }}</div>
                        </div>

                        <!-- Role & Status -->
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Role with Custom Field -->
                            <div>
                                <div class="flex justify-between items-baseline mb-2">
                                    <label class="block text-sm font-medium text-gray-900">Role</label>

                                     <div v-if="!isCustomRoleMode" class="flex gap-2">
                                         <button type="button" @click="switchToNewMode" class="flex items-center gap-1 text-[#2F4F4F] hover:text-[#1e3636] text-xs font-semibold transition-colors">
                                             <Plus class="w-3 h-3" />
                                             New
                                         </button>
                                         <button type="button" @click="switchToEditMode" class="flex items-center gap-1 text-[#2F4F4F] hover:text-[#1e3636] text-xs font-semibold transition-colors">
                                             <Edit3 class="w-3 h-3" />
                                             Edit
                                         </button>
                                         <button type="button" @click="deleteCurrentRole" class="flex items-center gap-1 text-red-600 hover:text-red-700 text-xs font-semibold transition-colors">
                                             <Trash2 class="w-3 h-3" />
                                             Delete
                                         </button>
                                     </div>

                                    <button v-else type="button" @click="cancelCustomMode" class="text-red-600 hover:text-red-700 text-xs font-semibold transition-colors">
                                        Cancel
                                    </button>
                                </div>

                                <select v-if="!isCustomRoleMode" v-model="form.role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2F4F4F] focus:border-transparent text-sm">
                                    <option v-for="role in roleOptions" :key="role" :value="role">{{ displayRoleLabel(role) }}</option>
                                </select>

                                <div v-else class="space-y-2">
                                    <input
                                        v-model="customRoleValue"
                                        type="text"
                                        :placeholder="isEditMode ? 'Edit role name' : 'Enter new role name'"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2F4F4F] focus:border-transparent text-sm"
                                        @keyup.enter="applyCustomRole"
                                    />
                                    <button
                                        type="button"
                                        @click="applyCustomRole"
                                        class="w-full px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium"
                                    >
                                        {{ isEditMode ? 'Update Role' : 'Add Role' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-900 mb-2">Status</label>
                                <select v-model="form.status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2F4F4F] focus:border-transparent text-sm">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                    </div>

                    <!-- Footer - Button text changes based on mode -->
                    <div class="flex justify-end gap-3 p-6 bg-gray-50 border-t border-gray-200 rounded-b-xl">
                        <button type="button" @click="close" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors text-sm font-medium">
                            Cancel
                        </button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-[#2F4F4F] text-white rounded-lg hover:bg-[#1e3636] transition-colors text-sm font-medium disabled:opacity-50">
                            {{ form.processing ? (isEditing ? 'Updating...' : 'Adding...') : (isEditing ? 'Update User' : 'Add User') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from, .modal-leave-to {
    opacity: 0;
}
.modal-enter-active .bg-white,
.modal-leave-active .bg-white {
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-enter-from .bg-white {
    transform: scale(0.95) translateY(10px);
}
.modal-leave-to .bg-white {
    transform: scale(0.95);
}
</style>
