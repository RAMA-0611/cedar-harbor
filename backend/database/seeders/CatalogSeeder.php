<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Synthetic MVP catalog adapted from the E2 prototype dataset.
     * Data is embedded here so production runtime never reads web/ JSON.
     */
    public function run(): void
    {
        $taxRate = '0.1900';

        $categoryDefs = [
            ['name' => 'Rodamientos', 'slug' => 'rodamientos'],
            ['name' => 'Control y maniobra', 'slug' => 'control-y-maniobra'],
            ['name' => 'Cables y conductores', 'slug' => 'cables-y-conductores'],
            ['name' => 'Protección eléctrica', 'slug' => 'proteccion-electrica'],
            ['name' => 'Conexión', 'slug' => 'conexion'],
            ['name' => 'Transformadores', 'slug' => 'transformadores'],
            ['name' => 'Motores', 'slug' => 'motores'],
        ];

        $categories = [];
        foreach ($categoryDefs as $def) {
            $categories[$def['name']] = Category::query()->create([
                'name' => $def['name'],
                'slug' => $def['slug'],
                'description' => 'Categoría sintética del catálogo local MVP.',
                'is_active' => true,
            ]);
        }

        $products = [
            ['id' => '7c1e4b2a-5d3f-4a8e-9b61-2f0d3c4e5a01', 'sku' => 'PR-ROD-6205', 'name' => 'Rodamiento rígido de bolas 6205-2RS', 'category' => 'Rodamientos', 'brand' => 'Genérica', 'condition' => 'Nuevo', 'price' => '18500.00', 'stock' => 48, 'unit' => 'unidad', 'description' => 'Rodamiento de una hilera con doble sello de caucho, apto para motores pequeños, bombas y ventiladores.'],
            ['id' => '7c1e4b2a-5d3f-4a8e-9b61-2f0d3c4e5a02', 'sku' => 'PR-CTL-C40A', 'name' => 'Contactor trifásico 40 A, bobina 220 V', 'category' => 'Control y maniobra', 'brand' => 'Línea Rotor', 'condition' => 'Nuevo', 'price' => '186000.00', 'stock' => 12, 'unit' => 'unidad', 'description' => 'Contactor de tres polos para arranque directo de motores.'],
            ['id' => '7c1e4b2a-5d3f-4a8e-9b61-2f0d3c4e5a03', 'sku' => 'PR-CAB-3X10', 'name' => 'Cable de potencia 3x10 AWG THHN', 'category' => 'Cables y conductores', 'brand' => 'Genérica', 'condition' => 'Nuevo', 'price' => '21900.00', 'stock' => 350, 'unit' => 'metro', 'description' => 'Cable multiconductor de cobre con aislamiento termoplástico para 600 V.'],
            ['id' => '7c1e4b2a-5d3f-4a8e-9b61-2f0d3c4e5a04', 'sku' => 'PR-PRT-TM3P63', 'name' => 'Interruptor termomagnético 3P 63 A', 'category' => 'Protección eléctrica', 'brand' => 'Línea Rotor', 'condition' => 'Nuevo', 'price' => '142000.00', 'stock' => 3, 'unit' => 'unidad', 'description' => 'Interruptor automático tripolar con curva C y capacidad de corte de 6 kA.'],
            ['id' => '7c1e4b2a-5d3f-4a8e-9b61-2f0d3c4e5a05', 'sku' => 'PR-PRT-RT2332', 'name' => 'Relé térmico industrial 23–32 A', 'category' => 'Protección eléctrica', 'brand' => 'Genérica', 'condition' => 'Nuevo', 'price' => '97500.00', 'stock' => 0, 'unit' => 'unidad', 'description' => 'Relé de sobrecarga con ajuste de corriente.'],
            ['id' => '7c1e4b2a-5d3f-4a8e-9b61-2f0d3c4e5a06', 'sku' => 'PR-CNX-T35', 'name' => 'Terminal de cobre para potencia 35 mm²', 'category' => 'Conexión', 'brand' => 'Genérica', 'condition' => 'Nuevo', 'price' => '38000.00', 'stock' => 25, 'unit' => 'paquete de 10 piezas', 'description' => 'Terminal tubular de cobre estañado con ojo para tornillo M8.'],
            ['id' => '7c1e4b2a-5d3f-4a8e-9b61-2f0d3c4e5a07', 'sku' => 'PR-PRT-GM0914', 'name' => 'Guardamotor trifásico 9–14 A', 'category' => 'Protección eléctrica', 'brand' => 'Línea Rotor', 'condition' => 'Nuevo', 'price' => '168000.00', 'stock' => 7, 'unit' => 'unidad', 'description' => 'Disyuntor de protección de motor con disparo magnético y térmico regulable.'],
            ['id' => '7c1e4b2a-5d3f-4a8e-9b61-2f0d3c4e5a08', 'sku' => 'PR-TRF-C250', 'name' => 'Transformador de control 220/110 V, 250 VA', 'category' => 'Transformadores', 'brand' => 'Genérica', 'condition' => 'Nuevo', 'price' => '215000.00', 'stock' => 4, 'unit' => 'unidad', 'description' => 'Transformador monofásico de aislamiento para circuitos de mando.'],
            ['id' => '7c1e4b2a-5d3f-4a8e-9b61-2f0d3c4e5a09', 'sku' => 'PR-MOT-2HP18', 'name' => 'Motor eléctrico trifásico 2 HP, 1800 rpm', 'category' => 'Motores', 'brand' => 'Línea Rotor', 'condition' => 'Reacondicionado', 'price' => '1290000.00', 'stock' => 2, 'unit' => 'unidad', 'description' => 'Motor de inducción de cuatro polos, carcasa de hierro fundido.'],
            ['id' => '7c1e4b2a-5d3f-4a8e-9b61-2f0d3c4e5a0a', 'sku' => 'PR-CTL-VF15', 'name' => 'Variador de frecuencia 1,5 kW, 220 V', 'category' => 'Control y maniobra', 'brand' => 'Línea Rotor', 'condition' => 'Nuevo', 'price' => '980000.00', 'stock' => 5, 'unit' => 'unidad', 'description' => 'Variador para motores trifásicos con entrada monofásica.'],
            ['id' => '7c1e4b2a-5d3f-4a8e-9b61-2f0d3c4e5a0b', 'sku' => 'PR-CTL-PD22', 'name' => 'Pulsador doble con piloto 22 mm', 'category' => 'Control y maniobra', 'brand' => 'Genérica', 'condition' => 'Nuevo', 'price' => '24500.00', 'stock' => 60, 'unit' => 'unidad', 'description' => 'Pulsador de marcha y parada en un solo cuerpo con lámpara LED.'],
            ['id' => '7c1e4b2a-5d3f-4a8e-9b61-2f0d3c4e5a0c', 'sku' => 'PR-ROD-30205', 'name' => 'Rodamiento de rodillos cónicos 30205', 'category' => 'Rodamientos', 'brand' => 'Genérica', 'condition' => 'Nuevo', 'price' => '46000.00', 'stock' => 15, 'unit' => 'unidad', 'description' => 'Rodamiento para cargas combinadas radiales y axiales.'],
        ];

        foreach ($products as $row) {
            Product::query()->create([
                'id' => $row['id'],
                'category_id' => $categories[$row['category']]->id,
                'sku' => $row['sku'],
                'name' => $row['name'],
                'description' => $row['description'],
                'brand' => $row['brand'],
                'condition' => $row['condition'],
                'unit' => $row['unit'],
                'price' => $row['price'],
                'currency' => 'COP',
                'tax_rate' => $taxRate,
                'stock_quantity' => $row['stock'],
                'is_active' => true,
            ]);
        }

        Customer::query()->create([
            'name' => 'Cliente Sintético Demo',
            'email' => 'cliente.sintetico@example.test',
            'phone' => '+57-300-000-0000',
            'document_type' => 'CC',
            'document_number' => '1000000000',
        ]);
    }
}

