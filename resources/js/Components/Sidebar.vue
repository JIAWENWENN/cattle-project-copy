<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import {
    LayoutDashboard,
    Beef,
    Utensils,
    HeartPulse,
    Baby,
    Skull,
    Shield,
    Trees,
    ArrowLeftRight,
    Truck,
    Package,
    PieChart,
    ClipboardCheck,
    Settings,
    ChevronDown,
    Search,
    Pill
} from 'lucide-vue-next';

const openMenus = ref([]);
const searchQuery = ref('');
const page = usePage();

// Auto-open menu based on current URL
onMounted(() => {
    const url = page.url;
    // Updated to detect Feeds, Medications, and Stock pages
    if (url.startsWith('/medications') || url.startsWith('/inventory/stock')) {
        if (!openMenus.value.includes('inventory')) openMenus.value.push('inventory');
    }
    if (url.startsWith('/users') || url.startsWith('/access-control') || url.startsWith('/field-permissions') || url.startsWith('/audit-logs')) {
        if (!openMenus.value.includes('settings')) openMenus.value.push('settings');
    }
    if (url.startsWith('/cattle/weekly-return')) {
        if (!openMenus.value.includes('analytics')) openMenus.value.push('analytics');
    } else if (url.startsWith('/cattle')) {
        if (!openMenus.value.includes('cattle')) openMenus.value.push('cattle');
    }
    if (url.startsWith('/mortality')) {
        if (!openMenus.value.includes('mortality')) openMenus.value.push('mortality');
    }
    if (url.startsWith('/driver')) {
        if (!openMenus.value.includes('driver')) openMenus.value.push('driver');
    }
    if (url.startsWith('/health')) {
        if (!openMenus.value.includes('health')) openMenus.value.push('health');
    }
    if (url.startsWith('/transfer')) {
        if (!openMenus.value.includes('transfer')) openMenus.value.push('transfer');
    }
});

const toggleMenu = (menuId) => {
    const index = openMenus.value.indexOf(menuId);
    if (index > -1) {
        openMenus.value.splice(index, 1);
    } else {
        openMenus.value.push(menuId);
    }
};

const menuItems = [
    { id: 'dashboard', name: 'Dashboard', hasSubmenu: false },
    { id: 'cattle', name: 'Cattle', hasSubmenu: false },
    { id: 'feeding', name: 'Feeding', hasSubmenu: true },
    { id: 'health', name: 'Health', hasSubmenu: true },
    { id: 'calving', name: 'Calving', hasSubmenu: true },
    { id: 'mortality', name: 'Mortality', hasSubmenu: true },
    { id: 'pasture', name: 'Pasture', hasSubmenu: true },
    { id: 'transfer', name: 'Transfer', hasSubmenu: true },
    
    { id: 'driver', name: 'Driver', hasSubmenu: true },
    { id: 'inventory', name: 'Inventory', hasSubmenu: true },
    { id: 'analytics', name: 'Analytics', hasSubmenu: true },
    { id: 'task', name: 'Task', hasSubmenu: true },
    { id: 'settings', name: 'Settings', hasSubmenu: true }
];

const menuToModules = {
    dashboard: ['Dashboard'],
    cattle: ['Cattle Directory', 'Daily Operation DOML'],
    feeding: ['Feeding Record'],
    health: ['Treatment Record', 'Veterinary Contact'],
    calving: ['Calving Record', 'Calving Checklist'],
    mortality: ['Mortality Records'],
    pasture: ['Pasture'],
    transfer: ['Transfer CTV', 'Transfer Receival', 'Transfer SIV'],
    driver: ['Driver'],
    inventory: ['Inventory Medication Stock'],
    analytics: ['Weekly Return', 'Performance Summary'],
    task: ['Task'],
    settings: ['Settings'],
};

const hasModuleAccess = (moduleName) => {
    if (moduleName === 'Daily Operation DOML') return true;
    if (moduleName === 'Weekly Return') return true;
    
    const permissionValue = page.props.auth?.permissions?.[moduleName] ?? ['no-access'];
    const permissionList = Array.isArray(permissionValue)
        ? permissionValue
        : String(permissionValue)
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean);

    return !permissionList.includes('no-access');
};

