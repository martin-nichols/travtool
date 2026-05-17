<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const stats = [
    { label: 'Mondes suivis', value: '01', detail: 'Un premier monde branché à venir' },
    { label: 'Dernier import', value: '—', detail: 'Pipeline map.sql pas encore raccordé' },
    { label: 'Signaux actifs', value: '—', detail: 'Population, villages et changements à calculer' },
];

const filters = [
    { label: 'Monde', value: 'rof.x2.europe.travian.com' },
    { label: 'Rayon', value: '15 cases autour des villages cibles' },
    { label: 'Heuristique', value: 'Population stable + aucun développement visible' },
];
</script>

<template>
    <Head title="Chercheur d'inactifs" />

    <div class="min-h-screen bg-[#f5f0e5] text-[#191511]">
        <div class="mx-auto max-w-7xl px-6 py-8 lg:px-10">
            <header class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                <div>
                    <Link href="/" class="text-sm font-medium text-[#6a5d52] transition hover:text-[#8b4a27]">
                        ← Retour à l'accueil
                    </Link>
                    <p class="mt-5 text-xs font-semibold uppercase tracking-[0.28em] text-[#8b4a27]">
                        Chercheur d'inactifs
                    </p>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] text-[#1c1814]">
                        Prototype de l’outil map.sql
                    </h1>
                    <p class="mt-4 max-w-3xl text-base leading-8 text-[#5b5047]">
                        Cet écran prépare le futur module : imports quotidiens, snapshots, filtres géographiques et détection
                        des comptes à faible activité.
                    </p>
                </div>

                <Link
                    href="/login"
                    class="inline-flex items-center justify-center rounded-full bg-[#1f1a14] px-5 py-3 text-sm font-medium text-[#f7efe1] transition hover:bg-[#8b4a27]"
                >
                    Aller à la connexion
                </Link>
            </header>

            <section class="mt-10 grid gap-4 lg:grid-cols-3">
                <article
                    v-for="stat in stats"
                    :key="stat.label"
                    class="rounded-[28px] border border-[#1f1a14]/10 bg-white px-6 py-5 shadow-[0_20px_60px_rgba(56,43,27,0.07)]"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#8a7b6a]">
                        {{ stat.label }}
                    </p>
                    <p class="mt-4 text-4xl font-semibold tracking-[-0.05em] text-[#1c1814]">
                        {{ stat.value }}
                    </p>
                    <p class="mt-3 text-sm leading-7 text-[#5d534a]">
                        {{ stat.detail }}
                    </p>
                </article>
            </section>

            <section class="mt-8 grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
                <article class="rounded-[32px] border border-[#1f1a14]/10 bg-[#1f1a14] p-7 text-[#f6ede0]">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#ffb36b]">
                        Filtres cibles
                    </p>

                    <div class="mt-6 grid gap-4">
                        <div
                            v-for="filter in filters"
                            :key="filter.label"
                            class="rounded-3xl border border-white/10 bg-white/5 px-5 py-4"
                        >
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#d8c8b7]">
                                {{ filter.label }}
                            </p>
                            <p class="mt-3 text-sm leading-7 text-[#f6ede0]">
                                {{ filter.value }}
                            </p>
                        </div>
                    </div>
                </article>

                <article class="rounded-[32px] border border-[#1f1a14]/10 bg-white p-7">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#8b4a27]">
                        Zone de résultats
                    </p>

                    <div class="mt-6 rounded-[28px] border border-dashed border-[#8b4a27]/25 bg-[#fcf7ee] p-8">
                        <h2 class="text-2xl font-semibold tracking-[-0.03em] text-[#1c1814]">
                            Les premières listes sortiront ici
                        </h2>
                        <p class="mt-4 max-w-2xl text-sm leading-8 text-[#5b5047]">
                            Une fois le pipeline `map.sql` branché, cette vue présentera les villages filtrés, les deltas de
                            population, les coordonnées, la distance et les scores d’inactivité probables.
                        </p>

                        <ul class="mt-6 grid gap-3 text-sm text-[#463d36]">
                            <li class="rounded-2xl bg-white px-4 py-3">Population stable sur plusieurs snapshots</li>
                            <li class="rounded-2xl bg-white px-4 py-3">Aucun nouveau village détecté</li>
                            <li class="rounded-2xl bg-white px-4 py-3">Hors alliance ou activité faible</li>
                        </ul>
                    </div>
                </article>
            </section>
        </div>
    </div>
</template>
