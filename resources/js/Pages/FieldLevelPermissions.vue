<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { 
    Shield, Settings, Save, ChevronDown, ChevronUp, 
    Lock, Eye, Plus, Edit, Trash2, Check, X, Info,
    ToggleLeft, ToggleRight
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

const props = defineProps({
    roles: {
        type: Array,
        default: () => []
    },
    permissions: {
        type: Array,
        default: () => []
    },
    permissionsByModule: {
        type: Object,
        default: () => ({})
    }
});

const searchQuery = ref('');
const isSaving = ref(false);
const expandedModules = ref({});

// Initialize permissions from props
const permissions = ref({});

const initializePermissions = () => {
    const perms = {};
    
    if (props.permissions && props.permissions.length > 0) {
        props.permissions.forEach(perm => {
            const key = `${perm.module}-${perm.field}-${perm.action}`;
            perms[key] = {
                id: perm.id,
                module: perm.module,
                field: perm.field,
                action: perm.action,
                description: perm.description,
                allowed_roles: perm.allowed_roles || []
            };
        });
    }
    
    permissions.value = perms;
};

initializePermissions();

const allRoles = computed(() => props.roles || []);
const allModules = computed(() => Object.keys(props.permissionsByModule || {}));

const filteredModules = computed(() => {
    if (!searchQuery.value) return allModules.value;
    const query = searchQuery.value.toLowerCase();
    return allModules.value.filter(module => 
        module.toLowerCase().includes(query)
    );
});

const toggleModule = (module) => {
    expandedModules.value[module] = !expandedModules.value[module];
};

const isModuleExpanded = (module) => {
    return expandedModules.value[module] !== false;
};

const getModulePermissions = (module) => {
    if (!props.permissionsByModule || !props.permissionsByModule[module]) {
        return [];
    }
    return props.permissionsByModule[module];
};

const isRoleSelected = (permKey, role) => {
    return permissions.value[permKey]?.allowed_roles?.includes(role) || false;
};

const toggleRole = (permKey, role) => {
    if (!permissions.value[permKey]) {
        permissions.value[permKey] = { allowed_roles: [] };
    }
    
    const index = permissions.value[permKey].allowed_roles.indexOf(role);
    if (index > -1) {
        permissions.value[permKey].allowed_roles.splice(index, 1);
    } else {
        permissions.value[permKey].allowed_roles.push(role);
    }
};

const selectAllRoles = (permKey) => {
    if (!permissions.value[permKey]) {
        permissions.value[permKey] = { allowed_roles: [] };
    }
    permissions.value[permKey].allowed_roles = [...allRoles.value];
};

const clearAllRoles = (permKey) => {
    if (permissions.value[permKey]) {
        permissions.value[permKey].allowed_roles = [];
    }
};

const savePermissions = () => {
    isSaving.value = true;
    
    const permissionsArray = Object.entries(permissions.value)
        .filter(([key, perm]) => perm && perm.allowed_roles && perm.allowed_roles.length > 0)
        .map(([key, perm]) => {
            const [module, field, action] = key.split('-');
            return {
                id: perm.id || null,
                module,
                field,
                action,
                allowed_roles: perm.allowed_roles,
                is_active: true
            };
        });
    
    const form = useForm({
        permissions: permissionsArray
    });
    
    form.post(route('field-permissions.update'), {
        onSuccess: () => {
            isSaving.value = false;
            alert('Field level permissions saved successfully!');
        },
        onError: (errors) => {
            isSaving.value = false;
            console.error('Save failed:', errors);
            alert('Failed to save permissions. Please try again.');
        }
    });
};

const getRoleBadgeClass = (role) => {
    const roleColors = {
        'admin': 'bg-purple-100 text-purple-700 border-purple-200',
        'manager': 'bg-blue-100 text-blue-700 border-blue-200',
        'supervisor': 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'penyelia': 'bg-indigo-100 text-indigo-700 border-indigo-200',
        'security': 'bg-red-100 text-red-700 border-red-200',
        'livestock': 'bg-teal-100 text-teal-700 border-teal-200',
        'estate': 'bg-orange-100 text-orange-700 border-orange-200',
        'storekeeper': 'bg-amber-100 text-amber-700 border-amber-200',
    };
    return roleColors[role?.toLowerCase()] || 'bg-gray-100 text-gray-700 border-gray-200';
};

const getActionColor = (action) => {
    const colors = {
        'create': 'text-green-600 bg-green-50 border-green-200',
        'view': 'text-blue-600 bg-blue-50 border-blue-200',
        'edit': 'text-orange-600 bg-orange-50 border-orange-200',
        'delete': 'text-red-600 bg-red-50 border-red-200',
        'approve': 'text-purple-600 bg-purple-50 border-purple-200',
        'upload': 'text-cyan-600 bg-cyan-50 border-cyan-200',
        'perform': 'text-pink-600 bg-pink-50 border-pink-200',
        'complete': 'text-emerald-600 bg-emerald-50 border-emerald-200',
        'manage': 'text-indigo-600 bg-indigo-50 border-indigo-200',
    };
    return colors[action] || 'text-gray-600 bg-gray-50 border-gray-200';
};

const formatFieldName = (field) => {
    return field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const formatActionName = (action) => {
    return action.charAt(0).toUpperCase() + action.slice(1);
};

const getPermissionCount = (module) => {
    const perms = getModulePermissions(module);
    return perms.length;
};

const getSelectedCount = (permKey) => {
    return permissions.value[permKey]?.allowed_roles?.length || 0;
};
</script>

<template>
    <Head title="Field Level Permissions" />

    <AppLayout
        title="Field Level Permissions"
        parent="Settings"
        parentUrl="#"
    >
        <div class="max-w-7xl mx-auto">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Field Level Permissions</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Manage granular permissions for each module and field. Control which roles can perform specific actions.
                </p>
            </div>

            <!-- Search and Actions -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <div class="relative w-full md:w-80">
                    <input 
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search modules..."
                        class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none shadow-sm"
                    >
                    <Shield class="w-4 h-4 text-gray-400 absolute left-3 top-3" />
                </div>
                
                <button 
                    @click="savePermissions"
                    :disabled="isSaving"
                    class="px-6 py-2.5 bg-[#34554a] text-white rounded-lg font-medium hover:bg-[#2c463d] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#34554a] transition-all flex items-center gap-2 shadow-sm disabled:opacity-50"
                >
                    <Save class="w-4 h-4" />
                    {{ isSaving ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>

            <!-- Roles Legend -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                <h3 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                    <Info class="w-4 h-4 text-[#34554a]" />
                    Role Legend
                </h3>
                <div class="flex flex-wrap gap-2">
                    <span 
                        v-for="role in roles" 
                        :key="role"
                        :class="['px-3 py-1 rounded-full text-xs font-medium border', getRoleBadgeClass(role)]"
                    >
                        {{ role }}
                    </span>
                </div>
            </div>

            <!-- Modules Cards -->
            <div class="grid gap-4">
                <template v-for="module in filteredModules" :key="module">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <!-- Module Header -->
                        <button 
                            @click="toggleModule(module)"
                            class="w-full px-6 py-4 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white hover:from-gray-100 hover:to-gray-50 transition-all"
                        >
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl bg-[#34554a] flex items-center justify-center shadow-md">
                                    <Shield class="w-6 h-6 text-white" />
                                </div>
                                <div class="text-left">
                                    <h3 class="text-lg font-bold text-gray-900">{{ module }}</h3>
                                    <p class="text-sm text-gray-500">{{ getPermissionCount(module) }} permission(s)</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <ChevronDown 
                                    v-if="!isModuleExpanded(module)"
                                    class="w-5 h-5 text-gray-400" 
                                />
                                <ChevronUp 
                                    v-else
                                    class="w-5 h-5 text-[#34554a]" 
                                />
                            </div>
                        </button>

                        <!-- Module Content -->
                        <div 
                            v-if="isModuleExpanded(module)"
                            class="border-t border-gray-100"
                        >
                            <div class="p-6">
                                <div class="grid gap-3">
                                    <div 
                                        v-for="perm in getModulePermissions(module)" 
                                        :key="perm.id"
                                        class="bg-gray-50 rounded-lg p-4 border border-gray-100 hover:border-gray-200 transition-all"
                                    >
                                        <div class="flex flex-col md:flex-row md:items-center gap-4">
                                            <!-- Field Info -->
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <Lock class="w-4 h-4 text-[#34554a]" />
                                                    <span class="font-semibold text-gray-900">
                                                        {{ formatFieldName(perm.field) }}
                                                    </span>
                                                    <span :class="['px-2 py-0.5 rounded text-xs font-medium border', getActionColor(perm.action)]">
                                                        {{ formatActionName(perm.action) }}
                                                    </span>
                                                </div>
                                                <p class="text-xs text-gray-500 ml-6">
                                                    {{ perm.description || 'No description' }}
                                                </p>
                                            </div>

                                            <!-- Role Toggles -->
                                            <div class="flex flex-wrap gap-2">
                                                <button
                                                    v-for="role in roles"
                                                    :key="role"
                                                    @click="toggleRole(`${perm.module}-${perm.field}-${perm.action}`, role)"
                                                    :class="[
                                                        'px-3 py-1.5 rounded-lg text-xs font-medium border transition-all flex items-center gap-1.5',
                                                        isRoleSelected(`${perm.module}-${perm.field}-${perm.action}`, role)
                                                            ? getRoleBadgeClass(role) + ' shadow-sm'
                                                            : 'bg-white text-gray-400 border-gray-200 hover:border-gray-300'
                                                    ]"
                                                >
                                                    <Check v-if="isRoleSelected(`${perm.module}-${perm.field}-${perm.action}`, role)" class="w-3 h-3" />
                                                    <X v-else class="w-3 h-3" />
                                                    {{ role }}
                                                </button>
                                            </div>

                                            <!-- Quick Actions -->
                                            <div class="flex items-center gap-1 ml-auto">
                                                <button
                                                    @click="selectAllRoles(`${perm.module}-${perm.field}-${perm.action}`)"
                                                    class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                                    title="Select All"
                                                >
                                                    <Check class="w-4 h-4" />
                                                </button>
                                                <button
                                                    @click="clearAllRoles(`${perm.module}-${perm.field}-${perm.action}`)"
                                                    class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                    title="Clear All"
                                                >
                                                    <X class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Empty State -->
                <div 
                    v-if="filteredModules.length === 0"
                    class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center"
                >
                    <Shield class="w-16 h-16 text-gray-300 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Modules Found</h3>
                    <p class="text-gray-500">
                        {{ searchQuery ? 'Try adjusting your search query' : 'No field level permissions configured yet' }}
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
