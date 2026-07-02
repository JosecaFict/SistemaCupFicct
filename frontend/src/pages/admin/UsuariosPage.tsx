import { useEffect, useRef, useState } from "react";
import { Card } from "../../components/ui/Card";
import { Button } from "../../components/ui/Button";
import { Input } from "../../components/ui/Input";
import { Select } from "../../components/ui/Select";
import { Modal } from "../../components/ui/Modal";
import { DataTable } from "../../components/tables/DataTable";
import { Badge } from "../../components/ui/Badge";
import { adminService } from "../../services/adminService";
import { api } from "../../services/api";
import type { Materia, Paginated, PerfilDocente, Rol, User } from "../../types";
import { useToast } from "../../hooks/useToast";

/*
 * UsuariosPage (CU2 - Ciclo 3)
 * --------------------------------------------------------------------------
 * Lista, crea, edita y activa/inactiva usuarios. Solo accesible para
 * ADMINISTRADOR (protegido por RoleRoute en el router).
 *
 * Perfil DOCENTE (Ciclo 3):
 *   - Checkboxes de materias habilitadas (MAT, FIS, ING, COMP).
 *   - Descripcion estructurada: profesion + experiencias[] (min 2) + formacion.
 *   - Badge con las materias habilitadas y contador X/4 en la tabla.
 */

interface FormState {
  role_id: string;
  nombre: string;
  apellidos: string;
  fecha_nacimiento: string;
  ci: string;
  telefono: string;
  email: string;
  password: string;
  activo: boolean;
  // Perfil docente (Ciclo 3)
  materias_habilitadas: number[];
  profesion: string;
  experiencias: string[];
  formacion_adicional: string;
}

const formVacio = (): FormState => ({
  role_id: "",
  nombre: "",
  apellidos: "",
  fecha_nacimiento: "",
  ci: "",
  telefono: "",
  email: "",
  password: "",
  activo: true,
  materias_habilitadas: [],
  profesion: "",
  experiencias: ["", ""],
  formacion_adicional: "",
});

const badgeTonoMateria = (codigo: string): "info" | "warning" | "success" | "neutral" => {
  switch (codigo) {
    case "MAT": return "info";
    case "FIS": return "warning";
    case "ING": return "success";
    case "COMP": return "neutral";
    default: return "neutral";
  }
};

