<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowLeft,
    Mountain,
    CheckCircle,
    TrendingUp,
    DollarSign,
    Search,
    Download,
    Eye,
    Pencil,
    ChevronLeft,
    ChevronRight,
    BarChart3,
    Map,
    X,
    Save,
    Loader2,
    Plus,
    Trash2,
    ListChecks,
    MapPin
} from 'lucide-vue-next';

const props = defineProps({
    estate: Object,
    blocks: Array,
    summary: Object,
    month: String,
    grazingData: Object,
});

// ========================================
// Estate Detail Modal State
// ========================================
const showEstateDetailModal = ref(false);
const showEstateEditModal = ref(false);
const estateForm = useForm({
    name: '',
    area: '',
    latitude: '',
    longitude: '',
    place_name: ''
});

const openEstateDetailModal = () => {
    estateForm.name = props.estate.name;
    estateForm.area = props.estate.area;
    estateForm.latitude = props.estate.latitude || '';
    estateForm.longitude = props.estate.longitude || '';
    estateForm.place_name = props.estate.place_name || '';
    showEstateDetailModal.value = true;
};

const openEstateEditModal = () => {
    showEstateDetailModal.value = false;
    estateForm.name = props.estate.name;
    estateForm.area = props.estate.area;
    estateForm.latitude = props.estate.latitude || '';
    estateForm.longitude = props.estate.longitude || '';
    estateForm.place_name = props.estate.place_name || '';
    showEstateEditModal.value = true;
};

const saveEstateDetails = () => {
    estateForm.put(route('herd-cards.estates.update', props.estate.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEstateEditModal.value = false;
        }
    });
};

const openGoogleMapsDirections = () => {
    if (props.estate.latitude && props.estate.longitude) {
        const url = `https://www.google.com/maps/dir/?api=1&destination=${props.estate.latitude},${props.estate.longitude}`;
        window.open(url, '_blank');
    }
};

const getStaticMapUrl = (lat, lng, width = 200, height = 100) => {
    if (!lat || !lng) return null;
    const zoom = width > 300 ? 14 : 12;
    return `https://static-maps.yandex.ru/1.x/?lang=en-US&ll=${lng},${lat}&z=${zoom}&size=${width},${height}&l=sat`;
};

// ========================================
// End Estate Detail Modal
// ========================================

// Bulk action mode
const bulkActionMode = ref(false);
const selectedBlocks = ref([]);
const selectAll = ref(false);

// Toggle bulk action mode
const toggleBulkActionMode = () => {
    bulkActionMode.value = !bulkActionMode.value;
    if (!bulkActionMode.value) {
        selectedBlocks.value = [];
        selectAll.value = false;
    }
};

// Toggle select all
const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedBlocks.value = props.blocks.map(b => b.id);
    } else {
        selectedBlocks.value = [];
    }
};

// Toggle single block selection
const toggleBlockSelection = (id) => {
    const index = selectedBlocks.value.indexOf(id);
    if (index > -1) {
        selectedBlocks.value.splice(index, 1);
    } else {
        selectedBlocks.value.push(id);
    }
    selectAll.value = selectedBlocks.value.length === props.blocks.length;
};

// Check if block is selected
const isSelected = (id) => selectedBlocks.value.includes(id);

// View modal state
const showViewModal = ref(false);
const viewingBlock = ref(null);

// Open view modal
const openViewModal = (block) => {
    viewingBlock.value = block;
    showViewModal.value = true;
};

// Edit block modal state
const showEditBlockModal = ref(false);
const editingBlock = ref(null);
const editBlockForm = useForm({
    id: null,
    block_id: '',
    area: 0,
    actual: 0,
    achievement: 0,
    rate: 11.09,
});

// Open edit block modal
const openEditBlockModal = (block) => {
    editingBlock.value = block;
    editBlockForm.id = block.id;
    editBlockForm.block_id = block.block_id;
    editBlockForm.area = block.area;
    editBlockForm.actual = block.rate; // 'rate' in display is 'actual' in DB
    editBlockForm.achievement = block.achievement;
    editBlockForm.rate = block.rm_per_ha;
    showEditBlockModal.value = true;
};

