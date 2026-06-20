<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import type { User as AuthUser } from '@/types';

type OwnedPlayedAccount = {
    id: number;
    world_key: string;
    name: string;
    invite_code: string | null;
    duals: AccountDual[];
};

type AccountDual = {
    membership_id: number;
    name: string;
    email: string | null;
    joined_at: string | null;
};

const props = defineProps<{
    ownedPlayedAccounts: OwnedPlayedAccount[];
}>();

const page = usePage<{
    auth: { user: AuthUser | null };
    flash?: { status?: string | null };
}>();
const flashStatus = () => page.props.flash?.status ?? null;
const ownerModalOpen = ref(page.props.flash?.status === 'dual-owner');
const pendingDualRemoval = ref<AccountDual | null>(null);

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const dualForm = useForm({
    invite_code: '',
});

watch(
    () => page.props.flash?.status,
    (status) => {
        ownerModalOpen.value = status === 'dual-owner';
    },
);

const updatePassword = (): void => {
    passwordForm.put('/account/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
        },
        onFinish: () => {
            passwordForm.reset('current_password', 'password', 'password_confirmation');
        },
    });
};

const joinDual = (): void => {
    dualForm.post('/played-accounts/join', {
        preserveScroll: true,
        onSuccess: () => {
            dualForm.reset();
        },
    });
};

const requestDualRemoval = (dual: AccountDual): void => {
    pendingDualRemoval.value = dual;
};

const cancelDualRemoval = (): void => {
    pendingDualRemoval.value = null;
};

const confirmDualRemoval = (): void => {
    if (!pendingDualRemoval.value) {
        return;
    }

    router.delete(`/account/duals/${pendingDualRemoval.value.membership_id}`, {
        preserveScroll: true,
        onFinish: () => {
            pendingDualRemoval.value = null;
        },
    });
};
</script>

