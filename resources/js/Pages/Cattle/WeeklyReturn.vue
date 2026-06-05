<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { FileSignature } from 'lucide-vue-next';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
    categoryCodes: { type: Array, default: () => ['B/B', 'B/C', 'W/B', 'H', 'M/C', 'F/C'] },
    operatingUnits: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    meta: { type: Object, default: () => ({}) },
    company: { type: Object, default: () => ({}) },
    isExport: { type: Boolean, default: false },
});

const filterForm = reactive({
    month: props.filters?.month || new Date().toISOString().slice(0, 7),
    week: String(props.filters?.week || 1),
    unit: props.filters?.unit || '',
});

const weekOptions = [1, 2, 3, 4, 5];
const itemsPerPage = 10;
const currentPage = ref(1);

const groupedHeaders = computed(() => [
    { key: 'opening', label: 'Opening balance (Head)', cols: [...props.categoryCodes, 'TOTAL'] },
    { key: 'calving', label: 'Calving', cols: ['M/C', 'F/C'] },
    { key: 'mortality', label: 'Mortality (Head)', cols: [...props.categoryCodes] },
    { key: 'sale', label: 'Sale (Head)', cols: [...props.categoryCodes] },
    { key: 'transfer_in', label: 'Transfer in (Head)', cols: [...props.categoryCodes] },
    { key: 'transfer_out', label: 'Transfer out (Head)', cols: [...props.categoryCodes] },
    { key: 'closing', label: 'Closing balance (Head)', cols: [...props.categoryCodes, 'TOTAL'] },
]);

const applyFilters = () => {
    const payload = {
        mode: 'week',
        month: filterForm.month,
        week: filterForm.week,
        unit: filterForm.unit || undefined,
    };

    router.get(route('cattle.weekly-return'), payload, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const exportDocument = () => {
    if (!filterForm.unit) {
        alert('Please select Operating Unit first.');
        return;
    }

    const query = new URLSearchParams({
        mode: props.filters?.mode || 'week',
        month: props.filters?.month || filterForm.month,
        week: String(props.filters?.week || filterForm.week || 1),
        from: props.filters?.from || '',
        to: props.filters?.to || '',
        unit: filterForm.unit,
    }).toString();

    window.open(`${route('cattle.weekly-return.endorsement-form')}?${query}`, '_blank');
};

const displayRows = computed(() => props.rows || []);

const totalPages = computed(() => {
    const total = Math.ceil(displayRows.value.length / itemsPerPage);
    return total > 0 ? total : 1;
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

const currentPageRows = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return displayRows.value.slice(start, start + itemsPerPage);
});

const fixedRows = computed(() => {
    const rows = [...currentPageRows.value];
    while (rows.length < itemsPerPage) {
        rows.push({ __blank: true });
    }
    return rows;
});

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) currentPage.value = page;
};

const previousPage = () => {
    if (currentPage.value > 1) currentPage.value -= 1;
};

const nextPage = () => {
    if (currentPage.value < totalPages.value) currentPage.value += 1;
};

watch(
    () => props.rows,
    () => {
        currentPage.value = 1;
    }
);

const getVal = (bucket, code) => Number(bucket?.[code] ?? 0);

const openWorkflow = () => {
    if (!filterForm.unit) {
        alert('Please select Operating Unit first.');
        return;
    }

    router.get(route('cattle.weekly-return.workflow'), {
        mode: 'week',
        month: filterForm.month,
        week: filterForm.week,
        unit: filterForm.unit,
    });
};

</script>

