import { Badge } from '@/Components/ui';

const TONES = { active: 'green', expiring: 'amber', expired: 'red', none: 'slate' };
const LABELS = { active: 'Activa', expiring: 'Por vencer', expired: 'Vencida', none: 'Sin membresía' };

/** Estado de la membresía: active | expiring | expired | none */
export default function StatusBadge({ status, label }) {
    return <Badge tone={TONES[status] ?? 'slate'}>{label ?? LABELS[status] ?? status}</Badge>;
}