<template>
    <Head title="Compte" />

    <div class="min-h-screen bg-[#f3ead9] text-[#171411]">
        <div class="mx-auto flex min-h-screen max-w-5xl flex-col px-6 py-8 lg:px-10">
            <header class="flex items-start justify-between gap-6">
                <Link href="/" class="block">
                    <p class="text-xs font-semibold uppercase tracking-[0.32em] text-[#8b4a27]">Travtool</p>
                    <p class="mt-2 text-sm text-[#5f574f]">Compte</p>
                </Link>

                <div class="flex items-center gap-3">
                    <div class="hidden md:block">
                        <LanguageSwitcher />
                    </div>
                    <Link
                        href="/"
                        class="rounded-full border border-[#1f1a14]/10 bg-white/70 px-4 py-2 text-sm font-medium text-[#1f1a14] transition hover:bg-white"
                    >
                        Tableau de bord
                    </Link>
                    <Link
                        href="/troops"
                        class="rounded-full border border-[#1f1a14]/10 bg-white/70 px-4 py-2 text-sm font-medium text-[#1f1a14] transition hover:bg-white"
                    >
                        Troupes
                    </Link>
                </div>
            </header>

            <main class="flex-1 py-10">
                <div class="md:hidden">
                    <LanguageSwitcher />
                </div>

                <div class="mt-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#8b4a27]">Paramètres</p>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] text-[#171411] sm:text-5xl">
                        Compte
                    </h1>
                    <p v-if="page.props.auth.user" class="mt-4 text-sm text-[#5f574f]">
                        {{ page.props.auth.user.name }} · {{ page.props.auth.user.email }}
                    </p>
                </div>

                <div class="mt-9 grid gap-6">
                    <section class="rounded-[24px] border border-[#1f1a14]/10 bg-white/75 p-6 shadow-[0_24px_80px_rgba(74,49,22,0.08)]">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8b4a27]">Mon compte</p>
                                <h2 class="mt-3 text-2xl font-semibold tracking-[-0.03em] text-[#1f1a14]">
                                    Mot de passe
                                </h2>
                            </div>
                            <p v-if="passwordForm.recentlySuccessful" class="rounded-full bg-[#456f5b]/12 px-3 py-2 text-sm font-medium text-[#456f5b]">
                                Mot de passe mis à jour
                            </p>
                        </div>

                        <form class="mt-6 grid gap-4" @submit.prevent="updatePassword">
                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-[#544b43]">Ancien mot de passe</span>
                                <input
                                    v-model="passwordForm.current_password"
                                    type="password"
                                    autocomplete="current-password"
                                    class="w-full rounded-2xl border border-[#1f1a14]/10 bg-[#faf6ee] px-4 py-3 text-[#1b1815] outline-none transition focus:border-[#8b4a27]/40 focus:bg-white"
                                />
                                <p v-if="passwordForm.errors.current_password" class="mt-2 text-sm text-[#b94b39]">
                                    {{ passwordForm.errors.current_password }}
                                </p>
                            </label>

                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-[#544b43]">Nouveau mot de passe</span>
                                    <input
                                        v-model="passwordForm.password"
                                        type="password"
                                        autocomplete="new-password"
                                        class="w-full rounded-2xl border border-[#1f1a14]/10 bg-[#faf6ee] px-4 py-3 text-[#1b1815] outline-none transition focus:border-[#8b4a27]/40 focus:bg-white"
                                    />
                                    <p v-if="passwordForm.errors.password" class="mt-2 text-sm text-[#b94b39]">
                                        {{ passwordForm.errors.password }}
                                    </p>
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-[#544b43]">Confirmer le nouveau mot de passe</span>
                                    <input
                                        v-model="passwordForm.password_confirmation"
                                        type="password"
                                        autocomplete="new-password"
                                        class="w-full rounded-2xl border border-[#1f1a14]/10 bg-[#faf6ee] px-4 py-3 text-[#1b1815] outline-none transition focus:border-[#8b4a27]/40 focus:bg-white"
                                    />
                                </label>
                            </div>

                            <div>
                                <button
                                    type="submit"
                                    class="rounded-full bg-[#1f1a14] px-5 py-3 text-sm font-medium text-[#f7efe1] transition hover:bg-[#8b4a27] disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="passwordForm.processing"
                                >
                                    Enregistrer
                                </button>
                            </div>
                        </form>
                    </section>

                    <section class="rounded-[24px] border border-[#1f1a14]/10 bg-white/75 p-6 shadow-[0_24px_80px_rgba(74,49,22,0.08)]">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8b4a27]">Dual</p>
                        <h2 class="mt-3 text-2xl font-semibold tracking-[-0.03em] text-[#1f1a14]">
                            Rejoindre un compte joué
                        </h2>

                        <form class="mt-6 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]" @submit.prevent="joinDual">
                            <label class="block min-w-0">
                                <span class="mb-2 block text-sm font-medium text-[#544b43]">Code dual</span>
                                <input
                                    v-model="dualForm.invite_code"
                                    type="text"
                                    maxlength="64"
                                    autocomplete="off"
                                    class="w-full rounded-2xl border border-[#1f1a14]/10 bg-[#faf6ee] px-4 py-3 text-[#1b1815] outline-none transition focus:border-[#8b4a27]/40 focus:bg-white"
                                />
                                <p v-if="dualForm.errors.invite_code" class="mt-2 text-sm text-[#b94b39]">
                                    {{ dualForm.errors.invite_code }}
                                </p>
                            </label>

                            <div class="flex items-end">
                                <button
                                    type="submit"
                                    class="w-full rounded-2xl bg-[#8b4a27] px-5 py-3 text-sm font-medium text-white transition hover:bg-[#6d3418] disabled:cursor-not-allowed disabled:opacity-60 md:w-auto"
                                    :disabled="dualForm.processing || !dualForm.invite_code.trim()"
                                >
                                    Rejoindre
                                </button>
                            </div>
                        </form>

                        <p v-if="flashStatus() === 'dual-joined'" class="mt-4 rounded-[16px] bg-[#456f5b]/12 px-4 py-3 text-sm font-medium text-[#456f5b]">
                            Compte dual rejoint. Le monde lié est maintenant dans tes mondes.
                        </p>
                        <p v-if="flashStatus() === 'dual-revoked'" class="mt-4 rounded-[16px] bg-[#456f5b]/12 px-4 py-3 text-sm font-medium text-[#456f5b]">
                            Accès dual retiré.
                        </p>

                        <div class="mt-8 border-t border-[#1f1a14]/10 pt-6">
                            <h3 class="text-lg font-semibold text-[#1f1a14]">Mes invitations duals</h3>

                            <div v-if="props.ownedPlayedAccounts.length > 0" class="mt-4 grid gap-4">
                                <article
                                    v-for="account in props.ownedPlayedAccounts"
                                    :key="account.id"
                                    class="rounded-[18px] border border-[#1f1a14]/10 bg-white px-4 py-4"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p class="font-medium text-[#1f1a14]">{{ account.name }}</p>
                                            <p class="mt-1 text-xs text-[#6b6258]">{{ account.world_key }}</p>
                                        </div>
                                        <div v-if="account.invite_code" class="min-w-0 rounded-[14px] bg-[#fffaf2] px-3 py-2">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-[#8b4a27]">Code dual</p>
                                            <p class="mt-1 break-all font-mono text-xs text-[#1f1a14]">{{ account.invite_code }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid gap-2">
                                        <div
                                            v-for="dual in account.duals"
                                            :key="dual.membership_id"
                                            class="flex flex-wrap items-center justify-between gap-3 rounded-[14px] border border-[#1f1a14]/10 bg-[#faf6ee] px-3 py-3"
                                        >
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-[#1f1a14]">{{ dual.name }}</p>
                                                <p v-if="dual.email" class="mt-1 truncate text-xs text-[#6b6258]">{{ dual.email }}</p>
                                            </div>
                                            <button
                                                type="button"
                                                class="rounded-full px-3 py-2 text-xs font-medium text-[#8b4a27] transition hover:bg-[#f2eadc]"
                                                @click="requestDualRemoval(dual)"
                                            >
                                                Retirer l'accès
                                            </button>
                                        </div>

                                        <p v-if="account.duals.length === 0" class="rounded-[14px] border border-dashed border-[#1f1a14]/15 px-3 py-3 text-sm text-[#6b6258]">
                                            Aucun dual n'a encore rejoint ce compte.
                                        </p>
                                    </div>
                                </article>
                            </div>

                            <p v-else class="mt-4 rounded-[16px] border border-dashed border-[#1f1a14]/15 px-4 py-4 text-sm text-[#6b6258]">
                                Tu n'es propriétaire d'aucun compte joué pour le moment.
                            </p>
                        </div>
                    </section>
                </div>
            </main>
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

        <div
            v-if="pendingDualRemoval"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#0d1318]/70 px-6 py-10 backdrop-blur-sm"
            @click.self="cancelDualRemoval"
        >
            <div class="w-full max-w-md rounded-[28px] border border-[#1f1a14]/10 bg-white p-6 shadow-[0_24px_80px_rgba(15,19,24,0.25)] sm:p-7">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8b4a27]">Retirer un dual</p>
                <h3 class="mt-3 text-2xl font-semibold tracking-[-0.03em] text-[#1c1814]">
                    Retirer l'accès de {{ pendingDualRemoval.name }}?
                </h3>
                <p class="mt-4 text-sm leading-7 text-[#5b5047]">
                    Ce compte Travtool ne sera plus lié à ton compte de jeu et perdra les accès associés.
                </p>
                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-full border border-[#1f1a14]/10 px-4 py-2 text-sm font-medium text-[#1f1a14] transition hover:bg-[#f7f4ee]"
                        @click="cancelDualRemoval"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        class="rounded-full bg-[#8b4a27] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#6d3418]"
                        @click="confirmDualRemoval"
                    >
                        Retirer
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
