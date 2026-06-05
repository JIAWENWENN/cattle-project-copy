<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import {
    Package, Search, CheckCircle2, AlertCircle, AlertTriangle,
    History, Pencil, X, FileDown, Settings2, Trash2,
    ChevronLeft, ChevronRight, Eye,
    Truck, Clock, Pill, ExternalLink, Plus
} from 'lucide-vue-next';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';

const props = defineProps({
    stocks: Object,
    filters: Object,
    suppliers: Array,
    categories: Array,
    stats: Object,
});

// --- State Management ---
const search = ref(props.filters?.search || '');
const categoryFilter = ref(props.filters?.category || '');
const sourceFilter = ref(props.filters?.source || '');
const statusFilter = ref(props.filters?.status || '');

// Modals
const showEditModal = ref(false);
const showViewModal = ref(false);
const showHistoryModal = ref(false);
const showSupplierModal = ref(false);
const showAddSupplierModal = ref(false);
const selectedItem = ref(null);
const selectedSupplier = ref(null);

// Bulk Actions
const bulkMode = ref(false);
const selectedIds = ref([]);
const showBulkSupplierModal = ref(false);

// Supplier Management State
const isEditingSupplier = ref(false);
const showDeleteSupplierModal = ref(false);
const supplierToDelete = ref(null);

// Sorting State
const sortKey = ref(props.filters?.sort || 'created_at');
const sortDir = ref(props.filters?.direction || 'desc');

// --- Computed ---
const allSelected = computed(() => {
    if (!props.stocks?.data?.length) return false;
    return selectedIds.value.length === props.stocks.data.length;
});

// --- Search & Filter Watchers ---
let searchTimeout;
watch([search, categoryFilter, sourceFilter, statusFilter], ([s, c, src, st]) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/inventory/stock',
            { search: s, category: c, source: src, status: st, sort: sortKey.value, direction: sortDir.value },
            { preserveState: true, replace: true }
        );
    }, 500);
});

// --- Form Setup ---
const form = useForm({
    id: null,
    supplier_id: null,
    min_threshold: 50,
    safety_stock: 20,
    oso_avg: 0,
    lead_time: 5,
    remark: '',
});

const supplierForm = useForm({
    id: null,
    name: '',
    type: 'Distributor',
    contact: '',
    phone: '',
    email: '',
    address: '',
});

const bulkSupplierForm = useForm({
    ids: [],
    supplier_id: null,
});

// --- Modal Handlers ---
const openEdit = (item) => {
    selectedItem.value = item;
    form.id = item.id;
    form.supplier_id = item.supplier_id;
    form.min_threshold = item.min_threshold;
    form.safety_stock = item.safety_stock;
    form.oso_avg = item.oso_avg;
    form.lead_time = item.lead_time;
    form.remark = item.remark || '';
    showEditModal.value = true;
};

const openView = (item) => {
    selectedItem.value = item;
    showViewModal.value = true;
};

const openHistory = (item) => {
    selectedItem.value = item;
    showHistoryModal.value = true;
};

const openSupplierDetails = (supplier) => {
    selectedSupplier.value = supplier;
    showSupplierModal.value = true;
};

const submitForm = () => {
    form.put(`/inventory/stock/${form.id}`, {
        onSuccess: () => showEditModal.value = false
    });
};

const openAddSupplier = () => {
    isEditingSupplier.value = false;
    supplierForm.reset();
    supplierForm.id = null;
    showAddSupplierModal.value = true;
};

const openEditSupplier = (supplier) => {
    isEditingSupplier.value = true;
    supplierForm.id = supplier.id;
    supplierForm.name = supplier.name;
    supplierForm.type = supplier.type || 'Distributor';
    supplierForm.contact = supplier.contact || '';
    supplierForm.phone = supplier.phone || '';
    supplierForm.email = supplier.email || '';
    supplierForm.address = supplier.address || '';
    showSupplierModal.value = false;
    showAddSupplierModal.value = true;
};

const saveSupplier = () => {
    if (isEditingSupplier.value && supplierForm.id) {
        supplierForm.put(`/suppliers/${supplierForm.id}`, {
            onSuccess: () => {
                showAddSupplierModal.value = false;
                supplierForm.reset();
                isEditingSupplier.value = false;
            }
        });
    } else {
        supplierForm.post('/suppliers', {
            onSuccess: () => {
                showAddSupplierModal.value = false;
                supplierForm.reset();
            }
        });
    }
};

