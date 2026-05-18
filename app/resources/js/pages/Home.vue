<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

const actionCards = [
    { key: 'inactive', href: '/inactive-finder', accent: 'from-[#ff7a1a] to-[#ffb36b]' },
    { key: 'map_builder', href: '/map-builder', accent: 'from-[#3f6d8f] to-[#8ec8e8]' },
    { key: 'login', href: '/login', accent: 'from-[#456f5b] to-[#9fc5a3]' },
];
</script>

<template>
    <Head :title="t('home.meta.title')" />

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
                        {{ t('common.app_name') }}
                    </p>
                    <p class="mt-2 text-sm text-[#5f574f]">
                        {{ t('home.header.tagline') }}
                    </p>
                </div>

                <div class="hidden items-center gap-6 md:flex">
                    <LanguageSwitcher />

                    <nav class="flex items-center gap-3">
                    <Link
                        href="/inactive-finder"
                        class="rounded-full border border-[#1f1a14]/10 px-4 py-2 text-sm font-medium text-[#3b3129] transition hover:border-[#8b4a27]/40 hover:bg-white/60"
                    >
                        {{ t('home.header.inactive_finder') }}
                    </Link>
                    <Link
                        href="/map-builder"
                        class="rounded-full border border-[#1f1a14]/10 px-4 py-2 text-sm font-medium text-[#3b3129] transition hover:border-[#3f6d8f]/40 hover:bg-white/60"
                    >
                        {{ t('home.header.map_builder') }}
                    </Link>
                    <Link
                        href="/login"
                        class="rounded-full bg-[#1f1a14] px-4 py-2 text-sm font-medium text-[#f7efe1] transition hover:bg-[#8b4a27]"
                    >
                        {{ t('home.header.login') }}
                    </Link>
                    </nav>
                </div>
            </header>

            <main class="flex flex-1 items-center py-10 lg:py-16">
                <div class="grid w-full gap-8 lg:grid-cols-[1.15fr_0.85fr] lg:items-end">
                    <section class="max-w-3xl">
                        <div class="inline-flex items-center gap-2 rounded-full border border-[#8b4a27]/15 bg-white/60 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-[#8b4a27] shadow-sm backdrop-blur">
                            <span class="h-2 w-2 rounded-full bg-[#ff7a1a]" />
                            {{ t('home.hero.badge') }}
                        </div>

                        <div class="mt-6 md:hidden">
                            <LanguageSwitcher />
                        </div>

                        <h1 class="mt-8 max-w-4xl text-5xl font-semibold tracking-[-0.04em] text-[#171411] sm:text-6xl lg:text-7xl">
                            {{ t('home.hero.title_before') }}
                            <span class="text-[#8b4a27]">{{ t('home.hero.title_highlight') }}</span>
                            {{ t('home.hero.title_after') }}
                        </h1>

                        <p class="mt-6 max-w-2xl text-lg leading-8 text-[#544b43]">
                            {{ t('home.hero.description') }}
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <div class="rounded-full bg-white/70 px-4 py-2 text-sm text-[#3b3129] shadow-sm">
                                {{ t('home.hero.stack_1') }}
                            </div>
                            <div class="rounded-full bg-white/70 px-4 py-2 text-sm text-[#3b3129] shadow-sm">
                                {{ t('home.hero.stack_2') }}
                            </div>
                            <div class="rounded-full bg-white/70 px-4 py-2 text-sm text-[#3b3129] shadow-sm">
                                {{ t('home.hero.stack_3') }}
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-4">
                        <article
                            v-for="card in actionCards"
                            :key="card.key"
                            class="group overflow-hidden rounded-[28px] border border-[#1f1a14]/10 bg-white/75 p-6 shadow-[0_24px_80px_rgba(74,49,22,0.08)] backdrop-blur"
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

                                <div
                                    class="h-12 w-12 shrink-0 rounded-2xl bg-gradient-to-br"
                                    :class="card.accent"
                                />
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

            <footer class="grid gap-3 border-t border-[#1f1a14]/10 pt-6 text-sm text-[#5f574f] md:grid-cols-3">
                <p>{{ t('home.footer.line_1') }}</p>
                <p>{{ t('home.footer.line_2') }}</p>
                <p class="md:text-right">{{ t('home.footer.line_3') }}</p>
            </footer>
        </div>
    </div>
</template>
