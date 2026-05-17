<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/lib/i18n';

const page = usePage();
const { locale, locales, t } = useI18n();

const redirect = computed(() => {
    const url = page.url || '/';
    return url.startsWith('/') ? url : '/';
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8a7b6a]">
            {{ t('common.language') }}
        </span>

        <div class="flex flex-wrap items-center gap-2">
            <Link
                v-for="item in locales"
                :key="item.code"
                :href="`/locale/${item.code}?redirect=${encodeURIComponent(redirect)}`"
                class="rounded-full px-3 py-1.5 text-sm transition"
                :class="
                    item.code === locale
                        ? 'bg-[#1f1a14] text-[#f7efe1]'
                        : 'border border-[#1f1a14]/10 bg-white/70 text-[#433931] hover:border-[#8b4a27]/35 hover:bg-white'
                "
            >
                {{ item.label }}
            </Link>
        </div>
    </div>
</template>
