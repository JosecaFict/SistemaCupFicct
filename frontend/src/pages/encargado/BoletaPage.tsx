import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { Spinner } from "../../components/ui/Spinner";
import { Button } from "../../components/ui/Button";
import { BoletaPreview } from "../../components/cards/BoletaPreview";
import { encargadoService } from "../../services/encargadoService";
import type { BoletaPayload } from "../../types";

/*
 * BoletaPage (CU10) -- pinta la boleta lista para imprimir con window.print().
 */
export function BoletaPage() {
  const { id } = useParams();
  const [b, setB] = useState<BoletaPayload | null>(null);

  useEffect(() => {
    if (!id) return;
    encargadoService.boleta(Number(id)).then(setB);
  }, [id]);

  if (!b) return <div className="flex justify-center py-10"><Spinner /></div>;

  return (
    <div className="space-y-4">
      <div className="no-print flex justify-end gap-2">
        <Button variant="secondary" onClick={() => window.history.back()}>Volver</Button>
        <Button onClick={() => window.print()}>Imprimir boleta</Button>
      </div>
      <BoletaPreview data={b} />
    </div>
  );
}
