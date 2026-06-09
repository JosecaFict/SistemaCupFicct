import { useEffect, useState } from "react";
import { Card } from "../../components/ui/Card";
import { Button } from "../../components/ui/Button";
import { Input } from "../../components/ui/Input";
import { Select } from "../../components/ui/Select";
import { Modal } from "../../components/ui/Modal";
import { DataTable } from "../../components/tables/DataTable";
import { Badge } from "../../components/ui/Badge";
import { adminService } from "../../services/adminService";
import type { Paginated, Rol, User } from "../../types";
import { useToast } from "../../hooks/useToast";

/*
 * UsuariosPage (CU2)
 * --------------------------------------------------------------------------
 * Lista, crea, edita y activa/inactiva usuarios. Solo accesible para
 * ADMINISTRADOR (protegido por RoleRoute en el router).
 */
export function UsuariosPage() {
  const { push } = useToast();
  const [data, setData] = useState<Paginated<User> | null>(null);
  const [roles, setRoles] = useState<Rol[]>([]);
  const [q, setQ] = useState("");
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<User | null>(null);
  const [form, setForm] = useState({ role_id: "", nombre: "", apellidos: "", email: "", password: "", activo: true });

  const cargar = (busq = q) => {
    adminService.usuarios({ q: busq }).then(setData);
  };

  useEffect(() => { adminService.roles().then(setRoles); cargar(""); }, []);

  const abrirNuevo = () => {
    setEditing(null);
    setForm({ role_id: "", nombre: "", apellidos: "", email: "", password: "", activo: true });
    setOpen(true);
  };
  const abrirEditar = (u: User) => {
    setEditing(u);
    setForm({ role_id: String(u.role_id), nombre: u.nombre, apellidos: u.apellidos, email: u.email, password: "", activo: u.activo });
    setOpen(true);
  };

  const guardar = async () => {
    try {
      const payload = {
        ...form,
        role_id: Number(form.role_id),
      } as Partial<User> & { password: string };
      if (editing) {
        await adminService.actualizarUsuario(editing.id, payload);
        push("Usuario actualizado", "success");
      } else {
        await adminService.crearUsuario(payload);
        push("Usuario creado", "success");
      }
      setOpen(false);
      cargar();
    } catch (e: unknown) {
      const er = e as { response?: { data?: { message?: string } } };
      push(er?.response?.data?.message ?? "Error al guardar", "danger");
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
            { header: "Nombre",    cell: (u) => `${u.nombre} ${u.apellidos}` },
            { header: "Correo",    cell: (u) => u.email },
            { header: "Rol",       cell: (u) => u.rol?.nombre ?? "-" },
            { header: "Estado",    cell: (u) => <Badge tone={u.activo ? "success" : "neutral"}>{u.activo ? "ACTIVO" : "INACTIVO"}</Badge> },
            { header: "Acciones",  cell: (u) => (
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
               <Button onClick={guardar}>{editing ? "Guardar" : "Crear"}</Button>
             </>}>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <Input label="Nombre"    value={form.nombre}    onChange={(e) => setForm({ ...form, nombre: e.target.value })} />
          <Input label="Apellidos" value={form.apellidos} onChange={(e) => setForm({ ...form, apellidos: e.target.value })} />
          <Input label="Correo"    type="email" value={form.email}     onChange={(e) => setForm({ ...form, email: e.target.value })} />
          <Select label="Rol" value={form.role_id} onChange={(e) => setForm({ ...form, role_id: e.target.value })}>
            <option value="">Selecciona</option>
            {roles.map((r) => <option key={r.id} value={r.id}>{r.nombre}</option>)}
          </Select>
          <Input label={editing ? "Nueva contrasena (opcional)" : "Contrasena"} type="password" value={form.password}
                 onChange={(e) => setForm({ ...form, password: e.target.value })} />
        </div>
      </Modal>
    </div>
  );
}
