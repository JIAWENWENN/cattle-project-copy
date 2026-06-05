<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { Plus, Search, FilterX, ChevronRight, ChevronLeft, Eye, FileSignature, Edit, Trash2, X, Filter, RotateCcw } from 'lucide-vue-next';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'SIV History', parent: 'Transfer', parentUrl: '/transfer/siv' }, () => page)
});

const page = usePage();
const documents = computed(() => page.props.documents?.data || []);

const sivPermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Transfer SIV'];
    return Array.isArray(perms) ? perms : ['no-access'];
});
const hasSivPermission = (action) => {
    if (String(page.props.auth?.user?.role || '').toLowerCase() === 'admin') return true;
    const perms = sivPermissions.value;
    return perms.includes('full') || perms.includes(action);
};
const canViewSiv = computed(() => hasSivPermission('view'));
const canCreateSiv = computed(() => hasSivPermission('create'));
const canEditSiv = computed(() => hasSivPermission('edit'));
const canDeleteSiv = computed(() => hasSivPermission('delete'));
const isAdmin = computed(() => String(page.props.auth?.user?.role || '').toLowerCase() === 'admin');

const isRecordLocked = (record) => record?.status === 'completed';

const searchQuery = ref(page.props.filters?.search || '');
const dateFrom = ref(page.props.filters?.date_from || '');
const dateTo = ref(page.props.filters?.date_to || '');
const fromOperatingUnit = ref(page.props.filters?.from_operating_unit || '');
const toOperatingUnit = ref(page.props.filters?.to_operating_unit || '');
const fromOperatingUnitOptions = computed(() => page.props.fromOperatingUnits || []);
const toOperatingUnitOptions = computed(() => page.props.toOperatingUnits || []);
const currentPage = ref(1);
const itemsPerPage = 10;
const showModal = ref(false);
const selectedDoc = ref(null);
const showDeleteModal = ref(false);
const deleteTarget = ref(null);
const showBulkDeleteModal = ref(false);

// Bulk actions
const selectedRecords = ref([]);
const showBulkActions = ref(false);
const hasSelectedRecords = computed(() => selectedRecords.value.length > 0);

const allSelected = computed(() => {
    const selectableRecords = paginatedDocs.value.filter(isBulkSelectable);
    if (selectableRecords.length === 0) return false;
    return selectableRecords.every(record => selectedRecords.value.includes(record.id));
});

const toggleSelectAll = () => {
    if (allSelected.value) {
        selectedRecords.value = [];
    } else {
        selectedRecords.value = paginatedDocs.value
            .filter(isBulkSelectable)
            .map(record => record.id);
    }
};

const toggleSelectRecord = (record) => {
    if (!isBulkSelectable(record)) return;

    const index = selectedRecords.value.indexOf(record.id);
    if (index > -1) {
        selectedRecords.value.splice(index, 1);
        return;
    }

    selectedRecords.value.push(record.id);
};

const isRecordSelected = (recordId) => selectedRecords.value.includes(recordId);

const bulkDelete = () => {
    if (!canDeleteSiv.value || selectedRecords.value.length === 0) return;
    showBulkDeleteModal.value = true;
};

const confirmBulkDelete = () => {
    if (selectedRecords.value.length === 0) return;

    router.post('/transfer/bulk-delete', {
        ids: selectedRecords.value,
        type: 'SIV',
    }, {
        preserveScroll: true,
        onFinish: () => {
            showBulkDeleteModal.value = false;
            selectedRecords.value = [];
            showBulkActions.value = false;
        },
        onError: (errors) => {
            showBulkDeleteModal.value = false;
            alert(errors?.error || 'Failed to delete selected records. Please try again.');
        },
    });
};

const clearSelection = () => {
    selectedRecords.value = [];
};

const filteredDocs = computed(() => {
    let rows = [...documents.value];
    const query = searchQuery.value.trim().toLowerCase();

    if (query) {
        rows = rows.filter((d) =>
            String(d.document_no || '').toLowerCase().includes(query) ||
            String(d.from_location || '').toLowerCase().includes(query) ||
            String(d.to_location || '').toLowerCase().includes(query)
        );
    }

    return rows;
});

watch(showBulkActions, (visible) => {
    if (!visible) {
        selectedRecords.value = [];
    }
});

const totalPages = computed(() => Math.ceil(filteredDocs.value.length / itemsPerPage));
const paginatedDocs = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredDocs.value.slice(start, start + itemsPerPage);
});

