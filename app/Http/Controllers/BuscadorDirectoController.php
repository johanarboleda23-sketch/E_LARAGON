<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuscadorDirectoController extends Controller
{
    public function redirigirComprobante(Request $request)
    {
        $consecutivo = trim($request->get('consecutivo'));
        $tipo = $request->get('tipo');

        if (!$consecutivo || !$tipo) {
            return response()->json(['success' => false, 'message' => 'Faltan parámetros requeridos.'], 400);
        }

        $idDocumento = null;
        $prefijoRuta = '';

        // Mapeo lógico según la base de datos de SU+ GESTIÓN
        switch ($tipo) {
            case 'venta_factura':
                $idDocumento = DB::table('facturas_ventas')->where('consecutivo', $consecutivo)->value('id');
                $prefijoRuta = '/ventas/facturas/ver/';
                break;

            case 'compra_factura':
                $idDocumento = DB::table('facturas_compras')->where('consecutivo', $consecutivo)->value('id');
                $prefijoRuta = '/compras/facturas/ver/';
                break;

            case 'documento_soporte':
                $idDocumento = DB::table('documentos_soporte')->where('consecutivo', $consecutivo)->value('id');
                $prefijoRuta = '/compras/documento-soporte/ver/';
                break;

            case 'nomina_periodo':
                $idDocumento = DB::table('nominas_periodos')->where('consecutivo', $consecutivo)->value('id');
                $prefijoRuta = '/nomina/periodos/ver/';
                break;

            case 'comprobante_contable':
                $idDocumento = DB::table('comprobantes_contables')->where('consecutivo', $consecutivo)->value('id');
                $prefijoRuta = '/contabilidad/comprobantes/ver/';
                break;

            default:
                return response()->json(['success' => false, 'message' => 'Tipo de comprobante inválido.']);
        }

        // Si encontramos el registro, armamos la URL interna y la respondemos al frontend
        if ($idDocumento) {
            return response()->json([
                'success' => true,
                'url' => url($prefijoRuta . $idDocumento)
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "No se encontró el consecutivo '{$consecutivo}' en este módulo."
        ]);
    }
}