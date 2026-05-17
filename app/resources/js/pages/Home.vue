<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const actionCards = [
    {
        href: '/inactive-finder',
        eyebrow: 'Prototype V1',
        title: "Chercheur d'inactifs",
        description:
            "Préparer le moteur map.sql, les filtres utiles et les premiers signaux pour repérer les cibles à faible activité.",
        accent: 'from-[#ff7a1a] to-[#ffb36b]',
        cta: 'Ouvrir le prototype',
    },
    {
        href: '/login',
        eyebrow: 'Accès privé',
        title: 'Connexion',
        description:
            "Accéder à l’espace de travail Travtool, aux mondes suivis et plus tard aux outils réservés à l’équipe.",
        accent: 'from-[#456f5b] to-[#9fc5a3]',
        cta: "Aller à l'interface",
    },
];
</script>

<template>
    <Head title="Travtool" />

    <div class="min-h-screen overflow-hidden bg-[#f3ead9] text-[#171411]">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute inset-x-0 top-0 h-64 bg-[radial-gradient(circle_at_top,rgba(255,153,70,0.22),transparent_58%)]" />
            <div class="absolute right-[-8rem] top-28 h-72 w-72 rounded-full bg-[#c8602b]/15 blur-3xl" />
            <div class="absolute left-[-6rem] bottom-0 h-80 w-80 rounded-full bg-[#506e54]/12 blur-3xl" />
            <div
                class="absolute inset-0 opacity-30"
                style="
                    background-image:
                        linear-gradient(rgba(23, 20, 17, 0.05) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(23, 20, 17, 0.05) 1px, transparent 1px);
                    background-size: 36px 36px;
                "
            />
        </div>

        <div class="relative mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 lg:px-10">
            <header class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.32em] text-[#8b4a27]">
                        Travtool
                    </p>
                    <p class="mt-2 text-sm text-[#5f574f]">
                        Intelligence Travian, ingestion `map.sql`, outils privés et futurs modules communautaires.
                    </p>
                </div>

                <nav class="hidden items-center gap-3 md:flex">
                    <Link
                        href="/inactive-finder"
                        class="rounded-full border border-[#1f1a14]/10 px-4 py-2 text-sm font-medium text-[#3b3129] transition hover:border-[#8b4a27]/40 hover:bg-white/60"
                    >
                        Chercheur d'inactifs
                    </Link>
                    <Link
                        href="/login"
                        class="rounded-full bg-[#1f1a14] px-4 py-2 text-sm font-medium text-[#f7efe1] transition hover:bg-[#8b4a27]"
                    >
                        Connexion
                    </Link>
                </nav>
            </header>

            <main class="flex flex-1 items-center py-10 lg:py-16">
                <div class="grid w-full gap-8 lg:grid-cols-[1.15fr_0.85fr] lg:items-end">
                    <section class="max-w-3xl">
                        <div class="inline-flex items-center gap-2 rounded-full border border-[#8b4a27]/15 bg-white/60 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-[#8b4a27] shadow-sm backdrop-blur">
                            <span class="h-2 w-2 rounded-full bg-[#ff7a1a]" />
                            Build public Travian en cours
                        </div>

                        <h1 class="mt-8 max-w-4xl text-5xl font-semibold tracking-[-0.04em] text-[#171411] sm:text-6xl lg:text-7xl">
                            Une base propre pour transformer
                            <span class="text-[#8b4a27]"> map.sql </span>
                            en outil utile.
                        </h1>

                        <p class="mt-6 max-w-2xl text-lg leading-8 text-[#544b43]">
                            Travtool commence par un chercheur d'inactifs privé, mais l’architecture vise déjà un vrai produit
                            pour la communauté Travian : mondes suivis, imports quotidiens, analyse et interfaces métier.
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <div class="rounded-full bg-white/70 px-4 py-2 text-sm text-[#3b3129] shadow-sm">
                                Laravel + Inertia + Vue
                            </div>
                            <div class="rounded-full bg-white/70 px-4 py-2 text-sm text-[#3b3129] shadow-sm">
                                MariaDB
                            </div>
                            <div class="rounded-full bg-white/70 px-4 py-2 text-sm text-[#3b3129] shadow-sm">
                                Pipeline map.sql
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-4">
                        <article
                            v-for="card in actionCards"
                            :key="card.href"
                            class="group overflow-hidden rounded-[28px] border border-[#1f1a14]/10 bg-white/75 p-6 shadow-[0_24px_80px_rgba(74,49,22,0.08)] backdrop-blur"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#8a7b6a]">
                                        {{ card.eyebrow }}
                                    </p>
                                    <h2 class="mt-3 text-2xl font-semibold tracking-[-0.03em] text-[#1d1814]">
                                        {{ card.title }}
                                    </h2>
                                </div>

                                <div
                                    class="h-12 w-12 shrink-0 rounded-2xl bg-gradient-to-br"
                                    :class="card.accent"
                                />
                            </div>

                            <p class="mt-4 max-w-md text-sm leading-7 text-[#5f574f]">
                                {{ card.description }}
                            </p>

                            <Link
                                :href="card.href"
                                class="mt-6 inline-flex items-center gap-2 rounded-full border border-[#1f1a14]/10 bg-[#fffdf8] px-4 py-2 text-sm font-medium text-[#1f1a14] transition group-hover:border-[#8b4a27]/35 group-hover:bg-[#fff7ec]"
                            >
                                {{ card.cta }}
                                <span aria-hidden="true">↗</span>
                            </Link>
                        </article>
                    </section>
                </div>
            </main>

            <footer class="grid gap-3 border-t border-[#1f1a14]/10 pt-6 text-sm text-[#5f574f] md:grid-cols-3">
                <p>Étape actuelle : landing, accès privé, prototype du chercheur d'inactifs.</p>
                <p>Étape suivante : import brut `map.sql`, staging, snapshots et projection courante.</p>
                <p class="md:text-right">Travtool.farmitrax.ca</p>
            </footer>
        </div>
    </div>
</template>
