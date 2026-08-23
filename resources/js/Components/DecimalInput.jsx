import TextInput from '@/Components/TextInput';
import { formatDecimalInput, sanitizeDecimalInput } from '@/utils/decimalInput';
import { forwardRef } from 'react';

const DecimalInput = forwardRef(function DecimalInput(
    { value, onChange, decimals = 2, className = '', onBlur, ...props },
    ref,
) {
    const handleChange = (event) => {
        onChange(sanitizeDecimalInput(event.target.value, decimals));
    };

    const handleBlur = (event) => {
        const formatted = formatDecimalInput(event.target.value, decimals);
        onChange(formatted);
        onBlur?.(event);
    };

    return (
        <TextInput
            {...props}
            ref={ref}
            type="text"
            inputMode="decimal"
            className={`text-right tabular-nums ${className}`.trim()}
            value={value ?? ''}
            onChange={handleChange}
            onBlur={handleBlur}
        />
    );
});

export default DecimalInput;
