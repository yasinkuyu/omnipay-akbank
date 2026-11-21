<?php

namespace Omnipay\Akbank;

use Omnipay\Common\AbstractGateway;
use Omnipay\Akbank\Message\CompletePurchaseRequest;
use Omnipay\Akbank\Message\FetchTransactionRequest;
use Omnipay\Akbank\Message\PurchaseRequest;
use Omnipay\Akbank\Message\RefundRequest;
use Omnipay\Akbank\Message\VoidRequest;

/**
 * Akbank sanal POS.
 *
 * purchase()          ortak ödeme sayfası formu (3D Pay Hosting: kart bilgisi Akbank'ın sayfasında girilir).
 *                     Ağ isteği yapmaz; yanıt bankaya POST yönlendirmesidir.
 * completePurchase()  bankanın dönüşü: hash (hashParams + gizli anahtar, HMAC-SHA512) doğrulanır, sonuç okunur.
 * refund() / void()   JSON API ile iade / iptal; fetchTransaction() sipariş sorgusu.
 *
 * (c) Yasin Kuyu — https://github.com/yasinkuyu/omnipay-akbank
 */
class Gateway extends AbstractGateway
{
    public function getName(): string
    {
        return 'Akbank';
    }

    public function getDefaultParameters(): array
    {
        return [
            'merchantSafeId' => '',
            'terminalSafeId' => '',
            'secretKey' => '',
            'testMode' => false,
        ];
    }

    /** Üye iş yeri güvenli numarası (32 karakter) */
    public function getMerchantSafeId() { return $this->getParameter('merchantSafeId'); }
    public function setMerchantSafeId($value): self { return $this->setParameter('merchantSafeId', $value); }

    /** Terminal güvenli numarası (32 karakter) */
    public function getTerminalSafeId() { return $this->getParameter('terminalSafeId'); }
    public function setTerminalSafeId($value): self { return $this->setParameter('terminalSafeId', $value); }

    /** Gizli anahtar (secret key) */
    public function getSecretKey() { return $this->getParameter('secretKey'); }
    public function setSecretKey($value): self { return $this->setParameter('secretKey', $value); }

    /** Alt üye iş yeri numarası (pazaryeri / ödeme kuruluşu) */
    public function getSubMerchantId() { return $this->getParameter('subMerchantId'); }
    public function setSubMerchantId($value): self { return $this->setParameter('subMerchantId', $value); }

    public function purchase(array $options = []): PurchaseRequest
    {
        return $this->createRequest(PurchaseRequest::class, $options);
    }

    public function completePurchase(array $options = []): CompletePurchaseRequest
    {
        return $this->createRequest(CompletePurchaseRequest::class, $options);
    }

    public function acceptNotification(array $options = []): CompletePurchaseRequest
    {
        return $this->createRequest(CompletePurchaseRequest::class, $options);
    }

    public function refund(array $options = []): RefundRequest
    {
        return $this->createRequest(RefundRequest::class, $options);
    }

    public function void(array $options = []): VoidRequest
    {
        return $this->createRequest(VoidRequest::class, $options);
    }

    public function fetchTransaction(array $options = []): FetchTransactionRequest
    {
        return $this->createRequest(FetchTransactionRequest::class, $options);
    }
}
