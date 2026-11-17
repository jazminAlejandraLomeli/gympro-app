import { Badge } from '@/Components/ui';

/** Estado del clip para el panel del entrenador. */
export default function ClipStatusBadge({ status }) {
    switch (status) {
        case 'processing':
            return <Badge tone="amber">Procesando…</Badge>;
        case 'ready':
            return <Badge tone="green">Clip listo</Badge>;
        case 'failed':
            return <Badge tone="red">Falló el clip</Badge>;
        default:
            return <Badge>Sin clip</Badge>;
    }
}
