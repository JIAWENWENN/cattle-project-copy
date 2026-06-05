<script setup>
import { ref, computed } from 'vue';
import { router, Link, Head, usePage } from '@inertiajs/vue3';
import {
    Activity, Beef, CircleAlert, DollarSign,
    Eye, Plus, Search, Trash2, ChevronLeft, ChevronRight, Download, FileSpreadsheet, FileText, File
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import CattleForm from './CattleForm.vue';

// Props from Laravel Controller
const props = defineProps({
    cattles: Array,
    customFields: Object,
    operatingUnits: {
        type: Array,
        default: () => []
    }
});

const page = usePage();

const cattlePermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Cattle Directory'];
    return Array.isArray(perms) ? perms : ['no-access'];
});

const canCreateCattle = computed(() => {
    if (String(page.props.auth?.user?.role || '').toLowerCase() === 'admin') return true;
    const perms = cattlePermissions.value;
    return perms.includes('full') || perms.includes('create');
});

const canDeleteCattle = computed(() => {
    if (String(page.props.auth?.user?.role || '').toLowerCase() === 'admin') return true;
    const perms = cattlePermissions.value;
    return perms.includes('full') || perms.includes('delete');
});

const search = ref('');
const filterCategory = ref('');
const filterStatus = ref('');
const showAddModal = ref(false);
const showDeleteModal = ref(false);
const showExportDropdown = ref(false);
const currentPage = ref(1);
const itemsPerPage = 10;
const cattleToDelete = ref(null);

const stats = computed(() => {
    return {
        total: props.cattles.length,
        active: props.cattles.filter(c => c.status === 'Active').length,
        deceased: props.cattles.filter(c => c.status === 'Deceased').length,
        sold: props.cattles.filter(c => c.status === 'Sold').length,
    };
});

const filteredCattles = computed(() => {
    let data = props.cattles;
    if (search.value) {
        const q = search.value.toLowerCase();
        data = data.filter(c =>
            c.tag_no.toLowerCase().includes(q) ||
            (c.category && c.category.toLowerCase().includes(q)) ||
            (c.location_block && c.location_block.toLowerCase().includes(q)) ||
            (c.location_phase && c.location_phase.toLowerCase().includes(q))
        );
    }
    if (filterCategory.value) data = data.filter(c => c.category === filterCategory.value);
    if (filterStatus.value) data = data.filter(c => c.status === filterStatus.value);
    return data;
});

const totalPages = computed(() => {
    return Math.ceil(filteredCattles.value.length / itemsPerPage);
});

const paginatedCattles = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredCattles.value.slice(start, end);
});

const getStatusTextClass = (status) => {
    const normalized = String(status || '').toLowerCase();
    if (normalized === 'active') return 'text-[#0D4715]';
    if (normalized === 'deceased') return 'text-[#A82323]';
    return 'text-gray-900';
};

const getDisplayLocation = (cattle) => {
    return cattle.location_block || cattle.operating_unit || 'N/A';
};

const getDisplayPhase = (cattle) => {
    return cattle.location_phase || 'N/A';
};

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

const categories = computed(() => {
    const fromCustomFields = (props.customFields?.category || [])
        .map(opt => opt.value)
        .filter(Boolean);

    if (fromCustomFields.length > 0) {
        return [...new Set(fromCustomFields)];
    }

    return [...new Set(props.cattles.map(c => c.category).filter(Boolean))];
});

