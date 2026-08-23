import { Combobox, Transition } from '@headlessui/react';
import { ChevronsUpDown } from 'lucide-react';
import { Fragment, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

function normalize(text) {
    return String(text ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function OptionsPortal({ open, anchorRef, setQuery, filtered, allowClear, placeholder }) {
    const [menuStyle, setMenuStyle] = useState({});

    useLayoutEffect(() => {
        if (!open || !anchorRef.current) {
            return undefined;
        }

        const update = () => {
            const rect = anchorRef.current.getBoundingClientRect();
            const width = Math.max(rect.width, 240);
            const spaceBelow = window.innerHeight - rect.bottom;
            const openUp = spaceBelow < 240 && rect.top > spaceBelow;

            setMenuStyle({
                position: 'fixed',
                left: Math.min(rect.left, window.innerWidth - width - 8),
                width,
                top: openUp ? undefined : rect.bottom + 4,
                bottom: openUp ? window.innerHeight - rect.top + 4 : undefined,
                zIndex: 70,
            });
        };

        update();
        window.addEventListener('scroll', update, true);
        window.addEventListener('resize', update);

        return () => {
            window.removeEventListener('scroll', update, true);
            window.removeEventListener('resize', update);
        };
    }, [open, anchorRef, filtered.length]);

    if (typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <Transition
            as={Fragment}
            show={open}
            leave="transition ease-in duration-100"
            leaveFrom="opacity-100"
            leaveTo="opacity-0"
            afterLeave={() => setQuery('')}
        >
            <Combobox.Options
                static
                style={menuStyle}
                className="max-h-60 overflow-auto rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-black/10 focus:outline-none dark:bg-slate-800 dark:ring-white/10"
            >
                {allowClear && (
                    <Combobox.Option
                        value={null}
                        className={({ active }) =>
                            `cursor-pointer select-none px-3 py-2 ${
                                active ? 'bg-emerald-600 text-white' : 'text-slate-500 dark:text-slate-400'
                            }`
                        }
                    >
                        {placeholder}
                    </Combobox.Option>
                )}
                {filtered.length === 0 ? (
                    <div className="px-3 py-2 text-slate-500 dark:text-slate-400">Sin resultados</div>
                ) : (
                    filtered.map((opt) => (
                        <Combobox.Option
                            key={String(opt.value)}
                            value={opt}
                            className={({ active }) =>
                                `cursor-pointer select-none px-3 py-2 ${
                                    active ? 'bg-emerald-600 text-white' : 'text-slate-900 dark:text-slate-100'
                                }`
                            }
                        >
                            {opt.label}
                        </Combobox.Option>
                    ))
                )}
            </Combobox.Options>
        </Transition>,
        document.body,
    );
}

/**
 * Select con búsqueda local.
 * options: [{ value, label, search? }]
 * onChange(value, option|null)
 */
export default function SearchableSelect({
    options = [],
    value = '',
    onChange,
    placeholder = 'Seleccionar...',
    className = '',
    inputClassName = 'w-full rounded-md border-slate-300 bg-white text-slate-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm pr-8 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-400',
    disabled = false,
    allowClear = true,
    autoSelectSingle = false,
}) {
    const [query, setQuery] = useState('');
    // Selección local hasta que el value controlado (p. ej. Inertia) se sincronice.
    const [pending, setPending] = useState(null);
    const anchorRef = useRef(null);
    const onChangeRef = useRef(onChange);
    const ignoreNullRef = useRef(false);
    onChangeRef.current = onChange;

    const selected = useMemo(() => {
        const fromProp = options.find((o) => String(o.value) === String(value ?? '')) ?? null;
        if (fromProp) {
            return fromProp;
        }
        return pending;
    }, [options, value, pending]);

    useEffect(() => {
        if (pending && String(value ?? '') === String(pending.value)) {
            setPending(null);
        }
    }, [value, pending]);

    const filtered = useMemo(() => {
        const q = normalize(query).trim();
        if (!q) {
            return options;
        }

        return options.filter((o) => {
            const haystack = normalize(`${o.label} ${o.search ?? ''}`);
            return q.split(/\s+/).every((token) => haystack.includes(token));
        });
    }, [options, query]);

    const commit = (opt) => {
        setQuery('');
        setPending(opt);
        onChangeRef.current?.(opt ? opt.value : '', opt ?? null);
    };

    useEffect(() => {
        if (!autoSelectSingle || disabled) {
            return;
        }

        const q = normalize(query).trim();
        if (!q || filtered.length !== 1) {
            return;
        }

        const only = filtered[0];
        const currentId = value ?? pending?.value ?? '';
        if (String(only.value) === String(currentId)) {
            return;
        }

        ignoreNullRef.current = true;
        commit(only);

        // Cerrar el menú; el Combobox nullable suele emitir null al blur — se ignora.
        requestAnimationFrame(() => {
            anchorRef.current?.querySelector('input')?.blur();
            window.setTimeout(() => {
                ignoreNullRef.current = false;
            }, 50);
        });
    }, [autoSelectSingle, disabled, filtered, query, value, pending]);

    return (
        <Combobox
            value={selected}
            by="value"
            disabled={disabled}
            nullable={allowClear}
            onChange={(opt) => {
                if (opt == null && ignoreNullRef.current) {
                    return;
                }
                commit(opt);
            }}
        >
            {({ open }) => (
                <div className={`relative ${className}`}>
                    <div ref={anchorRef} className="relative">
                        <Combobox.Input
                            className={inputClassName}
                            displayValue={(opt) => opt?.label ?? ''}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={placeholder}
                            autoComplete="off"
                        />
                        <Combobox.Button
                            type="button"
                            className="absolute inset-y-0 right-0 flex items-center pr-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        >
                            <ChevronsUpDown className="h-4 w-4" strokeWidth={2} />
                        </Combobox.Button>
                    </div>

                    <OptionsPortal
                        open={open}
                        anchorRef={anchorRef}
                        setQuery={setQuery}
                        filtered={filtered}
                        allowClear={allowClear}
                        placeholder={placeholder}
                    />
                </div>
            )}
        </Combobox>
    );
}

export function toCuentaOptions(cuentas) {
    return (cuentas ?? []).map((c) => ({
        value: c.id,
        label: `${c.codigo} — ${c.descripcion}`,
        search: `${c.codigo} ${c.descripcion}`,
        cuenta: c,
    }));
}
