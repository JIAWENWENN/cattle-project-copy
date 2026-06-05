<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import {
    Beef, Baby, Skull, ClipboardCheck, AlertTriangle, Users,
    TreePine, Package, Pill, Activity, HeartPulse, ArrowLeftRight,
    TrendingUp, TrendingDown, CheckCircle, XCircle,
    Eye, ChevronRight, Plus, FileText, Download, Filter,
    RefreshCw, Bell, Search, MoreHorizontal, Truck, Send, MapPin
} from 'lucide-vue-next';

// Props from controller
const props = defineProps({
    // Cattle Stats
    cattleStats: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            sold: 0,
            deceased: 0,
            byCategory: {},
            byGender: {},
            byStatus: {},
            byCondition: {},
            byOwnership: {}
        })
    },
    // Breeding Stats
    breedingStats: {
        type: Object,
        default: () => ({
            total: 0,
            pregnant: 0,
            awaitingCalving: 0,
            byMethod: {}
        })
    },
    // Calving Stats
    calvingStats: {
        type: Object,
        default: () => ({
            total: 0,
            thisYear: 0,
            thisMonth: 0,
            bySex: {},
            recentCalvings: []
        })
    },
    // Mortality Stats
    mortalityStats: {
        type: Object,
        default: () => ({
            total: 0,
            thisMonth: 0,
            thisYear: 0,
            mortalityRate: 0,
            byCause: {},
            recentMortality: []
        })
    },
    // Health Stats
    healthStats: {
        type: Object,
        default: () => ({
            total: 0,
            pendingVaccinations: 0,
            overdueTraatments: 0,
            underObservation: 0,
            healthy: 0
        })
    },
    // Inventory Stats
    inventoryStats: {
        type: Object,
        default: () => ({
            medications: { total: 0, totalStock: 0, lowStock: 0, expiringSoon: 0, expired: 0 }
        })
    },
    // Stock Alerts
    stockAlerts: {
        type: Array,
        default: () => []
    },
    // Estates & Herds
    estatesStats: {
        type: Object,
        default: () => ({
            totalEstates: 0,
            activeEstates: 0,
            totalArea: 0,
            totalHerds: 0,
            estates: [],
            operatingUnits: []
        })
    },
    // Pending Approvals
    pendingApprovals: {
        type: Array,
        default: () => []
    },
    pendingApprovalRoles: {
        type: Array,
        default: () => []
    },
    // Activity Log
    activityLog: {
        type: Array,
        default: () => []
    },
    activitySummary: {
        type: Object,
        default: () => ({
            today: 0,
            this_week: 0,
            this_month: 0
        })
    },
    // User Stats
    userStats: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
            byRole: {},
            recentUsers: [],
            recentActivity: []
        })
    },
    // Transfer Stats
    transferStats: {
        type: Object,
        default: () => ({
            total: 0,
            pending: 0,
            completed: 0,
            thisMonth: 0,
            inTransit: 0,
            byType: {},
            recentTransfers: [],
            pendingTransfers: []
        })
    }
});

// Filters for Activity Log
const activityFilter = ref({
    user: '',
    module: '',
    action: '',
    dateRange: 'today'
});

const activityUsers = computed(() => {
    const map = new Map();
    for (const a of props.activityLog || []) {
        if (!a?.user_id) continue;
        if (!map.has(String(a.user_id))) {
            map.set(String(a.user_id), {
                id: String(a.user_id),
                name: a.user_name || 'Unknown'
            });
        }
    }
    return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name));
});

const activityModules = computed(() => {
    return [...new Set((props.activityLog || []).map(a => a?.module).filter(Boolean))].sort();
});

const activityActions = computed(() => {
    return [...new Set((props.activityLog || []).map(a => a?.action).filter(Boolean))].sort();
});

