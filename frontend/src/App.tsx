import { BrowserRouter } from "react-router-dom";
import { AuthProvider } from "./context/AuthContext";
import { ToastProvider } from "./hooks/useToast";
import { AppRoutes } from "./routes/AppRoutes";

/*
 * App
 * --------------------------------------------------------------------------
 * Solo orquesta los providers globales y monta el router. Ninguna logica
 * de negocio vive aqui (queda en services y pages).
 */
export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <ToastProvider>
          <AppRoutes />
        </ToastProvider>
      </AuthProvider>
    </BrowserRouter>
  );
}
