<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Search, Filter, RotateCcw, Plus, Edit, Trash2, X, ChevronLeft, ChevronRight, Settings, Pencil
} from 'lucide-vue-next';

// Props from Laravel controller
const props = defineProps({
    records: { type: Array, default: () => [] },
    pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 50, total: 0, from: null, to: null }) },
    dailyMatrix: { type: Object, default: () => ({}) },
    tripRows: { type: Array, default: () => [] },
    tripGroups: { type: Object, default: () => ({}) },
    trips: { type: Array, default: () => [] },
    feedTypes: { type: Array, default: () => [] },
    feedingOptions: { type: Object, default: () => ({ trip_no: [], feed_type: [] }) },
    summary: { type: Array, default: () => [] },
    tripDateDefaults: { type: Object, default: () => ({}) },
    viewMode: { type: String, default: 'daily' },
    filters: { type: Object, default: () => ({ start_date: '', end_date: '', trip: '', feed_type: '', per_page: 50 }) }
});

const page = usePage();
const userRole = computed(() => page.props.auth?.user?.role || '');
const feedingModulePermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Feeding Record'];
    if (!Array.isArray(perms)) return ['no-access'];
    return perms
        .map((perm) => String(perm || '').toLowerCase().trim())
        .filter(Boolean);
});

const canonicalRole = (role) => String(role || '').toLowerCase().trim();

const hasFeedingPermission = (action) => {
    if (canonicalRole(userRole.value) === 'admin') return true;
    const perms = feedingModulePermissions.value;
    return perms.includes('full') || perms.includes(action);
};

const canCreateFeeding = computed(() => hasFeedingPermission('create'));
const canEditFeeding = computed(() => hasFeedingPermission('edit'));
const canDeleteFeeding = computed(() => hasFeedingPermission('delete'));
const canManageFeedingOptions = computed(() => canEditFeeding.value || canDeleteFeeding.value);

// ==========================================
// FILTER STATE
// ==========================================
const filterStartDate = ref(props.filters.start_date || '');
const filterEndDate = ref(props.filters.end_date || '');
const filterTrip = ref(props.filters.trip || '');
const filterFeedType = ref(props.filters.feed_type || '');

const toDisplayDate = (value) => {
    if (!value) return '';
    const [year, month, day] = String(value).split('-');
    if (!year || !month || !day) return value;
    return `${day}/${month}/${year}`;
};

const toInputDate = (value) => {
    if (!value) return '';
    const trimmed = String(value).trim();
    if (trimmed.includes('-')) return trimmed;
    const parts = trimmed.split('/');
    if (parts.length !== 3) return trimmed;
    const [day, month, year] = parts;
    if (!day || !month || !year) return trimmed;
    const dd = day.padStart(2, '0');
    const mm = month.padStart(2, '0');
    return `${year}-${mm}-${dd}`;
};

// ==========================================
// MODAL STATE
// ==========================================
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const showDeleteConfirm = ref(false);
const deletingId = ref(null);
const showOptionModal = ref(false);
const optionFieldType = ref('trip_no');
const newOptionValue = ref('');
const editingOptionId = ref(null);
const editingOptionValue = ref('');
const optionErrors = ref({});

const form = useForm({
    date: '',
    trip_no: '',
    cattle_count: 0,
    feed_type: 'Napier',
    planned: 0,
    actual_usage: 0,
    receive: 0,
    carry_forward: 0,
    balance: 0,
    remarks: '',
});

const tripOptions = computed(() => props.trips || []);
const feedTypeOptions = computed(() => props.feedTypes || []);
const currentManagedOptions = computed(() => {
    if (optionFieldType.value === 'trip_no') {
        const managedTripOptions = props.feedingOptions?.trip_no || [];
        if (managedTripOptions.length > 0) {
            return managedTripOptions;
        }
        return (props.trips || []).map(t => ({ id: t, value: t }));
    }
    return props.feedingOptions?.[optionFieldType.value] || [];
});
const optionPage = ref(1);
const optionPageSize = 5;
const paginatedOptions = computed(() => {
    const all = currentManagedOptions.value;
    const start = (optionPage.value - 1) * optionPageSize;
    return all.slice(start, start + optionPageSize);
});
const totalOptionPages = computed(() => Math.ceil(currentManagedOptions.value.length / optionPageSize));
const optionModalTitle = computed(() => optionFieldType.value === 'trip_no' ? 'Trip Options' : 'Feed Type Options');

const displayOption = (value) => {
    if (!value) return '';
    return String(value).toUpperCase() === 'OPF' ? 'OPF' : String(value).charAt(0).toUpperCase() + String(value).slice(1).toLowerCase();
};

const openOptionModal = (fieldType) => {
    if (!canManageFeedingOptions.value) return;
    optionFieldType.value = fieldType;
    newOptionValue.value = '';
    editingOptionId.value = null;
    editingOptionValue.value = '';
    optionErrors.value = {};
    optionPage.value = 1;
    showOptionModal.value = true;
};

const addOption = () => {
    if (!newOptionValue.value.trim()) return;
    router.post('/feeding/options', {
        field_type: optionFieldType.value,
        value: newOptionValue.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            newOptionValue.value = '';
            optionErrors.value = {};
            router.reload({ only: ['trips', 'feedTypes', 'feedingOptions'] });
        },
        onError: (errors) => {
            optionErrors.value = errors || {};
        },
    });
};

