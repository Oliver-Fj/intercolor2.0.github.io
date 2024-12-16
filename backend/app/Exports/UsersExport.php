<?php

namespace App\Exports;

use App\Models\User;
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

class UsersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithProperties, ShouldAutoSize, WithEvents
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
            'title'         => 'Reporte de Usuarios',
            'description'   => 'Listado de usuarios del sistema',
            'subject'       => 'Usuarios',
            'keywords'      => 'usuarios,reporte,excel',
            'category'      => 'Reportes',
            'manager'       => 'INTERCOLOR',
            'company'       => 'INTERCOLOR',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function collection()
    {
        return User::all();
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
                'Nombre',
                'Email',
                'Rol',
                'Estado',
                'Fecha de Registro'
            ]
        ];
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->email,
            $user->role === 'admin' ? 'Administrador' : 'Usuario',
            $user->status === 1 ? 'Activo' : 'Inactivo',
            $user->created_at->format('d/m/Y')
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;

                // Estilos para el encabezado corporativo
                $sheet->mergeCells('A1:E1');
                $sheet->mergeCells('A2:E2');
                $sheet->mergeCells('A3:E3');
                $sheet->mergeCells('A4:E4');
                $sheet->mergeCells('A5:E5');
                $sheet->mergeCells('A6:E6');

                // Estilo para el encabezado corporativo
                $sheet->getStyle('A1:E6')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '00A0DF'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                // Estilo para los encabezados de la tabla
                $sheet->getStyle('A8:E8')->applyFromArray([
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

                // Estilo para los datos
                $lastRow = $sheet->getHighestRow();
                $sheet->getStyle('A9:E' . $lastRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                    ],
                ]);

                // Filas alternadas
                for ($i = 9; $i <= $lastRow; $i++) {
                    if ($i % 2 == 0) {
                        $sheet->getStyle('A'.$i.':E'.$i)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F8F9FA');
                    }
                }

                // Footer
                $sheet->mergeCells('A'.($lastRow + 2).':E'.($lastRow + 2));
                $sheet->setCellValue('A'.($lastRow + 2), 'INTERCOLOR - Todos los derechos reservados © ' . date('Y'));
                $sheet->getStyle('A'.($lastRow + 2))->applyFromArray([
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