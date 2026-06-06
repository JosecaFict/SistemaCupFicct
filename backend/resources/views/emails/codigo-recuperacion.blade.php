<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Codigo de recuperacion</title>
</head>
<body style="margin:0; padding:24px; background:#f4f5f7; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="max-width:480px; margin:0 auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:10px; padding:32px;">
        <div style="font-size:12px; letter-spacing:2px; text-transform:uppercase; color:#64748b;">FICCT</div>
        <h2 style="margin:4px 0 16px; color:#1e3a5f;">Recuperacion de contrasena</h2>

        <p style="margin:0 0 12px; font-size:14px; line-height:1.5;">
            Recibimos una solicitud para restablecer la contrasena de tu cuenta en el
            <b>Sistema CUP FICCT</b>. Usa el siguiente codigo de verificacion:
        </p>

        <div style="font-size:34px; font-weight:700; letter-spacing:10px; text-align:center;
                    background:#f0f4f8; color:#1e3a5f; padding:18px; border-radius:8px; margin:20px 0;">
            {{ $codigo }}
        </div>

        <p style="margin:0 0 12px; font-size:14px; line-height:1.5;">
            Ingresa este codigo en la pantalla de recuperacion para definir tu nueva contrasena.
        </p>

        <p style="margin:16px 0 0; font-size:12px; color:#6b7280; line-height:1.5;">
            El codigo vence en {{ $minutos }} minutos. Si no solicitaste este cambio,
            ignora este correo: tu contrasena seguira siendo la misma.
        </p>
    </div>
</body>
</html>
