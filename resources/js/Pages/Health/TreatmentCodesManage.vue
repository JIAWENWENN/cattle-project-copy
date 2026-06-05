<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import {
    Plus, Pencil, Trash, CheckCircle, XCircle, ArrowLeft,
    ClipboardList, FileText, AlertCircle
} from 'lucide-vue-next';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Treatment Codes', parent: 'Health', parentUrl: '/health' }, () => page)
});

const page = usePage();
const treatmentCodes = page.props.treatmentCodes;

const user = page.props.auth?.user;
const canManage = user?.role === 'admin' || user?.role === 'manager';

const editingCode = ref(null);
const editData = ref({ code: '', label: '', description: '', is_active: true });
const showAddModal = ref(false);
const newCodeData = ref({ code: '', label: '', description: '', is_active: true });

if (!canManage) {
    router.visit('/health');
}

// Handle edit
const startEdit = (code) => {
    editingCode.value = code.id;
    editData.value = {
        code: code.code,
        label: code.label,
        description: code.description,
        is_active: code.is_active
    };
};

const cancelEdit = () => {
    editingCode.value = null;
    editData.value = { code: '', label: '', description: '', is_active: true };
};

const updateCode = async (id) => {
    try {
        const response = await fetch(`/health/treatment-codes/${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify(editData.value)
        });

        if (response.ok) {
            router.reload();
            editingCode.value = null;
            editData.value = { code: '', label: '', description: '', is_active: true };
        } else {
            const error = await response.json();
            alert('Error updating code: ' + (error.message || JSON.stringify(error.errors)));
        }
    } catch (error) {
        alert('Network error occurred');
    }
};

// Handle delete
const deleteCode = async (id, code) => {
    if (!confirm(`Are you sure you want to delete "${code}"?`)) {
        return;
    }

    try {
        const response = await fetch(`/health/treatment-codes/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        });

        if (response.ok) {
            router.reload();
        } else {
            const error = await response.json();
            alert('Error deleting code: ' + (error.message || JSON.stringify(error.errors)));
        }
    } catch (error) {
        alert('Network error occurred');
    }
};

// Handle add new
const addNewCode = async () => {
    try {
        const response = await fetch('/health/treatment-codes', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify(newCodeData.value)
        });

        if (response.ok) {
            router.reload();
            showAddModal.value = false;
            newCodeData.value = { code: '', label: '', description: '', is_active: true };
        } else {
            const error = await response.json();
            alert('Error adding code: ' + (error.message || JSON.stringify(error.errors)));
        }
    } catch (error) {
        alert('Network error occurred');
    }
};

// Toggle active status
const toggleStatus = async (code) => {
    try {
        const response = await fetch(`/health/treatment-codes/${code.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                code: code.code,
                label: code.label,
                description: code.description,
                is_active: !code.is_active
            })
        });

        if (response.ok) {
            router.reload();
        } else {
            alert('Error updating status');
        }
    } catch (error) {
        alert('Network error occurred');
    }
};
</script>

<template>
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="$inertia.visit('/health')" class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-gray-600" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Treatment Codes Management</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage treatment codes for medications.</p>
                </div>
            </div>

            <div class="flex gap-3" v-if="canManage">
                <button @click="showAddModal = true" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm">
                    <Plus class="w-4 h-4" />
                    Add New Code
                </button>
            </div>
        </div>

        <!-- Alert for access denied -->
        <div v-if="!canManage" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex items-center gap-2">
                <AlertCircle class="w-5 h-5 text-red-600" />
                <p class="text-red-700 text-sm">You don't have permission to manage treatment codes.</p>
            </div>
        </div>

        <!-- Codes List -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Label</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" v-if="canManage">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="code in treatmentCodes" :key="code.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <input
                                        v-if="editingCode === code.id"
                                        v-model="editData.code"
                                        class="w-full px-2 py-1 border rounded text-sm focus:ring-2 focus:ring-[#34554a]"
                                        required
                                    >
                                    <span v-else>{{ code.code }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <input
                                        v-if="editingCode === code.id"
                                        v-model="editData.label"
                                        class="w-full px-2 py-1 border rounded text-sm focus:ring-2 focus:ring-[#34554a]"
                                        required
                                    >
                                    <span v-else>{{ code.label }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <textarea
                                        v-if="editingCode === code.id"
                                        v-model="editData.description"
                                        class="w-full px-2 py-1 border rounded text-sm focus:ring-2 focus:ring-[#34554a] resize-none"
                                        rows="2"
                                    ></textarea>
                                    <span v-else>{{ code.description || '-' }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button
                                        @click="toggleStatus(code)"
                                        :disabled="!canManage"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium"
                                        :class="code.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                                    >
                                        <CheckCircle v-if="code.is_active" class="w-3 h-3" />
                                        <XCircle v-else class="w-3 h-3" />
                                        {{ code.is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2" v-if="canManage">
                                    <button
                                        v-if="editingCode === code.id"
                                        @click="updateCode(code.id)"
                                        class="text-green-600 hover:text-green-900"
                                    >
                                        Save
                                    </button>
                                    <button
                                        v-if="editingCode === code.id"
                                        @click="cancelEdit"
                                        class="text-gray-600 hover:text-gray-900"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        v-if="editingCode !== code.id"
                                        @click="startEdit(code)"
                                        class="text-blue-600 hover:text-blue-900 mr-2"
                                        title="Edit"
                                    >
                                        <Pencil class="w-4 h-4" />
                                    </button>
                                    <button
                                        v-if="editingCode !== code.id"
                                        @click="deleteCode(code.id, code.code)"
                                        class="text-red-600 hover:text-red-900"
                                        title="Delete"
                                    >
                                        <Trash class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="treatmentCodes && treatmentCodes.length === 0" class="text-center py-8 text-gray-500">
                        No treatment codes found.
                    </div>
                </div>
            </div>
        </div>

        <!-- Add New Code Modal -->
        <div v-if="showAddModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50" @click.self="showAddModal = false; newCodeData = { code: '', label: '', description: '', is_active: true }">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <h3 class="text-lg font-semibold mb-4">Add New Treatment Code</h3>
                <form @submit.prevent="addNewCode" class="space-y-4">
                    <div>
                        <input
                            v-model="newCodeData.code"
                            type="text"
                            class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#34554a] text-sm"
                            placeholder="Enter treatment code (e.g., OTC, VIT)"
                            required
                            maxlength="10"
                        >
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button
                            type="button"
                            @click="showAddModal = false; newCodeData = { code: '', label: '', description: '', is_active: true }"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-[#34554a] text-white rounded-lg text-sm hover:bg-[#2a443b]"
                        >
                            Add Code
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
