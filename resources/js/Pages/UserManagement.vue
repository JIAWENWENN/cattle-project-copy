<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Users, UserCheck, UserX, Shield, Search, Plus, Download, Edit3, Trash2, ChevronLeft, ChevronRight, X } from 'lucide-vue-next';
import AddUserModal from '@/Components/AddUserModal.vue';
import DeleteUserModal from '@/Components/DeleteUserModal.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    users: Array,
    availableRoles: Array
});

const showModal = ref(false);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const selectedRole = ref('');
const selectedStatus = ref('');
const editingUser = ref(null);  // ← Already here
const showDeleteModal = ref(false);
const deletingUser = ref(null);
const selectedPhotoUser = ref(null);

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
// ↓↓↓ ADD THESE TWO FUNCTIONS ↓↓↓
// Function to open modal for adding user
const openAddModal = () => {
    editingUser.value = null;
    showModal.value = true;
};

// Function to open modal for editing user
// Function to open modal for editing user
const openEditModal = (user) => {
    editingUser.value = { ...user };
    showModal.value = true;
};

// Function to open delete confirmation modal
const openDeleteModal = (user) => {
    deletingUser.value = user;
    showDeleteModal.value = true;
};

const uniqueRolesCount = computed(() => {
    return new Set(props.users.map(u => u.role)).size;
});

const filteredUsers = computed(() => {
    let filtered = props.users;

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(user =>
            user.name.toLowerCase().includes(query) ||
            user.email.toLowerCase().includes(query) ||
            user.role.toLowerCase().includes(query) ||
            displayRoleLabel(user.role).toLowerCase().includes(query)
        );
    }

    if (selectedRole.value && selectedRole.value !== 'All Roles') {
        filtered = filtered.filter(user => user.role === selectedRole.value);
    }

    if (selectedStatus.value && selectedStatus.value !== 'All Status') {
        filtered = filtered.filter(user => user.status === selectedStatus.value.toLowerCase());
    }

    return filtered;
});

const totalPages = computed(() => {
    return Math.ceil(filteredUsers.value.length / itemsPerPage);
});

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredUsers.value.slice(start, end);
});

const pageNumbers = computed(() => {
    const pages = [];
    const maxVisible = 5;

    if (totalPages.value <= maxVisible) {
        for (let i = 1; i <= totalPages.value; i++) {
            pages.push(i);
        }
    } else {
        if (currentPage.value <= 3) {
            pages.push(1, 2, 3, '...', totalPages.value);
        } else if (currentPage.value >= totalPages.value - 2) {
            pages.push(1, '...', totalPages.value - 2, totalPages.value - 1, totalPages.value);
        } else {
            pages.push(1, '...', currentPage.value - 1, currentPage.value, currentPage.value + 1, '...', totalPages.value);
        }
    }

    return pages;
});

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

const previousPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--;
    }
};

const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
};

const getUserPhotoUrl = (user) => {
    const path = user?.profile_photo;
    if (!path) return null;
    if (String(path).startsWith('http://') || String(path).startsWith('https://')) return path;
    if (String(path).startsWith('/storage/')) return path;
    return `/storage/${path}`;
};

const getUserInitial = (user) => {
    return (user?.name || 'U').charAt(0).toUpperCase();
};

const openPhotoPopup = (user) => {
    if (!getUserPhotoUrl(user)) return;
    selectedPhotoUser.value = user;
};

const closePhotoPopup = () => {
    selectedPhotoUser.value = null;
};
</script>

