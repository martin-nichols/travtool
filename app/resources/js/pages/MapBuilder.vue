<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useI18n } from '@/lib/i18n';

type FilterState = {
    world: string;
    alliance_tags: string;
    player_names: string;
    region_names: string;
};

type WorldOption = {
    key: string;
    name: string;
    base_url: string;
    has_imported_snapshot: boolean;
    current_snapshot_date: string | null;
};

type MapLegendItem = {
    key: string;
    type: 'alliance' | 'player' | 'region';
    label: string;
    color: string;
    count: number;
    parent_key: string | null;
    parent_label: string | null;
    note: string | null;
};

type MapVillage = {
    id: number;
    village_name: string;
    player_name: string;
    alliance_tag: string | null;
    region_name: string | null;
    population: number;
    coords: {
        x: number;
        y: number;
    };
    map: {
        x: number;
        y: number;
    };
    legend_key: string;
    legend_type: 'alliance' | 'player' | 'region';
    legend_label: string;
    legend_parent_label: string | null;
    legend_note: string | null;
    color: string;
    stroke_color: string;
};

type MapData = {
    status: 'choose_world' | 'choose_criteria' | 'waiting_snapshot' | 'empty' | 'ready';
    has_criteria: boolean;
    matched_village_count: number;
    criteria: {
        alliances: string[];
        players: string[];
        regions: string[];
    };
    villages: MapVillage[];
    legend: MapLegendItem[];
    bounds: {
        min_x: number;
        max_x: number;
        min_y: number;
        max_y: number;
    } | null;
    view_box: {
        x: number;
        y: number;
        width: number;
        height: number;
    };
    world_size: number;
};

type Summary = {
    selectedWorldKey: string;
    selectedWorldName: string;
    selectedWorldBaseUrl: string;
    currentSnapshotDate: string | null;
    hasImportedSnapshot: boolean;
    matchedVillageCount: number;
    legendCount: number;
    hasCriteria: boolean;
    shareUrl: string | null;
};

const props = defineProps<{
    filters: FilterState;
    worlds: WorldOption[];
    summary: Summary;
    map: MapData;
}>();

const { t } = useI18n();

const form = reactive<FilterState>({ ...props.filters });
const worldSelectionError = ref(false);
const shareCopied = ref(false);
const selectedVillage = ref<MapVillage | null>(null);
const mapViewport = ref<HTMLElement | null>(null);
const activePointers = new Map<number, { x: number; y: number }>();
const interactionState = reactive({
    mode: 'idle' as 'idle' | 'pan' | 'pinch',
    suppressClick: false,
    moved: false,
    startX: 0,
    startY: 0,
    startPanX: 0,
    startPanY: 0,
    startDistance: 0,
    startScale: 1,
    startMidX: 0,
    startMidY: 0,
});
const mapTransform = reactive({
    scale: 1,
    panX: 0,
    panY: 0,
});
let removeNativeGestureGuards: (() => void) | null = null;

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

watch(
    () => props.map,
    () => {
        mapTransform.scale = 1;
        mapTransform.panX = 0;
        mapTransform.panY = 0;
        selectedVillage.value = null;
        activePointers.clear();
        interactionState.mode = 'idle';
        interactionState.suppressClick = false;
        interactionState.moved = false;
    },
    { deep: true },
);

watch(mapViewport, (element) => {
    removeNativeGestureGuards?.();
    removeNativeGestureGuards = null;

    if (!element) {
        return;
    }

    const preventNativeGesture = (event: Event) => {
        if (event.cancelable) {
            event.preventDefault();
        }
    };

    const preventNativeTouchMove = (event: TouchEvent) => {
        if (event.cancelable) {
            event.preventDefault();
        }
    };

    element.addEventListener('gesturestart', preventNativeGesture, { passive: false });
    element.addEventListener('gesturechange', preventNativeGesture, { passive: false });
    element.addEventListener('gestureend', preventNativeGesture, { passive: false });
    element.addEventListener('touchmove', preventNativeTouchMove, { passive: false });

    removeNativeGestureGuards = () => {
        element.removeEventListener('gesturestart', preventNativeGesture);
        element.removeEventListener('gesturechange', preventNativeGesture);
        element.removeEventListener('gestureend', preventNativeGesture);
        element.removeEventListener('touchmove', preventNativeTouchMove);
    };
});