export function UsuariosPage() {
  const { push } = useToast();
  const [data, setData] = useState<Paginated<User> | null>(null);
  const [roles, setRoles] = useState<Rol[]>([]);
  const [materias, setMaterias] = useState<Materia[]>([]);
  const [q, setQ] = useState("");
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<User | null>(null);
  const [form, setForm] = useState<FormState>(formVacio());
  const [erroresForm, setErroresForm] = useState<string[]>([]);
  const [guardando, setGuardando] = useState(false);

  // Guarda anti respuestas fuera de orden (buscador): solo la ultima cargar() aplica.
  const reqIdRef = useRef(0);
  const cargar = (busq = q) => {
    const reqId = ++reqIdRef.current;
    adminService.usuarios({ q: busq }).then((d) => { if (reqId === reqIdRef.current) setData(d); });
  };

  useEffect(() => {
    adminService.roles().then(setRoles);
    api.get<Materia[]>("/api/catalogos/materias").then((r) => setMaterias(r.data));
    cargar("");
  }, []);

  const codigoRolSeleccionado = roles.find((r) => String(r.id) === form.role_id)?.codigo ?? "";
  const esDocente = codigoRolSeleccionado === "DOCENTE";

  const abrirNuevo = () => {
    setEditing(null);
    setErroresForm([]);
    setForm(formVacio());
    setOpen(true);
  };

  const abrirEditar = (u: User) => {
    setEditing(u);
    setErroresForm([]);
    const perfil: PerfilDocente | null = u.perfil_docente ?? null;
    setForm({
      role_id: String(u.role_id),
      nombre: u.nombre,
      apellidos: u.apellidos,
      fecha_nacimiento: u.fecha_nacimiento ?? "",
      ci: u.ci ?? "",
      telefono: u.telefono ?? "",
      email: u.email,
      password: "",
      activo: u.activo,
      materias_habilitadas: (u.materias_habilitadas ?? []).map((m) => m.id),
      profesion: perfil?.profesion ?? "",
      experiencias: perfil?.experiencias && perfil.experiencias.length >= 2
        ? [...perfil.experiencias]
        : [perfil?.experiencias?.[0] ?? "", ""],
      formacion_adicional: perfil?.formacion_adicional ?? "",
    });
    setOpen(true);
  };

  const toggleMateria = (materiaId: number) => {
    setForm((f) => ({
      ...f,
      materias_habilitadas: f.materias_habilitadas.includes(materiaId)
        ? f.materias_habilitadas.filter((id) => id !== materiaId)
        : [...f.materias_habilitadas, materiaId],
    }));
  };

  const agregarExperiencia = () => {
    setForm((f) => ({ ...f, experiencias: [...f.experiencias, ""] }));
  };
  const quitarExperiencia = (idx: number) => {
    setForm((f) => ({
      ...f,
      experiencias: f.experiencias.length <= 2
        ? f.experiencias
        : f.experiencias.filter((_, i) => i !== idx),
    }));
  };
  const setExperiencia = (idx: number, val: string) => {
    setForm((f) => ({
      ...f,
      experiencias: f.experiencias.map((e, i) => (i === idx ? val : e)),
    }));
  };

  const validarLocal = (): string[] => {
    const errs: string[] = [];
    if (!form.role_id) errs.push("Selecciona un rol.");
    if (!form.nombre.trim()) errs.push("El nombre es obligatorio.");
    if (!form.apellidos.trim()) errs.push("Los apellidos son obligatorios.");
    if (!form.email.trim()) errs.push("El correo es obligatorio.");
    if (!editing && form.password.length < 8) errs.push("La contrasena debe tener al menos 8 caracteres.");
    if (esDocente) {
      if (form.materias_habilitadas.length === 0) {
        errs.push("Marca al menos una materia habilitada.");
      }
      if (form.profesion.trim().length < 5) {
        errs.push("La profesion debe tener al menos 5 caracteres.");
      }
      const validExp = form.experiencias.filter((e) => e.trim().length >= 15);
      if (validExp.length < 2) {
        errs.push("Debes registrar al menos 2 experiencias con 15+ caracteres cada una.");
      }
    }
    return errs;
  };

  const guardar = async () => {
    const errs = validarLocal();
    setErroresForm(errs);
    if (errs.length > 0) return;

    setGuardando(true);
    try {
      const payload: Record<string, unknown> = {
        role_id: Number(form.role_id),
        nombre: form.nombre,
        apellidos: form.apellidos,
        fecha_nacimiento: form.fecha_nacimiento || null,
        ci: form.ci || null,
        telefono: form.telefono || null,
        email: form.email,
        activo: form.activo,
      };
      if (form.password) payload.password = form.password;

      if (esDocente) {
        payload.materias_habilitadas = form.materias_habilitadas;
        payload.descripcion_estructurada = {
          profesion: form.profesion.trim(),
          experiencias: form.experiencias.map((e) => e.trim()).filter((e) => e.length > 0),
          formacion_adicional: form.formacion_adicional.trim() || null,
        };
      }

      if (editing) {
        await adminService.actualizarUsuario(editing.id, payload as Partial<User> & { password?: string });
        push("Usuario actualizado", "success");
      } else {
        await adminService.crearUsuario(payload as Partial<User> & { password: string });
        push("Usuario creado", "success");
      }
      setOpen(false);
      cargar();
    } catch (e: unknown) {
      const er = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
      const errores = er?.response?.data?.errors
        ? Object.values(er.response.data.errors).flat()
        : [er?.response?.data?.message ?? "Error al guardar"];
      setErroresForm(errores);
      push(errores[0] ?? "Error al guardar", "danger");
    } finally {
      setGuardando(false);
    }
  };

  const toggle = async (u: User) => {
    await adminService.toggleActivoUsuario(u.id);
    cargar();
  };

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap justify-between items-center gap-3">
        <h1 className="text-2xl font-semibold text-institutional-800">Usuarios y roles</h1>
        <Button onClick={abrirNuevo}>Nuevo usuario</Button>
      </div>

      <Card>
        <div className="flex gap-2 items-end mb-4">
          <Input label="Buscar" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Nombre, apellido o correo" className="flex-1" />
          <Button variant="secondary" onClick={() => cargar()}>Buscar</Button>
        </div>

        <DataTable
          rows={data?.data ?? []}
          empty="Sin usuarios."
          columns={[
            { header: "Nombre",   cell: (u) => `${u.nombre} ${u.apellidos}` },
            { header: "Correo",   cell: (u) => u.email },
            { header: "Rol",      cell: (u) => u.rol?.nombre ?? "-" },
            {
              header: "Materias",
              cell: (u) => {
                if (u.rol?.codigo !== "DOCENTE") return <span className="text-muted-400">-</span>;
                const mats = u.materias_habilitadas ?? [];
                if (mats.length === 0) {
                  return <span className="text-xs text-warning-600">Sin habilitar</span>;
                }
                return (
                  <div className="flex flex-wrap gap-1">
                    {mats.map((m) => (
                      <Badge key={m.id} tone={badgeTonoMateria(m.codigo)}>{m.codigo}</Badge>
                    ))}
                  </div>
                );
              },
            },
            {
              header: "Carga",
              cell: (u) => {
                if (u.rol?.codigo !== "DOCENTE") return <span className="text-muted-400">-</span>;
                const usadas = u.asignaciones_usadas ?? 0;
                const max = u.asignaciones_maximo ?? 4;
                const tono = usadas >= max ? "danger" : usadas >= max - 1 ? "warning" : "neutral";
                return <Badge tone={tono}>{usadas}/{max}</Badge>;
              },
            },
            { header: "Estado",   cell: (u) => <Badge tone={u.activo ? "success" : "neutral"}>{u.activo ? "ACTIVO" : "INACTIVO"}</Badge> },
            { header: "Acciones", cell: (u) => (
                <div className="flex gap-2">
                  <Button size="sm" variant="secondary" onClick={() => abrirEditar(u)}>Editar</Button>
                  <Button size="sm" variant={u.activo ? "danger" : "success"} onClick={() => toggle(u)}>
                    {u.activo ? "Inactivar" : "Activar"}
                  </Button>
                </div>
              ) },
          ]}
        />
      </Card>

      <Modal open={open} onClose={() => setOpen(false)} title={editing ? "Editar usuario" : "Nuevo usuario"}
             footer={<>
               <Button variant="secondary" onClick={() => setOpen(false)}>Cancelar</Button>
               <Button onClick={guardar} disabled={guardando}>
                 {guardando ? "Guardando..." : editing ? "Guardar" : "Crear"}
               </Button>
             </>}>
        {erroresForm.length > 0 && (
          <div className="mb-3 rounded-md border border-danger-300 bg-danger-50 p-2 text-sm text-danger-700">
            <ul className="list-disc list-inside space-y-0.5">
              {erroresForm.map((e, i) => <li key={i}>{e}</li>)}
            </ul>
          </div>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Input label="Nombre"    value={form.nombre}    onChange={(e) => setForm({ ...form, nombre: e.target.value })} />
          <Input label="Apellidos" value={form.apellidos} onChange={(e) => setForm({ ...form, apellidos: e.target.value })} />
          <Input label="Fecha de nacimiento" type="date" value={form.fecha_nacimiento}
                 onChange={(e) => setForm({ ...form, fecha_nacimiento: e.target.value })} />
          <Input label="Carnet de identidad" value={form.ci} maxLength={20}
                 onChange={(e) => setForm({ ...form, ci: e.target.value })} />
          <Input label="Telefono" value={form.telefono} maxLength={20}
                 onChange={(e) => setForm({ ...form, telefono: e.target.value })} />
          <Input label="Correo"    type="email" value={form.email}     onChange={(e) => setForm({ ...form, email: e.target.value })} />
          <Select label="Rol" value={form.role_id} onChange={(e) => setForm({ ...form, role_id: e.target.value })}>
            <option value="">Selecciona</option>
            {roles.map((r) => <option key={r.id} value={r.id}>{r.nombre}</option>)}
          </Select>
          <Input label={editing ? "Nueva contrasena (opcional)" : "Contrasena"} type="password" value={form.password}
                 onChange={(e) => setForm({ ...form, password: e.target.value })} />
        </div>

        {/* ==================================================================
             Perfil profesional del docente (Ciclo 3 - Opcion B1)
             ================================================================== */}
        {esDocente && (
          <div className="mt-5 rounded-md border border-institutional-200 bg-institutional-50/40 p-3 space-y-4">
            <div className="text-sm font-semibold text-institutional-700">
              Perfil profesional del docente
            </div>

            {/* Materias habilitadas */}
            <div>
              <label className="block text-xs font-medium text-institutional-700 mb-1">
                Materias que puede enseñar en el CUP <span className="text-danger-600">*</span>
              </label>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                {materias.map((m) => {
                  const marcada = form.materias_habilitadas.includes(m.id);
                  return (
                    <button
                      key={m.id}
                      type="button"
                      onClick={() => toggleMateria(m.id)}
                      className={`rounded-md border px-3 py-2 text-sm text-left transition ${
                        marcada
                          ? "border-institutional-500 bg-institutional-100 text-institutional-800 font-medium"
                          : "border-muted-200 bg-white text-muted-600 hover:border-institutional-300"
                      }`}
                    >
                      <div className="flex items-center gap-2">
                        <input type="checkbox" checked={marcada} onChange={() => {}} className="pointer-events-none" />
                        <span>{m.codigo}</span>
                      </div>
                      <div className="text-xs text-muted-500">{m.nombre}</div>
                    </button>
                  );
                })}
              </div>
              <p className="text-xs text-muted-500 mt-1">
                Al asignar grupos, solo aparecera para las materias que marques aqui.
              </p>
            </div>

            {/* Profesion */}
            <Input
              label="Profesion / Titulo academico *"
              value={form.profesion}
              onChange={(e) => setForm({ ...form, profesion: e.target.value })}
              maxLength={120}
              placeholder="Ej: Ingeniero Civil"
            />

            {/* Experiencias */}
            <div>
              <label className="block text-xs font-medium text-institutional-700 mb-1">
                Experiencia profesional <span className="text-danger-600">*</span> (minimo 2)
              </label>
              <div className="space-y-2">
                {form.experiencias.map((exp, idx) => (
                  <div key={idx} className="flex gap-2 items-start">
                    <div className="flex-1">
                      <textarea
                        className="w-full rounded-md border border-muted-300 px-3 py-2 text-sm focus:border-institutional-500 focus:outline-none focus:ring-1 focus:ring-institutional-500"
                        rows={2}
                        value={exp}
                        onChange={(e) => setExperiencia(idx, e.target.value)}
                        placeholder={`Experiencia ${idx + 1}: ej. 2 anos como docente invitado en la UAGRM`}
                        maxLength={200}
                      />
                    </div>
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      disabled={form.experiencias.length <= 2}
                      onClick={() => quitarExperiencia(idx)}
                      title={form.experiencias.length <= 2 ? "Debe haber al menos 2" : "Quitar esta experiencia"}
                    >
                      ×
                    </Button>
                  </div>
                ))}
              </div>
              <Button type="button" size="sm" variant="secondary" onClick={agregarExperiencia} className="mt-2">
                + Agregar experiencia
              </Button>
            </div>

            {/* Formacion adicional */}
            <div>
              <label className="block text-xs font-medium text-institutional-700 mb-1">
                Formacion adicional (opcional)
              </label>
              <textarea
                className="w-full rounded-md border border-muted-300 px-3 py-2 text-sm focus:border-institutional-500 focus:outline-none focus:ring-1 focus:ring-institutional-500"
                rows={2}
                value={form.formacion_adicional}
                onChange={(e) => setForm({ ...form, formacion_adicional: e.target.value })}
                maxLength={400}
                placeholder="Maestria, diplomados, publicaciones, etc."
              />
            </div>

            <p className="text-xs text-muted-500 italic">
              Solo el administrador puede editar este perfil.
            </p>
          </div>
        )}
      </Modal>
    </div>
  );
}