const filteredActivities = computed(() => {
    let activities = props.activityLog;

    if (activityFilter.value.dateRange) {
        const now = new Date();
        const startOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const startOfWeek = new Date(startOfDay);
        const day = startOfWeek.getDay();
        const diff = day === 0 ? 6 : day - 1;
        startOfWeek.setDate(startOfWeek.getDate() - diff);
        const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);

        activities = activities.filter((a) => {
            if (!a?.created_at) return false;
            const createdAt = new Date(a.created_at);
            if (activityFilter.value.dateRange === 'today') {
                return createdAt >= startOfDay;
            }
            if (activityFilter.value.dateRange === 'week') {
                return createdAt >= startOfWeek;
            }
            if (activityFilter.value.dateRange === 'month') {
                return createdAt >= startOfMonth;
            }
            return true;
        });
    }

    if (activityFilter.value.user) {
        activities = activities.filter(a => String(a.user_id) === String(activityFilter.value.user));
    }

    if (activityFilter.value.module) {
        activities = activities.filter(a => a.module === activityFilter.value.module);
    }

    if (activityFilter.value.action) {
        activities = activities.filter(a => a.action === activityFilter.value.action);
    }

    return activities.slice(0, 10);
});

const formatActivityDay = (date) => {
    if (!date) return '-';
    const d = new Date(date);
    const today = new Date();
    const isToday =
        d.getDate() === today.getDate() &&
        d.getMonth() === today.getMonth() &&
        d.getFullYear() === today.getFullYear();
    if (isToday) return 'Today';
    return d.toLocaleDateString('en-MY', { day: '2-digit', month: 'short' });
};

// Helper functions
const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-MY', { 
        day: '2-digit', 
        month: 'short', 
        year: 'numeric' 
    });
};

const formatTime = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleTimeString('en-MY', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
};

const getActionColor = (action) => {
    const colors = {
        'CREATE': 'bg-green-100 text-green-700',
        'UPDATE': 'bg-blue-100 text-blue-700',
        'DELETE': 'bg-red-100 text-red-700',
        'APPROVE': 'bg-emerald-100 text-emerald-700',
        'REJECT': 'bg-orange-100 text-orange-700',
        'LOGIN': 'bg-purple-100 text-purple-700',
        'LOGOUT': 'bg-gray-100 text-gray-700'
    };
    return colors[action] || 'bg-gray-100 text-gray-700';
};

const getStatusColor = (status) => {
    const colors = {
        'Pending': 'bg-amber-100 text-amber-700 border-amber-200',
        'Approved': 'bg-green-100 text-green-700 border-green-200',
        'Rejected': 'bg-red-100 text-red-700 border-red-200'
    };
    return colors[status] || 'bg-gray-100 text-gray-700 border-gray-200';
};

const operatingUnits = computed(() => props.estatesStats?.operatingUnits || []);
const operatingUnitTotalCattle = computed(() => {
    return operatingUnits.value.reduce((sum, unit) => sum + Number(unit.cattle_count || 0), 0);
});

const realtimeUserStats = ref({
    total: props.userStats?.total || 0,
    active: props.userStats?.active || 0,
    inactive: props.userStats?.inactive || 0,
    byRole: props.userStats?.byRole || {},
    recentUsers: props.userStats?.recentUsers || [],
    recentActivity: props.userStats?.recentActivity || []
});

const isRefreshing = ref(false);

const pendingApprovalFilterRole = ref('');
const pendingApprovalsPage = ref(1);
const pendingApprovalsPerPage = 10;

const pendingApprovalRoleOptions = computed(() => props.pendingApprovalRoles || []);

const filteredPendingApprovals = computed(() => {
    const rows = props.pendingApprovals || [];
    if (!pendingApprovalFilterRole.value) {
        return rows;
    }
    return rows.filter(row => row.submitted_by === pendingApprovalFilterRole.value);
});

const pendingApprovalsTotalPages = computed(() => {
    const total = filteredPendingApprovals.value.length;
    return Math.max(1, Math.ceil(total / pendingApprovalsPerPage));
});

const paginatedPendingApprovals = computed(() => {
    const start = (pendingApprovalsPage.value - 1) * pendingApprovalsPerPage;
    return filteredPendingApprovals.value.slice(start, start + pendingApprovalsPerPage);
});

const pendingApprovalsRequireAttentionText = computed(() => {
    const count = filteredPendingApprovals.value.length;
    return `${count} item${count === 1 ? '' : 's'} require your attention`;
});

const resetPendingApprovalFilters = () => {
    pendingApprovalFilterRole.value = '';
    pendingApprovalsPage.value = 1;
};

const applyPendingApprovalRoleFilter = () => {
    pendingApprovalsPage.value = 1;
};