onBeforeUnmount(() => {
    removeNativeGestureGuards?.();
});

const selectedWorld = computed(() => props.worlds.find((world) => world.key === form.world) ?? null);
const resultTitle = computed(() => props.summary.selectedWorldName || t('map_builder.state.choose_world_title'));
const baseViewBox = computed(() => props.map.view_box);
const interactiveViewBox = computed(() => {
    const box = baseViewBox.value;
    const viewportWidth = mapViewport.value?.clientWidth ?? 0;
    const viewportHeight = mapViewport.value?.clientHeight ?? 0;
    const scaledWidth = box.width / mapTransform.scale;
    const scaledHeight = box.height / mapTransform.scale;
    const baseCenterX = box.x + box.width / 2;
    const baseCenterY = box.y + box.height / 2;
    const centerShiftX =
        viewportWidth > 0 ? (mapTransform.panX * box.width) / (viewportWidth * mapTransform.scale) : 0;
    const centerShiftY =
        viewportHeight > 0 ? (mapTransform.panY * box.height) / (viewportHeight * mapTransform.scale) : 0;
    const centerX = clamp(baseCenterX - centerShiftX, scaledWidth / 2, props.map.world_size - scaledWidth / 2);
    const centerY = clamp(baseCenterY - centerShiftY, scaledHeight / 2, props.map.world_size - scaledHeight / 2);

    return {
        x: centerX - scaledWidth / 2,
        y: centerY - scaledHeight / 2,
        width: scaledWidth,
        height: scaledHeight,
    };
});
const viewBox = computed(() => {
    const box = interactiveViewBox.value;

    return `${box.x} ${box.y} ${box.width} ${box.height}`;
});

const hasRenderableMap = computed(() => props.map.status === 'ready' && props.map.villages.length > 0);
const visibleMapSpan = computed(() => {
    const width = interactiveViewBox.value.width || 1;
    const height = interactiveViewBox.value.height || 1;

    return Math.max(width, height);
});
const maxZoomScale = computed(() => {
    const width = baseViewBox.value.width || 1;
    const height = baseViewBox.value.height || 1;
    const dominantSpan = Math.max(width, height);

    return Math.max(1, dominantSpan / 10);
});
const mapPixelsPerUnit = computed(() => {
    const viewportWidth = mapViewport.value?.clientWidth ?? 0;
    const viewportHeight = mapViewport.value?.clientHeight ?? 0;
    const viewBoxWidth = interactiveViewBox.value.width || 1;
    const viewBoxHeight = interactiveViewBox.value.height || 1;

    if (viewportWidth <= 0 || viewportHeight <= 0) {
        return mapTransform.scale;
    }

    return Math.min(viewportWidth / viewBoxWidth, viewportHeight / viewBoxHeight);
});
const villagePointDiameterUnits = computed(() => {
    const shrinkStartSpan = 140;
    const shrinkEndSpan = 35;
    const shrinkProgress = clamp((shrinkStartSpan - visibleMapSpan.value) / (shrinkStartSpan - shrinkEndSpan), 0, 1);

    return 3.2 - shrinkProgress * 2.2;
});
const villagePointRadius = computed(() => {
    return Math.max(0.5, villagePointDiameterUnits.value / 2);
});
const villagePointStrokeWidth = computed(() => {
    const pixelsPerUnit = mapPixelsPerUnit.value;

    if (pixelsPerUnit <= 0 || visibleMapSpan.value <= 26) {
        return 0;
    }

    const targetStrokePx = visibleMapSpan.value >= 90 ? 0.9 : 0.55;

    return Math.max(0.01, targetStrokePx / pixelsPerUnit);
});
const villageHitRadius = computed(() => {
    const pixelsPerUnit = mapPixelsPerUnit.value;

    if (pixelsPerUnit <= 0) {
        return 1.2;
    }

    const radius = 10 / pixelsPerUnit;

    return Math.max(villagePointRadius.value, radius);
});

