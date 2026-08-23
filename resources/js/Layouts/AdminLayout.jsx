import Dropdown from '@/Components/Dropdown';
import { configNav, mainNav, reportNav } from '@/config/navigation';
import { clearListState } from '@/utils/listState';
import {
    applyTheme,
    getStoredNavSections,
    getStoredThemePreference,
    resolveTheme,
    setStoredNavSections,
    toggleTheme,
} from '@/utils/theme';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';

const DEFAULT_SECTIONS = {
    operacion: true,
    informes: true,
    configuracion: true,
};

function NavItem({ item, onNavigate }) {
    const active = item.route && route().current(item.route);

    if (item.soon || !item.href) {
        return (
            <span className="block rounded-md px-3 py-2 text-sm text-slate-400 cursor-not-allowed">
                {item.name}
            </span>
        );
    }

    return (
        <Link
            href={route(item.href)}
            onClick={() => {
                if (item.resetListState) {
                    clearListState(item.resetListState);
                }
                onNavigate?.();
            }}
            className={`block rounded-md px-3 py-2 text-sm font-medium ${
                active
                    ? 'bg-emerald-600 text-white'
                    : 'text-slate-200 hover:bg-slate-700 hover:text-white'
            }`}
        >
            {item.name}
        </Link>
    );
}

function NavSection({ id, title, items, open, onToggle, onNavigate }) {
    if (!items.length) {
        return null;
    }

    return (
        <div>
            <button
                type="button"
                onClick={() => onToggle(id)}
                className="w-full flex items-center justify-between gap-2 px-3 py-1.5 rounded-md text-xs font-semibold uppercase tracking-wide text-slate-400 hover:bg-slate-700/60 hover:text-slate-200"
                aria-expanded={open}
            >
                <span>{title}</span>
                <ChevronDown
                    className={`h-3.5 w-3.5 shrink-0 transition-transform duration-200 ${open ? 'rotate-0' : '-rotate-90'}`}
                    strokeWidth={2.5}
                />
            </button>
            {open && (
                <div className="mt-1 space-y-1">
                    {items.map((item) => (
                        <NavItem key={item.name} item={item} onNavigate={onNavigate} />
                    ))}
                </div>
            )}
        </div>
    );
}

function SidebarContent({ appName, onNavigate, onClose, navConfig, sectionsOpen, onToggleSection }) {
    return (
        <>
            <div className="px-4 py-5 border-b border-slate-700 flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="text-lg font-semibold truncate">{appName}</div>
                    <div className="text-xs text-slate-400 mt-1">Finanzas personales</div>
                </div>
                {onClose && (
                    <button
                        type="button"
                        onClick={onClose}
                        className="lg:hidden rounded-md p-2 text-slate-300 hover:bg-slate-700 hover:text-white"
                        aria-label="Cerrar menú"
                    >
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                )}
            </div>

            <nav className="flex-1 overflow-y-auto p-3 space-y-3">
                <NavSection
                    id="operacion"
                    title="Operación"
                    items={navConfig.main}
                    open={sectionsOpen.operacion}
                    onToggle={onToggleSection}
                    onNavigate={onNavigate}
                />
                <NavSection
                    id="informes"
                    title="Informes"
                    items={navConfig.reports}
                    open={sectionsOpen.informes}
                    onToggle={onToggleSection}
                    onNavigate={onNavigate}
                />
                <NavSection
                    id="configuracion"
                    title="Configuración"
                    items={navConfig.config}
                    open={sectionsOpen.configuracion}
                    onToggle={onToggleSection}
                    onNavigate={onNavigate}
                />
            </nav>
        </>
    );
}

function MenuButton({ open, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="lg:hidden inline-flex items-center justify-center rounded-md border border-slate-200 bg-white p-2 text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            aria-expanded={open}
            aria-label={open ? 'Cerrar menú' : 'Abrir menú'}
        >
            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                {open ? (
                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                ) : (
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                )}
            </svg>
        </button>
    );
}

function ThemeToggle({ theme, onToggle }) {
    const isDark = theme === 'dark';
    return (
        <button
            type="button"
            onClick={onToggle}
            className="inline-flex items-center justify-center rounded-md border border-slate-200 bg-white p-2 text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            aria-label={isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'}
            title={isDark ? 'Modo claro' : 'Modo oscuro'}
        >
            {isDark ? <Sun className="h-4 w-4" strokeWidth={2} /> : <Moon className="h-4 w-4" strokeWidth={2} />}
        </button>
    );
}

