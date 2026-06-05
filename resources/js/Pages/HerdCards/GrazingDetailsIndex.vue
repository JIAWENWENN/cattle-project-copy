<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import { Search, Plus, Eye, Pencil, Trash2, ChevronLeft, ChevronRight, Save, Loader2, X } from 'lucide-vue-next';

const page = usePage();

const props = defineProps({
    records: { type: Array, default: () => [] },
    operatingUnits: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 10, total: 0 }) },
});

const pasturePermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Pasture'];
    return Array.isArray(perms) ? perms : ['no-access'];
});
const hasPasturePermission = (action) => {
    if (String(page.props.auth?.user?.role || '').toLowerCase() === 'admin') return true;
    const perms = pasturePermissions.value;
    return perms.includes('full') || perms.includes(action);
};
const canCreatePasture = computed(() => hasPasturePermission('create'));
const canEditPasture = computed(() => hasPasturePermission('edit'));
const canDeletePasture = computed(() => hasPasturePermission('delete'));

const search = ref(props.filters?.search || '');
const selectedOperatingUnit = ref(props.filters?.operating_unit || '');
const selectedMonth = ref(props.filters?.month || '');
const currentPage = ref(props.pagination?.current_page || 1);

const showViewModal = ref(false);
const viewingRecord = ref(null);

const showEditModal = ref(false);
const editingRecord = ref(null);
const editForm = useForm({
    estate_id: '',
    month: '',
    allocated_area: 0,
    rotation_period: 62,
    days_in_month: 30,
    current_month_ha: 0,
    rate_per_ha: 11.09,
    deduction_percent: 0,
    deduction_amount: 0,
    to_date_ha: 0,
    total_budget: 0,
    ytd_claim: 0,
});

const showDeleteModal = ref(false);
const deletingRecord = ref(null);

const formatMonthDisplay = (month) => {
    if (!month) return '-';
    const [year, m] = String(month).split('-');
    return `${m}/${year}`;
};

