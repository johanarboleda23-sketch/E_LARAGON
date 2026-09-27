<?php

return [
    // UVT 2026. Keep this value configurable because DIAN updates it annually.
    'uvt' => (float) env('COLOMBIA_UVT_2026', 52374),

    'concepts' => [
        'none' => [
            'label' => 'No aplicar retención',
            'rate' => 0,
            'base_uvt' => 0,
            'base_on' => 'subtotal',
        ],
        'purchase_declarante' => [
            'label' => 'Compras - proveedor declarante (2,5%)',
            'rate' => 0.025,
            'base_uvt' => 27,
            'base_on' => 'subtotal',
        ],
        'purchase_no_declarante' => [
            'label' => 'Compras - proveedor no declarante (3,5%)',
            'rate' => 0.035,
            'base_uvt' => 27,
            'base_on' => 'subtotal',
        ],
        'service_declarante' => [
            'label' => 'Servicios - proveedor declarante (4%)',
            'rate' => 0.04,
            'base_uvt' => 4,
            'base_on' => 'subtotal',
        ],
        'service_no_declarante' => [
            'label' => 'Servicios - proveedor no declarante (6%)',
            'rate' => 0.06,
            'base_uvt' => 4,
            'base_on' => 'subtotal',
        ],
        'professional_fee_10' => [
            'label' => 'Honorarios o comisiones (10%)',
            'rate' => 0.10,
            'base_uvt' => 0,
            'base_on' => 'subtotal',
        ],
        'professional_fee_11' => [
            'label' => 'Honorarios o comisiones (11%)',
            'rate' => 0.11,
            'base_uvt' => 0,
            'base_on' => 'subtotal',
        ],
        'rent_immovable' => [
            'label' => 'Arrendamiento de inmueble (3,5%)',
            'rate' => 0.035,
            'base_uvt' => 27,
            'base_on' => 'subtotal',
        ],
        'rent_movable' => [
            'label' => 'Arrendamiento de bien mueble (4%)',
            'rate' => 0.04,
            'base_uvt' => 27,
            'base_on' => 'subtotal',
        ],
        'vat' => [
            'label' => 'Retención de IVA (15% del IVA)',
            'rate' => 0.15,
            'base_uvt' => 0,
            'base_on' => 'iva',
        ],
    ],
];