const openDeleteSupplier = (supplier) => {
    supplierToDelete.value = supplier;
    showSupplierModal.value = false;
    showDeleteSupplierModal.value = true;
};

const executeDeleteSupplier = () => {
    router.delete(`/suppliers/${supplierToDelete.value.id}`, {
        onSuccess: () => {
            showDeleteSupplierModal.value = false;
            supplierToDelete.value = null;
        }
    });
};

// --- Bulk Actions ---
const toggleBulkMode = () => {
    bulkMode.value = !bulkMode.value;
    if (!bulkMode.value) {
        selectedIds.value = [];
    }
};

const toggleSelectAll = () => {
    if (allSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.stocks.data.map(i => i.id);
    }
};

const toggleRowSelect = (id) => {
    const index = selectedIds.value.indexOf(id);
    if (index === -1) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value.splice(index, 1);
    }
};

const openBulkSupplierModal = () => {
    bulkSupplierForm.ids = [...selectedIds.value];
    showBulkSupplierModal.value = true;
};

const submitBulkSupplier = () => {
    bulkSupplierForm.post('/inventory/stock/bulk-supplier', {
        onSuccess: () => {
            showBulkSupplierModal.value = false;
            selectedIds.value = [];
            bulkMode.value = false;
        }
    });
};

// --- Helpers ---
const handleSort = (key) => {
    sortDir.value = (sortKey.value === key && sortDir.value === 'asc') ? 'desc' : 'asc';
    sortKey.value = key;
    router.get('/inventory/stock',
        { search: search.value, category: categoryFilter.value, source: sourceFilter.value, status: statusFilter.value, sort: key, direction: sortDir.value },
        { preserveState: true }
    );
};

const getStatus = (item) => {
    if (item.current_stock <= 0) return { label: 'Out of Stock', class: 'bg-gray-100 text-gray-700 border-gray-200' };
    if (item.current_stock < item.min_threshold) return { label: 'Low Stock', class: 'bg-gray-100 text-gray-700 border-gray-200' };
    return { label: 'In Stock', class: 'bg-gray-100 text-gray-700 border-gray-200' };
};

const getDayCoverClass = (item) => {
    if (item.day_cover <= 0) return 'text-red-600';
    if (item.day_cover < item.lead_time) return 'text-red-600';
    return 'text-green-600';
};

const getExpiryStatus = (item) => {
    if (!item.expiry_date) return { label: 'N/A', class: 'bg-gray-100 text-gray-700 border-gray-200', icon: null };
    const today = new Date();
    const expiry = new Date(item.expiry_date);
    const diffDays = Math.ceil((expiry - today) / (1000 * 60 * 60 * 24));

    if (diffDays <= 0) return { label: 'Expired', class: 'bg-gray-100 text-gray-700 border-gray-200', icon: 'warning' };
    if (diffDays <= 15) return { label: `${diffDays}d`, class: 'bg-gray-100 text-gray-700 border-gray-200', icon: 'warning' };
    if (diffDays <= 45) return { label: `${diffDays}d`, class: 'bg-gray-100 text-gray-700 border-gray-200', icon: 'schedule' };
    return { label: 'OK', class: 'bg-gray-100 text-gray-700 border-gray-200', icon: 'check' };
};

const getSourceLink = () => '/medications';

const goToPage = (page) => {
    router.get('/inventory/stock',
        { page, search: search.value, category: categoryFilter.value, source: sourceFilter.value, status: statusFilter.value, sort: sortKey.value, direction: sortDir.value },
        { preserveState: true }
    );
};

const exportData = () => {
    window.location.href = '/inventory/stock/export';
};

const clearFilters = () => {
    search.value = '';
    categoryFilter.value = '';
    sourceFilter.value = '';
    statusFilter.value = '';
};
</script>

