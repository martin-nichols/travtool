<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { User as AuthUser } from '@/types';

type AdminStats = {
    users: number;
    played_accounts: number;
    saved_maps: number;
    active_worlds: number;
    imported_worlds: number;
};

type AdminPlayedAccount = {
    id: number;
    world_key: string;
    player_name: string;
    matched_player: boolean;
    updated_at: string | null;
};

type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    last_login_at: string | null;
    last_login_ip: string | null;
    last_world_key: string | null;
    created_at: string | null;
    played_accounts_count: number;
    maps_count: number;
    played_accounts: AdminPlayedAccount[];
};

const props = defineProps<{
    stats: AdminStats;
    users: AdminUser[];
}>();

const page = usePage<{ auth: { user: AuthUser | null } }>();
const authUser = computed(() => page.props.auth.user);

const dateFormatter = new Intl.DateTimeFormat('fr-CA', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const formatDate = (value: string | null): string => {
    if (!value) {
        return 'Jamais';
    }

    return dateFormatter.format(new Date(value));
};

const statCards = computed(() => [
    { label: 'Comptes', value: props.stats.users },
    { label: 'Comptes joués', value: props.stats.played_accounts },
    { label: 'Cartes enregistrées', value: props.stats.saved_maps },
    { label: 'Mondes actifs', value: props.stats.active_worlds },
    { label: 'Mondes importés', value: props.stats.imported_worlds },
]);
</script>

<template>
    <Head title="Administration" />

    <div class="min-h-screen bg-[#f5f0e5] text-[#191511]">
        <div class="mx-auto max-w-7xl px-6 py-8 lg:px-10">
            <header class="flex flex-col gap-5 border-b border-[#1f1a14]/10 pb-6 md:flex-row md:items-start md:justify-between">
                <div>
                    <Link href="/" class="text-sm font-medium text-[#6a5d52] transition hover:text-[#8b4a27]">
                        Retour
                    </Link>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] text-[#1c1814]">
                        Administration
                    </h1>
                    <p class="mt-3 text-sm text-[#5b5047]">
                        {{ authUser?.email }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Link
                        href="/troops"
                        class="inline-flex items-center justify-center rounded-full border border-[#1f1a14]/10 bg-white/70 px-5 py-3 text-sm font-medium text-[#1f1a14] transition hover:bg-white"
                    >
                        Troupes
                    </Link>
                    <Link
                        as="button"
                        method="post"
                        href="/logout"
                        class="inline-flex items-center justify-center rounded-full bg-[#1f1a14] px-5 py-3 text-sm font-medium text-[#f7efe1] transition hover:bg-[#8b4a27]"
                    >
                        Déconnexion
                    </Link>
                </div>
            </header>

            <section class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <article
                    v-for="card in statCards"
                    :key="card.label"
                    class="rounded-[18px] border border-[#1f1a14]/10 bg-white px-5 py-4 shadow-sm"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#8b4a27]">{{ card.label }}</p>
                    <p class="mt-3 text-3xl font-semibold text-[#1f1a14]">{{ card.value }}</p>
                </article>
            </section>

            <section class="mt-8 overflow-hidden rounded-[24px] border border-[#1f1a14]/10 bg-white shadow-[0_20px_60px_rgba(56,43,27,0.07)]">
                <div class="border-b border-[#1f1a14]/10 px-5 py-4">
                    <h2 class="text-lg font-semibold text-[#1f1a14]">Utilisateurs</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[1100px] divide-y divide-[#1f1a14]/10 text-sm">
                        <thead class="bg-[#fcf7ee] text-left text-xs font-semibold uppercase tracking-[0.16em] text-[#7f6d5d]">
                            <tr>
                                <th class="px-5 py-3">Compte</th>
                                <th class="px-5 py-3">Dernière connexion</th>
                                <th class="px-5 py-3">Monde actif</th>
                                <th class="px-5 py-3">Comptes joués</th>
                                <th class="px-5 py-3">Cartes</th>
                                <th class="px-5 py-3">Création</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#1f1a14]/10">
                            <tr v-for="user in users" :key="user.id">
                                <td class="px-5 py-4 align-top">
                                    <div class="font-semibold text-[#1f1a14]">
                                        {{ user.name }}
                                        <span v-if="user.is_admin" class="ml-2 rounded-full bg-[#1f1a14] px-2 py-1 text-[10px] font-medium text-white">ADMIN</span>
                                    </div>
                                    <div class="mt-1 text-xs text-[#6b6258]">{{ user.email }}</div>
                                </td>
                                <td class="px-5 py-4 align-top whitespace-nowrap">
                                    <div>{{ formatDate(user.last_login_at) }}</div>
                                    <div class="mt-1 text-xs text-[#6b6258]">{{ user.last_login_ip ?? 'IP inconnue' }}</div>
                                </td>
                                <td class="px-5 py-4 align-top">{{ user.last_world_key ?? '-' }}</td>
                                <td class="px-5 py-4 align-top">
                                    <div class="font-medium">{{ user.played_accounts_count }}</div>
                                    <div class="mt-2 grid gap-1 text-xs text-[#6b6258]">
                                        <div v-for="account in user.played_accounts" :key="account.id">
                                            {{ account.world_key }} · {{ account.player_name }}
                                            <span v-if="!account.matched_player">(non associé)</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 align-top">{{ user.maps_count }}</td>
                                <td class="px-5 py-4 align-top whitespace-nowrap">{{ formatDate(user.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>
