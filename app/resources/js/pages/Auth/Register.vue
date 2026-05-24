<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head :title="t('register.meta.title')" />

    <div class="min-h-screen bg-[#f4efe6] text-[#1b1815]">
        <div class="mx-auto flex min-h-screen max-w-6xl flex-col px-6 py-8 lg:px-10">
            <header class="flex items-center justify-between gap-4">
                <Link href="/" class="text-sm font-medium text-[#5d5248] transition hover:text-[#8b4a27]">
                    {{ t('common.back_home') }}
                </Link>

                <div class="flex items-center gap-3">
                    <LanguageSwitcher />
                    <Link
                        href="/login"
                        class="rounded-full bg-[#1f1a14] px-4 py-2 text-sm font-medium text-[#f7efe1] transition hover:bg-[#8b4a27]"
                    >
                        {{ t('common.login') }}
                    </Link>
                </div>
            </header>

            <main class="flex flex-1 items-center py-10">
                <div class="grid w-full overflow-hidden rounded-[32px] border border-[#1f1a14]/10 bg-white shadow-[0_32px_120px_rgba(56,43,27,0.08)] lg:grid-cols-[1.05fr_0.95fr]">
                    <section class="relative overflow-hidden bg-[#1f1a14] px-8 py-10 text-[#f7efe1] lg:px-10 lg:py-12">
                        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(69,111,91,0.28),transparent_34%)]" />
                        <div class="absolute bottom-[-5rem] left-[-4rem] h-56 w-56 rounded-full bg-[#ff7a1a]/25 blur-3xl" />

                        <div class="relative">
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#ffb36b]">
                                {{ t('register.panel.eyebrow') }}
                            </p>
                            <h1 class="mt-5 max-w-md text-4xl font-semibold tracking-[-0.04em]">
                                {{ t('register.panel.title') }}
                            </h1>
                            <p class="mt-5 max-w-md text-base leading-7 text-[#d8cec1]">
                                {{ t('register.panel.description') }}
                            </p>

                            <div class="mt-10 grid gap-4">
                                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#ffb36b]">
                                        {{ t('register.panel.memory_title') }}
                                    </p>
                                    <p class="mt-3 text-sm leading-7 text-[#e8dfd2]">
                                        {{ t('register.panel.memory_text') }}
                                    </p>
                                </div>
                                <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#ffb36b]">
                                        {{ t('register.panel.features_title') }}
                                    </p>
                                    <p class="mt-3 text-sm leading-7 text-[#e8dfd2]">
                                        {{ t('register.panel.features_text') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="px-8 py-10 lg:px-10 lg:py-12">
                        <div class="max-w-md">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#8b4a27]">
                                {{ t('register.form.eyebrow') }}
                            </p>
                            <h2 class="mt-4 text-3xl font-semibold tracking-[-0.04em] text-[#1c1814]">
                                {{ t('register.form.title') }}
                            </h2>

                            <form class="mt-8 space-y-5" @submit.prevent="submit">
                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-[#544b43]">{{ t('register.form.name') }}</span>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        autocomplete="name"
                                        class="w-full rounded-2xl border border-[#1f1a14]/10 bg-[#faf6ee] px-4 py-3 text-[#1b1815] outline-none transition focus:border-[#8b4a27]/40 focus:bg-white"
                                        :placeholder="t('register.form.name_placeholder')"
                                    />
                                    <p v-if="form.errors.name" class="mt-2 text-sm text-[#b94b39]">{{ form.errors.name }}</p>
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-[#544b43]">{{ t('register.form.email') }}</span>
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        autocomplete="email"
                                        class="w-full rounded-2xl border border-[#1f1a14]/10 bg-[#faf6ee] px-4 py-3 text-[#1b1815] outline-none transition focus:border-[#8b4a27]/40 focus:bg-white"
                                        :placeholder="t('register.form.email_placeholder')"
                                    />
                                    <p v-if="form.errors.email" class="mt-2 text-sm text-[#b94b39]">{{ form.errors.email }}</p>
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-[#544b43]">{{ t('register.form.password') }}</span>
                                    <input
                                        v-model="form.password"
                                        type="password"
                                        autocomplete="new-password"
                                        class="w-full rounded-2xl border border-[#1f1a14]/10 bg-[#faf6ee] px-4 py-3 text-[#1b1815] outline-none transition focus:border-[#8b4a27]/40 focus:bg-white"
                                        :placeholder="t('register.form.password_placeholder')"
                                    />
                                    <p v-if="form.errors.password" class="mt-2 text-sm text-[#b94b39]">{{ form.errors.password }}</p>
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-[#544b43]">{{ t('register.form.password_confirmation') }}</span>
                                    <input
                                        v-model="form.password_confirmation"
                                        type="password"
                                        autocomplete="new-password"
                                        class="w-full rounded-2xl border border-[#1f1a14]/10 bg-[#faf6ee] px-4 py-3 text-[#1b1815] outline-none transition focus:border-[#8b4a27]/40 focus:bg-white"
                                        :placeholder="t('register.form.password_confirmation_placeholder')"
                                    />
                                </label>

                                <div class="flex flex-wrap items-center gap-3">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center justify-center rounded-full bg-[#1f1a14] px-5 py-3 text-sm font-medium text-[#f7efe1] transition hover:bg-[#8b4a27] disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="form.processing"
                                    >
                                        {{ form.processing ? t('register.form.submitting') : t('register.form.submit') }}
                                    </button>

                                    <Link
                                        href="/login"
                                        class="text-sm font-medium text-[#8b4a27] transition hover:text-[#6c3419]"
                                    >
                                        {{ t('register.form.login_link') }}
                                    </Link>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
</template>