<template>
    <Head title="Weekly Cattle Return" />

    <AppLayout title="Weekly Cattle Return" parent="Cattle" :parentUrl="route('cattle.index')">
        <div class="space-y-6">
            <div v-if="!isExport" class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm print:hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3">
                    <div class="xl:col-span-1">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Month</label>
                        <input v-model="filterForm.month" type="month" class="w-full rounded-md border-gray-300 text-sm" />
                    </div>

                    <div class="xl:col-span-1">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Week</label>
                        <select v-model="filterForm.week" class="w-full rounded-md border-gray-300 text-sm">
                            <option v-for="week in weekOptions" :key="week" :value="String(week)">Week {{ week }}</option>
                        </select>
                    </div>

                    <div class="xl:col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Operating Unit / Herd</label>
                        <select v-model="filterForm.unit" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">All Operating Units</option>
                            <option v-for="unit in operatingUnits" :key="unit" :value="unit">{{ unit }}</option>
                        </select>
                    </div>

                    <div class="xl:col-span-1 flex items-end gap-2">
                        <button @click="applyFilters" class="px-4 py-2 bg-[#34554a] text-white rounded-md text-sm font-semibold hover:bg-[#2e4b42]">
                            Apply Filter
                        </button>
                        <button @click="exportDocument" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Export
                        </button>
                    </div>
                </div>

                <div class="mt-4 border border-gray-200 rounded-lg p-3 bg-gray-50 flex items-center justify-between">
                    <div></div>
                    <button
                        type="button"
                        @click="openWorkflow"
                        class="inline-flex items-center gap-2 px-3 py-2 border border-[#34554a] text-[#34554a] rounded-md text-sm font-semibold hover:bg-green-50"
                    >
                        <FileSignature class="w-4 h-4" />
                        Open Workflow Page
                    </button>
                </div>
            </div>

            <div v-if="!isExport" class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
                <div class="flex items-center justify-between p-5 border-b border-gray-100 bg-white">
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 tracking-tight">Weekly Cattle Return</h1>
                        <p class="text-sm text-gray-500 mt-1">Auto-generated performance statistics by selected period.</p>
                    </div>
                    <div class="text-sm text-gray-500">Generated: {{ meta.generatedAt }}</div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-xs">
                        <thead>
                            <tr class="bg-[#34554a] text-white">
                                <th rowspan="2" class="border border-[#2e4b42] px-2 py-2">NO</th>
                                <th rowspan="2" class="border border-[#2e4b42] px-2 py-2 min-w-[160px]">HERD</th>
                                <th v-for="group in groupedHeaders" :key="group.key" :colspan="group.cols.length" class="border border-[#2e4b42] px-2 py-2 font-semibold">
                                    {{ group.label }}
                                </th>
                            </tr>
                            <tr class="bg-[#41665a] text-white">
                                <template v-for="group in groupedHeaders" :key="`${group.key}-sub-main`">
                                    <th v-for="sub in group.cols" :key="`${group.key}-main-${sub}`" class="border border-[#2e4b42] px-2 py-1.5">
                                        {{ sub }}
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in fixedRows" :key="`main-${currentPage}-${index}`" class="odd:bg-white even:bg-gray-50/60 hover:bg-green-50/50">
                                <td class="border border-gray-200 px-2 py-2 text-center">{{ index + 1 }}</td>
                                <td class="border border-gray-200 px-2 py-2 font-medium text-gray-800">{{ row.__blank ? '' : row.herd }}</td>

                                <td v-for="code in [...categoryCodes, 'TOTAL']" :key="`main-opening-${index}-${code}`" class="border border-gray-200 px-1.5 py-2 text-center">{{ row.__blank ? '' : getVal(row.opening, code) }}</td>

                                <td class="border border-gray-200 px-1.5 py-2 text-center">{{ row.__blank ? '' : getVal(row.calving, 'M/C') }}</td>
                                <td class="border border-gray-200 px-1.5 py-2 text-center">{{ row.__blank ? '' : getVal(row.calving, 'F/C') }}</td>

                                <td v-for="code in categoryCodes" :key="`main-mortality-${index}-${code}`" class="border border-gray-200 px-1.5 py-2 text-center">{{ row.__blank ? '' : getVal(row.mortality, code) }}</td>
                                <td v-for="code in categoryCodes" :key="`main-sale-${index}-${code}`" class="border border-gray-200 px-1.5 py-2 text-center">{{ row.__blank ? '' : getVal(row.sale, code) }}</td>
                                <td v-for="code in categoryCodes" :key="`main-tin-${index}-${code}`" class="border border-gray-200 px-1.5 py-2 text-center">{{ row.__blank ? '' : getVal(row.transfer_in, code) }}</td>
                                <td v-for="code in categoryCodes" :key="`main-tout-${index}-${code}`" class="border border-gray-200 px-1.5 py-2 text-center">{{ row.__blank ? '' : getVal(row.transfer_out, code) }}</td>

                                <td v-for="code in [...categoryCodes, 'TOTAL']" :key="`main-closing-${index}-${code}`" class="border border-gray-200 px-1.5 py-2 text-center font-semibold">{{ row.__blank ? '' : getVal(row.closing, code) }}</td>

                            </tr>

                            <tr class="bg-[#f1f7f4] font-bold text-gray-900">
                                <td colspan="2" class="border border-gray-300 px-2 py-2 text-left">TOTAL</td>

                                <td v-for="code in [...categoryCodes, 'TOTAL']" :key="`main-tot-opening-${code}`" class="border border-gray-300 px-1.5 py-2 text-center">{{ getVal(totals.opening, code) }}</td>

                                <td class="border border-gray-300 px-1.5 py-2 text-center">{{ getVal(totals.calving, 'M/C') }}</td>
                                <td class="border border-gray-300 px-1.5 py-2 text-center">{{ getVal(totals.calving, 'F/C') }}</td>

                                <td v-for="code in categoryCodes" :key="`main-tot-mortality-${code}`" class="border border-gray-300 px-1.5 py-2 text-center">{{ getVal(totals.mortality, code) }}</td>
                                <td v-for="code in categoryCodes" :key="`main-tot-sale-${code}`" class="border border-gray-300 px-1.5 py-2 text-center">{{ getVal(totals.sale, code) }}</td>
                                <td v-for="code in categoryCodes" :key="`main-tot-tin-${code}`" class="border border-gray-300 px-1.5 py-2 text-center">{{ getVal(totals.transfer_in, code) }}</td>
                                <td v-for="code in categoryCodes" :key="`main-tot-tout-${code}`" class="border border-gray-300 px-1.5 py-2 text-center">{{ getVal(totals.transfer_out, code) }}</td>

                                <td v-for="code in [...categoryCodes, 'TOTAL']" :key="`main-tot-closing-${code}`" class="border border-gray-300 px-1.5 py-2 text-center">{{ getVal(totals.closing, code) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 bg-gray-50">
                    <div class="text-sm text-gray-600">
                        Page {{ currentPage }} of {{ totalPages }}
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="previousPage"
                            :disabled="currentPage === 1"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-md disabled:opacity-50 disabled:cursor-not-allowed hover:bg-white"
                        >
                            Previous
                        </button>

                        <button
                            v-for="page in pageNumbers"
                            :key="`wr-page-${page}`"
                            @click="page !== '...' && goToPage(page)"
                            :disabled="page === '...'"
                            :class="[
                                page === currentPage ? 'bg-[#34554a] text-white border-[#34554a]' : 'bg-white text-gray-700 border-gray-300',
                                page === '...' ? 'cursor-default opacity-70' : 'hover:bg-gray-50'
                            ]"
                            class="px-3 py-1.5 text-sm border rounded-md"
                        >
                            {{ page }}
                        </button>

                        <button
                            @click="nextPage"
                            :disabled="currentPage === totalPages"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-md disabled:opacity-50 disabled:cursor-not-allowed hover:bg-white"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="weekly-report bg-white text-black mx-auto shadow-sm border border-gray-300">
                <div class="report-body">
                    <div class="company-header">
                        <div class="font-bold">{{ company.name }}</div>
                        <div>{{ company.subsidiary }}</div>
                        <div>{{ company.address }}</div>
                    </div>

                    <div class="meta-line"><strong>WEEKLY CATTLE RETURN FOR THE MONTH OF:</strong> {{ meta.monthLabel }}</div>
                    <div class="meta-line"><strong>DATE OF SUBMISSION:</strong> {{ meta.submissionLabel }}</div>

                    <div class="section-title">1. PERFORMANCE STATISTIC</div>

                    <div class="table-wrap">
                        <table class="performance-table">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="narrow">NO</th>
                                    <th rowspan="2" class="herd">HERD</th>
                                    <th v-for="group in groupedHeaders" :key="group.key" :colspan="group.cols.length" class="group-head">
                                        {{ group.label }}
                                    </th>
                                </tr>
                                <tr>
                                    <template v-for="group in groupedHeaders" :key="`${group.key}-sub`">
                                        <th v-for="sub in group.cols" :key="`${group.key}-${sub}`">{{ sub }}</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in fixedRows" :key="`export-${currentPage}-${index}`">
                                    <td>{{ index + 1 }}</td>
                                    <td class="text-left">{{ row.__blank ? '' : row.herd }}</td>

                                    <td v-for="code in [...categoryCodes, 'TOTAL']" :key="`opening-${index}-${code}`">{{ row.__blank ? '' : getVal(row.opening, code) }}</td>

                                    <td>{{ row.__blank ? '' : getVal(row.calving, 'M/C') }}</td>
                                    <td>{{ row.__blank ? '' : getVal(row.calving, 'F/C') }}</td>

                                    <td v-for="code in categoryCodes" :key="`mortality-${index}-${code}`">{{ row.__blank ? '' : getVal(row.mortality, code) }}</td>
                                    <td v-for="code in categoryCodes" :key="`sale-${index}-${code}`">{{ row.__blank ? '' : getVal(row.sale, code) }}</td>
                                    <td v-for="code in categoryCodes" :key="`tin-${index}-${code}`">{{ row.__blank ? '' : getVal(row.transfer_in, code) }}</td>
                                    <td v-for="code in categoryCodes" :key="`tout-${index}-${code}`">{{ row.__blank ? '' : getVal(row.transfer_out, code) }}</td>

                                    <td v-for="code in [...categoryCodes, 'TOTAL']" :key="`closing-${index}-${code}`">{{ row.__blank ? '' : getVal(row.closing, code) }}</td>
                                </tr>

                                <tr class="total-row">
                                    <td colspan="2" class="text-left">TOTAL</td>

                                    <td v-for="code in [...categoryCodes, 'TOTAL']" :key="`tot-opening-${code}`">{{ getVal(totals.opening, code) }}</td>

                                    <td>{{ getVal(totals.calving, 'M/C') }}</td>
                                    <td>{{ getVal(totals.calving, 'F/C') }}</td>

                                    <td v-for="code in categoryCodes" :key="`tot-mortality-${code}`">{{ getVal(totals.mortality, code) }}</td>
                                    <td v-for="code in categoryCodes" :key="`tot-sale-${code}`">{{ getVal(totals.sale, code) }}</td>
                                    <td v-for="code in categoryCodes" :key="`tot-tin-${code}`">{{ getVal(totals.transfer_in, code) }}</td>
                                    <td v-for="code in categoryCodes" :key="`tot-tout-${code}`">{{ getVal(totals.transfer_out, code) }}</td>

                                    <td v-for="code in [...categoryCodes, 'TOTAL']" :key="`tot-closing-${code}`">{{ getVal(totals.closing, code) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="signature-grid">
                        <div class="sig-col">
                            <p><strong>Prepared by:</strong></p>
                            <div class="sig-space"></div>
                            <p><strong>SR. ASSISTANT LIVESTOCK</strong></p>
                        </div>
                        <div class="sig-col">
                            <p><strong>Verified by:</strong></p>
                            <div class="sig-space"></div>
                            <p><strong>PENYELIA SECURITY</strong></p>
                        </div>
                        <div class="sig-col">
                            <p><strong>Checked by:</strong></p>
                            <div class="sig-space"></div>
                            <p><strong>LIVESTOCK SUPERVISOR</strong></p>
                        </div>
                        <div class="sig-col">
                            <p><strong>Approved by:</strong></p>
                            <div class="sig-space"></div>
                            <p><strong>ACT. LIVESTOCK MANAGER</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.weekly-report {
    width: 100%;
    max-width: 1180px;
    background: #fff;
}

.report-body {
    padding: 22px 24px 28px;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    color: #000;
}

.company-header {
    line-height: 1.4;
    margin-bottom: 10px;
}

.meta-line {
    margin: 3px 0;
}

.section-title {
    margin-top: 14px;
    margin-bottom: 6px;
    font-weight: 700;
    font-size: 13px;
}

.table-wrap {
    overflow-x: auto;
}

.performance-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 10px;
}

.performance-table th,
.performance-table td {
    border: 1px solid #000;
    padding: 3px 2px;
    text-align: center;
    vertical-align: middle;
}

.performance-table .narrow {
    width: 38px;
}

.performance-table .herd {
    width: 140px;
}

.performance-table .group-head {
    font-weight: 700;
}

.performance-table .text-left {
    text-align: left;
    padding-left: 6px;
}

.performance-table .total-row td {
    font-weight: 700;
}

.signature-grid {
    margin-top: 28px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.sig-col {
    min-height: 132px;
    font-size: 11px;
}

.sig-col p {
    margin: 0;
    line-height: 1.35;
}

.sig-space {
    height: 48px;
}

@media (max-width: 900px) {
    .signature-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .report-body {
        padding: 14px;
    }
}

@media print {
    :global(body) {
        background: #fff;
    }

    .weekly-report {
        border: none;
        box-shadow: none;
        max-width: none;
    }

    .report-body {
        padding: 0;
    }
}
</style>
