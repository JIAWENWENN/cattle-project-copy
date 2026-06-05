<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import {
    Pill, Search, Plus, Package, CheckCircle2, AlertCircle,
    CalendarClock, History, Pencil, Trash2, X, Settings2,
    ChevronLeft, ChevronRight, Eye, GripVertical, ChevronUp,
    ChevronDown, ChevronsUpDown
} from 'lucide-vue-next';

const page = usePage();

const props = defineProps({
    medications: Object,
    filters: Object,
    savedColumns: Array,
    dynamicCategories: Array
});

const inventoryPermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Inventory Medication Stock'];
    return Array.isArray(perms) ? perms : ['no-access'];
});
const hasInventoryPermission = (action) => {
    if (String(page.props.auth?.user?.role || '').toLowerCase() === 'admin') return true;
    const perms = inventoryPermissions.value;
    return perms.includes('full') || perms.includes(action);
};
const canCreateInventory = computed(() => hasInventoryPermission('create'));
const canEditInventory = computed(() => hasInventoryPermission('edit'));
const canDeleteInventory = computed(() => hasInventoryPermission('delete'));

// --- State Management ---
const search = ref(props.filters?.search || '');
const categoryFilter = ref(props.filters?.category || '');
const showModal = ref(false);
const showColumnModal = ref(false);
const showHistoryModal = ref(false);
const showViewModal = ref(false);
const isEditing = ref(false);
const selectedItem = ref(null);

// Delete/Drag State
const showDeleteModal = ref(false);
const itemToDelete = ref(null);
const managerDragIndex = ref(null);
const bulkMode = ref(false);
const selectedIds = ref([]);
const showBulkDeleteModal = ref(false);

// Sorting State
const sortKey = ref(props.filters?.sort || 'created_at');
const sortDir = ref(props.filters?.direction || 'desc');

// --- 1. COLUMNS CONFIGURATION ---
const defaultColumns = [
    { key: 'name', label: 'Medication', type: 'text', visible: true, standard: true },
    { key: 'generic', label: 'Generic Name', type: 'text', visible: true, standard: true },
    { key: 'category', label: 'Category', type: 'select', options: ['Antibiotics', 'Vaccine', 'Vitamin', 'Supplement'], visible: true, standard: true, editableOptions: true },
    { key: 'stock', label: 'Stock', type: 'number', visible: true, standard: true },
    { key: 'expiry_date', label: 'Expiry', type: 'date', visible: true, standard: true },
    { key: 'status', label: 'Status', type: 'text', visible: true, standard: true },
];

const columns = ref((props.savedColumns && props.savedColumns.length > 0) ? props.savedColumns : defaultColumns);
const visibleColumns = computed(() => columns.value.filter(c => c.visible));

// Auto-save column preferences to DB
watch(columns, (val) => {
    router.post('/medications/columns', { columns: val }, { preserveScroll: true, preserveState: true });
}, { deep: true });

// --- 2. SEARCH & FILTER WATCHERS ---
let searchTimeout;
watch([search, categoryFilter], ([s, c]) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/medications',
            { search: s, category: c, sort: sortKey.value, direction: sortDir.value },
            { preserveState: true, replace: true }
        );
    }, 500);
});

// --- 3. FORM SETUP ---
const form = useForm({
    id: null, name: '', generic: '', category: '', batch_number: '', stock: 0,
    expiry_date: '', remark: '', custom_fields: {}
});

const openAdd = () => {
    isEditing.value = false;
    form.reset();
    form.custom_fields = {};
    columns.value.forEach(col => { if (!col.standard) form.custom_fields[col.key] = ''; });
    showModal.value = true;
};

const openEdit = (item) => {
    isEditing.value = true;
    form.id = item.id;
    form.name = item.name;
    form.generic = item.generic;
    form.category = item.category;
    form.batch_number = item.batch_number;
    form.stock = item.stock;
    form.expiry_date = item.expiry_date;
    form.remark = item.remark;
    form.custom_fields = item.custom_fields || {};
    // Ensure all dynamic fields exist in form
    columns.value.forEach(col => { if (!col.standard && form.custom_fields[col.key] === undefined) form.custom_fields[col.key] = ''; });
    showModal.value = true;
};