const pageNumbers = computed(() => {
    const pages = [];
    const maxVisible = 5;

    if (totalPages.value <= maxVisible) {
        for (let i = 1; i <= totalPages.value; i++) pages.push(i);
    } else if (currentPage.value <= 3) {
        pages.push(1, 2, 3, '...', totalPages.value);
    } else if (currentPage.value >= totalPages.value - 2) {
        pages.push(1, '...', totalPages.value - 2, totalPages.value - 1, totalPages.value);
    } else {
        pages.push(1, '...', currentPage.value - 1, currentPage.value, currentPage.value + 1, '...', totalPages.value);
    }

    return pages;
});

watch(filteredDocs, () => {
    if (currentPage.value > totalPages.value) currentPage.value = Math.max(1, totalPages.value);
});

const goToPage = (pageNum) => {
    if (pageNum >= 1 && pageNum <= totalPages.value) currentPage.value = pageNum;
};

const previousPage = () => {
    if (currentPage.value > 1) currentPage.value -= 1;
};

const nextPage = () => {
    if (currentPage.value < totalPages.value) currentPage.value += 1;
};

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatTime = (value) => {
    if (!value) return '-';
    const raw = String(value);
    if (/^\d{2}:\d{2}/.test(raw)) return raw.slice(0, 5);
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return raw;
    return date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
};

const formatStatusLabel = (status) => {
    if (!status) return 'Pending';
    const normalized = String(status).replace(/_/g, ' ').toLowerCase();
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

const formatMoney = (value) => {
    const n = Number(value);
    if (!Number.isFinite(n)) return '-';
    return n.toFixed(2);
};

const totalWeight = computed(() => {
    if (!selectedDoc.value?.livestock) return 0;
    return selectedDoc.value.livestock.reduce((sum, row) => sum + (Number(row.weight) || 0), 0);
});

const totalValue = computed(() => {
    if (!selectedDoc.value?.livestock) return 0;
    return selectedDoc.value.livestock.reduce((sum, row) => sum + (Number(row.value) || 0), 0);
});

const applyFilters = () => {
    router.get('/transfer/siv', {
        search: searchQuery.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
        from_operating_unit: fromOperatingUnit.value,
        to_operating_unit: toOperatingUnit.value,
    }, { preserveState: true });
};

const clearFilters = () => {
    searchQuery.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    fromOperatingUnit.value = '';
    toOperatingUnit.value = '';
    router.get('/transfer/siv', {}, { preserveState: true });
};

const goToCreate = () => {
    if (!canCreateSiv.value) return;
    router.visit('/transfer/create/siv');
};
const editRecord = (doc) => {
    if (!canEditRecord(doc)) return;
    router.visit(`/transfer/siv/${doc.id}/edit`);
};
const viewWorkflow = (doc) => {
    if (!canWorkflowRecord(doc)) return;
    router.visit(`/transfer/siv/${doc.id}/workflow`);
};

const reopenRecord = (doc) => {
    if (!canReopenRecord(doc)) return;
    if (!confirm('Reopen this transfer workflow?')) return;
    router.post(`/transfer/${doc.id}/reopen`, {}, { preserveScroll: true });
};
const openViewPopup = (doc) => {
    if (!canViewSiv.value) return;
    selectedDoc.value = doc;
    showModal.value = true;
};
const closeModal = () => {
    showModal.value = false;
    selectedDoc.value = null;
};
const openDeleteModal = (doc) => {
    if (!canDeleteRecord(doc)) return;
    deleteTarget.value = doc;
    showDeleteModal.value = true;
};

const isBulkSelectable = (record) => canDeleteRecord(record);

const canWorkflowRecord = (record) => {
    if (!record || !canViewSiv.value) return false;
    return true;
};

const canReopenRecord = (record) => isAdmin.value && isRecordLocked(record);

const canEditRecord = (record) => {
    if (!record || !canEditSiv.value) return false;
    return !isRecordLocked(record);
};

const canDeleteRecord = (record) => {
    if (!record || !canDeleteSiv.value) return false;
    return !isRecordLocked(record);
};

const getWorkflowDisabledReason = (record) => {
    if (!record) return 'Workflow unavailable';
    if (!canViewSiv.value) return 'You do not have view permission';
    if (isRecordLocked(record)) return 'Disabled: record already marked as completed. Reopen workflow to continue.';
    return 'Workflow';
};

const getEditDisabledReason = (record) => {
    if (!record) return 'Edit unavailable';
    if (!canEditSiv.value) return 'You do not have edit permission';
    if (isRecordLocked(record)) return 'Disabled: record already marked as completed. Reopen workflow to edit.';
    return 'Edit record';
};

const getDeleteDisabledReason = (record) => {
    if (!record) return 'Delete unavailable';
    if (!canDeleteSiv.value) return 'You do not have delete permission';
    if (isRecordLocked(record)) return 'Disabled: record already marked as completed. Reopen workflow to delete.';
    return 'Delete record';
};
const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deleteTarget.value = null;
};
const confirmDelete = () => {
    if (!deleteTarget.value) return;
    router.delete(`/transfer/siv/${deleteTarget.value.id}`, {
        onFinish: () => {
            closeDeleteModal();
        },
    });
};
</script>

