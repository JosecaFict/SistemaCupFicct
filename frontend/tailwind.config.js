/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{ts,tsx,js,jsx}"],
  theme: {
    extend: {
      // Paleta institucional Sistema CUP FICCT
      colors: {
        // Azul oscuro institucional
        institutional: {
          50:  "#eef2f7",
          100: "#d7e0ec",
          200: "#aec0d8",
          300: "#7c98b9",
          400: "#4f7395",
          500: "#2d567b",
          600: "#1f4063",
          700: "#193455",   // base
          800: "#142a44",
          900: "#0e1f33",
        },
        // Verde oscuro institucional (estados positivos)
        success: {
          50:  "#eaf4ed",
          100: "#c9e2d0",
          200: "#9bc9a8",
          300: "#6cad7e",
          400: "#43935b",
          500: "#1f7a3f",
          600: "#176633",   // base
          700: "#125229",
          800: "#0e3f20",
          900: "#0a3018",
        },
        warning: {
          500: "#b76e00",
          600: "#955700",
        },
        danger: {
          500: "#b91c1c",
          600: "#991b1b",
        },
        muted: {
          50:  "#f7f8fa",
          100: "#eef0f4",
          200: "#dde1e8",
          300: "#c2c8d2",
          400: "#9aa1ad",
          500: "#6e7682",
          600: "#4d5460",
          700: "#363b46",
        },
      },
      fontFamily: {
        sans: ['"Inter"', "system-ui", "Segoe UI", "Roboto", "sans-serif"],
      },
      boxShadow: {
        card: "0 1px 2px rgba(15,23,42,0.06), 0 1px 3px rgba(15,23,42,0.10)",
      },
    },
  },
  plugins: [],
};
