import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { Card } from "../../components/ui/Card";
import { Input } from "../../components/ui/Input";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";
import { Spinner } from "../../components/ui/Spinner";
import { StatusBadge } from "../../components/tables/StatusBadge";
import { publicService } from "../../services/publicService";
import type { Pago } from "../../types";

/*
 * PagoSimulado (CU7)
 * --------------------------------------------------------------------------
 * Pantalla estilo Stripe Test Mode. Inicia un Pago en estado PENDIENTE y
 * permite al postulante "pagar" con tarjetas simuladas:
 *   4242 4242 4242 4242  -> APROBADO
 *   XXXX XXXX XXXX 0000  -> RECHAZADO
 *   XXXX XXXX XXXX 9999  -> CANCELADO
 *
 * Cuando Stripe Test Mode real este activo, esta pantalla se reemplaza por
 * Stripe Elements (el flujo del backend ya esta listo para eso).
 */
export function PagoSimulado() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [pago, setPago] = useState<Pago | null>(null);
  const [tarjetas, setTarjetas] = useState<Record<string, string>>({});
  const [tarjeta, setTarjeta] = useState("4242 4242 4242 4242");
  const [confirmando, setConfirmando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [iniciando, setIniciando] = useState(true);

  useEffect(() => {
    if (!id) return;
    (async () => {
      try {
        const r = await publicService.iniciarPago(Number(id));
        setPago(r.pago);
        setTarjetas(r.tarjetas_de_prueba);
      } catch (e: unknown) {
        const er = e as { response?: { data?: { message?: string } } };
        setError(er?.response?.data?.message ?? "No fue posible iniciar el pago.");
      } finally {
        setIniciando(false);
      }
    })();
  }, [id]);

  const onConfirmar = async () => {
    if (!pago) return;
    setError(null);
    setConfirmando(true);
    try {
      const { pago: actualizado } = await publicService.confirmarPago(pago.id, tarjeta);
      setPago(actualizado);
    } catch (e: unknown) {
      const er = e as { response?: { data?: { message?: string } } };
      setError(er?.response?.data?.message ?? "Error al confirmar el pago.");
    } finally {
      setConfirmando(false);
    }
  };

  if (iniciando) return <div className="flex justify-center py-10"><Spinner /></div>;
  if (error && !pago) return <Alert tone="danger">{error}</Alert>;
  if (!pago) return null;

  return (
    <div className="max-w-xl mx-auto space-y-5">
      <div className="flex items-center justify-between">
        <h2 className="text-2xl font-semibold text-institutional-800">Pago de inscripcion</h2>
        <StatusBadge estado={pago.estado} tipo="pago" />
      </div>

      <Card title="Datos del pago" action={<span className="text-xs text-muted-500">Modo {pago.modo}</span>}>
        <div className="grid grid-cols-2 gap-3 text-sm">
          <div><div className="text-[11px] uppercase text-muted-500">Monto</div><div className="font-medium">{pago.monto} {pago.moneda}</div></div>
          <div><div className="text-[11px] uppercase text-muted-500">Referencia</div><div className="font-mono text-xs">{pago.referencia}</div></div>
        </div>
      </Card>

      <Card title="Tarjeta simulada (Stripe Test)">
        <Input label="Numero de tarjeta"
               value={tarjeta}
               onChange={(e) => setTarjeta(e.target.value)}
               hint="Usa una de las tarjetas de prueba para simular distintos resultados." />
        <div className="mt-3 text-xs text-muted-500 space-y-0.5">
          {Object.entries(tarjetas).map(([k, v]) => (
            <div key={k}><b>{k}:</b> {v}</div>
          ))}
        </div>
        {error && <div className="mt-3"><Alert tone="danger">{error}</Alert></div>}

        <div className="mt-4 flex justify-between items-center">
          <Button variant="ghost" onClick={() => navigate(`/preinscripcion/${id}/formulario`)}>Atras</Button>
          {pago.estado === "PENDIENTE" ? (
            <Button onClick={onConfirmar} loading={confirmando}>Pagar ahora</Button>
          ) : (
            <Button variant="success" onClick={() => navigate("/")}>Volver al inicio</Button>
          )}
        </div>
      </Card>

      {pago.estado === "APROBADO" && (
        <Alert tone="success" title="Pago aprobado">
          Tu pago fue aprobado. Pasa al encargado de inscripcion con tus documentos para completar el proceso.
        </Alert>
      )}
      {pago.estado === "RECHAZADO" && (
        <Alert tone="danger" title="Pago rechazado">
          El pago fue rechazado. Puedes intentar nuevamente desde el formulario.
        </Alert>
      )}
      {pago.estado === "CANCELADO" && (
        <Alert tone="warning" title="Pago cancelado">El pago fue cancelado.</Alert>
      )}
    </div>
  );
}
