<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Plus, Save, Loader2, ChevronLeft, Search } from 'lucide-vue-next';

const props = defineProps({
    operatingUnit: { type: Object, required: true },
    operatingUnits: { type: Array, default: () => [] },
    viewMode: { type: String, default: 'unit' },
});

const blockForm = useForm({ name: '', area: '' });
const phaseForms = ref({});

const showBlockForm = ref(false);
const showPhaseFormByBlock = ref({});

const selectedOperatingUnitId = ref(props.operatingUnit?.id || null);
const searchQuery = ref('');

const getPhaseForm = (blockId) => {
    if (!phaseForms.value[blockId]) phaseForms.value[blockId] = useForm({ name: '' });
    return phaseForms.value[blockId];
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

const flattenRows = (units) => {
    const rows = [];
    units.forEach((unit) => {
        const blocks = unit.blocks || [];
        if (!blocks.length) {
            rows.push({
                unitId: unit.id,
                unitName: unit.name,
                blockId: null,
                blockName: '-',
                blockArea: '-',
                phases: [],
            });
            return;
        }

        blocks.forEach((block) => {
            rows.push({
                unitId: unit.id,
                unitName: unit.name,
                blockId: block.id,
                blockName: block.name,
                blockArea: block.area,
                phases: block.phases || [],
            });
        });
    });
    return rows;
};

const targetUnits = computed(() => {
    if (props.viewMode === 'all') return props.operatingUnits || [];
    return props.operatingUnit ? [props.operatingUnit] : [];
});

const allRows = computed(() => flattenRows(targetUnits.value));

const filteredRows = computed(() => {
    let rows = allRows.value;

    if (props.viewMode === 'all' && selectedOperatingUnitId.value) {
        rows = rows.filter((row) => row.unitId === Number(selectedOperatingUnitId.value));
    }

    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return rows;

    return rows.filter((row) => {
        const phaseText = (row.phases || []).map((p) => p.name).join(' ');
        return [row.unitName, row.blockName, String(row.blockArea), phaseText]
            .join(' ')
            .toLowerCase()
            .includes(q);
    });
});

const totals = computed(() => {
    const blockSet = new Set();
    let phaseCount = 0;

    filteredRows.value.forEach((row) => {
        if (row.blockId) blockSet.add(row.blockId);
        phaseCount += row.phases?.length || 0;
    });

    return { blockCount: blockSet.size, phaseCount };
});

const onChangeOperatingUnitFilter = () => {
    if (!selectedOperatingUnitId.value) return;
    router.visit(route('pasture.show', selectedOperatingUnitId.value) + '?view=all', {
        preserveScroll: true,
        preserveState: true,
    });
};

const submitBlock = () => {
    blockForm.post(route('pasture.blocks.store', props.operatingUnit.id), {
        preserveScroll: true,
        onSuccess: () => {
            blockForm.reset();
            showBlockForm.value = false;
        },
    });
};

const submitPhase = (blockId) => {
    const form = getPhaseForm(blockId);
    form.post(route('pasture.phases.store', blockId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showPhaseFormByBlock.value[blockId] = false;
        },
    });
};
</script>

