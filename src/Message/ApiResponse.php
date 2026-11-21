<?php

namespace Omnipay\Akbank\Message;

use Omnipay\Common\Message\AbstractResponse;

/**
 * API yanıtı. isSuccessful(): responseCode = VPS-0000 (+ hostResponseCode varsa 00).
 * isReadable() false ise yanıt okunamadı: işlem bankada olmuş olabilir, otomatik tekrar denenmemeli.
 */
class ApiResponse extends AbstractResponse
{
    public function isReadable(): bool
    {
        return is_array($this->data) && isset($this->data['responseCode']);
    }

    public function isSuccessful(): bool
    {
        $host = (string) ($this->data['hostResponseCode'] ?? '');

        return $this->isReadable() && (string) $this->data['responseCode'] === 'VPS-0000' && ($host === '' || $host === '00');
    }

    public function getCode(): ?string
    {
        $v = is_array($this->data) ? (string) (($this->data['hostResponseCode'] ?? '') ?: ($this->data['responseCode'] ?? '')) : '';

        return $v !== '' ? $v : null;
    }

    public function getMessage(): ?string
    {
        if (!$this->isReadable()) {
            return 'Unreadable response';
        }
        if ($this->isSuccessful()) {
            return null;
        }
        $m = trim((string) (($this->data['hostMessage'] ?? '') ?: ($this->data['responseMessage'] ?? '')));

        return $m !== '' ? preg_replace('/^VPS-\d+\s*-\s*/', '', $m) : null;
    }

    public function getTransactionReference(): ?string
    {
        $v = (string) ($this->data['transaction']['rrn'] ?? '');

        return $v !== '' ? $v : null;
    }

    /** Sorgu: işlem satırları */
    public function getTransactions(): array
    {
        return is_array($this->data['txnDetailList'] ?? null) ? $this->data['txnDetailList'] : [];
    }
}