const fmtMoney = (num) => `RM ${parseFloat(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const applyFilters = (page = 1) => {
    router.get(route('herd-cards.grazing-details.index'), {
        search: search.value,
        operating_unit: selectedOperatingUnit.value,
        month: selectedMonth.value,
        page,
    }, { preserveState: true, preserveScroll: true });
};

let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(1), 300);
});
watch([selectedOperatingUnit, selectedMonth], () => applyFilters(1));

const goToPage = (page) => {
    if (page < 1 || page > (props.pagination?.last_page || 1)) return;
    currentPage.value = page;
    applyFilters(page);
};

const pageNumbers = computed(() => {
    const totalPages = props.pagination?.last_page || 1;
    const current = props.pagination?.current_page || 1;
    const pages = [];

    if (totalPages <= 7) {
        for (let i = 1; i <= totalPages; i += 1) pages.push(i);
        return pages;
    }

    pages.push(1);
    if (current > 3) pages.push('...');
    for (let i = Math.max(2, current - 1); i <= Math.min(totalPages - 1, current + 1); i += 1) pages.push(i);
    if (current < totalPages - 2) pages.push('...');
    pages.push(totalPages);
    return pages;
});

const openViewModal = (record) => {
    viewingRecord.value = record;
    showViewModal.value = true;
};

const openEditModal = (record) => {
    editingRecord.value = record;
    editForm.estate_id = record.operating_unit_id;
    editForm.month = record.month;
    editForm.allocated_area = record.allocated_area;
    editForm.rotation_period = record.rotation_period;
    editForm.days_in_month = record.days_in_month;
    editForm.current_month_ha = record.current_month_ha;
    editForm.rate_per_ha = record.rate_per_ha;
    editForm.deduction_percent = record.deduction_percent;
    editForm.deduction_amount = record.deduction_amount;
    editForm.to_date_ha = record.to_date_ha;
    editForm.total_budget = record.total_budget;
    editForm.ytd_claim = record.ytd_claim;
    showEditModal.value = true;
};

const saveEdit = () => {
    if (!editingRecord.value) return;
    editForm.put(route('herd-cards.grazing-details.update', editingRecord.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingRecord.value = null;
        },
    });
};

const openDeleteModal = (record) => {
    deletingRecord.value = record;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    if (!deletingRecord.value) return;
    router.delete(route('herd-cards.grazing-details.destroy', deletingRecord.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            deletingRecord.value = null;
        },
    });
};

const summaryStart = computed(() => ((props.pagination.current_page - 1) * props.pagination.per_page) + (props.records.length ? 1 : 0));
const summaryEnd = computed(() => Math.min(props.pagination.current_page * props.pagination.per_page, props.pagination.total));
</script>

<template>
    <Head title="View Grazing Details" />
    <AppLayout title="View Grazing Details" parent="Data Management">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-8 flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Grazing Details</h1>
                    <p class="text-sm text-gray-500 mt-1">View all grazing records with filter by operating unit and month.</p>
                </div>
                <Link v-if="canCreatePasture" :href="route('herd-cards.grazing-details.create')" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg font-medium hover:bg-[#2a443b] text-sm shadow-sm">
                    <Plus class="w-4 h-4" />
                    Add Grazing Data
                </Link>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                <div class="flex flex-col md:flex-row gap-3">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                        <input v-model="search" type="text" placeholder="Search operating unit..." class="w-full pl-10 pr-3 py-2 rounded-lg border border-gray-200 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <select v-model="selectedOperatingUnit" class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                        <option value="">All Operating Units</option>
                        <option v-for="unit in operatingUnits" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                    </select>
                    <input v-model="selectedMonth" type="month" class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead>
                            <tr class="bg-[#34554a] text-white text-sm">
                                <th class="p-4 font-semibold w-16">No.</th>
                                <th class="p-4 font-semibold">Month</th>
                                <th class="p-4 font-semibold">Operating Unit</th>
                                <th class="p-4 font-semibold">Current Month (Ha)</th>
                                <th class="p-4 font-semibold">Total Budget</th>
                                <th class="p-4 font-semibold">YTD Claimed</th>
                                <th class="p-4 font-semibold">Budget Remaining</th>
                                <th class="p-4 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-sm text-gray-700">
                            <tr v-if="records.length === 0">
                                <td colspan="8" class="p-8 text-center text-gray-400 italic">No grazing records found.</td>
                            </tr>
                            <tr v-for="(record, index) in records" :key="record.id" class="hover:bg-gray-50 transition-colors">
                                <td class="p-4 font-medium text-gray-900">{{ (pagination.current_page - 1) * pagination.per_page + index + 1 }}</td>
                                <td class="p-4 text-gray-600">{{ formatMonthDisplay(record.month) }}</td>
                                <td class="p-4 font-medium text-gray-900">{{ record.operating_unit }}</td>
                                <td class="p-4 text-gray-600">{{ record.current_month_ha }}</td>
                                <td class="p-4 text-gray-900 font-semibold">{{ fmtMoney(record.total_budget) }}</td>
                                <td class="p-4 text-gray-600">{{ fmtMoney(record.ytd_claim) }}</td>
                                <td class="p-4 text-gray-900 font-semibold">{{ fmtMoney(record.budget_remaining) }}</td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button @click="openViewModal(record)" class="w-8 h-8 text-gray-400 hover:text-[#34554a] hover:bg-[#34554a]/10 rounded flex items-center justify-center" title="View">
                                            <Eye class="w-4 h-4" />
                                        </button>
                                        <button v-if="canEditPasture" @click="openEditModal(record)" class="w-8 h-8 text-gray-400 hover:text-[#34554a] hover:bg-[#34554a]/10 rounded flex items-center justify-center" title="Edit">
                                            <Pencil class="w-4 h-4" />
                                        </button>
                                        <button v-if="canDeletePasture" @click="openDeleteModal(record)" class="w-8 h-8 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded flex items-center justify-center" title="Delete">
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col md:flex-row justify-between items-center p-4 border-t border-gray-100 bg-gray-50">
                    <div class="flex items-center gap-2 mb-4 md:mb-0">
                        <button @click="goToPage((pagination.current_page || 1) - 1)" :disabled="(pagination.current_page || 1) === 1" :class="(pagination.current_page || 1) === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'" class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500">
                            <ChevronLeft class="w-4 h-4" />
                        </button>
                        <button v-for="page in pageNumbers" :key="`grazing-page-${page}`" @click="page !== '...' && goToPage(page)" :class="[page === (pagination.current_page || 1) ? 'bg-[#34554a] text-white' : 'text-gray-600 hover:bg-white', page === '...' ? 'cursor-default' : '']" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors">
                            {{ page }}
                        </button>
                        <button @click="goToPage((pagination.current_page || 1) + 1)" :disabled="(pagination.current_page || 1) === (pagination.last_page || 1) || (pagination.last_page || 1) === 0" :class="(pagination.current_page || 1) === (pagination.last_page || 1) || (pagination.last_page || 1) === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'" class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500">
                            <ChevronRight class="w-4 h-4" />
                        </button>
                    </div>
                    <div class="text-sm text-gray-500">
                        Showing <span class="font-semibold text-gray-800">{{ summaryStart }}-{{ summaryEnd }}</span> of <span class="font-semibold text-gray-800">{{ pagination.total || 0 }}</span> records
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showViewModal && viewingRecord" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto py-4" @click.self="showViewModal = false">
            <div class="bg-gray-100 rounded-xl w-full max-w-4xl shadow-2xl my-4 max-h-[95vh] overflow-y-auto">
                <div class="bg-white px-6 py-4 border-b flex items-center justify-between sticky top-0 z-10">
                    <h3 class="text-lg font-bold text-gray-900">View Grazing Data</h3>
                    <button @click="showViewModal = false" class="text-gray-400 hover:text-gray-600 p-2"><X class="w-5 h-5" /></button>
                </div>
                <div class="p-6 space-y-6">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h4 class="text-lg font-semibold text-gray-700 mb-6 border-b border-gray-100 pb-2">Coverage & Financials</h4>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-4">
                            <div class="space-y-3">
                                <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-600">Operating Unit</span><span class="text-sm font-bold text-gray-900">{{ viewingRecord.operating_unit }}</span></div>
                                <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-600">Month</span><span class="text-sm font-bold text-gray-900">{{ formatMonthDisplay(viewingRecord.month) }}</span></div>
                                <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-600">Allocated Area</span><span class="text-sm font-bold text-gray-900">{{ viewingRecord.allocated_area }} Ha</span></div>
                                <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-600">Rotation Period</span><span class="text-sm font-bold text-gray-900">{{ viewingRecord.rotation_period }} days</span></div>
                                <div class="flex justify-between py-2"><span class="text-sm text-gray-600">Current Month</span><span class="text-sm font-bold text-gray-900">{{ viewingRecord.current_month_ha }} Ha</span></div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-600">Rate per Ha</span><span class="text-sm font-bold text-gray-900">{{ fmtMoney(viewingRecord.rate_per_ha) }}</span></div>
                                <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-600">Deduction %</span><span class="text-sm font-bold text-gray-900">{{ viewingRecord.deduction_percent }}%</span></div>
                                <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-600">Deduction Amount</span><span class="text-sm font-bold text-gray-900">{{ fmtMoney(viewingRecord.deduction_amount) }}</span></div>
                                <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-600">Total Budget</span><span class="text-sm font-bold text-gray-900">{{ fmtMoney(viewingRecord.total_budget) }}</span></div>
                                <div class="flex justify-between py-2 bg-gray-50 px-3 rounded-lg"><span class="text-sm font-semibold text-gray-700">Budget Remaining</span><span class="text-sm font-bold text-gray-900">{{ fmtMoney(viewingRecord.budget_remaining) }}</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showEditModal && editingRecord" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showEditModal = false">
            <div class="bg-white rounded-xl p-6 w-full max-w-3xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Edit Grazing Data</h3>
                    <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="saveEdit" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Operating Unit</label>
                            <select v-model="editForm.estate_id" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                                <option v-for="unit in operatingUnits" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Month</label>
                            <input v-model="editForm.month" type="month" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div><label class="block text-xs font-bold text-gray-500 mb-1">Allocated Area</label><input v-model="editForm.allocated_area" type="number" step="0.01" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" /></div>
                        <div><label class="block text-xs font-bold text-gray-500 mb-1">Rotation Period</label><input v-model="editForm.rotation_period" type="number" min="1" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" /></div>
                        <div><label class="block text-xs font-bold text-gray-500 mb-1">Days in Month</label><input v-model="editForm.days_in_month" type="number" min="1" max="31" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" /></div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div><label class="block text-xs font-bold text-gray-500 mb-1">Current Month (Ha)</label><input v-model="editForm.current_month_ha" type="number" step="0.01" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" /></div>
                        <div><label class="block text-xs font-bold text-gray-500 mb-1">Rate per Ha</label><input v-model="editForm.rate_per_ha" type="number" step="0.01" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" /></div>
                        <div><label class="block text-xs font-bold text-gray-500 mb-1">To Date (Ha)</label><input v-model="editForm.to_date_ha" type="number" step="0.01" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" /></div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div><label class="block text-xs font-bold text-gray-500 mb-1">Deduction %</label><input v-model="editForm.deduction_percent" type="number" step="0.01" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" /></div>
                        <div><label class="block text-xs font-bold text-gray-500 mb-1">Deduction Amount</label><input v-model="editForm.deduction_amount" type="number" step="0.01" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" /></div>
                        <div><label class="block text-xs font-bold text-gray-500 mb-1">Total Budget</label><input v-model="editForm.total_budget" type="number" step="0.01" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" /></div>
                        <div><label class="block text-xs font-bold text-gray-500 mb-1">YTD Claimed</label><input v-model="editForm.ytd_claim" type="number" step="0.01" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" /></div>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" :disabled="editForm.processing" class="px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] disabled:opacity-50 flex items-center gap-2">
                            <Loader2 v-if="editForm.processing" class="w-4 h-4 animate-spin" />
                            <Save v-else class="w-4 h-4" />
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteModal"
            title="Delete Grazing Record"
            message="Are you sure you want to delete this grazing record"
            :item-name="deletingRecord ? `${deletingRecord.operating_unit} (${formatMonthDisplay(deletingRecord.month)})` : 'this record'"
            @close="showDeleteModal = false; deletingRecord = null"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
