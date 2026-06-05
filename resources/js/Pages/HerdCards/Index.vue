<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Grid3x3,
    CheckCircle,
    AlertTriangle,
    DollarSign,
    Search,
    Eye,
    History,
    Pencil,
    Plus,
    LayoutGrid,
    List,
    ChevronLeft,
    ChevronRight,
    X,
    Save,
    Loader2,
    Trash2
} from 'lucide-vue-next';

const props = defineProps({
    herdCards: Array,
    herds: Array,
    summary: Object,
    filters: Object,
});

// View mode: 'table' or 'grid'
const viewMode = ref('table');

// Local filter state
const search = ref(props.filters?.search || '');
const selectedMonth = ref(props.filters?.month || new Date().toISOString().slice(0, 7));
const selectedStatus = ref(props.filters?.status || '');

// Herds list (reactive)
const herds = ref(props.herds || []);

// Edit modal state
const showEditModal = ref(false);
const editingCard = ref(null);
const editForm = useForm({
    name: '',
    area: '',
    herd: '',
    rate_per_ha: 11.09,
    month: '',
    grazing_data_id: null,
    allocated_area: 0,
    rotation_period: 62,
    days_in_month: 30,
    current_month_ha: 0,
    deduction_percent: 0,
    deduction_amount: 0,
    to_date_ha: 0,
    total_budget: 0,
    ytd_claim: 0,
    blocks: [],
});

// Herd CRUD Modal State
const showHerdModal = ref(false);
const herdModalMode = ref('add');
const editingHerdId = ref(null);
const herdForm = useForm({
    name: ''
});

// Estate CRUD Modal State
const showEstateModal = ref(false);
const estateModalMode = ref('add');
const estateForm = useForm({
    name: '',
    area: ''
});

// Bulk Selection State
const selectedItems = ref([]);
const selectAll = ref(false);

// Delete Modal State
const showDeleteModal = ref(false);
const deleteTarget = ref(null); // 'single', 'bulk', 'estate', 'herd'
const deleteItemName = ref('');
const deleteItemId = ref(null);
const isDeleting = ref(false);

// View Modal State
const showViewModal = ref(false);
const viewingCard = ref(null);

// Open view modal
const openViewModal = (e, card) => {
    e.stopPropagation();
    viewingCard.value = card;
    showViewModal.value = true;
};

// Toggle select all
const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedItems.value = props.herdCards.map(card => card.id);
    } else {
        selectedItems.value = [];
    }
};

// Toggle single item selection
const toggleItemSelection = (id) => {
    const index = selectedItems.value.indexOf(id);
    if (index > -1) {
        selectedItems.value.splice(index, 1);
    } else {
        selectedItems.value.push(id);
    }
    selectAll.value = selectedItems.value.length === props.herdCards.length;
};

// Check if item is selected
const isSelected = (id) => selectedItems.value.includes(id);

// Open delete confirmation modal
const openDeleteModal = (type, name = '', id = null) => {
    deleteTarget.value = type;
    deleteItemName.value = name;
    deleteItemId.value = id;
    showDeleteModal.value = true;
};

// Confirm delete action
const confirmDelete = () => {
    isDeleting.value = true;

    if (deleteTarget.value === 'bulk') {
        // Bulk delete
        router.post(route('herd-cards.estates.bulk-destroy'), {
            ids: selectedItems.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedItems.value = [];
                selectAll.value = false;
                showDeleteModal.value = false;
                isDeleting.value = false;
            },
            onError: () => {
                isDeleting.value = false;
            }
        });
    } else if (deleteTarget.value === 'estate') {
        router.delete(route('herd-cards.estates.destroy', deleteItemId.value), {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteModal.value = false;
                showEditModal.value = false;
                isDeleting.value = false;
            },
            onError: () => {
                isDeleting.value = false;
            }
        });
    } else if (deleteTarget.value === 'herd') {
        router.delete(route('herd-cards.herds.destroy', deleteItemId.value), {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteModal.value = false;
                editForm.herd = herds.value[0]?.name || '';
                isDeleting.value = false;
            },
            onError: () => {
                isDeleting.value = false;
            }
        });
    }
};

// Watch for herds prop changes
watch(() => props.herds, (newHerds) => {
    if (newHerds) herds.value = newHerds;
}, { deep: true });

