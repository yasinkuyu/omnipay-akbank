<?php

namespace Omnipay\Akbank\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\NotificationInterface;
use Omnipay\Common\Message\RequestInterface;

/**
 * Dönüşün sonucu. isSuccessful(): imza geçerli + responseCode = VPS-0000 (+ hostResponseCode varsa 00).
 * isVerified() false ise alanlara güvenilmez: sonuç kaydedilmemelidir.
 */
class CompletePurchaseResponse extends AbstractResponse implements NotificationInterface
{
    public const SUCCESS = 'VPS-0000';

    public function __construct(RequestInterface $request, $data, protected bool $verified)
    {
        parent::__construct($request, $data);
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function isSuccessful(): bool
    {
        $host = (string) ($this->data['hostResponseCode'] ?? '');

        return $this->verified && (string) ($this->data['responseCode'] ?? '') === self::SUCCESS && ($host === '' || $host === '00');
    }

    public function getTransactionStatus(): string
    {
        if (!$this->verified) {
            return self::STATUS_PENDING;
        }

        return $this->isSuccessful() ? self::STATUS_COMPLETED : self::STATUS_FAILED;
    }

    public function getTransactionId(): ?string
    {
        return ($this->data['orderId'] ?? '') !== '' ? (string) $this->data['orderId'] : null;
    }

    /** Bankanın referans numarası (rrn) */
    public function getTransactionReference(): ?string
    {
        $v = (string) ($this->data['rrn'] ?? '');

        return $v !== '' && $v !== '0' ? $v : null;
    }

    public function getCode(): ?string
    {
        $v = (string) (($this->data['hostResponseCode'] ?? '') ?: ($this->data['responseCode'] ?? ''));

        return $v !== '' ? $v : null;
    }

    public function getMessage(): ?string
    {
        if (!$this->verified) {
            return 'Invalid signature';
        }
        if ($this->isSuccessful()) {
            return null;
        }
        $m = trim((string) (($this->data['hostMessage'] ?? '') ?: ($this->data['responseMessage'] ?? '')));

        return $m !== '' ? preg_replace('/^VPS-\d+\s*-\s*/', '', $m) : null;
    }

    public function getAmount(): ?string
    {
        return isset($this->data['amount']) && $this->data['amount'] !== '' ? number_format((float) $this->data['amount'], 2, '.', '') : null;
    }

    public function getInstallment(): int
    {
        return max(1, (int) ($this->data['installCount'] ?? 1));
    }

    public function getMaskedCard(): ?string
    {
        return ($this->data['maskedCardNumber'] ?? '') !== '' ? (string) $this->data['maskedCardNumber'] : null;
    }

    public function getAuthCode(): ?string
    {
        return ($this->data['authCode'] ?? '') !== '' ? (string) $this->data['authCode'] : null;
    }

    public function getHostReference(): ?string
    {
        return $this->getTransactionReference();
    }

    public function getMdStatus(): ?string
    {
        return isset($this->data['mdStatus']) ? (string) $this->data['mdStatus'] : null;
    }
}