const validateWorldSelection = (): boolean => {
    const isValid = Boolean(form.world);
    worldSelectionError.value = !isValid;

    return isValid;
};

const cleanedFilters = (): Record<string, string> => {
    const payload: Record<string, string> = {};

    if (form.world.trim()) payload.world = form.world.trim();
    if (form.alliance_tags.trim()) payload.alliance_tags = form.alliance_tags.trim();
    if (form.player_names.trim()) payload.player_names = form.player_names.trim();
    if (form.region_names.trim()) payload.region_names = form.region_names.trim();

    return payload;
};

const submit = () => {
    if (!validateWorldSelection()) {
        return;
    }

    router.get('/map-builder', cleanedFilters(), {
        preserveScroll: true,
        preserveState: false,
        replace: false,
    });
};

const reset = () => {
    shareCopied.value = false;
    worldSelectionError.value = false;

    router.get(
        '/map-builder',
        {},
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

const copyShareLink = async () => {
    if (!props.summary.shareUrl) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.summary.shareUrl);
        shareCopied.value = true;
        window.setTimeout(() => {
            shareCopied.value = false;
        }, 2000);
    } catch {
        shareCopied.value = false;
    }
};

const criteriaChips = computed(() => [
    ...props.map.criteria.alliances.map((value) => ({ key: `alliance:${value}`, label: `${t('map_builder.criteria.alliance_prefix')}: ${value}` })),
    ...props.map.criteria.players.map((value) => ({ key: `player:${value}`, label: `${t('map_builder.criteria.player_prefix')}: ${value}` })),
    ...props.map.criteria.regions.map((value) => ({ key: `region:${value}`, label: `${t('map_builder.criteria.region_prefix')}: ${value}` })),
]);

const stateTitle = computed(() => t(`map_builder.state.${props.map.status}_title`));
const stateDescription = computed(() => t(`map_builder.state.${props.map.status}_description`));

const legendNote = (item: MapLegendItem): string | null => {
    if (item.type === 'player' && item.parent_label) {
        return `${t('map_builder.legend.variant_of')} ${item.parent_label}`;
    }

    return null;
};

const boundsLabel = computed(() => {
    if (!props.map.bounds) {
        return null;
    }

    return `X ${props.map.bounds.min_x} -> ${props.map.bounds.max_x} / Y ${props.map.bounds.min_y} -> ${props.map.bounds.max_y}`;
});

const clamp = (value: number, min: number, max: number): number => Math.min(max, Math.max(min, value));

const clampPan = (): void => {
    const viewport = mapViewport.value;

    if (!viewport) {
        return;
    }

    const viewportWidth = viewport.clientWidth;
    const viewportHeight = viewport.clientHeight;
    const box = baseViewBox.value;
    const scaledWidth = box.width / mapTransform.scale;
    const scaledHeight = box.height / mapTransform.scale;
    const baseCenterX = box.x + box.width / 2;
    const baseCenterY = box.y + box.height / 2;
    const desiredCenterShiftX =
        viewportWidth > 0 ? (mapTransform.panX * box.width) / (viewportWidth * mapTransform.scale) : 0;
    const desiredCenterShiftY =
        viewportHeight > 0 ? (mapTransform.panY * box.height) / (viewportHeight * mapTransform.scale) : 0;
    const desiredCenterX = baseCenterX - desiredCenterShiftX;
    const desiredCenterY = baseCenterY - desiredCenterShiftY;
    const clampedCenterX = clamp(desiredCenterX, scaledWidth / 2, props.map.world_size - scaledWidth / 2);
    const clampedCenterY = clamp(desiredCenterY, scaledHeight / 2, props.map.world_size - scaledHeight / 2);

    mapTransform.panX =
        viewportWidth > 0
            ? ((baseCenterX - clampedCenterX) * viewportWidth * mapTransform.scale) / box.width
            : 0;
    mapTransform.panY =
        viewportHeight > 0
            ? ((baseCenterY - clampedCenterY) * viewportHeight * mapTransform.scale) / box.height
            : 0;
};