<template>
    <Head title="User Management" />

    <AppLayout
        title="User Account"
        parent="Settings"
        parentUrl="#"
    >
        <!-- Page Header -->
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">User Management</h1>
                <p class="text-sm text-gray-500 mt-1">Manage user roles, access, and account status.</p>
            </div>
            <div class="flex items-center gap-3">
                <button class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                    <Download class="w-4 h-4" />
                    Export
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition-shadow">
                <div class="w-14 h-14 rounded-full flex items-center justify-center bg-blue-50 text-blue-600">
                    <Users class="w-7 h-7" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Total Users</p>
                    <p class="text-3xl font-extrabold text-gray-900">{{ props.users.length }}</p>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition-shadow">
                <div class="w-14 h-14 rounded-full flex items-center justify-center bg-green-50 text-green-600">
                    <UserCheck class="w-7 h-7" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Active Users</p>
                    <p class="text-3xl font-extrabold text-gray-900">{{ props.users.filter(u => u.status === 'active').length }}</p>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition-shadow">
                <div class="w-14 h-14 rounded-full flex items-center justify-center bg-red-50 text-red-600">
                    <UserX class="w-7 h-7" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Inactive Users</p>
                    <p class="text-3xl font-extrabold text-gray-900">{{ props.users.filter(u => u.status === 'inactive').length }}</p>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition-shadow">
                <div class="w-14 h-14 rounded-full flex items-center justify-center bg-amber-50 text-amber-600">
                    <Shield class="w-7 h-7" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Roles</p>
                    <p class="text-3xl font-extrabold text-gray-900">{{ new Set(props.users.map(u => u.role)).size }}</p>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4 bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
            <div class="relative w-full md:w-96">
                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search users..."
                    class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm"
                />
            </div>

            <div class="flex gap-2 w-full md:w-auto">
                <select
                    v-model="selectedRole"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-gray-200"
                >
                    <option value="">All Roles</option>
                    <option v-for="role in availableRoles" :key="role" :value="role">{{ displayRoleLabel(role) }}</option>
                </select>

                <select
                    v-model="selectedStatus"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-600 text-sm focus:outline-none focus:ring-2 focus:ring-gray-200"
                >
                    <option value="">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>

                <button @click="openAddModal" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg font-medium hover:bg-opacity-90 transition-colors shadow-sm text-sm">
                    <Plus class="w-4 h-4" />
                    Add User
                </button>

            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                    <tr class="bg-[#34554a] text-white">
                        <th class="py-4 px-6 font-semibold text-sm">Name</th>
                        <th class="py-4 px-6 font-semibold text-sm">Email</th>
                        <th class="py-4 px-6 font-semibold text-sm">Role</th>
                        <th class="py-4 px-6 font-semibold text-sm">Status</th>
                        <th class="py-4 px-6 font-semibold text-sm">Last Login</th>
                        <th class="py-4 px-6 font-semibold text-sm text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                    <tr v-for="user in paginatedUsers" :key="user.id" class="border-b border-gray-100 hover:bg-gray-50 transition-colors group">
                        <td class="py-4 px-6">
                            <div class="flex items-center gap-3">
                                <button
                                    v-if="getUserPhotoUrl(user)"
                                    type="button"
                                    @click="openPhotoPopup(user)"
                                    :title="'Click to view photo'"
                                    class="w-10 h-10 rounded-full overflow-hidden border border-gray-200 flex items-center justify-center"
                                >
                                    <img
                                        :src="getUserPhotoUrl(user)"
                                        :alt="`${user.name} profile photo`"
                                        class="w-full h-full object-cover"
                                    />
                                </button>
                                <span class="font-medium text-gray-900">{{ user.name }}</span>
                            </div>
                        </td>
                        <td class="py-4 px-6 text-gray-500">{{ user.email }}</td>
                        <td class="py-4 px-6">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ displayRoleLabel(user.role) }}
                                </span>
                        </td>
                        <td class="py-4 px-6">
    <span
        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
        :class="user.status === 'active'
            ? 'bg-green-100 text-green-800 border-green-200'
            : 'bg-red-100 text-red-800 border-red-200'"
    >
        {{ user.status === 'active' ? 'Active' : 'Inactive' }}
    </span>
                        </td>
                        <td class="py-4 px-6 text-gray-500 text-sm">  <!-- ADD THIS -->
                            {{ user.last_login_at ? new Date(user.last_login_at).toLocaleString() : 'Never' }}
                        </td>
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-3 opacity-60 group-hover:opacity-100 transition-opacity">
                                <button @click="openEditModal(user)" class="text-gray-400 hover:text-green-600 transition-colors">
                                    <Edit3 class="w-4 h-4" />
                                </button>
                                <button @click="openDeleteModal(user)" class="text-gray-400 hover:text-red-600 transition-colors">
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="flex flex-col md:flex-row justify-between items-center p-4 border-t border-gray-100 bg-gray-50">
                <div class="flex items-center gap-2 mb-4 md:mb-0">
                    <button
                        @click="previousPage"
                        :disabled="currentPage === 1"
                        :class="currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                        class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>

                    <button
                        v-for="page in pageNumbers"
                        :key="page"
                        @click="page !== '...' && goToPage(page)"
                        :class="[
                            page === currentPage ? 'bg-[#34554a] text-white' : 'text-gray-600 hover:bg-white',
                            page === '...' ? 'cursor-default' : ''
                        ]"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors"
                    >
                        {{ page }}
                    </button>

                    <button
                        @click="nextPage"
                        :disabled="currentPage === totalPages"
                        :class="currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                        class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>

                <div class="text-sm text-gray-500">
                    Showing <span class="font-semibold text-gray-800">{{ (currentPage - 1) * itemsPerPage + 1 }}-{{ Math.min(currentPage * itemsPerPage, filteredUsers.length) }}</span> of <span class="font-semibold text-gray-800">{{ filteredUsers.length }}</span> users
                </div>

            </div>
        </div>

        <!-- Add User Modal -->
        <AddUserModal
            :show="showModal"
            :available-roles="availableRoles"
            :editing-user="editingUser"
            @close="showModal = false; editingUser = null"
        />

        <!-- Delete User Modal -->
        <DeleteUserModal
            :show="showDeleteModal"
            :user="deletingUser"
            @close="showDeleteModal = false; deletingUser = null"
        />

        <!-- Profile Photo Popup -->
        <Teleport to="body">
            <div
                v-if="selectedPhotoUser"
                class="fixed inset-0 z-[120] flex items-center justify-center p-4"
            >
                <div class="absolute inset-0 bg-black/70" @click="closePhotoPopup"></div>
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-xl overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-800">{{ selectedPhotoUser.name }}'s Profile Photo</h3>
                        <button
                            type="button"
                            @click="closePhotoPopup"
                            class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                    <div class="p-4 bg-gray-50 flex items-center justify-center">
                        <img
                            :src="getUserPhotoUrl(selectedPhotoUser)"
                            :alt="`${selectedPhotoUser.name} profile photo`"
                            class="max-h-[70vh] w-auto max-w-full object-contain rounded-lg"
                        />
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