const filteredMenuItems = computed(() => {
    if (!searchQuery.value) return menuItems;
    const query = searchQuery.value.toLowerCase();
    return menuItems.filter(item => item.name.toLowerCase().includes(query));
});

const canAccessMenu = (menuId) => {
    const user = page.props.auth?.user;
    if (!user) return false;

    const alwaysVisibleMenus = new Set([
        'dashboard',
        'feeding',
        'health',
        'pasture',
        'driver',
        'task',
        'mortality',
        'transfer',
    ]);

    if (alwaysVisibleMenus.has(menuId)) {
        return true;
    }

    if (String(user.role || '').toLowerCase() === 'admin') {
        return true;
    }

    const moduleNames = menuToModules[menuId];
    if (!moduleNames || moduleNames.length === 0) return true;

    return moduleNames.some((moduleName) => hasModuleAccess(moduleName));
};

const isVisible = (menuId) => {
    return filteredMenuItems.value.some(item => item.id === menuId) && canAccessMenu(menuId);
};
</script>

<template>
    <aside class="fixed inset-y-0 left-0 w-64 bg-[#22262a] text-gray-400 flex flex-col font-medium z-30 shadow-xl h-screen overflow-hidden text-sm">
        <div class="h-24 flex items-center justify-center py-2 bg-[#22262a] flex-shrink-0 border-b border-gray-800">
            <img src="/images/sawit-kinabalu-logo.png" alt="Sawit Kinabalu Logo" class="h-full w-auto px-2" />
        </div>

        <div class="px-4 py-2 flex-shrink-0">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 w-4 h-4" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search menu..."
                    class="w-full pl-9 pr-4 py-2 rounded-md bg-[#2a2e33] border border-[#3a3f44] text-sm focus:outline-none text-gray-300 placeholder-gray-500 focus:border-gray-500 transition-colors"
                />
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-2 space-y-1 pb-8 sidebar-scroll">

            <Link
                v-show="isVisible('dashboard')"
                href="/dashboard"
                class="flex items-center px-4 py-2.5 text-gray-300 hover:bg-[#2a2e33] hover:text-white rounded-md transition-colors"
                :class="{ 'bg-[#2a2e33] text-white': $page.url === '/dashboard' }"
            >
                <LayoutDashboard class="w-4 h-4 mr-3" />
                Dashboard
            </Link>

            <div v-show="isVisible('cattle')" class="nav-item group">
                <button @click="toggleMenu('cattle')" class="w-full flex items-center justify-between px-4 py-2.5 text-gray-300 hover:bg-[#2a2e33] hover:text-white rounded-md">
                    <div class="flex items-center"><Beef class="w-4 h-4 mr-3" />Cattle</div>
                    <ChevronDown class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': openMenus.includes('cattle') }" />
                </button>
                <div v-show="openMenus.includes('cattle')" class="pl-11 space-y-1 mt-1">
                    <Link href="/cattle" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/cattle' }">Cattle Directory</Link>
                    <Link href="/cattle/daily-operation" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/cattle/daily-operation' }">Daily Operation (DOML)</Link>
                </div>
            </div>

            <div v-show="isVisible('feeding')" class="nav-item group">
                <button @click="toggleMenu('feeding')" class="w-full flex items-center justify-between px-4 py-2.5 text-gray-300 hover:bg-[#2a2e33] hover:text-white rounded-md">
                    <div class="flex items-center"><Utensils class="w-4 h-4 mr-3" />Feeding</div>
                    <ChevronDown class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': openMenus.includes('feeding') }" />
                </button>
                <div v-show="openMenus.includes('feeding')" class="pl-11 space-y-1 mt-1">
                    <Link href="/feeding" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url.startsWith('/feeding') }">Feeding Records</Link>
                </div>
            </div>

            <div v-show="isVisible('health')" class="nav-item group">
                <button @click="toggleMenu('health')" class="w-full flex items-center justify-between px-4 py-2.5 text-gray-300 hover:bg-[#2a2e33] hover:text-white rounded-md">
                    <div class="flex items-center"><HeartPulse class="w-4 h-4 mr-3" />Health</div>
                    <ChevronDown class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': openMenus.includes('health') }" />
                </button>
                <div v-show="openMenus.includes('health')" class="pl-11 space-y-1 mt-1">
                    <Link href="/health/treatment" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url.startsWith('/health/treatment') }">Treatment Records</Link>
                    <Link href="/health/contact" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url.startsWith('/health/contact') }">Veterinary Contact</Link>
                </div>
            </div>

            <div v-show="isVisible('calving')" class="nav-item group">
                <button @click="toggleMenu('calving')" class="w-full flex items-center justify-between px-4 py-2.5 text-gray-300 hover:bg-[#2a2e33] hover:text-white rounded-md">
                    <div class="flex items-center"><Baby class="w-4 h-4 mr-3" />Calving</div>
                    <ChevronDown class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': openMenus.includes('calving') }" />
                </button>
                <div v-show="openMenus.includes('calving')" class="pl-11 space-y-1 mt-1">
                    <Link href="/calving" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/calving' }">Calving Records</Link>
                    <Link href="/calving-checklist" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/calving-checklist' }">Calving Checklist</Link>
                </div>
            </div>

            <div v-show="isVisible('mortality')" class="nav-item group">
                <button @click="toggleMenu('mortality')" class="w-full flex items-center justify-between px-4 py-2.5 text-gray-300 hover:bg-[#2a2e33] hover:text-white rounded-md">
                    <div class="flex items-center"><Skull class="w-4 h-4 mr-3" />Mortality</div>
                    <ChevronDown class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': openMenus.includes('mortality') }" />
                </button>
                <div v-show="openMenus.includes('mortality')" class="pl-11 space-y-1 mt-1">
                    <Link href="/mortality/records" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/mortality/records' }">Mortality Records</Link>
                </div>
            </div>

            <div v-show="isVisible('pasture')" class="nav-item group">
                <button @click="toggleMenu('pasture')" class="w-full flex items-center justify-between px-4 py-2.5 text-gray-300 hover:bg-[#2a2e33] hover:text-white rounded-md">
                    <div class="flex items-center"><Trees class="w-4 h-4 mr-3" />Pasture</div>
                    <ChevronDown class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': openMenus.includes('pasture') }" />
                </button>
                <div v-show="openMenus.includes('pasture')" class="pl-11 space-y-1 mt-1">
                    <Link href="/pasture/all" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url.startsWith('/pasture/all') }">All Pastures</Link>
                </div>
            </div>

            <div v-show="isVisible('transfer')" class="nav-item group">
                <button @click="toggleMenu('transfer')" class="w-full flex items-center justify-between px-4 py-2.5 text-gray-300 hover:bg-[#2a2e33] hover:text-white rounded-md">
                    <div class="flex items-center"><ArrowLeftRight class="w-4 h-4 mr-3" />Transfer</div>
                    <ChevronDown class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': openMenus.includes('transfer') }" />
                </button>
                <div v-show="openMenus.includes('transfer')" class="pl-11 space-y-1 mt-1">
                    <Link href="/transfer/ctv" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/transfer/ctv' || $page.url === '/transfer/create/ctv' }">CTV</Link>
                    <Link href="/transfer/receival" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/transfer/receival' || $page.url === '/transfer/create/receival' }">Receival</Link>
                    <Link href="/transfer/siv" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/transfer/siv' || $page.url === '/transfer/create/siv' }">SIV</Link>
                </div>
            </div>

            <div v-show="isVisible('driver')" class="nav-item group">
                <button @click="toggleMenu('driver')" class="w-full flex items-center justify-between px-4 py-2.5 text-gray-300 hover:bg-[#2a2e33] hover:text-white rounded-md">
                    <div class="flex items-center"><Truck class="w-4 h-4 mr-3" />Driver</div>
                    <ChevronDown class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': openMenus.includes('driver') }" />
                </button>
                <div v-show="openMenus.includes('driver')" class="pl-11 space-y-1 mt-1">
                    <Link href="/driver" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/driver' }">All Drivers</Link>
                    <Link href="/driver/shift-schedule" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/driver/shift-schedule' }">Shift Schedule</Link>
                    <Link href="/driver/delivery-history" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/driver/delivery-history' }">Delivery History</Link>
                </div>
            </div>

            <div v-show="isVisible('inventory')" class="nav-item group">
                <button @click="toggleMenu('inventory')" class="w-full flex items-center justify-between px-4 py-2.5 text-gray-300 hover:bg-[#2a2e33] hover:text-white rounded-md">
                    <div class="flex items-center"><Package class="w-4 h-4 mr-3" />Inventory</div>
                    <ChevronDown class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': openMenus.includes('inventory') }" />
                </button>
                <div v-show="openMenus.includes('inventory')" class="pl-11 space-y-1 mt-1">
                    <Link href="/medications" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/medications' }">Medication Stock</Link>
                </div>
            </div>

            <div v-show="isVisible('analytics')" class="nav-item group">
                <button @click="toggleMenu('analytics')" class="w-full flex items-center justify-between px-4 py-2.5 text-gray-300 hover:bg-[#2a2e33] hover:text-white rounded-md">
                    <div class="flex items-center"><PieChart class="w-4 h-4 mr-3" />Analytics</div>
                    <ChevronDown class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': openMenus.includes('analytics') }" />
                </button>
                <div v-show="openMenus.includes('analytics')" class="pl-11 space-y-1 mt-1">
                    <Link href="/analytics/performance-summary" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/analytics/performance-summary' }">Performance Summary</Link>
                    <Link href="/cattle/weekly-return" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url.startsWith('/cattle/weekly-return') }">Weekly Return</Link>
                </div>
            </div>

            <div v-show="isVisible('task')" class="nav-item group">
                <button @click="toggleMenu('task')" class="w-full flex items-center justify-between px-4 py-2.5 text-gray-300 hover:bg-[#2a2e33] hover:text-white rounded-md">
                    <div class="flex items-center"><ClipboardCheck class="w-4 h-4 mr-3" />Task</div>
                    <ChevronDown class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': openMenus.includes('task') }" />
                </button>
                <div v-show="openMenus.includes('task')" class="pl-11 space-y-1 mt-1">
                    <Link href="/task" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/task' }">All Tasks</Link>
                    <Link href="/task/calendar" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/task/calendar' }">Calendar View</Link>
                    <Link href="/task-notifications/page" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url.startsWith('/task-notifications/page') }">Notifications</Link>
                </div>
            </div>

            <div v-show="isVisible('settings')" class="nav-item group">
                <button @click="toggleMenu('settings')" class="w-full flex items-center justify-between px-4 py-2.5 text-gray-300 hover:bg-[#2a2e33] hover:text-white rounded-md">
                    <div class="flex items-center"><Settings class="w-4 h-4 mr-3" />Settings</div>
                    <ChevronDown class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': openMenus.includes('settings') }" />
                </button>
                <div v-show="openMenus.includes('settings')" class="pl-11 space-y-1 mt-1">
                    <Link href="/users" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/users' }">User Account</Link>
                    <Link href="/access-control" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url === '/access-control' }">Access Control Matrix</Link>
                    <Link href="/audit-logs" class="block py-2 text-gray-500 hover:text-white text-xs" :class="{ 'text-white font-bold': $page.url.startsWith('/audit-logs') }">Audit Logs</Link>
                </div>
            </div>

        </nav>
    </aside>
</template>

<style scoped>
.sidebar-scroll::-webkit-scrollbar {
    width: 5px;
}
.sidebar-scroll::-webkit-scrollbar-track {
    background: #22262a;
}
.sidebar-scroll::-webkit-scrollbar-thumb {
    background-color: #4b5563;
    border-radius: 10px;
}
</style>
