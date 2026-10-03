<?php

return [
    // UVT 2026. Keep this value configurable because DIAN updates it annually.
    'uvt' => (float) env('COLOMBIA_UVT_2026', 52374),

    // Bases y tarifas del Decreto 572 de 2025, reactivado desde el 1 de julio de 2026
    // (Auto Consejo de Estado, Sección Cuarta, 2 de junio de 2026, expediente 30229).
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
            'base_uvt' => 10,
            'base_on' => 'subtotal',
        ],
        'purchase_no_declarante' => [
            'label' => 'Compras - proveedor no declarante (3,5%)',
            'rate' => 0.035,
            'base_uvt' => 10,
            'base_on' => 'subtotal',
        ],
        'service_declarante' => [
            'label' => 'Servicios - proveedor declarante (4%)',
            'rate' => 0.04,
            'base_uvt' => 2,
            'base_on' => 'subtotal',
        ],
        'service_no_declarante' => [
            'label' => 'Servicios - proveedor no declarante (6%)',
            'rate' => 0.06,
            'base_uvt' => 2,
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
            'base_uvt' => 10,
            'base_on' => 'subtotal',
        ],
        'rent_movable' => [
            'label' => 'Arrendamiento de bien mueble (4%)',
            'rate' => 0.04,
            'base_uvt' => 0,
            'base_on' => 'subtotal',
        ],
        'agricultural_no_processing' => [
            'label' => 'Compra de productos agropecuarios sin procesamiento industrial (1,5%)',
            'rate' => 0.015,
            'base_uvt' => 70,
            'base_on' => 'subtotal',
        ],
        'coffee_parchment' => [
            'label' => 'Compra de café pergamino o cereza (0,5%)',
            'rate' => 0.005,
            'base_uvt' => 70,
            'base_on' => 'subtotal',
        ],
        'fuel' => [
            'label' => 'Compra de combustibles derivados del petróleo (0,1%)',
            'rate' => 0.001,
            'base_uvt' => 0,
            'base_on' => 'subtotal',
        ],
        'fixed_assets_individual' => [
            'label' => 'Enajenación de activos fijos de personas naturales (1%)',
            'rate' => 0.01,
            'base_uvt' => 10,
            'base_on' => 'subtotal',
        ],
        'vehicle_purchase' => [
            'label' => 'Compra de vehículos (1%)',
            'rate' => 0.01,
            'base_uvt' => 10,
            'base_on' => 'subtotal',
        ],
        'gold_trading_company' => [
            'label' => 'Compra de oro por sociedades de comercialización internacional (2,5%)',
            'rate' => 0.025,
            'base_uvt' => 0,
            'base_on' => 'subtotal',
        ],
        'real_estate_housing' => [
            'label' => 'Bienes raíces para vivienda de habitación, hasta 10.000 UVT (1%)',
            'rate' => 0.01,
            'base_uvt' => 10000,
            'base_on' => 'subtotal',
        ],
        'real_estate_non_housing' => [
            'label' => 'Bienes raíces para uso distinto a vivienda (2,5%)',
            'rate' => 0.025,
            'base_uvt' => 10,
            'base_on' => 'subtotal',
        ],
        'ecclesiastical_declarante' => [
            'label' => 'Emolumentos eclesiásticos - declarante (4%)',
            'rate' => 0.04,
            'base_uvt' => 10,
            'base_on' => 'subtotal',
        ],
        'ecclesiastical_no_declarante' => [
            'label' => 'Emolumentos eclesiásticos - no declarante (3,5%)',
            'rate' => 0.035,
            'base_uvt' => 10,
            'base_on' => 'subtotal',
        ],
        'cargo_transport' => [
            'label' => 'Transporte de carga terrestre (1%)',
            'rate' => 0.01,
            'base_uvt' => 2,
            'base_on' => 'subtotal',
        ],
        'passenger_transport' => [
            'label' => 'Transporte de pasajeros vía terrestre (3,5%)',
            'rate' => 0.035,
            'base_uvt' => 10,
            'base_on' => 'subtotal',
        ],
        'temporary_services' => [
            'label' => 'Servicios temporales de empleo, sobre AIU (1%)',
            'rate' => 0.01,
            'base_uvt' => 2,
            'base_on' => 'subtotal',
        ],
        'security_cleaning' => [
            'label' => 'Servicios de vigilancia y aseo, sobre AIU (2%)',
            'rate' => 0.02,
            'base_uvt' => 2,
            'base_on' => 'subtotal',
        ],
        'hotel_restaurant' => [
            'label' => 'Hoteles, restaurantes y hospedajes (3,5%)',
            'rate' => 0.035,
            'base_uvt' => 2,
            'base_on' => 'subtotal',
        ],
        'lottery' => [
            'label' => 'Loterías, rifas y apuestas (20%)',
            'rate' => 0.20,
            'base_uvt' => 48,
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