const submitForm = () => {
    // Determine if creating or editing
    if (form.id) {
        router.put(`/medications/${form.id}`, form, { onSuccess: () => { showModal.value = false; resetForm(); }, onError: console.error });
    } else {
        router.post('/medications', form, { onSuccess: () => { showModal.value = false; resetForm(); }, onError: console.error });
    }
};

const allSelected = computed(() => {
    if (props.medications.data.length === 0) return false;
    return props.medications.data.every((item) => selectedIds.value.includes(item.id));
});

const toggleBulkMode = () => {
    bulkMode.value = !bulkMode.value;
    if (!bulkMode.value) {
        selectedIds.value = [];
    }
};

const toggleSelectAll = () => {
    const pageIds = props.medications.data.map((item) => item.id);
    if (allSelected.value) {
        selectedIds.value = selectedIds.value.filter((id) => !pageIds.includes(id));
        return;
    }
    selectedIds.value = [...new Set([...selectedIds.value, ...pageIds])];
};

const toggleSelectItem = (id) => {
    const index = selectedIds.value.indexOf(id);
    if (index >= 0) {
        selectedIds.value.splice(index, 1);
        return;
    }
    selectedIds.value.push(id);
};

const confirmBulkDelete = () => {
    if (selectedIds.value.length === 0) return;

    router.post(route('medications.bulk-delete'), { ids: selectedIds.value }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            bulkMode.value = false;
        },
        onFinish: () => {
            showBulkDeleteModal.value = false;
        }
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return dateStr;
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// --- 4. CUSTOM FIELD MANAGER LOGIC ---
const editingColKey = ref(null);
const newColName = ref('');
const newColType = ref('text');
const newColOptions = ref([]);
const newOptionInput = ref('');

const addOption = () => { if (newOptionInput.value.trim()) { newColOptions.value.push(newOptionInput.value.trim()); newOptionInput.value = ''; } };
const removeOption = (index) => newColOptions.value.splice(index, 1);

const editColumnConfig = (col) => {
    editingColKey.value = col.key;
    newColName.value = col.label;
    newColType.value = col.type;
    newColOptions.value = col.options ? [...col.options] : [];
};

const saveCustomColumn = () => {
    if (!newColName.value) return;
    if (editingColKey.value) {
        const index = columns.value.findIndex(c => c.key === editingColKey.value);
        if (index !== -1) {
            columns.value[index].label = newColName.value;
            if (!columns.value[index].standard) columns.value[index].type = newColType.value;
            columns.value[index].options = [...newColOptions.value];
        }
    } else {
        columns.value.push({
            key: 'custom_' + Date.now(),
            label: newColName.value,
            type: newColType.value,
            options: [...newColOptions.value],
            visible: true,
            standard: false
        });
    }
    cancelColumnEdit();
};

const cancelColumnEdit = () => {
    editingColKey.value = null; newColName.value = ''; newColType.value = 'text';
    newColOptions.value = []; newOptionInput.value = '';
};

const deleteColumn = (key) => { if(confirm('Delete this field?')) columns.value = columns.value.filter(c => c.key !== key); };

const onManagerDrop = (e, targetIndex) => {
    if (managerDragIndex.value === null || managerDragIndex.value === targetIndex) return;
    const item = columns.value.splice(managerDragIndex.value, 1)[0];
    columns.value.splice(targetIndex, 0, item);
};

// --- 5. HELPERS ---
const handleSort = (key) => {
    sortDir.value = (sortKey.value === key && sortDir.value === 'asc') ? 'desc' : 'asc';
    sortKey.value = key;
    router.get('/medications', { search: search.value, category: categoryFilter.value, sort: sortKey.value, direction: sortDir.value }, { preserveState: true });
};

const getStatus = (stock) => {
    if (stock <= 0) return { label: 'Out of Stock', class: 'bg-red-100 text-red-800 border-red-200' };
    if (stock < 50) return { label: 'Low Stock', class: 'bg-amber-100 text-amber-800 border-amber-200' };
    return { label: 'In Stock', class: 'bg-green-100 text-green-800 border-green-200' };
};

const goToPage = (page) => router.get('/medications', { page, search: search.value, category: categoryFilter.value, sort: sortKey.value, direction: sortDir.value }, { preserveState: true });
</script>

<template>
    <Head title="Medication Stock Management" />
    <AppLayout title="Medication Stock Management" parent="Inventory">

        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Medication Stock Management</h1>
            <p class="text-sm text-gray-500 mt-1">Manage inventory levels, track history, and monitor low stock.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center"><Package class="w-7 h-7" /></div>
                <div><p class="text-xs text-gray-500 font-bold uppercase">Total Items</p><p class="text-3xl font-black text-gray-900">{{ medications.total }}</p></div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 rounded-full bg-green-50 text-green-600 flex items-center justify-center"><CheckCircle2 class="w-7 h-7" /></div>
                <div><p class="text-xs text-gray-500 font-bold uppercase">In Stock</p><p class="text-3xl font-black text-gray-900">{{ medications.data.filter(i => i.stock >= 50).length }}</p></div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-red-100 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 rounded-full bg-red-50 text-red-600 flex items-center justify-center"><AlertCircle class="w-7 h-7" /></div>
                <div><p class="text-xs text-gray-500 font-bold uppercase text-red-600">Critical Stock</p><p class="text-3xl font-black text-red-600">{{ medications.data.filter(i => i.stock < 50).length }}</p></div>
            </div>
        </div>

        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4 bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex flex-1 gap-2 w-full md:w-auto">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                    <input v-model="search" type="text" placeholder="Search pharmacy..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]" />
                </div>
                <select v-model="categoryFilter" class="px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-600 text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                    <option value="">All Categories</option>
                    <option v-for="cat in dynamicCategories" :key="cat" :value="cat">{{ cat }}</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button @click="toggleBulkMode" :class="bulkMode ? 'bg-[#34554a] text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'" class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm shadow-sm transition-colors">
                    <Settings2 class="w-4 h-4" /> Bulk Action
                </button>
                <button @click="showColumnModal = true" class="flex items-center gap-2 bg-white border border-gray-200 px-4 py-2 rounded-lg hover:bg-gray-50 text-sm shadow-sm transition-colors">
                    <Settings2 class="w-4 h-4" /> Columns
                </button>
                <button v-if="canCreateInventory" @click="openAdd" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg font-medium hover:bg-opacity-90 text-sm shadow-sm">
                    <Plus class="w-4 h-4" /> Add Medication
                </button>
            </div>
        </div>

        <div v-if="bulkMode" class="mb-4 flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3">
            <span class="text-sm font-semibold text-[#34554a]">{{ selectedIds.length }} selected</span>
            <button @click="showBulkDeleteModal = true" :disabled="selectedIds.length === 0" class="flex items-center gap-2 rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"><Trash2 class="w-4 h-4" /> Delete Selected</button>
            <button @click="toggleBulkMode" class="ml-auto rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                    <tr class="bg-[#34554a] text-white text-sm">
                        <th v-if="bulkMode" class="p-4 font-semibold w-12 text-center"><input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer" /></th>
                        <th v-for="col in visibleColumns" :key="col.key" @click="handleSort(col.key)" class="p-4 font-semibold cursor-pointer hover:bg-[#2a443b]">
                            <div class="flex items-center gap-2">
                                {{ col.label }}
                                <span v-if="sortKey === col.key"><ChevronUp v-if="sortDir === 'asc'" class="w-3 h-3" /><ChevronDown v-else class="w-3 h-3" /></span>
                                <ChevronsUpDown v-else class="w-3 h-3 opacity-30" />
                            </div>
                        </th>
                        <th class="p-4 text-right font-semibold">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y text-sm text-gray-700">
                    <tr v-for="item in medications.data" :key="item.id" class="hover:bg-gray-50 transition-colors">
                        <td v-if="bulkMode" class="p-4 text-center">
                            <input type="checkbox" :checked="selectedIds.includes(item.id)" @change="toggleSelectItem(item.id)" class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer" />
                        </td>
                        <td v-for="col in visibleColumns" :key="col.key" class="p-4">
                            <span v-if="col.key === 'status'" :class="getStatus(item.stock).class" class="px-2.5 py-0.5 rounded-full text-xs font-medium border">
                                {{ getStatus(item.stock).label }}
                            </span>
                            <span v-else-if="col.key === 'expiry_date'">{{ formatDate(item[col.key]) }}</span>
                            <span v-else-if="col.key === 'name'" class="font-bold text-gray-900">{{ item.name }}</span>
                            <span v-else-if="!col.standard">{{ item.custom_fields?.[col.key] || '-' }}</span>
                            <span v-else>{{ item[col.key] || '-' }}</span>
                        </td>
                        <td class="p-4 text-right flex justify-end gap-3 text-gray-400">
                            <button class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors" @click="selectedItem = item; showViewModal = true"><Eye class="w-4 h-4" /></button>
                            <button class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors" @click="selectedItem = item; showHistoryModal = true"><History class="w-4 h-4" /></button>
                            <button class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors" @click="openEdit(item)"><Pencil class="w-4 h-4" /></button>
                            <button class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors" @click="itemToDelete = item; showDeleteModal = true"><Trash2 class="w-4 h-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="medications.data.length === 0">
                        <td :colspan="visibleColumns.length + (bulkMode ? 2 : 1)" class="p-8 text-center text-gray-500 italic">No medication records found.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t bg-gray-50 flex items-center justify-between">
                <p class="text-sm text-gray-500 font-medium">Showing {{ medications.from || 0 }} to {{ medications.to || 0 }} of {{ medications.total }} records</p>
                <div class="flex gap-2">
                    <button @click="goToPage(medications.current_page - 1)" :disabled="!medications.prev_page_url" class="w-8 h-8 flex items-center justify-center border rounded-lg bg-white disabled:opacity-50 transition-all"><ChevronLeft class="w-4 h-4"/></button>
                    <button @click="goToPage(medications.current_page + 1)" :disabled="!medications.next_page_url" class="w-8 h-8 flex items-center justify-center border rounded-lg bg-white disabled:opacity-50 transition-all"><ChevronRight class="w-4 h-4"/></button>
                </div>
            </div>
        </div>

        <div v-if="showColumnModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm transition-all" @click.self="showColumnModal = false">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden flex flex-col max-h-[90vh]">
                <div class="flex justify-between items-center p-6 border-b bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-900">Manage Dynamic Fields</h3>
                    <button @click="showColumnModal = false" class="text-gray-400 hover:text-gray-600 transition-colors"><X class="w-5 h-5" /></button>
                </div>
                <div class="p-6 overflow-y-auto custom-scrollbar">
                    <h4 class="text-xs font-bold text-gray-500 uppercase mb-3 tracking-wider">Active Columns (Drag to Reorder)</h4>
                    <div class="grid grid-cols-1 gap-2 mb-6">
                        <div v-for="(col, index) in columns" :key="col.key"
                             class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 cursor-grab active:cursor-grabbing group transition-colors bg-white shadow-sm"
                             draggable="true" @dragstart="managerDragIndex = index" @dragover.prevent @drop="onManagerDrop($event, index)">
                            <div class="flex items-center gap-3">
                                <GripVertical class="w-4 h-4 text-gray-300 group-hover:text-gray-500" />
                                <input type="checkbox" v-model="col.visible" class="w-4 h-4 text-[#34554a] rounded focus:ring-[#34554a] cursor-pointer">
                                <span class="text-sm font-medium text-gray-700">{{ col.label }}</span>
                            </div>
                            <div class="flex gap-2">
                                <button @click="editColumnConfig(col)" class="text-blue-400 hover:text-blue-600 transition-colors"><Pencil class="w-4 h-4" /></button>
                                <button v-if="!col.standard" @click="deleteColumn(col.key)" class="text-red-400 hover:text-red-600 transition-colors"><Trash2 class="w-4 h-4" /></button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-5 rounded-xl border border-gray-200" :class="{'border-[#34554a] ring-1 ring-[#34554a]': editingColKey}">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-xs font-bold uppercase tracking-wide" :class="editingColKey ? 'text-[#34554a]' : 'text-gray-800'">
                                {{ editingColKey ? 'Editing: ' + newColName : 'Add New Column' }}
                            </h4>
                            <button v-if="editingColKey" @click="cancelColumnEdit" class="text-xs text-gray-500 underline">Cancel</button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
                            <div class="md:col-span-4 space-y-1">
                                <label class="text-xs font-semibold text-gray-600">Field Name</label>
                                <input v-model="newColName" type="text" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-1 focus:ring-[#34554a] outline-none shadow-sm">
                            </div>
                            <div class="md:col-span-3 space-y-1">
                                <label class="text-xs font-semibold text-gray-600">Type</label>
                                <select v-model="newColType" :disabled="editingColKey && columns.find(c => c.key === editingColKey)?.standard" class="w-full px-3 py-2 border rounded-lg text-sm bg-white outline-none shadow-sm">
                                    <option value="text">Text</option>
                                    <option value="number">Number</option>
                                    <option value="select">Dropdown</option>
                                    <option value="date">Date</option>
                                </select>
                            </div>
                            <div class="md:col-span-5 space-y-1" v-if="newColType === 'select'">
                                <label class="text-xs font-semibold text-gray-600">Options</label>
                                <div class="flex gap-2 mb-2">
                                    <input v-model="newOptionInput" type="text" placeholder="Add Option" class="w-full px-2 py-1 border rounded text-sm outline-none shadow-sm">
                                    <button @click="addOption" class="px-3 py-1 bg-gray-200 rounded text-xs hover:bg-gray-300 transition-colors font-bold">Add</button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span v-for="(opt, idx) in newColOptions" :key="idx" class="px-2 py-1 bg-white border rounded text-xs flex items-center gap-1 shadow-sm font-medium">
                                        {{ opt }} <button @click="removeOption(idx)" class="text-red-500 font-bold ml-1 hover:bg-red-50 rounded">×</button>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <button @click="saveCustomColumn" class="mt-5 w-full bg-[#34554a] text-white py-2 rounded-lg text-sm font-bold hover:bg-opacity-90 shadow-md transition-all">
                            {{ editingColKey ? 'Update Configuration' : 'Add Dynamic Field' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm transition-all" @click.self="showModal = false">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 overflow-hidden max-h-[90vh] flex flex-col border border-gray-100">
                <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-900">{{ isEditing ? 'Edit Medication' : 'Add Medication' }}</h3>
                    <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 transition-colors"><X class="w-5 h-5" /></button>
                </div>
                <div class="p-6 overflow-y-auto bg-white custom-scrollbar">
                    <form @submit.prevent="submitForm" class="space-y-5">
                        <div v-for="col in columns" :key="col.key">
                            <div v-if="col.key !== 'status' && col.visible" class="space-y-1">
                                <label class="block text-sm font-bold text-gray-700">{{ col.label }}</label>

                                <template v-if="col.standard">
                                    <select v-if="col.type === 'select'" v-model="form[col.key]" class="w-full p-2 border rounded-lg bg-white outline-none focus:ring-2 focus:ring-[#34554a] shadow-sm">
                                        <option value="" disabled>Select...</option>
                                        <option v-for="opt in col.options" :key="opt" :value="opt">{{ opt }}</option>
                                    </select>
                                    <input v-else :type="col.type" v-model="form[col.key]" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm">
                                </template>

                                <template v-else>
                                    <select v-if="col.type === 'select'" v-model="form.custom_fields[col.key]" class="w-full p-2 border rounded-lg bg-white focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm">
                                        <option value="">Select option...</option>
                                        <option v-for="opt in col.options" :key="opt" :value="opt">{{ opt }}</option>
                                    </select>
                                    <input v-else :type="col.type" v-model="form.custom_fields[col.key]" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none shadow-sm">
                                </template>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-5 border-t border-gray-100">
                            <button type="button" @click="showModal = false" class="px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-50 transition-all font-medium">Cancel</button>
                            <button type="submit" class="px-6 py-2 bg-[#34554a] text-white rounded-lg hover:bg-opacity-90 shadow-md font-bold transition-all">Save Medication</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div v-if="showViewModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm transition-all" @click.self="showViewModal = false">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 overflow-hidden flex flex-col border border-gray-100">
                <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-900">Medication Details</h3>
                    <button @click="showViewModal = false" class="text-gray-400 hover:text-gray-600 transition-colors"><X class="w-5 h-5" /></button>
                </div>
                <div class="p-6 space-y-4 bg-white">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-16 h-16 bg-blue-50 text-[#34554a] rounded-full flex items-center justify-center shadow-inner"><Pill class="w-8 h-8" /></div>
                        <div><h4 class="text-xl font-bold text-gray-900 tracking-tight">{{ selectedItem?.name }}</h4><p class="text-sm text-gray-500 font-medium">{{ selectedItem?.generic }}</p></div>
                    </div>
                    <div class="space-y-3">
                        <div v-for="col in columns" :key="col.key" class="flex justify-between border-b pb-2 border-gray-100 last:border-0">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">{{ col.label }}</span>
                            <span v-if="col.key === 'status'" :class="getStatus(selectedItem?.stock).class" class="px-2 py-0.5 rounded-full text-[10px] font-black border uppercase tracking-wider">{{ getStatus(selectedItem?.stock).label }}</span>
                            <span v-else-if="col.key === 'expiry_date'" class="text-sm font-semibold text-gray-900">{{ formatDate(selectedItem?.[col.key]) }}</span>
                            <span v-else-if="!col.standard" class="text-sm font-semibold text-gray-900">{{ selectedItem?.custom_fields?.[col.key] || '-' }}</span>
                            <span v-else class="text-sm font-semibold text-gray-900">{{ selectedItem?.[col.key] || '-' }}</span>
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-gray-50 flex justify-end rounded-b-xl border-t">
                    <button @click="showViewModal = false" class="px-6 py-2 border bg-white rounded-lg text-sm font-bold shadow-sm hover:bg-gray-50 transition-all text-gray-700">Close Details</button>
                </div>
            </div>
        </div>

        <div v-if="showHistoryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm transition-all" @click.self="showHistoryModal = false">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[85vh] flex flex-col border border-gray-100">
                <div class="p-6 border-b border-gray-100 bg-gray-50 flex justify-between items-center rounded-t-xl">
                    <div><h3 class="font-bold text-gray-900 tracking-tight">Activity History</h3><p class="text-xs text-gray-500 uppercase font-black tracking-widest mt-1">{{ selectedItem?.name }}</p></div>
                    <button @click="showHistoryModal = false" class="text-gray-400 hover:text-gray-600 transition-colors"><X class="w-5 h-5" /></button>
                </div>
                <div class="p-8 overflow-y-auto custom-scrollbar bg-white">
                    <div class="space-y-8 relative border-l-2 border-gray-100 ml-3">
                        <div v-for="(h, idx) in selectedItem?.history" :key="h.id" class="relative pl-7">
                            <div class="absolute -left-[10px] top-1 w-4 h-4 rounded-full border-4 border-white shadow-sm" :class="idx === 0 ? 'bg-[#34554a]' : 'bg-gray-200'"></div>
                            <p class="text-[10px] text-gray-400 font-mono mb-1 font-black">{{ new Date(h.created_at).toLocaleString() }}</p>
                            <p class="font-bold text-sm text-gray-800">{{ h.action }} by {{ h.user }}</p>
                            <p class="text-xs text-gray-500 mt-1 leading-relaxed font-medium">{{ h.detail }}</p>
                        </div>
                        <div v-if="!selectedItem?.history?.length" class="text-center py-6 text-gray-400 italic text-sm font-medium">No activity records found for this medication.</div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal :show="showDeleteModal" :item-name="itemToDelete?.name" @close="showDeleteModal = false" @confirm="router.delete(`/medications/${itemToDelete.id}`, { onSuccess: () => showDeleteModal = false })" />
        <DeleteConfirmationModal :show="showBulkDeleteModal" item-name="selected medication records" @close="showBulkDeleteModal = false" @confirm="confirmBulkDelete" />

    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
th.cursor-grab:active { cursor: grabbing; }
</style>
