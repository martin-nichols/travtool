<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import type { User as AuthUser } from '@/types';

type TroopColumn = {
    key: string;
    name: string;
};

type VillageRow = {
    village_name: string;
    total: number;
    troops: Record<string, number>;
};

type Totals = {
    total: number;
    troops: Record<string, number>;
};

type TroopRate = {
    population: number | null;
    crop_consumption: number;
    ratio: number | null;
};

const props = defineProps<{
    selectedWorldKey: string;
    selectedAccount: {
        world_key: string;
        world_name: string;
        player_name: string;
        is_owner: boolean;
    } | null;
    troopColumns: TroopColumn[];
    villages: VillageRow[];
    totals: Totals;
    troopRate: TroopRate;
    lastImportedAt: string | null;
    troopStorageReady: boolean;
}>();

const page = usePage<{
    auth: { user: AuthUser | null };
    flash: { status?: string | null };
    errors: Record<string, string>;
}>();

const authUser = computed(() => page.props.auth.user);
const menuOpen = ref(false);
const importPanelOpen = ref(false);

const form = useForm({
    world_key: props.selectedWorldKey,
    troops_text: '',
});

const numberFormatter = new Intl.NumberFormat();
const rateFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 1,
});
const dateFormatter = new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
});

const hasTroops = computed(() => props.villages.length > 0 && props.troopColumns.length > 0);
const status = computed(() => page.props.flash.status ?? null);
const troopTextError = computed(() => page.props.errors.troops_text ?? null);
const worldError = computed(() => page.props.errors.world_key ?? null);
const lastImportedLabel = computed(() => (props.lastImportedAt ? dateFormatter.format(new Date(props.lastImportedAt)) : null));
const troopRateLabel = computed(() => (props.troopRate.ratio === null ? 'N/D' : `${rateFormatter.format(props.troopRate.ratio)}:1`));
const playedAccountLabel = computed(() =>
    props.selectedAccount ? `${props.selectedAccount.player_name} sur ${props.selectedAccount.world_name}` : 'Aucun compte joué lié',
);

const quantity = (row: VillageRow | Totals, troopKey: string): number => row.troops[troopKey] ?? 0;

function submitTroops(): void {
    form.world_key = props.selectedWorldKey;
    form.post('/troops/import', {
        preserveScroll: true,
        onSuccess: () => {
            form.troops_text = '';
        },
    });
}
</script>

