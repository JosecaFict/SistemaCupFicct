import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { Card } from "../../components/ui/Card";
import { Button } from "../../components/ui/Button";
import { Select } from "../../components/ui/Select";
import { Alert } from "../../components/ui/Alert";
import { Spinner } from "../../components/ui/Spinner";
import { StatusBadge } from "../../components/tables/StatusBadge";
import { encargadoService } from "../../services/encargadoService";
import { api } from "../../services/api";
import type { Postulacion, PostulacionRequisito, Turno } from "../../types";
import { useToast } from "../../hooks/useToast";

/*
 * PostulanteDetallePage (CU8 + CU9 + CU10)
 * --------------------------------------------------------------------------
 * Pantalla central del Encargado:
 *  - Verifica el checklist de requisitos (VALIDADO/OBSERVADO/RECHAZADO).
 *  - Selecciona turno y confirma inscripcion (genera codigo + asigna grupo).
 *  - Acceso directo a la boleta.
 */
export function PostulanteDetallePage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { push } = useToast();
  const [post, setPost] = useState<Postulacion | null>(null);
  const [reqs, setReqs] = useState<PostulacionRequisito[]>([]);
  const [turnos, setTurnos] = useState<Turno[]>([]);
  const [turno, setTurno] = useState<string>("");
  const [confirmando, setConfirmando] = useState(false);
  const [todosOk, setTodosOk] = useState(false);

  const cargar = async () => {
    if (!id) return;
    const p = await encargadoService.postulacion(Number(id));
    setPost(p);
    const r = await encargadoService.requisitos(Number(id));
    setReqs(r);
    const c = await encargadoService.requisitosCompletados(Number(id));
    setTodosOk(c.todos_completados);
  };

  useEffect(() => {
    cargar();
    api.get<Turno[]>("/api/catalogos/turnos").then((res) => setTurnos(res.data));
  }, [id]);

  const marcar = async (pr: PostulacionRequisito, estado: string) => {
    let observacion: string | undefined;
    if (estado === "OBSERVADO" || estado === "RECHAZADO") {
      observacion = prompt("Observacion:") ?? undefined;
    }
    await encargadoService.marcarRequisito(pr.id, estado, observacion);
    cargar();
  };

  const confirmar = async () => {
    if (!post || !turno) return;
    if (!confirm("Confirmar la inscripcion de este postulante?")) return;
    setConfirmando(true);
    try {
      await encargadoService.confirmar(post.id, Number(turno));
      push("Inscripcion confirmada", "success");
      navigate(`/encargado/postulantes/${post.id}/boleta`);
    } catch (e: unknown) {
      const er = e as { response?: { data?: { message?: string } } };
      push(er?.response?.data?.message ?? "No fue posible confirmar.", "danger");
    } finally {
      setConfirmando(false);
    }
  };

  if (!post) return <div className="flex justify-center py-10"><Spinner /></div>;

  const p = post.persona!;
  const yaInscrito = post.estado === "INSCRITO";
  const tienePagoAprobado = post.estado === "PAGO_APROBADO" || post.estado === "INSCRITO" || post.estado === "OBSERVADO";

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-institutional-800">
            {p.nombre} {p.apellido_paterno ?? ""} {p.apellido_materno ?? ""}
          </h1>
          <div className="text-sm text-muted-500">
            Documento: <b>{p.documento}{p.expedido ? " " + p.expedido : ""}</b> ({p.tipo_documento})
          </div>
        </div>
        <div className="flex items-center gap-2">
          {post.codigo_postulante && <span className="text-xs text-muted-500">Codigo: <b className="text-institutional-700">{post.codigo_postulante}</b></span>}
          <StatusBadge estado={post.estado} tipo="postulacion" />
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Card title="Datos academicos">
          <div className="text-sm space-y-1">
            <div><b>Gestion:</b> {post.gestion?.codigo}</div>
            <div><b>Primera opcion:</b> {post.carrera_primera?.nombre}</div>
            <div><b>Segunda opcion:</b> {post.carrera_segunda?.nombre ?? "-"}</div>
            {post.turno && <div><b>Turno asignado:</b> {post.turno.nombre} ({post.turno.codigo})</div>}
            {post.grupo && <div><b>Grupo asignado:</b> {post.grupo.codigo}</div>}
          </div>
        </Card>

        <Card title="Contacto">
          <div className="text-sm space-y-1">
            <div><b>Correo:</b> {p.email ?? "-"}</div>
            <div><b>Telefono:</b> {p.telefono ?? "-"}</div>
            <div><b>Direccion:</b> {p.direccion ?? "-"}</div>
          </div>
        </Card>
      </div>

      <Card title="Requisitos (CU8)">
        {!tienePagoAprobado && (
          <Alert tone="warning">El pago aun no esta aprobado. La inscripcion no puede confirmarse.</Alert>
        )}
        <table className="min-w-full text-sm mt-3">
          <thead>
            <tr className="bg-muted-50 text-muted-600 text-left">
              <th className="px-3 py-2">Requisito</th>
              <th className="px-3 py-2">Estado</th>
              <th className="px-3 py-2">Observacion</th>
              <th className="px-3 py-2">Acciones</th>
            </tr>
          </thead>
          <tbody>
            {reqs.map((r) => (
              <tr key={r.id} className="border-t border-muted-100">
                <td className="px-3 py-2">{r.requisito?.nombre}</td>
                <td className="px-3 py-2"><StatusBadge estado={r.estado} tipo="requisito" /></td>
                <td className="px-3 py-2 text-muted-600">{r.observacion ?? "-"}</td>
                <td className="px-3 py-2 flex gap-1 flex-wrap">
                  <Button size="sm" variant="success" onClick={() => marcar(r, "VALIDADO")}>Validar</Button>
                  <Button size="sm" variant="secondary" onClick={() => marcar(r, "OBSERVADO")}>Observar</Button>
                  <Button size="sm" variant="danger" onClick={() => marcar(r, "RECHAZADO")}>Rechazar</Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      {!yaInscrito && (
        <Card title="Confirmar inscripcion (CU9 + CU10)">
          {!todosOk && <Alert tone="warning">Aun hay requisitos sin validar.</Alert>}
          <div className="flex flex-wrap gap-3 items-end mt-3">
            <Select label="Turno" value={turno} onChange={(e) => setTurno(e.target.value)}>
              <option value="">-- selecciona --</option>
              {turnos.map((t) => <option key={t.id} value={t.id}>{t.nombre} ({t.codigo})</option>)}
            </Select>
            <Button onClick={confirmar} loading={confirmando}
                    disabled={!todosOk || !tienePagoAprobado || !turno}
                    variant="success">
              Confirmar inscripcion
            </Button>
          </div>
        </Card>
      )}

      {yaInscrito && (
        <div className="flex justify-end">
          <Button onClick={() => navigate(`/encargado/postulantes/${post.id}/boleta`)}>Ver boleta</Button>
        </div>
      )}
    </div>
  );
}