const previousPendingApprovalsPage = () => {
    if (pendingApprovalsPage.value > 1) {
        pendingApprovalsPage.value -= 1;
    }
};

const nextPendingApprovalsPage = () => {
    if (pendingApprovalsPage.value < pendingApprovalsTotalPages.value) {
        pendingApprovalsPage.value += 1;
    }
};

const refreshUserStats = async () => {
    isRefreshing.value = true;
    try {
        const response = await fetch('/api/dashboard/user-stats', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        const data = await response.json();
        realtimeUserStats.value = data;
    } catch (error) {
        console.error('Failed to fetch user stats:', error);
    } finally {
        isRefreshing.value = false;
    }
};

onMounted(() => {
    refreshUserStats();
    setInterval(refreshUserStats, 30000);
});
</script>

<template>
    <Head title="Admin Dashboard" />

    <AppLayout title="Dashboard">
        <template #breadcrumb>Dashboard</template>

        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Admin Dashboard</h1>
                    <p class="text-sm text-gray-500 mt-1">Welcome to Sawit Kinabalu Cattle Management System</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-500">{{ new Date().toLocaleDateString('en-MY', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</span>

                    <button class="p-2 rounded-lg bg-white border border-gray-200 hover:bg-gray-50 transition-colors">
                        <RefreshCw class="w-4 h-4 text-gray-500" />
                    </button>
                </div>
            </div>
        </div>

        <!-- KPI Summary Cards - Row 1 -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <!-- Total Cattle -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-blue-50 text-blue-600">
                        <Beef class="w-5 h-5" />
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ cattleStats.total || 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Cattle</p>
            </div>


            <!-- Mortality -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-red-50 text-red-600">
                        <Skull class="w-5 h-5" />
                    </div>
                    <span class="text-xs text-gray-500 font-medium">{{ mortalityStats.mortalityRate ? mortalityStats.mortalityRate.toFixed(1) : 0 }}%</span>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ mortalityStats.thisYear || 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Mortality (Year)</p>
            </div>

            <!-- Calving -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-pink-50 text-pink-600">
                        <Baby class="w-5 h-5" />
                    </div>
                    <span class="text-xs text-pink-600 font-medium">{{ calvingStats.thisMonth || 0 }} this month</span>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ calvingStats.total || 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Calvings</p>
            </div>

            <!-- Stock Alerts -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-orange-50 text-orange-600">
                        <AlertTriangle class="w-5 h-5" />
                    </div>
                    <span class="text-xs text-orange-600 font-medium">Low</span>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ stockAlerts.length || 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Stock Alerts</p>
            </div>

            <!-- Users -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-purple-50 text-purple-600">
                        <Users class="w-5 h-5" />
                    </div>
                    <span class="text-xs text-green-600 font-medium">{{ userStats.active || 0 }} active</span>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ userStats.total || 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Users</p>
            </div>
        </div>

        <!-- KPI Summary Cards - Row 2 -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
            <!-- Operating Units -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-green-50 text-green-600">
                        <TreePine class="w-5 h-5" />
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ estatesStats.activeEstates || 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Operating Units</p>
            </div>

            <!-- Medications -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-indigo-50 text-indigo-600">
                        <Pill class="w-5 h-5" />
                    </div>
                    <span v-if="inventoryStats.medications?.lowStock > 0" class="text-xs text-amber-600 font-medium flex items-center gap-1">
                        <AlertTriangle class="w-3 h-3" />
                        {{ inventoryStats.medications.lowStock }} low
                    </span>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ inventoryStats.medications?.total || 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">
                    {{ inventoryStats.medications?.totalStock?.toLocaleString() || 0 }} units total
                </p>
                <div class="flex gap-2 mt-2">
                    <span v-if="inventoryStats.medications?.expiringSoon > 0" class="text-[10px] px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded">
                        {{ inventoryStats.medications.expiringSoon }} expiring
                    </span>
                    <span v-if="inventoryStats.medications?.expired > 0" class="text-[10px] px-1.5 py-0.5 bg-red-100 text-red-700 rounded">
                        {{ inventoryStats.medications.expired }} expired
                    </span>
                </div>
            </div>


            <!-- Health Records -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-cyan-50 text-cyan-600">
                        <Activity class="w-5 h-5" />
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ healthStats.total || 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Health Records</p>
            </div>

            <!-- Total Transfers -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-blue-50 text-blue-600">
                        <Truck class="w-5 h-5" />
                    </div>
                    <span class="text-xs text-blue-600 font-medium">Total</span>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ transferStats.total || 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Transfers</p>
            </div>
        </div>

        <!-- Main Grid: Mortality Only -->
        <div class="grid grid-cols-1 lg:grid-cols-1 gap-6 mb-8">
            <!-- Mortality Tracking -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between p-5 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center">
                            <Skull class="w-4 h-4 text-red-600" />
                        </div>
                        <h3 class="font-semibold text-gray-900">Mortality Tracking</h3>
                    </div>
                    <Link href="/mortality/create" class="text-sm text-[#34554a] font-medium hover:underline">+ Record Mortality</Link>
                </div>
                <div class="p-5">
                    <!-- Mortality Summary -->
                    <div class="grid grid-cols-2 gap-4 mb-5">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500 mb-1">Total Deaths</p>
                            <p class="text-xl font-bold text-gray-900">{{ mortalityStats.total || 0 }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500 mb-1">Mortality Rate</p>
                            <p class="text-xl font-bold text-red-600">{{ mortalityStats.mortalityRate ? mortalityStats.mortalityRate.toFixed(1) : 0 }}%</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500 mb-1">This Month</p>
                            <p class="text-xl font-bold text-gray-900">{{ mortalityStats.thisMonth || 0 }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500 mb-1">This Year</p>
                            <p class="text-xl font-bold text-gray-900">{{ mortalityStats.thisYear || 0 }}</p>
                        </div>
                    </div>

                    <!-- By Cause -->
                    <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-3">By Cause of Death</h4>
                    <div class="space-y-2 mb-4">
                        <div v-for="(count, cause) in mortalityStats.byCause" :key="cause" class="flex items-center justify-between py-1">
                            <span class="text-sm text-gray-700 capitalize">{{ cause || 'Unknown' }}</span>
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-red-500 rounded-full" :style="{ width: ((count / Math.max(mortalityStats.total, 1)) * 100) + '%' }"></div>
                                </div>
                                <span class="text-xs font-medium text-gray-600">{{ count }}</span>
                            </div>
                        </div>
                        <div v-if="!mortalityStats.byCause || Object.keys(mortalityStats.byCause).length === 0" class="text-sm text-gray-500 italic py-2">
                            No cause of death data available.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cattle Overview -->
        <div class="grid grid-cols-1 gap-6 mb-8">
            <!-- Cattle Overview -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between p-5 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                            <Beef class="w-4 h-4 text-blue-600" />
                        </div>
                        <h3 class="font-semibold text-gray-900">Cattle Overview</h3>
                    </div>
                    <Link href="/cattle" class="text-sm text-[#34554a] font-medium hover:underline">View All</Link>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-2 gap-6">
                        <!-- By Category -->
                        <div>
                            <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-3">By Category</h4>
                            <div class="space-y-2">
                                <div v-for="(count, category) in cattleStats.byCategory" :key="'cat-'+category" class="flex items-center justify-between py-1">
                                    <span class="text-sm text-gray-700 capitalize">{{ category || 'Unknown' }}</span>
                                    <span class="text-sm font-bold text-gray-900">{{ count }}</span>
                                </div>
                                <div v-if="!cattleStats.byCategory || Object.keys(cattleStats.byCategory).length === 0" class="text-sm text-gray-500 italic py-1">
                                    No data
                                </div>
                            </div>
                        </div>

                        <!-- By Status -->
                        <div>
                            <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-3">By Status</h4>
                            <div class="space-y-2">
                                <div v-for="(count, status) in cattleStats.byStatus" :key="'status-'+status" class="flex items-center justify-between py-1">
                                    <span class="text-sm text-gray-700 capitalize">{{ status || 'Unknown' }}</span>
                                    <span class="text-sm font-bold text-gray-900">{{ count }}</span>
                                </div>
                                <div v-if="!cattleStats.byStatus || Object.keys(cattleStats.byStatus).length === 0" class="text-sm text-gray-500 italic py-1">
                                    No data
                                </div>
                            </div>
                        </div>

                        <!-- By Gender -->
                        <div>
                            <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-3">By Gender</h4>
                            <div class="space-y-2">
                                <div v-for="(count, gender) in cattleStats.byGender" :key="'gender-'+gender" class="flex items-center justify-between py-1">
                                    <span class="text-sm text-gray-700 capitalize">{{ gender || 'Unknown' }}</span>
                                    <span class="text-sm font-bold text-gray-900">{{ count }}</span>
                                </div>
                                <div v-if="!cattleStats.byGender || Object.keys(cattleStats.byGender).length === 0" class="text-sm text-gray-500 italic py-1">
                                    No data
                                </div>
                            </div>
                        </div>

                        <!-- By Condition -->
                        <div>
                            <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-3">By Condition</h4>
                            <div class="space-y-2">
                                <div v-for="(count, condition) in cattleStats.byCondition" :key="'condition-'+condition" class="flex items-center justify-between py-1">
                                    <span class="text-sm text-gray-700 capitalize">{{ condition || 'Unknown' }}</span>
                                    <span class="text-sm font-bold" :class="{'text-green-600': condition === 'Good' || condition === 'Excellent', 'text-amber-600': condition === 'Fair', 'text-red-600': condition === 'Poor' || condition === 'Sick', 'text-gray-900': !['Good', 'Excellent', 'Fair', 'Poor', 'Sick'].includes(condition)}">{{ count }}</span>
                                </div>
                                <div v-if="!cattleStats.byCondition || Object.keys(cattleStats.byCondition).length === 0" class="text-sm text-gray-500 italic py-1">
                                    No data
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            </div>

        <!-- Inventory & Stock Alerts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Medications Inventory -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between p-5 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                            <Pill class="w-4 h-4 text-indigo-600" />
                        </div>
                        <h3 class="font-semibold text-gray-900">Medications</h3>
                    </div>
                    <Link href="/medications" class="text-sm text-[#34554a] font-medium hover:underline">Manage</Link>
                </div>
                <div class="p-5">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">Total Items</span>
                            <span class="text-sm font-bold text-gray-900">{{ inventoryStats.medications?.total || 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">Total Stock</span>
                            <span class="text-sm font-bold text-gray-900">{{ (inventoryStats.medications?.totalStock || 0).toLocaleString() }} units</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">Expiring Soon</span>
                            <span class="text-sm font-bold text-amber-600">{{ inventoryStats.medications?.expiringSoon || 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-sm text-gray-600">Expired</span>
                            <span class="text-sm font-bold text-red-600">{{ inventoryStats.medications?.expired || 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transfer Management Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Transfer Overview -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between p-5 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                            <Truck class="w-4 h-4 text-blue-600" />
                        </div>
                        <h3 class="font-semibold text-gray-900">Transfer Management</h3>
                    </div>
                    <Link href="/transfer/create/ctv" class="text-sm text-[#34554a] font-medium hover:underline">+ Create Transfer</Link>
                </div>
                <div class="p-5">
                    <!-- Transfer Summary -->
                    <div class="grid grid-cols-2 gap-4 mb-5">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500 mb-1">Total Transfers</p>
                            <p class="text-xl font-bold text-gray-900">{{ transferStats.total || 0 }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500 mb-1">This Month</p>
                            <p class="text-xl font-bold text-blue-600">{{ transferStats.thisMonth || 0 }}</p>
                        </div>
                        <div class="bg-amber-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500 mb-1">Pending Approval</p>
                            <p class="text-xl font-bold text-amber-600">{{ transferStats.pending || 0 }}</p>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Pending & Recent Transfers -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between p-5 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                            <ArrowLeftRight class="w-4 h-4 text-amber-600" />
                        </div>
                        <h3 class="font-semibold text-gray-900">Transfer Activity</h3>
                    </div>
                    <Link href="/transfer/ctv" class="text-sm text-[#34554a] font-medium hover:underline">View All</Link>
                </div>
                <div class="p-5">
                    <!-- Pending Transfers -->
                    <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-3">Pending Transfers</h4>
                    <div class="space-y-2 mb-5">
                        <div v-for="transfer in transferStats.pendingTransfers" :key="`pending-${transfer.id}`" class="flex items-center justify-between py-2 px-3 rounded-lg" :class="transfer.current_step === 'transported' || transfer.current_step === 'witness_transit' || transfer.current_step === 'verified_transit' ? 'bg-purple-50' : 'bg-amber-50'">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center" :class="transfer.current_step === 'transported' || transfer.current_step === 'witness_transit' || transfer.current_step === 'verified_transit' ? 'bg-purple-100' : 'bg-amber-100'">
                                    <Send v-if="transfer.current_step === 'transported' || transfer.current_step === 'witness_transit' || transfer.current_step === 'verified_transit'" class="w-4 h-4 text-purple-600" />
                                    <Truck v-else class="w-4 h-4 text-amber-600" />
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-gray-900">#{{ transfer.document_no }}</p>
                                    <p class="text-xs text-gray-500">{{ transfer.total_cattle || 0 }} cattle • {{ transfer.from_location }} → {{ transfer.to_location }}</p>
                                </div>
                            </div>
                            <span class="px-2 py-1 rounded text-xs font-bold" :class="transfer.current_step === 'transported' || transfer.current_step === 'witness_transit' || transfer.current_step === 'verified_transit' ? 'bg-purple-100 text-purple-700' : 'bg-amber-100 text-amber-700'">
                                {{ transfer.current_step === 'transported' || transfer.current_step === 'witness_transit' || transfer.current_step === 'verified_transit' ? 'In Transit' : 'Pending' }}
                            </span>
                        </div>
                        <div v-if="!transferStats.pendingTransfers || transferStats.pendingTransfers.length === 0" class="text-sm text-gray-500 italic py-2 px-3">
                            No pending transfers.
                        </div>
                    </div>

                    <!-- Recent Completed Transfers -->
                    <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-3">Recent Completed</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-gray-500">
                                    <th class="pb-2">Voucher</th>
                                    <th class="pb-2">Cattle</th>
                                    <th class="pb-2">Route</th>
                                    <th class="pb-2">Date</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700">
                                <tr v-for="transfer in transferStats.recentTransfers" :key="`completed-${transfer.id}`" class="border-t border-gray-100">
                                    <td class="py-2 font-medium">#{{ transfer.document_no }}</td>
                                    <td class="py-2">{{ transfer.total_cattle || 0 }}</td>
                                    <td class="py-2">{{ transfer.from_location }} → {{ transfer.to_location }}</td>
                                    <td class="py-2 text-gray-500">{{ formatDate(transfer.date) }}</td>
                                </tr>
                                <tr v-if="!transferStats.recentTransfers || transferStats.recentTransfers.length === 0">
                                    <td colspan="4" class="py-4 text-center text-gray-400 italic">No recent completed transfers.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 gap-6 mb-8">
            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center gap-3 p-5 border-b border-gray-100">
                    <div class="w-8 h-8 rounded-lg bg-[#34554a] flex items-center justify-center">
                        <Plus class="w-4 h-4 text-white" />
                    </div>
                    <h3 class="font-semibold text-gray-900">Quick Actions</h3>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <Link href="/cattle" class="flex flex-col items-center justify-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors group">
                            <Beef class="w-6 h-6 text-gray-400 group-hover:text-[#34554a] mb-2 transition-colors" />
                            <span class="text-xs font-medium text-gray-600">Add Cattle</span>
                        </Link>
                        <Link href="/calving/create" class="flex flex-col items-center justify-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors group">
                            <Baby class="w-6 h-6 text-gray-400 group-hover:text-pink-600 mb-2 transition-colors" />
                            <span class="text-xs font-medium text-gray-600">Record Calving</span>
                        </Link>
                        <Link href="/mortality/create" class="flex flex-col items-center justify-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors group">
                            <Skull class="w-6 h-6 text-gray-400 group-hover:text-red-600 mb-2 transition-colors" />
                            <span class="text-xs font-medium text-gray-600">Record Mortality</span>
                        </Link>
                        <Link href="/health/treatment" class="flex flex-col items-center justify-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors group">
                            <HeartPulse class="w-6 h-6 text-gray-400 group-hover:text-rose-600 mb-2 transition-colors" />
                            <span class="text-xs font-medium text-gray-600">Health Records</span>
                        </Link>
                        <Link href="/medications" class="flex flex-col items-center justify-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors group">
                            <Pill class="w-6 h-6 text-gray-400 group-hover:text-indigo-600 mb-2 transition-colors" />
                            <span class="text-xs font-medium text-gray-600">Medication Stock</span>
                        </Link>
                        <Link href="/users" class="flex flex-col items-center justify-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors group">
                            <Users class="w-6 h-6 text-gray-400 group-hover:text-purple-600 mb-2 transition-colors" />
                            <span class="text-xs font-medium text-gray-600">Add User</span>
                        </Link>
                        <Link href="/transfer/ctv" class="flex flex-col items-center justify-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors group">
                            <ArrowLeftRight class="w-6 h-6 text-gray-400 group-hover:text-blue-600 mb-2 transition-colors" />
                            <span class="text-xs font-medium text-gray-600">Movement</span>
                        </Link>
                        <Link href="/transfer/create/ctv" class="flex flex-col items-center justify-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors group">
                            <Truck class="w-6 h-6 text-gray-400 group-hover:text-indigo-600 mb-2 transition-colors" />
                            <span class="text-xs font-medium text-gray-600">New Transfer</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Management & Estates Summary -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- User Management Summary -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between p-5 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-[#34554a] text-white">
                            <Users class="w-4 h-4" />
                        </div>
                        <h3 class="font-semibold text-gray-900">User Management</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="refreshUserStats" :disabled="isRefreshing" class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors" :class="{ 'opacity-50 cursor-not-allowed': isRefreshing }" title="Refresh">
                            <RefreshCw class="w-4 h-4 text-gray-500" :class="{ 'animate-spin': isRefreshing }" />
                        </button>
                        <Link href="/users" class="text-sm text-[#34554a] font-medium hover:underline">Manage Users</Link>
                    </div>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-3 gap-4 mb-5">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-gray-900">{{ realtimeUserStats.total }}</p>
                            <p class="text-xs text-gray-500">Total Users</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-[#34554a]">{{ realtimeUserStats.active }}</p>
                            <p class="text-xs text-gray-500">Active</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-gray-400">{{ realtimeUserStats.inactive }}</p>
                            <p class="text-xs text-gray-500">Inactive</p>
                        </div>
                    </div>

                    <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-3">By Role</h4>
                    <div class="space-y-2">
                        <div v-for="(count, role) in realtimeUserStats.byRole" :key="role" class="flex items-center justify-between">
                            <span class="text-sm text-gray-700 capitalize">{{ role }}</span>
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full bg-[#34554a]" :style="{ width: (count / realtimeUserStats.total * 100) + '%' }"></div>
                                </div>
                                <span class="text-xs font-medium text-gray-600">{{ count }}</span>
                            </div>
                        </div>
                    </div>


                </div>
            </div>

            <!-- Operating Unit Summary -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between p-5 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                            <TreePine class="w-4 h-4 text-green-600" />
                        </div>
                        <h3 class="font-semibold text-gray-900">Operating Units & Herds</h3>
                    </div>
                    <Link href="/herd-cards" class="text-sm text-[#34554a] font-medium hover:underline">View All</Link>
                </div>
                <div class="p-5">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
                                    <th class="pb-3">Operating Unit</th>
                                    <th class="pb-3">Area (ha)</th>
                                    <th class="pb-3">Herds</th>
                                    <th class="pb-3">Cattle</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700">
                                <tr v-for="unit in operatingUnits" :key="`unit-${unit.id}`" class="border-b border-gray-50">
                                    <td class="py-3 font-medium">{{ unit.name }}</td>
                                    <td class="py-3">{{ Number(unit.area || 0).toFixed(1) }}</td>
                                    <td class="py-3">{{ unit.herd_count || 0 }}</td>
                                    <td class="py-3">{{ unit.cattle_count || 0 }}</td>
                                </tr>
                                <tr v-if="operatingUnits.length === 0" class="border-b border-gray-50">
                                    <td colspan="4" class="py-4 text-center text-gray-400 italic">No operating unit records found</td>
                                </tr>
                                <tr class="font-bold bg-gray-50">
                                    <td class="py-3">TOTAL</td>
                                    <td class="py-3">{{ Number(estatesStats.totalArea || 0).toFixed(1) }}</td>
                                    <td class="py-3">{{ estatesStats.totalHerds || 0 }}</td>
                                    <td class="py-3">{{ operatingUnitTotalCattle }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