<template>
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">SIV history</h1>
                <p class="text-sm text-gray-500 mt-1">Sales issue voucher records</p>
            </div>
            <div class="flex items-center gap-3">
                <button
                    v-if="canDeleteSiv"
                    @click="showBulkActions = !showBulkActions"
                    :class="showBulkActions ? 'bg-[#34554a] text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                >
                    <Filter class="w-4 h-4" />
                    Bulk Actions
                </button>
                <button v-if="canCreateSiv" @click="goToCreate" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors">
                    <Plus class="w-4 h-4" />
                    <span>Create SIV</span>
                </button>
            </div>
        </div>

        <div v-if="showBulkActions && hasSelectedRecords" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4 flex items-center justify-between">
            <div class="text-sm font-semibold text-red-800">
                {{ selectedRecords.length }} record(s) selected
            </div>
            <div class="flex items-center gap-3">
                <button
                    @click="clearSelection"
                    class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors"
                >
                    Clear Selection
                </button>
                <button
                    @click="bulkDelete"
                    class="flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors"
                >
                    <Trash2 class="w-4 h-4" />
                    Delete Selected
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="p-4 flex flex-wrap gap-3 items-center">
                <div class="relative flex-1 min-w-[220px]">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                    <input v-model="searchQuery" @keyup.enter="applyFilters" placeholder="Search document or location..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]" />
                </div>
                <input v-model="dateFrom" type="date" class="px-3 py-2 rounded-lg border border-gray-200 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                <input v-model="dateTo" type="date" class="px-3 py-2 rounded-lg border border-gray-200 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                <select v-model="fromOperatingUnit" class="px-3 py-2 rounded-lg border border-gray-200 text-sm outline-none focus:ring-2 focus:ring-[#34554a] min-w-[220px]">
                    <option value="">From</option>
                    <option v-for="unit in fromOperatingUnitOptions" :key="`siv-from-unit-${unit}`" :value="unit">{{ unit }}</option>
                </select>
                <select v-model="toOperatingUnit" class="px-3 py-2 rounded-lg border border-gray-200 text-sm outline-none focus:ring-2 focus:ring-[#34554a] min-w-[220px]">
                    <option value="">To</option>
                    <option v-for="unit in toOperatingUnitOptions" :key="`siv-to-unit-${unit}`" :value="unit">{{ unit }}</option>
                </select>
                <button @click="applyFilters" class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-200 text-gray-600 hover:bg-gray-50">Apply</button>
                <button v-if="searchQuery || dateFrom || dateTo || fromOperatingUnit || toOperatingUnit" @click="clearFilters" class="px-3 py-2 text-gray-500 text-sm hover:text-gray-700 font-medium flex items-center gap-1">
                    <FilterX class="w-4 h-4" />
                    <span>Clear</span>
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-sm">
                            <th v-if="canDeleteSiv && showBulkActions" class="p-4 font-semibold w-12 text-center">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    @change="toggleSelectAll"
                                    class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                >
                            </th>
                            <th class="p-4 font-semibold w-12">No.</th>
                            <th class="p-4 font-semibold">Document No.</th>
                            <th class="p-4 font-semibold">Date</th>
                            <th class="p-4 font-semibold">From</th>
                            <th class="p-4 font-semibold">To</th>
                            <th class="p-4 font-semibold">Cattle</th>
                            <th class="p-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-sm text-gray-700">
                        <tr v-for="(doc, index) in paginatedDocs" :key="doc.id"
                            :class="[
                                'hover:bg-gray-50 transition-colors',
                                isRecordSelected(doc.id) ? 'bg-blue-50' : ''
                            ]">
                            <td v-if="canDeleteSiv && showBulkActions" class="p-4 text-center">
                                <input
                                    type="checkbox"
                                    :checked="isRecordSelected(doc.id)"
                                    :disabled="!isBulkSelectable(doc)"
                                    @change="toggleSelectRecord(doc)"
                                    class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer disabled:cursor-not-allowed disabled:opacity-40"
                                >
                            </td>
                            <td class="p-4 font-medium text-gray-900">{{ (currentPage - 1) * itemsPerPage + index + 1 }}</td>
                            <td class="p-4 font-mono text-xs text-[#34554a] font-bold">{{ doc.document_no || '-' }}</td>
                            <td class="p-4 text-gray-600">{{ formatDate(doc.date) }}</td>
                            <td class="p-4 text-gray-600">{{ doc.from_location || '-' }}</td>
                            <td class="p-4 text-gray-600">{{ doc.to_location || '-' }}</td>
                            <td class="p-4 text-gray-600">{{ doc.total_cattle || 0 }}</td>
                            <td class="p-4 text-right flex justify-end gap-2">
                                <span v-if="canEditSiv" class="inline-flex" :title="getEditDisabledReason(doc)">
                                    <button
                                        @click="editRecord(doc)"
                                        :disabled="!canEditRecord(doc)"
                                        class="w-8 h-8 rounded flex items-center justify-center transition-colors"
                                        :class="canEditRecord(doc) ? 'text-gray-400 hover:text-[#34554a] hover:bg-[#34554a]/10' : 'text-gray-300 cursor-not-allowed'"
                                    >
                                        <Edit class="w-4 h-4" />
                                    </button>
                                </span>
                                <button
                                    v-if="canViewSiv"
                                    @click="openViewPopup(doc)"
                                    class="w-8 h-8 text-gray-400 hover:text-[#34554a] hover:bg-[#34554a]/10 rounded flex items-center justify-center transition-colors"
                                    title="View Details"
                                >
                                    <Eye class="w-4 h-4" />
                                </button>
                                <span
                                    v-if="canViewSiv"
                                    class="inline-flex"
                                    :title="getWorkflowDisabledReason(doc)"
                                >
                                    <button
                                        @click="viewWorkflow(doc)"
                                        :disabled="!canWorkflowRecord(doc)"
                                        class="w-8 h-8 rounded flex items-center justify-center transition-colors"
                                        :class="canWorkflowRecord(doc) ? 'text-gray-400 hover:text-[#34554a] hover:bg-[#34554a]/10' : 'text-gray-300 cursor-not-allowed'"
                                    >
                                        <FileSignature class="w-4 h-4" />
                                    </button>
                                </span>

                                <span v-if="canDeleteSiv" class="inline-flex" :title="getDeleteDisabledReason(doc)">
                                    <button
                                        @click="openDeleteModal(doc)"
                                        :disabled="!canDeleteRecord(doc)"
                                        class="w-8 h-8 rounded flex items-center justify-center transition-colors"
                                        :class="canDeleteRecord(doc) ? 'text-gray-400 hover:text-red-600 hover:bg-red-50' : 'text-gray-300 cursor-not-allowed'"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </span>
                            </td>
                        </tr>
                        <tr v-if="filteredDocs.length === 0">
                            <td :colspan="canDeleteSiv && showBulkActions ? 8 : 7" class="p-8 text-center text-gray-400 italic">No SIV records found</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col md:flex-row justify-between items-center p-4 border-t border-gray-100 bg-gray-50">
                <div class="text-xs text-gray-500 mb-3 md:mb-0">
                    Showing {{ filteredDocs.length === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, filteredDocs.length) }} of {{ filteredDocs.length }} records
                </div>
                <div class="flex items-center gap-2">
                    <button @click="previousPage" :disabled="currentPage === 1" :class="currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'" class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500">
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                    <button v-for="pageNumber in pageNumbers" :key="`siv-page-${pageNumber}`" @click="pageNumber !== '...' && goToPage(pageNumber)" :class="[pageNumber === currentPage ? 'bg-[#34554a] text-white' : 'text-gray-600 hover:bg-white', pageNumber === '...' ? 'cursor-default' : '']" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors">
                        {{ pageNumber }}
                    </button>
                    <button @click="nextPage" :disabled="currentPage === totalPages || totalPages === 0" :class="currentPage === totalPages || totalPages === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'" class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500">
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="showModal && selectedDoc"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-5 overflow-y-auto"
                @click.self="closeModal"
            >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl mx-auto overflow-hidden border border-gray-100 max-h-[90vh] flex flex-col" @click.stop>
                    <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 sticky top-0">
                        <h3 class="text-lg font-bold text-gray-900">
                            SIV Record Details - {{ selectedDoc.document_no || '-' }}
                        </h3>
                        <button
                            type="button"
                            @click="closeModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="p-6 overflow-y-auto flex-1">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-5">
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3">Document Information</h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Document No.</span>
                                            <span class="font-semibold text-gray-900">{{ selectedDoc.document_no || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">SIV No.</span>
                                            <span class="font-semibold text-gray-900">{{ selectedDoc.siv_no || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Receipt No.</span>
                                            <span class="font-semibold text-gray-900">{{ selectedDoc.receipt_no || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Date</span>
                                            <span class="font-semibold text-gray-900">{{ formatDate(selectedDoc.date) }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Time</span>
                                            <span class="font-semibold text-gray-900">{{ formatTime(selectedDoc.time) }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5">
                                            <span class="text-gray-500">Status</span>
                                            <span class="font-semibold text-gray-900">{{ formatStatusLabel(selectedDoc.status) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3">Transfer Route</h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">From Location</span>
                                            <span class="font-semibold text-gray-900">{{ selectedDoc.from_location || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5">
                                            <span class="text-gray-500">To Location</span>
                                            <span class="font-semibold text-gray-900">{{ selectedDoc.to_location || '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-5">
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3">Customer & Transport</h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Customer</span>
                                            <span class="font-semibold text-gray-900">{{ selectedDoc.customer_name || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Address</span>
                                            <span class="font-semibold text-gray-900 text-right max-w-[60%] break-words">{{ selectedDoc.address || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Vehicle No.</span>
                                            <span class="font-semibold text-gray-900">{{ selectedDoc.vehicle_no || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Driver Name</span>
                                            <span class="font-semibold text-gray-900">{{ selectedDoc.driver_name || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Driver Tel</span>
                                            <span class="font-semibold text-gray-900">{{ selectedDoc.driver_tel || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5">
                                            <span class="text-gray-500">Driver IC</span>
                                            <span class="font-semibold text-gray-900">{{ selectedDoc.driver_ic || '-' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3">Record Summary</h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Type</span>
                                            <span class="font-semibold text-gray-900">{{ selectedDoc.type || 'SIV' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Total Cattle</span>
                                            <span class="font-semibold text-gray-900">{{ selectedDoc.total_cattle || 0 }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Total Weight</span>
                                            <span class="font-semibold text-gray-900">{{ totalWeight ? `${totalWeight} KG` : '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5">
                                            <span class="text-gray-500">Total Value</span>
                                            <span class="font-semibold text-gray-900">RM {{ formatMoney(totalValue) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-xl p-4 mt-6">
                            <h4 class="text-sm font-bold text-gray-800 mb-4">Livestock Details</h4>
                            <div class="overflow-x-auto bg-white rounded-lg border border-gray-200">
                                <table class="w-full text-left whitespace-nowrap text-sm min-w-[1000px]">
                                    <thead>
                                        <tr class="bg-[#34554a] text-white">
                                            <th class="p-3 font-semibold">No.</th>
                                            <th class="p-3 font-semibold">Tag No.</th>
                                            <th class="p-3 font-semibold">Category</th>
                                            <th class="p-3 font-semibold">Colour</th>
                                            <th class="p-3 font-semibold">Weight (KG)</th>
                                            <th class="p-3 font-semibold">Unit Cost (RM)</th>
                                            <th class="p-3 font-semibold">Value (RM)</th>
                                            <th class="p-3 font-semibold">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y text-gray-700">
                                        <tr v-for="(item, index) in selectedDoc.livestock || []" :key="`${item.id || item.tag_no}-${index}`" class="hover:bg-gray-50">
                                            <td class="p-3">{{ index + 1 }}</td>
                                            <td class="p-3 font-medium">{{ item.tag_no || '-' }}</td>
                                            <td class="p-3">{{ item.category || '-' }}</td>
                                            <td class="p-3">{{ item.colour || '-' }}</td>
                                            <td class="p-3">{{ item.weight ? `${item.weight}` : '-' }}</td>
                                            <td class="p-3">{{ item.unit_cost != null ? formatMoney(item.unit_cost) : '-' }}</td>
                                            <td class="p-3">{{ item.value != null ? formatMoney(item.value) : '-' }}</td>
                                            <td class="p-3">{{ item.remarks || '-' }}</td>
                                        </tr>
                                        <tr v-if="!(selectedDoc.livestock || []).length">
                                            <td colspan="8" class="p-6 text-center text-gray-400 italic">No livestock records found</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <DeleteConfirmationModal
            :show="showDeleteModal"
            title="Delete SIV Record"
            message="Are you sure you want to delete this SIV record"
            :item-name="deleteTarget?.document_no || 'this document'"
            @close="closeDeleteModal"
            @confirm="confirmDelete"
        />

        <DeleteConfirmationModal
            :show="showBulkDeleteModal"
            title="Delete SIV Records"
            message="Are you sure you want to delete the selected SIV records"
            :item-name="`${selectedRecords.length} record(s)`"
            @close="showBulkDeleteModal = false"
            @confirm="confirmBulkDelete"
        />
    </div>
</template>
