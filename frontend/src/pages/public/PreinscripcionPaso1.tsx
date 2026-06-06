import { useNavigate } from "react-router-dom";
import { useEffect, useState } from "react";
import { Card } from "../../components/ui/Card";
import { Input } from "../../components/ui/Input";
import { Select } from "../../components/ui/Select";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";
import { EXPEDIDOS_BO } from "../../data/constants";
import type { GestionCup, TipoDocumento } from "../../types";
import { publicService } from "../../services/publicService";

const fmtFecha = (iso?: string | null) =>
  iso ? iso.slice(0, 10).split("-").reverse().join("/") : "-";

/*
 * PreinscripcionPaso1 -- Identificacion (CU5 paso 1)
 * --------------------------------------------------------------------------
 * Recoge tipo de documento, numero (y expedido si CI Bolivia) y verifica
 * que exista una gestion CUP activa. La info se guarda en sessionStorage
 * para el Paso 2.
 */
export function PreinscripcionPaso1() {
  const navigate = useNavigate();
  const [tipo, setTipo] = useState<TipoDocumento>("CI_BO");
  const [documento, setDocumento] = useState("");
  const [expedido, setExpedido] = useState<string>("SC");
  const [gestion, setGestion] = useState<GestionCup | null>(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    publicService.gestionActiva()
      // El backend puede devolver {} (objeto vacio) cuando no hay gestion real.
      // Solo la tomamos como valida si trae un id; si no, es "sin gestion".
      .then((g) => setGestion(g && g.id ? g : null))
      .finally(() => setCargando(false));
  }, []);

  const onContinuar = () => {
    setError(null);
    if (!gestion) {
      setError("No hay una gestion CUP activa en este momento.");
      return;
    }
    if (!documento.trim()) {
      setError("Ingresa tu numero de documento.");
      return;
    }
    sessionStorage.setItem("cup_preinsc_paso1", JSON.stringify({
      tipo_documento: tipo,
      documento: documento.trim(),
      expedido: tipo === "CI_BO" ? expedido : null,
      gestion_cup_id: gestion.id,
    }));
    navigate("/preinscripcion/2");
  };

  if (cargando) return <Card>Cargando...</Card>;

  return (
    <div className="max-w-xl mx-auto space-y-6">
      <div>
        <div className="text-xs uppercase text-institutional-500">Paso 1 de 2</div>
        <h2 className="text-2xl font-semibold text-institutional-800">Identificacion</h2>
        <p className="text-sm text-muted-500">Para empezar tu preinscripcion, identificate con tu documento.</p>
      </div>

      {!gestion && (
        <Alert tone="warning" title="Sin gestion activa">
          Aun no hay una gestion CUP abierta para preinscripcion. Vuelve mas tarde.
        </Alert>
      )}

      {gestion && (
        <Alert tone="info" title={`Gestion CUP ${gestion.codigo}`}>
          Preinscripcion abierta del {fmtFecha(gestion.fecha_inicio_preinscripcion)} al {fmtFecha(gestion.fecha_cierre_preinscripcion)}.
        </Alert>
      )}

      <Card>
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
        {error && <div className="mt-3"><Alert tone="danger">{error}</Alert></div>}
        <div className="mt-5 flex justify-end">
          <Button onClick={onContinuar} disabled={!gestion}>Continuar</Button>
        </div>
      </Card>
    </div>
  );
}
