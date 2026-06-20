<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import type { User as AuthUser } from '@/types';

type VillageOption = {
    id: number;
    name: string;
    x: number;
    y: number;
    population: number;
    player_name?: string | null;
};

type AvailableTroop = {
    key: string;
    name: string;
    speed: number;
    quantity: number;
};

type CatalogTroop = {
    key: string;
    tribe_key: string;
    name: string;
    speed: number;
};

type SelectedAccount = {
    world_key: string;
    world_name: string;
    player_name: string;
} | null;

const props = defineProps<{
    selectedWorldKey: string;
    selectedAccount: SelectedAccount;
    world: {
        speed: number;
        movement_speed_factor: number;
        radius: number;
        size: number;
    };
    ownedVillages: VillageOption[];
    availableTroopsByVillage: Record<string, AvailableTroop[]>;
    catalogTroops: CatalogTroop[];
}>();

const page = usePage<{ auth: { user: AuthUser | null } }>();
const authUser = computed(() => page.props.auth.user);
const menuOpen = ref(false);
const coordinatesMode = ref(false);
const startName = ref(props.ownedVillages[0]?.name ?? '');
const targetName = ref('');
const startX = ref<number | null>(props.ownedVillages[0]?.x ?? null);
const startY = ref<number | null>(props.ownedVillages[0]?.y ?? null);
const targetX = ref<number | null>(null);
const targetY = ref<number | null>(null);
const selectedStartVillage = ref<VillageOption | null>(props.ownedVillages[0] ?? null);
const selectedTargetVillage = ref<VillageOption | null>(null);
const targetResults = ref<VillageOption[]>([]);
const startResults = ref<VillageOption[]>([]);
const selectedTroopKey = ref('');
const troopMode = ref<'available' | 'catalog'>(props.ownedVillages.length > 0 ? 'available' : 'catalog');

const numberFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 2,
});

const selectedAccountLabel = computed(() =>
    props.selectedAccount ? `${props.selectedAccount.player_name} sur ${props.selectedAccount.world_name}` : 'Aucun compte joué lié',
);

const startVillage = computed(() => props.ownedVillages.find((village) => village.name === startName.value) ?? null);
const availableTroops = computed(() => (startVillage.value ? props.availableTroopsByVillage[startVillage.value.name] ?? [] : []));
const selectedAvailableTroop = computed(() => availableTroops.value.find((troop) => troop.key === selectedTroopKey.value) ?? null);
const selectedCatalogTroop = computed(() => props.catalogTroops.find((troop) => troop.key === selectedTroopKey.value) ?? null);
const selectedTroop = computed(() => (troopMode.value === 'available' ? selectedAvailableTroop.value : selectedCatalogTroop.value));
const effectiveSpeed = computed(() => (selectedTroop.value?.speed ?? 0) * props.world.movement_speed_factor);

const resolvedStart = computed(() => {
    if (coordinatesMode.value) {
        return nullableCoords(startX.value, startY.value);
    }

    const village = selectedStartVillage.value ?? startVillage.value;
    return village ? { x: village.x, y: village.y } : null;
});

const resolvedTarget = computed(() => {
    if (coordinatesMode.value) {
        return nullableCoords(targetX.value, targetY.value);
    }

    const exact = selectedTargetVillage.value ?? targetResults.value.find((village) => village.name === targetName.value);
    return exact ? { x: exact.x, y: exact.y } : null;
});

const distance = computed(() => {
    if (!resolvedStart.value || !resolvedTarget.value) {
        return null;
    }

    const rawX = Math.abs(resolvedStart.value.x - resolvedTarget.value.x);
    const rawY = Math.abs(resolvedStart.value.y - resolvedTarget.value.y);
    const dx = Math.min(rawX, props.world.size - rawX);
    const dy = Math.min(rawY, props.world.size - rawY);

    return Math.sqrt(dx * dx + dy * dy);
});

const oneWaySeconds = computed(() => {
    if (distance.value === null || effectiveSpeed.value <= 0) {
        return null;
    }

    return Math.ceil((distance.value / effectiveSpeed.value) * 3600);
});

const results = computed(() => ({
    go: oneWaySeconds.value,
    back: oneWaySeconds.value,
    roundTrip: oneWaySeconds.value === null ? null : oneWaySeconds.value * 2,
}));

