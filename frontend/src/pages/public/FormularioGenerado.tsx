import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { Card } from "../../components/ui/Card";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";
import { Spinner } from "../../components/ui/Spinner";
import { StatusBadge } from "../../components/tables/StatusBadge";
import { publicService } from "../../services/publicService";
import type { Postulacion } from "../../types";

/*
 * FormularioGenerado (CU6)
 * --------------------------------------------------------------------------
 * Despues de preinscribir, esta pantalla:
 *  - Genera el formulario (cambia estado a FORMULARIO_GENERADO)
 *  - Lo muestra en pantalla listo para imprimir (window.print)
 *  - Habilita el boton para ir al pago
 */
export function FormularioGenerado() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [postulacion, setPostulacion] = useState<Postulacion | null>(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!id) return;
    const pid = Number(id);
    (async () => {
      try {
        const { postulacion } = await publicService.generarFormulario(pid);
        setPostulacion(postulacion);
      } catch (e: unknown) {
        const er = e as { response?: { data?: { message?: string } } };
        setError(er?.response?.data?.message ?? "No fue posible generar el formulario.");
      } finally {
        setCargando(false);
      }
    })();
  }, [id]);

  if (cargando) return <div className="flex justify-center py-10"><Spinner /></div>;
  if (error)    return <Alert tone="danger">{error}</Alert>;
  if (!postulacion) return null;

  const p = postulacion;
  return (
    <div className="max-w-3xl mx-auto space-y-6">
      <div className="no-print flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-institutional-800">Formulario generado</h2>
          <p className="text-sm text-muted-500">Imprime o descarga este formulario. Luego continua con el pago.</p>
        </div>
        <StatusBadge estado={p.estado} tipo="postulacion" />
      </div>

      <Card className="print-page">
        <div className="text-center border-b border-muted-200 pb-3 mb-4">
          <div className="text-xs uppercase tracking-widest text-muted-500">FICCT</div>
          <div className="text-lg font-bold text-institutional-800">Formulario de Preinscripcion CUP</div>
          <div className="text-xs text-muted-500">Gestion {p.gestion?.codigo} - {p.gestion?.nombre}</div>
        </div>

        <div className="grid grid-cols-2 gap-3 text-sm">
          <Item label="Nombres" value={p.persona?.nombre ?? ""} />
          <Item label="Apellidos" value={`${p.persona?.apellido_paterno ?? ""} ${p.persona?.apellido_materno ?? ""}`} />
          <Item label="Documento" value={`${p.persona?.documento}${p.persona?.expedido ? " " + p.persona?.expedido : ""} (${p.persona?.tipo_documento})`} />
          <Item label="Fecha nacimiento" value={p.persona?.fecha_nacimiento ?? "-"} />
          <Item label="Sexo" value={p.persona?.sexo ?? "-"} />
          <Item label="Correo" value={p.persona?.email ?? "-"} />
          <Item label="Telefono" value={p.persona?.telefono ?? "-"} />
          <Item label="Direccion" value={p.persona?.direccion ?? "-"} />
          <Item label="Primera opcion" value={p.carrera_primera?.nombre ?? ""} />
          <Item label="Segunda opcion" value={p.carrera_segunda?.nombre ?? "-"} />
        </div>
      </Card>

      <div className="flex flex-wrap justify-end gap-2 no-print">
        <Button variant="secondary" onClick={() => window.print()}>Imprimir formulario</Button>
        <Button onClick={() => navigate(`/preinscripcion/${p.id}/pago`)}>Continuar al pago</Button>
      </div>
    </div>
  );
}

function Item({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-[11px] uppercase text-muted-500">{label}</div>
      <div className="font-medium text-muted-700">{value}</div>
    </div>
  );
}
