<?php

namespace Omnipay\Akbank\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\AbstractRequest as BaseAbstractRequest;

/**
 * Ortak parametreler, adresler, imza (HMAC-SHA512, base64).
 */
abstract class AbstractRequest extends BaseAbstractRequest
{
    public const TEST_GATE = 'https://virtualpospaymentgatewaypre.akbank.com/payhosting';
    public const LIVE_GATE = 'https://virtualpospaymentgateway.akbank.com/payhosting';
    public const TEST_API = 'https://apipre.akbank.com/api/v1/payment/virtualpos';
    public const LIVE_API = 'https://api.akbank.com/api/v1/payment/virtualpos';

    public const CURRENCIES = ['TRY' => '949', 'TL' => '949', 'USD' => '840', 'EUR' => '978', 'GBP' => '826', 'JPY' => '392'];

    public function getMerchantSafeId() { return $this->getParameter('merchantSafeId'); }
    public function setMerchantSafeId($value) { return $this->setParameter('merchantSafeId', $value); }

    public function getTerminalSafeId() { return $this->getParameter('terminalSafeId'); }
    public function setTerminalSafeId($value) { return $this->setParameter('terminalSafeId', $value); }

    public function getSecretKey() { return $this->getParameter('secretKey'); }
    public function setSecretKey($value) { return $this->setParameter('secretKey', $value); }

    public function getSubMerchantId() { return $this->getParameter('subMerchantId'); }
    public function setSubMerchantId($value) { return $this->setParameter('subMerchantId', $value); }

    /** Taksit sayısı (1 ya da boş = peşin) */
    public function getInstallment() { return $this->getParameter('installment'); }
    public function setInstallment($value) { return $this->setParameter('installment', $value); }

    public function getCurrencyNumeric(): string
    {
        $c = strtoupper((string) ($this->getCurrency() ?: 'TRY'));
        if (!isset(self::CURRENCIES[$c])) {
            throw new InvalidRequestException('Unsupported currency: ' . $c);
        }

        return self::CURRENCIES[$c];
    }

    /** base64(HMAC-SHA512(metin, gizli anahtar)) */
    public static function sign(string $data, string $secretKey): string
    {
        return base64_encode(hash_hmac('sha512', $data, $secretKey, true));
    }

    /** İstek zamanı, İstanbul saatiyle: 2026-11-21T22:30:04.000 */
    protected static function requestDateTime(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Istanbul')))->format('Y-m-d\TH:i:s') . '.000';
    }

    protected static function randomNumber(): string
    {
        return strtoupper(bin2hex(random_bytes(64)));
    }
}
