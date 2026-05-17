<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const form = reactive({
    email: '',
    password: '',
    remember: true,
});

const submitted = ref(false);

const submit = () => {
    submitted.value = true;
};
</script>

<template>
    <Head title="Connexion" />

    <div class="min-h-screen bg-[#f4efe6] text-[#1b1815]">
        <div class="mx-auto flex min-h-screen max-w-6xl flex-col px-6 py-8 lg:px-10">
            <header class="flex items-center justify-between">
                <Link href="/" class="text-sm font-medium text-[#5d5248] transition hover:text-[#8b4a27]">
                    ← Retour à l'accueil
                </Link>
                <Link
                    href="/inactive-finder"
                    class="rounded-full border border-[#1f1a14]/10 px-4 py-2 text-sm text-[#3d342d] transition hover:border-[#8b4a27]/35 hover:bg-white/70"
                >
                    Voir le prototype
                </Link>
            </header>

            <main class="flex flex-1 items-center py-10">
                <div class="grid w-full overflow-hidden rounded-[32px] border border-[#1f1a14]/10 bg-white shadow-[0_32px_120px_rgba(56,43,27,0.08)] lg:grid-cols-[1.05fr_0.95fr]">
                    <section class="relative overflow-hidden bg-[#1f1a14] px-8 py-10 text-[#f7efe1] lg:px-10 lg:py-12">
                        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,122,26,0.28),transparent_34%)]" />
                        <div class="absolute bottom-[-5rem] left-[-4rem] h-56 w-56 rounded-full bg-[#456f5b]/30 blur-3xl" />

                        <div class="relative">
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#ffb36b]">
                                Espace privé
                            </p>
                            <h1 class="mt-5 max-w-md text-4xl font-semibold tracking-[-0.04em]">
                                Interface de connexion Travtool
                            </h1>
                            <p class="mt-5 max-w-md text-base leading-7 text-[#d8cec1]">
                                Ce premier écran prépare l’accès aux mondes suivis, aux imports `map.sql` et aux outils internes.
                                Le backend d’authentification sera branché ensuite.
                            </p>

                            <div class="mt-10 grid gap-4">
                                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#ffb36b]">
                                        À terme
                                    </p>
                                    <p class="mt-3 text-sm leading-7 text-[#e8dfd2]">
                                        Comptes privés, équipes, mondes suivis et journal des imports quotidiens.
                                    </p>
                                </div>
                                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#ffb36b]">
                                        Aujourd’hui
                                    </p>
                                    <p class="mt-3 text-sm leading-7 text-[#e8dfd2]">
                                        Prototype visuel pour préparer la structure produit avant de brancher l’auth réelle.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="px-8 py-10 lg:px-10 lg:py-12">
                        <div class="max-w-md">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#8b4a27]">
                                Connexion
                            </p>
                            <h2 class="mt-4 text-3xl font-semibold tracking-[-0.04em] text-[#1c1814]">
                                Accéder à l’espace de pilotage
                            </h2>

                            <form class="mt-8 space-y-5" @submit.prevent="submit">
                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-[#544b43]">Adresse e-mail</span>
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        class="w-full rounded-2xl border border-[#1f1a14]/10 bg-[#faf6ee] px-4 py-3 text-[#1b1815] outline-none transition focus:border-[#8b4a27]/40 focus:bg-white"
                                        placeholder="martin@travtool.app"
                                    />
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-[#544b43]">Mot de passe</span>
                                    <input
                                        v-model="form.password"
                                        type="password"
                                        class="w-full rounded-2xl border border-[#1f1a14]/10 bg-[#faf6ee] px-4 py-3 text-[#1b1815] outline-none transition focus:border-[#8b4a27]/40 focus:bg-white"
                                        placeholder="••••••••"
                                    />
                                </label>

                                <label class="flex items-center gap-3 text-sm text-[#544b43]">
                                    <input
                                        v-model="form.remember"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-[#1f1a14]/20 text-[#8b4a27] focus:ring-[#8b4a27]"
                                    />
                                    Se souvenir de cette session
                                </label>

                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-full bg-[#1f1a14] px-5 py-3 text-sm font-medium text-[#f7efe1] transition hover:bg-[#8b4a27]"
                                >
                                    Continuer
                                </button>
                            </form>

                            <div
                                v-if="submitted"
                                class="mt-6 rounded-3xl border border-[#8b4a27]/15 bg-[#fff5e8] p-4 text-sm leading-7 text-[#6a5643]"
                            >
                                Interface prête. L’auth backend n’est pas encore branchée : cette étape servira ensuite à
                                connecter l’accès privé Laravel.
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
</template>