// Save block edit
const saveBlockEdit = () => {
    // Update the specific block in the blocks array
    const updatedBlocks = props.blocks.map(b => {
        if (b.id === editBlockForm.id) {
            return {
                id: editBlockForm.id,
                block_id: editBlockForm.block_id,
                area: editBlockForm.area,
                actual: editBlockForm.actual,
                achievement: editBlockForm.achievement,
                rate: editBlockForm.rate,
            };
        }
        return {
            id: b.id,
            block_id: b.block_id,
            area: b.area,
            actual: b.rate, // 'rate' in display is 'actual' in DB
            achievement: b.achievement,
            rate: b.rm_per_ha,
        };
    });

    router.put(route('herd-cards.update', props.estate.id), {
        name: props.estate.name,
        area: props.estate.total_area,
        herd: props.grazingData?.herd || '',
        rate_per_ha: props.grazingData?.rate_per_ha || 11.09,
        month: props.month,
        grazing_data_id: props.grazingData?.id || null,
        blocks: updatedBlocks,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showEditBlockModal.value = false;
            editingBlock.value = null;
        }
    });
};

// Delete modal state
const showDeleteModal = ref(false);
const deleteTarget = ref(null); // 'single' or 'bulk'
const deleteBlockId = ref(null);
const deleteBlockName = ref('');
const isDeleting = ref(false);

// Open delete modal for single block
const openDeleteModal = (block) => {
    deleteTarget.value = 'single';
    deleteBlockId.value = block.id;
    deleteBlockName.value = block.block_id;
    showDeleteModal.value = true;
};

// Open delete modal for bulk
const openBulkDeleteModal = () => {
    if (selectedBlocks.value.length === 0) return;
    deleteTarget.value = 'bulk';
    deleteBlockName.value = `${selectedBlocks.value.length} blocks`;
    showDeleteModal.value = true;
};

// Confirm delete
const confirmDelete = () => {
    isDeleting.value = true;

    if (deleteTarget.value === 'single') {
        // Delete single block using dedicated endpoint
        router.delete(route('herd-cards.blocks.destroy', deleteBlockId.value), {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteModal.value = false;
                isDeleting.value = false;
            },
            onError: () => {
                isDeleting.value = false;
            }
        });
    } else {
        // Bulk delete using dedicated endpoint
        router.post(route('herd-cards.blocks.bulk-destroy'), {
            ids: selectedBlocks.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteModal.value = false;
                isDeleting.value = false;
                selectedBlocks.value = [];
                selectAll.value = false;
                bulkActionMode.value = false;
            },
            onError: () => {
                isDeleting.value = false;
            }
        });
    }
};

const search = ref('');
const selectedStatus = ref('');

