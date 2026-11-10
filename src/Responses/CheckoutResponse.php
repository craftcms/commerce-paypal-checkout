<?php

declare(strict_types=1);

namespace CraftCms\CommercePayPalCheckout\Responses;

use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;

/**
 * Wraps a PayPal Orders v2 or Payments v2 response.
 *
 * `$data` has the `statusCode`, `result` and `headers` keys returned by {@see \CraftCms\CommercePayPalCheckout\Client}.
 * When a request fails, the gateway builds a `result` with `status` set to {@see self::STATUS_ERROR} and a `message`.
 */
class CheckoutResponse implements RequestResponseInterface
{
    public const string STATUS_ERROR = 'error';

    public const string STATUS_REDIRECT = 'redirect';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_SUCCESSFUL = 'successful';

    private string $message = '';

    /**
     * @param array{statusCode?: int, result?: mixed, headers?: array<string, string>} $data
     */
    public function __construct(
        protected readonly array $data,
    ) {
        $result = $this->result();

        if (($result['status'] ?? null) === self::STATUS_ERROR && isset($result['message'])) {
            $this->message = (string)$result['message'];
        }
    }

    public function getStatus(): string
    {
        $result = $this->result();
        $status = $result['status'] ?? null;

        if ($status === self::STATUS_ERROR) {
            return self::STATUS_ERROR;
        }

        if ($status !== 'COMPLETED') {
            return self::STATUS_REDIRECT;
        }

        $purchaseUnit = $result['purchase_units'] ?? null;

        // Older responses had a single purchase unit object rather than a list.
        if (is_array($purchaseUnit) && array_is_list($purchaseUnit)) {
            $purchaseUnit = $purchaseUnit[0] ?? null;
        }

        $payments = is_array($purchaseUnit) ? ($purchaseUnit['payments'] ?? null) : null;

        if (!is_array($payments)) {
            return self::STATUS_SUCCESSFUL;
        }

        $captureStatus = $payments['captures'][0]['status'] ?? null;
        $authorizeStatus = $payments['authorizations'][0]['status'] ?? null;
        $statuses = [$captureStatus, $authorizeStatus];

        if (array_intersect($statuses, ['DECLINED', 'FAILED'])) {
            return self::STATUS_ERROR;
        }

        if (in_array('PENDING', $statuses, true)) {
            return self::STATUS_PROCESSING;
        }

        return self::STATUS_SUCCESSFUL;
    }

    public function isSuccessful(): bool
    {
        return $this->getStatus() === self::STATUS_SUCCESSFUL;
    }

    public function isProcessing(): bool
    {
        return $this->getStatus() === self::STATUS_PROCESSING;
    }

    public function isRedirect(): bool
    {
        return $this->getStatus() === self::STATUS_REDIRECT;
    }

    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    public function getRedirectData(): array
    {
        return [];
    }

    /**
     * Returns the PayPal order ID. The PayPal Buttons approve the order in a popup, so there is no URL to redirect to.
     */
    public function getRedirectUrl(): string
    {
        return $this->getTransactionReference();
    }

    public function getTransactionReference(): string
    {
        return (string)($this->result()['id'] ?? '');
    }

    public function getCode(): string
    {
        return (string)($this->data['statusCode'] ?? '');
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
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
