import { useEffect, useMemo, useState } from "react";
import { Card } from "../../components/ui/Card";
import { Input } from "../../components/ui/Input";
import { Select } from "../../components/ui/Select";
import { Badge } from "../../components/ui/Badge";
import { Button } from "../../components/ui/Button";
import { DataTable } from "../../components/tables/DataTable";
import { StatCard } from "../../components/cards/StatCard";
import { resultadosService } from "../../services/resultadosService";
import { publicService } from "../../services/publicService";
import type {
  Carrera,
  EstadoResultado,
  FiltrosResultados,
  GestionCup,
  Paginated,
  Resultado,
  ResultadoKPIs,
} from "../../types";

/*
 * ResultadosPage (Ciclo 2)
 * --------------------------------------------------------------------------
 * Vista de resultados del CUP para ADMINISTRADOR y COORDINADOR.
 * Permite filtrar por gestion, carrera, estado y opcion, ademas de buscar
 * por codigo o nombre. Muestra KPIs y tabla paginada.
 */
export function ResultadosPage() {
  const [filtros, setFiltros] = useState<FiltrosResultados>({
    estado: "ACEPTADO",
  });
  const [pagina, setPagina] = useState(1);
  const [data, setData] = useState<Paginated<Resultado> | null>(null);
  const [kpis, setKpis] = useState<ResultadoKPIs | null>(null);
  const [gestiones, setGestiones] = useState<GestionCup[]>([]);
  const [carreras, setCarreras] = useState<Carrera[]>([]);
  const [cargando, setCargando] = useState(false);
  const [modo, setModo] = useState<"detalle" | "compacto">("detalle");

  // Cargar catalogos (gestiones + carreras) una sola vez
  useEffect(() => {
    Promise.all([
      resultadosService.gestiones(),
      publicService.carreras(),
    ]).then(([g, c]) => {
      setGestiones(g);
      setCarreras(c);
    });
  }, []);

  // Recargar lista + KPIs cuando cambien filtros, pagina o modo
  useEffect(() => {
    setCargando(true);
    // Modo compacto: pide hasta 200 items y solo ACEPTADOS publicados
    const filtrosEfectivos: FiltrosResultados =
      modo === "compacto"
        ? { ...filtros, estado: "ACEPTADO" }
        : filtros;
    const perPage = modo === "compacto" ? 200 : 20;

    Promise.all([
      resultadosService.lista(filtrosEfectivos, pagina, perPage),
      resultadosService.kpis(filtros),
    ])
      .then(([lista, k]) => {
        setData(lista);
        setKpis(k);
      })
      .finally(() => setCargando(false));
  }, [filtros, pagina, modo]);

  function setFiltro<K extends keyof FiltrosResultados>(k: K, v: FiltrosResultados[K]) {
    setFiltros((f) => ({ ...f, [k]: v }));
    setPagina(1);
  }

  function limpiarFiltros() {
    setFiltros({ estado: "ACEPTADO" });
    setPagina(1);
  }

  const fmtNombre = (r: Resultado) => {
    const p = r.postulacion?.persona;
    return p
      ? [p.nombre, p.apellido_paterno, p.apellido_materno].filter(Boolean).join(" ")
      : "-";
  };

  const fmtCodigoPublico = (r: Resultado) => {
    const c = r.postulacion?.codigo_postulante;
    if (!c) return "-";
    if (r.estado_final !== "ACEPTADO") return c;
    const sufijo = r.opcion_aceptada === "PRIMERA" ? "1ra"
                : r.opcion_aceptada === "SEGUNDA" ? "2da"
                : "";
    return sufijo ? `${c}-${sufijo}` : c;
  };

  const tonoEstado: Record<EstadoResultado, "success" | "neutral" | "warning" | "danger"> = {
    ACEPTADO: "success",
    REPROBADO: "danger",
    SIN_CUPO: "warning",
    PENDIENTE_DESEMPATE: "warning",
  };

  const totalCarreras = useMemo(
    () => kpis?.por_carrera.reduce((acc, c) => acc + c.cantidad, 0) ?? 0,
    [kpis],
  );

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-institutional-800">Resultados CUP</h1>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-2 md:grid-cols-6 gap-3">
        <StatCard label="Total" value={kpis?.totales.total ?? "-"} />
        <StatCard label="Aceptados" value={kpis?.totales.aceptados ?? "-"} accent="success" />
        <StatCard label="1ra opcion" value={kpis?.totales.primera_opcion ?? "-"} accent="success" helper="Aceptados en su 1ra carrera" />
        <StatCard label="2da opcion" value={kpis?.totales.segunda_opcion ?? "-"} accent="success" helper="Aceptados en su 2da carrera" />
        <StatCard label="Reprobados" value={kpis?.totales.reprobados ?? "-"} accent="danger" helper="No alcanzaron nota minima" />
        <StatCard label="Sin cupo" value={kpis?.totales.sin_cupo ?? "-"} accent="warning" helper="Aprobaron pero sin cupo" />
      </div>

      {/* Aceptados por carrera */}
      {kpis && kpis.por_carrera.length > 0 && (
        <Card>
          <div className="mb-3 text-sm font-medium text-institutional-800">
            Aceptados por carrera
          </div>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {kpis.por_carrera.map((c) => {
              const pct = totalCarreras > 0 ? Math.round((c.cantidad / totalCarreras) * 100) : 0;
              return (
                <div key={c.carrera_id} className="border border-muted-100 rounded-md p-3 bg-white">
                  <div className="text-xs uppercase tracking-wide text-muted-500">{c.carrera_codigo}</div>
                  <div className="text-xl font-semibold text-institutional-700">{c.cantidad}</div>
                  <div className="text-xs text-muted-500">{c.carrera_nombre}</div>
                  <div className="text-xs text-success-600 mt-1">{pct}% del total</div>
                </div>
              );
            })}
          </div>
        </Card>
      )}

      {/* Filtros */}
      <Card>
        <div className="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
          <div>
            <label className="text-xs text-muted-500">Gestion</label>
            <Select
              value={String(filtros.gestion_id ?? "")}
              onChange={(e) => setFiltro("gestion_id", e.target.value ? Number(e.target.value) : "")}
            >
              <option value="">Todas</option>
              {gestiones.map((g) => (
                <option key={g.id} value={g.id}>{g.codigo}  {g.nombre}</option>
              ))}
            </Select>
          </div>
          <div>
            <label className="text-xs text-muted-500">Carrera asignada</label>
            <Select
              value={String(filtros.carrera_id ?? "")}
              onChange={(e) => setFiltro("carrera_id", e.target.value ? Number(e.target.value) : "")}
            >
              <option value="">Todas</option>
              {carreras.map((c) => (
                <option key={c.id} value={c.id}>{c.codigo}  {c.nombre}</option>
              ))}
            </Select>
          </div>
          <div>
            <label className="text-xs text-muted-500">Estado</label>
            <Select
              value={filtros.estado ?? ""}
              onChange={(e) => setFiltro("estado", e.target.value as FiltrosResultados["estado"])}
            >
              <option value="TODOS">Todos</option>
              <option value="ACEPTADO">Aceptados</option>
              <option value="REPROBADO">Reprobados</option>
              <option value="SIN_CUPO">Sin cupo</option>
              <option value="PENDIENTE_DESEMPATE">Pendiente desempate</option>
            </Select>
          </div>
          <div>
            <label className="text-xs text-muted-500">Opcion</label>
            <Select
              value={filtros.opcion ?? ""}
              onChange={(e) => setFiltro("opcion", e.target.value as FiltrosResultados["opcion"])}
            >
              <option value="">Cualquiera</option>
              <option value="PRIMERA">1ra opcion</option>
              <option value="SEGUNDA">2da opcion</option>
            </Select>
          </div>
          <div>
            <label className="text-xs text-muted-500">Buscar</label>
            <Input
              value={filtros.q ?? ""}
              placeholder="Codigo, nombre, CI"
              onChange={(e) => setFiltro("q", e.target.value)}
            />
          </div>
        </div>
        <div className="flex justify-end">
          <Button variant="secondary" size="sm" onClick={limpiarFiltros}>Limpiar filtros</Button>
        </div>
      </Card>

      {/* Tabla / Vista compacta */}
      <Card>
        <div className="flex items-center justify-between mb-3">
          <div className="text-sm text-muted-500">
            {modo === "detalle"
              ? "Vista detallada (todos los campos)."
              : "Vista compacta: solo cdigos publicados de los aceptados (modo lista oficial)."}
          </div>
          <div className="inline-flex rounded-md border border-muted-200 overflow-hidden">
            <button
              type="button"
              onClick={() => { setModo("detalle"); setPagina(1); }}
              className={`px-3 py-1.5 text-sm ${modo === "detalle" ? "bg-institutional-700 text-white" : "bg-white text-muted-700 hover:bg-muted-100"}`}
            >
              Detalle
            </button>
            <button
              type="button"
              onClick={() => { setModo("compacto"); setPagina(1); }}
              className={`px-3 py-1.5 text-sm border-l border-muted-200 ${modo === "compacto" ? "bg-institutional-700 text-white" : "bg-white text-muted-700 hover:bg-muted-100"}`}
            >
              Compacto (cdigos)
            </button>
          </div>
        </div>

        {modo === "compacto" ? (
          <ListaCompacta data={data} cargando={cargando} />
        ) : (
        <DataTable
          rows={data?.data ?? []}
          empty={cargando ? "Cargando..." : "Sin resultados con esos filtros."}
          columns={[
            { header: "Codigo", cell: (r: Resultado) => (
                <span className="font-mono text-institutional-700">{fmtCodigoPublico(r)}</span>
              ) },
            { header: "Nombre", cell: (r: Resultado) => fmtNombre(r) },
            { header: "Gestion", cell: (r: Resultado) => r.postulacion?.gestion?.codigo ?? "-" },
            { header: "Carrera asignada", cell: (r: Resultado) => (
                r.carrera_asignada ? `${r.carrera_asignada.codigo}  ${r.carrera_asignada.nombre}` : ""
              ) },
            { header: "Opcion", cell: (r: Resultado) => (
                r.opcion_aceptada === "PRIMERA" ? "1ra" :
                r.opcion_aceptada === "SEGUNDA" ? "2da" :
                ""
              ) },
            { header: "Nota final", cell: (r: Resultado) => (
                <span className="font-mono">{Number(r.nota_final).toFixed(2)}</span>
              ) },
            { header: "Ranking", cell: (r: Resultado) => r.ranking_global ?? "" },
            { header: "Estado", cell: (r: Resultado) => (
                <Badge tone={tonoEstado[r.estado_final]}>
                  {r.estado_final === "PENDIENTE_DESEMPATE" ? "PENDIENTE DESEMPATE" : r.estado_final}
                </Badge>
              ) },
            { header: "Publicado", cell: (r: Resultado) => (
                r.publicado
                  ? <Badge tone="success">Si</Badge>
                  : <Badge tone="neutral">No</Badge>
              ) },
          ]}
        />
        )}

        {modo === "detalle" && data && data.last_page > 1 && (
          <div className="flex items-center justify-between mt-4 text-sm">
            <div className="text-muted-500">
              <div>Pagina {data.current_page} de {data.last_page}</div>
              <div>{data.total} registros</div>
            </div>
            <div className="flex gap-2">
              <Button
                variant="secondary"
                size="sm"
                onClick={() => setPagina((p) => Math.max(1, p - 1))}
                disabled={pagina <= 1 || cargando}
              >
                Anterior
              </Button>
              <Button
                variant="secondary"
                size="sm"
                onClick={() => setPagina((p) => Math.min(data.last_page, p + 1))}
                disabled={pagina >= data.last_page || cargando}
              >
                Siguiente
              </Button>
            </div>
          </div>
        )}
      </Card>
    </div>
  );
}