watch(startName, (name) => {
    const village = props.ownedVillages.find((item) => item.name === name);
    if (village) {
        selectedStartVillage.value = village;
        startX.value = village.x;
        startY.value = village.y;
    } else if (selectedStartVillage.value?.name !== name) {
        selectedStartVillage.value = null;
    }

    void searchVillages(name, startResults);
});

watch(targetName, (name) => {
    if (selectedTargetVillage.value?.name !== name) {
        selectedTargetVillage.value = null;
    }

    void searchVillages(name, targetResults);
});

watch(troopMode, () => {
    selectedTroopKey.value = troopMode.value === 'available'
        ? availableTroops.value[0]?.key ?? ''
        : props.catalogTroops[0]?.key ?? '';
});

watch(availableTroops, (troops) => {
    if (troopMode.value === 'available' && !troops.some((troop) => troop.key === selectedTroopKey.value)) {
        selectedTroopKey.value = troops[0]?.key ?? '';
    }
}, { immediate: true });

function nullableCoords(x: number | null, y: number | null): { x: number; y: number } | null {
    return Number.isFinite(x) && Number.isFinite(y) ? { x: Number(x), y: Number(y) } : null;
}

async function searchVillages(query: string, target: { value: VillageOption[] }): Promise<void> {
    if (!props.selectedWorldKey || query.trim().length < 2) {
        target.value = [];
        return;
    }

    const params = new URLSearchParams({ world_key: props.selectedWorldKey, q: query.trim() });
    const response = await fetch(`/villages/search?${params.toString()}`, {
        headers: { Accept: 'application/json' },
    });

    target.value = response.ok ? await response.json() : [];
}

function applyVillage(village: VillageOption, target: 'start' | 'target'): void {
    if (target === 'start') {
        startName.value = village.name;
        selectedStartVillage.value = village;
        startX.value = village.x;
        startY.value = village.y;
        startResults.value = [];
        return;
    }

    targetName.value = village.name;
    selectedTargetVillage.value = village;
    targetX.value = village.x;
    targetY.value = village.y;
    targetResults.value = [];
}