// Format currency
const fmtMoney = (num) => {
    return "RM " + parseFloat(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const toTitleCase = (value) => {
    if (!value) return '';
    return String(value)
        .toLowerCase()
        .split(' ')
        .filter(Boolean)
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

// Format month display
const formatMonthDisplay = (monthStr) => {
    if (!monthStr) return '';
    const date = new Date(monthStr + '-01');
    return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
};

// Apply filters
const applyFilters = () => {
    router.get(route('herd-cards.index'), {
        month: selectedMonth.value,
        status: selectedStatus.value,
        search: search.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Debounced search
let searchTimeout;
watch(search, (newVal) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
});

watch([selectedMonth, selectedStatus], () => {
    applyFilters();
});

// Navigate to herd details
const goToDetails = (card) => {
    router.visit('/herd-cards/' + card.id + '?month=' + selectedMonth.value);
};

// Open edit modal
const openEditModal = (e, card) => {
    e.stopPropagation();
    editingCard.value = card;
    // Basic info
    editForm.name = card.name;
    editForm.area = card.total_area;
    editForm.herd = card.herd || '';
    editForm.month = selectedMonth.value;
    editForm.grazing_data_id = card.grazing_data_id || null;
    // Coverage & Financial
    editForm.allocated_area = card.allocated_area || card.total_area;
    editForm.rotation_period = card.rotation_period || 62;
    editForm.days_in_month = card.days_in_month || 30;
    editForm.current_month_ha = card.current_month_ha || 0;
    editForm.rate_per_ha = card.rate_per_ha || 11.09;
    editForm.deduction_percent = card.deduction_percent || 0;
    editForm.deduction_amount = card.deduction_amount || 0;
    editForm.to_date_ha = card.to_date_ha || 0;
    editForm.total_budget = card.total_budget || 0;
    editForm.ytd_claim = card.ytd_claim || 0;
    // Blocks
    editForm.blocks = card.blocks ? [...card.blocks] : [];
    showEditModal.value = true;
};

// Add new block
const addBlock = () => {
    editForm.blocks.push({
        id: null,
        block_id: `Block ${editForm.blocks.length + 1}`,
        area: 0,
        actual: 0,
        achievement: 0,
        rate: editForm.rate_per_ha || 11.09,
    });
};

// Remove block
const removeBlock = (index) => {
    editForm.blocks.splice(index, 1);
};

// Save edit
const saveEdit = () => {
    if (!editingCard.value || !editingCard.value.id) {
        alert('Error: No card selected for editing');
        return;
    }

    // Prepare blocks - ensure block_id is always a string
    const preparedBlocks = editForm.blocks.map(block => ({
        id: block.id || null,
        block_id: String(block.block_id || ''),
        area: Number(block.area) || 0,
        actual: Number(block.actual) || 0,
        achievement: Number(block.achievement) || 0,
        rate: Number(block.rate) || 11.09,
    }));

    // Get area - ensure it's a valid number, fallback to editingCard values
    let areaValue = Number(editForm.area);
    if (isNaN(areaValue) || areaValue === 0) {
        areaValue = Number(editingCard.value.total_area) || Number(editingCard.value.area) || 0;
    }

    const data = {
        name: editForm.name,
        area: areaValue,
        herd: editForm.herd || '',
        rate_per_ha: Number(editForm.rate_per_ha) || 11.09,
        month: editForm.month,
        grazing_data_id: editForm.grazing_data_id,
        allocated_area: Number(editForm.allocated_area) || 0,
        rotation_period: Number(editForm.rotation_period) || 62,
        days_in_month: Number(editForm.days_in_month) || 30,
        current_month_ha: Number(editForm.current_month_ha) || 0,
        deduction_percent: Number(editForm.deduction_percent) || 0,
        deduction_amount: Number(editForm.deduction_amount) || 0,
        to_date_ha: Number(editForm.to_date_ha) || 0,
        total_budget: Number(editForm.total_budget) || 0,
        ytd_claim: Number(editForm.ytd_claim) || 0,
        blocks: preparedBlocks,
    };

    console.log('Sending data:', JSON.stringify(data));

    router.put(route('herd-cards.update', editingCard.value.id), data, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingCard.value = null;
        },
        onError: (errors) => {
            console.error('Save errors:', errors);
            alert('Error saving: ' + JSON.stringify(errors));
        },
    });
};

// Herd CRUD Functions
const openAddHerdModal = () => {
    herdModalMode.value = 'add';
    herdForm.reset();
    showHerdModal.value = true;
};

const openEditHerdModal = () => {
    const currentHerd = herds.value.find(h => h.name === editForm.herd);
    if (!currentHerd) return;
    herdModalMode.value = 'edit';
    editingHerdId.value = currentHerd.id;
    herdForm.name = currentHerd.name;
    showHerdModal.value = true;
};

const saveHerd = () => {
    if (herdModalMode.value === 'add') {
        herdForm.post(route('herd-cards.herds.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showHerdModal.value = false;
                herdForm.reset();
            }
        });
    } else {
        herdForm.put(route('herd-cards.herds.update', editingHerdId.value), {
            preserveScroll: true,
            onSuccess: () => {
                showHerdModal.value = false;
                herdForm.reset();
            }
        });
    }
};

const deleteHerd = () => {
    const currentHerd = herds.value.find(h => h.name === editForm.herd);
    if (!currentHerd) return;
    openDeleteModal('herd', currentHerd.name, currentHerd.id);
};

// Estate CRUD Functions
const openAddEstateModal = () => {
    estateModalMode.value = 'add';
    estateForm.reset();
    showEstateModal.value = true;
};

const openEditEstateModal = () => {
    estateModalMode.value = 'edit';
    estateForm.name = editForm.name;
    estateForm.area = editForm.area;
    showEstateModal.value = true;
};

const saveEstate = () => {
    if (estateModalMode.value === 'add') {
        estateForm.post(route('herd-cards.estates.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showEstateModal.value = false;
                estateForm.reset();
            }
        });
    } else {
        estateForm.put(route('herd-cards.estates.update', editingCard.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showEstateModal.value = false;
                estateForm.reset();
                // Update the edit form with new values
                editForm.name = estateForm.name;
                editForm.area = estateForm.area;
            }
        });
    }
};

