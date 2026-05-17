<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/lib/i18n';

type LocaleOption = {
    code: string;
    label: string;
};

const page = usePage();
const { t } = useI18n();

const locale = computed(() => page.props.locale.current);
const locales = computed(() => page.props.locale.available as LocaleOption[]);
const redirect = computed(() => {
    if (typeof window === 'undefined') {
        return '/';
    }

    return `${window.location.pathname}${window.location.search}`;
});

const switchLocale = (event: Event) => {
    const target = event.target as HTMLSelectElement | null;
    const nextLocale = target?.value;

    if (!nextLocale || nextLocale === locale.value) {
        return;
    }

    router.get(
        `/locale/${nextLocale}`,
        { redirect: redirect.value },
        {
            preserveScroll: true,
            preserveState: false,
            replace: true,
        },
    );
};
</script>

<template>
    <label class="inline-flex items-center gap-3 rounded-full border border-[#1f1a14]/10 bg-white px-3 py-2 text-sm text-[#3b3129] shadow-[0_10px_30px_rgba(56,43,27,0.08)]">
        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-[#8b4a27]">
            {{ t('common.language') }}
        </span>
        <select
            :value="locale"
            class="min-w-[7rem] bg-transparent text-sm font-medium text-[#1f1a14] outline-none"
            @change="switchLocale"
        >
            <option v-for="item in locales" :key="item.code" :value="item.code">
                {{ item.label }}
            </option>
        </select>
    </label>
</template>
