import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { Card } from "../../components/ui/Card";
import { Input } from "../../components/ui/Input";
import { Select } from "../../components/ui/Select";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";
import { EXPEDIDOS_BO } from "../../data/constants";
import type { TipoDocumento } from "../../types";
import { publicService } from "../../services/publicService";

/*
 * ReimprimirFormulario
 * --------------------------------------------------------------------------
 * Permite al postulante que ya se preinscribio recuperar su formulario para
 * volver a imprimirlo (por si lo perdio, no lo descargo, etc.).
 * Identifica al postulante por carnet + expedido (igual que CU5 paso 1).
 */
export function ReimprimirFormulario() {
  const navigate = useNavigate();
  const [tipo, setTipo] = useState<TipoDocumento>("CI_BO");
  const [documento, setDocumento] = useState("");
  const [expedido, setExpedido] = useState<string>("SC");
  const [error, setError] = useState<string | null>(null);
  const [sinFormulario, setSinFormulario] = useState(false);
  const [cargando, setCargando] = useState(false);

  const onBuscar = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSinFormulario(false);

    if (!documento.trim()) {
      setError("Ingresa tu numero de documento.");
      return;
    }

    setCargando(true);
    try {
      const { postulacion } = await publicService.buscarPorDocumento({
        tipo_documento: tipo,
        documento: documento.trim(),
        expedido: tipo === "CI_BO" ? expedido : null,
      });
      navigate(`/preinscripcion/${postulacion.id}/formulario`);
    } catch (e: unknown) {
      const er = e as { response?: { status?: number; data?: { message?: string } } };
      if (er?.response?.status === 404) {
        setSinFormulario(true);
      } else {
        setError(er?.response?.data?.message ?? "No fue posible buscar tu formulario.");
      }
    } finally {
      setCargando(false);
    }
  };

  return (
    <div className="max-w-xl mx-auto space-y-6">
      <div>
        <h2 className="text-2xl font-semibold text-institutional-800">Reimprimir formulario</h2>
        <p className="text-sm text-muted-500">
          Si ya te preinscribiste y necesitas reimprimir tu formulario, identificate con tu documento.
        </p>
      </div>

      {sinFormulario && (
        <Alert tone="warning" title="No encontramos tu preinscripcion">
          No registramos un formulario con esos datos en la gestion actual.{" "}
          <Link to="/preinscripcion" className="underline text-institutional-700">
            Preinscribete aqui
          </Link>.
        </Alert>
      )}

      <Card>
        <form onSubmit={onBuscar} className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Select label="Tipo de documento" value={tipo}
                    onChange={(e) => setTipo(e.target.value as TipoDocumento)}>
              <option value="CI_BO">CI Bolivia</option>
              <option value="EXT">Extranjero</option>
            </Select>
            <Input label="Numero de documento"
                   value={documento}
                   onChange={(e) => setDocumento(e.target.value)}
                   placeholder={tipo === "CI_BO" ? "12345678" : "AB123456"} />
            {tipo === "CI_BO" && (
              <Select label="Expedido" value={expedido} onChange={(e) => setExpedido(e.target.value)}>
                {EXPEDIDOS_BO.map((c) => <option key={c} value={c}>{c}</option>)}
              </Select>
            )}
          </div>

          {error && <Alert tone="danger">{error}</Alert>}

          <div className="flex justify-between items-center">
            <Link to="/" className="text-sm text-muted-500 hover:underline">Volver al inicio</Link>
            <Button type="submit" loading={cargando}>Buscar formulario</Button>
          </div>
        </form>
      </Card>
    </div>
  );
}
