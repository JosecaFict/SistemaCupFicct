import { useState } from "react";
import { Card } from "../../components/ui/Card";
import { Input } from "../../components/ui/Input";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";
import { StatusBadge } from "../../components/tables/StatusBadge";
import { publicService } from "../../services/publicService";

/*
 * ResultadosPublicos
 * --------------------------------------------------------------------------
 * Consulta publica por CODIGO DE POSTULANTE (no por CI).
 * En Ciclo 1 solo se muestra el estado de la postulacion. En Ciclos siguientes
 * se sumara nota por materia, ranking y resultado final (ACEPTADO/SIN_CUPO).
 */
export function ResultadosPublicos() {
  const [codigo, setCodigo] = useState("");
  const [cargando, setCargando] = useState(false);
  const [resultado, setResultado] = useState<Record<string, unknown> | null>(null);
  const [error, setError] = useState<string | null>(null);

  const onBuscar = async () => {
    setError(null);
    setResultado(null);
    if (!codigo.trim()) return;
    setCargando(true);
    try {
      const r = await publicService.consultarResultado(codigo.trim());
      setResultado(r);
    } catch (e: unknown) {
      const er = e as { response?: { data?: { message?: string } } };
      setError(er?.response?.data?.message ?? "No se encontro el codigo.");
    } finally {
      setCargando(false);
    }
  };

  return (
    <div className="max-w-xl mx-auto space-y-5">
      <div>
        <h2 className="text-2xl font-semibold text-institutional-800">Consulta de resultados CUP</h2>
        <p className="text-sm text-muted-500">Ingresa tu codigo de postulante (lo recibes al confirmar inscripcion).</p>
      </div>

      <Card>
        <div className="flex gap-2 items-end">
          <Input label="Codigo de postulante"
                 value={codigo}
                 onChange={(e) => setCodigo(e.target.value)}
                 placeholder="0000001"
                 className="flex-1" />
          <Button onClick={onBuscar} loading={cargando}>Buscar</Button>
        </div>
      </Card>

      {error && <Alert tone="warning">{error}</Alert>}

      {resultado && (
        <Card title="Resultado">
          <div className="space-y-2 text-sm">
            <div><b>Codigo:</b> {String(resultado.codigo)}</div>
            <div><b>Nombre:</b> {(resultado.persona as { nombre_completo?: string })?.nombre_completo}</div>
            <div><b>Gestion:</b> {String(resultado.gestion ?? "-")}</div>

            {(() => {
              const estadoFinal = resultado.estado_final as string | null | undefined;
              const publicado   = resultado.publicado === true;
              const carreraAsignada = resultado.carrera_asignada as string | null | undefined;
              const carreraSolicitada = resultado.carrera as string | null | undefined;
              const opcion = resultado.opcion_aceptada as string | null | undefined;

              // 1) Aceptado y publicado -> mostrar carrera asignada + opcion.
              if (publicado && estadoFinal === "ACEPTADO" && carreraAsignada) {
                const opcionTxt =
                  opcion === "PRIMERA" ? " (1ra opcion)" :
                  opcion === "SEGUNDA" ? " (2da opcion)" : "";
                return <div><b>Carrera asignada:</b> {carreraAsignada}{opcionTxt}</div>;
              }
              // 2) Sin cupo y publicado -> mostrar la primera opcion que solicito.
              if (publicado && estadoFinal === "SIN_CUPO") {
                return <div><b>Carrera (1ra opcion solicitada):</b> {carreraSolicitada ?? "-"}</div>;
              }
              // 3) Aun no calculado / no publicado: mostrar lo que pidio.
              return <div><b>Carrera (1ra opcion):</b> {carreraSolicitada ?? "-"}</div>;
            })()}

            <div className="flex items-center gap-2">
              <b>Estado:</b>{" "}
              {resultado.publicado === true && resultado.estado_final
                ? <StatusBadge estado={String(resultado.estado_final)} tipo="postulacion" />
                : <StatusBadge estado={String(resultado.estado)} tipo="postulacion" />}
            </div>

            {!(resultado.publicado === true && resultado.estado_final) && (
              <div className="text-xs text-muted-500 pt-2 border-t border-muted-100">
                Cuando la gestion publique los resultados finales (ACEPTADO / SIN_CUPO),
                aparecera aqui automaticamente.
              </div>
            )}
          </div>
        </Card>
      )}
    </div>
  );
}
