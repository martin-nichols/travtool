<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useI18n } from '@/lib/i18n';
import type { User as AuthUser } from '@/types';

type WorldOption = {
    key: string;
    name: string;
    base_url: string;
    is_active: boolean;
    category_key: string;
};

type WorldDashboard = {
    availableWorlds: WorldOption[];
    myWorldKeys: string[];
    selectedWorldKey: string | null;
    playedAccounts: PlayedAccount[];
};

type PlayedAccount = {
    id: number;
    world_key: string;
    player_name: string;
    matched_player: boolean;
    invite_code: string | null;
};

type PlayerSuggestion = {
    id: number;
    name: string;
    population: number;
    villages: number;
};

const { t } = useI18n();
const page = usePage<{
    auth: { user: AuthUser | null };
    worldDashboard: WorldDashboard;
    errors?: Record<string, string>;
    flash?: { status?: string | null };
}>();
const authUser = computed(() => page.props.auth.user);
const dashboard = computed(() => page.props.worldDashboard);
const joinPlayedAccountError = computed(() => page.props.errors?.invite_code ?? null);
const ownerModalOpen = ref(page.props.flash?.status === 'dual-owner');
const menuOpen = ref(false);
const worldToAdd = ref('');
const playedAccountName = ref('');
const playedAccountInviteCode = ref('');
const playerSuggestions = ref<PlayerSuggestion[]>([]);
const playerSearchLoading = ref(false);
let playerSearchTimer: ReturnType<typeof window.setTimeout> | null = null;

const activeWorlds = computed(() => dashboard.value.availableWorlds.filter((world) => world.is_active));
const myWorlds = computed(() => {
    const byKey = new Map(activeWorlds.value.map((world) => [world.key, world]));

    return dashboard.value.myWorldKeys.map((key) => byKey.get(key)).filter((world): world is WorldOption => Boolean(world));
});
const selectedWorld = computed(() => {
    const selectedKey = dashboard.value.selectedWorldKey;

    return activeWorlds.value.find((world) => world.key === selectedKey) ?? myWorlds.value[0] ?? activeWorlds.value[0] ?? null;
});
const currentPlayedAccount = computed(
    () => dashboard.value.playedAccounts.find((account) => account.world_key === selectedWorld.value?.key) ?? null,
);
const worldsAvailableToAdd = computed(() => {
    const existingKeys = new Set(myWorlds.value.map((world) => world.key));

    return activeWorlds.value.filter((world) => !existingKeys.has(world.key));
});

const actionCards = computed(() =>
    authUser.value
        ? [
              { key: 'inactive', href: toolHref('/inactive-finder'), accent: 'from-[#ff7a1a] to-[#ffb36b]' },
              { key: 'map_builder', href: toolHref('/map-builder'), accent: 'from-[#3f6d8f] to-[#8ec8e8]' },
          ]
        : [
              { key: 'inactive', href: '/inactive-finder', accent: 'from-[#ff7a1a] to-[#ffb36b]' },
              { key: 'map_builder', href: '/map-builder', accent: 'from-[#3f6d8f] to-[#8ec8e8]' },
              { key: 'register', href: '/register', accent: 'from-[#456f5b] to-[#9fc5a3]' },
          ],
);

function toolHref(path: string, worldKey = selectedWorld.value?.key): string {
    return worldKey ? `${path}?world=${encodeURIComponent(worldKey)}` : path;
}

function selectWorld(worldKey: string): void {
    router.patch(
        '/my-worlds/selected',
        { world_key: worldKey },
        {
            preserveScroll: true,
            onSuccess: () => {
                menuOpen.value = false;
            },
        },
    );
}

function addWorld(): void {
    if (!worldToAdd.value) {
        return;
    }

    router.post(
        '/my-worlds',
        { world_key: worldToAdd.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                worldToAdd.value = '';
            },
        },
    );
}

