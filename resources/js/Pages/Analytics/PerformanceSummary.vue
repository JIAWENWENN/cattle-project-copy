<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { Search, FilterX, ChevronLeft, ChevronRight } from 'lucide-vue-next';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Cattle Performance Summary', parent: 'Analytics', parentUrl: '/analytics' }, () => page)
});

const page = usePage();
const rows = computed(() => page.props.rows || []);
const totals = computed(() => page.props.totals || {});
const categoryCodes = computed(() => page.props.categoryCodes || []);
const operatingUnits = computed(() => page.props.operatingUnits || []);
const filters = computed(() => page.props.filters || {});
const meta = computed(() => page.props.meta || {});

const searchQuery = ref('');
const herdFilter = ref(filters.value.herd || filters.value.operating_unit || '');

const currentPage = ref(1);
const itemsPerPage = 10;

const now = new Date();

const months = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

const years = computed(() => {
    const currentYear = new Date().getFullYear();
    return Array.from({ length: currentYear - 2020 + 1 }, (_, i) => 2020 + i);
});

const selectedMonth = ref(Number(filters.value.month || meta.value?.month || now.getMonth() + 1));
const selectedYear = ref(Number(filters.value.year || meta.value?.year || now.getFullYear()));

const filteredRows = computed(() => {
    let data = [...rows.value];
    const query = searchQuery.value.trim().toLowerCase();
    if (query) {
        data = data.filter(row => 
            String(row.herd || '').toLowerCase().includes(query)
            || String(row.operating_unit || '').toLowerCase().includes(query)
        );
    }
    return data;
});

const totalPages = computed(() => Math.ceil(filteredRows.value.length / itemsPerPage));

const paginatedRows = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredRows.value.slice(start, start + itemsPerPage);
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

watch(filteredRows, () => {
    if (currentPage.value > totalPages.value) currentPage.value = Math.max(1, totalPages.value);
});

const goToPage = (pageNum) => {
    if (pageNum >= 1 && pageNum <= totalPages.value) currentPage.value = pageNum;
};

const applyFilters = () => {
    router.get('/analytics/performance-summary', {
        month: selectedMonth.value,
        year: selectedYear.value,
        operating_unit: herdFilter.value || '',
    }, { preserveState: true, preserveScroll: true });
};

const clearFilters = () => {
    searchQuery.value = '';
    herdFilter.value = '';
    selectedMonth.value = now.getMonth() + 1;
    selectedYear.value = now.getFullYear();
    currentPage.value = 1;
    applyFilters();
};
</script>

<template>
    <div class="w-full">
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Cattle Performance Summary</h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ meta.monthLabel }} {{ meta.yearLabel }} · Generated: {{ meta.generatedAt }}
                </p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Search Herd / Operating Unit</label>
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search herd..."
                            class="pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-[#34554a] w-48"
                        >
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Month</label>
                    <select
                        v-model="selectedMonth"
                        class="px-4 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                    >
                        <option v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Year</label>
                    <select
                        v-model="selectedYear"
                        class="px-4 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                    >
                        <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Operating Unit</label>
                    <select
                        v-model="herdFilter"
                        class="px-4 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                    >
                        <option value="">All Operating Units</option>
                        <option v-for="unit in operatingUnits" :key="unit.id" :value="unit.name">{{ unit.name }}</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button
                        @click="applyFilters"
                        class="px-4 py-2 bg-[#34554a] text-white rounded-lg text-sm font-medium hover:bg-[#2a443b]"
                    >
                        Apply Filter
                    </button>
                    <button
                        @click="clearFilters"
                        class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 flex items-center gap-1"
                    >
                        <FilterX class="w-4 h-4" />
                        Clear
                    </button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-sm">
                            <th class="p-4 font-semibold text-center">No.</th>
                            <th class="p-4 font-semibold">Operating Unit</th>
                            <th class="p-4 font-semibold text-center">Opening Bal<br>Book (Hd)</th>
                            <th class="p-4 font-semibold text-center">Calving<br>(Hd)</th>
                            <th class="p-4 font-semibold text-center">Mortality<br>(Hd)</th>
                            <th class="p-4 font-semibold text-center">Transfer<br>In (Hd)</th>
                            <th class="p-4 font-semibold text-center">Transfer<br>Out (Hd)</th>
                            <th class="p-4 font-semibold text-center">Sales<br>(Hd)</th>
                            <th class="p-4 font-semibold text-center">Purchased<br>(Hd)</th>
                            <th class="p-4 font-semibold text-center">Missing<br>(Hd)</th>
                            <th class="p-4 font-semibold text-center">Recovered<br>(Hd)</th>
                            <th class="p-4 font-semibold text-center">Closing Bal<br>Book (Hd)</th>
                            <th class="p-4 font-semibold text-center">Physical<br>Count (Hd)</th>
                            <th class="p-4 font-semibold text-center">Difference<br>(Hd)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-sm text-gray-700">
                        <tr v-for="(row, index) in paginatedRows" :key="index" class="hover:bg-gray-50">
                            <td class="p-4 text-center font-medium">{{ (currentPage - 1) * itemsPerPage + index + 1 }}</td>
                            <td class="p-4 font-medium">{{ row.operating_unit || '-' }}</td>
                            <td class="p-4 text-center">{{ row.opening.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ row.calving.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ row.mortality.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ row.transfer_in.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ row.transfer_out.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ row.sale.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ row.purchased.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ row.missing.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ row.recovered.TOTAL || 0 }}</td>
                            <td class="p-4 text-center font-medium">{{ row.closing.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ row.physical_count.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ row.difference.TOTAL || 0 }}</td>
                        </tr>
                        <tr v-if="paginatedRows.length === 0">
                            <td colspan="14" class="p-8 text-center text-gray-400 italic">No records found</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="totals" class="bg-gray-50 font-semibold">
                        <tr>
                            <td colspan="2" class="p-4 text-right">TOTAL</td>
                            <td class="p-4 text-center">{{ totals.opening?.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ totals.calving?.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ totals.mortality?.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ totals.transfer_in?.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ totals.transfer_out?.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ totals.sale?.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ totals.purchased?.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ totals.missing?.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ totals.recovered?.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ totals.closing?.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ totals.physical_count?.TOTAL || 0 }}</td>
                            <td class="p-4 text-center">{{ totals.difference?.TOTAL || 0 }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t bg-gray-50 flex items-center justify-between">
                <p class="text-sm text-gray-500 font-medium">
                    Showing {{ filteredRows.length ? ((currentPage - 1) * itemsPerPage) + 1 : 0 }} to {{ filteredRows.length ? Math.min(currentPage * itemsPerPage, filteredRows.length) : 0 }} of {{ filteredRows.length }} records
                </p>
                <div class="flex gap-2">
                    <button
                        @click="goToPage(currentPage - 1)"
                        :disabled="currentPage === 1"
                        class="w-8 h-8 flex items-center justify-center border rounded-lg bg-white disabled:opacity-50"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                    <button
                        v-for="pageNum in pageNumbers"
                        :key="pageNum"
                        @click="goToPage(pageNum)"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium"
                        :class="pageNum === currentPage ? 'bg-[#34554a] text-white' : 'border bg-white text-gray-600 hover:bg-gray-50'"
                        :disabled="pageNum === '...'"
                    >
                        {{ pageNum }}
                    </button>
                    <button
                        @click="goToPage(currentPage + 1)"
                        :disabled="currentPage === totalPages"
                        class="w-8 h-8 flex items-center justify-center border rounded-lg bg-white disabled:opacity-50"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>