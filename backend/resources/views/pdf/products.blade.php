<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Reporte de Productos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .company-info {
            margin-bottom: 20px;
            color: #00A0DF;
        }

        .report-info {
            margin-bottom: 30px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th {
            background-color: #00A0DF;
            color: white;
            padding: 12px;
            text-align: left;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            vertical-align: middle;
        }

        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }

        .product-image-placeholder {
            width: 50px;
            height: 50px;
            background-color: #f0f0f0;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #666;
        }

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company-info">
            <h1 style="color: #00A0DF;">INTERCOLOR</h1>
            <p>RUC: 20123456789</p>
            <p>Dirección: Av. Principal 123</p>
            <p>Teléfono: (01) 123-4567</p>
        </div>
    </div>

    <div class="report-info">
        <p><strong>Reporte de Productos</strong></p>
        <p>Fecha de generación: {{ now()->format('d/m/Y H:i:s') }}</p>
        <p>Generado por: {{ auth()->user()->name }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="60">Imagen</th>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $product)
            <tr>
                <td>
                    @if($product['image_base64'])
                    <img src="{{ $product['image_base64'] }}"
                        style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #ddd; border-radius: 4px;"
                        alt="{{ $product['name'] }}">
                    @else
                    <div style="width: 50px; height: 50px; background-color: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #666;">
                        Sin imagen
                    </div>
                    @endif
                </td>
                <td>{{ $product->name }}</td>
                <td>{{ $product->category }}</td>
                <td>${{ number_format($product->price, 2) }}</td>
                <td>{{ $product->stock }}</td>
                <td>{{ $product->status === 'active' ? 'Activo' : 'Inactivo' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>INTERCOLOR - Todos los derechos reservados © {{ date('Y') }}</p>
        <p>Este es un documento generado automáticamente</p>
    </div>
</body>

</html>