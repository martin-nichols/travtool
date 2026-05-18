<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

const workflowSteps = [
    'map_builder.workflow.world',
    'map_builder.workflow.center',
    'map_builder.workflow.layers',
    'map_builder.workflow.export',
];

const workspaceCards = [
    'map_builder.workspace.settings',
    'map_builder.workspace.overlays',
    'map_builder.workspace.output',
];
</script>

<template>
    <Head :title="t('map_builder.meta.title')" />

    <div class="min-h-screen bg-[#eef2e8] text-[#171411]">
        <div class="mx-auto max-w-7xl px-6 py-8 lg:px-10">
            <header class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                <div>
                    <Link href="/" class="text-sm font-medium text-[#5d6457] transition hover:text-[#3f6d8f]">
                        {{ t('common.back_home') }}
                    </Link>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] text-[#1c1814]">
                        {{ t('map_builder.meta.title') }}
                    </h1>
                    <p class="mt-4 max-w-3xl text-base leading-8 text-[#555249] sm:text-lg">
                        {{ t('map_builder.hero.description') }}
                    </p>
                </div>

                <div class="flex flex-col items-start gap-4 md:items-end">
                    <LanguageSwitcher />
                    <Link
                        href="/login"
                        class="inline-flex items-center justify-center rounded-full bg-[#1f1a14] px-5 py-3 text-sm font-medium text-[#f7efe1] transition hover:bg-[#3f6d8f]"
                    >
                        {{ t('common.go_to_login') }}
                    </Link>
                </div>
            </header>

            <section class="mt-10 grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <article class="overflow-hidden rounded-[32px] border border-[#1f1a14]/10 bg-[#1d262b] p-6 text-[#edf3f6] shadow-[0_24px_80px_rgba(27,39,48,0.18)] sm:p-8">
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-[#9fd1ef]">
                        <span class="h-2 w-2 rounded-full bg-[#7fc4f1]" />
                        {{ t('map_builder.hero.badge') }}
                    </div>

                    <h2 class="mt-6 text-3xl font-semibold tracking-[-0.03em] text-white sm:text-4xl">
                        {{ t('map_builder.hero.title') }}
                    </h2>

                    <p class="mt-5 max-w-3xl text-sm leading-8 text-[#c6d3da] sm:text-base">
                        {{ t('map_builder.hero.copy') }}
                    </p>

                    <div class="mt-8 grid gap-4 md:grid-cols-2">
                        <div class="rounded-[28px] border border-white/10 bg-white/5 p-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#9fd1ef]">
                                {{ t('map_builder.workflow.title') }}
                            </p>
                            <ol class="mt-4 grid gap-3">
                                <li
                                    v-for="step in workflowSteps"
                                    :key="step"
                                    class="flex items-start gap-3 rounded-2xl border border-white/8 bg-[#11181c] px-4 py-3 text-sm leading-7 text-[#edf3f6]"
                                >
                                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-[#7fc4f1]" />
                                    <span>{{ t(step) }}</span>
                                </li>
                            </ol>
                        </div>

                        <div class="rounded-[28px] border border-white/10 bg-gradient-to-br from-[#233847] to-[#15232c] p-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#9fd1ef]">
                                {{ t('map_builder.preview.title') }}
                            </p>
                            <div class="mt-4 rounded-[24px] border border-white/10 bg-[#0e1519] p-4">
                                <div class="flex items-center justify-between text-xs font-medium uppercase tracking-[0.2em] text-[#7ea7bf]">
                                    <span>{{ t('map_builder.preview.header_left') }}</span>
                                    <span>{{ t('map_builder.preview.header_right') }}</span>
                                </div>

                                <div class="mt-4 aspect-[1.2] rounded-[20px] border border-dashed border-[#7fc4f1]/30 bg-[radial-gradient(circle_at_center,rgba(127,196,241,0.18),transparent_48%),linear-gradient(rgba(255,255,255,0.04)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.04)_1px,transparent_1px)] bg-[length:auto,28px_28px,28px_28px] p-4">
                                    <div class="relative h-full w-full overflow-hidden rounded-[16px] border border-white/8">
                                        <div class="absolute left-1/2 top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#ffb36b] shadow-[0_0_0_10px_rgba(255,179,107,0.12)]" />
                                        <div class="absolute left-1/2 top-1/2 h-32 w-32 -translate-x-1/2 -translate-y-1/2 rounded-full border border-[#7fc4f1]/50" />
                                        <div class="absolute left-1/2 top-1/2 h-52 w-52 -translate-x-1/2 -translate-y-1/2 rounded-full border border-dashed border-[#7fc4f1]/35" />
                                        <div class="absolute left-[26%] top-[38%] h-2.5 w-2.5 rounded-full bg-[#f57c61]" />
                                        <div class="absolute left-[62%] top-[28%] h-2.5 w-2.5 rounded-full bg-[#f57c61]" />
                                        <div class="absolute left-[68%] top-[62%] h-2.5 w-2.5 rounded-full bg-[#f57c61]" />
                                        <div class="absolute left-[36%] top-[68%] h-2.5 w-2.5 rounded-full bg-[#8fd38a]" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="rounded-[32px] border border-[#1f1a14]/10 bg-white p-6 shadow-[0_20px_60px_rgba(56,43,27,0.08)] sm:p-8">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#3f6d8f]">
                        {{ t('map_builder.workspace.title') }}
                    </p>
                    <h2 class="mt-4 text-3xl font-semibold tracking-[-0.03em] text-[#1c1814]">
                        {{ t('map_builder.workspace.heading') }}
                    </h2>
                    <p class="mt-4 text-sm leading-8 text-[#5b5047] sm:text-base">
                        {{ t('map_builder.workspace.copy') }}
                    </p>

                    <div class="mt-8 grid gap-4">
                        <div
                            v-for="card in workspaceCards"
                            :key="card"
                            class="rounded-[28px] border border-[#1f1a14]/10 bg-[#f8f3eb] p-5"
                        >
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8b4a27]">
                                {{ t(`${card}.eyebrow`) }}
                            </p>
                            <h3 class="mt-3 text-xl font-semibold tracking-[-0.02em] text-[#1c1814]">
                                {{ t(`${card}.title`) }}
                            </h3>
                            <p class="mt-3 text-sm leading-7 text-[#5b5047]">
                                {{ t(`${card}.description`) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            href="/inactive-finder"
                            class="inline-flex items-center justify-center rounded-full border border-[#1f1a14]/10 px-5 py-3 text-sm font-medium text-[#1f1a14] transition hover:border-[#3f6d8f]/35 hover:bg-[#eef5fb]"
                        >
                            {{ t('map_builder.actions.back_to_finder') }}
                        </Link>
                        <button
                            type="button"
                            class="inline-flex cursor-default items-center justify-center rounded-full bg-[#3f6d8f] px-5 py-3 text-sm font-medium text-white"
                        >
                            {{ t('map_builder.actions.coming_soon') }}
                        </button>
                    </div>
                </article>
            </section>
        </div>
    </div>
</template>
