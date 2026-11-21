<?php

namespace Omnipay\Akbank\Message;

/**
 * Ortak ödeme sayfası formu (paymentModel 3D_PAY_HOSTING, txnCode 3000). Ağ isteği yapmaz.
 *
 * Parametreler: amount, currency, transactionId (orderId), returnUrl (okUrl), cancelUrl (failUrl, yoksa returnUrl),
 * installment, card (yalnız e-posta), lang (TR | EN).
 *
 * İmza: base64(HMAC-SHA512(paymentModel + txnCode + merchantSafeId + terminalSafeId + orderId + lang + amount +
 * ccb + pcb + xcb ödül tutarları + currencyCode + installCount + okUrl + failUrl + emailAddress + subMerchantId +
 * kart alanları (boş) + randomNumber + requestDateTime + b2bIdentityNumber, gizli anahtar)).
 */
class PurchaseRequest extends AbstractRequest
{
    public function getLang() { return $this->getParameter('lang'); }
    public function setLang($value) { return $this->setParameter('lang', $value); }

    public function getData(): array
    {
        $this->validate('merchantSafeId', 'terminalSafeId', 'secretKey', 'amount', 'transactionId', 'returnUrl');

        $data = [
            'paymentModel' => '3D_PAY_HOSTING',
            'txnCode' => '3000',
            'merchantSafeId' => (string) $this->getMerchantSafeId(),
            'terminalSafeId' => (string) $this->getTerminalSafeId(),
            'orderId' => (string) $this->getTransactionId(),
            'lang' => strtoupper((string) ($this->getLang() ?: 'TR')),
            'amount' => $this->getAmount(),
            'currencyCode' => $this->getCurrencyNumeric(),
            'installCount' => (string) max(1, (int) $this->getInstallment()),
            'okUrl' => (string) $this->getReturnUrl(),
            'failUrl' => (string) ($this->getCancelUrl() ?: $this->getReturnUrl()),
            'randomNumber' => self::randomNumber(),
            'requestDateTime' => self::requestDateTime(),
        ];
        $email = $this->getCard() ? (string) $this->getCard()->getEmail() : '';
        if ($email !== '') {
            $data['emailAddress'] = mb_substr($email, 0, 64);
        }
        if ($this->getSubMerchantId()) {
            $data['subMerchantId'] = (string) $this->getSubMerchantId();
        }

        $data['hash'] = self::sign(implode('', [
            $data['paymentModel'], $data['txnCode'], $data['merchantSafeId'], $data['terminalSafeId'], $data['orderId'],
            $data['lang'], $data['amount'], '', '', '', $data['currencyCode'], $data['installCount'], $data['okUrl'],
            $data['failUrl'], $data['emailAddress'] ?? '', $data['subMerchantId'] ?? '', '', '', '',
            $data['randomNumber'], $data['requestDateTime'], '',
        ]), (string) $this->getSecretKey());

        return $data;
    }

    public function sendData($data): PurchaseResponse
    {
        return $this->response = new PurchaseResponse($this, $data, $this->getTestMode() ? self::TEST_GATE : self::LIVE_GATE);
    }
}
