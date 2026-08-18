<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #1f2937; }
        .header { text-align: center; border-bottom: 2px solid #1f2937; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { font-size: 15px; margin: 0 0 4px; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 11px; }
        .titulo { text-align: center; font-size: 14px; font-weight: bold; text-transform: uppercase; margin: 20px 0; }
        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.datos td { padding: 5px 4px; vertical-align: top; }
        table.datos td.label { width: 160px; font-weight: bold; color: #374151; }
        .motivo-box { border: 1px solid #d1d5db; padding: 10px; margin-bottom: 20px; min-height: 50px; }
        .aviso { font-size: 10px; color: #4b5563; font-style: italic; margin-bottom: 30px; }
        .firma { text-align: center; margin-top: 40px; }
        .firma img { height: 60px; }
        .firma .linea { border-top: 1px solid #1f2937; width: 220px; margin: 4px auto 0; padding-top: 4px; }
        .footer { margin-top: 30px; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $config['universidad'] ?? '' }}</h1>
        <p>{{ $config['facultad'] ?? '' }}</p>
        <p>{{ $config['dependencia'] ?? '' }}</p>
    </div>

    <div class="titulo">Constancia de Presentación de Solicitud</div>

    <table class="datos">
        <tr>
            <td class="label">N&deg; de constancia:</td>
            <td>{{ $solicitud->id }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de presentación:</td>
            <td>{{ $fechaPresentacion }}</td>
        </tr>
        <tr>
            <td class="label">Tipo de solicitud:</td>
            <td>{{ $solicitud->tipoSolicitud->nombre ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Alumno:</td>
            <td>{{ $solicitud->user->name ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Código universitario:</td>
            <td>{{ $solicitud->user->codigo_universitario ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Escuela profesional:</td>
            <td>{{ $solicitud->user->escuela?->nombre_corto ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Curso:</td>
            <td>
                {{ $solicitud->programacion?->curso?->codigo ?? '' }}
                — {{ $solicitud->programacion?->curso?->nombre ?? '' }}
                @if($solicitud->programacion?->seccion)
                    (Sección {{ $solicitud->programacion->seccion }})
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Periodo académico:</td>
            <td>{{ $solicitud->periodo->nombre ?? '' }}</td>
        </tr>
    </table>

    <table class="datos">
        <tr>
            <td class="label" style="width:auto;">Motivo / Sustento:</td>
        </tr>
    </table>
    <div class="motivo-box">{{ $solicitud->motivo }}</div>

    <p class="aviso">
        Es responsabilidad exclusiva del alumno la veracidad de la información declarada en esta solicitud.
        Este documento es una constancia de recepción y no implica la aprobación del trámite; el resultado
        se comunicará por este mismo medio una vez evaluado por la Secretaría Académica.
    </p>

    <div class="firma">
        @if($firmaBase64)
            <img src="data:image/png;base64,{{ $firmaBase64 }}" alt="Firma digital">
        @endif
        <div class="linea">Firma del alumno</div>
    </div>

    <div class="footer">
        {{ $config['ciudad'] ?? '' }} — Documento generado automáticamente por el Sistema de Secretaría Académica.
    </div>
</body>
</html>
