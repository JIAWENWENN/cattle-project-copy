<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import {
    Plus, Search, FilterX, ChevronRight, ChevronLeft, Eye, FileSignature, Edit, Trash2, Filter,
    ArrowLeftRight, Calendar, Truck, FileText, RotateCcw
} from 'lucide-vue-next';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'CTV History', parent: 'Transfer', parentUrl: '/transfer/ctv' }, () => page)
});

const page = usePage();
const documents = computed(() => page.props.documents?.data || []);
const transferWorkflowAssignment = computed(() => page.props.transferWorkflowAssignment || null);
const userId = computed(() => Number(page.props.auth?.user?.id || 0));

const ctvAssignmentKeys = [
    'issued_by_user_ids',
    'approved_by_user_ids',
    'transported_by_user_ids',
    'witnessed_transit_by_user_ids',
    'verified_transit_by_user_ids',
    'witnessed_receive_by_user_ids',
    'received_by_user_ids',
    'completed_by_user_ids',
];
const ctvLegacyKeys = [
    'issued_by_user_id',
    'approved_by_user_id',
    'transported_by_user_id',
    'witnessed_transit_by_user_id',
    'verified_transit_by_user_id',
    'witnessed_receive_by_user_id',
    'received_by_user_id',
    'completed_by_user_id',
];

const getAssignedIdsForStep = (stepIndex) => {
    const key = ctvAssignmentKeys[stepIndex];
    const legacyKey = ctvLegacyKeys[stepIndex];
    let ids = transferWorkflowAssignment.value?.[key];
    if (!Array.isArray(ids) || ids.length === 0) {
        const legacyId = transferWorkflowAssignment.value?.[legacyKey];
        ids = legacyId ? [legacyId] : [];
    }
    return Array.isArray(ids) ? ids.map((v) => Number(v)) : [];
};

const isUserAssignedToAnyStep = () => {
    for (let i = 0; i < ctvAssignmentKeys.length; i++) {
        if (getAssignedIdsForStep(i).includes(userId.value)) return true;
    }
    return false;
};

const ctvPermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Transfer CTV'];
    return Array.isArray(perms) ? perms : ['no-access'];
});
const hasCtvPermission = (action) => {
    if (String(page.props.auth?.user?.role || '').toLowerCase() === 'admin') return true;
    const perms = ctvPermissions.value;
    return perms.includes('full') || perms.includes(action);
};
const canViewCtv = computed(() => hasCtvPermission('view'));
const canCreateCtv = computed(() => hasCtvPermission('create'));
const canEditCtv = computed(() => hasCtvPermission('edit'));
const canDeleteCtv = computed(() => hasCtvPermission('delete'));
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
const selectedRecord = ref(null);
const showDeleteModal = ref(false);
const recordToDelete = ref(null);
const showBulkDeleteModal = ref(false);

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
    if (!canDeleteCtv.value || selectedRecords.value.length === 0) return;
    showBulkDeleteModal.value = true;
};

