<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de Verificación</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" style="width: 100%; max-width: 500px; border-collapse: collapse; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 20px; text-align: center; background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 16px 16px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">
                                Secretaría Académica FII
                            </h1>
                            <p style="margin: 8px 0 0; color: rgba(255,255,255,0.8); font-size: 12px; text-transform: uppercase; letter-spacing: 2px;">
                                Facultad de Ingeniería Industrial - UNP
                            </p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px; color: #334155; font-size: 16px; line-height: 1.6;">
                                Hola <strong>{{ $userName }}</strong>,
                            </p>

                            <p style="margin: 0 0 30px; color: #64748b; font-size: 14px; line-height: 1.6;">
                                Has solicitado un código de verificación para acceder al Sistema de la Secretaría Académica de la Facultad de Ingeniería Industrial.
                                Usa el siguiente código:
                            </p>

                            <!-- OTP Code Box -->
                            <div style="background-color: #f1f5f9; border-radius: 12px; padding: 30px; text-align: center; margin-bottom: 30px;">
                                <p style="margin: 0 0 10px; color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 2px; font-weight: 600;">
                                    Tu código de verificación
                                </p>
                                <p style="margin: 0; font-size: 42px; font-weight: 800; letter-spacing: 12px; color: #4f46e5; font-family: 'Courier New', monospace;">
                                    {{ $code }}
                                </p>
                            </div>

                            <!-- Expiration Warning -->
                            <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 0 8px 8px 0; margin-bottom: 30px;">
                                <p style="margin: 0; color: #92400e; font-size: 13px; font-weight: 600;">
                                    ⏱️ Este código expira en {{ $expirationMinutes }} minutos
                                </p>
                            </div>

                            <p style="margin: 0; color: #64748b; font-size: 13px; line-height: 1.6;">
                                Si no solicitaste este código, puedes ignorar este correo.
                                Tu cuenta está segura.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #f8fafc; border-radius: 0 0 16px 16px; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0 0 8px; color: #94a3b8; font-size: 11px; text-align: center; text-transform: uppercase; letter-spacing: 1px;">
                                Facultad de Ingeniería Industrial - Universidad Nacional de Piura
                            </p>
                            <p style="margin: 0; color: #cbd5e1; font-size: 10px; text-align: center;">
                                Este es un correo automático. Por favor no responder.
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Security Notice -->
                <table role="presentation" style="width: 100%; max-width: 500px; margin-top: 20px;">
                    <tr>
                        <td style="text-align: center;">
                            <p style="margin: 0; color: #94a3b8; font-size: 11px;">
                                🔒 Nunca compartas este código con nadie. El personal de la FII-UNP nunca te pedirá tu código.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