/* -------- Subcomponente: vista compacta tipo lista oficial -------- */
function ListaCompacta({
  data,
  cargando,
}: {
  data: Paginated<Resultado> | null;
  cargando: boolean;
}) {
  if (cargando) return <div className="text-sm text-muted-500 py-6 text-center">Cargando...</div>;
  if (!data || data.data.length === 0) {
    return <div className="text-sm text-muted-500 py-6 text-center">Sin aceptados para mostrar.</div>;
  }

  // Solo mostramos los que tienen codigo publicado.
  const items = data.data.filter((r) => r.publicado && r.postulacion?.codigo_postulante);

  const codigos = items.map((r) => {
    const codigo = r.postulacion?.codigo_postulante;
    const sufijo =
      r.opcion_aceptada === "PRIMERA" ? "1ra" :
      r.opcion_aceptada === "SEGUNDA" ? "2da" : "";
    return sufijo ? `${codigo}-${sufijo}` : codigo;
  });

  return (
    <div>
      <div className="mb-4 text-center">
        <div className="text-base font-semibold text-institutional-800">
          LISTA DE APROBADOS
        </div>
        <div className="text-xs text-muted-500">
          {items.length} cdigos publicados
        </div>
      </div>
      <div className="border border-muted-100 bg-white rounded-md p-4 font-mono text-sm text-institutional-800 flex flex-wrap gap-x-2 gap-y-1">
        {codigos.map((c, i) => (
          <span key={i} className="whitespace-nowrap">
            {c}{i < codigos.length - 1 ? " --" : ""}
          </span>
        ))}
      </div>
    </div>
  );
}
