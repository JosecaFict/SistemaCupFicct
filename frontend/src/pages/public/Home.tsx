import { Link } from "react-router-dom";
import { Card } from "../../components/ui/Card";
import { Button } from "../../components/ui/Button";

/*
 * Home (Landing publica CUP)
 * --------------------------------------------------------------------------
 * Punto de entrada para el postulante. Explica el proceso CUP y enlaza a
 * preinscripcion y consulta de resultados.
 */
export function Home() {
  return (
    <div className="space-y-8">
      <section className="bg-white border border-muted-100 rounded-lg shadow-card p-8 md:p-12">
        <div className="text-xs uppercase tracking-widest text-institutional-500">FICCT</div>
        <h1 className="text-3xl md:text-4xl font-bold text-institutional-800 mt-1">
          Curso Preuniversitario CUP
        </h1>
        <p className="text-muted-600 mt-3 max-w-2xl">
          Bienvenido al sistema de admision del Curso Preuniversitario de la
          Facultad de Ingenieria en Ciencias de la Computacion y Telecomunicaciones.
          Desde aqui puedes preinscribirte, pagar tu inscripcion y consultar tus resultados.
        </p>
        <div className="mt-6 flex flex-wrap gap-3">
          <Link to="/preinscripcion"><Button>Preinscribirme</Button></Link>
          <Link to="/reimprimir-formulario"><Button variant="secondary">Reimprimir Formulario</Button></Link>
          <Link to="/iniciar-pago"><Button variant="secondary">Pagos</Button></Link>
          <Link to="/resultados"><Button variant="secondary">Consultar resultados</Button></Link>
        </div>
      </section>

      <section className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card title="1. Preinscripcion">
          Completa tus datos personales y elige tu carrera. Es gratis y no requiere cuenta.
        </Card>
        <Card title="2. Pago de inscripcion">
          Una vez generado el formulario, paga en linea (modo de prueba en esta fase del sistema).
        </Card>
        <Card title="3. Verificacion e inscripcion">
          El encargado revisa tus documentos, confirma tu inscripcion y te asigna grupo. Recibes tu codigo de postulante.
        </Card>
      </section>
    </div>
  );
}
