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
            <div><b>Carrera:</b> {String(resultado.carrera ?? "-")}</div>
            <div><b>Turno:</b> {String(resultado.turno ?? "-")}</div>
            <div><b>Grupo:</b> {String(resultado.grupo ?? "-")}</div>
            <div className="flex items-center gap-2">
              <b>Estado:</b> <StatusBadge estado={String(resultado.estado)} tipo="postulacion" />
            </div>
            <div className="text-xs text-muted-500 pt-2 border-t border-muted-100">
              El resultado final del examen CUP (ACEPTADO / SIN_CUPO) estara disponible en una siguiente etapa del sistema.
            </div>
          </div>
        </Card>
      )}
    </div>
  );
}