const deleteEstate = () => {
    openDeleteModal('estate', editForm.name, editingCard.value.id);
};

// Bulk delete selected items
const bulkDelete = () => {
    if (selectedItems.value.length === 0) return;
    openDeleteModal('bulk', `${selectedItems.value.length} estates`, null);
};

// Get status badge classes
const getStatusClasses = (status) => {
    switch (status) {
        case 'above':
            return 'bg-gray-100 text-gray-700 border-gray-200';
        case 'at':
            return 'bg-gray-100 text-gray-700 border-gray-200';
        case 'below':
            return 'bg-gray-100 text-gray-700 border-gray-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};

// Get achievement bar color
const getAchievementColor = (achievement) => {
    return 'bg-gray-500';
};

// Get icon background for status
const getIconBg = (status) => {
    switch (status) {
        case 'above':
            return 'bg-gray-100 text-gray-600';
        case 'at':
            return 'bg-gray-100 text-gray-600';
        case 'below':
            return 'bg-gray-100 text-gray-600';
        default:
            return 'bg-gray-50 text-gray-600';
    }
};

// Get text color for achievement
const getAchievementTextColor = (achievement) => {
    return 'text-gray-700';
};
</script>

<template>
    <Head title="Herd Cards" />
    <AppLayout title="Herd Cards" parent="Farm Management">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Herd Management Cards</h1>
                <p class="text-sm text-gray-500 mt-1">Monthly Performance Summary for {{ formatMonthDisplay(selectedMonth) }}</p>
            </div>

            <!-- Summary Stat Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-5 shadow-sm">
                    <div class="w-14 h-14 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center">
                        <Grid3x3 class="w-7 h-7" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-bold">Total Cattle</p>
                        <p class="text-3xl font-black text-gray-900">{{ summary.total_herds }}</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-5 shadow-sm">
                    <div class="w-14 h-14 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center">
                        <CheckCircle class="w-7 h-7" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-bold">Above Target</p>
                        <p class="text-3xl font-black text-gray-900">{{ summary.above_target }}</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-5 shadow-sm">
                    <div class="w-14 h-14 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center">
                        <AlertTriangle class="w-7 h-7" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-bold">At Target</p>
                        <p class="text-3xl font-black text-gray-900">{{ summary.at_target }}</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-5 shadow-sm">
                    <div class="w-14 h-14 rounded-full bg-[#34554a]/10 text-[#34554a] flex items-center justify-center">
                        <DollarSign class="w-7 h-7" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-bold">Total Claim</p>
                        <p class="text-3xl font-black text-gray-900">{{ fmtMoney(summary.total_claim) }}</p>
                    </div>
                </div>
            </div>

            <!-- Filter/Action Bar -->
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4 bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <div class="flex flex-1 gap-2 w-full md:w-auto">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search herds..."
                            class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                        />
                    </div>
                    <select
                        v-model="selectedStatus"
                        class="px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-600 text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                        <option value="">All Status</option>
                        <option value="above">Above Target</option>
                        <option value="at">At Target</option>
                        <option value="below">Below Target</option>
                    </select>
                    <input
                        v-model="selectedMonth"
                        type="month"
                        class="px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-600 text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                    />
                </div>
                <div class="flex gap-2">
                    <!-- Bulk Delete Button -->
                    <button
                        v-if="selectedItems.length > 0"
                        @click="bulkDelete"
                        class="flex items-center gap-2 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 text-sm font-medium transition-colors">
                        <Trash2 class="w-4 h-4" />
                        Delete ({{ selectedItems.length }})
                    </button>

                    <!-- View Toggle -->
                    <div class="flex border border-gray-200 rounded-lg overflow-hidden">
                        <button
                            @click="viewMode = 'table'"
                            :class="[
                                'flex items-center gap-2 px-3 py-2 text-sm transition-colors',
                                viewMode === 'table' ? 'bg-[#34554a] text-white' : 'bg-white text-gray-600 hover:bg-gray-50'
                            ]">
                            <List class="w-4 h-4" />
                        </button>
                        <button
                            @click="viewMode = 'grid'"
                            :class="[
                                'flex items-center gap-2 px-3 py-2 text-sm transition-colors',
                                viewMode === 'grid' ? 'bg-[#34554a] text-white' : 'bg-white text-gray-600 hover:bg-gray-50'
                            ]">
                            <LayoutGrid class="w-4 h-4" />
                        </button>
                    </div>
                    <Link
                        href="/herd-cards/grazing-detail"
                        class="flex items-center gap-2 border border-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium hover:bg-gray-50 text-sm shadow-sm">
                        <Eye class="w-4 h-4" />
                        View Grazing Details
                    </Link>
                    <Link
                        :href="route('data-input.index')"
                        class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg font-medium hover:bg-opacity-90 text-sm shadow-sm">
                        <Plus class="w-4 h-4" />
                        Add Data
                    </Link>
                </div>
            </div>

            <!-- TABLE VIEW -->
            <div v-if="viewMode === 'table'" class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200 mb-8">
                <div class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead>
                            <tr class="bg-[#34554a] text-white text-sm">
                                <th class="p-4 w-12">
                                    <input
                                        type="checkbox"
                                        v-model="selectAll"
                                        @change="toggleSelectAll"
                                        class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                    />
                                </th>
                                <th class="p-4 font-semibold">Herd / Estate Name</th>
                                <th class="p-4 font-semibold">Total Area</th>
                                <th class="p-4 font-semibold">Active Blocks</th>
                                <th class="p-4 font-semibold">Achievement</th>
                                <th class="p-4 font-semibold">RM/Hectare</th>
                                <th class="p-4 font-semibold">Monthly Claim</th>
                                <th class="p-4 font-semibold">Status</th>
                                <th class="p-4 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-sm text-gray-700">
                            <tr v-if="herdCards.length === 0">
                                <td colspan="9" class="p-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center gap-2">
                                        <Grid3x3 class="w-12 h-12 text-gray-300" />
                                        <p class="font-medium">No herd data found</p>
                                        <p class="text-xs">Add data through the Data Input page</p>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="card in herdCards"
                                :key="card.id"
                                @click="goToDetails(card)"
                                :class="['hover:bg-gray-50 transition-colors cursor-pointer', isSelected(card.id) ? 'bg-gray-50' : '']">
                                <td class="p-4" @click.stop>
                                    <input
                                        type="checkbox"
                                        :checked="isSelected(card.id)"
                                        @change="toggleItemSelection(card.id)"
                                        class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                    />
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div :class="['w-10 h-10 rounded-lg flex items-center justify-center', getIconBg(card.status)]">
                                            <Grid3x3 class="w-5 h-5" />
                                        </div>
                                        <div>
                                            <span class="font-bold text-gray-900">{{ toTitleCase(card.name) }}</span>
                                            <p class="text-xs text-gray-400">
                                                <span v-if="card.herd" class="text-gray-700 font-medium">{{ toTitleCase(card.herd) }}</span>
                                                <span v-else>{{ card.updated_at ? 'Updated ' + card.updated_at : 'No data' }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 font-medium">{{ card.total_area }} Ha</td>
                                <td class="p-4">{{ card.active_blocks }}</td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div
                                                :class="['h-full rounded-full', getAchievementColor(card.achievement)]"
                                                :style="{ width: Math.min(card.achievement, 100) + '%' }">
                                            </div>
                                        </div>
                                        <span :class="['font-bold', getAchievementTextColor(card.achievement)]">
                                            {{ card.achievement }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4">RM {{ card.rate_per_ha.toFixed(2) }}</td>
                                <td class="p-4 font-bold text-gray-900">
                                    {{ fmtMoney(card.monthly_claim) }}
                                </td>
                                <td class="p-4">
                                    <span :class="['px-2.5 py-0.5 rounded-full text-xs font-medium border', getStatusClasses(card.status)]">
                                        {{ card.status_label }}
                                    </span>
                                </td>
                                <td class="p-4 text-right" @click.stop>
                                    <div class="flex justify-end gap-3 text-gray-400">
                                        <button
                                            @click="openViewModal($event, card)"
                                            class="cursor-pointer hover:text-[#34554a] transition-colors"
                                            title="View Details">
                                            <Eye class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="openEditModal($event, card)"
                                            class="cursor-pointer hover:text-[#34554a] transition-colors"
                                            title="Edit">
                                            <Pencil class="w-4 h-4" />
                                        </button>
                                        <button
                                            @click="openDeleteModal('estate', card.name, card.id)"
                                            class="cursor-pointer hover:text-[#34554a] transition-colors"
                                            title="Delete">
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer -->
                <div class="p-4 border-t bg-gray-50 flex items-center justify-between">
                    <p class="text-sm text-gray-500 font-medium">Showing {{ herdCards.length }} herds</p>
                    <div class="flex items-center gap-4">
                        <span class="text-sm font-medium text-gray-900">
                            Total: <span class="text-gray-900">{{ fmtMoney(summary.total_claim) }}</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- GRID VIEW -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div v-if="herdCards.length === 0" class="col-span-full bg-white rounded-xl p-8 text-center text-gray-500 border border-gray-200">
                    <Grid3x3 class="w-12 h-12 text-gray-300 mx-auto mb-2" />
                    <p class="font-medium">No herd data found</p>
                    <p class="text-xs">Add data through the Data Input page</p>
                </div>

                <div
                    v-for="card in herdCards"
                    :key="card.id"
                    @click="goToDetails(card)"
                    class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all cursor-pointer overflow-hidden group">
                    <!-- Card Header -->
                    <div :class="[
                        'p-4 border-b',
                        'bg-gray-50 border-gray-100'
                    ]">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div :class="['w-10 h-10 rounded-lg flex items-center justify-center', getIconBg(card.status)]">
                                    <Grid3x3 class="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900">{{ toTitleCase(card.name) }}</h3>
                                    <p v-if="card.herd" class="text-xs text-gray-700 font-medium">{{ toTitleCase(card.herd) }}</p>
                                </div>
                            </div>
                            <span :class="['px-2.5 py-0.5 rounded-full text-xs font-medium border', getStatusClasses(card.status)]">
                                {{ card.status_label }}
                            </span>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="p-4 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-gray-500 font-medium">Achievement</span>
                            <div class="flex items-center gap-2">
                                <div class="w-20 h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div
                                        :class="['h-full rounded-full', getAchievementColor(card.achievement)]"
                                        :style="{ width: Math.min(card.achievement, 100) + '%' }">
                                    </div>
                                </div>
                                <span :class="['font-bold text-sm', getAchievementTextColor(card.achievement)]">
                                    {{ card.achievement }}%
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-100">
                            <div>
                                <p class="text-xs text-gray-400 font-bold">Area</p>
                                <p class="text-sm font-semibold text-gray-900">{{ card.total_area }} Ha</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-bold">Blocks</p>
                                <p class="text-sm font-semibold text-gray-900">{{ card.active_blocks }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-bold">Rate</p>
                                <p class="text-sm font-semibold text-gray-900">RM {{ card.rate_per_ha.toFixed(2) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-bold">Claim</p>
                                <p class="text-sm font-bold text-gray-900">
                                    {{ fmtMoney(card.monthly_claim) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex justify-between items-center" @click.stop>
                        <span class="text-xs text-gray-400">{{ card.updated_at ? 'Updated ' + card.updated_at : 'No data' }}</span>
                        <div class="flex gap-2 text-gray-400">
                            <button @click="openViewModal($event, card)" class="hover:text-[#34554a] transition-colors" title="View">
                                <Eye class="w-4 h-4" />
                            </button>
                            <button @click="openEditModal($event, card)" class="hover:text-[#34554a] transition-colors" title="Edit">
                                <Pencil class="w-4 h-4" />
                            </button>
                            <button @click="openDeleteModal('estate', card.name, card.id)" class="hover:text-[#34554a] transition-colors" title="Delete">
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Modal - Matching Data Input Layout -->
            <div v-if="showEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto py-4" @click.self="showEditModal = false">
                <div class="bg-gray-100 rounded-xl w-full max-w-5xl shadow-2xl my-4 max-h-[95vh] overflow-y-auto">
                    <!-- Header -->
                    <div class="bg-white px-6 py-4 border-b flex items-center justify-between sticky top-0 z-10">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Edit Grazing Data</h3>
                            <p class="text-xs text-gray-500">{{ editForm.name }} - {{ formatMonthDisplay(editForm.month) }}</p>
                        </div>
                        <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 p-2">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <form @submit.prevent="saveEdit" class="p-6 space-y-6">
                        <!-- Validation Errors Display -->
                        <div v-if="Object.keys(editForm.errors).length > 0" class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <h4 class="text-sm font-bold text-gray-700 mb-2">Please fix the following errors:</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                                <li v-for="(error, field) in editForm.errors" :key="field">{{ error }}</li>
                            </ul>
                        </div>

                        <!-- Selected Estate/Herd/Month Section -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h4 class="text-sm font-bold text-gray-500 mb-4">Selected Estate & Period</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Estate Name</label>
                                    <div class="flex gap-2">
                                        <input v-model="editForm.name" type="text" required class="flex-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Selected Herd</label>
                                    <div class="flex gap-2">
                                        <select v-model="editForm.herd" class="flex-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                                            <option value="">-- Select Herd --</option>
                                            <option v-for="herd in herds" :key="herd.id" :value="herd.name">{{ toTitleCase(herd.name) }}</option>
                                        </select>
                                        <button type="button" @click="openAddHerdModal" class="bg-[#34554a] hover:bg-[#2a443b] text-white rounded-lg px-3 py-2" title="Add New Herd">
                                            <Plus class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Month</label>
                                    <input type="month" v-model="editForm.month" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                                </div>
                            </div>
                        </div>

                        <!-- Coverage & Financials Section -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h4 class="text-lg font-semibold text-gray-700 mb-6 border-b border-gray-100 pb-2">Monthly Grazing Coverage & Financials</h4>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-4">
                                <!-- Left: Coverage -->
                                <div class="space-y-4">
                                    <div class="grid grid-cols-2 gap-4 items-center">
                                        <label class="text-sm text-gray-600 font-medium">Allocated Area (Ha)</label>
                                        <input type="number" v-model="editForm.allocated_area" step="0.01" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-right font-medium focus:ring-2 focus:ring-[#34554a]">
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 items-center">
                                        <label class="text-sm text-gray-600 font-medium">Rotation Period (Days)</label>
                                        <input type="number" v-model="editForm.rotation_period" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-right font-medium focus:ring-2 focus:ring-[#34554a]">
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 items-center">
                                        <label class="text-sm text-gray-600 font-medium">Days in Month</label>
                                        <input type="number" v-model="editForm.days_in_month" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-right font-medium focus:ring-2 focus:ring-[#34554a]">
                                    </div>
                                    <div class="border-t border-gray-100 my-2"></div>
                                    <div class="grid grid-cols-2 gap-4 items-center">
                                        <label class="text-sm text-gray-900 font-semibold">Current Month (Ha)</label>
                                        <input type="number" v-model="editForm.current_month_ha" step="0.01" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-right font-bold text-gray-800 focus:ring-2 focus:ring-[#34554a]">
                                    </div>
                                </div>
                                <!-- Right: Financials -->
                                <div class="space-y-4">
                                    <div class="grid grid-cols-2 gap-4 items-center">
                                        <label class="text-sm text-gray-600 font-medium">Pay/Ha (RM)</label>
                                        <input type="number" v-model="editForm.rate_per_ha" step="0.01" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-right font-medium focus:ring-2 focus:ring-[#34554a]">
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 items-center">
                                        <label class="text-sm text-gray-600 font-medium">Deduction %</label>
                                        <input type="number" v-model="editForm.deduction_percent" step="0.01" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-right font-medium focus:ring-2 focus:ring-[#34554a]">
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 items-center">
                                        <label class="text-sm text-gray-600 font-medium">Deduction Amount (RM)</label>
                                        <input type="number" v-model="editForm.deduction_amount" step="0.01" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-right font-medium text-gray-700 focus:ring-2 focus:ring-[#34554a]">
                                    </div>
                                    <div class="border-t border-gray-100 my-2"></div>
                                    <div class="grid grid-cols-2 gap-4 items-center">
                                        <label class="text-sm text-gray-600 font-medium">To Date (Ha)</label>
                                        <input type="number" v-model="editForm.to_date_ha" step="0.01" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-right font-medium focus:ring-2 focus:ring-[#34554a]">
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 items-center">
                                        <label class="text-sm text-gray-600 font-medium">Total Budget (RM)</label>
                                        <input type="number" v-model="editForm.total_budget" step="0.01" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-right font-medium focus:ring-2 focus:ring-[#34554a]">
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 items-center">
                                        <label class="text-sm text-gray-600 font-medium">YTD Claim (RM)</label>
                                        <input type="number" v-model="editForm.ytd_claim" step="0.01" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-right font-medium focus:ring-2 focus:ring-[#34554a]">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Block Level Data Section -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                                <h4 class="text-lg font-semibold text-gray-700">Block Level Data</h4>
                                <button type="button" @click="addBlock" class="text-sm text-[#34554a] hover:text-[#2a443b] font-medium flex items-center gap-1">
                                    <Plus class="w-4 h-4" /> Add Block
                                </button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left whitespace-nowrap">
                                    <thead class="bg-[#34554a] text-white text-sm">
                                        <tr>
                                            <th class="px-4 py-3 font-semibold">Block</th>
                                            <th class="px-4 py-3 font-semibold text-right">Area (Ha)</th>
                                            <th class="px-4 py-3 font-semibold text-right">Actual (Ha)</th>
                                            <th class="px-4 py-3 font-semibold text-right">Achievement</th>
                                            <th class="px-4 py-3 font-semibold text-right bg-[#2a443b]">Rate (RM)</th>
                                            <th class="px-4 py-3 text-center font-semibold bg-[#2a443b] w-16"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                                        <tr v-if="editForm.blocks.length === 0">
                                            <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                                                No blocks added. Click "Add Block" to create one.
                                            </td>
                                        </tr>
                                        <tr v-for="(block, index) in editForm.blocks" :key="index" class="hover:bg-gray-50">
                                            <td class="px-4 py-2">
                                                <input type="text" v-model="block.block_id" placeholder="Block ID" class="w-full bg-transparent border border-gray-200 rounded px-2 py-1.5 text-sm font-bold text-gray-900 focus:ring-2 focus:ring-[#34554a]">
                                            </td>
                                            <td class="px-4 py-2">
                                                <input type="number" v-model="block.area" step="0.01" class="w-full rounded px-2 py-1.5 text-right text-sm border border-gray-200 focus:ring-2 focus:ring-[#34554a]">
                                            </td>
                                            <td class="px-4 py-2">
                                                <input type="number" v-model="block.actual" step="0.01" class="w-full rounded px-2 py-1.5 text-right text-sm border border-gray-200 focus:ring-2 focus:ring-[#34554a]">
                                            </td>
                                            <td class="px-4 py-2">
                                                <input type="number" v-model="block.achievement" step="0.01" class="w-full rounded px-2 py-1.5 text-right text-sm border border-gray-200 focus:ring-2 focus:ring-[#34554a]">
                                            </td>
                                            <td class="px-4 py-2">
                                                <input type="number" v-model="block.rate" step="0.01" class="bg-[#34554a]/5 border-[#34554a]/20 text-[#34554a] font-bold w-full rounded px-2 py-1.5 text-right text-sm focus:ring-[#34554a]">
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <button type="button" @click="removeBlock(index)" class="p-1.5 text-gray-500 hover:bg-gray-100 rounded" title="Remove">
                                                    <Trash2 class="w-4 h-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex justify-between items-center sticky bottom-0">
                            <button type="button" @click="deleteEstate" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 flex items-center gap-2 text-sm font-medium">
                                <Trash2 class="w-4 h-4" /> Delete Estate
                            </button>
                            <div class="flex gap-3">
                                <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="editForm.processing" class="px-6 py-2.5 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] disabled:opacity-50 flex items-center gap-2 text-sm font-bold shadow-md">
                                    <Loader2 v-if="editForm.processing" class="w-4 h-4 animate-spin" />
                                    <Save v-else class="w-4 h-4" />
                                    Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Herd Modal -->
            <div v-if="showHerdModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60]" @click.self="showHerdModal = false">
                <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">
                            {{ herdModalMode === 'add' ? 'Add New Herd' : 'Edit Herd' }}
                        </h3>
                        <button @click="showHerdModal = false" class="text-gray-400 hover:text-gray-600">
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                    <form @submit.prevent="saveHerd" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Herd Name</label>
                            <input
                                v-model="herdForm.name"
                                type="text"
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent"
                                placeholder="e.g., Herd A"
                            />
                            <p v-if="herdForm.errors.name" class="mt-1 text-sm text-gray-600">{{ herdForm.errors.name }}</p>
                        </div>
                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button" @click="showHerdModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" :disabled="herdForm.processing" class="px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] disabled:opacity-50 flex items-center gap-2">
                                <Loader2 v-if="herdForm.processing" class="w-4 h-4 animate-spin" />
                                <Save v-else class="w-4 h-4" />
                                {{ herdModalMode === 'add' ? 'Add Herd' : 'Save' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Estate Modal -->
            <div v-if="showEstateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60]" @click.self="showEstateModal = false">
                <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">
                            {{ estateModalMode === 'add' ? 'Add New Estate' : 'Edit Estate' }}
                        </h3>
                        <button @click="showEstateModal = false" class="text-gray-400 hover:text-gray-600">
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                    <form @submit.prevent="saveEstate" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estate Name</label>
                            <input
                                v-model="estateForm.name"
                                type="text"
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent"
                                placeholder="Enter estate name"
                            />
                            <p v-if="estateForm.errors.name" class="mt-1 text-sm text-gray-600">{{ estateForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Area (Hectares)</label>
                            <input
                                v-model="estateForm.area"
                                type="number"
                                step="0.01"
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent"
                                placeholder="Enter area in hectares"
                            />
                            <p v-if="estateForm.errors.area" class="mt-1 text-sm text-gray-600">{{ estateForm.errors.area }}</p>
                        </div>
                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button" @click="showEstateModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" :disabled="estateForm.processing" class="px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] disabled:opacity-50 flex items-center gap-2">
                                <Loader2 v-if="estateForm.processing" class="w-4 h-4 animate-spin" />
                                <Save v-else class="w-4 h-4" />
                                {{ estateModalMode === 'add' ? 'Add Estate' : 'Save' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- View Modal - Read Only Display -->
            <div v-if="showViewModal && viewingCard" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto py-4" @click.self="showViewModal = false">
                <div class="bg-gray-100 rounded-xl w-full max-w-5xl shadow-2xl my-4 max-h-[95vh] overflow-y-auto">
                    <!-- Header -->
                    <div class="bg-white px-6 py-4 border-b flex items-center justify-between sticky top-0 z-10">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">View Grazing Data</h3>
                            <p class="text-xs text-gray-500">{{ viewingCard.name }} - {{ formatMonthDisplay(selectedMonth) }}</p>
                        </div>
                        <button @click="showViewModal = false" class="text-gray-400 hover:text-gray-600 p-2">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-6 space-y-6">
                        <!-- Estate & Period Info -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h4 class="text-sm font-bold text-gray-500 mb-4">Estate & Period Information</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                                <div>
                                    <span class="block text-xs text-gray-400 mb-1">Estate Name</span>
                                    <span class="text-sm font-bold text-gray-900">{{ toTitleCase(viewingCard.name) }}</span>
                                </div>
                                <div>
                                    <span class="block text-xs text-gray-400 mb-1">Total Area</span>
                                    <span class="text-sm font-bold text-gray-900">{{ viewingCard.total_area }} Ha</span>
                                </div>
                                <div>
                                    <span class="block text-xs text-gray-400 mb-1">Herd</span>
                                    <span class="text-sm font-bold text-gray-900">{{ viewingCard.herd ? toTitleCase(viewingCard.herd) : 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="block text-xs text-gray-400 mb-1">Month</span>
                                    <span class="text-sm font-bold text-gray-900">{{ formatMonthDisplay(selectedMonth) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Coverage & Financials -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h4 class="text-lg font-semibold text-gray-700 mb-6 border-b border-gray-100 pb-2">Coverage & Financials</h4>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-4">
                                <!-- Coverage -->
                                <div class="space-y-3">
                                    <div class="flex justify-between py-2 border-b border-gray-50">
                                        <span class="text-sm text-gray-600">Allocated Area</span>
                                        <span class="text-sm font-bold text-gray-900">{{ viewingCard.allocated_area || viewingCard.total_area }} Ha</span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-50">
                                        <span class="text-sm text-gray-600">Rotation Period</span>
                                        <span class="text-sm font-bold text-gray-900">{{ viewingCard.rotation_period || 62 }} days</span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-50">
                                        <span class="text-sm text-gray-600">Days in Month</span>
                                        <span class="text-sm font-bold text-gray-900">{{ viewingCard.days_in_month || 30 }}</span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-50">
                                        <span class="text-sm text-gray-600">Current Month</span>
                                        <span class="text-sm font-bold text-gray-900">{{ viewingCard.current_month_ha || 0 }} Ha</span>
                                    </div>
                                    <div class="flex justify-between py-2">
                                        <span class="text-sm text-gray-600">Achievement</span>
                                        <span class="text-sm font-bold text-gray-900">{{ viewingCard.achievement }}%</span>
                                    </div>
                                </div>
                                <!-- Financials -->
                                <div class="space-y-3">
                                    <div class="flex justify-between py-2 border-b border-gray-50">
                                        <span class="text-sm text-gray-600">Rate per Ha</span>
                                        <span class="text-sm font-bold text-gray-900">RM {{ viewingCard.rate_per_ha || 0 }}</span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-50">
                                        <span class="text-sm text-gray-600">Deduction %</span>
                                        <span class="text-sm font-bold text-gray-900">{{ viewingCard.deduction_percent || 0 }}%</span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-50">
                                        <span class="text-sm text-gray-600">Deduction Amount</span>
                                        <span class="text-sm font-bold text-gray-900">{{ fmtMoney(viewingCard.deduction_amount || 0) }}</span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-50">
                                        <span class="text-sm text-gray-600">To Date</span>
                                        <span class="text-sm font-bold text-gray-900">{{ viewingCard.to_date_ha || 0 }} Ha</span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-50">
                                        <span class="text-sm text-gray-600">Total Budget</span>
                                        <span class="text-sm font-bold text-gray-900">{{ fmtMoney(viewingCard.total_budget || 0) }}</span>
                                    </div>
                                    <div class="flex justify-between py-2 bg-gray-50 px-3 rounded-lg">
                                        <span class="text-sm font-semibold text-gray-700">Monthly Claim</span>
                                        <span class="text-sm font-bold text-gray-900">{{ fmtMoney(viewingCard.monthly_claim) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Block Data -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="p-4 border-b border-gray-200 bg-gray-50">
                                <h4 class="text-lg font-semibold text-gray-700">Block Level Data</h4>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left">
                                    <thead class="bg-[#34554a] text-white text-sm">
                                        <tr>
                                            <th class="px-4 py-3 font-semibold">Block ID</th>
                                            <th class="px-4 py-3 font-semibold text-right">Area (Ha)</th>
                                            <th class="px-4 py-3 font-semibold text-right">Actual (Ha)</th>
                                            <th class="px-4 py-3 font-semibold text-right">Achievement</th>
                                            <th class="px-4 py-3 font-semibold text-right">Rate (RM)</th>
                                            <th class="px-4 py-3 font-semibold text-right">Total (RM)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                                        <tr v-if="!viewingCard.blocks || viewingCard.blocks.length === 0">
                                            <td colspan="6" class="px-4 py-8 text-center text-gray-400">No block data available</td>
                                        </tr>
                                        <tr v-for="block in viewingCard.blocks" :key="block.id" class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-bold text-gray-900">{{ block.block_id }}</td>
                                            <td class="px-4 py-3 text-right">{{ block.area }}</td>
                                            <td class="px-4 py-3 text-right">{{ block.actual }}</td>
                                            <td class="px-4 py-3 text-right">{{ block.achievement }}</td>
                                            <td class="px-4 py-3 text-right text-gray-900 font-bold">{{ block.rate }}</td>
                                            <td class="px-4 py-3 text-right font-bold">{{ fmtMoney(block.achievement * block.rate) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex justify-between items-center">
                            <span class="text-xs text-gray-400">
                                Status: <span class="font-bold text-gray-900">{{ viewingCard.status_label }}</span>
                            </span>
                            <div class="flex gap-3">
                                <button type="button" @click="showViewModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium">
                                    Close
                                </button>
                                <button type="button" @click="showViewModal = false; openEditModal($event, viewingCard)" class="px-6 py-2 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] flex items-center gap-2 text-sm font-medium">
                                    <Pencil class="w-4 h-4" /> Edit
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[70]" @click.self="showDeleteModal = false">
                <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl">
                    <div class="flex items-center justify-center mb-4">
                        <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center">
                            <Trash2 class="w-8 h-8 text-red-500" />
                        </div>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Confirm Delete</h3>
                    <p class="text-sm text-gray-600 text-center mb-6">
                        <span v-if="deleteTarget === 'bulk'">
                            Are you sure you want to delete <strong>{{ deleteItemName }}</strong>? This will also delete all related grazing data.
                        </span>
                        <span v-else-if="deleteTarget === 'estate'">
                            Are you sure you want to delete estate <strong>"{{ deleteItemName }}"</strong>? This will also delete all grazing data for this estate.
                        </span>
                        <span v-else-if="deleteTarget === 'herd'">
                            Are you sure you want to delete herd <strong>"{{ deleteItemName }}"</strong>? This will remove the herd from all grazing data records.
                        </span>
                        <span v-else>
                            Are you sure you want to delete <strong>"{{ deleteItemName }}"</strong>?
                        </span>
                    </p>
                    <p class="text-xs text-red-500 text-center mb-6 font-medium">This action cannot be undone.</p>
                    <div class="flex gap-3">
                        <button
                            type="button"
                            @click="showDeleteModal = false"
                            :disabled="isDeleting"
                            class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium disabled:opacity-50">
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="confirmDelete"
                            :disabled="isDeleting"
                            class="flex-1 px-4 py-2.5 bg-red-500 text-white rounded-lg hover:bg-red-600 font-medium disabled:opacity-50 flex items-center justify-center gap-2">
                            <Loader2 v-if="isDeleting" class="w-4 h-4 animate-spin" />
                            <Trash2 v-else class="w-4 h-4" />
                            {{ isDeleting ? 'Deleting...' : 'Delete' }}
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
