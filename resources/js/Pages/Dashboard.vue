<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { 
    LayoutDashboard, Users, Activity, TrendingUp,
    Beef, HeartPulse, AlertTriangle, Calendar
} from 'lucide-vue-next';

defineOptions({ layout: AppLayout });

const page = usePage();
const user = computed(() => page.props.auth?.user);
</script>

<template>
    <div class="max-w-7xl mx-auto">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">Welcome back, {{ user?.name || 'User' }}!</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Cattle</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">--</p>
                    </div>
                    <div class="w-12 h-12 bg-[#34554a]/10 rounded-xl flex items-center justify-center">
                        <Beef class="w-6 h-6 text-[#34554a]" />
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Active Cases</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">--</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                        <Activity class="w-6 h-6 text-blue-600" />
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Health Alerts</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">--</p>
                    </div>
                    <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center">
                        <AlertTriangle class="w-6 h-6 text-amber-600" />
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Mortality Rate</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">--%</p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                        <HeartPulse class="w-6 h-6 text-red-600" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Quick Actions</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="/cattle/create" class="flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:border-[#34554a] hover:bg-[#34554a]/5 transition-colors">
                    <Beef class="w-8 h-8 text-[#34554a] mb-2" />
                    <span class="text-sm font-medium text-gray-700">Add Cattle</span>
                </a>
                <a href="/mortality/create" class="flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:border-[#34554a] hover:bg-[#34554a]/5 transition-colors">
                    <AlertTriangle class="w-8 h-8 text-[#34554a] mb-2" />
                    <span class="text-sm font-medium text-gray-700">Report Mortality</span>
                </a>
                <a href="/health/create" class="flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:border-[#34554a] hover:bg-[#34554a]/5 transition-colors">
                    <HeartPulse class="w-8 h-8 text-[#34554a] mb-2" />
                    <span class="text-sm font-medium text-gray-700">Health Record</span>
                </a>
                <a href="/reports" class="flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:border-[#34554a] hover:bg-[#34554a]/5 transition-colors">
                    <TrendingUp class="w-8 h-8 text-[#34554a] mb-2" />
                    <span class="text-sm font-medium text-gray-700">View Reports</span>
                </a>
            </div>
        </div>
    </div>
</template>
