<?php

// ==========================================
// PLANTILLAS DE RUBRO PARA CATEGORÍAS
// ==========================================
// Conjuntos predefinidos de categorías con sus reglas de negocio
// (clasificación ABC, tipo de manejo y stocks por defecto) para
// cargar el sistema de una sola vez, sin crear una por una.
//
// Estructura: cada plantilla tiene un nombre, una descripción y una
// lista de categorías. Para agregar una plantilla nueva basta con
// añadir otro elemento a este arreglo (no hace falta tocar código).
//
// Campos de cada categoría:
//   - nombre:      nombre de la categoría (se guarda en mayúsculas).
//   - descripcion: descripción corta opcional.
//   - abc:         clasificación ABC: 'A', 'B', 'C' o '' (sin clasificar).
//   - manejo:      normal | inflamable | liquido | peligroso | voluminoso | aerosol
//   - stock_min:   stock mínimo por defecto para productos de la categoría.
//   - stock_max:   capacidad máxima por defecto (0 = heredar).

return [
    'automotriz' => [
        'nombre' => 'Rubro Automotriz General',
        'descripcion' => 'Tienda de repuestos y accesorios: autos, motos, otros vehículos y productos varios.',
        'categorias' => [
            ['nombre' => 'ACEITES DE MOTOR', 'descripcion' => 'Aceites lubricantes para motores de gasolina y diésel.', 'abc' => 'A', 'manejo' => 'liquido', 'stock_min' => 10, 'stock_max' => 150],
            ['nombre' => 'ACEITES PARA MOTOS', 'descripcion' => 'Lubricantes especializados para motocicletas.', 'abc' => 'A', 'manejo' => 'normal', 'stock_min' => 8, 'stock_max' => 100],
            ['nombre' => 'ACEITES DE TRANSMISION', 'descripcion' => 'Fluidos para cajas automáticas, manuales y diferenciales.', 'abc' => 'B', 'manejo' => 'liquido', 'stock_min' => 6, 'stock_max' => 80],
            ['nombre' => 'LÍQUIDO DE FRENOS', 'descripcion' => 'Fluidos de frenos DOT3, DOT4 y DOT5.', 'abc' => 'B', 'manejo' => 'liquido', 'stock_min' => 6, 'stock_max' => 80],
            ['nombre' => 'REFRIGERANTES', 'descripcion' => 'Refrigerantes, anticongelantes y agua destilada.', 'abc' => 'B', 'manejo' => 'liquido', 'stock_min' => 6, 'stock_max' => 80],
            ['nombre' => 'ADITIVOS', 'descripcion' => 'Aditivos para motor, combustible, transmisión y dirección.', 'abc' => 'C', 'manejo' => 'liquido', 'stock_min' => 4, 'stock_max' => 60],
            ['nombre' => 'GRASAS Y LUBRICANTES', 'descripcion' => 'Grasas multiuso, lubricantes en spray y desengrasantes.', 'abc' => 'C', 'manejo' => 'normal', 'stock_min' => 4, 'stock_max' => 60],
            ['nombre' => 'FILTROS', 'descripcion' => 'Filtros de aceite, aire, gasolina y cabina.', 'abc' => 'B', 'manejo' => 'normal', 'stock_min' => 10, 'stock_max' => 120],
            ['nombre' => 'FRENOS', 'descripcion' => 'Pastillas, discos, tambores y componentes de freno.', 'abc' => 'B', 'manejo' => 'normal', 'stock_min' => 8, 'stock_max' => 100],
            ['nombre' => 'SUSPENSIÓN Y DIRECCIÓN', 'descripcion' => 'Amortiguadores, bujes, terminales y rótulas.', 'abc' => 'C', 'manejo' => 'voluminoso', 'stock_min' => 4, 'stock_max' => 50],
            ['nombre' => 'MOTOR Y SUS PARTES', 'descripcion' => 'Correas, cadenas, empacaduras y repuestos de motor.', 'abc' => 'C', 'manejo' => 'normal', 'stock_min' => 4, 'stock_max' => 50],
            ['nombre' => 'SISTEMA ELÉCTRICO', 'descripcion' => 'Baterías, bujías, alternadores, arranques y luces.', 'abc' => 'B', 'manejo' => 'normal', 'stock_min' => 6, 'stock_max' => 80],
            ['nombre' => 'LLANTAS Y RINES', 'descripcion' => 'Neumáticos, cauchos, rines y válvulas.', 'abc' => 'C', 'manejo' => 'voluminoso', 'stock_min' => 8, 'stock_max' => 100],
            ['nombre' => 'ACCESORIOS Y AUTOPARTES', 'descripcion' => 'Espejos, tapices, limpiadores de parabrisas y accesorios varios.', 'abc' => 'C', 'manejo' => 'normal', 'stock_min' => 5, 'stock_max' => 80],
            ['nombre' => 'LIMPIEZA Y CUIDADO', 'descripcion' => 'Shampoo, ceras, siliconas, aerosoles y aromatizantes.', 'abc' => 'C', 'manejo' => 'inflamable', 'stock_min' => 5, 'stock_max' => 80],
            ['nombre' => 'HERRAMIENTAS Y EQUIPOS', 'descripcion' => 'Herramientas manuales, eléctricas y equipos de taller.', 'abc' => 'C', 'manejo' => 'normal', 'stock_min' => 3, 'stock_max' => 40],
        ],
    ],
];