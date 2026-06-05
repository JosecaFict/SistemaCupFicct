import { useEffect, useState } from "react";
import { Card } from "../../components/ui/Card";
import { Button } from "../../components/ui/Button";
import { Badge } from "../../components/ui/Badge";
import { Alert } from "../../components/ui/Alert";
import { Modal } from "../../components/ui/Modal";
import { Select } from "../../components/ui/Select";
import { Spinner } from "../../components/ui/Spinner";
import { StatCard } from "../../components/cards/StatCard";
import { api } from "../../services/api";
import { calculoService, type EstadoCalculo, type ResumenCalculo } from "../../services/calculoService";

/*
 * CalculoResultadosPage (Ciclo 2 - CU16, CU17)
 * --------------------------------------------------------------------------
 * Para ADMINISTRADOR y COORDINADOR. Permite:
 *   - Ver el estado de la gestion (puede calcularse? cuantas notas faltan?).
 *   - Disparar el calculo (con confirmacion si hay calculo previo).
 *   - Publicar los resultados para que el postulante publico los vea.
 *   - Despublicar (solo ADMIN).
 *
 * Desempate: el algoritmo desempata automaticamente por fecha de
 * preinscripcion (el primero en preinscribirse gana). Es objetivo y sin
 * sesgos.
 */
interface GestionLite { id: number; codigo: string; nombre: string; estado: string }

