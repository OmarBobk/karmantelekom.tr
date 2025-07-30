<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->name }}</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            background-color: #667eea;
            color: #2d3748;
            line-height: 1.6;
        }

        .invoice-container {
            min-height: 100vh;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
        }

        .invoice-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 40px 30px;
            position: relative;
            overflow: hidden;
        }

        /* Subtle background pattern */
        .invoice-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.02'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        /* Premium Logo Section Design */
        .company-info {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .logo-section {
            position: relative;
            display: flex;
            align-items: center;
        }

        .logo-container {
            position: relative;
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow:
                0 20px 40px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .logo-image {
            width: 90px;
            height: 90px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .brand-name {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            letter-spacing: -0.5px;
        }

        .brand-tagline {
            font-size: 14px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
            text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(255, 255, 255, 0.1);
            padding: 6px 14px;
            border-radius: 14px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .invoice-meta {
            text-align: left;
            font-size: 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            padding: 18px 22px;
            border-radius: 16px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .invoice-meta p {
            margin: 0;
            font-weight: 500;
        }

        .invoice-meta strong {
            color: rgba(255, 255, 255, 0.9);
        }

        .invoice-body {
            padding: 30px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            table-layout: fixed;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .items-table thead {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
        }

        .items-table th,
        .items-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
            text-align: left;
            word-wrap: break-word;
        }

        .items-table th:nth-child(1) { width: 35%; } /* Product Name */
        .items-table th:nth-child(2) { width: 12%; } /* Code */
        .items-table th:nth-child(3) { width: 8%; text-align: center; } /* Qty */
        .items-table th:nth-child(4) { width: 15%; text-align: right; } /* Unit Price */
        .items-table th:nth-child(5) { width: 15%; text-align: right; } /* Subtotal */
        .items-table th:nth-child(6) { width: 15%; text-align: right; } /* Total */

        .items-table td:nth-child(3) { text-align: center; } /* Qty */
        .items-table td:nth-child(4),
        .items-table td:nth-child(5),
        .items-table td:nth-child(6) {
            text-align: right;
            white-space: nowrap;
        }

        .product-name {
            font-weight: 600;
            color: #1e293b;
        }

        .product-serial {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 500;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .total-section {
            padding: 24px;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .total-label {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }

        .total-amount {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .contact-strip {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            text-align: center;
            padding: 24px;
            font-size: 13px;
        }

        .contact-strip p {
            margin-bottom: 6px;
        }

        .contact-strip strong {
            color: #60a5fa;
        }
    </style>
</head>
<body>
<div class="invoice-container">
    <!-- Header -->
    <div class="invoice-header">
        <div class="header-content">
            <div class="company-info">
                <div class="logo-section">
                    <div class="logo-container">
                        <img src="{{ asset('assets/images/indirimgo_logo.png') }}"
                             alt="{{ config('app.name') }}"
                             class="logo-image"
                             loading="eager">
                    </div>
                </div>
            </div>
            <div class="invoice-meta">
                <p><strong>Order No:</strong> #{{ $invoice->buyer->serial }}</p>
                <p><strong>Date:</strong> {{ $invoice->buyer->date }}</p>
            </div>
        </div>
    </div>

    <!-- Body -->
    <div class="invoice-body">
        <table class="items-table">
            <thead>
            <tr>
                <th>Product Name</th>
                <th>Code</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Subtotal</th>
                <th>Total</th>
            </tr>
            </thead>
            <tbody>
            @php
                $grandSubtotal = 0;
                $grandTotal = 0;
            @endphp
            @foreach($invoice->buyer->invoice_records as $item)
                @php
                    $quantity = $item->quantity ?? 1;
                    $unitPrice = $item->price;
                    $subtotal = $quantity * $unitPrice;
                    $itemTotal = $subtotal;

                    $grandSubtotal += $subtotal;
                    $grandTotal += $itemTotal;
                @endphp
                <tr>
                    <td><span class="product-name">{{ $item->product->name }}</span></td>
                    <td><span class="product-serial">{{ $item->product->code }}</span></td>
                    <td>{{ $quantity }}</td>
                    <td>{{ number_format($unitPrice, 2) }} TL</td>
                    <td>{{ number_format($subtotal, 2) }} TL</td>
                    <td>{{ number_format($itemTotal, 2) }} TL</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="total-section">
            <span class="total-label">Total</span>
            <span class="total-amount">{{ number_format($grandTotal, 2) }} TL</span>
        </div>
    </div>

    <!-- Footer -->
    <div class="contact-strip">
        <p><strong>İndirimGo</strong> - Thank you for the premium shopping experience!</p>
        <p>Customer service: +90 535 340 25 39 | support@indirimgo.com</p>
    </div>
</div>
</body>
</html>