const statuses = computed(() => {
    const fromCustomFields = (props.customFields?.status || [])
        .map(opt => opt.value)
        .filter(Boolean);

    if (fromCustomFields.length > 0) {
        return [...new Set(fromCustomFields)];
    }

    return [...new Set(props.cattles.map(c => c.status).filter(Boolean))];
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

const deleteCattle = (cattle) => {
    if (!canDeleteCattle.value) return;

    cattleToDelete.value = cattle;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    if (!cattleToDelete.value || !canDeleteCattle.value) return;

    router.delete(route('cattle.destroy', cattleToDelete.value.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
            cattleToDelete.value = null;
        },
        onError: () => {
            showDeleteModal.value = false;
            cattleToDelete.value = null;
        }
    });
};

const viewCattle = (id) => {
    router.visit(route('cattle.show', id));
};

const exportData = (format) => {
    showExportDropdown.value = false;
    
    const params = new URLSearchParams({
        search: search.value,
        category: filterCategory.value,
        status: filterStatus.value,
        format: format
    });
    
    // Use direct URL since Ziggy might have issues
    window.location.href = '/export-cattle?' + params.toString();
};

const openAddModal = () => {
    if (!canCreateCattle.value) return;
    console.log('Opening add modal');
    showAddModal.value = true;
};
</script>

<template>
    <Head title="Cattle Directory" />

    <AppLayout
        title="Cattle Management"
        parent="Dashboard"
        :parentUrl="route('dashboard')"
    >
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Cattle Directory</h1>
                    <p class="text-sm text-gray-500 mt-1">Central database for all livestock units.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative z-50">
                        <button
                            @click="showExportDropdown = !showExportDropdown"
                            class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                            <Download class="w-4 h-4" />
                            <span>Export</span>
                            <ChevronRight class="w-3 h-3 ml-1" :class="showExportDropdown ? 'rotate-90' : ''" />
                        </button>

                        <div
                            v-if="showExportDropdown"
                            class="absolute top-full right-0 mt-2 w-56 bg-white rounded-xl shadow-2xl border border-gray-200 py-2 overflow-hidden"
                            style="z-index: 100;">
                            <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Select Format</p>
                            </div>
                            <button
                                @click="exportData('csv')"
                                class="w-full flex items-center gap-3 px-4 py-3 text-sm text-gray-700 hover:bg-green-50 hover:text-green-700 transition-all border-b border-gray-50">
                                <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                                    <FileSpreadsheet class="w-4 h-4 text-green-600" />
                                </div>
                                <div class="text-left">
                                    <p class="font-medium">Export as CSV</p>
                                    <p class="text-xs text-gray-400">Comma separated values</p>
                                </div>
                            </button>
                            <button
                                @click="exportData('xlsx')"
                                class="w-full flex items-center gap-3 px-4 py-3 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-all border-b border-gray-50">
                                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <File class="w-4 h-4 text-blue-600" />
                                </div>
                                <div class="text-left">
                                    <p class="font-medium">Export as Excel</p>
                                    <p class="text-xs text-gray-400">Microsoft Excel format</p>
                                </div>
                            </button>
                            <button
                                @click="exportData('pdf')"
                                class="w-full flex items-center gap-3 px-4 py-3 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 transition-all">
                                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                                    <FileText class="w-4 h-4 text-red-600" />
                                </div>
                                <div class="text-left">
                                    <p class="font-medium">Export as PDF</p>
                                    <p class="text-xs text-gray-400">Portable document format</p>
                                </div>
                            </button>
                        </div>

                        <div
                            v-if="showExportDropdown"
                            class="fixed inset-0 z-30"
                            @click="showExportDropdown = false">
                        </div>
                    </div>
                    <button
                        v-if="canCreateCattle"
                        @click="openAddModal"
                        type="button"
                        class="flex items-center gap-2 bg-[#34554a] text-white px-5 py-2.5 rounded-lg font-medium hover:bg-opacity-90 shadow-sm transition-colors cursor-pointer">
                        <Plus class="w-5 h-5" /> Register New Cattle
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition-shadow">
                <div class="w-14 h-14 rounded-full flex items-center justify-center bg-gray-100 text-gray-600">
                    <svg class="w-9 h-9" viewBox="0 0 150 150" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M47.68,45.28c-8.05-11-8.76-29.65-.1-37.83a.46.46,0,0,1,.75.39c-.64,10.41,8,19.66,14.08,24.8a5.53,5.53,0,0,1-.25,8.65l-6.76,5.07A5.51,5.51,0,0,1,47.68,45.28Z" fill="#fceebb" stroke="#000000" stroke-width="2" stroke-miterlimit="10"></path>
                        <path d="M102.32,45.28c8.05-11,8.76-29.65.1-37.83a.46.46,0,0,0-.75.39c.64,10.41-8,19.66-14.08,24.8a5.53,5.53,0,0,0,.25,8.65l6.76,5.07A5.51,5.51,0,0,0,102.32,45.28Z" fill="#fceebb" stroke="#000000" stroke-width="2" stroke-miterlimit="10"></path>
                        <path d="M102.39,50.21a6.47,6.47,0,0,0,2.5-2c13.33-16.51,35.17-11.69,36.4-7.66s-16,21-32.45,16.59C96.23,53.81,99.94,51.24,102.39,50.21Z" fill="#fceebb" stroke="#000000" stroke-width="3" stroke-miterlimit="10"></path>
                        <path d="M47.61,50.21a6.47,6.47,0,0,1-2.5-2C31.79,31.75,9.94,36.57,8.71,40.6s16,21,32.45,16.59C53.77,53.81,50.06,51.24,47.61,50.21Z" fill="#fceebb" stroke="#000000" stroke-width="3" stroke-miterlimit="10"></path>
                        <path d="M116.48,68c0,28.66-18.57,62.09-41.48,62.09S33.52,96.63,33.52,68,52.09,26.28,75,26.28,116.48,39.31,116.48,68Z" fill="#fceebb" stroke="#000000" stroke-width="4" stroke-miterlimit="10"></path>
                        <ellipse cx="75" cy="115.81" rx="30.3" ry="25.85" fill="#e2bda8" stroke="#000000" stroke-width="4" stroke-miterlimit="10"></ellipse>
                        <circle cx="56.3" cy="59.35" r="5.01"></circle>
                        <circle cx="93.7" cy="59.35" r="5.01"></circle>
                        <circle cx="62.82" cy="117.37" r="5.52" fill="#ffffff" stroke="#000000" stroke-width="2" stroke-miterlimit="10"></circle>
                        <circle cx="87.18" cy="117.37" r="5.52" fill="#ffffff" stroke="#000000" stroke-width="2" stroke-miterlimit="10"></circle>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Total Herd</p>
                    <p class="text-3xl font-extrabold text-gray-900">{{ stats.total }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition-shadow">
                <div class="w-14 h-14 rounded-full flex items-center justify-center bg-green-50 text-green-600">
                    <Activity class="w-7 h-7" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Active</p>
                    <p class="text-3xl font-extrabold text-gray-900">{{ stats.active }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition-shadow">
                <div class="w-14 h-14 rounded-full flex items-center justify-center bg-red-50 text-red-600">
                    <CircleAlert class="w-7 h-7" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Deceased</p>
                    <p class="text-3xl font-extrabold text-gray-900">{{ stats.deceased }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition-shadow">
                <div class="w-14 h-14 rounded-full flex items-center justify-center bg-amber-50 text-amber-600">
                    <DollarSign class="w-7 h-7" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Sold</p>
                    <p class="text-3xl font-extrabold text-gray-900">{{ stats.sold }}</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4 md:space-y-0 md:flex md:items-center md:justify-between gap-4 mb-6">
            <div class="relative flex-1 max-w-lg">
                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by Tag, Category, Location..."
                    class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#34554a] focus:border-transparent text-sm focus:outline-none">
            </div>
            <div class="flex flex-wrap gap-3">
                <select
                    v-model="filterCategory"
                    class="px-4 py-2.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-[#34554a]">
                    <option value="">All Categories</option>
                    <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                </select>
                <select
                    v-model="filterStatus"
                    class="px-4 py-2.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-[#34554a]">
                    <option value="">All Status</option>
                    <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                    <tr class="bg-[#34554a] text-white">
                        <th class="py-4 px-6 font-semibold text-sm tracking-wide">Tag Identity</th>
                        <th class="py-4 px-6 font-semibold text-sm tracking-wide">Location</th>
                        <th class="py-4 px-6 font-semibold text-sm tracking-wide">Category</th>
                        <th class="py-4 px-6 font-semibold text-sm tracking-wide">Physical</th>
                        <th class="py-4 px-6 font-semibold text-sm tracking-wide">Condition</th>
                        <th class="py-4 px-6 font-semibold text-sm tracking-wide">Status</th>
                        <th class="py-4 px-6 font-semibold text-sm tracking-wide text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                    <tr
                        v-for="cattle in paginatedCattles"
                        :key="cattle.id"
                        class="border-b border-gray-100 hover:bg-gray-50 transition-colors group">
                        <td class="py-4 px-6">
                            <div class="font-bold text-gray-900 text-base">{{ cattle.tag_no }}</div>
                        </td>
                        <td class="py-4 px-6">
                            <div class="font-medium text-gray-800">{{ getDisplayLocation(cattle) }}</div>
                            <div class="text-xs text-gray-500">
                                Phase: {{ getDisplayPhase(cattle) }}
                            </div>
                        </td>
                        <td class="py-4 px-6">
                            <div class="font-medium text-gray-800">{{ cattle.category }}</div>
                        </td>
                        <td class="py-4 px-6">
                            <div class="text-gray-900 font-medium">{{ cattle.receival_weight || 'N/A' }} kg</div>
                            <div class="text-xs text-gray-500">{{ cattle.gender }} • {{ cattle.coat_colour || 'N/A' }}</div>
                        </td>
                        <td class="py-4 px-6">
                                <span v-if="cattle.general_condition" class="text-sm font-medium text-gray-900">
                                    {{ cattle.general_condition }}
                                </span>
                            <span v-else class="text-gray-400 text-xs">N/A</span>
                        </td>
                        <td class="py-4 px-6">
                                <span class="text-sm font-medium" :class="getStatusTextClass(cattle.status)">
                                    {{ cattle.status }}
                                </span>
                        </td>
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-3 opacity-60 group-hover:opacity-100 transition-opacity">
                                <button
                                    @click.stop="viewCattle(cattle.id)"
                                    class="text-gray-400 hover:text-[#34554a] transition-colors"
                                    title="View Details">
                                    <Eye class="w-4 h-4" />
                                </button>
                                <button
                                    v-if="canDeleteCattle"
                                    @click.stop="deleteCattle(cattle)"
                                    class="text-gray-400 hover:text-red-600 transition-colors"
                                    title="Delete">
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="paginatedCattles.length === 0">
                        <td colspan="7" class="py-12 text-center text-gray-500">
                            <div class="flex flex-col items-center gap-2">
                                <Beef class="w-12 h-12 text-gray-300" />
                                <p class="text-sm font-medium">No cattle found</p>
                                <p class="text-xs">Try adjusting your search or filters</p>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div class="flex flex-col md:flex-row justify-between items-center p-4 border-t border-gray-100 bg-gray-50">
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
                    Showing <span class="font-semibold text-gray-800">{{ (currentPage - 1) * itemsPerPage + 1 }}-{{ Math.min(currentPage * itemsPerPage, filteredCattles.length) }}</span> of <span class="font-semibold text-gray-800">{{ filteredCattles.length }}</span> cattle
                </div>

            </div>
        </div>

        <!-- Modal -->
        <Modal
            :show="showAddModal"
            @close="showAddModal = false"
            title="Register New Cattle"
            maxWidth="max-w-5xl">
            <CattleForm :onClose="() => showAddModal = false" :customFields="customFields" :operatingUnits="operatingUnits" />
        </Modal>

        <DeleteConfirmationModal
            :show="showDeleteModal"
            title="Delete Cattle Record"
            message="Are you sure you want to delete"
            :item-name="cattleToDelete?.tag_no || 'this cattle record'"
            @close="showDeleteModal = false; cattleToDelete = null"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