// Format currency
const fmtMoney = (num) => {
    return "RM " + parseFloat(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// Format month display
const formatMonthDisplay = (monthStr) => {
    if (!monthStr) return '';
    const date = new Date(monthStr + '-01');
    return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
};

// Get status badge classes
const getStatusClasses = (status) => {
    switch (status) {
        case 'excellent':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'good':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'average':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'below':
            return 'bg-red-100 text-red-800 border-red-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};

// Filter blocks
const filteredBlocks = () => {
    let result = props.blocks;

    if (search.value) {
        result = result.filter(block =>
            block.block_id.toLowerCase().includes(search.value.toLowerCase())
        );
    }

    if (selectedStatus.value) {
        result = result.filter(block => block.status === selectedStatus.value);
    }

    return result;
};
</script>

<template>
    <Head :title="estate.name + ' - Herd Details'" />
    <AppLayout :title="estate.name" parent="Herd Cards">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- Page Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <Link
                            :href="route('herd-cards.index')"
                            class="hover:text-[#34554a] transition-colors flex items-center gap-1">
                            <ArrowLeft class="w-4 h-4" />
                            Back to Herd Cards
                        </Link>
                    </div>
                    <button 
                        @click="openEstateDetailModal"
                        class="px-3 py-1.5 bg-[#34554a] text-white text-xs font-medium rounded-lg hover:bg-[#2a443b] transition-colors flex items-center gap-1"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <path d="M3 9h18"/>
                            <path d="M9 21V9"/>
                        </svg>
                        View/Edit Estate
                    </button>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight mt-2">{{ estate.name }} Herd Details</h1>
                <p class="text-sm text-gray-500 mt-1">{{ formatMonthDisplay(month) }} Performance</p>
            </div>

            <!-- Summary Stat Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <!-- Estate Info Card with Map Preview -->
                <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-5 shadow-sm col-span-1">
                    <div class="w-14 h-14 rounded-full bg-[#34554a]/10 text-[#34554a] flex items-center justify-center flex-shrink-0">
                        <MapPin class="w-7 h-7" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-gray-500 font-bold uppercase">Estate Location</p>
                        <p class="text-lg font-black text-gray-900 truncate">{{ estate.name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ estate.area }} Ha</p>
                        <div v-if="estate.latitude && estate.longitude" class="flex items-center gap-1 mt-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="10" r="3"/>
                                <path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 7 8 11.7z"/>
                            </svg>
                            <span class="text-[10px] text-green-600 font-medium">Located</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-5 shadow-sm">
                    <div class="w-14 h-14 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                        <Mountain class="w-7 h-7" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase">Total Area</p>
                        <p class="text-3xl font-black text-gray-900">{{ summary.total_area }} Ha</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-5 shadow-sm">
                    <div class="w-14 h-14 rounded-full bg-green-50 text-green-600 flex items-center justify-center">
                        <CheckCircle class="w-7 h-7" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase">Active Blocks</p>
                        <p class="text-3xl font-black text-gray-900">{{ summary.active_blocks }}</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-5 shadow-sm">
                    <div class="w-14 h-14 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <TrendingUp class="w-7 h-7" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase">Avg Achievement</p>
                        <p class="text-3xl font-black text-green-600">{{ summary.avg_achievement }}%</p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-green-100 flex items-center gap-5 shadow-sm">
                    <div class="w-14 h-14 rounded-full bg-green-50 text-green-600 flex items-center justify-center">
                        <DollarSign class="w-7 h-7" />
                    </div>
                    <div>
                        <p class="text-xs text-green-600 font-bold uppercase">Total Claim</p>
                        <p class="text-3xl font-black text-green-600">{{ fmtMoney(summary.total_claim) }}</p>
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
                            placeholder="Search blocks..."
                            class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                        />
                    </div>
                    <select
                        v-model="selectedStatus"
                        class="px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-600 text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                        <option value="">All Status</option>
                        <option value="excellent">Excellent</option>
                        <option value="good">Good</option>
                        <option value="average">Average</option>
                        <option value="below">Below Target</option>
                    </select>
                    <!-- Bulk Action Toggle -->
                    <button
                        @click="toggleBulkActionMode"
                        :class="[
                            'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                            bulkActionMode
                                ? 'bg-[#34554a] text-white'
                                : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'
                        ]">
                        <ListChecks class="w-4 h-4" />
                        Bulk Action
                    </button>
                    <!-- Bulk Delete Button (only when in bulk mode and items selected) -->
                    <button
                        v-if="bulkActionMode && selectedBlocks.length > 0"
                        @click="openBulkDeleteModal"
                        class="flex items-center gap-2 bg-red-500 text-white px-4 py-2 rounded-lg font-medium hover:bg-red-600 text-sm transition-colors">
                        <Trash2 class="w-4 h-4" />
                        Delete ({{ selectedBlocks.length }})
                    </button>
                </div>
                <div class="flex gap-2">
                    <button class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        <Download class="w-4 h-4" />
                        Export
                    </button>
                </div>
            </div>

            <!-- Block Details Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200 mb-8">
                <div class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead>
                            <tr class="bg-[#34554a] text-white text-sm">
                                <th v-if="bulkActionMode" class="p-4 w-12">
                                    <input
                                        type="checkbox"
                                        v-model="selectAll"
                                        @change="toggleSelectAll"
                                        class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                    />
                                </th>
                                <th class="p-4 font-semibold">Block</th>
                                <th class="p-4 font-semibold">Area (Ha)</th>
                                <th class="p-4 font-semibold">Rate</th>
                                <th class="p-4 font-semibold">Area Actual %</th>
                                <th class="p-4 font-semibold">Achievement</th>
                                <th class="p-4 font-semibold">RM/Ha</th>
                                <th class="p-4 font-semibold">Total (RM)</th>
                                <th class="p-4 font-semibold">Status</th>
                                <th class="p-4 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-sm text-gray-700">
                            <tr v-if="filteredBlocks().length === 0">
                                <td :colspan="bulkActionMode ? 10 : 9" class="p-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center gap-2">
                                        <Map class="w-12 h-12 text-gray-300" />
                                        <p class="font-medium">No block data found</p>
                                        <p class="text-xs">Add data through the Data Input page</p>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="block in filteredBlocks()"
                                :key="block.id"
                                :class="['hover:bg-gray-50 transition-colors', isSelected(block.id) ? 'bg-blue-50' : '']">
                                <td v-if="bulkActionMode" class="p-4">
                                    <input
                                        type="checkbox"
                                        :checked="isSelected(block.id)"
                                        @change="toggleBlockSelection(block.id)"
                                        class="w-4 h-4 rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                    />
                                </td>
                                <td class="p-4">
                                    <span class="font-bold text-gray-900">{{ block.block_id }}</span>
                                </td>
                                <td class="p-4">{{ block.area }}</td>
                                <td class="p-4">{{ block.rate.toFixed(2) }}</td>
                                <td class="p-4">
                                    <span :class="[
                                        'px-2.5 py-0.5 rounded-full text-xs font-medium border',
                                        block.area_actual_percent >= 98 ? 'bg-green-100 text-green-800 border-green-200' :
                                        block.area_actual_percent >= 95 ? 'bg-amber-100 text-amber-800 border-amber-200' :
                                        'bg-red-100 text-red-800 border-red-200'
                                    ]">
                                        {{ block.area_actual_percent }}%
                                    </span>
                                </td>
                                <td class="p-4">{{ block.achievement.toFixed(2) }}</td>
                                <td class="p-4">{{ block.rm_per_ha.toFixed(2) }}</td>
                                <td class="p-4 font-bold text-green-600">{{ block.total.toFixed(2) }}</td>
                                <td class="p-4">
                                    <span :class="['px-2.5 py-0.5 rounded-full text-xs font-medium border', getStatusClasses(block.status)]">
                                        {{ block.status_label }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-3 text-gray-400">
                                        <button @click="openViewModal(block)" class="cursor-pointer hover:text-blue-600 transition-colors" title="View">
                                            <Eye class="w-4 h-4" />
                                        </button>
                                        <button @click="openEditBlockModal(block)" class="cursor-pointer hover:text-green-600 transition-colors" title="Edit">
                                            <Pencil class="w-4 h-4" />
                                        </button>
                                        <button @click="openDeleteModal(block)" class="cursor-pointer hover:text-red-600 transition-colors" title="Delete">
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
                    <p class="text-sm text-gray-500 font-medium">Showing {{ filteredBlocks().length }} of {{ blocks.length }} records</p>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-900">
                            Grand Total: <span class="text-green-600">{{ fmtMoney(summary.total_claim) }}</span>
                        </span>
                        <div class="flex gap-2 ml-4">
                            <button disabled class="w-8 h-8 flex items-center justify-center border rounded-lg bg-white disabled:opacity-50 transition-all">
                                <ChevronLeft class="w-4 h-4" />
                            </button>
                            <button disabled class="w-8 h-8 flex items-center justify-center border rounded-lg bg-white disabled:opacity-50 transition-all">
                                <ChevronRight class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                            <BarChart3 class="w-5 h-5" />
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Performance Metrics</h3>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between border-b pb-2 border-gray-100">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Avg Achievement Rate</span>
                            <span class="text-sm font-semibold text-green-600">{{ summary.avg_achievement }}%</span>
                        </div>
                        <div class="flex justify-between border-b pb-2 border-gray-100">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Best Performing Block</span>
                            <span class="text-sm font-semibold text-gray-900">{{ summary.best_block }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Total Area Coverage</span>
                            <span class="text-sm font-semibold text-gray-900">{{ summary.total_area }} Ha</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-10 h-10 rounded-full bg-green-50 text-green-600 flex items-center justify-center">
                            <DollarSign class="w-5 h-5" />
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Financial Summary</h3>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between border-b pb-2 border-gray-100">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Total Monthly Claim</span>
                            <span class="text-sm font-semibold text-green-600">{{ fmtMoney(summary.total_claim) }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-2 border-gray-100">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Avg RM per Hectare</span>
                            <span class="text-sm font-semibold text-gray-900">RM {{ summary.avg_rm_per_ha.toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Total Achievement</span>
                            <span class="text-sm font-semibold text-gray-900">{{ summary.total_achievement.toFixed(2) }}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-10 h-10 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center">
                            <Map class="w-5 h-5" />
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Block Status</h3>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between border-b pb-2 border-gray-100">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Active Blocks</span>
                            <span class="text-sm font-semibold text-green-600">{{ summary.active_blocks }}/{{ summary.active_blocks }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-2 border-gray-100">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Excellent Performance</span>
                            <span class="text-sm font-semibold text-green-600">
                                {{ blocks.filter(b => b.status === 'excellent').length > 0 ? Math.round((blocks.filter(b => b.status === 'excellent').length / blocks.length) * 100) : 0 }}%
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Area Actual %</span>
                            <span class="text-sm font-semibold text-gray-900">{{ summary.avg_achievement }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================== -->
            <!-- Estate Detail Modal -->
            <!-- ======================================== -->
            <div v-if="showEstateDetailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showEstateDetailModal = false">
                <div class="bg-white rounded-xl p-6 w-full max-w-lg shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#34554a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <path d="M3 9h18"/>
                                <path d="M9 21V9"/>
                            </svg>
                            Estate Details
                        </h3>
                        <button @click="showEstateDetailModal = false" class="text-gray-400 hover:text-gray-600">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="space-y-4">
                        <!-- Estate Name & Area -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="text-xl font-bold text-gray-900">{{ estate.name }}</h4>
                            <p class="text-sm text-gray-500 mt-1">{{ estate.area }} Hectares</p>
                        </div>

                        <!-- Location Info -->
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                                <span class="text-xs font-semibold text-gray-700 uppercase">Location Information</span>
                            </div>
                            <div class="p-4 space-y-3">
                                <div v-if="estate.place_name" class="flex items-start gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-green-600 mt-0.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>
                                    <div>
                                        <div class="text-xs text-gray-500">Address</div>
                                        <div class="text-sm text-gray-800">{{ estate.place_name }}</div>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"/>
                                        <line x1="2" y1="12" x2="22" y2="12"/>
                                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                                    </svg>
                                    <div>
                                        <div class="text-xs text-gray-500">Coordinates</div>
                                        <div class="text-sm text-gray-800 font-mono">
                                            {{ estate.latitude ? parseFloat(estate.latitude).toFixed(7) : '-' }}, 
                                            {{ estate.longitude ? parseFloat(estate.longitude).toFixed(7) : '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Map Preview -->
                        <div v-if="estate.latitude && estate.longitude" class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 flex justify-between items-center">
                                <span class="text-xs font-semibold text-gray-700 uppercase">Map Preview</span>
                            </div>
                            <div class="relative">
                                <img 
                                    :src="getStaticMapUrl(estate.latitude, estate.longitude, 450, 200)" 
                                    :alt="estate.name + ' location'"
                                    class="w-full h-40 object-cover"
                                    @error="$event.target.style.display='none'"
                                />
                                <div class="absolute bottom-2 right-2">
                                    <button 
                                        @click="openGoogleMapsDirections"
                                        class="px-3 py-1.5 bg-[#34554a] text-white text-xs font-medium rounded-lg shadow-lg hover:bg-[#2a443b] transition-colors flex items-center gap-1"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polygon points="3 11 22 2 13 21 11 13 3 11"/>
                                        </svg>
                                        Get Directions
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-3 pt-2">
                            <button 
                                @click="openGoogleMapsDirections"
                                :disabled="!estate.latitude || !estate.longitude"
                                class="flex-1 px-4 py-2.5 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 font-medium"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="3 11 22 2 13 21 11 13 3 11"/>
                                </svg>
                                Open in Google Maps
                            </button>
                            <button 
                                @click="openEstateEditModal"
                                class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 flex items-center justify-center gap-2 font-medium"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                                Edit Estate
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================== -->
            <!-- Estate Edit Modal -->
            <!-- ======================================== -->
            <div v-if="showEstateEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showEstateEditModal = false">
                <div class="bg-white rounded-xl p-6 w-full max-w-lg shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#34554a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                            Edit Estate
                        </h3>
                        <button @click="showEstateEditModal = false" class="text-gray-400 hover:text-gray-600">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <form @submit.prevent="saveEstateDetails" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estate Name</label>
                            <input
                                v-model="estateForm.name"
                                type="text"
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent"
                                placeholder="Enter estate name"
                            />
                            <p v-if="estateForm.errors.name" class="mt-1 text-sm text-red-600">{{ estateForm.errors.name }}</p>
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
                            <p v-if="estateForm.errors.area" class="mt-1 text-sm text-red-600">{{ estateForm.errors.area }}</p>
                        </div>

                        <!-- Location Section -->
                        <div class="border-t border-gray-200 pt-4 mt-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Location (Optional)</h4>
                            
                            <div class="grid grid-cols-2 gap-4 mb-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Latitude</label>
                                    <input
                                        v-model="estateForm.latitude"
                                        type="text"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent"
                                        placeholder="e.g., 5.9788"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Longitude</label>
                                    <input
                                        v-model="estateForm.longitude"
                                        type="text"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent"
                                        placeholder="e.g., 116.0753"
                                    />
                                </div>
                            </div>
                            
                            <div v-if="estateForm.latitude && estateForm.longitude" class="mb-3">
                                <img 
                                    :src="getStaticMapUrl(estateForm.latitude, estateForm.longitude, 450, 120)" 
                                    alt="Location preview"
                                    class="w-full h-24 object-cover rounded border border-gray-200"
                                    @error="$event.target.style.display='none'"
                                />
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                                <button
                                    type="button"
                                    @click="showEstateEditModal = false"
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="estateForm.processing"
                                    class="px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] disabled:opacity-50 flex items-center gap-2">
                                    <Loader2 v-if="estateForm.processing" class="w-4 h-4 animate-spin" />
                                    <Save v-else class="w-4 h-4" />
                                    Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- View Block Modal -->
            <div v-if="showViewModal && viewingBlock" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showViewModal = false">
                <div class="bg-white rounded-xl w-full max-w-lg shadow-2xl">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Block Details</h3>
                            <p class="text-xs text-gray-500">{{ viewingBlock.block_id }}</p>
                        </div>
                        <button @click="showViewModal = false" class="text-gray-400 hover:text-gray-600 p-2">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="block text-xs text-gray-400 uppercase mb-1">Block ID</span>
                                <span class="text-sm font-bold text-gray-900">{{ viewingBlock.block_id }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-400 uppercase mb-1">Area (Ha)</span>
                                <span class="text-sm font-bold text-gray-900">{{ viewingBlock.area }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-400 uppercase mb-1">Rate</span>
                                <span class="text-sm font-bold text-gray-900">{{ viewingBlock.rate.toFixed(2) }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-400 uppercase mb-1">Area Actual %</span>
                                <span :class="[
                                    'px-2.5 py-0.5 rounded-full text-xs font-medium border inline-block',
                                    viewingBlock.area_actual_percent >= 98 ? 'bg-green-100 text-green-800 border-green-200' :
                                    viewingBlock.area_actual_percent >= 95 ? 'bg-amber-100 text-amber-800 border-amber-200' :
                                    'bg-red-100 text-red-800 border-red-200'
                                ]">
                                    {{ viewingBlock.area_actual_percent }}%
                                </span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-400 uppercase mb-1">Achievement</span>
                                <span class="text-sm font-bold text-gray-900">{{ viewingBlock.achievement.toFixed(2) }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-400 uppercase mb-1">RM/Ha</span>
                                <span class="text-sm font-bold text-gray-900">{{ viewingBlock.rm_per_ha.toFixed(2) }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-400 uppercase mb-1">Total (RM)</span>
                                <span class="text-sm font-bold text-green-600">{{ viewingBlock.total.toFixed(2) }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-400 uppercase mb-1">Status</span>
                                <span :class="['px-2.5 py-0.5 rounded-full text-xs font-medium border inline-block', getStatusClasses(viewingBlock.status)]">
                                    {{ viewingBlock.status_label }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-3 rounded-b-xl">
                        <button @click="showViewModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 text-sm font-medium">
                            Close
                        </button>
                        <button @click="showViewModal = false; openEditBlockModal(viewingBlock)" class="px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] text-sm font-medium flex items-center gap-2">
                            <Pencil class="w-4 h-4" /> Edit
                        </button>
                    </div>
                </div>
            </div>

            <!-- Edit Block Modal -->
            <div v-if="showEditBlockModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showEditBlockModal = false">
                <div class="bg-white rounded-xl w-full max-w-lg shadow-2xl">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Edit Block</h3>
                            <p class="text-xs text-gray-500">{{ editBlockForm.block_id || 'Block Details' }}</p>
                        </div>
                        <button @click="showEditBlockModal = false" class="text-gray-400 hover:text-gray-600 p-2">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <form @submit.prevent="saveBlockEdit" class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Block ID</label>
                            <input
                                v-model="editBlockForm.block_id"
                                type="text"
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent"
                                placeholder="e.g., Block A"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Area (Ha)</label>
                                <input
                                    v-model="editBlockForm.area"
                                    type="number"
                                    step="0.01"
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent text-right"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Actual (Ha)</label>
                                <input
                                    v-model="editBlockForm.actual"
                                    type="number"
                                    step="0.01"
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent text-right"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Achievement</label>
                                <input
                                    v-model="editBlockForm.achievement"
                                    type="number"
                                    step="0.01"
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent text-right"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Rate (RM/Ha)</label>
                                <input
                                    v-model="editBlockForm.rate"
                                    type="number"
                                    step="0.01"
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent text-right"
                                />
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <button type="button" @click="showEditBlockModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium">
                                Cancel
                            </button>
                            <button type="submit" :disabled="editBlockForm.processing" class="px-6 py-2 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] disabled:opacity-50 flex items-center gap-2 text-sm font-bold">
                                <Loader2 v-if="editBlockForm.processing" class="w-4 h-4 animate-spin" />
                                <Save v-else class="w-4 h-4" />
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60]" @click.self="showDeleteModal = false">
                <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl">
                    <div class="flex items-center justify-center mb-4">
                        <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center">
                            <Trash2 class="w-8 h-8 text-red-500" />
                        </div>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Confirm Delete</h3>
                    <p class="text-sm text-gray-600 text-center mb-6">
                        Are you sure you want to delete <strong>"{{ deleteBlockName }}"</strong>?
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
