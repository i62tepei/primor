<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckVentasHorasCommand extends Command
{
    protected $signature = 'ventas:check';
    protected $description = 'Verifica incidencias en ventas por hora comparando con store_id = 1';

    public function handle()
    {
        $csvPath = storage_path('app/datos.csv');
        
        if (!file_exists($csvPath)) {
            $this->error('Archivo datos.csv no encontrado');
            return;
        }

        $data = array_map('str_getcsv', file($csvPath));
        $headers = array_map('trim', explode(';', $data[0][0]));
        unset($data[0]);

        $registros = [];

        foreach ($data as $row) {
            $values = array_map('trim', explode(';', $row[0]));
            $registro = array_combine($headers, $values);

            $registro['hora'] = (int) $registro['hora'];
            $registro['pedidos_minimos'] = (float) $registro['pedidos_minimos'];
            $registro['store_id'] = (int) $registro['store_id'];
            $registro['app'] = (int) $registro['app'];

            $registros[] = $registro;
        }

        // Agrupamos referencias para store_id = 1
        $referencias = collect($registros)
            ->where('store_id', 1)
            ->groupBy(fn($item) => $item['hora'] . '_' . $item['app']);

        // Leemos equivalencias desde store_website
        $tiendas = DB::table('store_website')
            ->pluck('code', 'website_id')
            ->toArray();

        $valores = [];

        foreach ($registros as $registro) {
            if ($registro['store_id'] === 1) continue;

            $claveRef = $registro['hora'] . '_' . $registro['app'];

            $refRegistro = $referencias[$claveRef]->first();
            $ref = $refRegistro['pedidos_minimos'] ?? null;

            if (
                $ref !== null
                && $registro['pedidos_minimos'] <= $ref / 2
            ) {
                $nombreTienda = $tiendas[$registro['store_id']] ?? 'store ' . $registro['store_id'];
                $valorFormateado = rtrim(rtrim(number_format($registro['pedidos_minimos'], 2, '.', ''), '0'), '.');
                $valores[] = "store $nombreTienda, app={$registro['app']}, valor = $valorFormateado";
            }
        }

        $mensaje = 'WARNING - Incidencia ventas horas';
        if (!empty($valores)) {
            $mensaje .= ' | ' . implode(' | ', $valores);
        }

        $this->line($mensaje);
        return 1; // WARNING
    }
}