function formatDuration(totalSeconds: number | null): string {
    if (totalSeconds === null) {
        return '--:--:--';
    }

    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

function troopLabel(troop: CatalogTroop): string {
    return `${tribeLabel(troop.tribe_key)} - ${troop.name}`;
}

function tribeLabel(tribe: string): string {
    return {
        romans: 'Romains',
        teutons: 'Teutons',
        gauls: 'Gaulois',
        egyptians: 'Égyptiens',
        huns: 'Huns',
        spartans: 'Spartiates',
    }[tribe] ?? tribe;
}
</script>

<template>
    <Head title="Calculateur de trajets" />

    <div class="min-h-screen overflow-x-hidden bg-[#f4efe4] text-[#171411]">
        <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8 xl:px-10">
            <header class="flex min-w-0 items-start justify-between gap-4">
                <div class="min-w-0">
                    <Link href="/" class="text-sm font-medium text-[#6a5d52] transition hover:text-[#8b4a27]">
                        Retour à l'accueil
                    </Link>
                    <h1 class="mt-4 text-4xl font-semibold text-[#1c1814]">
                        Calculateur de trajets
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-[#62584d]">
                        Calcule le temps de trajet aller, retour et aller-retour entre deux villages ou coordonnées.
                    </p>
                    <p class="mt-3 text-sm font-semibold text-[#1f1a14]">
                        {{ selectedAccountLabel }}
                    </p>
                    <div class="mt-4 md:hidden">
                        <LanguageSwitcher />
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <div class="hidden md:block">
                        <LanguageSwitcher />
                    </div>
                    <div v-if="authUser" class="relative">
                        <button
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-[#1f1a14]/10 bg-white/75 text-[#1f1a14] shadow-sm transition hover:bg-white"
                            aria-label="Ouvrir le menu"
                            @click="menuOpen = !menuOpen"
                        >
                            <span class="grid gap-1">
                                <span class="block h-0.5 w-5 rounded-full bg-current" />
                                <span class="block h-0.5 w-5 rounded-full bg-current" />
                                <span class="block h-0.5 w-5 rounded-full bg-current" />
                            </span>
                        </button>

                        <div
                            v-if="menuOpen"
                            class="absolute right-0 z-20 mt-3 max-h-[calc(100vh-6rem)] w-[calc(100vw-2rem)] max-w-[24rem] overflow-y-auto rounded-[18px] border border-[#1f1a14]/10 bg-[#fffdf8] p-4 text-[#1f1a14] shadow-[0_24px_90px_rgba(44,32,20,0.18)]"
                        >
                            <div class="border-b border-[#1f1a14]/10 pb-4">
                                <p class="text-sm font-semibold">{{ authUser.name }}</p>
                                <p class="mt-1 text-xs text-[#6b6258]">{{ authUser.email }}</p>
                            </div>

                            <div class="grid gap-2 py-4">
                                <Link href="/" class="rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-[#f2eadc]">Accueil</Link>
                                <Link href="/account" class="rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-[#f2eadc]">Compte</Link>
                                <Link v-if="authUser.is_admin" href="/admin" class="rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-[#f2eadc]">Administration</Link>
                                <Link :href="props.selectedWorldKey ? `/inactive-finder?world=${encodeURIComponent(props.selectedWorldKey)}` : '/inactive-finder'" class="rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-[#f2eadc]">Chercheur d'inactifs</Link>
                                <Link :href="props.selectedWorldKey ? `/map-builder?world=${encodeURIComponent(props.selectedWorldKey)}` : '/map-builder'" class="rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-[#f2eadc]">Créateur de carte</Link>
                                <Link :href="props.selectedWorldKey ? `/troops?world=${encodeURIComponent(props.selectedWorldKey)}` : '/troops'" class="rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-[#f2eadc]">Troupes</Link>
                                <Link :href="props.selectedWorldKey ? `/travel-calculator?world=${encodeURIComponent(props.selectedWorldKey)}` : '/travel-calculator'" class="rounded-xl bg-[#f2eadc] px-3 py-2 text-sm font-medium transition hover:bg-[#eadcc8]">Calculateur de trajets</Link>
                            </div>

                            <Link as="button" method="post" href="/logout" class="w-full rounded-xl bg-[#1f1a14] px-3 py-2 text-sm font-medium text-[#f7efe1] transition hover:bg-[#8b4a27]">
                                Déconnexion
                            </Link>
                        </div>
                    </div>
                </div>
            </header>

            <main class="grid min-w-0 gap-6 py-8 lg:grid-cols-[1fr_0.9fr]">
                <section class="min-w-0 rounded-[18px] border border-[#1f1a14]/10 bg-white/75 p-4 shadow-sm sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-[#1f1a14]">Critères</h2>
                            <p class="mt-1 text-sm text-[#6b6258]">
                                Serveur x{{ props.world.speed }} · déplacement x{{ props.world.movement_speed_factor }}
                            </p>
                        </div>

                        <label class="flex items-center gap-3 rounded-full border border-[#1f1a14]/10 bg-white px-4 py-2 text-sm font-medium">
                            <span>Coordonnées</span>
                            <input v-model="coordinatesMode" type="checkbox" class="h-4 w-4 accent-[#1f1a14]">
                        </label>
                    </div>

                    <div class="mt-5 grid gap-4">
                        <div v-if="!coordinatesMode" class="grid gap-4 md:grid-cols-2">
                            <div class="relative">
                                <label class="text-sm font-semibold text-[#1f1a14]">Village de départ</label>
                                <input v-model="startName" class="mt-2 w-full rounded-xl border border-[#1f1a14]/10 bg-white px-3 py-3 text-sm outline-none" placeholder="Nom du village">
                                <div v-if="startResults.length > 0" class="absolute z-10 mt-2 w-full overflow-hidden rounded-xl border border-[#1f1a14]/10 bg-white shadow-lg">
                                    <button v-for="village in startResults" :key="village.id" type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-[#f2eadc]" @click="applyVillage(village, 'start')">
                                        {{ village.name }} ({{ village.x }}|{{ village.y }}) <span class="text-[#6b6258]">{{ village.player_name }}</span>
                                    </button>
                                </div>
                            </div>
                            <div class="relative">
                                <label class="text-sm font-semibold text-[#1f1a14]">Village d'arrivée</label>
                                <input v-model="targetName" class="mt-2 w-full rounded-xl border border-[#1f1a14]/10 bg-white px-3 py-3 text-sm outline-none" placeholder="Nom du village">
                                <div v-if="targetResults.length > 0" class="absolute z-10 mt-2 w-full overflow-hidden rounded-xl border border-[#1f1a14]/10 bg-white shadow-lg">
                                    <button v-for="village in targetResults" :key="village.id" type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-[#f2eadc]" @click="applyVillage(village, 'target')">
                                        {{ village.name }} ({{ village.x }}|{{ village.y }}) <span class="text-[#6b6258]">{{ village.player_name }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-else class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-semibold text-[#1f1a14]">Départ</label>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <input v-model.number="startX" type="number" class="rounded-xl border border-[#1f1a14]/10 bg-white px-3 py-3 text-sm outline-none" placeholder="x">
                                    <input v-model.number="startY" type="number" class="rounded-xl border border-[#1f1a14]/10 bg-white px-3 py-3 text-sm outline-none" placeholder="y">
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-[#1f1a14]">Arrivée</label>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <input v-model.number="targetX" type="number" class="rounded-xl border border-[#1f1a14]/10 bg-white px-3 py-3 text-sm outline-none" placeholder="x">
                                    <input v-model.number="targetY" type="number" class="rounded-xl border border-[#1f1a14]/10 bg-white px-3 py-3 text-sm outline-none" placeholder="y">
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-[auto_1fr] md:items-end">
                            <div>
                                <label class="text-sm font-semibold text-[#1f1a14]">Source des troupes</label>
                                <div class="mt-2 grid grid-cols-2 gap-2 rounded-xl bg-[#f2eadc] p-1">
                                    <button type="button" class="rounded-lg px-3 py-2 text-sm font-medium" :class="troopMode === 'available' ? 'bg-white shadow-sm' : ''" @click="troopMode = 'available'">Village</button>
                                    <button type="button" class="rounded-lg px-3 py-2 text-sm font-medium" :class="troopMode === 'catalog' ? 'bg-white shadow-sm' : ''" @click="troopMode = 'catalog'">Catalogue</button>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-[#1f1a14]">Troupe</label>
                                <select v-if="troopMode === 'available'" v-model="selectedTroopKey" class="mt-2 w-full rounded-xl border border-[#1f1a14]/10 bg-white px-3 py-3 text-sm outline-none">
                                    <option value="">Choisir une troupe disponible</option>
                                    <option v-for="troop in availableTroops" :key="troop.key" :value="troop.key">
                                        {{ troop.name }} · {{ troop.quantity }} · {{ troop.speed }} cases/h
                                    </option>
                                </select>
                                <select v-else v-model="selectedTroopKey" class="mt-2 w-full rounded-xl border border-[#1f1a14]/10 bg-white px-3 py-3 text-sm outline-none">
                                    <option value="">Choisir une troupe</option>
                                    <option v-for="troop in props.catalogTroops" :key="troop.key" :value="troop.key">
                                        {{ troopLabel(troop) }} · {{ troop.speed }} cases/h
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="min-w-0 rounded-[18px] border border-[#1f1a14]/10 bg-white/75 p-4 shadow-sm sm:p-5">
                    <h2 class="text-xl font-semibold text-[#1f1a14]">Résultat</h2>
                    <div class="mt-5 grid gap-3">
                        <div class="rounded-xl bg-[#fffaf1] px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#8b4a27]">Distance</p>
                            <p class="mt-2 text-2xl font-semibold">{{ distance === null ? 'N/D' : numberFormatter.format(distance) }} cases</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl bg-[#f8f1e6] px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#8b4a27]">Aller</p>
                                <p class="mt-2 text-xl font-semibold tabular-nums">{{ formatDuration(results.go) }}</p>
                            </div>
                            <div class="rounded-xl bg-[#f8f1e6] px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#8b4a27]">Retour</p>
                                <p class="mt-2 text-xl font-semibold tabular-nums">{{ formatDuration(results.back) }}</p>
                            </div>
                            <div class="rounded-xl bg-[#f8f1e6] px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#8b4a27]">Aller-retour</p>
                                <p class="mt-2 text-xl font-semibold tabular-nums">{{ formatDuration(results.roundTrip) }}</p>
                            </div>
                        </div>
                        <p class="text-sm text-[#6b6258]">
                            Vitesse effective: {{ effectiveSpeed || 0 }} cases/h.
                        </p>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>
