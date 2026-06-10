import { useEffect, useState, type ChangeEvent } from "react";
import { useParams, useNavigate, Link } from "react-router-dom";
import { Card } from "../../components/ui/Card";
import { Button } from "../../components/ui/Button";
import { Badge } from "../../components/ui/Badge";
import { Alert } from "../../components/ui/Alert";
import { Spinner } from "../../components/ui/Spinner";
import { notasService, type NotaFila, type NotasExamenResponse } from "../../services/notasService";

/*
 * CargaNotasPage (Ciclo 2 - CU14)
 * --------------------------------------------------------------------------
 * Pantalla donde el docente carga las notas de todos los postulantes de un
 * grupo, para una materia y un examen especifico.
 *
 * Reglas:
 *   - Notas en VALIDADA no se pueden modificar (mostradas en gris).
 *   - Al guardar, todas las modificaciones quedan en PENDIENTE.
 *   - El campo nota acepta 0-100 (HTML5 input number).
 *   - Si el valor < nota_minima_aprobacion, el sistema marca descalifica=true
 *     automaticamente en el backend.
 */
export function CargaNotasPage() {
  const { grupoId, gmId, examen } = useParams();
  const navigate = useNavigate();

  const [data, setData] = useState<NotasExamenResponse | null>(null);
  const [filas, setFilas] = useState<NotaFila[]>([]);
  const [valores, setValores] = useState<Record<number, string>>({});
  const [guardando, setGuardando] = useState(false);
  const [importando, setImportando] = useState(false);
  const [mensaje, setMensaje] = useState<{ tone: "success" | "danger"; text: string } | null>(null);

  function recargar() {
    notasService.notasDelExamen(Number(grupoId), Number(gmId), Number(examen)).then((d) => {
      setData(d);
      setFilas(d.postulantes);
      const init: Record<number, string> = {};
      d.postulantes.forEach((p) => { init[p.postulacion_id] = p.valor !== null ? String(p.valor) : ""; });
      setValores(init);
    });
  }

  async function descargarPlantilla() {
    if (!grupoId || !gmId || !examen) return;
    const blob = await notasService.descargarPlantilla(Number(grupoId), Number(gmId), Number(examen));
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `plantilla-notas-ex${examen}.xlsx`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
  }

  async function importarExcel(e: ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    e.target.value = ""; // permite resubir el mismo archivo
    if (!file || !grupoId || !gmId || !examen) return;
    setMensaje(null);
    setImportando(true);
    try {
      const res = await notasService.importarNotas({
        grupo_id: Number(grupoId),
        gestion_materia_id: Number(gmId),
        numero_examen: Number(examen),
        archivo: file,
      });
      setMensaje({
        tone: "success",
        text: `${res.mensaje} Insertadas: ${res.insertados}, actualizadas: ${res.actualizados}` +
          (res.ignoradas ? `, ignoradas: ${res.ignoradas}` : "") + ".",
      });
      recargar();
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
    } catch (err: any) {
      setMensaje({ tone: "danger", text: String(err?.response?.data?.message ?? "No se pudo importar el Excel.") });
    } finally {
      setImportando(false);
    }
  }

  useEffect(() => {
    if (!grupoId || !gmId || !examen) return;
    notasService.notasDelExamen(Number(grupoId), Number(gmId), Number(examen))
      .then((d) => {
        setData(d);
        setFilas(d.postulantes);
        const init: Record<number, string> = {};
        d.postulantes.forEach((p) => {
          init[p.postulacion_id] = p.valor !== null ? String(p.valor) : "";
        });
        setValores(init);
      });
  }, [grupoId, gmId, examen]);

  if (!data) return <div className="flex justify-center py-10"><Spinner /></div>;

  const notaMinima = parseFloat(String(data.asignacion.gestion?.nota_minima_aprobacion ?? "60"));
  const bloqueado = data.bloqueado === true;

  function setValor(postId: number, v: string) {
    setValores((prev) => ({ ...prev, [postId]: v }));
  }

  function isReadonly(f: NotaFila): boolean {
    return bloqueado || f.estado === "VALIDADA";
  }

  async function guardar() {
    setMensaje(null);
    const notas: Array<{ postulacion_id: number; valor: number }> = [];
    for (const f of filas) {
      if (isReadonly(f)) continue;
      const v = valores[f.postulacion_id];
      if (v === "" || v === undefined) continue;
      const num = parseFloat(v);
      if (Number.isNaN(num) || num < 0 || num > 100) {
        setMensaje({ tone: "danger", text: `Nota invalida para ${f.nombre_completo} (debe estar entre 0 y 100).` });
        return;
      }
      notas.push({ postulacion_id: f.postulacion_id, valor: num });
    }

    if (notas.length === 0) {
      setMensaje({ tone: "danger", text: "No hay notas para guardar." });
      return;
    }

    setGuardando(true);
    try {
      const res = await notasService.guardarNotas({
        grupo_id: Number(grupoId),
        gestion_materia_id: Number(gmId),
        numero_examen: Number(examen),
        notas,
      });
      setMensaje({
        tone: "success",
        text: `${res.mensaje} Insertadas: ${res.insertados}, actualizadas: ${res.actualizados}.`,
      });
      // Recargar
      notasService.notasDelExamen(Number(grupoId), Number(gmId), Number(examen)).then((d) => {
        setData(d);
        setFilas(d.postulantes);
      });
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
    } catch (e: any) {
      const msg = e?.response?.data?.message ?? "Error al guardar.";
      setMensaje({ tone: "danger", text: String(msg) });
    } finally {
      setGuardando(false);
    }
  }

  const totales = {
    total: filas.length,
    cargadas: filas.filter((f) => f.valor !== null).length,
    validadas: filas.filter((f) => f.estado === "VALIDADA").length,
    pendientes: filas.filter((f) => f.estado === "PENDIENTE").length,
    rechazadas: filas.filter((f) => f.estado === "RECHAZADA").length,
  };

  return (
    <div className="space-y-5">
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-semibold text-institutional-800">
            Carga de notas  Examen {data.examen}
          </h1>
          <p className="text-sm text-muted-500">
            <span className="font-mono">{data.asignacion.grupo?.codigo}</span>
            {" "} {data.asignacion.gestion_materia?.materia?.nombre}
            {" "}({data.asignacion.gestion?.codigo})
          </p>
        </div>
        <Link to="/docente">
          <Button variant="secondary" size="sm"> Volver a mis asignaciones</Button>
        </Link>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
        <div className="border border-muted-100 bg-white rounded p-2 text-center">
          <div className="text-xs text-muted-500">Total</div>
          <div className="font-semibold">{totales.total}</div>
        </div>
        <div className="border border-muted-100 bg-white rounded p-2 text-center">
          <div className="text-xs text-muted-500">Cargadas</div>
          <div className="font-semibold">{totales.cargadas}</div>
        </div>
        <div className="border border-warning-200 bg-orange-50 rounded p-2 text-center">
          <div className="text-xs text-warning-600">Pendientes</div>
          <div className="font-semibold text-warning-600">{totales.pendientes}</div>
        </div>
        <div className="border border-success-200 bg-success-50 rounded p-2 text-center">
          <div className="text-xs text-success-700">Validadas</div>
          <div className="font-semibold text-success-700">{totales.validadas}</div>
        </div>
        <div className="border border-red-200 bg-red-50 rounded p-2 text-center">
          <div className="text-xs text-danger-600">Rechazadas</div>
          <div className="font-semibold text-danger-600">{totales.rechazadas}</div>
        </div>
      </div>

      {mensaje && <Alert tone={mensaje.tone}>{mensaje.text}</Alert>}

      {bloqueado && (
        <Alert tone="warning">
          <b>Carga deshabilitada.</b>{" "}
          {data.motivo_bloqueo ?? "La carga de notas aun no esta habilitada para este examen."}
          {" "}Podes consultar las notas ya cargadas, pero no editarlas hasta la fecha del examen.
        </Alert>
      )}

      <Card>
        <div className="flex flex-wrap items-center gap-3">
          <div className="text-sm text-muted-600">
            <b>Plantilla Excel:</b> descarga la plantilla con tus alumnos, completa la columna <i>Nota</i> y subila para cargar en bloque.
          </div>
          <div className="flex gap-2 sm:ml-auto">
            <Button variant="secondary" size="sm" onClick={descargarPlantilla} disabled={bloqueado}>Descargar plantilla</Button>
            <label className={`inline-flex items-center px-3 py-1.5 text-sm rounded-md border border-muted-200 ${
              bloqueado || importando ? "opacity-60 pointer-events-none" : "cursor-pointer hover:bg-muted-100"
            }`}>
              {importando ? "Importando..." : "Subir Excel"}
              <input type="file" accept=".xlsx" className="hidden" onChange={importarExcel} disabled={importando || bloqueado} />
            </label>
          </div>
        </div>
      </Card>

      <Card>
        <div className="text-xs text-muted-500 mb-2">
          Nota minima para no descalificar: <b>{notaMinima}</b>.
          Las notas guardadas quedan en PENDIENTE de validacion por el coordinador.
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-muted-50 text-muted-700">
              <tr>
                <th className="px-2 py-2 text-left">N</th>
                <th className="px-2 py-2 text-left">Cdigo</th>
                <th className="px-2 py-2 text-left">Nombre completo</th>
                <th className="px-2 py-2 text-right">Nota (0-100)</th>
                <th className="px-2 py-2 text-center">Estado</th>
              </tr>
            </thead>
            <tbody>
              {filas.map((f) => {
                const readonly = isReadonly(f);
                const v = valores[f.postulacion_id] ?? "";
                const num = parseFloat(v);
                const esBajo = !Number.isNaN(num) && num < notaMinima;
                return (
                  <tr key={f.postulacion_id} className="border-t border-muted-100">
                    <td className="px-2 py-1 text-muted-500">{f.numero}</td>
                    <td className="px-2 py-1 font-mono">{f.codigo_postulante ?? ""}</td>
                    <td className="px-2 py-1">{f.nombre_completo}</td>
                    <td className="px-2 py-1 text-right">
                      <input
                        type="number"
                        min={0}
                        max={100}
                        step={0.01}
                        value={v}
                        onChange={(e) => setValor(f.postulacion_id, e.target.value)}
                        readOnly={readonly}
                        className={`w-24 text-right border rounded px-2 py-1 ${
                          readonly ? "bg-muted-50 text-muted-500" :
                          esBajo ? "border-danger-300 bg-red-50" :
                          "border-muted-200"
                        }`}
                      />
                    </td>
                    <td className="px-2 py-1 text-center">
                      {f.estado === "VALIDADA" && <Badge tone="success">Validada</Badge>}
                      {f.estado === "PENDIENTE" && <Badge tone="warning">Pendiente</Badge>}
                      {f.estado === "RECHAZADA" && <Badge tone="danger">Rechazada</Badge>}
                      {!f.estado && <span className="text-muted-500"></span>}
                      {f.descalifica && (
                        <div className="text-xs text-danger-600 mt-1">descalifica</div>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <div className="flex justify-end mt-4 gap-2">
          <Button variant="secondary" onClick={() => navigate("/docente")}>Cancelar</Button>
          <Button onClick={guardar} disabled={guardando || bloqueado}>
            {guardando ? "Guardando..." : bloqueado ? "Bloqueado" : "Guardar notas"}
          </Button>
        </div>
      </Card>
    </div>
  );
}