const applyScale = (nextScale: number): void => {
    mapTransform.scale = clamp(nextScale, 1, maxZoomScale.value);
    clampPan();
};

const pointerDistance = (first: { x: number; y: number }, second: { x: number; y: number }): number =>
    Math.hypot(second.x - first.x, second.y - first.y);

const pointerMidpoint = (first: { x: number; y: number }, second: { x: number; y: number }): { x: number; y: number } => ({
    x: (first.x + second.x) / 2,
    y: (first.y + second.y) / 2,
});

const beginPanFromPointer = (pointer: { x: number; y: number }): void => {
    interactionState.mode = 'pan';
    interactionState.startX = pointer.x;
    interactionState.startY = pointer.y;
    interactionState.startPanX = mapTransform.panX;
    interactionState.startPanY = mapTransform.panY;
};

const beginPinchFromPointers = (): void => {
    const [first, second] = Array.from(activePointers.values());

    if (!first || !second) {
        return;
    }

    interactionState.mode = 'pinch';
    interactionState.startDistance = pointerDistance(first, second);
    interactionState.startScale = mapTransform.scale;
    interactionState.startPanX = mapTransform.panX;
    interactionState.startPanY = mapTransform.panY;

    const midpoint = pointerMidpoint(first, second);
    interactionState.startMidX = midpoint.x;
    interactionState.startMidY = midpoint.y;
};

const onMapPointerDown = (event: PointerEvent) => {
    if (event.pointerType === 'mouse' && event.button !== 0) {
        return;
    }

    mapViewport.value?.setPointerCapture?.(event.pointerId);
    activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

    if (activePointers.size === 1) {
        interactionState.moved = false;
        beginPanFromPointer({ x: event.clientX, y: event.clientY });
    } else if (activePointers.size === 2) {
        beginPinchFromPointers();
    }
};

const onMapPointerMove = (event: PointerEvent) => {
    if (!activePointers.has(event.pointerId)) {
        return;
    }

    activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

    if (interactionState.mode === 'pinch' && activePointers.size >= 2) {
        const [first, second] = Array.from(activePointers.values());

        if (!first || !second) {
            return;
        }

        const distance = pointerDistance(first, second);
        const midpoint = pointerMidpoint(first, second);

        if (Math.abs(distance - interactionState.startDistance) > 4 || Math.abs(midpoint.x - interactionState.startMidX) > 4 || Math.abs(midpoint.y - interactionState.startMidY) > 4) {
            interactionState.moved = true;
            interactionState.suppressClick = true;
        }

        applyScale(interactionState.startScale * (distance / Math.max(1, interactionState.startDistance)));
        mapTransform.panX = interactionState.startPanX + (midpoint.x - interactionState.startMidX);
        mapTransform.panY = interactionState.startPanY + (midpoint.y - interactionState.startMidY);
        clampPan();

        if (event.cancelable) {
            event.preventDefault();
        }

        return;
    }

    if (interactionState.mode !== 'pan' || activePointers.size !== 1) {
        return;
    }

    const deltaX = event.clientX - interactionState.startX;
    const deltaY = event.clientY - interactionState.startY;

    if (Math.abs(deltaX) > 4 || Math.abs(deltaY) > 4) {
        interactionState.moved = true;
        interactionState.suppressClick = true;
    }

    mapTransform.panX = interactionState.startPanX + deltaX;
    mapTransform.panY = interactionState.startPanY + deltaY;
    clampPan();

    if (interactionState.moved && event.cancelable) {
        event.preventDefault();
    }
};