const startEditOption = (option) => {
    editingOptionId.value = option.id;
    editingOptionValue.value = option.value;
    optionErrors.value = {};
};

const cancelOptionEdit = () => {
    editingOptionId.value = null;
    editingOptionValue.value = '';
    optionErrors.value = {};
};

const saveOptionEdit = () => {
    if (!editingOptionId.value || !editingOptionValue.value.trim()) return;

    router.put(`/feeding/options/${editingOptionId.value}`, {
        field_type: optionFieldType.value,
        value: editingOptionValue.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            cancelOptionEdit();
            router.reload({ only: ['trips', 'feedTypes', 'feedingOptions'] });
        },
        onError: (errors) => {
            optionErrors.value = errors || {};
        },
    });
};

const deleteOption = (option) => {
    if (!confirm(`Delete "${option.value}"?`)) return;
    router.delete(`/feeding/options/${option.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (editingOptionId.value === option.id) {
                cancelOptionEdit();
            }
            router.reload({ only: ['trips', 'feedTypes', 'feedingOptions'] });
        },
    });
};

const autoFillCattleCount = () => {
    if (!isEditing.value && form.trip_no && form.date) {
        const key = `${form.date}|${form.trip_no}`;
        const existing = props.tripDateDefaults[key];
        if (existing) {
            form.cattle_count = existing.cattle_count;
            form.remarks = existing.remarks || '';
        }
    }
};

const previewBalance = computed(() => Number(form.carry_forward || 0) + Number(form.receive || 0) - Number(form.actual_usage || 0));

const openAddModal = () => {
    if (!canCreateFeeding.value) return;
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.date = filterStartDate.value || new Date().toISOString().split('T')[0];
    showModal.value = true;
};

const openEditModal = (record) => {
    if (!canEditFeeding.value) return;
    isEditing.value = true;
    editingId.value = record.id;
    form.date = record.date;
    form.trip_no = record.trip_no;
    form.cattle_count = record.cattle_count;
    form.feed_type = record.feed_type;
    form.planned = record.planned || 0;
    form.actual_usage = record.actual_usage || 0;
    form.receive = record.receive || 0;
    form.carry_forward = record.carry_forward || 0;
    form.balance = record.balance || 0;
    form.remarks = record.remarks || '';
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.clearErrors();
};

const submitForm = () => {
    if (isEditing.value && !canEditFeeding.value) return;
    if (!isEditing.value && !canCreateFeeding.value) return;

    const refreshAfterSubmit = () => {
        closeModal();
        router.reload({
            preserveState: false,
            only: ['records', 'pagination', 'dailyMatrix', 'tripRows', 'tripGroups', 'summary', 'tripDateDefaults', 'viewMode', 'filters', 'trips', 'feedingOptions'],
        });
    };

    if (isEditing.value) {
        console.log('Editing record ID:', editingId.value);
        form.put(`/feeding/${editingId.value}`, {
            preserveState: false,
            onSuccess: () => {
                console.log('Edit successful');
                refreshAfterSubmit();
            },
            onError: (errors) => {
                console.error('Edit errors:', errors);
                alert('Error: ' + JSON.stringify(errors));
            },
        });
    } else {
        form.post('/feeding', {
            preserveState: false,
            onSuccess: refreshAfterSubmit,
            onError: (errors) => {
                console.error('Create errors:', errors);
                alert('Error: ' + JSON.stringify(errors));
            },
        });
    }
};

const confirmDelete = (id) => {
    if (!canDeleteFeeding.value) return;
    deletingId.value = id;
    showDeleteConfirm.value = true;
};

const deleteRecord = () => {
    router.delete(`/feeding/${deletingId.value}`, {
        onSuccess: () => {
            showDeleteConfirm.value = false;
            deletingId.value = null;
        },
    });
};

// ==========================================
// PAGINATION (Individual Records)
// ==========================================
const paginatedRecords = computed(() => props.records || []);
const currentPage = computed(() => Number(props.pagination?.current_page || 1));
const totalPages = computed(() => Number(props.pagination?.last_page || 1));
const itemsPerPage = computed(() => Number(props.pagination?.per_page || 50));

const pageNumbers = computed(() => {
    const pages = [];
    const maxVisible = 5;
    if (totalPages.value <= maxVisible) {
        for (let i = 1; i <= totalPages.value; i++) pages.push(i);
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
    if (page < 1 || page > totalPages.value || page === currentPage.value) return;

    router.get('/feeding', {
        start_date: toInputDate(filterStartDate.value),
        end_date: toInputDate(filterEndDate.value),
        trip: filterTrip.value,
        feed_type: filterFeedType.value,
        per_page: itemsPerPage.value,
        page
    }, { preserveState: true, preserveScroll: true, replace: true });
};
const previousPage = () => { if (currentPage.value > 1) goToPage(currentPage.value - 1); };
const nextPage = () => { if (currentPage.value < totalPages.value) goToPage(currentPage.value + 1); };

// ==========================================
// COMPUTED VALUES
// ==========================================
const sortedDates = computed(() => {
    return Object.keys(props.dailyMatrix).sort((a, b) => new Date(a) - new Date(b));
});

const matrixFeedTypes = ['Napier', 'Silage', 'Baller'];

const tripGroupsWithTotals = computed(() => {
    const result = [];
    const groups = props.tripGroups;
    for (const prefix of Object.keys(groups).sort()) {
        const trips = groups[prefix];
        trips.forEach(trip => {
            result.push({ type: 'row', ...trip });
        });
        if (trips.length > 1) {
            const subtotal = {
                type: 'subtotal', label: 'TOTAL',
                cattle_count: trips.reduce((s, t) => s + t.cattle_count, 0),
                napier_total: trips.reduce((s, t) => s + t.napier_total, 0),
                silage_total: trips.reduce((s, t) => s + t.silage_total, 0),
                baller_total: trips.reduce((s, t) => s + t.baller_total, 0),
                receive_total: trips.reduce((s, t) => s + (t.receive_total || 0), 0),
                planning_total: trips.reduce((s, t) => s + t.planning_total, 0),
                actual_total: trips.reduce((s, t) => s + t.actual_total, 0),
            };
            const cc = subtotal.cattle_count;
            subtotal.napier_kghead = cc > 0 ? Math.round(subtotal.napier_total / cc * 10) / 10 : 0;
            subtotal.silage_kghead = cc > 0 ? Math.round(subtotal.silage_total / cc * 10) / 10 : 0;
            subtotal.baller_kghead = cc > 0 ? Math.round(subtotal.baller_total / cc * 10) / 10 : 0;
            result.push(subtotal);
        }
    }
    return result;
});

const grandTotal = computed(() => {
    const rows = props.tripRows;
    const gt = {
        cattle_count: rows.reduce((s, t) => s + t.cattle_count, 0),
        napier_total: rows.reduce((s, t) => s + t.napier_total, 0),
        silage_total: rows.reduce((s, t) => s + t.silage_total, 0),
        baller_total: rows.reduce((s, t) => s + t.baller_total, 0),
        receive_total: rows.reduce((s, t) => s + (t.receive_total || 0), 0),
        planning_total: rows.reduce((s, t) => s + t.planning_total, 0),
        actual_total: rows.reduce((s, t) => s + t.actual_total, 0),
    };
    const cc = gt.cattle_count;
    gt.napier_kghead = cc > 0 ? Math.round(gt.napier_total / cc * 10) / 10 : 0;
    gt.silage_kghead = cc > 0 ? Math.round(gt.silage_total / cc * 10) / 10 : 0;
    gt.baller_kghead = cc > 0 ? Math.round(gt.baller_total / cc * 10) / 10 : 0;
    return gt;
});

const displayDate = computed(() => {
    if (props.filters.start_date) {
        return toDisplayDate(props.filters.start_date);
    }
    return '';
});

// ==========================================
// ACTIONS
// ==========================================
const applyFilter = () => {
    router.get('/feeding', {
        start_date: toInputDate(filterStartDate.value),
        end_date: toInputDate(filterEndDate.value),
        trip: filterTrip.value,
        feed_type: filterFeedType.value,
        per_page: itemsPerPage.value,
        page: 1,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const clearFilter = () => {
    filterStartDate.value = '';
    filterEndDate.value = '';
    filterTrip.value = '';
    filterFeedType.value = '';
    router.get('/feeding', { per_page: itemsPerPage.value, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
};

const fmt = (v) => {
    if (v === null || v === undefined) return '-';
    return Number(v).toLocaleString();
};
</script>

<template>
    <Head title="Feeding Management" />

    <AppLayout title="Feeding Records" parent="Feeding" parentUrl="/feeding">
        <!-- Page Header -->
        <div class="mb-6 print:mb-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Cattle Feeding Management System</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage feeding plans, track daily stock, and monitor usage across trips.</p>
                </div>
                <div class="flex items-center gap-2 print:hidden">
                    <button
                        v-if="canCreateFeeding"
                        @click="openAddModal"
                        class="flex items-center gap-2 bg-[#34554a] text-white px-5 py-2.5 rounded-lg font-bold hover:bg-opacity-90 text-sm shadow-md transition-all"
                    >
                        <Plus class="w-5 h-5" />
                        Add Record
                    </button>
                </div>
            </div>
        </div>

        <!-- FILTER PANEL -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-6 print:hidden">
            <div class="flex items-center gap-2 mb-4">
                <Filter class="w-4 h-4 text-[#34554a]" />
                <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wider">Filter Panel</h2>
            </div>
            <div class="flex flex-col md:flex-row items-end gap-4">
                <div class="flex-1 w-full md:w-auto">
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">Start Date</label>
                    <input v-model="filterStartDate" type="text" placeholder="dd/mm/yyyy" class="w-full px-3 py-2.5 rounded-lg border border-gray-200 text-sm focus:ring-2 focus:ring-[#34554a] outline-none bg-gray-50" />
                </div>
                <div class="flex-1 w-full md:w-auto">
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">End Date</label>
                    <input v-model="filterEndDate" type="text" placeholder="dd/mm/yyyy" class="w-full px-3 py-2.5 rounded-lg border border-gray-200 text-sm focus:ring-2 focus:ring-[#34554a] outline-none bg-gray-50" />
                </div>
                <div class="flex-1 w-full md:w-auto">
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">Trip</label>
                    <select v-model="filterTrip" class="w-full px-3 py-2.5 rounded-lg border border-gray-200 text-sm focus:ring-2 focus:ring-[#34554a] outline-none bg-gray-50">
                        <option value="">All Trips</option>
                        <option v-for="trip in tripOptions" :key="trip" :value="trip">{{ displayOption(trip) }}</option>
                    </select>
                </div>
                <div class="flex-1 w-full md:w-auto">
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">Feed Type</label>
                    <select v-model="filterFeedType" class="w-full px-3 py-2.5 rounded-lg border border-gray-200 text-sm focus:ring-2 focus:ring-[#34554a] outline-none bg-gray-50">
                        <option value="">All Feed Types</option>
                        <option v-for="ft in feedTypeOptions" :key="ft" :value="ft">{{ displayOption(ft) }}</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button @click="applyFilter" class="flex items-center gap-2 bg-[#34554a] text-white px-5 py-2.5 rounded-lg font-medium hover:bg-opacity-90 text-sm shadow-sm transition-all text-nowrap">
                        <Search class="w-4 h-4" /> Apply Filter
                    </button>
                    <button @click="clearFilter" class="flex items-center gap-2 bg-white border border-gray-200 text-gray-600 px-4 py-2.5 rounded-lg font-medium hover:bg-gray-50 text-sm transition-all">
                        <RotateCcw class="w-4 h-4" /> Clear
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================== -->
        <!-- VIEW 1: TRIP BREAKDOWN (single date) -->
        <!-- ========================================================== -->
        <div v-if="props.viewMode === 'trip'" class="bg-white rounded-xl shadow-lg border border-gray-300 mb-5 overflow-hidden">
            <div class="bg-gray-100 p-4 border-b border-gray-200 text-center">
                <h2 class="text-lg font-black text-gray-800 uppercase tracking-[0.15em]">Daily Feeding Record</h2>
                <p v-if="displayDate" class="text-sm font-bold text-gray-600 mt-1">Date: {{ displayDate }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-center border-collapse min-w-[1100px]">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-[11px] font-bold">
                            <th rowspan="2" class="border border-[#2a443b] py-3 px-2 w-20">Trip</th>
                            <th rowspan="2" class="border border-[#2a443b] py-3 px-2 w-16">Cattle</th>
                            <th colspan="2" class="border border-[#2a443b] py-3 px-2">Napier</th>
                            <th colspan="2" class="border border-[#2a443b] py-3 px-2">Silage</th>
                            <th colspan="2" class="border border-[#2a443b] py-3 px-2">Baller</th>
                            <th rowspan="2" class="border border-[#2a443b] py-3 px-2 w-20">Receive</th>
                            <th rowspan="2" class="border border-[#2a443b] py-3 px-2 w-20">Planned</th>
                            <th rowspan="2" class="border border-[#2a443b] py-3 px-2 w-20">Actual</th>
                            <th rowspan="2" class="border border-[#2a443b] py-3 px-2 w-24">Remarks</th>
                        </tr>
                        <tr class="bg-[#3d6358] text-white text-[10px] font-semibold">
                            <th class="border border-[#2a443b] py-2 px-1 w-16">Total</th>
                            <th class="border border-[#2a443b] py-2 px-1 w-16">KG/HEAD</th>
                            <th class="border border-[#2a443b] py-2 px-1 w-16">Total</th>
                            <th class="border border-[#2a443b] py-2 px-1 w-16">KG/HEAD</th>
                            <th class="border border-[#2a443b] py-2 px-1 w-16">Total</th>
                            <th class="border border-[#2a443b] py-2 px-1 w-16">KG/HEAD</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs">
                        <template v-for="(row, idx) in tripGroupsWithTotals" :key="idx">
                            <tr v-if="row.type === 'subtotal'" class="bg-gray-200 font-extrabold text-gray-800 border-t-2 border-gray-400">
                                <td class="border border-gray-300 py-2 px-2 text-right">{{ row.label }}</td>
                                <td class="border border-gray-300 py-2 px-2">{{ fmt(row.cattle_count) }}</td>
                                <td class="border border-gray-300 py-2 px-2">{{ fmt(row.napier_total) }}</td>
                                <td class="border border-gray-300 py-2 px-2">{{ row.napier_kghead }}</td>
                                <td class="border border-gray-300 py-2 px-2">{{ fmt(row.silage_total) }}</td>
                                <td class="border border-gray-300 py-2 px-2">{{ row.silage_kghead }}</td>
                                <td class="border border-gray-300 py-2 px-2">{{ fmt(row.baller_total) }}</td>
                                <td class="border border-gray-300 py-2 px-2">{{ row.baller_kghead }}</td>
                                <td class="border border-gray-300 py-2 px-2">{{ fmt(row.receive_total) }}</td>
                                <td class="border border-gray-300 py-2 px-2">{{ fmt(row.planning_total) }}</td>
                                <td class="border border-gray-300 py-2 px-2">{{ fmt(row.actual_total) }}</td>
                                <td class="border border-gray-300 py-2 px-2"></td>
                            </tr>
                            <tr v-else class="hover:bg-gray-50 transition-colors">
                                <td class="border border-gray-200 py-2 px-2 font-bold text-gray-800">{{ row.trip_no }}</td>
                                <td class="border border-gray-200 py-2 px-2">{{ row.cattle_count }}</td>
                                <td class="border border-gray-200 py-2 px-2">{{ fmt(row.napier_total) }}</td>
                                <td class="border border-gray-200 py-2 px-2 text-gray-500">{{ row.napier_kghead }}</td>
                                <td class="border border-gray-200 py-2 px-2">{{ fmt(row.silage_total) }}</td>
                                <td class="border border-gray-200 py-2 px-2 text-gray-500">{{ row.silage_kghead }}</td>
                                <td class="border border-gray-200 py-2 px-2">{{ fmt(row.baller_total) }}</td>
                                <td class="border border-gray-200 py-2 px-2 text-gray-500">{{ row.baller_kghead }}</td>
                                <td class="border border-gray-200 py-2 px-2">{{ fmt(row.receive_total) }}</td>
                                <td class="border border-gray-200 py-2 px-2">{{ fmt(row.planning_total) }}</td>
                                <td class="border border-gray-200 py-2 px-2">{{ fmt(row.actual_total) }}</td>
                                <td class="border border-gray-200 py-2 px-2 text-[11px] text-gray-500">{{ row.remarks }}</td>
                            </tr>
                        </template>
                        <tr v-if="tripGroupsWithTotals.length === 0">
                            <td colspan="12" class="py-16 text-center text-gray-400 italic text-sm">No records found for the selected date.</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="tripGroupsWithTotals.length > 0">
                        <tr class="bg-[#34554a] text-white font-black text-sm">
                            <td class="border border-[#2a443b] py-3 px-2">GRAND TOTAL</td>
                            <td class="border border-[#2a443b] py-3 px-2">{{ fmt(grandTotal.cattle_count) }}</td>
                            <td class="border border-[#2a443b] py-3 px-2">{{ fmt(grandTotal.napier_total) }}</td>
                            <td class="border border-[#2a443b] py-3 px-2">{{ grandTotal.napier_kghead }}</td>
                            <td class="border border-[#2a443b] py-3 px-2">{{ fmt(grandTotal.silage_total) }}</td>
                            <td class="border border-[#2a443b] py-3 px-2">{{ grandTotal.silage_kghead }}</td>
                            <td class="border border-[#2a443b] py-3 px-2">{{ fmt(grandTotal.baller_total) }}</td>
                            <td class="border border-[#2a443b] py-3 px-2">{{ grandTotal.baller_kghead }}</td>
                            <td class="border border-[#2a443b] py-3 px-2">{{ fmt(grandTotal.receive_total) }}</td>
                            <td class="border border-[#2a443b] py-3 px-2">{{ fmt(grandTotal.planning_total) }}</td>
                            <td class="border border-[#2a443b] py-3 px-2">{{ fmt(grandTotal.actual_total) }}</td>
                            <td class="border border-[#2a443b] py-3 px-2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- ========================================================== -->
        <!-- VIEW 2: DAILY MATRIX (date range / all records) -->
        <!-- ========================================================== -->
        <div v-else class="bg-white rounded-xl shadow-lg border border-gray-300 mb-5 overflow-hidden">
            <div class="bg-gray-100 p-4 border-b border-gray-200 text-center">
                <h2 class="text-lg font-black text-gray-800 uppercase tracking-[0.15em]">Daily Feeding Record</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-center border-collapse table-fixed min-w-[1200px]">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-xs font-bold">
                            <th rowspan="2" class="w-32 border border-[#2a443b] py-4">Date</th>
                            <th v-for="type in matrixFeedTypes" :key="type" colspan="4" class="border border-[#2a443b] py-3">{{ displayOption(type) }}</th>
                        </tr>
                        <tr class="bg-[#3d6358] text-white text-[10px] font-semibold">
                            <template v-for="n in matrixFeedTypes.length" :key="n">
                                <th class="border border-[#2a443b] py-2 w-20">Carry Forward</th>
                                <th class="border border-[#2a443b] py-2 w-20">Receive</th>
                                <th class="border border-[#2a443b] py-2 w-20">Actual</th>
                                <th class="border border-[#2a443b] py-2 w-20">Balance</th>
                            </template>
                        </tr>
                    </thead>
                    <tbody class="text-xs">
                        <tr v-for="date in sortedDates" :key="date" class="hover:bg-gray-50 transition-colors">
                            <td class="border border-gray-300 py-3 font-bold bg-gray-50">{{ date }}</td>
                            <template v-for="type in matrixFeedTypes" :key="type">
                                <template v-if="props.dailyMatrix[date] && props.dailyMatrix[date][type]">
                                    <td class="border border-gray-300 py-3">{{ fmt(props.dailyMatrix[date][type].carry_forward) }}</td>
                                    <td class="border border-gray-300 py-3 text-blue-600">{{ fmt(props.dailyMatrix[date][type].receive) }}</td>
                                    <td class="border border-gray-300 py-3 font-semibold">{{ fmt(props.dailyMatrix[date][type].actual_usage) }}</td>
                                    <td class="border border-gray-300 py-3 font-black text-gray-900 bg-yellow-50/30">{{ fmt(props.dailyMatrix[date][type].balance) }}</td>
                                </template>
                                <template v-else>
                                    <td class="border border-gray-300 py-3 text-gray-300">-</td>
                                    <td class="border border-gray-300 py-3 text-gray-300">-</td>
                                    <td class="border border-gray-300 py-3 text-gray-300">-</td>
                                    <td class="border border-gray-300 py-3 text-gray-300">-</td>
                                </template>
                            </template>
                        </tr>
                        <tr v-if="sortedDates.length === 0">
                            <td :colspan="1 + matrixFeedTypes.length * 4" class="py-16 text-center text-gray-400 italic text-sm">No records found for the selected date range.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ========================================================== -->
        <!-- RAW RECORDS TABLE (editable) -->
        <!-- ========================================================== -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-300 mb-5 overflow-hidden print:hidden">
            <div class="bg-gray-100 p-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-sm font-black text-gray-800 uppercase tracking-wider">Individual Records</h2>
                <span class="text-xs text-gray-500">{{ props.pagination.total || 0 }} record(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[900px]">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-[11px] font-bold">
                            <th class="border border-[#2a443b] py-2.5 px-3">Date</th>
                            <th class="border border-[#2a443b] py-2.5 px-3">Trip</th>
                            <th class="border border-[#2a443b] py-2.5 px-3">Cattle</th>
                            <th class="border border-[#2a443b] py-2.5 px-3">Feed Type</th>
                            <th class="border border-[#2a443b] py-2.5 px-3 text-right">Planned</th>
                            <th class="border border-[#2a443b] py-2.5 px-3 text-right">Actual</th>
                            <th class="border border-[#2a443b] py-2.5 px-3 text-right">Receive</th>
                            <th class="border border-[#2a443b] py-2.5 px-3 text-right">Carry Forward</th>
                            <th class="border border-[#2a443b] py-2.5 px-3 text-right">Balance</th>
                            <th class="border border-[#2a443b] py-2.5 px-3">Remarks</th>
                            <th v-if="canEditFeeding || canDeleteFeeding" class="border border-[#2a443b] py-2.5 px-3 text-center w-24">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs">
                        <tr v-for="record in paginatedRecords" :key="record.id" class="hover:bg-blue-50/50 transition-colors">
                            <td class="border border-gray-200 py-2 px-3 font-medium">{{ record.date }}</td>
                            <td class="border border-gray-200 py-2 px-3 font-bold text-gray-800">{{ record.trip_no }}</td>
                            <td class="border border-gray-200 py-2 px-3">{{ record.cattle_count }}</td>
                            <td class="border border-gray-200 py-2 px-3">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold"
                                      :class="{
                                          'bg-green-100 text-green-700': record.feed_type === 'Napier',
                                          'bg-blue-100 text-blue-700': record.feed_type === 'OPF',
                                          'bg-amber-100 text-amber-700': record.feed_type === 'Silage',
                                          'bg-purple-100 text-purple-700': record.feed_type === 'Baller',
                                          'bg-gray-100 text-gray-700': !['Napier','OPF','Silage','Baller'].includes(record.feed_type)
                                      }">
                                    {{ displayOption(record.feed_type) }}
                                </span>
                            </td>
                            <td class="border border-gray-200 py-2 px-3 text-right">{{ fmt(record.planned) }}</td>
                            <td class="border border-gray-200 py-2 px-3 text-right font-semibold">{{ fmt(record.actual_usage) }}</td>
                            <td class="border border-gray-200 py-2 px-3 text-right text-blue-600">{{ fmt(record.receive) }}</td>
                            <td class="border border-gray-200 py-2 px-3 text-right">{{ fmt(record.carry_forward) }}</td>
                            <td class="border border-gray-200 py-2 px-3 text-right font-bold">{{ fmt(record.balance) }}</td>
                            <td class="border border-gray-200 py-2 px-3 text-gray-500">{{ record.remarks }}</td>
                            <td v-if="canEditFeeding || canDeleteFeeding" class="border border-gray-200 py-2 px-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button
                                        v-if="canEditFeeding"
                                        @click="openEditModal(record)"
                                        class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors"
                                        title="Edit"
                                    >
                                        <Edit class="w-4 h-4" />
                                    </button>
                                    <button
                                        v-if="canDeleteFeeding"
                                        @click="confirmDelete(record.id)"
                                        class="w-8 h-8 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded flex items-center justify-center transition-colors"
                                        title="Delete"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="paginatedRecords.length === 0">
                            <td :colspan="canEditFeeding || canDeleteFeeding ? 11 : 10" class="py-12 text-center text-gray-400 italic text-sm">No records found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div v-if="totalPages > 0" class="flex flex-col md:flex-row justify-between items-center p-4 border-t border-gray-100 bg-gray-50">
                <div class="flex items-center gap-2 mb-4 md:mb-0">
                    <button
                        @click="previousPage"
                        :disabled="currentPage === 1"
                        :class="currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                        class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500">
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
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors">
                        {{ page }}
                    </button>

                    <button
                        @click="nextPage"
                        :disabled="currentPage === totalPages"
                        :class="currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                        class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500">
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>

                <div class="text-sm text-gray-500">
                    Showing <span class="font-semibold text-gray-800">{{ props.pagination.from || 0 }}-{{ props.pagination.to || 0 }}</span> of <span class="font-semibold text-gray-800">{{ props.pagination.total || 0 }}</span> records
                </div>
            </div>
        </div>

        <!-- ========================================================== -->
        <!-- ADD/EDIT MODAL -->
        <!-- ========================================================== -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="closeModal"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto z-10">
                    <!-- Modal Header -->
                    <div class="sticky top-0 bg-[#34554a] text-white px-6 py-4 rounded-t-2xl flex items-center justify-between">
                        <h3 class="text-lg font-bold">{{ isEditing ? 'Edit Feeding Record' : 'Add New Feeding Record' }}</h3>
                        <button @click="closeModal" class="p-1 hover:bg-white/20 rounded-lg transition-colors">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Modal Form -->
                    <form @submit.prevent="submitForm" class="p-6 space-y-5">
                        <!-- Row 1: Date + Trip -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5 uppercase tracking-wider">Date *</label>
                                <input v-model="form.date" type="date" required @change="autoFillCattleCount"
                                    class="w-full px-3 py-2.5 rounded-lg border text-sm focus:ring-2 focus:ring-[#34554a] outline-none"
                                    :class="form.errors.date ? 'border-red-400 bg-red-50' : 'border-gray-200 bg-gray-50'" />
                                <p v-if="form.errors.date" class="text-xs text-red-500 mt-1">{{ form.errors.date }}</p>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-bold text-gray-600 tracking-wider">Trip *</label>
                                    <button
                                        v-if="canManageFeedingOptions"
                                        type="button"
                                        @click="openOptionModal('trip_no')"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#34554a] hover:underline"
                                    >
                                        <Settings class="w-3.5 h-3.5" />
                                        Trip Options
                                    </button>
                                </div>
                                <select v-model="form.trip_no" required @change="autoFillCattleCount"
                                    class="w-full px-3 py-2.5 rounded-lg border text-sm focus:ring-2 focus:ring-[#34554a] outline-none"
                                    :class="form.errors.trip_no ? 'border-red-400 bg-red-50' : 'border-gray-200 bg-gray-50'">
                                    <option value="" disabled>Select trip...</option>
                                    <option v-for="trip in tripOptions" :key="trip" :value="trip">{{ displayOption(trip) }}</option>
                                </select>
                                <p v-if="form.errors.trip_no" class="text-xs text-red-500 mt-1">{{ form.errors.trip_no }}</p>
                            </div>
                        </div>

                        <!-- Row 2: Cattle Count + Feed Type -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5 uppercase tracking-wider">Cattle Count *</label>
                                <input v-model.number="form.cattle_count" type="number" min="0" required
                                    class="w-full px-3 py-2.5 rounded-lg border text-sm focus:ring-2 focus:ring-[#34554a] outline-none"
                                    :class="form.errors.cattle_count ? 'border-red-400 bg-red-50' : 'border-gray-200 bg-gray-50'" />
                                <p v-if="form.errors.cattle_count" class="text-xs text-red-500 mt-1">{{ form.errors.cattle_count }}</p>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-bold text-gray-600 tracking-wider">Feed Type *</label>
                                    <button
                                        v-if="canManageFeedingOptions"
                                        type="button"
                                        @click="openOptionModal('feed_type')"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#34554a] hover:underline"
                                    >
                                        <Settings class="w-3.5 h-3.5" />
                                        Feed Type Options
                                    </button>
                                </div>
                                <select v-model="form.feed_type" required
                                    class="w-full px-3 py-2.5 rounded-lg border text-sm focus:ring-2 focus:ring-[#34554a] outline-none"
                                    :class="form.errors.feed_type ? 'border-red-400 bg-red-50' : 'border-gray-200 bg-gray-50'">
                                    <option v-for="ft in feedTypeOptions" :key="ft" :value="ft">{{ displayOption(ft) }}</option>
                                </select>
                                <p v-if="form.errors.feed_type" class="text-xs text-red-500 mt-1">{{ form.errors.feed_type }}</p>
                            </div>
                        </div>

                        <!-- Row 3: Planned + Actual -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5 uppercase tracking-wider">Planned (kg)</label>
                                <input v-model.number="form.planned" type="number" min="0" step="0.01"
                                    class="w-full px-3 py-2.5 rounded-lg border border-gray-200 bg-gray-50 text-sm focus:ring-2 focus:ring-[#34554a] outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5 tracking-wider">Actual (kg)</label>
                                <input v-model.number="form.actual_usage" type="number" min="0" step="0.01"
                                    class="w-full px-3 py-2.5 rounded-lg border border-gray-200 bg-gray-50 text-sm focus:ring-2 focus:ring-[#34554a] outline-none" />
                            </div>
                        </div>

                        <!-- Row 4: Receive + Carry Forward + Balance -->
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5 uppercase tracking-wider">Receive (kg)</label>
                                <input v-model.number="form.receive" type="number" min="0" step="0.01"
                                    class="w-full px-3 py-2.5 rounded-lg border border-gray-200 bg-gray-50 text-sm focus:ring-2 focus:ring-[#34554a] outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5 tracking-wider">Carry Forward (kg)</label>
                                <input v-model.number="form.carry_forward" type="number" min="0" step="0.01" disabled
                                    class="w-full px-3 py-2.5 rounded-lg border border-gray-200 bg-gray-100 text-sm text-gray-500 outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5 tracking-wider">Balance (kg)</label>
                                <input :value="previewBalance" type="number" disabled
                                    class="w-full px-3 py-2.5 rounded-lg border border-gray-200 bg-gray-100 text-sm text-gray-500 outline-none" />
                            </div>
                        </div>

                        <!-- Row 5: Remarks -->
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1.5 uppercase tracking-wider">Remarks</label>
                            <input v-model="form.remarks" type="text" placeholder="e.g. EPKC/FL1, FL1/WEAN"
                                class="w-full px-3 py-2.5 rounded-lg border border-gray-200 bg-gray-50 text-sm focus:ring-2 focus:ring-[#34554a] outline-none" />
                        </div>

                        <!-- Submit -->
                        <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                            <button type="button" @click="closeModal" class="px-5 py-2.5 rounded-lg border border-gray-200 text-gray-600 font-medium text-sm hover:bg-gray-50 transition-all">
                                Cancel
                            </button>
                            <button type="submit" :disabled="form.processing"
                                class="px-6 py-2.5 rounded-lg bg-[#34554a] text-white font-bold text-sm hover:bg-opacity-90 transition-all shadow-md disabled:opacity-50">
                                {{ form.processing ? 'Saving...' : (isEditing ? 'Update Record' : 'Save Record') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- OPTION MANAGER MODAL -->
        <Teleport to="body">
            <div v-if="showOptionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showOptionModal = false; cancelOptionEdit()"></div>
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md z-10 overflow-hidden">
                    <div class="bg-[#34554a] text-white px-5 py-4 flex items-center justify-between">
                        <h3 class="text-base font-bold">{{ optionModalTitle }}</h3>
                        <button @click="showOptionModal = false; cancelOptionEdit()" class="p-1 hover:bg-white/20 rounded-lg transition-colors">
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                    <div class="p-5 space-y-4">
                        <div v-if="canEditFeeding" class="rounded-lg border border-gray-200 bg-gray-50 p-3 space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-bold uppercase tracking-wider text-gray-600">
                                    {{ editingOptionId ? 'Edit option' : 'Add option' }}
                                </p>
                                <button v-if="editingOptionId" type="button" @click="cancelOptionEdit" class="text-xs text-gray-500 underline">
                                    Cancel edit
                                </button>
                            </div>
                            <div class="flex gap-2">
                                <input
                                    v-if="editingOptionId"
                                    v-model="editingOptionValue"
                                    @keyup.enter="saveOptionEdit"
                                    type="text"
                                    placeholder="Edit option..."
                                    class="flex-1 px-3 py-2.5 rounded-lg border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-[#34554a] outline-none"
                                />
                                <input
                                    v-else
                                    v-model="newOptionValue"
                                    @keyup.enter="addOption"
                                    type="text"
                                    placeholder="Add option..."
                                    class="flex-1 px-3 py-2.5 rounded-lg border border-gray-200 bg-white text-sm focus:ring-2 focus:ring-[#34554a] outline-none"
                                />
                                <button
                                    v-if="editingOptionId"
                                    type="button"
                                    @click="saveOptionEdit"
                                    class="px-4 py-2.5 rounded-lg bg-[#34554a] text-white font-bold text-sm hover:bg-opacity-90"
                                >
                                    Save
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    @click="addOption"
                                    class="px-4 py-2.5 rounded-lg bg-[#34554a] text-white font-bold text-sm hover:bg-opacity-90"
                                >
                                    Add
                                </button>
                            </div>
                            <p v-if="optionErrors.value" class="text-xs text-red-500">{{ optionErrors.value }}</p>
                            <p v-if="optionErrors.field_type" class="text-xs text-red-500">{{ optionErrors.field_type }}</p>
                        </div>
<div class="border border-gray-200 rounded-lg divide-y max-h-72 overflow-y-auto bg-white">
                            <div v-if="currentManagedOptions.length === 0" class="p-4 text-center text-sm text-gray-400">
                                No options yet.
                            </div>
                            <div v-for="option in paginatedOptions" :key="option.id" class="flex items-center justify-between gap-3 p-3">
                                <div class="min-w-0 flex-1">
                                    <span class="text-sm font-medium text-gray-800">{{ displayOption(option.value) }}</span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button v-if="canEditFeeding && typeof option.id === 'number'" type="button" @click="startEditOption(option)" class="p-1.5 rounded-md text-blue-600 hover:bg-blue-50" title="Edit">
                                        <Pencil class="w-4 h-4" />
                                    </button>
                                    <button v-if="canDeleteFeeding && typeof option.id === 'number'" type="button" @click="deleteOption(option)" class="p-1.5 rounded-md text-red-500 hover:bg-red-50" title="Delete">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-if="totalOptionPages > 1" class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                            <span class="text-xs text-gray-500">Page {{ optionPage }} of {{ totalOptionPages }}</span>
                            <div class="flex gap-1">
                                <button type="button" @click="optionPage--" :disabled="optionPage <= 1" class="px-2 py-1 text-xs rounded border border-gray-200 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">Prev</button>
                                <button type="button" @click="optionPage++" :disabled="optionPage >= totalOptionPages" class="px-2 py-1 text-xs rounded border border-gray-200 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">Next</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- DELETE CONFIRMATION MODAL -->
        <Teleport to="body">
            <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showDeleteConfirm = false"></div>
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm z-10 p-6 text-center">
                    <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <Trash2 class="w-7 h-7 text-red-500" />
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Delete Record?</h3>
                    <p class="text-sm text-gray-500 mb-6">This action cannot be undone. The record will be permanently removed.</p>
                    <div class="flex justify-center gap-3">
                        <button @click="showDeleteConfirm = false" class="px-5 py-2.5 rounded-lg border border-gray-200 text-gray-600 font-medium text-sm hover:bg-gray-50">
                            Cancel
                        </button>
                        <button @click="deleteRecord" class="px-5 py-2.5 rounded-lg bg-red-600 text-white font-bold text-sm hover:bg-red-700 transition-all">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
@media print {
    .print\:hidden { display: none !important; }
    .print\:mb-4 { margin-bottom: 1rem !important; }
}
th { font-weight: 800; }
</style>

