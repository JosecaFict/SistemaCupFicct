import { api, withCsrf } from "./api";
import type { User } from "../types";

/*
 * authService -- envoltorio para los endpoints /api/auth/*
 * Maneja login/logout/me y recuperacion de contrasena.
 */
export const authService = {
  async login(email: string, password: string): Promise<User> {
    return withCsrf(async () => {
      const { data } = await api.post("/api/auth/login", { email, password });
      return data.user as User;
    });
  },

  async logout(): Promise<void> {
    return withCsrf(async () => {
      await api.post("/api/auth/logout");
    });
  },

  async me(): Promise<User | null> {
    try {
      const { data } = await api.get("/api/auth/me");
      return data.user as User;
    } catch {
      return null;
    }
  },

  async forgotPassword(email: string): Promise<{ message: string }> {
    return withCsrf(async () => {
      const { data } = await api.post("/api/auth/forgot-password", { email });
      return data;
    });
  },

  async resetPassword(payload: {
    email: string;
    codigo: string;
    password: string;
    password_confirmation: string;
  }): Promise<{ message: string }> {
    return withCsrf(async () => {
      const { data } = await api.post("/api/auth/reset-password", payload);
      return data;
    });
  },

  /** Actualiza los datos personales del usuario autenticado. */
  async actualizarPerfil(payload: {
    nombre: string;
    apellidos: string;
    email: string;
  }): Promise<User> {
    return withCsrf(async () => {
      const { data } = await api.put("/api/auth/perfil", payload);
      return data.user as User;
    });
  },

  /** Cambia la contrasena del usuario autenticado (pide la actual). */
  async cambiarPassword(payload: {
    current_password: string;
    password: string;
    password_confirmation: string;
  }): Promise<{ message: string }> {
    return withCsrf(async () => {
      const { data } = await api.put("/api/auth/password", payload);
      return data;
    });
  },
};