export function CalculoResultadosPage() {
  const [gestiones, setGestiones] = useState<GestionLite[]>([]);
  const [gestionId, setGestionId] = useState<number | "">("");
  const [estado, setEstado] = useState<EstadoCalculo | null>(null);
  const [resumen, setResumen] = useState<ResumenCalculo | null>(null);
  const [cargando, setCargando] = useState(false);
  const [confirmandoCalculo, setConfirmandoCalculo] = useState(false);
  const [confirmandoPublicar, setConfirmandoPublicar] = useState(false);
  const [confirmandoDespublicar, setConfirmandoDespublicar] = useState(false);
  const [procesando, setProcesando] = useState(false);
  const [mensaje, setMensaje] = useState<{ tone: "success" | "danger" | "warning"; text: string } | null>(null);

  useEffect(() => {
    api.get<GestionLite[]>("/api/dashboard/gestiones").then((r) => {
      setGestiones(r.data);
      if (!gestionId && r.data.length > 0) setGestionId(r.data[0].id);
    });
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (!gestionId) return;
    refreshEstado();
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [gestionId]);

  function refreshEstado() {
    if (!gestionId) return;
    setCargando(true);
    calculoService.estado(gestionId as number)
      .then(setEstado)
      .finally(() => setCargando(false));
  }

  async function calcular() {
    setConfirmandoCalculo(false);
    if (!gestionId) return;
    setProcesando(true);
    setResumen(null);
    setMensaje(null);
    try {
      const r = await calculoService.calcular(gestionId as number);
      setEstado(r.estado);
      setResumen(r.resumen);
      setMensaje({ tone: "success", text: r.mensaje });
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    } catch (e: any) {
      const msg = e?.response?.data?.razon ?? e?.response?.data?.mensaje ?? "Error al calcular.";
      setMensaje({ tone: "danger", text: String(msg) });
    } finally {
      setProcesando(false);
    }
  }

  async function publicar() {
    setConfirmandoPublicar(false);
    if (!gestionId) return;
    setProcesando(true);
    setMensaje(null);
    try {
      const r = await calculoService.publicar(gestionId as number);
      setEstado(r.estado);
      setMensaje({ tone: "success", text: r.mensaje });
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    } catch (e: any) {
      setMensaje({ tone: "danger", text: e?.response?.data?.message ?? "Error al publicar." });
    } finally {
      setProcesando(false);
    }
  }

  async function despublicar() {
    setConfirmandoDespublicar(false);
    if (!gestionId) return;
    setProcesando(true);
    setMensaje(null);
    try {
      const r = await calculoService.despublicar(gestionId as number);
      setMensaje({ tone: "success", text: r.mensaje });
      refreshEstado();
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    } catch (e: any) {
      setMensaje({ tone: "danger", text: e?.response?.data?.message ?? "Error al despublicar." });
    } finally {
      setProcesando(false);
    }
  }

  return (
    <div className="space-y-5">
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-semibold text-institutional-800">Calculo de resultados</h1>
          <p className="text-sm text-muted-500">
            Procesa la gestion y genera los resultados finales (ACEPTADO / REPROBADO / SIN_CUPO).
            Desempate por fecha de preinscripcion.
          </p>
        </div>
        <div className="min-w-[260px]">
          <label className="text-xs text-muted-500">Gestion</label>
          <Select
            value={String(gestionId)}
            onChange={(e) => {
              setGestionId(e.target.value ? Number(e.target.value) : "");
              setResumen(null);
              setMensaje(null);
            }}
          >
            {gestiones.map((g) => (
              <option key={g.id} value={g.id}>{g.codigo} ({g.estado})</option>
            ))}
          </Select>
        </div>
      </div>

      {mensaje && <Alert tone={mensaje.tone}>{mensaje.text}</Alert>}

      {cargando || !estado ? (
        <div className="flex justify-center py-10"><Spinner /></div>
      ) : (
        <>
          <Card title={`Estado de la gestion ${estado.gestion_codigo}`}>
            <div className="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
              <StatCard label="Postulantes" value={estado.postulaciones} />
              <StatCard
                label="Notas cargadas"
                value={`${estado.notas_cargadas}/${estado.notas_esperadas}`}
                accent={estado.notas_cargadas >= estado.notas_esperadas ? "success" : "warning"}
              />
              <StatCard
                label="Notas pendientes"
                value={estado.notas_pendientes}
                accent={estado.notas_pendientes > 0 ? "warning" : "success"}
              />
              <StatCard
                label="Calculo previo"
                value={estado.tiene_calculo_previo ? "Si" : "No"}
                accent={estado.tiene_calculo_previo ? "success" : "institutional"}
                helper={estado.tiene_publicacion ? "Publicado" : "Sin publicar"}
              />
            </div>

            {/* Pre-requisitos */}
            {estado.puede_calcularse ? (
              <Alert tone="success">
                Listo para calcular. Todas las notas estan cargadas y validadas.
              </Alert>
            ) : (
              <Alert tone="warning">
                <b>No puede calcularse todavia:</b> {estado.razon_bloqueo}
              </Alert>
            )}

            <div className="flex gap-2 mt-4 flex-wrap">
              <Button
                onClick={() => estado.tiene_calculo_previo ? setConfirmandoCalculo(true) : calcular()}
                disabled={!estado.puede_calcularse || procesando}
              >
                {procesando ? "Procesando..." : estado.tiene_calculo_previo ? "Recalcular" : "Calcular resultados"}
              </Button>

              <Button
                variant="success"
                onClick={() => setConfirmandoPublicar(true)}
                disabled={!estado.tiene_calculo_previo || procesando || estado.tiene_publicacion}
              >
                {estado.tiene_publicacion ? "Ya publicado" : "Publicar"}
              </Button>

              {estado.tiene_publicacion && (
                <Button
                  variant="danger"
                  onClick={() => setConfirmandoDespublicar(true)}
                  disabled={procesando}
                >
                  Despublicar (admin)
                </Button>
              )}
            </div>
          </Card>

          {resumen && (
            <Card title="Resumen del ultimo calculo">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                <StatCard label="Aceptados" value={resumen.aceptados} accent="success" />
                <StatCard label="Reprobados" value={resumen.reprobados} accent="danger" />
                <StatCard label="Sin cupo" value={resumen.sin_cupo} accent="warning" />
              </div>
              <div className="text-xs text-muted-500">
                Total procesado: {resumen.total} postulantes.
              </div>
            </Card>
          )}

          {estado.tiene_publicacion && (
            <Card title="Publicacion activa">
              <div className="text-sm">
                <Badge tone="success">PUBLICADO</Badge>
                {" "}{estado.resultados_publicados} resultados visibles publicamente.
              </div>
              <p className="text-xs text-muted-500 mt-2">
                Los postulantes pueden consultar su resultado por codigo. Solo los
                ACEPTADOS aparecen en la lista publica con formato "codigo-1ra" o
                "codigo-2da".
              </p>
            </Card>
          )}
        </>
      )}

      {/* Confirmacion: recalcular */}
      <Modal
        open={confirmandoCalculo}
        onClose={() => setConfirmandoCalculo(false)}
        title="Confirmar recalculo"
        footer={
          <>
            <Button variant="secondary" onClick={() => setConfirmandoCalculo(false)}>Cancelar</Button>
            <Button onClick={calcular} disabled={procesando}>Si, recalcular</Button>
          </>
        }
      >
        <div className="text-sm space-y-2">
          <p>
            Ya existe un calculo previo en esta gestion. Si continuas:
          </p>
          <ul className="list-disc list-inside text-xs">
            <li>Se borraran los resultados actuales.</li>
            <li>Se generaran resultados nuevos con las notas actuales.</li>
            <li>Si los resultados estaban publicados, se despublicaran.</li>
          </ul>
          <p className="text-warning-600 text-xs">
            Esta accion queda registrada en bitacora.
          </p>
        </div>
      </Modal>

      {/* Confirmacion: publicar */}
      <Modal
        open={confirmandoPublicar}
        onClose={() => setConfirmandoPublicar(false)}
        title="Confirmar publicacion"
        footer={
          <>
            <Button variant="secondary" onClick={() => setConfirmandoPublicar(false)}>Cancelar</Button>
            <Button variant="success" onClick={publicar} disabled={procesando}>Si, publicar</Button>
          </>
        }
      >
        <div className="text-sm">
          Despues de publicar, los postulantes podran consultar su resultado por codigo.
          Solo los <b>ACEPTADOS</b> apareceran en la lista publica.
        </div>
      </Modal>

      {/* Confirmacion: despublicar */}
      <Modal
        open={confirmandoDespublicar}
        onClose={() => setConfirmandoDespublicar(false)}
        title="Confirmar despublicacion"
        footer={
          <>
            <Button variant="secondary" onClick={() => setConfirmandoDespublicar(false)}>Cancelar</Button>
            <Button variant="danger" onClick={despublicar} disabled={procesando}>Si, despublicar</Button>
          </>
        }
      >
        <div className="text-sm">
          Los resultados dejaran de ser visibles publicamente. Esta accion es
          reversible: podes volver a publicar despues de corregir lo necesario.
        </div>
      </Modal>
    </div>
  );
}
