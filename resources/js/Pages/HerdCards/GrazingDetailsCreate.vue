<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Save, Loader2 } from 'lucide-vue-next';

const props = defineProps({
    operatingUnits: { type: Array, default: () => [] },
});

const form = useForm({
    estate_id: '',
    month: new Date().toISOString().slice(0, 7),
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

const selectedUnit = computed(() => props.operatingUnits.find((u) => Number(u.id) === Number(form.estate_id)) || null);
const budgetRemaining = computed(() => Number(form.total_budget || 0) - Number(form.ytd_claim || 0));

watch(() => form.estate_id, (value) => {
    const unit = props.operatingUnits.find((u) => Number(u.id) === Number(value));
    if (unit) form.allocated_area = unit.area || 0;
});

const submit = () => {
    form.post(route('herd-cards.grazing-details.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Add Grazing Data" />
    <AppLayout title="Add Grazing Data" parent="Pasture" parentUrl="/pasture/all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Add Grazing Data</h1>
                    <p class="text-sm text-gray-500 mt-1">Choose operating unit and month, then add grazing and budget details.</p>
                </div>
                <Link :href="route('herd-cards.grazing-details.index')" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50">
                    <ArrowLeft class="w-4 h-4" />
                    View Grazing Details
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b border-gray-100 pb-2">Basic Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Operating Unit</label>
                            <select v-model="form.estate_id" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                                <option value="" disabled>Select operating unit</option>
                                <option v-for="unit in operatingUnits" :key="unit.id" :value="unit.id">{{ unit.name }} ({{ unit.area }} Ha)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Month</label>
                            <input v-model="form.month" type="month" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Allocated Area (Ha)</label>
                            <input v-model="form.allocated_area" type="number" step="0.01" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b border-gray-100 pb-2">Coverage Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Rotation Period</label>
                            <input v-model="form.rotation_period" type="number" min="1" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Days in Month</label>
                            <input v-model="form.days_in_month" type="number" min="1" max="31" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Current Month (Ha)</label>
                            <input v-model="form.current_month_ha" type="number" step="0.01" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Rate per Ha (RM)</label>
                            <input v-model="form.rate_per_ha" type="number" step="0.01" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b border-gray-100 pb-2">Budget Status</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Total Budget</label>
                            <div class="flex">
                                <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-gray-200 bg-gray-50 text-gray-600 text-sm">RM</span>
                                <input v-model="form.total_budget" type="number" step="0.01" required class="w-full rounded-r-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Ytd Claimed</label>
                            <div class="flex">
                                <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-gray-200 bg-gray-50 text-gray-600 text-sm">RM</span>
                                <input v-model="form.ytd_claim" type="number" step="0.01" required class="w-full rounded-r-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Budget Remaining</label>
                            <div class="h-10 rounded-lg border border-gray-200 bg-gray-50 px-3 flex items-center text-sm font-bold text-gray-900">
                                RM {{ Number(budgetRemaining).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Deduction %</label>
                            <input v-model="form.deduction_percent" type="number" step="0.01" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Deduction Amount</label>
                            <input v-model="form.deduction_amount" type="number" step="0.01" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">To Date (Ha)</label>
                            <input v-model="form.to_date_ha" type="number" step="0.01" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 bg-[#34554a] text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-[#2a443b] disabled:opacity-50">
                        <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                        <Save v-else class="w-4 h-4" />
                        Save Grazing Data
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
