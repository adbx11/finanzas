import SearchableSelect, { toCuentaOptions } from '@/Components/SearchableSelect';
import { useMemo } from 'react';

/**
 * Select buscable de cuentas contables.
 * onChange(id, cuenta|null)
 */
export default function CuentaSelect({
    cuentas = [],
    value = '',
    onChange,
    placeholder = 'Seleccionar...',
    className = '',
    inputClassName,
    disabled = false,
    allowClear = true,
}) {
    const options = useMemo(() => toCuentaOptions(cuentas), [cuentas]);

    return (
        <SearchableSelect
            options={options}
            value={value}
            onChange={(id, option) => onChange?.(id, option?.cuenta ?? null)}
            placeholder={placeholder}
            className={className}
            inputClassName={inputClassName}
            disabled={disabled}
            allowClear={allowClear}
            autoSelectSingle
        />
    );
}
