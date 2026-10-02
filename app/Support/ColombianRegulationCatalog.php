<?php

namespace App\Support;

class ColombianRegulationCatalog
{
    public const SECTORS = [
        'contable' => 'Contable',
        'laboral' => 'Laboral',
        'seguridad_social' => 'Seguridad social',
        'financiero' => 'Financiero',
        'economico' => 'Económico',
        'revisoria' => 'Revisoría fiscal',
    ];

    /**
     * @return array<int, array{sector: string, title: string, description: string, source: string, url: string, keywords: string}>
     */
    public static function all(): array
    {
        return [
            [
                'sector' => 'contable',
                'title' => 'Normas de Información Financiera (NIIF)',
                'description' => 'Marco técnico normativo contable vigente en Colombia, consultas y conceptos del Consejo Técnico de la Contaduría Pública.',
                'source' => 'CTCP',
                'url' => 'https://www.ctcp.gov.co/',
                'keywords' => 'niif normas contables contabilidad marco tecnico normativo ctcp',
            ],
            [
                'sector' => 'contable',
                'title' => 'Obligaciones tributarias y facturación electrónica',
                'description' => 'Normativa tributaria, calendario de obligaciones y facturación electrónica ante la DIAN.',
                'source' => 'DIAN',
                'url' => 'https://www.dian.gov.co/',
                'keywords' => 'impuestos facturacion electronica tributario dian renta iva',
            ],
            [
                'sector' => 'laboral',
                'title' => 'Código Sustantivo del Trabajo y normativa laboral',
                'description' => 'Consulta unificada de leyes, decretos y normas laborales colombianas.',
                'source' => 'SUIN-Juriscol',
                'url' => 'https://www.suin-juriscol.gov.co/',
                'keywords' => 'codigo sustantivo del trabajo normativa laboral leyes decretos',
            ],
            [
                'sector' => 'laboral',
                'title' => 'Jornada laboral, contratos y novedades de empleo',
                'description' => 'Jornada de trabajo, tipos de contrato, novedades laborales y conceptos oficiales del Ministerio del Trabajo.',
                'source' => 'MinTrabajo',
                'url' => 'https://www.mintrabajo.gov.co/',
                'keywords' => 'horario laboral jornada de trabajo contrato de trabajo novedades mintrabajo',
            ],
            [
                'sector' => 'seguridad_social',
                'title' => 'Aportes a salud, pensión y ARL',
                'description' => 'Normativa sobre afiliación y aportes al sistema general de seguridad social en salud.',
                'source' => 'MinSalud',
                'url' => 'https://www.minsalud.gov.co/',
                'keywords' => 'salud pension arl seguridad social aportes afiliacion eps',
            ],
            [
                'sector' => 'seguridad_social',
                'title' => 'Fiscalización de aportes parafiscales y PILA',
                'description' => 'Control y fiscalización de los aportes al sistema de protección social (PILA).',
                'source' => 'UGPP',
                'url' => 'https://www.ugpp.gov.co/',
                'keywords' => 'pila parafiscales fiscalizacion aportes ugpp planilla',
            ],
            [
                'sector' => 'financiero',
                'title' => 'Vigilancia del sistema financiero',
                'description' => 'Regulación y vigilancia de entidades financieras, bancos y aseguradoras.',
                'source' => 'Superintendencia Financiera',
                'url' => 'https://www.superfinanciera.gov.co/',
                'keywords' => 'sistema financiero bancos vigilancia seguros superfinanciera',
            ],
            [
                'sector' => 'financiero',
                'title' => 'Política fiscal y presupuesto nacional',
                'description' => 'Normativa de política fiscal, presupuesto y hacienda pública nacional.',
                'source' => 'MinHacienda',
                'url' => 'https://www.minhacienda.gov.co/',
                'keywords' => 'politica fiscal presupuesto hacienda publica minhacienda',
            ],
            [
                'sector' => 'economico',
                'title' => 'Función pública y gestión del talento humano',
                'description' => 'Normativa de empleo público, estructura del Estado y gestión administrativa.',
                'source' => 'Función Pública',
                'url' => 'https://www.funcionpublica.gov.co/',
                'keywords' => 'funcion publica empleo publico gestion administrativa estado',
            ],
            [
                'sector' => 'economico',
                'title' => 'Estatuto tributario y normativa económica',
                'description' => 'Consulta del estatuto tributario y normativa económica nacional.',
                'source' => 'DIAN',
                'url' => 'https://www.dian.gov.co/',
                'keywords' => 'estatuto tributario economia normativa economica dian',
            ],
            [
                'sector' => 'revisoria',
                'title' => 'Normas de aseguramiento de la información y revisoría fiscal',
                'description' => 'Normas internacionales de auditoría y aseguramiento aplicables a la revisoría fiscal en Colombia.',
                'source' => 'CTCP',
                'url' => 'https://www.ctcp.gov.co/',
                'keywords' => 'revisoria fiscal auditoria aseguramiento normas internacionales',
            ],
            [
                'sector' => 'revisoria',
                'title' => 'Consulta unificada de leyes y decretos',
                'description' => 'Buscador oficial de normas vigentes: leyes, decretos, resoluciones y conceptos.',
                'source' => 'SUIN-Juriscol',
                'url' => 'https://www.suin-juriscol.gov.co/',
                'keywords' => 'consulta de normas leyes decretos resoluciones conceptos',
            ],
        ];
    }
}
