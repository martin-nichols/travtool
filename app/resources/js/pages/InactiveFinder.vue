<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useI18n } from '@/lib/i18n';

type FilterState = {
    world: string;
    q: string | null;
    tribe_id: number | string | null;
    min_score: number | string | null;
    min_population: number | string | null;
    max_population: number | string | null;
    x: number | string | null;
    y: number | string | null;
    radius_min: number | string | null;
    radius_max: number | string | null;
    no_alliance: boolean;
    one_village: boolean;
    stable_only: boolean;
    include_npcs: boolean;
    sort: string;
};

type WorldOption = {
    key: string;
    name: string;
    base_url: string;
    has_imported_snapshot: boolean;
    current_snapshot_date: string | null;
    history_ready: boolean;
};

type TribeOption = {
    value: number;
    label: string;
};

type SortOption = {
    value: string;
    label: string;
};

type FinderResult = {
    village_name: string;
    player_name: string;
    alliance_tag: string | null;
    coords: {
        x: number;
        y: number;
    };
    tribe_id: number;
    population: number;
    region_name: string | null;
    player_village_count: number;
    player_population_total: number;
    population_delta_1d: number | null;
    village_count_delta_1d: number | null;
    score: number;
    distance: number | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedResults = {
    data: FinderResult[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

type Summary = {
    activeWorldCount: number;
    selectedWorldKey: string;
    selectedWorldName: string;
    selectedWorldBaseUrl: string;
    currentSnapshotDate: string | null;
    lastImportAt: string | null;
    historyReady: boolean;
    resultsCount: number;
    hasImportedSnapshot: boolean;
};

const props = defineProps<{
    filters: FilterState;
    worlds: WorldOption[];
    tribes: TribeOption[];
    sorts: SortOption[];
    summary: Summary;
    results: PaginatedResults;
}>();

const { t } = useI18n();
const resultsScroller = ref<HTMLElement | null>(null);
const worldSelectionError = ref(false);

const form = reactive<FilterState>({ ...props.filters });

watch(
    () => props.filters,
    (nextFilters) => {
        Object.assign(form, nextFilters);
    },
    { deep: true },
);

watch(
    () => form.world,
    (nextWorld) => {
        if (nextWorld) {
            worldSelectionError.value = false;
        }
    },
);

const numberFormatter = new Intl.NumberFormat();

const nullableNumber = (value: unknown): number | null => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const normalized = Number(value);

    return Number.isFinite(normalized) ? normalized : null;
};

const normalizedCenter = (): { x: number | null; y: number | null } => {
    const xValue = typeof form.x === 'string' ? form.x.trim() : form.x;
    const yValue = typeof form.y === 'string' ? form.y.trim() : form.y;

    if ((yValue === '' || yValue === null) && typeof xValue === 'string') {
        const match = xValue.match(/^\s*(-?\d+)\s*\|\s*(-?\d+)\s*$/);

        if (match) {
            return {
                x: Number(match[1]),
                y: Number(match[2]),
            };
        }
    }

    return {
        x: nullableNumber(xValue),
        y: nullableNumber(yValue),
    };
};

const selectedWorld = computed(() => props.worlds.find((world) => world.key === form.world) ?? null);
const canUseDistanceSort = computed(() => {
    const { x, y } = normalizedCenter();

    return x !== null && y !== null;
});
const selectedResultWorldName = computed(() => props.summary.selectedWorldName || t('inactive_finder.results.choose_world_title'));

const validateWorldSelection = (): boolean => {
    const isValid = Boolean(form.world);

    worldSelectionError.value = !isValid;

    return isValid;
};

const cleanedFilters = (): Record<string, string | number | boolean> => {
    const { x, y } = normalizedCenter();
    const radiusMin = nullableNumber(form.radius_min);
    const radiusMax = nullableNumber(form.radius_max);
    const hasCenter = x !== null && y !== null;
    const payload: Record<string, string | number | boolean> = {
        world: form.world,
        one_village: form.one_village,
        include_npcs: form.include_npcs,
        no_alliance: form.no_alliance,
        stable_only: form.stable_only,
        sort: form.sort,
    };

    if (form.q) payload.q = form.q;
    if (nullableNumber(form.tribe_id) !== null) payload.tribe_id = nullableNumber(form.tribe_id) as number;
    if (nullableNumber(form.min_score) !== null) payload.min_score = nullableNumber(form.min_score) as number;
    if (nullableNumber(form.min_population) !== null) payload.min_population = nullableNumber(form.min_population) as number;
    if (nullableNumber(form.max_population) !== null) payload.max_population = nullableNumber(form.max_population) as number;
    if (x !== null) payload.x = x;
    if (y !== null) payload.y = y;
    if (radiusMin !== null) payload.radius_min = radiusMin;
    if (radiusMax !== null) {
        payload.radius_max = radiusMax;
    } else if (hasCenter && radiusMin === null) {
        payload.radius_max = 25;
    }

    return payload;
};

const submit = () => {
    if (!validateWorldSelection()) {
        return;
    }

    router.get('/inactive-finder', cleanedFilters(), {
        preserveScroll: true,
        preserveState: false,
        replace: false,
    });
};

const reset = () => {
    router.get(
        '/inactive-finder',
        {
            world: form.world || props.summary.selectedWorldKey,
            min_score: 100,
            one_village: true,
            include_npcs: false,
            sort: 'score',
        },
        {
            preserveScroll: true,
            preserveState: false,
            replace: true,
        },
    );
};

const openSelectedWorld = () => {
    if (!validateWorldSelection() || !selectedWorld.value?.base_url) {
        return;
    }

    window.open(selectedWorld.value.base_url, '_blank', 'noopener,noreferrer');
};

const formatNumber = (value: number | null): string => {
    if (value === null) {
        return t('inactive_finder.results.no_data');
    }

    return numberFormatter.format(value);
};

const formatDelta = (populationDelta: number | null, villageDelta: number | null): string => {
    if (populationDelta === null || villageDelta === null) {
        return t('inactive_finder.results.no_data');
    }

    return `P ${populationDelta >= 0 ? '+' : ''}${populationDelta} / V ${villageDelta >= 0 ? '+' : ''}${villageDelta}`;
};

const coordsLabel = (x: number, y: number): string => `${x}|${y}`;

const tribeLabel = (tribeId: number): string => props.tribes.find((tribe) => tribe.value === tribeId)?.label ?? String(tribeId);
const villagePopulationLabel = (population: number): string => `${t('inactive_finder.results.population_prefix')} = ${formatNumber(population)}`;

const villageMapLink = (x: number, y: number): string | null => {
    const baseUrl = props.summary.selectedWorldBaseUrl;

    if (!baseUrl) {
        return null;
    }

    return `${baseUrl.replace(/\/+$/, '')}/karte.php?x=${x}&y=${y}`;
};

const dragState = reactive({
    active: false,
    moved: false,
    suppressClick: false,
    startX: 0,
    startY: 0,
    startScrollLeft: 0,
    pointerId: -1,
    direction: '' as '' | 'horizontal' | 'vertical',
});

const onResultsPointerDown = (event: PointerEvent) => {
    if (event.pointerType === 'mouse' && event.button !== 0) {
        return;
    }

    const scroller = resultsScroller.value;

    if (!scroller || scroller.scrollWidth <= scroller.clientWidth) {
        return;
    }

    dragState.active = true;
    dragState.moved = false;
    dragState.direction = '';
    dragState.startX = event.clientX;
    dragState.startY = event.clientY;
    dragState.startScrollLeft = scroller.scrollLeft;
    dragState.pointerId = event.pointerId;
};

const onResultsPointerMove = (event: PointerEvent) => {
    if (!dragState.active || dragState.pointerId !== event.pointerId) {
        return;
    }

    const scroller = resultsScroller.value;

    if (!scroller) {
        return;
    }

    const deltaX = event.clientX - dragState.startX;
    const deltaY = event.clientY - dragState.startY;

    if (dragState.direction === '') {
        if (Math.abs(deltaX) < 6 && Math.abs(deltaY) < 6) {
            return;
        }

        dragState.direction = Math.abs(deltaX) > Math.abs(deltaY) ? 'horizontal' : 'vertical';

        if (dragState.direction === 'horizontal') {
            scroller.setPointerCapture?.(event.pointerId);
        } else {
            resetResultsDrag();
            return;
        }
    }

    if (dragState.direction !== 'horizontal') {
        return;
    }

    if (Math.abs(deltaX) > 6) {
        dragState.moved = true;
        dragState.suppressClick = true;
    }

    scroller.scrollLeft = dragState.startScrollLeft - deltaX;

    if (dragState.moved && event.cancelable) {
        event.preventDefault();
    }
};

const resetResultsDrag = (event?: PointerEvent) => {
    const scroller = resultsScroller.value;

    if (event && scroller && dragState.pointerId === event.pointerId) {
        scroller.releasePointerCapture?.(event.pointerId);
    }

    dragState.active = false;
    dragState.pointerId = -1;
    dragState.direction = '';

    window.setTimeout(() => {
        dragState.suppressClick = false;
    }, 0);
};

const suppressDraggedClick = (event: MouseEvent) => {
    if (!dragState.suppressClick) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
};
</script>

<template>
    <Head :title="t('inactive_finder.meta.title')" />

    <div class="min-h-screen bg-[#f5f0e5] text-[#191511]">
        <div class="mx-auto max-w-7xl px-6 py-8 lg:px-10">
            <header class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                <div>
                    <Link href="/" class="text-sm font-medium text-[#6a5d52] transition hover:text-[#8b4a27]">
                        {{ t('common.back_home') }}
                    </Link>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] text-[#1c1814]">
                        {{ t('inactive_finder.meta.title') }}
                    </h1>
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

            <section class="mt-10">
                <article class="overflow-hidden rounded-[32px] border border-[#1f1a14]/10 bg-[#1f1a14] p-5 text-[#f6ede0] shadow-[0_24px_80px_rgba(40,28,17,0.16)] sm:p-7">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#ffb36b]">
                            {{ t('inactive_finder.filters.title') }}
                        </p>
                    </div>

                    <form class="mt-6 grid gap-5" @submit.prevent="submit">
                        <div class="grid gap-4 lg:grid-cols-12">
                            <div class="grid min-w-0 gap-3 lg:col-span-3">
                                <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[#d8c8b7]">
                                    {{ t('inactive_finder.filters.world_label') }}
                                </span>
                                <div class="relative">
                                    <div
                                        v-if="worldSelectionError"
                                        class="absolute -top-3 left-4 z-10 -translate-y-full rounded-full bg-[#dc5a4a] px-3 py-1 text-xs font-semibold text-white shadow-[0_12px_24px_rgba(220,90,74,0.3)]"
                                    >
                                        {{ t('inactive_finder.filters.choose_world_error') }}
                                    </div>
                                    <select
                                        v-model="form.world"
                                        :class="[
                                            'w-full min-w-0 rounded-2xl border bg-white/5 px-4 py-3 text-sm text-[#f6ede0] outline-none',
                                            worldSelectionError ? 'border-[#dc5a4a] ring-2 ring-[#dc5a4a]/30' : 'border-white/10',
                                        ]"
                                    >
                                        <option value="" class="text-[#1f1a14]">{{ t('inactive_finder.filters.choose_world_placeholder') }}</option>
                                        <option v-for="world in worlds" :key="world.key" :value="world.key" class="text-[#1f1a14]">
                                            {{ world.name }}
                                        </option>
                                    </select>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-full border border-white/10 bg-white/5 px-4 py-3 text-sm font-medium text-[#f4c18e] transition hover:bg-white/10 hover:text-white"
                                    @click="openSelectedWorld"
                                >
                                    {{ t('inactive_finder.filters.open_world') }}
                                </button>
                            </div>

                            <label class="grid min-w-0 gap-2 lg:col-span-3">
                                <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[#d8c8b7]">
                                    {{ t('inactive_finder.filters.sort_label') }}
                                </span>
                                <select v-model="form.sort" class="w-full min-w-0 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-[#f6ede0] outline-none">
                                    <option
                                        v-for="sortOption in sorts"
                                        :key="sortOption.value"
                                        :value="sortOption.value"
                                        :disabled="sortOption.value === 'distance_asc' && !canUseDistanceSort"
                                        class="text-[#1f1a14]"
                                    >
                                        {{ t(sortOption.label) }}
                                    </option>
                                </select>
                            </label>

                            <label class="grid min-w-0 gap-2 lg:col-span-6">
                                <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[#d8c8b7]">
                                    {{ t('inactive_finder.filters.search_label') }}
                                </span>
                                <input
                                    v-model="form.q"
                                    type="text"
                                    :placeholder="t('inactive_finder.filters.search_placeholder')"
                                    class="w-full min-w-0 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-[#f6ede0] outline-none placeholder:text-[#b7a593]"
                                />
                            </label>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-12">
                            <label class="grid min-w-0 gap-2 lg:col-span-4">
                                <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[#d8c8b7]">
                                    {{ t('inactive_finder.filters.tribe_label') }}
                                </span>
                                <select v-model="form.tribe_id" class="w-full min-w-0 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-[#f6ede0] outline-none">
                                    <option :value="null" class="text-[#1f1a14]">{{ t('inactive_finder.filters.all_tribes') }}</option>
                                    <option v-for="tribe in tribes" :key="tribe.value" :value="tribe.value" class="text-[#1f1a14]">
                                        {{ tribe.label }}
                                    </option>
                                </select>
                            </label>

                            <label class="grid min-w-0 gap-2 lg:col-span-2">
                                <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[#d8c8b7]">
                                    {{ t('inactive_finder.filters.min_score_label') }}
                                </span>
                                <input v-model="form.min_score" type="number" min="0" max="999" class="w-full min-w-0 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-[#f6ede0] outline-none" />
                            </label>

                            <label class="grid min-w-0 gap-2 lg:col-span-3">
                                <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[#d8c8b7]">
                                    {{ t('inactive_finder.filters.min_population_label') }}
                                </span>
                                <input v-model="form.min_population" type="number" min="0" class="w-full min-w-0 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-[#f6ede0] outline-none" />
                            </label>

                            <label class="grid min-w-0 gap-2 lg:col-span-3">
                                <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[#d8c8b7]">
                                    {{ t('inactive_finder.filters.max_population_label') }}
                                </span>
                                <input v-model="form.max_population" type="number" min="0" class="w-full min-w-0 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-[#f6ede0] outline-none" />
                            </label>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-12 lg:items-start">
                            <div class="grid gap-4 lg:col-span-6 lg:grid-cols-4">
                                <label class="grid min-w-0 gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[#d8c8b7]">
                                        {{ t('inactive_finder.filters.center_x_label') }}
                                    </span>
                                    <input
                                        v-model="form.x"
                                        type="text"
                                        inputmode="numeric"
                                        placeholder="-15"
                                        class="w-full min-w-0 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-[#f6ede0] outline-none placeholder:text-[#b7a593]"
                                    />
                                </label>

                                <label class="grid min-w-0 gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[#d8c8b7]">
                                        {{ t('inactive_finder.filters.center_y_label') }}
                                    </span>
                                    <input
                                        v-model="form.y"
                                        type="text"
                                        inputmode="numeric"
                                        placeholder="-15"
                                        class="w-full min-w-0 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-[#f6ede0] outline-none placeholder:text-[#b7a593]"
                                    />
                                </label>

                                <label class="grid min-w-0 gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[#d8c8b7]">
                                        {{ t('inactive_finder.filters.radius_min_label') }}
                                    </span>
                                    <input v-model="form.radius_min" type="number" min="0" max="400" class="w-full min-w-0 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-[#f6ede0] outline-none" />
                                </label>

                                <label class="grid min-w-0 gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[#d8c8b7]">
                                        {{ t('inactive_finder.filters.radius_max_label') }}
                                    </span>
                                    <input v-model="form.radius_max" type="number" min="0" max="400" class="w-full min-w-0 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-[#f6ede0] outline-none" />
                                </label>
                            </div>

                            <div class="grid gap-3 rounded-3xl border border-white/10 bg-white/5 px-4 py-4 sm:px-5 lg:col-span-6">
                                <label class="flex items-start gap-3 text-sm text-[#f6ede0]">
                                    <input v-model="form.one_village" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 bg-transparent" />
                                    <span>{{ t('inactive_finder.filters.one_village') }}</span>
                                </label>
                                <label class="flex items-start gap-3 text-sm text-[#f6ede0]">
                                    <input v-model="form.no_alliance" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 bg-transparent" />
                                    <span>{{ t('inactive_finder.filters.no_alliance') }}</span>
                                </label>
                                <label class="flex items-start gap-3 text-sm text-[#f6ede0]">
                                    <input v-model="form.include_npcs" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 bg-transparent" />
                                    <span>{{ t('inactive_finder.filters.include_npcs') }}</span>
                                </label>
                                <label class="flex items-start gap-3 text-sm text-[#f6ede0]" :class="{ 'opacity-50': !summary.historyReady }">
                                    <input v-model="form.stable_only" :disabled="!summary.historyReady" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 bg-transparent" />
                                    <span>{{ t('inactive_finder.filters.stable_only') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-white/10 pt-5 lg:flex-row lg:items-center lg:justify-between">
                            <p class="max-w-3xl text-sm leading-7 text-[#d8c8b7]">
                                {{ t('inactive_finder.filters.radius_help') }}
                            </p>

                            <p class="max-w-3xl text-sm leading-7 text-[#d8c8b7] lg:text-right">
                                {{ summary.historyReady ? t('inactive_finder.notice.history_ready') : t('inactive_finder.notice.history_waiting') }}
                            </p>

                            <div class="flex flex-wrap gap-3">
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-full bg-[#ffb36b] px-5 py-3 text-sm font-medium text-[#1f1a14] transition hover:bg-[#ff9a3d]"
                                >
                                    {{ t('inactive_finder.filters.apply') }}
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-full border border-white/15 px-5 py-3 text-sm font-medium text-[#f6ede0] transition hover:bg-white/10"
                                    @click="reset"
                                >
                                    {{ t('inactive_finder.filters.reset') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </article>
            </section>

            <section class="mt-8">
                <article class="rounded-[32px] border border-[#1f1a14]/10 bg-white p-5 shadow-[0_20px_60px_rgba(56,43,27,0.07)] sm:p-7">
                    <div class="flex flex-col gap-4 border-b border-[#1f1a14]/10 pb-5 lg:flex-row lg:items-end lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#8b4a27]">
                                {{ t('inactive_finder.results.title') }}
                            </p>
                            <h2 class="mt-3 text-2xl font-semibold tracking-[-0.03em] text-[#1c1814] sm:text-3xl">
                                {{ selectedResultWorldName }}
                            </h2>
                            <p class="mt-2 max-w-3xl text-sm leading-7 text-[#5b5047]">
                                {{
                                    !summary.selectedWorldKey
                                        ? t('inactive_finder.results.choose_world_description')
                                        : summary.hasImportedSnapshot
                                            ? t('inactive_finder.results.ready_description')
                                            : t('inactive_finder.results.waiting_description')
                                }}
                            </p>
                        </div>

                        <div v-if="summary.selectedWorldKey" class="flex flex-wrap gap-2 text-xs font-medium text-[#5b5047]">
                            <span class="rounded-full bg-[#fcf7ee] px-3 py-2">
                                <strong class="font-semibold text-[#1c1814]">{{ formatNumber(summary.resultsCount) }}</strong>
                                {{ t('inactive_finder.results.items') }}
                            </span>
                        </div>
                    </div>

                    <div v-if="!summary.selectedWorldKey" class="mt-6 rounded-[28px] border border-dashed border-[#8b4a27]/25 bg-[#fcf7ee] p-8">
                        <h3 class="text-2xl font-semibold tracking-[-0.03em] text-[#1c1814]">
                            {{ t('inactive_finder.results.choose_world_title') }}
                        </h3>
                        <p class="mt-4 max-w-2xl text-sm leading-8 text-[#5b5047]">
                            {{ t('inactive_finder.results.choose_world_description') }}
                        </p>
                    </div>

                    <div v-else-if="!summary.hasImportedSnapshot" class="mt-6 rounded-[28px] border border-dashed border-[#8b4a27]/25 bg-[#fcf7ee] p-8">
                        <h3 class="text-2xl font-semibold tracking-[-0.03em] text-[#1c1814]">
                            {{ t('inactive_finder.results.empty_title') }}
                        </h3>
                        <p class="mt-4 max-w-2xl text-sm leading-8 text-[#5b5047]">
                            {{ t('inactive_finder.results.empty_description') }}
                        </p>
                    </div>

                    <div v-else-if="results.data.length === 0" class="mt-6 rounded-[28px] border border-dashed border-[#8b4a27]/25 bg-[#fcf7ee] p-8">
                        <h3 class="text-2xl font-semibold tracking-[-0.03em] text-[#1c1814]">
                            {{ t('inactive_finder.results.no_match_title') }}
                        </h3>
                        <p class="mt-4 max-w-2xl text-sm leading-8 text-[#5b5047]">
                            {{ t('inactive_finder.results.no_match_description') }}
                        </p>
                    </div>

                    <div v-else class="mt-6 overflow-hidden rounded-[28px] border border-[#1f1a14]/10">
                        <div class="border-b border-[#1f1a14]/10 bg-[#fcf7ee] px-4 py-3 text-sm text-[#5b5047] sm:px-5">
                            {{ t('inactive_finder.results.swipe_hint') }}
                        </div>

                        <div
                            ref="resultsScroller"
                            class="overflow-x-auto overscroll-x-contain select-none touch-pan-y"
                            @click.capture="suppressDraggedClick"
                            @pointercancel="resetResultsDrag"
                            @pointerdown="onResultsPointerDown"
                            @pointermove="onResultsPointerMove"
                            @pointerup="resetResultsDrag"
                        >
                            <table class="min-w-[980px] divide-y divide-[#1f1a14]/10">
                                <thead class="bg-[#fcf7ee] text-left">
                                    <tr class="text-xs font-semibold uppercase tracking-[0.2em] text-[#7f6d5d]">
                                        <th class="px-4 py-3 whitespace-nowrap">{{ t('inactive_finder.results.columns.village') }}</th>
                                        <th class="px-4 py-3 whitespace-nowrap">
                                            <div>{{ t('inactive_finder.results.columns.player') }}</div>
                                            <div class="mt-1 text-[10px] font-medium normal-case tracking-normal text-[#9a8a7c]">
                                                {{ t('inactive_finder.results.columns.player_population_hint') }}
                                            </div>
                                        </th>
                                        <th class="px-4 py-3 whitespace-nowrap">{{ t('inactive_finder.results.columns.alliance') }}</th>
                                        <th class="px-4 py-3 whitespace-nowrap">{{ t('inactive_finder.results.columns.coords') }}</th>
                                        <th class="px-4 py-3 whitespace-nowrap">{{ t('inactive_finder.results.columns.tribe') }}</th>
                                        <th class="px-4 py-3 whitespace-nowrap">{{ t('inactive_finder.results.columns.population') }}</th>
                                        <th class="px-4 py-3 whitespace-nowrap">{{ t('inactive_finder.results.columns.player_villages') }}</th>
                                        <th class="px-4 py-3 whitespace-nowrap">{{ t('inactive_finder.results.columns.player_delta') }}</th>
                                        <th class="px-4 py-3 whitespace-nowrap">{{ t('inactive_finder.results.columns.distance') }}</th>
                                        <th class="px-4 py-3 whitespace-nowrap">{{ t('inactive_finder.results.columns.score') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#1f1a14]/10 bg-white text-sm text-[#2a231d]">
                                    <tr v-for="row in results.data" :key="`${row.village_name}-${row.coords.x}-${row.coords.y}`">
                                        <td class="px-4 py-4 align-top">
                                            <a
                                                v-if="villageMapLink(row.coords.x, row.coords.y)"
                                                :href="villageMapLink(row.coords.x, row.coords.y) ?? undefined"
                                                target="_blank"
                                                rel="noreferrer"
                                                class="inline-flex items-center gap-2 font-semibold text-[#1c1814] transition hover:text-[#8b4a27]"
                                            >
                                                <span>{{ row.village_name }}</span>
                                                <svg
                                                    class="h-4 w-4 shrink-0"
                                                    viewBox="0 0 20 20"
                                                    fill="none"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        d="M7 5H15V13M15 5L5 15"
                                                        stroke="currentColor"
                                                        stroke-width="1.7"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                            </a>
                                            <div v-else class="font-semibold text-[#1c1814]">{{ row.village_name }}</div>
                                            <div class="mt-1 text-xs text-[#7b6b5d]">{{ villagePopulationLabel(row.population) }}</div>
                                            <div v-if="row.region_name" class="mt-1 text-xs text-[#7b6b5d]">{{ row.region_name }}</div>
                                        </td>
                                        <td class="px-4 py-4 align-top">
                                            <div class="font-medium">{{ row.player_name }}</div>
                                            <div class="mt-1 text-xs text-[#7b6b5d]">{{ formatNumber(row.player_population_total) }}</div>
                                        </td>
                                        <td class="px-4 py-4 align-top whitespace-nowrap">
                                            {{ row.alliance_tag ?? t('inactive_finder.results.no_alliance') }}
                                        </td>
                                        <td class="px-4 py-4 align-top whitespace-nowrap">{{ coordsLabel(row.coords.x, row.coords.y) }}</td>
                                        <td class="px-4 py-4 align-top whitespace-nowrap">{{ tribeLabel(row.tribe_id) }}</td>
                                        <td class="px-4 py-4 align-top whitespace-nowrap">{{ formatNumber(row.population) }}</td>
                                        <td class="px-4 py-4 align-top whitespace-nowrap">{{ formatNumber(row.player_village_count) }}</td>
                                        <td class="px-4 py-4 align-top whitespace-nowrap">{{ formatDelta(row.population_delta_1d, row.village_count_delta_1d) }}</td>
                                        <td class="px-4 py-4 align-top whitespace-nowrap">{{ row.distance !== null ? row.distance.toFixed(2) : t('inactive_finder.results.no_data') }}</td>
                                        <td class="px-4 py-4 align-top whitespace-nowrap">
                                            <span class="inline-flex rounded-full bg-[#1f1a14] px-3 py-1 text-xs font-semibold text-[#f7efe1]">
                                                {{ formatNumber(row.score) }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-[#1f1a14]/10 bg-[#fcf7ee] px-4 py-4 md:flex-row md:items-center md:justify-between">
                            <p class="text-sm text-[#5b5047]">
                                {{ results.from ?? 0 }} - {{ results.to ?? 0 }} / {{ formatNumber(results.total) }}
                            </p>

                            <nav class="flex flex-wrap gap-2">
                                <Link
                                    v-for="link in results.links"
                                    :key="`${link.label}-${link.url}`"
                                    :href="link.url ?? ''"
                                    :class="[
                                        'rounded-full px-3 py-2 text-sm transition',
                                        link.active ? 'bg-[#1f1a14] text-[#f7efe1]' : 'bg-white text-[#3b3129]',
                                        !link.url ? 'pointer-events-none opacity-40' : 'hover:bg-[#fff1de]',
                                    ]"
                                    v-html="link.label"
                                />
                            </nav>
                        </div>
                    </div>
                </article>
            </section>
        </div>
    </div>
</template>
