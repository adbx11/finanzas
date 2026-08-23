import { Link, router } from '@inertiajs/react';
import { Copy, Pencil, Trash2 } from 'lucide-react';

const iconBtn =
    'inline-flex items-center justify-center rounded p-1.5 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500/40';

function ActionIcon({ as: Component = 'button', title, className, children, ...props }) {
    return (
        <Component
            title={title}
            aria-label={title}
            className={`${iconBtn} ${className}`}
            {...(Component === 'button' ? { type: 'button' } : {})}
            {...props}
        >
            {children}
        </Component>
    );
}

/**
 * Acciones de fila con íconos (Editar / Copiar / Eliminar).
 * Usar `editHref`/`copyHref` para navegación, o `onEdit`/`onCopy`/`onDelete` para callbacks.
 */
export default function RowActions({
    editHref,
    copyHref,
    onEdit,
    onCopy,
    onDelete,
    destroyRoute,
    destroyId,
    destroyMessage = '¿Eliminar?',
}) {
    const handleDelete = () => {
        if (onDelete) {
            onDelete();
            return;
        }
        if (destroyRoute && destroyId != null && confirm(destroyMessage)) {
            router.delete(route(destroyRoute, destroyId));
        }
    };

    return (
        <span className="inline-flex items-center justify-start gap-0.5 whitespace-nowrap">
            {(editHref || onEdit) && (
                editHref ? (
                    <ActionIcon as={Link} href={editHref} title="Editar" className="text-emerald-700 hover:bg-emerald-50 hover:text-emerald-800">
                        <Pencil className="h-4 w-4" strokeWidth={2} />
                    </ActionIcon>
                ) : (
                    <ActionIcon title="Editar" onClick={onEdit} className="text-emerald-700 hover:bg-emerald-50 hover:text-emerald-800">
                        <Pencil className="h-4 w-4" strokeWidth={2} />
                    </ActionIcon>
                )
            )}

            {(copyHref || onCopy) && (
                copyHref ? (
                    <ActionIcon as={Link} href={copyHref} title="Copiar" className="text-slate-600 hover:bg-slate-100 hover:text-slate-800">
                        <Copy className="h-4 w-4" strokeWidth={2} />
                    </ActionIcon>
                ) : (
                    <ActionIcon title="Copiar" onClick={onCopy} className="text-slate-600 hover:bg-slate-100 hover:text-slate-800">
                        <Copy className="h-4 w-4" strokeWidth={2} />
                    </ActionIcon>
                )
            )}

            {(onDelete || destroyRoute) && (
                <ActionIcon title="Eliminar" onClick={handleDelete} className="text-red-600 hover:bg-red-50 hover:text-red-700">
                    <Trash2 className="h-4 w-4" strokeWidth={2} />
                </ActionIcon>
            )}
        </span>
    );
}