export default function AdminLayout({ header, children }) {
    const { auth, appName } = usePage().props;
    const user = auth.user;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [theme, setTheme] = useState(() => (
        typeof window !== 'undefined' ? resolveTheme(getStoredThemePreference()) : 'light'
    ));
    const [sectionsOpen, setSectionsOpen] = useState(() => (
        typeof window !== 'undefined' ? getStoredNavSections(DEFAULT_SECTIONS) : DEFAULT_SECTIONS
    ));

    const isAdmin = Boolean(user?.roles?.some((r) => r.name === 'admin'));
    const navConfig = {
        main: mainNav,
        reports: reportNav,
        config: configNav.filter((item) => !item.adminOnly || isAdmin),
    };

    const closeSidebar = () => setSidebarOpen(false);

    const handleToggleSection = (id) => {
        setSectionsOpen((prev) => {
            const next = { ...prev, [id]: !prev[id] };
            setStoredNavSections(next);
            return next;
        });
    };

    const handleToggleTheme = () => {
        setTheme((prev) => toggleTheme(prev));
    };

    useEffect(() => {
        const preference = getStoredThemePreference();
        setTheme(resolveTheme(preference));
        applyTheme(preference);

        if (preference !== 'system') {
            return undefined;
        }

        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => {
            applyTheme('system');
            setTheme(resolveTheme('system'));
        };
        mq.addEventListener('change', onChange);
        return () => mq.removeEventListener('change', onChange);
    }, []);

    useEffect(() => {
        const handleEscape = (event) => {
            if (event.key === 'Escape') {
                setSidebarOpen(false);
            }
        };

        document.addEventListener('keydown', handleEscape);

        return () => document.removeEventListener('keydown', handleEscape);
    }, []);

    useEffect(() => {
        document.body.style.overflow = sidebarOpen ? 'hidden' : '';

        return () => {
            document.body.style.overflow = '';
        };
    }, [sidebarOpen]);

    return (
        <div className="min-h-screen bg-slate-100 dark:bg-slate-950 flex">
            <aside className="hidden lg:flex w-64 bg-slate-800 text-white flex-col shrink-0">
                <SidebarContent
                    appName={appName}
                    onNavigate={closeSidebar}
                    navConfig={navConfig}
                    sectionsOpen={sectionsOpen}
                    onToggleSection={handleToggleSection}
                />
            </aside>

            <div
                className={`fixed inset-0 z-40 lg:hidden transition-opacity duration-200 ${
                    sidebarOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'
                }`}
                aria-hidden={!sidebarOpen}
            >
                <button
                    type="button"
                    className="absolute inset-0 bg-slate-900/50"
                    onClick={closeSidebar}
                    aria-label="Cerrar menú"
                />
                <aside
                    className={`relative h-full w-[min(18rem,85vw)] max-w-full bg-slate-800 text-white flex flex-col shadow-xl transition-transform duration-200 ease-out ${
                        sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                    }`}
                >
                    <SidebarContent
                        appName={appName}
                        onNavigate={closeSidebar}
                        onClose={closeSidebar}
                        navConfig={navConfig}
                        sectionsOpen={sectionsOpen}
                        onToggleSection={handleToggleSection}
                    />
                </aside>
            </div>

            <div className="flex-1 flex flex-col min-w-0">
                <header className="bg-white border-b border-slate-200 sticky top-0 z-30 dark:bg-slate-900 dark:border-slate-700">
                    <div className="px-4 sm:px-6 py-3 sm:py-4 flex items-center gap-3">
                        <MenuButton open={sidebarOpen} onClick={() => setSidebarOpen((open) => !open)} />

                        <h1 className="flex-1 min-w-0 text-lg sm:text-xl font-semibold text-slate-800 truncate dark:text-slate-100">
                            {header}
                        </h1>

                        <ThemeToggle theme={theme} onToggle={handleToggleTheme} />

                        <Dropdown>
                            <Dropdown.Trigger>
                                <span className="inline-flex rounded-md">
                                    <button
                                        type="button"
                                        className="inline-flex items-center max-w-[10rem] sm:max-w-none rounded-md border border-slate-200 bg-white px-2.5 sm:px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 truncate dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                    >
                                        <span className="truncate">{user?.name || user?.username}</span>
                                    </button>
                                </span>
                            </Dropdown.Trigger>
                            <Dropdown.Content contentClasses="py-1 bg-white dark:bg-slate-800">
                                <Dropdown.Link href={route('logout')} method="post" as="button">
                                    Cerrar sesión
                                </Dropdown.Link>
                            </Dropdown.Content>
                        </Dropdown>
                    </div>
                </header>

                <main className="flex-1 p-4 sm:p-6 overflow-x-auto text-slate-800 dark:text-slate-100">{children}</main>
            </div>
        </div>
    );
}