const resetInteractionClickSuppression = (): void => {
    window.setTimeout(() => {
        interactionState.suppressClick = false;
    }, 0);
};

const onMapPointerEnd = (event: PointerEvent) => {
    mapViewport.value?.releasePointerCapture?.(event.pointerId);
    activePointers.delete(event.pointerId);

    if (activePointers.size === 0) {
        interactionState.mode = 'idle';
        resetInteractionClickSuppression();

        return;
    }

    if (activePointers.size === 1) {
        const [remainingPointer] = Array.from(activePointers.values());

        if (!remainingPointer) {
            interactionState.mode = 'idle';
            resetInteractionClickSuppression();

            return;
        }

        beginPanFromPointer(remainingPointer);
        interactionState.moved = true;
        interactionState.suppressClick = true;
    } else if (activePointers.size >= 2) {
        beginPinchFromPointers();
    }
};

const onMapWheel = (event: WheelEvent) => {
    if (!hasRenderableMap.value) {
        return;
    }

    if (event.cancelable) {
        event.preventDefault();
    }

    const zoomFactor = event.deltaY < 0 ? 1.12 : 0.9;
    applyScale(mapTransform.scale * zoomFactor);
};

const suppressDraggedClick = (event: MouseEvent) => {
    if (!interactionState.suppressClick) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
};

const openVillageModal = (village: MapVillage): void => {
    if (interactionState.suppressClick) {
        return;
    }

    selectedVillage.value = village;
};

