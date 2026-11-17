import { Badge } from '@/Components/ui';

/** Insignia de existencias: "Agotado" / "Stock bajo". */
export default function StockBadge({ stock, lowStock }) {
    if (stock <= 0) return <Badge tone="red">Agotado</Badge>;
    if (lowStock) return <Badge tone="amber">Stock bajo</Badge>;
    return null;
}
