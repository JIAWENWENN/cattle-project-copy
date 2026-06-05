<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Search, Filter, Printer, RotateCcw } from 'lucide-vue-next';

const props = defineProps({
    tripRows: { type: Array, default: () => [] },
    tripGroups: { type: Object, default: () => ({}) },
    availableDates: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ date: '' }) }
});

const filterDate = ref(props.filters.date || '');

const applyFilter = () => {
    router.get('/feeding/schedule', { date: filterDate.value }, { preserveState: true });
};

const clearFilter = () => {
    filterDate.value = '';
    router.get('/feeding/schedule', {}, { preserveState: true });
};

const printRecord = () => window.print();

const fmt = (v) => {
    if (v === null || v === undefined || v === 0) return '';
    return Number(v).toLocaleString();
};

const displayDate = computed(() => {
    if (props.filters.date) {
        const d = new Date(props.filters.date);
        return `${d.getDate()}/${d.getMonth() + 1}/${d.getFullYear()}`;
    }
    return '';
});

// Build grouped rows with subtotals
const groupedRows = computed(() => {
    const result = [];
    const groups = props.tripGroups;
    for (const prefix of Object.keys(groups).sort()) {
        const trips = groups[prefix];
        trips.forEach(t => result.push({ type: 'row', ...t }));
        if (trips.length > 1) {
            const sub = { type: 'subtotal', label: 'TOTAL' };
            const fields = [
                'cattle_count', 'napier_60', 'opf_60', 'conc_60', 'bags_60', 'plan_60', 'actual_60',
                'napier_40', 'opf_40', 'conc_40', 'bags_40', 'plan_40', 'actual_40',
                'receive_total', 'planning_total', 'actual_total'
            ];
            fields.forEach(f => sub[f] = trips.reduce((s, t) => s + (t[f] || 0), 0));
            result.push(sub);
        }
    }
    return result;
});

// Grand total
const grandTotal = computed(() => {
    const rows = props.tripRows;
    const gt = {};
    const fields = [
        'cattle_count', 'napier_60', 'opf_60', 'conc_60', 'bags_60', 'plan_60', 'actual_60',
        'napier_40', 'opf_40', 'conc_40', 'bags_40', 'plan_40', 'actual_40',
        'receive_total', 'planning_total', 'actual_total'
    ];
    fields.forEach(f => gt[f] = rows.reduce((s, t) => s + (t[f] || 0), 0));
    return gt;
});
</script>