const closeVillageModal = (): void => {
    selectedVillage.value = null;
};
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

            <section class="mt-10 grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                <article class="rounded-[32px] border border-[#1f1a14]/10 bg-[#1d262b] p-6 text-[#edf3f6] shadow-[0_24px_80px_rgba(27,39,48,0.18)] sm:p-8">
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

                    <form class="mt-8 grid gap-5" @submit.prevent="submit">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-3">
                                <label class="text-xs font-semibold uppercase tracking-[0.22em] text-[#b8cad5]">
                                    {{ t('map_builder.filters.world_label') }}
                                </label>
                                <div class="relative">
                                    <div
                                        v-if="worldSelectionError"
                                        class="absolute -top-3 left-4 z-10 -translate-y-full rounded-full bg-[#dc5a4a] px-3 py-1 text-xs font-semibold text-white shadow-[0_12px_24px_rgba(220,90,74,0.3)]"
                                    >
                                        {{ t('map_builder.filters.choose_world_error') }}
                                    </div>
                                    <select
                                        v-model="form.world"
                                        :class="[
                                            'w-full rounded-2xl border bg-white/5 px-4 py-3 text-sm text-[#edf3f6] outline-none',
                                            worldSelectionError ? 'border-[#dc5a4a] ring-2 ring-[#dc5a4a]/30' : 'border-white/10',
                                        ]"
                                    >
                                        <option value="" class="text-[#1f1a14]">{{ t('map_builder.filters.choose_world_placeholder') }}</option>
                                        <option v-for="world in worlds" :key="world.key" :value="world.key" class="text-[#1f1a14]">
                                            {{ world.name }}
                                        </option>
                                    </select>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-full border border-white/10 bg-white/5 px-4 py-3 text-sm font-medium text-[#bfe3ff] transition hover:bg-white/10 hover:text-white"
                                    @click="openSelectedWorld"
                                >
                                    {{ t('map_builder.filters.open_world') }}
                                </button>
                            </div>

                            <div class="rounded-[28px] border border-white/10 bg-white/5 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#9fd1ef]">
                                    {{ t('map_builder.summary.title') }}
                                </p>
                                <div class="mt-4 grid gap-3 text-sm text-[#d6e2e8]">
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-[#9fb5c2]">{{ t('map_builder.summary.world') }}</span>
                                        <span class="text-right font-medium">{{ props.summary.selectedWorldName || t('map_builder.state.choose_world_title') }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-[#9fb5c2]">{{ t('map_builder.summary.snapshot') }}</span>
                                        <span class="text-right font-medium">{{ props.summary.currentSnapshotDate ?? t('map_builder.results.no_data') }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-[#9fb5c2]">{{ t('map_builder.summary.villages') }}</span>
                                        <span class="text-right font-medium">{{ props.summary.matchedVillageCount }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-[#9fb5c2]">{{ t('map_builder.summary.legend') }}</span>
                                        <span class="text-right font-medium">{{ props.summary.legendCount }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-3">
                            <label class="grid gap-3">
                                <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[#b8cad5]">
                                    {{ t('map_builder.filters.alliance_tags_label') }}
                                </span>
                                <textarea
                                    v-model="form.alliance_tags"
                                    rows="6"
                                    :placeholder="t('map_builder.filters.alliance_tags_placeholder')"
                                    class="min-h-[160px] rounded-[24px] border border-white/10 bg-white/5 px-4 py-4 text-sm leading-7 text-[#edf3f6] outline-none placeholder:text-[#8ea5b2]"
                                />
                            </label>

                            <label class="grid gap-3">
                                <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[#b8cad5]">
                                    {{ t('map_builder.filters.player_names_label') }}
                                </span>
                                <textarea
                                    v-model="form.player_names"
                                    rows="6"
                                    :placeholder="t('map_builder.filters.player_names_placeholder')"
                                    class="min-h-[160px] rounded-[24px] border border-white/10 bg-white/5 px-4 py-4 text-sm leading-7 text-[#edf3f6] outline-none placeholder:text-[#8ea5b2]"
                                />
                            </label>

                            <label class="grid gap-3">
                                <span class="text-xs font-semibold uppercase tracking-[0.22em] text-[#b8cad5]">
                                    {{ t('map_builder.filters.region_names_label') }}
                                </span>
                                <textarea
                                    v-model="form.region_names"
                                    rows="6"
                                    :placeholder="t('map_builder.filters.region_names_placeholder')"
                                    class="min-h-[160px] rounded-[24px] border border-white/10 bg-white/5 px-4 py-4 text-sm leading-7 text-[#edf3f6] outline-none placeholder:text-[#8ea5b2]"
                                />
                            </label>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-white/10 pt-5 xl:flex-row xl:items-center xl:justify-between">
                            <p class="max-w-3xl text-sm leading-7 text-[#c6d3da]">
                                {{ t('map_builder.filters.help') }}
                            </p>

                            <div class="flex flex-wrap gap-3">
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-full bg-[#7fc4f1] px-5 py-3 text-sm font-medium text-[#0f1a21] transition hover:bg-[#a6dcfa]"
                                >
                                    {{ t('map_builder.filters.apply') }}
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-full border border-white/15 px-5 py-3 text-sm font-medium text-[#edf3f6] transition hover:bg-white/10"
                                    @click="reset"
                                >
                                    {{ t('map_builder.filters.reset') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </article>

                <article class="rounded-[32px] border border-[#1f1a14]/10 bg-white p-6 shadow-[0_20px_60px_rgba(56,43,27,0.08)] sm:p-8">
                    <div class="flex flex-col gap-4 border-b border-[#1f1a14]/10 pb-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#3f6d8f]">
                                {{ t('map_builder.results.title') }}
                            </p>
                            <h2 class="mt-4 text-3xl font-semibold tracking-[-0.03em] text-[#1c1814]">
                                {{ resultTitle }}
                            </h2>
                            <p class="mt-3 max-w-3xl text-sm leading-8 text-[#5b5047]">
                                {{
                                    hasRenderableMap
                                        ? t('map_builder.results.ready_description')
                                        : stateDescription
                                }}
                            </p>
                        </div>

                        <div v-if="props.summary.shareUrl" class="grid min-w-0 gap-2 lg:max-w-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8b4a27]">
                                {{ t('map_builder.share.title') }}
                            </p>
                            <div class="flex gap-2">
                                <input
                                    :value="props.summary.shareUrl"
                                    readonly
                                    class="min-w-0 flex-1 rounded-2xl border border-[#1f1a14]/10 bg-[#f7f4ee] px-4 py-3 text-sm text-[#1f1a14] outline-none"
                                />
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-2xl bg-[#1f1a14] px-4 py-3 text-sm font-medium text-white transition hover:bg-[#3f6d8f]"
                                    @click="copyShareLink"
                                >
                                    {{ shareCopied ? t('map_builder.share.copied') : t('map_builder.share.copy') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="criteriaChips.length > 0" class="mt-5 flex flex-wrap gap-2">
                        <span
                            v-for="chip in criteriaChips"
                            :key="chip.key"
                            class="rounded-full bg-[#f2f5f7] px-3 py-2 text-xs font-medium text-[#31424d]"
                        >
                            {{ chip.label }}
                        </span>
                    </div>

                    <div v-if="hasRenderableMap" class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_280px]">
                        <div class="overflow-hidden rounded-[28px] border border-[#1f1a14]/10 bg-[#0f171c] p-4 sm:p-5">
                            <div class="mb-4 flex flex-wrap gap-2 text-xs font-medium text-[#d8e3e8]">
                                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-2">
                                    {{ t('map_builder.results.interaction_hint') }}
                                </span>
                            </div>

                            <div
                                ref="mapViewport"
                                class="relative overflow-hidden rounded-[22px] border border-white/10 bg-[radial-gradient(circle_at_center,rgba(127,196,241,0.12),transparent_45%)] touch-none [overscroll-behavior:contain]"
                                @click.capture="suppressDraggedClick"
                                @pointercancel="onMapPointerEnd"
                                @pointerdown="onMapPointerDown"
                                @pointermove="onMapPointerMove"
                                @pointerup="onMapPointerEnd"
                                @wheel="onMapWheel"
                            >
                                <svg
                                    class="aspect-square w-full cursor-grab active:cursor-grabbing"
                                    :viewBox="viewBox"
                                    xmlns="http://www.w3.org/2000/svg"
                                    role="img"
                                    :aria-label="t('map_builder.results.map_aria_label')"
                                >
                                    <defs>
                                        <pattern id="map-grid" width="40" height="40" patternUnits="userSpaceOnUse">
                                            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="1" />
                                        </pattern>
                                    </defs>

                                    <rect :width="props.map.world_size" :height="props.map.world_size" fill="#111a20" />
                                    <rect :width="props.map.world_size" :height="props.map.world_size" fill="url(#map-grid)" />

                                    <g v-for="village in props.map.villages" :key="village.id">
                                        <circle
                                            :cx="village.map.x"
                                            :cy="village.map.y"
                                            :r="villagePointRadius"
                                            :fill="village.color"
                                            :stroke="village.stroke_color"
                                            :stroke-width="villagePointStrokeWidth"
                                            class="pointer-events-none"
                                        />
                                        <circle
                                            :cx="village.map.x"
                                            :cy="village.map.y"
                                            :r="villageHitRadius"
                                            fill="transparent"
                                            class="cursor-pointer"
                                            @click.stop="openVillageModal(village)"
                                        />
                                    </g>
                                </svg>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-3 text-xs font-medium text-[#d8e3e8]">
                                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-2">
                                    {{ t('map_builder.summary.villages') }}: {{ props.summary.matchedVillageCount }}
                                </span>
                                <span v-if="boundsLabel" class="rounded-full border border-white/10 bg-white/5 px-3 py-2">
                                    {{ boundsLabel }}
                                </span>
                            </div>
                        </div>

                        <div class="grid gap-4">
                            <div class="rounded-[28px] border border-[#1f1a14]/10 bg-[#f8f3eb] p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8b4a27]">
                                    {{ t('map_builder.legend.title') }}
                                </p>

                                <div class="mt-4 grid gap-3">
                                    <div
                                        v-for="item in props.map.legend"
                                        :key="item.key"
                                        class="rounded-[22px] border px-4 py-3"
                                        :class="item.type === 'player' && item.parent_label ? 'border-[#3f6d8f]/18 bg-[#eef5fb]' : 'border-[#1f1a14]/10 bg-white'"
                                    >
                                        <div class="flex items-start gap-3">
                                            <span class="mt-1 h-4 w-4 shrink-0 rounded-full border border-black/10" :style="{ backgroundColor: item.color }" />
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center justify-between gap-3">
                                                    <p class="truncate text-sm font-semibold text-[#1c1814]">
                                                        {{ item.label }}
                                                    </p>
                                                    <span
                                                        v-if="item.count > 0"
                                                        class="shrink-0 rounded-full bg-[#f3ede4] px-2.5 py-1 text-xs font-medium text-[#5b5047]"
                                                    >
                                                        {{ item.count }}
                                                    </span>
                                                </div>
                                                <p v-if="legendNote(item)" class="mt-1 text-xs leading-6 text-[#6b6259]">
                                                    {{ legendNote(item) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="mt-6 rounded-[28px] border border-dashed border-[#3f6d8f]/25 bg-[#f7fafb] p-8">
                        <h3 class="text-2xl font-semibold tracking-[-0.03em] text-[#1c1814]">
                            {{ stateTitle }}
                        </h3>
                        <p class="mt-4 max-w-2xl text-sm leading-8 text-[#5b5047]">
                            {{ stateDescription }}
                        </p>
                    </div>
                </article>
            </section>
        </div>

        <div
            v-if="selectedVillage"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#0d1318]/70 px-6 py-10 backdrop-blur-sm"
            @click.self="closeVillageModal"
        >
            <div class="w-full max-w-md rounded-[28px] border border-[#1f1a14]/10 bg-white p-6 shadow-[0_24px_80px_rgba(15,19,24,0.25)] sm:p-7">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#3f6d8f]">
                            {{ t('map_builder.modal.title') }}
                        </p>
                        <h3 class="mt-3 text-2xl font-semibold tracking-[-0.03em] text-[#1c1814]">
                            {{ selectedVillage.village_name }}
                        </h3>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[#1f1a14]/10 text-[#5b5047] transition hover:bg-[#f7f4ee] hover:text-[#1c1814]"
                        :aria-label="t('map_builder.modal.close')"
                        @click="closeVillageModal"
                    >
                        ×
                    </button>
                </div>

                <dl class="mt-6 grid gap-4 text-sm">
                    <div class="rounded-[22px] bg-[#f7f4ee] px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-[#8b4a27]">{{ t('map_builder.modal.coords') }}</dt>
                        <dd class="mt-2 font-medium text-[#1c1814]">{{ selectedVillage.coords.x }}|{{ selectedVillage.coords.y }}</dd>
                    </div>
                    <div class="rounded-[22px] bg-[#f7f4ee] px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-[#8b4a27]">{{ t('map_builder.modal.population') }}</dt>
                        <dd class="mt-2 font-medium text-[#1c1814]">{{ selectedVillage.population }}</dd>
                    </div>
                    <div class="rounded-[22px] bg-[#f7f4ee] px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-[#8b4a27]">{{ t('map_builder.modal.player') }}</dt>
                        <dd class="mt-2 font-medium text-[#1c1814]">{{ selectedVillage.player_name }}</dd>
                    </div>
                    <div v-if="selectedVillage.alliance_tag" class="rounded-[22px] bg-[#f7f4ee] px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-[#8b4a27]">{{ t('map_builder.modal.alliance') }}</dt>
                        <dd class="mt-2 font-medium text-[#1c1814]">{{ selectedVillage.alliance_tag }}</dd>
                    </div>
                    <div v-if="selectedVillage.region_name" class="rounded-[22px] bg-[#f7f4ee] px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-[#8b4a27]">{{ t('map_builder.modal.region') }}</dt>
                        <dd class="mt-2 font-medium text-[#1c1814]">{{ selectedVillage.region_name }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</template>
