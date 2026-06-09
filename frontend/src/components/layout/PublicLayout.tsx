import { Link, Outlet } from "react-router-dom";
import { APP_NAME } from "../../data/constants";

/*
 * PublicLayout
 * --------------------------------------------------------------------------
 * Layout para pantallas publicas (landing, preinscripcion, pago, resultados).
 * No requiere autenticacion. Incluye topbar institucional y footer simple.
 */
export function PublicLayout() {
  return (
    <div className="min-h-screen flex flex-col bg-muted-50">
      <header className="bg-institutional-700 text-white no-print">
        <div className="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
          <Link to="/" className="font-semibold text-lg tracking-tight">
            {APP_NAME}
          </Link>
          <nav className="text-sm flex gap-5">
            <Link to="/preinscripcion" className="hover:underline">Preinscripcion</Link>
            <Link to="/resultados"     className="hover:underline">Resultados</Link>
            <Link to="/login"          className="hover:underline">Acceso</Link>
          </nav>
        </div>
      </header>

      <main className="flex-1 max-w-6xl w-full mx-auto px-4 py-8">
        <Outlet />
      </main>

      <footer className="bg-white border-t border-muted-100 text-xs text-muted-500 no-print">
        <div className="max-w-6xl mx-auto px-4 py-3 flex flex-wrap justify-between gap-2">
          <span>(c) FICCT - Facultad de Ingenieria en Ciencias de la Computacion y Telecomunicaciones</span>
          <span>Curso Preuniversitario CUP</span>
        </div>
      </footer>
    </div>
  );
}