<template>
    <Head title="Stock Alert Dashboard" />
    <AppLayout title="Stock Alert" parent="Inventory">

        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Stock Alert Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">Medication inventory with threshold management.</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                    <Package class="w-7 h-7" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase">Total Items</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.total }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 rounded-full bg-green-50 text-green-600 flex items-center justify-center">
                    <CheckCircle2 class="w-7 h-7" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase">In Stock</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.in_stock }}</p>
                </div>
            </div>
            <div
                class="bg-white p-6 rounded-xl border flex items-center gap-5 shadow-sm cursor-pointer hover:bg-red-50 transition-colors"
                :class="statusFilter === 'critical' ? 'border-red-300 bg-red-50' : 'border-red-100'"
                @click="statusFilter = statusFilter === 'critical' ? '' : 'critical'"
            >
                <div class="w-14 h-14 rounded-full bg-red-50 text-red-600 flex items-center justify-center">
                    <AlertCircle class="w-7 h-7" />
                </div>
                <div>
                    <p class="text-xs text-red-600 font-bold uppercase">Critical Stock</p>
                    <p class="text-3xl font-black text-red-600">{{ stats.critical }}</p>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4 bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex flex-1 gap-2 w-full md:w-auto flex-wrap">
                <div class="relative flex-1 min-w-[200px]">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search item or supplier..."
                        class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                    />
                </div>
                <select
                    v-model="categoryFilter"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-600 text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Categories</option>
                    <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                </select>
                <select
                    v-model="statusFilter"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-600 text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Status</option>
                    <option value="ok">In Stock</option>
                    <option value="critical">Critical / Low</option>
                </select>
                <button
                    v-if="search || categoryFilter || sourceFilter || statusFilter"
                    @click="clearFilters"
                    class="px-3 py-2 text-gray-500 hover:text-gray-700 text-sm font-medium"
                >
                    Clear
                </button>
            </div>
            <div class="flex gap-2">
                <button
                    v-if="!bulkMode"
                    @click="toggleBulkMode"
                    class="flex items-center gap-2 bg-white border border-gray-200 px-4 py-2 rounded-lg hover:bg-gray-50 text-sm shadow-sm transition-colors"
                >
                    <Settings2 class="w-4 h-4" />
                    Bulk Assign
                </button>
                <button
                    @click="exportData"
                    class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors"
                >
                    <FileDown class="w-4 h-4" />
                    Export
                </button>
                <button
                    @click="openAddSupplier"
                    class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg font-medium hover:bg-opacity-90 text-sm shadow-sm"
                >
                    <Plus class="w-4 h-4" />
                    Add Supplier
                </button>
            </div>
        </div>

        <!-- Bulk Action Bar -->
        <div
            v-if="bulkMode"
            class="bg-blue-50 border border-blue-100 px-4 py-3 rounded-xl flex items-center gap-4 mb-6"
        >
            <span class="text-blue-600 text-sm font-bold">{{ selectedIds.length }} items selected</span>
            <div class="w-px h-4 bg-blue-200"></div>
            <button
                @click="openBulkSupplierModal"
                :disabled="selectedIds.length === 0"
                class="flex items-center gap-1.5 text-blue-600 hover:text-blue-700 text-sm font-bold disabled:opacity-50"
            >
                <Truck class="w-4 h-4" />
                Assign Supplier
            </button>
            <button
                @click="toggleBulkMode"
                class="text-gray-500 hover:text-gray-800 text-sm font-medium ml-auto"
            >
                Cancel
            </button>
        </div>

        <!-- Inventory Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-sm">
                            <th v-if="bulkMode" class="p-4 w-10">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    @change="toggleSelectAll"
                                    class="w-4 h-4 rounded border-white/30 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                />
                            </th>
                            <th class="p-4 font-semibold">
                                <div class="flex items-center gap-2">
                                    Item Details
                                    <span class="text-xs opacity-60">(from Medication)</span>
                                </div>
                            </th>
                            <th class="p-4 font-semibold">Source</th>
                            <th class="p-4 font-semibold">Category</th>
                            <th class="p-4 font-semibold">Supplier</th>
                            <th class="p-4 font-semibold text-right">Stock</th>
                            <th class="p-4 font-semibold text-center" title="Min Threshold">Threshold</th>
                            <th class="p-4 font-semibold text-center" title="Order Point Average">OSO</th>
                            <th class="p-4 font-semibold text-center">Day Cover</th>
                            <th class="p-4 font-semibold text-center">Lead Time</th>
                            <th class="p-4 font-semibold text-center">Expiry</th>
                            <th class="p-4 font-semibold text-center">Status</th>
                            <th class="p-4 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-sm text-gray-700">
                        <tr
                            v-for="item in stocks.data"
                            :key="item.id"
                            class="hover:bg-gray-50 transition-colors"
                        >
                            <td v-if="bulkMode" class="p-4">
                                <input
                                    type="checkbox"
                                    :checked="selectedIds.includes(item.id)"
                                    @change="toggleRowSelect(item.id)"
                                    class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                />
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center border border-gray-200 bg-gray-50 text-gray-600">
                                        <Pill class="w-5 h-5" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-900">{{ item.name }}</span>
                                        <span class="text-xs text-gray-400">{{ item.sku }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4">
                                <Link
                                    :href="getSourceLink(item)"
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-bold bg-gray-100 text-gray-700"
                                >
                                    {{ item.source_type }}
                                    <ExternalLink class="w-3 h-3" />
                                </Link>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded bg-gray-100 text-gray-600 text-xs font-bold">
                                    {{ item.category || '-' }}
                                </span>
                            </td>
                            <td class="p-4">
                                <button
                                    v-if="item.supplier"
                                    @click="openSupplierDetails(item.supplier)"
                                    class="flex items-center gap-1.5 text-[#34554a] hover:text-[#2a443b] font-semibold transition-all"
                                >
                                    <span class="underline decoration-[#34554a]/30 underline-offset-4">
                                        {{ item.supplier.name }}
                                    </span>
                                    <Truck class="w-4 h-4" />
                                </button>
                                <span v-else class="text-gray-400 italic">Not assigned</span>
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex flex-col items-end">
                                    <span class="font-bold text-base text-gray-900">
                                        {{ item.current_stock }}
                                    </span>
                                    <span class="text-[10px] font-bold text-gray-400 uppercase">{{ item.unit }}</span>
                                </div>
                            </td>
                            <td class="p-4 text-center font-medium">{{ item.min_threshold }}</td>
                            <td class="p-4 text-center font-medium">{{ item.oso_avg?.toFixed(1) || '0.0' }}</td>
                            <td class="p-4 text-center font-medium text-gray-700">
                                {{ item.day_cover }}
                            </td>
                            <td class="p-4 text-center font-medium">
                                {{ item.lead_time }} <span class="text-xs text-gray-400">days</span>
                            </td>
                            <td class="p-4 text-center">
                                <span
                                    :class="getExpiryStatus(item).class"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold border"
                                >
                                    {{ getExpiryStatus(item).label }}
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <span
                                    :class="getStatus(item).class"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                                >
                                    {{ getStatus(item).label }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        @click="openView(item)"
                                        class="w-8 h-8 text-gray-400 hover:text-[#34554a] hover:bg-[#34554a]/10 rounded flex items-center justify-center transition-colors"
                                        title="View"
                                    >
                                        <Eye class="w-4 h-4" />
                                    </button>
                                    <button
                                        @click="openHistory(item)"
                                        class="w-8 h-8 text-gray-400 hover:text-[#34554a] hover:bg-[#34554a]/10 rounded flex items-center justify-center transition-colors"
                                        title="History"
                                    >
                                        <History class="w-4 h-4" />
                                    </button>
                                    <button
                                        @click="openEdit(item)"
                                        class="w-8 h-8 text-gray-400 hover:text-[#34554a] hover:bg-[#34554a]/10 rounded flex items-center justify-center transition-colors"
                                        title="Edit Settings"
                                    >
                                        <Pencil class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!stocks.data?.length">
                            <td :colspan="bulkMode ? 14 : 13" class="p-8 text-center text-gray-500 italic">
                                No stock records found. Add items in the Medication manager first.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t bg-gray-50 flex items-center justify-between">
                <p class="text-sm text-gray-500 font-medium">
                    Showing {{ stocks.from || 0 }} to {{ stocks.to || 0 }} of {{ stocks.total }} records
                </p>
                <div class="flex gap-2">
                    <button
                        @click="goToPage(stocks.current_page - 1)"
                        :disabled="!stocks.prev_page_url"
                        class="w-8 h-8 flex items-center justify-center border rounded-lg bg-white disabled:opacity-50 transition-all hover:bg-gray-50"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                    <button
                        @click="goToPage(stocks.current_page + 1)"
                        :disabled="!stocks.next_page_url"
                        class="w-8 h-8 flex items-center justify-center border rounded-lg bg-white disabled:opacity-50 transition-all hover:bg-gray-50"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: View Details -->
        <Modal :show="showViewModal" title="Stock Details" @close="showViewModal = false">
            <div class="p-6 space-y-5">
                <div class="flex items-center gap-4 mb-2">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center shadow-inner bg-blue-50 text-blue-600">
                        <Pill class="w-8 h-8" />
                    </div>
                    <div>
                        <h4 class="text-xl font-bold text-gray-900 tracking-tight">{{ selectedItem?.name }}</h4>
                        <p class="text-sm text-gray-500 font-medium">{{ selectedItem?.sku }}</p>
                        <div class="flex gap-2 mt-1">
                            <span class="text-xs font-bold bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ selectedItem?.category }}</span>
                            <span class="text-xs font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-700">
                                {{ selectedItem?.source_type }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 flex justify-between border-b pb-2 border-gray-100">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Supplier</span>
                        <span class="text-sm font-semibold text-gray-900">{{ selectedItem?.supplier?.name || 'Not Assigned' }}</span>
                    </div>
                    <div class="flex justify-between border-b pb-2 border-gray-100">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Current Stock</span>
                        <span class="text-sm font-semibold text-gray-900">{{ selectedItem?.current_stock }} {{ selectedItem?.unit }}</span>
                    </div>
                    <div class="flex justify-between border-b pb-2 border-gray-100">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Status</span>
                        <span v-if="selectedItem" :class="getStatus(selectedItem).class" class="px-2 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wider">
                            {{ getStatus(selectedItem).label }}
                        </span>
                    </div>
                    <div class="flex justify-between border-b pb-2 border-gray-100">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Min Threshold</span>
                        <span class="text-sm font-semibold text-gray-900">{{ selectedItem?.min_threshold }}</span>
                    </div>
                    <div class="flex justify-between border-b pb-2 border-gray-100">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Safety Stock</span>
                        <span class="text-sm font-semibold text-gray-900">{{ selectedItem?.safety_stock }}</span>
                    </div>
                    <div class="flex justify-between border-b pb-2 border-gray-100">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">OSO Avg Use</span>
                        <span class="text-sm font-semibold text-gray-900">{{ selectedItem?.oso_avg?.toFixed(1) }}</span>
                    </div>
                    <div class="flex justify-between border-b pb-2 border-gray-100">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Day Cover</span>
                        <span class="text-sm font-semibold text-gray-900">{{ selectedItem?.day_cover }} Days</span>
                    </div>
                    <div class="flex justify-between border-b pb-2 border-gray-100">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Lead Time</span>
                        <span class="text-sm font-semibold text-gray-900">{{ selectedItem?.lead_time }} Days</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Expiry Date</span>
                        <span class="text-sm font-bold" :class="selectedItem?.expiry_date ? 'text-gray-900' : 'text-gray-400'">
                            {{ selectedItem?.expiry_date || 'N/A' }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-gray-50 flex justify-end border-t">
                <button @click="showViewModal = false" class="px-6 py-2 border bg-white rounded-lg text-sm font-bold shadow-sm hover:bg-gray-50 transition-all text-gray-700">
                    Close
                </button>
            </div>
        </Modal>

        <!-- MODAL: Edit Settings -->
        <Modal :show="showEditModal" title="Edit Stock Settings" @close="showEditModal = false">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center border border-gray-200 bg-blue-50 text-blue-600">
                        <Pill class="w-5 h-5" />
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-900">{{ selectedItem?.name }}</h4>
                        <p class="text-xs text-gray-500">Current Stock: {{ selectedItem?.current_stock }} {{ selectedItem?.unit }}</p>
                    </div>
                </div>

                <form @submit.prevent="submitForm" class="space-y-5">
                    <div class="space-y-1">
                        <label class="block text-sm font-bold text-gray-700">Supplier</label>
                        <select
                            v-model="form.supplier_id"
                            class="w-full p-2 border rounded-lg bg-white focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm"
                        >
                            <option :value="null">-- Select Supplier --</option>
                            <option v-for="supp in suppliers" :key="supp.id" :value="supp.id">{{ supp.name }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-sm font-bold text-gray-700">Min Threshold</label>
                            <input
                                v-model="form.min_threshold"
                                type="number"
                                class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-bold text-gray-700">Safety Stock</label>
                            <input
                                v-model="form.safety_stock"
                                type="number"
                                class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-bold text-gray-700">OSO Avg Use</label>
                            <input
                                v-model="form.oso_avg"
                                type="number"
                                step="0.1"
                                class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-bold text-gray-700">Lead Time (Days)</label>
                            <input
                                v-model="form.lead_time"
                                type="number"
                                class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm"
                            />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-sm font-bold text-gray-700">Remark</label>
                        <textarea
                            v-model="form.remark"
                            rows="2"
                            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm"
                            placeholder="Optional notes..."
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-5 border-t border-gray-100">
                        <button
                            type="button"
                            @click="showEditModal = false"
                            class="px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-50 transition-all font-medium"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2 bg-[#34554a] text-white rounded-lg hover:bg-opacity-90 shadow-md font-bold transition-all disabled:opacity-50"
                        >
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL: Activity History -->
        <Modal :show="showHistoryModal" title="Activity History" @close="showHistoryModal = false">
            <div class="p-2 border-b border-gray-100 bg-gray-50">
                <p class="text-xs text-gray-500 uppercase font-bold tracking-widest px-4">{{ selectedItem?.name }}</p>
            </div>
            <div class="p-8 overflow-y-auto custom-scrollbar bg-white max-h-[60vh]">
                <div class="space-y-8 relative border-l-2 border-gray-100 ml-3">
                    <div v-for="(h, idx) in selectedItem?.history" :key="h.id" class="relative pl-7">
                        <div
                            class="absolute -left-[10px] top-1 w-4 h-4 rounded-full border-4 border-white shadow-sm"
                            :class="idx === 0 ? 'bg-[#34554a]' : 'bg-gray-200'"
                        ></div>
                        <p class="text-[10px] text-gray-400 font-mono mb-1 font-bold">{{ new Date(h.created_at).toLocaleString() }}</p>
                        <p class="font-bold text-sm text-gray-800">{{ h.action }} by {{ h.user }}</p>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed font-medium">{{ h.detail }}</p>
                    </div>
                    <div v-if="!selectedItem?.history?.length" class="text-center py-6 text-gray-400 italic text-sm font-medium">
                        No activity records found.
                    </div>
                </div>
            </div>
        </Modal>

        <!-- MODAL: Supplier Details -->
        <Modal :show="showSupplierModal" title="Supplier Profile" @close="showSupplierModal = false">
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-16 h-16 bg-[#34554a]/10 text-[#34554a] rounded-full flex items-center justify-center shadow-inner">
                        <Truck class="w-8 h-8" />
                    </div>
                    <div>
                        <h4 class="text-xl font-bold text-gray-900 tracking-tight">{{ selectedSupplier?.name }}</h4>
                        <span class="text-[10px] font-bold tracking-widest uppercase text-[#34554a] bg-[#34554a]/10 px-2 py-1 rounded">
                            {{ selectedSupplier?.type || 'Distributor' }}
                        </span>
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="flex justify-between border-b pb-2 border-gray-100">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Contact</span>
                        <span class="text-sm font-semibold text-gray-900">{{ selectedSupplier?.contact || '-' }}</span>
                    </div>
                    <div class="flex justify-between border-b pb-2 border-gray-100">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Phone</span>
                        <span class="text-sm font-semibold text-gray-900">{{ selectedSupplier?.phone || '-' }}</span>
                    </div>
                    <div class="flex justify-between border-b pb-2 border-gray-100">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Email</span>
                        <span class="text-sm font-semibold text-gray-900">{{ selectedSupplier?.email || '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Address</span>
                        <span class="text-sm font-semibold text-gray-900 text-right max-w-[200px]">{{ selectedSupplier?.address || '-' }}</span>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-gray-50 flex justify-between border-t">
                <div class="flex gap-2">
                    <button
                        @click="openEditSupplier(selectedSupplier)"
                        class="flex items-center gap-1.5 px-4 py-2 border bg-white rounded-lg text-sm font-bold shadow-sm hover:bg-gray-50 transition-all text-gray-700"
                    >
                        <Pencil class="w-4 h-4" />
                        Edit
                    </button>
                    <button
                        @click="openDeleteSupplier(selectedSupplier)"
                        class="flex items-center gap-1.5 px-4 py-2 border border-red-200 bg-white rounded-lg text-sm font-bold shadow-sm hover:bg-red-50 transition-all text-red-600"
                    >
                        <Trash2 class="w-4 h-4" />
                        Delete
                    </button>
                </div>
                <button @click="showSupplierModal = false" class="px-6 py-2 border bg-white rounded-lg text-sm font-bold shadow-sm hover:bg-gray-50 transition-all text-gray-700">
                    Close
                </button>
            </div>
        </Modal>

        <!-- MODAL: Add/Edit Supplier -->
        <Modal :show="showAddSupplierModal" :title="isEditingSupplier ? 'Edit Supplier' : 'Add New Supplier'" @close="showAddSupplierModal = false">
            <div class="p-6">
                <form @submit.prevent="saveSupplier" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 space-y-1">
                            <label class="block text-sm font-bold text-gray-700">Company Name *</label>
                            <input
                                v-model="supplierForm.name"
                                class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm"
                                placeholder="Legal corporate name"
                                required
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-bold text-gray-700">Type</label>
                            <select v-model="supplierForm.type" class="w-full p-2 border rounded-lg bg-white focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm">
                                <option>Distributor</option>
                                <option>Manufacturer</option>
                                <option>Wholesaler</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-bold text-gray-700">Contact Person</label>
                            <input v-model="supplierForm.contact" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm" placeholder="Name" />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-bold text-gray-700">Phone</label>
                            <input v-model="supplierForm.phone" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm" placeholder="+60 12-345 6789" />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-sm font-bold text-gray-700">Email</label>
                            <input v-model="supplierForm.email" type="email" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm" placeholder="email@company.com" />
                        </div>
                        <div class="col-span-2 space-y-1">
                            <label class="block text-sm font-bold text-gray-700">Address</label>
                            <textarea v-model="supplierForm.address" rows="2" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm" placeholder="Full address"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-5 border-t border-gray-100">
                        <button type="button" @click="showAddSupplierModal = false" class="px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-50 transition-all font-medium">Cancel</button>
                        <button type="submit" :disabled="supplierForm.processing" class="px-6 py-2 bg-[#34554a] text-white rounded-lg hover:bg-opacity-90 shadow-md font-bold transition-all disabled:opacity-50">
                            {{ isEditingSupplier ? 'Update Supplier' : 'Add Supplier' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL: Bulk Assign Supplier -->
        <Modal :show="showBulkSupplierModal" title="Assign Supplier to Selected Items" @close="showBulkSupplierModal = false">
            <div class="p-6">
                <p class="text-sm text-gray-600 mb-4">Assign a supplier to <strong>{{ selectedIds.length }}</strong> selected items.</p>
                <div class="space-y-1">
                    <label class="block text-sm font-bold text-gray-700">Select Supplier</label>
                    <select v-model="bulkSupplierForm.supplier_id" class="w-full p-2 border rounded-lg bg-white focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm">
                        <option :value="null">-- Remove Supplier --</option>
                        <option v-for="supp in suppliers" :key="supp.id" :value="supp.id">{{ supp.name }}</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-5 mt-5 border-t border-gray-100">
                    <button type="button" @click="showBulkSupplierModal = false" class="px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-50 transition-all font-medium">Cancel</button>
                    <button @click="submitBulkSupplier" :disabled="bulkSupplierForm.processing" class="px-6 py-2 bg-[#34554a] text-white rounded-lg hover:bg-opacity-90 shadow-md font-bold transition-all disabled:opacity-50">Apply</button>
                </div>
            </div>
        </Modal>

        <!-- Delete Supplier Confirmation Modal -->
        <DeleteConfirmationModal
            :show="showDeleteSupplierModal"
            :item-name="supplierToDelete?.name"
            @close="showDeleteSupplierModal = false"
            @confirm="executeDeleteSupplier"
        />

    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>