<template>
    <Head title="Feeding Schedule (Jadual Pemakanan)" />

    <AppLayout title="Jadual Pemakanan BL & FL" parent="Feeding" parentUrl="/feeding">
        <!-- Page Header -->
        <div class="mb-6 print:mb-2">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Jadual Pemakanan BL & FL</h1>
            <p class="text-sm text-gray-500 mt-1">Feeding Schedule — 40% / 60% Allocation Breakdown</p>
        </div>

        <!-- FILTER PANEL -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-6 print:hidden">
            <div class="flex items-center gap-2 mb-4">
                <Filter class="w-4 h-4 text-[#34554a]" />
                <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wider">Select Date</h2>
            </div>
            <div class="flex flex-col md:flex-row items-end gap-4">
                <div class="flex-1 w-full md:w-auto">
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">Date</label>
                    <input
                        v-model="filterDate"
                        type="date"
                        class="w-full px-3 py-2.5 rounded-lg border border-gray-200 text-sm focus:ring-2 focus:ring-[#34554a] outline-none bg-gray-50"
                    />
                </div>
                <div class="flex gap-2">
                    <button @click="applyFilter" class="flex items-center gap-2 bg-[#34554a] text-white px-5 py-2.5 rounded-lg font-medium hover:bg-opacity-90 text-sm shadow-sm transition-all text-nowrap">
                        <Search class="w-4 h-4" /> Apply
                    </button>
                    <button @click="clearFilter" class="flex items-center gap-2 bg-white border border-gray-200 text-gray-600 px-4 py-2.5 rounded-lg font-medium hover:bg-gray-50 text-sm transition-all">
                        <RotateCcw class="w-4 h-4" /> Clear
                    </button>
                </div>
            </div>
        </div>

        <!-- SCHEDULE TABLE -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-300 mb-5 overflow-hidden">
            <div class="bg-gray-100 p-3 border-b border-gray-200 text-center">
                <h2 class="text-base font-black text-gray-800 uppercase tracking-[0.15em]">Jadual Pemakanan BL & FL</h2>
                <p v-if="displayDate" class="text-sm font-bold text-gray-600 mt-0.5">Date: {{ displayDate }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-center border-collapse min-w-[1400px]">
                    <thead>
                        <!-- Row 1: Major column groups -->
                        <tr class="bg-[#34554a] text-white text-[10px] font-bold uppercase">
                            <th rowspan="2" class="border border-[#2a443b] py-3 px-1 w-16">Trip</th>
                            <th rowspan="2" class="border border-[#2a443b] py-3 px-1 w-14">Total<br/>Cattle</th>
                            <th colspan="6" class="border border-[#2a443b] py-2 px-1 bg-[#405f53]">60%</th>
                            <th colspan="6" class="border border-[#2a443b] py-2 px-1 bg-[#2d4a3f]">
                                40% Breeders / 60% Weaners,<br/>Fattening & Lactation
                            </th>
                            <th rowspan="2" class="border border-[#2a443b] py-3 px-1 w-16">Planning<br/>(Total)</th>
                            <th rowspan="2" class="border border-[#2a443b] py-3 px-1 w-16">Actual<br/>(Total)</th>
                            <th rowspan="2" class="border border-[#2a443b] py-3 px-1 w-16">Receive<br/>(Total)</th>
                            <th rowspan="2" class="border border-[#2a443b] py-3 px-1 w-20">Remarks</th>
                        </tr>
                        <!-- Row 2: Sub-columns -->
                        <tr class="bg-[#3d6358] text-white text-[9px] font-semibold">
                            <!-- 60% sub-columns -->
                            <th class="border border-[#2a443b] py-2 px-1 w-14">Napier</th>
                            <th class="border border-[#2a443b] py-2 px-1 w-12">OPF</th>
                            <th class="border border-[#2a443b] py-2 px-1 w-12">CON</th>
                            <th class="border border-[#2a443b] py-2 px-1 w-10">Bags</th>
                            <th class="border border-[#2a443b] py-2 px-1 w-16">Planning</th>
                            <th class="border border-[#2a443b] py-2 px-1 w-16">Actual</th>
                            <!-- 40% sub-columns -->
                            <th class="border border-[#2a443b] py-2 px-1 w-14">Napier</th>
                            <th class="border border-[#2a443b] py-2 px-1 w-12">OPF</th>
                            <th class="border border-[#2a443b] py-2 px-1 w-12">CON</th>
                            <th class="border border-[#2a443b] py-2 px-1 w-10">Bags</th>
                            <th class="border border-[#2a443b] py-2 px-1 w-16">Planning</th>
                            <th class="border border-[#2a443b] py-2 px-1 w-16">Actual</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px]">
                        <template v-for="(row, idx) in groupedRows" :key="idx">
                            <!-- Subtotal Row -->
                            <tr v-if="row.type === 'subtotal'" class="bg-gray-200 font-extrabold text-gray-800 border-t-2 border-gray-400">
                                <td class="border border-gray-300 py-1.5 px-1 text-right text-[10px]">{{ row.label }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.cattle_count) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.napier_60) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.opf_60) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.conc_60) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.bags_60) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.plan_60) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.actual_60) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.napier_40) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.opf_40) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.conc_40) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.bags_40) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.plan_40) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.actual_40) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.planning_total) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.actual_total) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1">{{ fmt(row.receive_total) }}</td>
                                <td class="border border-gray-300 py-1.5 px-1"></td>
                            </tr>
                            <!-- Normal Trip Row -->
                            <tr v-else class="hover:bg-gray-50 transition-colors">
                                <td class="border border-gray-200 py-1.5 px-1 font-bold text-gray-800">{{ row.trip_no }}</td>
                                <td class="border border-gray-200 py-1.5 px-1">{{ row.cattle_count }}</td>
                                <td class="border border-gray-200 py-1.5 px-1">{{ fmt(row.napier_60) }}</td>
                                <td class="border border-gray-200 py-1.5 px-1">{{ fmt(row.opf_60) }}</td>
                                <td class="border border-gray-200 py-1.5 px-1">{{ fmt(row.conc_60) }}</td>
                                <td class="border border-gray-200 py-1.5 px-1 text-gray-500">{{ fmt(row.bags_60) }}</td>
                                <td class="border border-gray-200 py-1.5 px-1">{{ fmt(row.plan_60) }}</td>
                                <td class="border border-gray-200 py-1.5 px-1">{{ fmt(row.actual_60) }}</td>
                                <td class="border border-gray-200 py-1.5 px-1 bg-gray-50/50">{{ fmt(row.napier_40) }}</td>
                                <td class="border border-gray-200 py-1.5 px-1 bg-gray-50/50">{{ fmt(row.opf_40) }}</td>
                                <td class="border border-gray-200 py-1.5 px-1 bg-gray-50/50">{{ fmt(row.conc_40) }}</td>
                                <td class="border border-gray-200 py-1.5 px-1 bg-gray-50/50 text-gray-500">{{ fmt(row.bags_40) }}</td>
                                <td class="border border-gray-200 py-1.5 px-1 bg-gray-50/50">{{ fmt(row.plan_40) }}</td>
                                <td class="border border-gray-200 py-1.5 px-1 bg-gray-50/50">{{ fmt(row.actual_40) }}</td>
                                <td class="border border-gray-200 py-1.5 px-1 font-semibold">{{ fmt(row.planning_total) }}</td>
                                <td class="border border-gray-200 py-1.5 px-1 font-semibold">{{ fmt(row.actual_total) }}</td>
                                <td class="border border-gray-200 py-1.5 px-1 font-semibold">{{ fmt(row.receive_total) }}</td>
                                <td class="border border-gray-200 py-1.5 px-1 text-[10px] text-gray-500">{{ row.remarks }}</td>
                            </tr>
                        </template>
                        <tr v-if="groupedRows.length === 0">
                            <td colspan="18" class="py-16 text-center text-gray-400 italic text-sm">
                                No records found. Select a date to view the schedule.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="groupedRows.length > 0">
                        <tr class="bg-[#34554a] text-white font-black text-[11px]">
                            <td class="border border-[#2a443b] py-2.5 px-1">GRAND TOTAL</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.cattle_count) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.napier_60) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.opf_60) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.conc_60) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.bags_60) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.plan_60) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.actual_60) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.napier_40) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.opf_40) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.conc_40) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.bags_40) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.plan_40) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.actual_40) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.planning_total) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.actual_total) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1">{{ fmt(grandTotal.receive_total) }}</td>
                            <td class="border border-[#2a443b] py-2.5 px-1"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="flex flex-wrap gap-3 print:hidden">
            <button @click="printRecord" class="flex items-center gap-2 bg-[#34554a] text-white px-8 py-3 rounded-lg font-bold hover:bg-opacity-90 transition-all shadow-md">
                <Printer class="w-5 h-5" /> Print Record
            </button>
            <button @click="router.get('/feeding')" class="flex items-center gap-2 bg-white border border-gray-200 text-gray-700 px-6 py-3 rounded-lg font-medium hover:bg-gray-50 transition-all">
                ← Back to Daily Record
            </button>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    .print\:hidden { display: none !important; }
    .print\:mb-2 { margin-bottom: 0.5rem !important; }
}
th { font-weight: 800; }
</style>
