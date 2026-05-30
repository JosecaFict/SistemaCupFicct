import { Routes, Route, Navigate } from "react-router-dom";

import { PublicLayout } from "../components/layout/PublicLayout";
import { AuthLayout }   from "../components/layout/AuthLayout";
import { AppLayout }    from "../components/layout/AppLayout";

import { Home } from "../pages/public/Home";
import { PreinscripcionPaso1 } from "../pages/public/PreinscripcionPaso1";
import { PreinscripcionPaso2 } from "../pages/public/PreinscripcionPaso2";
import { FormularioGenerado }  from "../pages/public/FormularioGenerado";
import { PagoSimulado }        from "../pages/public/PagoSimulado";
import { ResultadosPublicos }  from "../pages/public/ResultadosPublicos";
import { ReimprimirFormulario } from "../pages/public/ReimprimirFormulario";
import { IniciarPagoPublico }   from "../pages/public/IniciarPagoPublico";

import { Login }            from "../pages/auth/Login";
import { ForgotPassword }   from "../pages/auth/ForgotPassword";
import { ResetPassword }    from "../pages/auth/ResetPassword";

import { AdminDashboard }   from "../pages/admin/AdminDashboard";
import { UsuariosPage }     from "../pages/admin/UsuariosPage";
import { GestionesPage }    from "../pages/admin/GestionesPage";
import { GestionFormPage }  from "../pages/admin/GestionFormPage";
import { GruposPage }       from "../pages/admin/GruposPage";
import { BitacoraPage }     from "../pages/admin/BitacoraPage";

import { EncargadoDashboard }   from "../pages/encargado/EncargadoDashboard";
import { PostulantesPage }      from "../pages/encargado/PostulantesPage";
import { PostulanteDetallePage }from "../pages/encargado/PostulanteDetallePage";
import { BoletaPage }           from "../pages/encargado/BoletaPage";

import { DocenteDashboard }     from "../pages/docente/DocenteDashboard";
import { CoordinadorDashboard } from "../pages/coordinador/CoordinadorDashboard";

import { ProtectedRoute } from "./ProtectedRoute";
import { RoleRoute }      from "./RoleRoute";

/*
 * AppRoutes
 * --------------------------------------------------------------------------
 * Mapa central de rutas del SPA. Tres layouts:
 *  - PublicLayout: paginas sin login (postulante)
 *  - AuthLayout:   login / recuperar contrasena
 *  - AppLayout:    paginas autenticadas (sidebar + topbar segun rol)
 */
export function AppRoutes() {
  return (
    <Routes>
      {/* Publicas */}
      <Route element={<PublicLayout />}>
        <Route path="/"                  element={<Home />} />
        <Route path="/preinscripcion"    element={<PreinscripcionPaso1 />} />
        <Route path="/preinscripcion/2"  element={<PreinscripcionPaso2 />} />
        <Route path="/preinscripcion/:id/formulario" element={<FormularioGenerado />} />
        <Route path="/preinscripcion/:id/pago"       element={<PagoSimulado />} />
        <Route path="/reimprimir-formulario" element={<ReimprimirFormulario />} />
        <Route path="/iniciar-pago"          element={<IniciarPagoPublico />} />
        <Route path="/resultados"        element={<ResultadosPublicos />} />
      </Route>

      {/* Auth */}
      <Route element={<AuthLayout />}>
        <Route path="/login"            element={<Login />} />
        <Route path="/forgot-password"  element={<ForgotPassword />} />
        <Route path="/reset-password"   element={<ResetPassword />} />
      </Route>

      {/* Autenticadas */}
      <Route element={<ProtectedRoute><AppLayout /></ProtectedRoute>}>
        {/* ADMINISTRADOR */}
        <Route path="/admin"
               element={<RoleRoute roles={["ADMINISTRADOR"]}><AdminDashboard /></RoleRoute>} />
        <Route path="/admin/usuarios"
               element={<RoleRoute roles={["ADMINISTRADOR"]}><UsuariosPage /></RoleRoute>} />
        <Route path="/admin/gestiones"
               element={<RoleRoute roles={["ADMINISTRADOR"]}><GestionesPage /></RoleRoute>} />
        <Route path="/admin/gestiones/nueva"
               element={<RoleRoute roles={["ADMINISTRADOR"]}><GestionFormPage /></RoleRoute>} />
        <Route path="/admin/gestiones/:id"
               element={<RoleRoute roles={["ADMINISTRADOR"]}><GestionFormPage /></RoleRoute>} />
        <Route path="/admin/grupos"
               element={<RoleRoute roles={["ADMINISTRADOR"]}><GruposPage /></RoleRoute>} />
        <Route path="/admin/postulantes"
               element={<RoleRoute roles={["ADMINISTRADOR","ENCARGADO"]}><PostulantesPage /></RoleRoute>} />
        <Route path="/admin/bitacora"
               element={<RoleRoute roles={["ADMINISTRADOR"]}><BitacoraPage /></RoleRoute>} />

        {/* ENCARGADO */}
        <Route path="/encargado"
               element={<RoleRoute roles={["ENCARGADO","ADMINISTRADOR"]}><EncargadoDashboard /></RoleRoute>} />
        <Route path="/encargado/postulantes"
               element={<RoleRoute roles={["ENCARGADO","ADMINISTRADOR"]}><PostulantesPage /></RoleRoute>} />
        <Route path="/encargado/postulantes/:id"
               element={<RoleRoute roles={["ENCARGADO","ADMINISTRADOR"]}><PostulanteDetallePage /></RoleRoute>} />
        <Route path="/encargado/postulantes/:id/boleta"
               element={<RoleRoute roles={["ENCARGADO","ADMINISTRADOR"]}><BoletaPage /></RoleRoute>} />
        <Route path="/encargado/grupos"
               element={<RoleRoute roles={["ENCARGADO","ADMINISTRADOR"]}><GruposPage /></RoleRoute>} />

        {/* DOCENTE / COORDINADOR (Ciclo 2) */}
        <Route path="/docente"     element={<RoleRoute roles={["DOCENTE","ADMINISTRADOR"]}><DocenteDashboard /></RoleRoute>} />
        <Route path="/coordinador" element={<RoleRoute roles={["COORDINADOR","ADMINISTRADOR"]}><CoordinadorDashboard /></RoleRoute>} />

        <Route path="/sin-acceso" element={<div className="p-6">No tienes acceso a esta seccion.</div>} />
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
