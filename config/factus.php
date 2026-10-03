<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Endpoints base de Factus
    |--------------------------------------------------------------------------
    */
    'base_urls' => [
        'sandbox' => 'https://api-sandbox.factus.com.co',
        'production' => 'https://api.factus.com.co',
    ],

    /*
    |--------------------------------------------------------------------------
    | Valores por defecto para el adquiriente
    |--------------------------------------------------------------------------
    |
    | Usados cuando el tercero/cliente de la venta no trae un dato específico
    | requerido por la DIAN. Se deben ajustar según la operación real.
    |
    */
    'defaults' => [
        'identification_document_code' => '13', // Cédula de ciudadanía
        'nit_identification_document_code' => '31', // NIT
        'legal_organization_code' => '2', // Persona natural
        'nit_legal_organization_code' => '1', // Persona jurídica
        'tribute_code' => 'ZZ', // No aplica
        'municipality_code' => '11001', // Bogotá D.C.
        'country_code' => 'CO',
        'document' => '01', // Factura electrónica de venta
        'operation_type' => '10',
        'payment_form' => '1', // Contado
        'payment_method_code' => '10', // Efectivo
        'unit_measure_code' => '94', // Unidad
        'standard_code' => '999', // Sin clasificación estándar
        'tax_code' => '01', // IVA
        'payroll_payment_method_code' => '42', // Transferencia / consignación
        'payroll_period_code' => '5', // Mensual
        'worker_type_code' => '01', // Empleado
        'worker_subtype_code' => '00', // Normal
        'contract_type_code' => '1', // Término indefinido
    ],
];
