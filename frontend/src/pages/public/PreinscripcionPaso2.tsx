import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Input } from "../../components/ui/Input";
import { Select } from "../../components/ui/Select";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";
import { FormSection } from "../../components/forms/FormSection";
import type { Carrera } from "../../types";
import { publicService } from "../../services/publicService";
import { useToast } from "../../hooks/useToast";

/*
 * PreinscripcionPaso2 -- Datos del postulante (CU5 paso 2)
 * --------------------------------------------------------------------------
 * Recolecta datos personales, colegio y opciones de carrera. Llama al
 * endpoint POST /api/public/preinscripciones para crear la postulacion y
 * navega al paso de formulario generado.
 */
export function PreinscripcionPaso2() {
  const navigate = useNavigate();
  const { push } = useToast();
  const [carreras, setCarreras] = useState<Carrera[]>([]);
  const [enviando, setEnviando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({
    nombre: "", apellido_paterno: "", apellido_materno: "",
    sexo: "", fecha_nacimiento: "", email: "", telefono: "", direccion: "",
    colegio_nombre: "", colegio_ciudad: "", anio_egreso_colegio: "",
    carrera_primera_id: "", carrera_segunda_id: "",
  });

  useEffect(() => {
    if (!sessionStorage.getItem("cup_preinsc_paso1")) {
      navigate("/preinscripcion", { replace: true });
      return;
    }
    publicService.carreras().then(setCarreras);
  }, [navigate]);

  const upd = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    const paso1 = JSON.parse(sessionStorage.getItem("cup_preinsc_paso1") ?? "{}");

    if (!form.carrera_primera_id) {
      setError("Selecciona tu primera opcion de carrera.");
      return;
    }

    setEnviando(true);
    try {
      const payload = {
        ...paso1,
        ...form,
        anio_egreso_colegio: form.anio_egreso_colegio ? Number(form.anio_egreso_colegio) : null,
        carrera_primera_id: Number(form.carrera_primera_id),
        carrera_segunda_id: form.carrera_segunda_id ? Number(form.carrera_segunda_id) : null,
        sexo: form.sexo || null,
        fecha_nacimiento: form.fecha_nacimiento || null,
      };
      const { postulacion } = await publicService.preinscribir(payload);
      sessionStorage.removeItem("cup_preinsc_paso1");
      push("Preinscripcion registrada", "success");
      navigate(`/preinscripcion/${postulacion.id}/formulario`, { replace: true });
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      setError(e?.response?.data?.message ?? "No fue posible registrar la preinscripcion.");
    } finally {
      setEnviando(false);
    }
  };

  return (
    <form className="space-y-6 max-w-4xl mx-auto" onSubmit={onSubmit}>
      <div>
        <div className="text-xs uppercase text-institutional-500">Paso 2 de 2</div>
        <h2 className="text-2xl font-semibold text-institutional-800">Datos del postulante</h2>
      </div>

      <FormSection title="Datos personales">
        <Input label="Nombres" value={form.nombre} onChange={upd("nombre")} required />
        <Input label="Apellido paterno" value={form.apellido_paterno} onChange={upd("apellido_paterno")} />
        <Input label="Apellido materno" value={form.apellido_materno} onChange={upd("apellido_materno")} />
        <Select label="Sexo" value={form.sexo} onChange={upd("sexo")}>
          <option value="">--</option>
          <option value="M">Masculino</option>
          <option value="F">Femenino</option>
          <option value="O">Otro</option>
        </Select>
        <Input label="Fecha de nacimiento" type="date" value={form.fecha_nacimiento} onChange={upd("fecha_nacimiento")} />
      </FormSection>

      <FormSection title="Contacto">
        <Input label="Telefono" value={form.telefono} onChange={upd("telefono")} />
        <Input label="Correo electronico" type="email" value={form.email} onChange={upd("email")} />
        <Input label="Direccion" value={form.direccion} onChange={upd("direccion")} />
      </FormSection>

      <FormSection title="Colegio">
        <Input label="Nombre del colegio" value={form.colegio_nombre} onChange={upd("colegio_nombre")} />
        <Input label="Ciudad" value={form.colegio_ciudad} onChange={upd("colegio_ciudad")} />
        <Input label="Ano de egreso" type="number" min={1980} max={2100} value={form.anio_egreso_colegio} onChange={upd("anio_egreso_colegio")} />
      </FormSection>

      <FormSection title="Opciones de carrera">
        <Select label="Primera opcion" value={form.carrera_primera_id} onChange={upd("carrera_primera_id")} required>
          <option value="">-- Selecciona --</option>
          {carreras.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}
        </Select>
        <Select label="Segunda opcion (opcional)" value={form.carrera_segunda_id} onChange={upd("carrera_segunda_id")}>
          <option value="">-- Ninguna --</option>
          {carreras.filter((c) => String(c.id) !== form.carrera_primera_id).map((c) =>
            <option key={c.id} value={c.id}>{c.nombre}</option>
          )}
        </Select>
      </FormSection>

      {error && <Alert tone="danger">{error}</Alert>}

      <div className="flex justify-end gap-2">
        <Button type="button" variant="secondary" onClick={() => navigate("/preinscripcion")}>Atras</Button>
        <Button type="submit" loading={enviando}>Registrar preinscripcion</Button>
      </div>
    </form>
  );
}