<template>
    <Head :title="`Pasture - ${toTitleCase(operatingUnit.name)}`" />
    <AppLayout :title="`Pasture - ${toTitleCase(operatingUnit.name)}`" parent="Data Management" parentUrl="/pasture/all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <Link href="/pasture/all" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-[#34554a] mb-2">
                        <ChevronLeft class="w-4 h-4" />
                        Back to Operating Units
                    </Link>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                        {{ props.viewMode === 'all' ? 'All Block Records' : toTitleCase(operatingUnit.name) }}
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ props.viewMode === 'all' ? 'View all block/phase records with filters.' : 'Manage block and phase structure in table format.' }}
                    </p>
                </div>
                <button v-if="props.viewMode !== 'all'" @click="showBlockForm = !showBlockForm" class="inline-flex items-center gap-2 px-4 py-2 bg-[#34554a] text-white rounded-lg text-sm font-medium hover:bg-[#2a443b]">
                    <Plus class="w-4 h-4" />
                    Add Block
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Total Blocks</p>
                    <p class="text-2xl font-black text-gray-900 mt-1">{{ totals.blockCount }}</p>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Total Phases</p>
                    <p class="text-2xl font-black text-gray-900 mt-1">{{ totals.phaseCount }}</p>
                </div>
            </div>

            <div v-if="props.viewMode === 'all'" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                <div class="flex flex-col md:flex-row gap-3">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                        <input v-model="searchQuery" type="text" placeholder="Search unit, block, phase..." class="w-full pl-10 pr-3 py-2 rounded-lg border border-gray-200 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <select v-model="selectedOperatingUnitId" @change="onChangeOperatingUnitFilter" class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                        <option :value="null">All Operating Units</option>
                        <option v-for="unit in operatingUnits" :key="unit.id" :value="unit.id">{{ toTitleCase(unit.name) }}</option>
                    </select>
                </div>
            </div>

            <div v-if="showBlockForm && props.viewMode !== 'all'" class="bg-white rounded-xl border border-gray-200 p-4 mb-6 shadow-sm">
                <form @submit.prevent="submitBlock" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                    <div class="md:col-span-3">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Block Name</label>
                        <input v-model="blockForm.name" type="text" required class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" placeholder="e.g. Block A" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Area (Ha)</label>
                        <input v-model="blockForm.area" type="number" step="0.01" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <button type="submit" :disabled="blockForm.processing" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-[#34554a] text-white rounded-lg text-sm font-medium hover:bg-[#2a443b] disabled:opacity-50">
                        <Loader2 v-if="blockForm.processing" class="w-4 h-4 animate-spin" />
                        <Save v-else class="w-4 h-4" />
                        Save Block
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead>
                            <tr class="bg-[#34554a] text-white text-sm">
                                <th class="p-4 font-semibold w-16">No.</th>
                                <th class="p-4 font-semibold">Operating Unit</th>
                                <th class="p-4 font-semibold">Block</th>
                                <th class="p-4 font-semibold">Block Area (Ha)</th>
                                <th class="p-4 font-semibold">Phases</th>
                                <th v-if="props.viewMode !== 'all'" class="p-4 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-sm text-gray-700">
                            <template v-if="props.viewMode === 'all'">
                                <tr v-for="(row, idx) in filteredRows" :key="`all-${idx}-${row.unitId}-${row.blockId}`" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 font-medium text-gray-900">{{ idx + 1 }}</td>
                                    <td class="p-4 font-semibold text-gray-900">{{ toTitleCase(row.unitName) }}</td>
                                    <td class="p-4 text-gray-900">{{ toTitleCase(row.blockName) }}</td>
                                    <td class="p-4 text-gray-600">{{ row.blockArea }}</td>
                                    <td class="p-4 text-gray-600">
                                        <div v-if="row.phases?.length" class="flex flex-wrap gap-1.5">
                                            <span v-for="phase in row.phases" :key="phase.id" class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded-full text-xs">{{ toTitleCase(phase.name) }}</span>
                                        </div>
                                        <span v-else class="text-gray-400">No phases</span>
                                    </td>
                                </tr>
                            </template>

                            <template v-else>
                                <template v-for="(block, blockIndex) in operatingUnit.blocks" :key="block.id">
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="p-4 font-medium text-gray-900">{{ blockIndex + 1 }}</td>
                                        <td class="p-4 font-semibold text-gray-900">{{ toTitleCase(operatingUnit.name) }}</td>
                                        <td class="p-4 text-gray-900">{{ toTitleCase(block.name) }}</td>
                                        <td class="p-4 text-gray-600">{{ block.area }}</td>
                                        <td class="p-4 text-gray-600">
                                            <div v-if="block.phases?.length" class="flex flex-wrap gap-1.5">
                                                <span v-for="phase in block.phases" :key="phase.id" class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded-full text-xs">{{ toTitleCase(phase.name) }}</span>
                                            </div>
                                            <span v-else class="text-gray-400">No phases</span>
                                        </td>
                                        <td class="p-4 text-right">
                                            <button @click="showPhaseFormByBlock[block.id] = !showPhaseFormByBlock[block.id]" class="text-xs font-medium text-[#34554a] hover:underline">+ Add Phase</button>
                                        </td>
                                    </tr>

                                    <tr v-show="showPhaseFormByBlock[block.id]" class="bg-gray-50/60">
                                        <td colspan="6" class="p-4">
                                            <form @submit.prevent="submitPhase(block.id)" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                                                <div class="md:col-span-5">
                                                    <label class="block text-xs font-semibold text-gray-500 mb-1">New Phase for {{ toTitleCase(block.name) }}</label>
                                                    <input v-model="getPhaseForm(block.id).name" type="text" required class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                                                </div>
                                                <button type="submit" :disabled="getPhaseForm(block.id).processing" class="inline-flex items-center justify-center gap-2 px-3 py-2 bg-[#34554a] text-white rounded-lg text-sm font-medium hover:bg-[#2a443b] disabled:opacity-50">
                                                    <Loader2 v-if="getPhaseForm(block.id).processing" class="w-4 h-4 animate-spin" />
                                                    <Save v-else class="w-4 h-4" />
                                                    Save Phase
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                </template>
                            </template>

                            <tr v-if="props.viewMode === 'all' ? filteredRows.length === 0 : !operatingUnit.blocks?.length">
                                <td :colspan="props.viewMode === 'all' ? 5 : 6" class="p-8 text-center text-gray-400 italic">
                                    <p class="text-sm">No records found for your filter/search.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
