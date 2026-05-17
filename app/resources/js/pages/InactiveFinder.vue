<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

const filters = [
    { key: 'world' },
    { key: 'radius' },
    { key: 'heuristic' },
];

const stats = [
    { key: 'worlds' },
    { key: 'last_import' },
    { key: 'signals' },
];
</script>

<template>
    <Head :title="t('inactive_finder.meta.title')" />

    <div class="min-h-screen bg-[#f5f0e5] text-[#191511]">
        <div class="mx-auto max-w-7xl px-6 py-8 lg:px-10">
            <header class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                <div>
                    <Link href="/" class="text-sm font-medium text-[#6a5d52] transition hover:text-[#8b4a27]">
                        ← {{ t('common.back_home') }}
                    </Link>
                    <p class="mt-5 text-xs font-semibold uppercase tracking-[0.28em] text-[#8b4a27]">
                        {{ t('inactive_finder.hero.eyebrow') }}
                    </p>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] text-[#1c1814]">
                        {{ t('inactive_finder.hero.title') }}
                    </h1>
                    <p class="mt-4 max-w-3xl text-base leading-8 text-[#5b5047]">
                        {{ t('inactive_finder.hero.description') }}
                    </p>
                </div>

                <div class="flex flex-col items-start gap-4 md:items-end">
                    <LanguageSwitcher />
                    <Link
                        href="/login"
                        class="inline-flex items-center justify-center rounded-full bg-[#1f1a14] px-5 py-3 text-sm font-medium text-[#f7efe1] transition hover:bg-[#8b4a27]"
                    >
                        {{ t('common.go_to_login') }}
                    </Link>
                </div>
            </header>

            <section class="mt-10 grid gap-4 lg:grid-cols-3">
                <article
                    v-for="stat in stats"
                    :key="stat.key"
                    class="rounded-[28px] border border-[#1f1a14]/10 bg-white px-6 py-5 shadow-[0_20px_60px_rgba(56,43,27,0.07)]"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#8a7b6a]">
                        {{ t(`inactive_finder.stats.${stat.key}.label`) }}
                    </p>
                    <p class="mt-4 text-4xl font-semibold tracking-[-0.05em] text-[#1c1814]">
                        {{ t(`inactive_finder.stats.${stat.key}.value`) }}
                    </p>
                    <p class="mt-3 text-sm leading-7 text-[#5d534a]">
                        {{ t(`inactive_finder.stats.${stat.key}.detail`) }}
                    </p>
                </article>
            </section>

            <section class="mt-8 grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
                <article class="rounded-[32px] border border-[#1f1a14]/10 bg-[#1f1a14] p-7 text-[#f6ede0]">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#ffb36b]">
                        {{ t('inactive_finder.filters.title') }}
                    </p>

                    <div class="mt-6 grid gap-4">
                        <div
                            v-for="filter in filters"
                            :key="filter.key"
                            class="rounded-3xl border border-white/10 bg-white/5 px-5 py-4"
                        >
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#d8c8b7]">
                                {{ t(`inactive_finder.filters.${filter.key}.label`) }}
                            </p>
                            <p class="mt-3 text-sm leading-7 text-[#f6ede0]">
                                {{ t(`inactive_finder.filters.${filter.key}.value`) }}
                            </p>
                        </div>
                    </div>
                </article>

                <article class="rounded-[32px] border border-[#1f1a14]/10 bg-white p-7">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#8b4a27]">
                        {{ t('inactive_finder.results.title') }}
                    </p>

                    <div class="mt-6 rounded-[28px] border border-dashed border-[#8b4a27]/25 bg-[#fcf7ee] p-8">
                        <h2 class="text-2xl font-semibold tracking-[-0.03em] text-[#1c1814]">
                            {{ t('inactive_finder.results.card_title') }}
                        </h2>
                        <p class="mt-4 max-w-2xl text-sm leading-8 text-[#5b5047]">
                            {{ t('inactive_finder.results.description') }}
                        </p>

                        <ul class="mt-6 grid gap-3 text-sm text-[#463d36]">
                            <li class="rounded-2xl bg-white px-4 py-3">{{ t('inactive_finder.results.bullet_1') }}</li>
                            <li class="rounded-2xl bg-white px-4 py-3">{{ t('inactive_finder.results.bullet_2') }}</li>
                            <li class="rounded-2xl bg-white px-4 py-3">{{ t('inactive_finder.results.bullet_3') }}</li>
                        </ul>
                    </div>
                </article>
            </section>
        </div>
    </div>
</template>
