<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #{{ $order->id }} - {{ config('app.name') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        .order-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .order-details, .customer-details {
            flex: 1;
        }
        .order-details h3, .customer-details h3 {
            margin-top: 0;
            color: #555;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-row {
            margin-bottom: 8px;
        }
        .label {
            font-weight: bold;
            color: #666;
        }
        .value {
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-confirmed { background-color: #dbeafe; color: #1e40af; }
        .status-processing { background-color: #fef3c7; color: #92400e; }
        .status-ready { background-color: #d1fae5; color: #065f46; }
        .status-delivering { background-color: #e0e7ff; color: #3730a3; }
        .status-delivered { background-color: #d1fae5; color: #065f46; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        .items-table td {
            border: 1px solid #ddd;
            padding: 12px;
            vertical-align: top;
        }
        .product-info {
            display: flex;
            align-items: center;
        }
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 10px;
        }
        .product-name {
            font-weight: bold;
            margin-bottom: 4px;
        }
        .product-code {
            color: #666;
            font-size: 12px;
        }
        .quantity-badge {
            background-color: #e0e7ff;
            color: #3730a3;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .total-section {
            text-align: right;
            border-top: 2px solid #333;
            padding-top: 20px;
        }
        .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #059669;
        }
        .notes-section {
            margin-top: 30px;
            padding: 15px;
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
        }
        .notes-section h3 {
            margin-top: 0;
            color: #92400e;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
        <h2>Order Details</h2>
    </div>

    <div class="order-info">
        <div class="order-details">
            <h3>Order Information</h3>
            <div class="info-row">
                <span class="label">Order ID:</span>
                <span class="value">#{{ $order->id }}</span>
            </div>
            <div class="info-row">
                <span class="label">Date:</span>
                <span class="value">{{ $order->created_at->format('d M Y, H:i') }}</span>
            </div>
            <div class="info-row">
                <span class="label">Status:</span>
                <span class="value">
                    <span class="status-badge status-{{ strtolower($order->status->value) }}">
                        {{ ucfirst($order->status->value) }}
                    </span>
                </span>
            </div>
        </div>
        
        <div class="customer-details">
            <h3>Shop & Salesperson</h3>
            <div class="info-row">
                <span class="label">Shop:</span>
                <span class="value">{{ $order->shop->name }}</span>
            </div>
            <div class="info-row">
                <span class="label">Salesperson:</span>
                <span class="value">{{ $order->salesperson->name }}</span>
            </div>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
            <tr>
                <td>
                    <div class="product-info">
                        <img src="{{ asset('storage/' . $item->product->primary_image_url) }}" 
                             alt="{{ $item->product->name }}" 
                             class="product-image">
                        <div>
                            <div class="product-name">{{ $item->product->name }}</div>
                            <div class="product-code">{{ $item->product->code }}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="quantity-badge">{{ $item->quantity }}</span>
                </td>
                <td>${{ number_format($item->price, 2) }}</td>
                <td>${{ number_format($item->subtotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-amount">
            Total: ${{ number_format($order->total_price, 2) }}
        </div>
    </div>

    @if($order->notes)
    <div class="notes-section">
        <h3>Order Notes</h3>
        <p>{{ $order->notes }}</p>
    </div>
    @endif

    <div class="footer">
        <p>Generated on {{ now()->format('d M Y, H:i') }} | {{ config('app.name') }}</p>
        <p>Thank you for your business!</p>
    </div>
</body>
</html> 