const confirmBulkDelete = () => {
    if (selectedRecords.value.length === 0) return;

    router.post('/transfer/bulk-delete', {
        ids: selectedRecords.value,
        type: 'CTV',
    }, {
        preserveScroll: true,
        onFinish: () => {
            showBulkDeleteModal.value = false;
            selectedRecords.value = [];
            showBulkActions.value = false;
        },
        onError: () => {
            showBulkDeleteModal.value = false;
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
    if (!status) return '-';
    const normalized = String(status).replace(/_/g, ' ').toLowerCase();
    if (normalized === 'pending') return 'Pending';
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

const getConditionLabel = (item) => {
    if (item?.condition_good) return 'Good';
    if (item?.condition_not_good) return 'Not Good';
    return '-';
};

const applyFilters = () => {
    router.get('/transfer/ctv', {
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
    router.get('/transfer/ctv', {}, { preserveState: true });
};

const goToCreate = () => {
    if (!canCreateCtv.value) return;
    router.visit('/transfer/create/ctv');
};
const editRecord = (doc) => {
    if (!canEditRecord(doc)) return;
    router.visit(`/transfer/ctv/${doc.id}/edit`);
};
const viewWorkflow = (doc) => {
    if (!canWorkflowRecord(doc)) return;
    router.visit(`/transfer/ctv/${doc.id}/workflow`);
};

const reopenRecord = (doc) => {
    if (!canReopenRecord(doc)) return;
    if (!confirm('Reopen this transfer workflow? Cattle locations will be restored to before completion.')) return;
    router.post(`/transfer/${doc.id}/reopen`, {}, { preserveScroll: true });
};

const openViewPopup = (doc) => {
    if (!canViewCtv.value) return;
    selectedRecord.value = doc;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedRecord.value = null;
};

const openDeleteModal = (doc) => {
    if (!canDeleteRecord(doc)) return;
    recordToDelete.value = doc;
    showDeleteModal.value = true;
};

const confirmDeleteRecord = () => {
    if (!recordToDelete.value?.id) return;

    router.delete(`/transfer/ctv/${recordToDelete.value.id}`, {
        onFinish: () => {
            showDeleteModal.value = false;
            recordToDelete.value = null;
        },
    });
};

const isBulkSelectable = (record) => canDeleteRecord(record);

const canWorkflowRecord = (record) => {
    if (!record) return false;
    return canViewCtv.value || isUserAssignedToAnyStep();
};

const canReopenRecord = (record) => isAdmin.value && isRecordLocked(record);

const canEditRecord = (record) => {
    if (!record || !canEditCtv.value) return false;
    return !isRecordLocked(record);
};

const canDeleteRecord = (record) => {
    if (!record || !canDeleteCtv.value) return false;
    return !isRecordLocked(record);
};

const getWorkflowDisabledReason = (record) => {
    if (!record) return 'Workflow unavailable';
    if (isRecordLocked(record)) return 'Disabled: record already marked as completed. Reopen workflow to continue.';
    if (!canViewCtv.value && !isUserAssignedToAnyStep()) return 'You are not assigned to this workflow';
    return 'Workflow';
};

const getEditDisabledReason = (record) => {
    if (!record) return 'Edit unavailable';
    if (!canEditCtv.value) return 'You do not have edit permission';
    if (isRecordLocked(record)) return 'Disabled: record already marked as completed. Reopen workflow to edit.';
    return 'Edit record';
};

const getDeleteDisabledReason = (record) => {
    if (!record) return 'Delete unavailable';
    if (!canDeleteCtv.value) return 'You do not have delete permission';
    if (isRecordLocked(record)) return 'Disabled: record already marked as completed. Reopen workflow to delete.';
    return 'Delete record';
};

watch(showBulkActions, (visible) => {
    if (!visible) {
        selectedRecords.value = [];
    }
});
</script>

<template>
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">CTV history</h1>
                <p class="text-sm text-gray-500 mt-1">Cattle transfer voucher records</p>
            </div>
            <div class="flex items-center gap-3">
                <button
                    v-if="canDeleteCtv"
                    @click="showBulkActions = !showBulkActions"
                    :class="showBulkActions ? 'bg-[#34554a] text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                >
                    <Filter class="w-4 h-4" />
                    Bulk Actions
                </button>
                <button v-if="canCreateCtv" @click="goToCreate" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors">
                    <Plus class="w-4 h-4" />
                    Create CTV
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
                    <option v-for="unit in fromOperatingUnitOptions" :key="`from-unit-${unit}`" :value="unit">{{ unit }}</option>
                </select>
                <select v-model="toOperatingUnit" class="px-3 py-2 rounded-lg border border-gray-200 text-sm outline-none focus:ring-2 focus:ring-[#34554a] min-w-[220px]">
                    <option value="">To</option>
                    <option v-for="unit in toOperatingUnitOptions" :key="`to-unit-${unit}`" :value="unit">{{ unit }}</option>
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
                            <th v-if="canDeleteCtv && showBulkActions" class="p-4 font-semibold w-12 text-center">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    @change="toggleSelectAll"
                                    class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                >
                            </th>
                            <th class="p-4 font-semibold w-12">#</th>
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
                            <td v-if="canDeleteCtv && showBulkActions" class="p-4 text-center">
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
                                <span
                                    v-if="canEditCtv"
                                    class="inline-flex"
                                    :title="getEditDisabledReason(doc)"
                                >
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
                                    v-if="canViewCtv"
                                    @click="openViewPopup(doc)"
                                    class="w-8 h-8 text-gray-400 hover:text-[#34554a] hover:bg-[#34554a]/10 rounded flex items-center justify-center transition-colors"
                                    title="View Details"
                                >
                                    <Eye class="w-4 h-4" />
                                </button>
                                <span
                                    v-if="canViewCtv || isUserAssignedToAnyStep()"
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

                                <span
                                    v-if="canDeleteCtv"
                                    class="inline-flex"
                                    :title="getDeleteDisabledReason(doc)"
                                >
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
                            <td :colspan="canDeleteCtv && showBulkActions ? 8 : 7" class="p-8 text-center text-gray-400 italic">No CTV records found</td>
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
                    <button v-for="pageNumber in pageNumbers" :key="`ctv-page-${pageNumber}`" @click="pageNumber !== '...' && goToPage(pageNumber)" :class="[pageNumber === currentPage ? 'bg-[#34554a] text-white' : 'text-gray-600 hover:bg-white', pageNumber === '...' ? 'cursor-default' : '']" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors">
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
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-5 overflow-y-auto"
                @click.self="closeModal"
            >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl mx-auto overflow-hidden border border-gray-100 max-h-[90vh] flex flex-col" @click.stop>
                    <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 sticky top-0">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <ArrowLeftRight class="w-5 h-5 text-[#34554a]" />
                            <span>CTV Record Details - {{ selectedRecord?.document_no || '-' }}</span>
                        </h3>
                        <button
                            type="button"
                            @click="closeModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200"
                        >
                            ✕
                        </button>
                    </div>
                    <div class="p-6 overflow-y-auto flex-1">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-5">
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                        <FileText class="w-4 h-4 text-[#34554a]" />
                                        Document Information
                                    </h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Document No.</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.document_no || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Form Document No.</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.form_document_no || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Revision No.</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.revision_no || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Date</span>
                                            <span class="font-semibold text-gray-900">{{ formatDate(selectedRecord?.date) }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Time</span>
                                            <span class="font-semibold text-gray-900">{{ formatTime(selectedRecord?.time) }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5">
                                            <span class="text-gray-500">Status</span>
                                            <span class="font-semibold text-gray-900">{{ formatStatusLabel(selectedRecord?.status) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                        <ArrowLeftRight class="w-4 h-4 text-[#34554a]" />
                                        Transfer Route
                                    </h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">From Location</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.from_location || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5">
                                            <span class="text-gray-500">To Location</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.to_location || '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-5">
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                        <Edit class="w-4 h-4 text-[#34554a]" />
                                        Other Details
                                    </h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Total Cattle</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.total_cattle || 0 }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Created By</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.creator?.name || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Vehicle No.</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.vehicle_no || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5">
                                            <span class="text-gray-500">Driver</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.driver_name || '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                        <Calendar class="w-4 h-4 text-[#34554a]" />
                                        Record Summary
                                    </h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Type</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.type || 'CTV' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Current Step</span>
                                            <span class="font-semibold text-gray-900">{{ formatStatusLabel(selectedRecord?.current_step) }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5">
                                            <span class="text-gray-500">Created At</span>
                                            <span class="font-semibold text-gray-900">{{ formatDate(selectedRecord?.created_at) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-xl p-4 mt-6">
                            <h4 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
                                <Truck class="w-4 h-4 text-[#34554a]" />
                                Livestock Details
                            </h4>
                            <div class="overflow-x-auto bg-white rounded-lg border border-gray-200">
                                <table class="w-full text-left whitespace-nowrap text-sm">
                                    <thead>
                                        <tr class="bg-[#34554a] text-white">
                                            <th class="p-3 font-semibold">No.</th>
                                            <th class="p-3 font-semibold">Tag No.</th>
                                            <th class="p-3 font-semibold">Category</th>
                                            <th class="p-3 font-semibold">Colour</th>
                                            <th class="p-3 font-semibold">Weight</th>
                                            <th class="p-3 font-semibold">Condition</th>
                                            <th class="p-3 font-semibold">Purpose</th>
                                            <th class="p-3 font-semibold">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y text-gray-700">
                                        <tr v-for="(item, index) in selectedRecord?.livestock || []" :key="`${item.id || item.tag_no}-${index}`" class="hover:bg-gray-50">
                                            <td class="p-3">{{ index + 1 }}</td>
                                            <td class="p-3 font-medium">{{ item.tag_no || '-' }}</td>
                                            <td class="p-3">{{ item.category || '-' }}</td>
                                            <td class="p-3">{{ item.colour || '-' }}</td>
                                            <td class="p-3">{{ item.weight ? `${item.weight} KG` : '-' }}</td>
                                            <td class="p-3">{{ getConditionLabel(item) }}</td>
                                            <td class="p-3">{{ item.purpose || '-' }}</td>
                                            <td class="p-3">{{ item.remarks || '-' }}</td>
                                        </tr>
                                        <tr v-if="!(selectedRecord?.livestock || []).length">
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
            title="Delete CTV Record"
            message="Are you sure you want to delete this CTV record"
            :item-name="recordToDelete?.document_no || 'this record'"
            @close="showDeleteModal = false; recordToDelete = null"
            @confirm="confirmDeleteRecord"
        />

        <DeleteConfirmationModal
            :show="showBulkDeleteModal"
            title="Delete CTV Records"
            message="Are you sure you want to delete the selected CTV records"
            :item-name="`${selectedRecords.length} record(s)`"
            @close="showBulkDeleteModal = false"
            @confirm="confirmBulkDelete"
        />
    </div>
</template>
