import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

type TranslationTree = Record<string, string | TranslationTree>;
type LocaleOption = { code: string; label: string };

type SharedLocale = {
    current: string;
    available: LocaleOption[];
};

type SharedPageProps = {
    locale: SharedLocale;
    translations: TranslationTree;
};

const getValue = (tree: TranslationTree, path: string): string => {
    const value = path.split('.').reduce<string | TranslationTree | undefined>((current, segment) => {
        if (typeof current !== 'object' || current === null) {
            return undefined;
        }

        return current[segment];
    }, tree);

    return typeof value === 'string' ? value : path;
};

export const useI18n = () => {
    const page = usePage<SharedPageProps>();

    const locale = computed(() => page.props.locale.current);
    const locales = computed(() => page.props.locale.available);
    const messages = computed(() => page.props.translations);

    const t = (path: string) => getValue(messages.value, path);

    return {
        locale,
        locales,
        t,
    };
};
