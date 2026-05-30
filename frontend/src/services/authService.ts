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
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
  }): Promise<{ message: string }> {
    return withCsrf(async () => {
      const { data } = await api.post("/api/auth/reset-password", payload);
      return data;
    });
  },
};