<template>
    <Head title="Troupes" />

    <div class="min-h-screen overflow-x-hidden bg-[#f4efe4] text-[#171411]">
        <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8 xl:px-10">
            <header class="flex min-w-0 items-start justify-between gap-4">
                <div class="min-w-0">
                    <Link href="/" class="text-sm font-medium text-[#6a5d52] transition hover:text-[#8b4a27]">
                        Retour à l'accueil
                    </Link>
                    <h1 class="mt-4 text-4xl font-semibold text-[#1c1814]">
                        Troupes
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-[#62584d]">
                        Colle le contenu de la page Troupes Travian pour mettre à jour les effectifs visibles par les duals du même compte.
                    </p>
                    <p class="mt-3 text-sm font-semibold text-[#1f1a14]">
                        {{ playedAccountLabel }}
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
                                <Link href="/" class="rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-[#f2eadc]">
                                    Accueil
                                </Link>
                                <Link href="/account" class="rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-[#f2eadc]">
                                    Compte
                                </Link>
                                <Link
                                    v-if="authUser.is_admin"
                                    href="/admin"
                                    class="rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-[#f2eadc]"
                                >
                                    Administration
                                </Link>
                                <Link
                                    :href="props.selectedWorldKey ? `/inactive-finder?world=${encodeURIComponent(props.selectedWorldKey)}` : '/inactive-finder'"
                                    class="rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-[#f2eadc]"
                                >
                                    Chercheur d'inactifs
                                </Link>
                                <Link
                                    :href="props.selectedWorldKey ? `/map-builder?world=${encodeURIComponent(props.selectedWorldKey)}` : '/map-builder'"
                                    class="rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-[#f2eadc]"
                                >
                                    Créateur de carte
                                </Link>
                                <Link
                                    :href="props.selectedWorldKey ? `/troops?world=${encodeURIComponent(props.selectedWorldKey)}` : '/troops'"
                                    class="rounded-xl bg-[#f2eadc] px-3 py-2 text-sm font-medium transition hover:bg-[#eadcc8]"
                                >
                                    Troupes
                                </Link>
                                <Link
                                    :href="props.selectedWorldKey ? `/travel-calculator?world=${encodeURIComponent(props.selectedWorldKey)}` : '/travel-calculator'"
                                    class="rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-[#f2eadc]"
                                >
                                    Calculateur de trajets
                                </Link>
                            </div>

                            <Link
                                as="button"
                                method="post"
                                href="/logout"
                                class="w-full rounded-xl bg-[#1f1a14] px-3 py-2 text-sm font-medium text-[#f7efe1] transition hover:bg-[#8b4a27]"
                            >
                                Déconnexion
                            </Link>
                        </div>
                    </div>
                </div>
            </header>

            <main class="grid min-w-0 gap-6 py-8">
                <section class="min-w-0 rounded-[18px] border border-[#1f1a14]/10 bg-white/75 p-4 shadow-sm sm:p-5">
                    <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-semibold text-[#1f1a14]">Charger les troupes</h2>
                            <p class="mt-1 text-sm text-[#6b6258]">
                                Le chargement remplace l'état actuel des troupes pour ce compte joué.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="w-full rounded-full border border-[#1f1a14]/10 bg-white px-5 py-3 text-sm font-medium text-[#1f1a14] transition hover:bg-[#f2eadc] sm:w-auto"
                            @click="importPanelOpen = !importPanelOpen"
                        >
                            {{ importPanelOpen ? 'Masquer le chargeur' : 'Afficher le chargeur' }}
                        </button>
                    </div>

                    <div v-if="importPanelOpen" class="mt-4">
                        <textarea
                            v-model="form.troops_text"
                            class="min-h-64 w-full resize-y rounded-xl border border-[#1f1a14]/10 bg-white px-4 py-3 font-mono text-sm text-[#1f1a14] outline-none transition focus:border-[#8b4a27]/50"
                            placeholder="Colle ici le contenu complet de la page Troupes Travian..."
                        />

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="grid gap-2 text-sm">
                                <p v-if="!props.troopStorageReady" class="text-[#8b4a27]">
                                    La table des troupes n'existe pas encore. Exécute la migration sur le serveur.
                                </p>
                                <p v-if="status" class="text-[#2f6b3f]">{{ status }}</p>
                                <p v-if="troopTextError" class="text-[#8b4a27]">{{ troopTextError }}</p>
                                <p v-if="worldError" class="text-[#8b4a27]">{{ worldError }}</p>
                            </div>

                            <button
                                type="button"
                                class="w-full rounded-full bg-[#1f1a14] px-5 py-3 text-sm font-medium text-[#f7efe1] transition hover:bg-[#8b4a27] disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                                :disabled="!props.troopStorageReady || !props.selectedWorldKey || !form.troops_text.trim() || form.processing"
                                @click="submitTroops"
                            >
                                Charger les troupes
                            </button>
                        </div>
                    </div>
                </section>

                <section class="min-w-0 rounded-[18px] border border-[#1f1a14]/10 bg-white/75 p-4 shadow-sm sm:p-5">
                    <div class="flex min-w-0 flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-semibold text-[#1f1a14]">Total des troupes par village</h2>
                            <p class="mt-1 text-sm text-[#6b6258]">
                                Visible par tous les joueurs liés au même compte.
                            </p>
                            <div v-if="lastImportedLabel" class="mt-3 inline-block rounded-xl border border-[#1f1a14]/10 bg-[#fffaf1] px-4 py-3 text-sm text-[#5f574f]">
                                Dernière mise à jour<br>
                                <span class="font-semibold text-[#1f1a14]">{{ lastImportedLabel }}</span>
                            </div>
                        </div>
                        <div v-if="hasTroops" class="flex flex-wrap items-center gap-2">
                            <div class="rounded-full bg-[#fffaf1] px-4 py-2 text-sm font-semibold text-[#1f1a14]">
                                Taux de troupes {{ troopRateLabel }}
                            </div>
                            <div class="rounded-full bg-[#f2eadc] px-4 py-2 text-sm font-semibold text-[#1f1a14]">
                                Total {{ numberFormatter.format(props.totals.total) }}
                            </div>
                        </div>
                    </div>

                    <div v-if="hasTroops" class="mt-5 max-w-full overflow-x-auto overscroll-x-contain rounded-xl">
                        <table class="min-w-max border-separate border-spacing-0 text-left text-sm">
                            <thead>
                                <tr class="text-xs uppercase tracking-[0.18em] text-[#7a4b2b]">
                                    <th class="sticky left-0 z-10 rounded-l-xl bg-[#f8f1e6] px-4 py-3">Village</th>
                                    <th
                                        v-for="column in props.troopColumns"
                                        :key="column.key"
                                        class="bg-[#f8f1e6] px-4 py-3 text-right"
                                    >
                                        {{ column.name }}
                                    </th>
                                    <th class="rounded-r-xl bg-[#f8f1e6] px-4 py-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="village in props.villages" :key="village.village_name" class="border-b border-[#1f1a14]/10">
                                    <td class="sticky left-0 bg-white px-4 py-3 font-semibold text-[#1f1a14]">
                                        {{ village.village_name }}
                                    </td>
                                    <td
                                        v-for="column in props.troopColumns"
                                        :key="column.key"
                                        class="px-4 py-3 text-right tabular-nums text-[#312a22]"
                                    >
                                        {{ numberFormatter.format(quantity(village, column.key)) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums text-[#1f1a14]">
                                        {{ numberFormatter.format(village.total) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="font-semibold text-[#1f1a14]">
                                    <td class="sticky left-0 rounded-l-xl bg-[#f8f1e6] px-4 py-3">Somme</td>
                                    <td
                                        v-for="column in props.troopColumns"
                                        :key="column.key"
                                        class="bg-[#f8f1e6] px-4 py-3 text-right tabular-nums"
                                    >
                                        {{ numberFormatter.format(quantity(props.totals, column.key)) }}
                                    </td>
                                    <td class="rounded-r-xl bg-[#f8f1e6] px-4 py-3 text-right tabular-nums">
                                        {{ numberFormatter.format(props.totals.total) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div v-else class="mt-5 rounded-xl border border-dashed border-[#1f1a14]/20 bg-[#fffaf1] px-4 py-6 text-sm text-[#6b6258]">
                        Aucune troupe chargée pour ce compte.
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>
