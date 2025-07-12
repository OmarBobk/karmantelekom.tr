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
            background-color: #667eea; /* Replaces gradient */
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
            background-color: #4f46e5; /* Replaces gradient */
            color: white;
            padding: 30px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .company-info h1 {
            font-size: 28px;
            font-weight: bold;
        }

        .company-tagline {
            font-size: 14px;
            margin-top: 4px;
            opacity: 0.9;
        }

        .invoice-meta {
            text-align: left;
            font-size: 14px;
            justify-content: center;
            display: flex;
            flex-direction: column;
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
        }

        .items-table thead {
            background-color: #1e293b;
            color: white;
        }

        .items-table th,
        .items-table td {
            padding: 12px 6px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
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
        } /* Price columns - prevent wrapping */

        .product-name {
            font-weight: 600;
            color: #1e293b;
        }

        .product-serial {
            background-color: #e2e8f0;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        .footer-note {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px;
            border-radius: 6px;
            color: #92400e;
            font-size: 13px;
            margin-top: 20px;
            text-align: left;
        }

        .total-section {
            padding: 20px;
            background-color: #f8fafc;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-label {
            font-size: 16px;
            font-weight: 600;
        }

        .total-amount {
            font-size: 20px;
            font-weight: 700;
            color: #4f46e5;
        }

        .contact-strip {
            background-color: #1e293b;
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 13px;
        }

        .contact-strip p {
            margin-bottom: 5px;
        }

        .text-left {
            text-align: left;
        }
    </style>
</head>
<body>
<div class="invoice-container">
    <!-- Header -->
    <div class="invoice-header">
        <div class="header-content">
            <div class="company-info">
                <h1>İndirimGo</h1>
                <p class="company-tagline">Premium Shopping Experience</p>
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
                    $unitPrice = $item->unit_price ?? ($item->price / $quantity);
                    $subtotal = $quantity * $unitPrice;
                    $itemTotal = $subtotal; // No tax

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

{{--            <tr>--}}
{{--                <td colspan="6" style="padding-left:0; padding-right:0;">--}}
{{--                    <div class="footer-note">--}}
{{--                        <strong>Note:</strong> This invoice was generated electronically and is legally valid. Contact us for any questions.--}}
{{--                    </div>--}}
{{--                </td>--}}
{{--            </tr>--}}
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
