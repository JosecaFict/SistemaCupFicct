import { useEffect, useState } from "react";
import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import { Card } from "../../components/ui/Card";
import { Input } from "../../components/ui/Input";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";
import { Spinner } from "../../components/ui/Spinner";
import { StatusBadge } from "../../components/tables/StatusBadge";
import { ComprobantePreview } from "../../components/cards/ComprobantePreview";
import { publicService } from "../../services/publicService";
import type { ComprobantePago, Pago } from "../../types";

/*
 * PagoSimulado (CU7)
 * --------------------------------------------------------------------------
 * Pantalla de pago. Funciona en dos modos segun STRIPE_MODE del backend:
 *
 *  - simulated: pantalla estilo Stripe Test con tarjetas ficticias
 *      4242 4242 4242 4242 -> APROBADO | ...0000 -> RECHAZADO | ...9999 -> CANCELADO
 *
 *  - test/live (Stripe real): al iniciar, el backend devuelve checkout_url y
 *      redirigimos a Stripe. Al volver (success_url/cancel_url) llegamos aqui
 *      con ?estado=exito|cancelado&pago=ID y verificamos el pago contra Stripe.
 */
export function PagoSimulado() {
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  const estadoRetorno = searchParams.get("estado");   // exito | cancelado | null
  const pagoIdRetorno = searchParams.get("pago");

  const [pago, setPago] = useState<Pago | null>(null);
  const [comprobante, setComprobante] = useState<ComprobantePago | null>(null);
  const [tarjetas, setTarjetas] = useState<Record<string, string>>({});
  const [tarjeta, setTarjeta] = useState("4242 4242 4242 4242");
  const [mostrarTarjeta, setMostrarTarjeta] = useState(false); // solo modo simulado
  const [confirmando, setConfirmando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [cargando, setCargando] = useState(true);
  const [redirigiendo, setRedirigiendo] = useState(false);

  useEffect(() => {
    if (!id) return;
    (async () => {
      try {
        // Caso A: volvemos de Stripe (success_url / cancel_url).
        if (estadoRetorno && pagoIdRetorno) {
          if (estadoRetorno === "exito") {
            const { pago: actualizado, comprobante: comp } = await publicService.confirmarPago(Number(pagoIdRetorno), "");
            setPago(actualizado);
            setComprobante(comp);
          }
          // 'cancelado' no necesita llamada: se muestra aviso de cancelacion.
          return;
        }

        // Caso B: inicio del pago.
        const r = await publicService.iniciarPago(Number(id));

        // Modo Stripe: el backend devolvio la URL de la pasarela -> redirigir.
        if (r.checkout_url) {
          setRedirigiendo(true);
          window.location.href = r.checkout_url;
          return;
        }

        // Modo simulado: mostrar formulario de tarjeta ficticia.
        setPago(r.pago);
        setTarjetas(r.tarjetas_de_prueba);
        setMostrarTarjeta(true);
      } catch (e: unknown) {
        const er = e as { response?: { data?: { message?: string } } };
        setError(er?.response?.data?.message ?? "No fue posible iniciar el pago.");
      } finally {
        setCargando(false);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const onConfirmar = async () => {
    if (!pago) return;
    setError(null);
    setConfirmando(true);
    try {
      const { pago: actualizado, comprobante: comp } = await publicService.confirmarPago(pago.id, tarjeta);
      setPago(actualizado);
      setComprobante(comp);
    } catch (e: unknown) {
      const er = e as { response?: { data?: { message?: string } } };
      setError(er?.response?.data?.message ?? "Error al confirmar el pago.");
    } finally {
      setConfirmando(false);
    }
  };

  if (cargando || redirigiendo) {
    return (
      <div className="flex flex-col items-center gap-3 py-10">
        <Spinner />
        {redirigiendo && <div className="text-sm text-muted-500">Redirigiendo a la pasarela de pago...</div>}
      </div>
    );
  }

  // Volvimos cancelados desde Stripe.
  if (estadoRetorno === "cancelado" && !pago) {
    return (
      <div className="max-w-xl mx-auto space-y-4">
        <Alert tone="warning" title="Pago cancelado">
          Cancelaste el pago. Podes volver a intentarlo desde tu formulario.
        </Alert>
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={() => navigate(`/preinscripcion/${id}/formulario`)}>Volver al formulario</Button>
        </div>
      </div>
    );
  }

  if (error && !pago) return <Alert tone="danger">{error}</Alert>;
  if (!pago) return null;

  return (
    <>
    <div className="max-w-xl mx-auto space-y-5 no-print">
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

      {mostrarTarjeta && pago.estado === "PENDIENTE" && (
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
            <Button onClick={onConfirmar} loading={confirmando}>Pagar ahora</Button>
          </div>
        </Card>
      )}

      {pago.estado === "APROBADO" && (
        <>
          <Alert tone="success" title="Pago aprobado">
            Tu pago fue aprobado. Pasa al encargado de inscripcion con tus documentos para completar el proceso.
          </Alert>
          {comprobante && (
            <div className="flex justify-center">
              <Button variant="secondary" onClick={() => window.print()}>Descargar comprobante</Button>
            </div>
          )}
        </>
      )}
      {pago.estado === "RECHAZADO" && (
        <Alert tone="danger" title="Pago rechazado">
          El pago fue rechazado. Puedes intentar nuevamente desde el formulario.
        </Alert>
      )}
      {pago.estado === "CANCELADO" && (
        <Alert tone="warning" title="Pago cancelado">El pago fue cancelado.</Alert>
      )}
      {/* Stripe: volvimos con exito pero el pago no quedo confirmado. */}
      {pago.estado === "PENDIENTE" && !mostrarTarjeta && (
        <Alert tone="warning" title="Pago no confirmado">
          No pudimos confirmar el pago todavia. Si ya pagaste, espera unos segundos y refresca; si no, intenta de nuevo desde el formulario.
        </Alert>
      )}

      {(pago.estado === "APROBADO" || (pago.estado !== "PENDIENTE" && !mostrarTarjeta)) && (
        <div className="flex justify-end">
          <Button variant="success" onClick={() => navigate("/")}>Volver al inicio</Button>
        </div>
      )}
    </div>

    {/* Solo visible al imprimir: el comprobante queda como unica pagina. */}
    {comprobante && (
      <div className="hidden print:block">
        <ComprobantePreview data={comprobante} />
      </div>
    )}
    </>
  );
}
