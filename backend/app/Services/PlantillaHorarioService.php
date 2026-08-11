<?php

namespace App\Services;

class PlantillaHorarioService
{
    /**
     * Filas de la Plantilla Horaria institucional (grupos G1-G14).
     * Cada fila cubre un par de grupos (impar/par): el bloque completo
     * inicio-fin es el horario de los días "a" y "b"; "mitad" es el punto
     * donde se reparte el bloque de Miércoles ("h") entre ambos grupos.
     */
    private const FILAS = [
        1 => ['inicio' => '07:00', 'mitad' => '07:50', 'fin' => '08:40'],
        2 => ['inicio' => '08:50', 'mitad' => '09:40', 'fin' => '10:30'],
        3 => ['inicio' => '10:40', 'mitad' => '11:30', 'fin' => '12:20'],
        4 => ['inicio' => '13:50', 'mitad' => '14:40', 'fin' => '15:30'],
        5 => ['inicio' => '15:40', 'mitad' => '16:30', 'fin' => '17:20'],
        6 => ['inicio' => '17:30', 'mitad' => '18:20', 'fin' => '19:10'],
        7 => ['inicio' => '19:20', 'mitad' => '20:10', 'fin' => '21:00'],
    ];

    /**
     * A partir de un código de grupo tipo "G1", "G1A", "G1AB", "G7ABH", "G14BH"...
     * genera las filas de horario (día + hora) que le corresponden según
     * la Plantilla Horaria institucional.
     *
     * @return array<int, array{dia_semana: string, hora_inicio: string, hora_fin: string}>
     */
    public function generarDetalles(string $codigoGrupo): array
    {
        if (!preg_match('/^G(\d+)([A-Z]*)$/i', trim($codigoGrupo), $m)) {
            return [];
        }

        $numero = (int) $m[1];
        $letras = strtolower($m[2]);
        if ($numero < 1 || $letras === '') {
            return [];
        }

        $fila = (int) ceil($numero / 2);
        if (!isset(self::FILAS[$fila])) {
            return [];
        }

        $esImpar = $numero % 2 === 1;
        $inicio = self::FILAS[$fila]['inicio'];
        $mitad  = self::FILAS[$fila]['mitad'];
        $fin    = self::FILAS[$fila]['fin'];

        $detalles = [];
        $diasUsados = [];
        foreach (array_unique(str_split($letras)) as $letra) {
            $detalle = match ($letra) {
                'a' => ['dia_semana' => $esImpar ? 'lunes' : 'jueves', 'hora_inicio' => $inicio, 'hora_fin' => $fin],
                'b' => ['dia_semana' => $esImpar ? 'martes' : 'viernes', 'hora_inicio' => $inicio, 'hora_fin' => $fin],
                'h' => ['dia_semana' => 'miercoles', 'hora_inicio' => $esImpar ? $inicio : $mitad, 'hora_fin' => $esImpar ? $mitad : $fin],
                default => null,
            };

            if ($detalle && !in_array($detalle['dia_semana'], $diasUsados, true)) {
                $detalles[] = $detalle;
                $diasUsados[] = $detalle['dia_semana'];
            }
        }

        return $detalles;
    }
}
