<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Carbon\Carbon;

class InventoryReport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize, WithCustomStartCell, WithDrawings
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Icono de Pintura');
        $drawing->setPath(public_path('/images/paint-icon.png'));
        $drawing->setHeight(50);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(5);
        $drawing->setOffsetY(5);

        return $drawing;
    }

    public function collection()
    {
        return $this->data;
    }

    public function startCell(): string
    {
        return 'A8'; // Espacio para logo y encabezado
    }

    public function headings(): array
    {
        return [
            'ID',
            'Producto',
            'Precio',
            'Stock',
            'Valor Total',
            'Fecha Creación',
            'Última Actualización'
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            strtoupper($row->name),
            'S/. ' . number_format($row->price, 2),
            $this->getStockStatus($row->stock),
            'S/. ' . number_format($row->total_value, 2),
            Carbon::parse($row->created_at)->format('d/m/Y'),
            Carbon::parse($row->updated_at)->format('d/m/Y')
        ];
    }

    private function getStockStatus($stock)
    {
        if ($stock <= 0) {
            return '✕ SIN STOCK';
        } elseif ($stock <= 10) {
            return '⚠ ' . $stock . ' BAJO';
        } else {
            return '✓ ' . $stock . ' DISPONIBLE';
        }
    }

    public function title(): string
    {
        return 'Reporte de Inventario';
    }

    public function styles(Worksheet $sheet)
    {
        // Configuración de la página
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

        // Merge cells for header (ajustado para el logo)
        $sheet->mergeCells('B1:G1');
        $sheet->mergeCells('A2:G2');
        $sheet->mergeCells('A3:G3');
        $sheet->mergeCells('A4:G4');
        $sheet->mergeCells('A6:G6');

        // Company header
        $sheet->setCellValue('B1', 'INTERCOLOR S.R.L.');
        $sheet->setCellValue('A2', 'Expertos en Pinturas y Acabados');
        $sheet->setCellValue('A3', 'Av. República de Panamá 1234 - Huancayo • Tel: (+51) 987-654-321');
        $sheet->setCellValue('A4', 'RUC: 20123456789');
        $sheet->setCellValue('A6', 'REPORTE DE INVENTARIO - ' . Carbon::now()->format('d/m/Y H:i:s'));

        // Estilos del título principal
        $sheet->getStyle('B1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 24,
                'color' => ['rgb' => '1F4E78']
            ]
        ]);

        // Estilos del subtítulo
        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'italic' => true,
                'size' => 12,
                'color' => ['rgb' => '4F81BD']
            ]
        ]);

        // Información de contacto
        $sheet->getStyle('A3:A4')->applyFromArray([
            'font' => [
                'size' => 10
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);

        // Título del reporte
        $sheet->getStyle('A6')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_GRADIENT_LINEAR,
                'rotation' => 90,
                'startColor' => ['rgb' => '1F4E78'],
                'endColor' => ['rgb' => '4F81BD']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Estilo de cabecera de tabla
        $sheet->getStyle('A8:G8')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '305496']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFFFFF']
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);

        // Estilos para los datos
        $dataRange = 'A9:G' . ($sheet->getHighestRow());
        $sheet->getStyle($dataRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'BFBFBF']
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Filas alternadas
        $lastRow = $sheet->getHighestRow();
        for ($row = 9; $row <= $lastRow; $row++) {
            if ($row % 2 == 0) {
                $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F5F9FF']
                    ]
                ]);
            }
        }

        // Formateo condicional para stock
        foreach($sheet->getColumnIterator('D') as $column) {
            foreach($column->getCellIterator(9, $lastRow) as $cell) {
                $value = $cell->getValue();
                if (strpos($value, 'SIN STOCK') !== false) {
                    $cell->getStyle()->getFont()->getColor()->setRGB('FF0000');
                } elseif (strpos($value, 'BAJO') !== false) {
                    $cell->getStyle()->getFont()->getColor()->setRGB('FFA500');
                } else {
                    $cell->getStyle()->getFont()->getColor()->setRGB('008000');
                }
            }
        }

        // Ajustar altura de filas
        $sheet->getRowDimension(1)->setRowHeight(60); // Para el logo
        $sheet->getRowDimension(6)->setRowHeight(30); // Para el título del reporte
        $sheet->getRowDimension(8)->setRowHeight(25); // Para la cabecera de la tabla

        // Ajustar ancho de columnas específicas
        $sheet->getColumnDimension('B')->setWidth(40); // Nombre producto
        $sheet->getColumnDimension('C')->setWidth(15); // Precio
        $sheet->getColumnDimension('E')->setWidth(15); // Valor total

        // Pie de página con totales
        $lastRow += 2;
        $sheet->setCellValue('A' . $lastRow, 'RESUMEN DEL INVENTARIO');
        $sheet->mergeCells('A' . $lastRow . ':G' . $lastRow);
        $sheet->getStyle('A' . $lastRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E9EDF5']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);

        return $sheet;
    }
}