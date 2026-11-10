<?php

declare(strict_types=1);

namespace CraftCms\CommercePayPalCheckout\Responses;

use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;

/**
 * Wraps a PayPal [Refund captured payment](https://developer.paypal.com/docs/api/payments/v2/#captures_refund) response,
 * or the error PayPal returned instead.
 */
class RefundResponse implements RequestResponseInterface
{
    /**
     * @param array{statusCode?: int, result?: mixed, headers?: array<string, string>} $data
     */
    public function __construct(
        protected readonly array $data,
    ) {
    }

    public function isSuccessful(): bool
    {
        return ($this->result()['status'] ?? null) === 'COMPLETED';
    }

    public function isProcessing(): bool
    {
        return ($this->result()['status'] ?? null) === 'PENDING';
    }

    public function isRedirect(): bool
    {
        return false;
    }

    public function getRedirectMethod(): string
    {
        return '';
    }

    public function getRedirectData(): array
    {
        return [];
    }

    public function getRedirectUrl(): string
    {
        return '';
    }

    public function getTransactionReference(): string
    {
        return '';
    }

    public function getCode(): string
    {
        return '';
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getMessage(): string
    {
        $result = $this->result();

        if (isset($result['message'])) {
            return (string)$result['message'];
        }

        return (string)($result['status_details']['reason'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function result(): array
    {
        $result = $this->data['result'] ?? null;

        return is_array($result) ? $result : [];
    }
}
