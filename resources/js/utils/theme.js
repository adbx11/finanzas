const THEME_KEY = 'finanzas.theme';
const NAV_SECTIONS_KEY = 'finanzas.navSections';

/** @returns {'light'|'dark'|'system'} */
export function getStoredThemePreference() {
    try {
        const stored = localStorage.getItem(THEME_KEY);
        if (stored === 'dark' || stored === 'light' || stored === 'system') {
            return stored;
        }
    } catch {
        // ignore
    }
    return 'system';
}

export function getSystemTheme() {
    if (typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        return 'dark';
    }
    return 'light';
}

/** Resolved theme for CSS class: 'light' | 'dark' */
export function resolveTheme(preference = getStoredThemePreference()) {
    if (preference === 'system') {
        return getSystemTheme();
    }
    return preference;
}

/** @deprecated use getStoredThemePreference + resolveTheme */
export function getStoredTheme() {
    return resolveTheme(getStoredThemePreference());
}

export function applyTheme(preference) {
    const resolved = resolveTheme(preference);
    const root = document.documentElement;
    if (resolved === 'dark') {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }
    try {
        localStorage.setItem(THEME_KEY, preference);
    } catch {
        // ignore
    }
    return resolved;
}

/** Toggle between light and dark (leaves system mode). */
export function toggleTheme(currentResolved) {
    const next = currentResolved === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    return next;
}

export function getStoredNavSections(defaults = {}) {
    try {
        const raw = localStorage.getItem(NAV_SECTIONS_KEY);
        if (!raw) {
            return { ...defaults };
        }
        return { ...defaults, ...JSON.parse(raw) };
    } catch {
        return { ...defaults };
    }
}

export function setStoredNavSections(sections) {
    try {
        localStorage.setItem(NAV_SECTIONS_KEY, JSON.stringify(sections));
    } catch {
        // ignore
    }
}
