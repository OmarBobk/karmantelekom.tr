<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

/**
 * Translated attribute names for purchase validation messages.
 */
final class PurchaseAttributes
{
    /**
     * Operational validation messages; empty outside the Arabic and English operational locales.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $messages = __('operations.validation');

        return is_array($messages) ? $messages : [];
    }

    /**
     * @return array<string, string>
     */
    public static function header(): array
    {
        return [
            'store_id' => __('purchases.fields.store'),
            'operational_supplier_id' => __('purchases.fields.supplier'),
            'currency' => __('purchases.fields.currency'),
            'tax_treatment' => __('purchases.fields.tax_treatment'),
            'supplier_invoice_number' => __('purchases.fields.invoice_number'),
            'invoice_date' => __('purchases.fields.invoice_date'),
            'due_date' => __('purchases.fields.due_date'),
            'additional_charges' => __('purchases.fields.additional_charges'),
            'additional_charges_note' => __('purchases.fields.additional_charges_note'),
            'notes' => __('purchases.fields.notes'),
            'lines' => __('purchases.fields.lines'),
            'lines.*.product_definition_id' => __('purchases.fields.product'),
            'lines.*.quantity' => __('purchases.fields.quantity'),
            'lines.*.unit_cost' => __('purchases.fields.unit_cost'),
            'amount' => __('purchases.fields.amount'),
            'paid_on' => __('purchases.fields.paid_on'),
            'method' => __('purchases.fields.method'),
            'reference' => __('purchases.fields.reference'),
            'note' => __('purchases.fields.note'),
            'reason' => __('purchases.fields.reason'),
        ];
    }
}
