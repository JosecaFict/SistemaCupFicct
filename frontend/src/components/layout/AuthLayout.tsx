import { Outlet } from "react-router-dom";
import { APP_NAME } from "../../data/constants";

/*
 * AuthLayout
 * --------------------------------------------------------------------------
 * Layout minimalista para Login y Recuperacion de contrasena.
 */
export function AuthLayout() {
  return (
    <div className="min-h-screen flex bg-institutional-700">
      <div className="hidden lg:flex flex-1 items-center justify-center text-white p-12">
        <div className="max-w-md">
          <div className="text-sm uppercase tracking-widest text-institutional-200">FICCT</div>
          <h1 className="text-3xl font-semibold mt-3">{APP_NAME}</h1>
          <p className="mt-4 text-institutional-100">
            Gestion del proceso de admision del Curso Preuniversitario:
            preinscripcion, pago, verificacion de requisitos, inscripcion y
            distribucion en grupos.
          </p>
        </div>
      </div>
      <div className="flex-1 flex items-center justify-center bg-white">
        <div className="w-full max-w-sm px-6 py-10">
          <Outlet />
        </div>
      </div>
    </div>
  );
}