function removeWorld(worldKey: string): void {
    router.delete(`/my-worlds/${encodeURIComponent(worldKey)}`, {
        preserveScroll: true,
    });
}

function addPlayedAccount(): void {
    if (!selectedWorld.value || !playedAccountName.value.trim()) {
        return;
    }

    router.post(
        '/played-accounts',
        {
            world_key: selectedWorld.value.key,
            player_name: playedAccountName.value.trim(),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                playedAccountName.value = '';
                playerSuggestions.value = [];
            },
        },
    );
}

function removePlayedAccount(account: PlayedAccount): void {
    router.post(`/played-accounts/${account.id}/delete`, {}, { preserveScroll: true });
}

function joinPlayedAccount(): void {
    if (!playedAccountInviteCode.value.trim()) {
        return;
    }

    router.post(
        '/played-accounts/join',
        { invite_code: playedAccountInviteCode.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                playedAccountInviteCode.value = '';
            },
        },
    );
}

function pickPlayerSuggestion(player: PlayerSuggestion): void {
    playedAccountName.value = player.name;
    playerSuggestions.value = [];
}

watch([playedAccountName, selectedWorld], ([name, world]) => {
    if (playerSearchTimer !== null) {
        window.clearTimeout(playerSearchTimer);
    }

    const query = name.trim();

    if (!world || currentPlayedAccount.value || query.length < 2) {
        playerSuggestions.value = [];
        playerSearchLoading.value = false;
        return;
    }

    playerSearchLoading.value = true;
    playerSearchTimer = window.setTimeout(async () => {
        const params = new URLSearchParams({
            world_key: world.key,
            q: query,
        });

        try {
            const response = await fetch(`/players/search?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });

            playerSuggestions.value = response.ok ? await response.json() : [];
        } catch {
            playerSuggestions.value = [];
        } finally {
            playerSearchLoading.value = false;
        }
    }, 220);
});

watch(
    () => page.props.flash?.status,
    (status) => {
        ownerModalOpen.value = status === 'dual-owner';
    },
);
</script>

<template>
    <Head :title="t('home.meta.title')" />

    <div class="relative min-h-screen overflow-hidden bg-[#f3ead9] text-[#171411]">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute inset-x-0 top-0 h-64 bg-[radial-gradient(circle_at_top,rgba(255,153,70,0.22),transparent_58%)]" />
            <div class="absolute right-[-8rem] top-28 h-72 w-72 rounded-full bg-[#c8602b]/15 blur-3xl" />
            <div class="absolute left-[-6rem] bottom-0 h-80 w-80 rounded-full bg-[#506e54]/12 blur-3xl" />
        </div>

        <div class="relative mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 lg:px-10">
            <header class="flex items-start justify-between gap-6">
                <Link href="/" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.32em] text-[#8b4a27]">
                        {{ t('common.app_name') }}
                    </p>
                    <p class="mt-2 text-sm text-[#5f574f]">
                        {{ authUser ? 'Tableau de bord' : t('home.header.tagline') }}
                    </p>
                </Link>

                <div class="flex items-center gap-3">
                    <div class="hidden md:block">
                        <LanguageSwitcher />
                    </div>

                    <template v-if="authUser">
                        <div class="relative">
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
                                class="absolute right-0 z-20 mt-3 max-h-[calc(100vh-6rem)] w-[calc(100vw-2rem)] max-w-[26rem] overflow-y-auto rounded-[18px] border border-[#1f1a14]/10 bg-[#fffdf8] p-4 shadow-[0_24px_90px_rgba(44,32,20,0.18)]"
                            >
                                <div class="flex items-start justify-between gap-4 border-b border-[#1f1a14]/10 pb-4">
                                    <div>
                                        <p class="text-sm font-semibold text-[#1f1a14]">{{ authUser.name }}</p>
                                        <p class="mt-1 text-xs text-[#6b6258]">{{ authUser.email }}</p>
                                    </div>
                                    <Link
                                        as="button"
                                        method="post"
                                        href="/logout"
                                        class="rounded-full bg-[#1f1a14] px-3 py-2 text-xs font-medium text-[#f7efe1] transition hover:bg-[#8b4a27]"
                                    >
                                        {{ t('common.logout') }}
                                    </Link>
                                </div>

                                <div class="py-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8b4a27]">Ajouter un monde</p>
                                    <div class="mt-3 flex gap-2">
                                        <select
                                            v-model="worldToAdd"
                                            class="min-w-0 flex-1 rounded-xl border border-[#1f1a14]/10 bg-white px-3 py-2 text-sm text-[#1f1a14] outline-none"
                                        >
                                            <option value="">Choisir un monde</option>
                                            <option v-for="world in worldsAvailableToAdd" :key="world.key" :value="world.key">
                                                {{ world.name }}
                                            </option>
                                        </select>
                                        <button
                                            type="button"
                                            class="rounded-xl bg-[#8b4a27] px-3 py-2 text-sm font-medium text-white transition hover:bg-[#6d3418] disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="!worldToAdd"
                                            @click="addWorld"
                                        >
                                            Ajouter
                                        </button>
                                    </div>
                                </div>

                                <div class="grid gap-2 border-t border-[#1f1a14]/10 pt-4">
                                    <Link
                                        href="/account"
                                        class="rounded-xl px-3 py-2 text-sm font-medium text-[#1f1a14] transition hover:bg-[#f2eadc]"
                                    >
                                        Compte
                                    </Link>
                                    <Link
                                        v-if="authUser.is_admin"
                                        href="/admin"
                                        class="rounded-xl px-3 py-2 text-sm font-medium text-[#1f1a14] transition hover:bg-[#f2eadc]"
                                    >
                                        Administration
                                    </Link>
                                    <Link
                                        :href="toolHref('/inactive-finder')"
                                        class="rounded-xl px-3 py-2 text-sm font-medium text-[#1f1a14] transition hover:bg-[#f2eadc]"
                                    >
                                        Chercheur d'inactifs
                                    </Link>
                                    <Link
                                        :href="toolHref('/map-builder')"
                                        class="rounded-xl px-3 py-2 text-sm font-medium text-[#1f1a14] transition hover:bg-[#f2eadc]"
                                    >
                                        Créateur de carte
                                    </Link>
                                    <Link
                                        :href="toolHref('/troops')"
                                        class="rounded-xl px-3 py-2 text-sm font-medium text-[#1f1a14] transition hover:bg-[#f2eadc]"
                                    >
                                        Troupes
                                    </Link>
                                    <Link
                                        :href="toolHref('/travel-calculator')"
                                        class="rounded-xl px-3 py-2 text-sm font-medium text-[#1f1a14] transition hover:bg-[#f2eadc]"
                                    >
                                        Calculateur de trajets
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <nav class="flex items-center gap-3">
                            <Link href="/login" class="rounded-full border border-[#1f1a14]/10 px-4 py-2 text-sm font-medium text-[#3b3129] transition hover:bg-white/60">
                                {{ t('common.login') }}
                            </Link>
                            <Link href="/register" class="hidden rounded-full bg-[#1f1a14] px-4 py-2 text-sm font-medium text-[#f7efe1] transition hover:bg-[#8b4a27] md:inline-flex">
                                {{ t('common.create_account') }}
                            </Link>
                        </nav>
                    </template>
                </div>
            </header>

            <main class="flex flex-1 py-10 lg:py-14">
                <div class="grid w-full gap-8 lg:grid-cols-[1.1fr_0.9fr] lg:items-start">
                    <section>
                        <div class="mt-2 md:hidden">
                            <LanguageSwitcher />
                        </div>

                        <template v-if="authUser">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#8b4a27]">Mon monde actif</p>
                            <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] text-[#171411] sm:text-5xl">
                                {{ selectedWorld?.name ?? 'Sélectionne un monde' }}
                            </h1>
                            <p class="mt-4 max-w-2xl text-base leading-7 text-[#544b43]">
                                Choisis tes mondes suivis ici. Les outils ouvriront automatiquement le monde sélectionné.
                            </p>

                            <div class="mt-7 flex flex-wrap gap-3">
                                <Link
                                    :href="toolHref('/inactive-finder')"
                                    class="rounded-full bg-[#1f1a14] px-5 py-3 text-sm font-medium text-[#f7efe1] transition hover:bg-[#8b4a27]"
                                >
                                    Chercher les inactifs
                                </Link>
                                <Link
                                    :href="toolHref('/map-builder')"
                                    class="rounded-full border border-[#1f1a14]/10 bg-white/70 px-5 py-3 text-sm font-medium text-[#1f1a14] transition hover:bg-white"
                                >
                                    Ouvrir la carte
                                </Link>
                                <Link
                                    :href="toolHref('/troops')"
                                    class="rounded-full border border-[#1f1a14]/10 bg-white/70 px-5 py-3 text-sm font-medium text-[#1f1a14] transition hover:bg-white"
                                >
                                    Voir les troupes
                                </Link>
                                <Link
                                    :href="toolHref('/travel-calculator')"
                                    class="rounded-full border border-[#1f1a14]/10 bg-white/70 px-5 py-3 text-sm font-medium text-[#1f1a14] transition hover:bg-white"
                                >
                                    Calculer un trajet
                                </Link>
                            </div>

                            <section class="mt-9">
                                <div class="flex items-center justify-between gap-4">
                                    <h2 class="text-lg font-semibold text-[#1f1a14]">Mes mondes</h2>
                                    <span class="text-sm text-[#6b6258]">{{ myWorlds.length }} sélectionné(s)</span>
                                </div>

                                <div class="mt-4 grid gap-3">
                                    <article
                                        v-for="world in myWorlds"
                                        :key="world.key"
                                        class="flex flex-wrap items-center justify-between gap-3 rounded-[18px] border bg-white/75 px-4 py-3 shadow-sm"
                                        :class="world.key === selectedWorld?.key ? 'border-[#8b4a27]/40' : 'border-[#1f1a14]/10'"
                                    >
                                        <div>
                                            <p class="font-medium text-[#1f1a14]">{{ world.name }}</p>
                                            <p class="mt-1 text-xs text-[#6b6258]">{{ world.key }}</p>
                                        </div>
                                        <div class="flex gap-2">
                                            <button
                                                type="button"
                                                class="rounded-full border border-[#1f1a14]/10 px-3 py-2 text-xs font-medium text-[#1f1a14] transition hover:bg-[#f2eadc]"
                                                @click="selectWorld(world.key)"
                                            >
                                                Sélectionner
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-full px-3 py-2 text-xs font-medium text-[#8b4a27] transition hover:bg-[#f2eadc]"
                                                @click="removeWorld(world.key)"
                                            >
                                                Retirer
                                            </button>
                                        </div>
                                    </article>

                                    <div v-if="myWorlds.length === 0" class="rounded-[18px] border border-dashed border-[#1f1a14]/20 bg-white/55 px-4 py-5 text-sm text-[#5f574f]">
                                        Ouvre le menu en haut à droite pour ajouter ton premier monde.
                                    </div>
                                </div>
                            </section>

                            <section class="mt-9">
                                <div class="flex items-center justify-between gap-4">
                                    <h2 class="text-lg font-semibold text-[#1f1a14]">Mon compte</h2>
                                    <span v-if="selectedWorld" class="text-sm text-[#6b6258]">{{ selectedWorld.name }}</span>
                                </div>

                                <div class="mt-4 grid gap-3 rounded-[18px] border border-[#1f1a14]/10 bg-white/65 p-4">
                                    <template v-if="!selectedWorld">
                                        <p class="text-sm text-[#5f574f]">Sélectionne un monde avant d'ajouter ton compte.</p>
                                    </template>

                                    <template v-else-if="currentPlayedAccount">
                                        <div class="grid gap-3">
                                            <article class="flex flex-wrap items-center justify-between gap-3 rounded-[18px] border border-[#1f1a14]/10 bg-white px-4 py-3">
                                                <div>
                                                    <p class="font-medium text-[#1f1a14]">{{ currentPlayedAccount.player_name }}</p>
                                                    <p class="mt-1 text-xs text-[#6b6258]">
                                                        {{ selectedWorld.name }}
                                                        <span v-if="!currentPlayedAccount.matched_player"> · non associé aux imports</span>
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="rounded-full px-3 py-2 text-xs font-medium text-[#8b4a27] transition hover:bg-[#f2eadc]"
                                                    @click="removePlayedAccount(currentPlayedAccount)"
                                                >
                                                    Retirer
                                                </button>
                                            </article>

                                            <div
                                                v-if="currentPlayedAccount.invite_code"
                                                class="rounded-[18px] border border-[#1f1a14]/10 bg-[#fffaf2] px-4 py-3"
                                            >
                                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#8b4a27]">Code dual</p>
                                                <p class="mt-2 break-all font-mono text-sm text-[#1f1a14]">{{ currentPlayedAccount.invite_code }}</p>
                                            </div>
                                        </div>
                                    </template>

                                    <template v-else>
                                        <div class="grid gap-3 rounded-[16px] border border-[#1f1a14]/10 bg-white/70 p-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                                            <input
                                                v-model="playedAccountInviteCode"
                                                type="text"
                                                maxlength="64"
                                                placeholder="Code dual"
                                                autocomplete="off"
                                                class="w-full min-w-0 rounded-2xl border border-[#1f1a14]/10 bg-white px-4 py-3 text-sm text-[#1f1a14] outline-none"
                                            />
                                            <button
                                                type="button"
                                                class="rounded-2xl border border-[#1f1a14]/10 bg-white px-4 py-3 text-sm font-medium text-[#1f1a14] transition hover:bg-[#f2eadc] disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="!playedAccountInviteCode.trim()"
                                                @click="joinPlayedAccount"
                                            >
                                                Rejoindre
                                            </button>
                                            <p v-if="joinPlayedAccountError" class="text-sm text-[#8b4a27] lg:col-span-2">
                                                {{ joinPlayedAccountError }}
                                            </p>
                                        </div>

                                        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                                            <div class="relative min-w-0">
                                                <input
                                                    v-model="playedAccountName"
                                                    type="text"
                                                    maxlength="255"
                                                    placeholder="Nom exact du joueur"
                                                    autocomplete="off"
                                                    class="w-full min-w-0 rounded-2xl border border-[#1f1a14]/10 bg-white px-4 py-3 text-sm text-[#1f1a14] outline-none"
                                                />
                                                <div
                                                    v-if="playerSuggestions.length > 0"
                                                    class="absolute left-0 right-0 z-10 mt-2 overflow-hidden rounded-2xl border border-[#1f1a14]/10 bg-white shadow-[0_18px_45px_rgba(44,32,20,0.14)]"
                                                >
                                                    <button
                                                        v-for="player in playerSuggestions"
                                                        :key="player.id"
                                                        type="button"
                                                        class="block w-full px-4 py-3 text-left text-sm transition hover:bg-[#f2eadc]"
                                                        @click="pickPlayerSuggestion(player)"
                                                    >
                                                        <span class="block font-medium text-[#1f1a14]">{{ player.name }}</span>
                                                        <span class="mt-1 block text-xs text-[#6b6258]">
                                                            {{ player.population.toLocaleString('fr-CA') }} population · {{ player.villages }} village(s)
                                                        </span>
                                                    </button>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                class="rounded-2xl bg-[#8b4a27] px-4 py-3 text-sm font-medium text-white transition hover:bg-[#6d3418] disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="!playedAccountName.trim()"
                                                @click="addPlayedAccount"
                                            >
                                                Ajouter
                                            </button>
                                        </div>

                                        <p class="text-sm text-[#5f574f]">
                                            <span v-if="playerSearchLoading">Recherche en cours...</span>
                                            <span v-else>Le nom est recherché dans les joueurs importés de {{ selectedWorld.name }}.</span>
                                        </p>
                                    </template>
                                </div>
                            </section>
                        </template>

                        <template v-else>
                            <div class="inline-flex items-center gap-2 rounded-full border border-[#8b4a27]/15 bg-white/60 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-[#8b4a27] shadow-sm backdrop-blur">
                                <span class="h-2 w-2 rounded-full bg-[#ff7a1a]" />
                                {{ t('home.hero.badge') }}
                            </div>

                            <h1 class="mt-8 max-w-4xl text-5xl font-semibold tracking-[-0.04em] text-[#171411] sm:text-6xl lg:text-7xl">
                                {{ t('home.hero.title_before') }}
                                <span class="text-[#8b4a27]">{{ t('home.hero.title_highlight') }}</span>
                                {{ t('home.hero.title_after') }}
                            </h1>

                            <p class="mt-6 max-w-2xl text-lg leading-8 text-[#544b43]">
                                {{ t('home.hero.description') }}
                            </p>
                        </template>
                    </section>

                    <section class="grid gap-4">
                        <article
                            v-for="card in actionCards"
                            :key="card.key"
                            class="group overflow-hidden rounded-[24px] border border-[#1f1a14]/10 bg-white/75 p-6 shadow-[0_24px_80px_rgba(74,49,22,0.08)] backdrop-blur"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#8a7b6a]">
                                        {{ t(`home.cards.${card.key}.eyebrow`) }}
                                    </p>
                                    <h2 class="mt-3 text-2xl font-semibold tracking-[-0.03em] text-[#1d1814]">
                                        {{ t(`home.cards.${card.key}.title`) }}
                                    </h2>
                                </div>

                                <div class="h-12 w-12 shrink-0 rounded-2xl bg-gradient-to-br" :class="card.accent" />
                            </div>

                            <p class="mt-4 max-w-md text-sm leading-7 text-[#5f574f]">
                                {{ t(`home.cards.${card.key}.description`) }}
                            </p>

                            <Link
                                :href="card.href"
                                class="mt-6 inline-flex items-center gap-2 rounded-full border border-[#1f1a14]/10 bg-[#fffdf8] px-4 py-2 text-sm font-medium text-[#1f1a14] transition group-hover:border-[#8b4a27]/35 group-hover:bg-[#fff7ec]"
                            >
                                {{ t(`home.cards.${card.key}.cta`) }}
                                <span aria-hidden="true">↗</span>
                            </Link>
                        </article>
                    </section>
                </div>
            </main>

            <footer class="border-t border-[#1f1a14]/10 pt-6 text-sm text-[#5f574f]">
                <p>{{ t('home.footer.line_3') }}</p>
            </footer>
        </div>

        <div
            v-if="ownerModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#0d1318]/70 px-6 py-10 backdrop-blur-sm"
            @click.self="ownerModalOpen = false"
        >
            <div class="w-full max-w-md rounded-[28px] border border-[#1f1a14]/10 bg-white p-6 shadow-[0_24px_80px_rgba(15,19,24,0.25)] sm:p-7">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8b4a27]">Dual</p>
                <h3 class="mt-3 text-2xl font-semibold tracking-[-0.03em] text-[#1c1814]">
                    Tu es déjà le propriétaire du compte.
                </h3>
                <p class="mt-4 text-sm leading-7 text-[#5b5047]">
                    Ce code dual appartient déjà au compte de jeu lié à ton compte Travtool.
                </p>
                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="rounded-full bg-[#1f1a14] px-5 py-3 text-sm font-medium text-[#f7efe1] transition hover:bg-[#8b4a27]"
                        @click="ownerModalOpen = false"
                    >
                        OK
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
