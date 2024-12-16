<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Events\AfterSheet;

class ProductsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithProperties, ShouldAutoSize, WithEvents
{
    protected $auth_user;

    public function __construct()
    {
        $this->auth_user = auth()->user();
    }

    public function properties(): array
    {
        return [
            'creator'        => 'INTERCOLOR',
            'lastModifiedBy' => $this->auth_user->name,
            'title'         => 'Reporte de Productos',
            'description'   => 'Listado de productos del sistema',
            'subject'       => 'Productos',
            'keywords'      => 'productos,reporte,excel',
            'category'      => 'Reportes',
            'manager'       => 'INTERCOLOR',
            'company'       => 'INTERCOLOR',
        ];
    }

    public function collection()
    {
        return Product::all();
    }

    public function headings(): array
    {
        return [
            ['INTERCOLOR'],
            ['RUC: 20123456789'],
            ['Dirección: Av. Principal 123'],
            ['Teléfono: (01) 123-4567'],
            ['Reporte generado el: ' . now()->format('d/m/Y H:i:s')],
            ['Generado por: ' . $this->auth_user->name . ' - ' . $this->auth_user->email],
            [], // Línea en blanco
            [ // Encabezados de la tabla
                'Código',
                'Nombre',
                'Categoría',
                'Precio',
                'Stock',
                'Estado',
                'Última actualización'
            ]
        ];
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->name,
            $product->category,
            '$' . number_format($product->price, 2),
            $product->stock,
            $product->status === 'active' ? 'Activo' : 'Inactivo',
            $product->updated_at->format('d/m/Y H:i')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastRow = $sheet->getHighestRow();
                $lastColumn = 'G';

                // Header corporativo
                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->mergeCells("A2:{$lastColumn}2");
                $sheet->mergeCells("A3:{$lastColumn}3");
                $sheet->mergeCells("A4:{$lastColumn}4");
                $sheet->mergeCells("A5:{$lastColumn}5");
                $sheet->mergeCells("A6:{$lastColumn}6");

                // Estilo encabezado corporativo
                $sheet->getStyle("A1:{$lastColumn}6")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '00A0DF'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                // Estilo encabezados tabla
                $sheet->getStyle("A8:{$lastColumn}8")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '00A0DF'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                // Estilo datos
                $sheet->getStyle("A9:{$lastColumn}$lastRow")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Filas alternadas
                for ($i = 9; $i <= $lastRow; $i++) {
                    if ($i % 2 == 0) {
                        $sheet->getStyle("A$i:{$lastColumn}$i")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F8F9FA');
                    }
                }

                // Ajustar columnas
                foreach (range('A', $lastColumn) as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // Footer
                $footerRow = $lastRow + 2;
                $sheet->mergeCells("A{$footerRow}:{$lastColumn}{$footerRow}");
                $sheet->setCellValue("A{$footerRow}", 'INTERCOLOR - Todos los derechos reservados © ' . date('Y'));
                $sheet->getStyle("A{$footerRow}")->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'color' => ['rgb' => '666666'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
            },
        ];
    }
}