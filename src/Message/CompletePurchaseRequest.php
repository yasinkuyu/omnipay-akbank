<?php

namespace Omnipay\Akbank\Message;

/**
 * Bankanın dönüşü (okUrl / failUrl POST'u). Ağ isteği yapmaz; imzayı doğrular.
 *
 * İmza: base64(HMAC-SHA512(hashParams'ta "+" ile sayılan alanların değerleri sırayla, gizli anahtar)).
 * Sonuç kodu ve sipariş no imzalı alanlar arasında olmalıdır; değilse dönüş reddedilir. İmzalıysa üye iş yeri /
 * terminal numarası, verilmişse orderId transactionId ile karşılaştırılır.
 */
class CompletePurchaseRequest extends AbstractRequest
{
    private const MUST_SIGN = ['responseCode', 'orderId'];

    public function getFields() { return $this->getParameter('fields'); }
    public function setFields($value) { return $this->setParameter('fields', $value); }

    public function getData(): array
    {
        $this->validate('secretKey');
        if (is_array($this->getFields())) {
            return $this->getFields();
        }

        return $this->httpRequest->request->all() ?: $this->httpRequest->query->all();
    }

    public function sendData($data): CompletePurchaseResponse
    {
        return $this->response = new CompletePurchaseResponse($this, $data, $this->verify($data));
    }

    private function verify(array $d): bool
    {
        $given = (string) ($d['hash'] ?? '');
        $params = array_values(array_filter(explode('+', (string) ($d['hashParams'] ?? '')), fn ($p) => $p !== ''));
        if ($given === '' || !$params || array_diff(self::MUST_SIGN, $params)) {
            return false;
        }
        $vals = '';
        foreach ($params as $p) {
            $vals .= (string) ($d[$p] ?? '');
        }
        if (!hash_equals(self::sign($vals, (string) $this->getSecretKey()), $given)) {
            return false;
        }
        foreach (['merchantSafeId' => $this->getMerchantSafeId(), 'terminalSafeId' => $this->getTerminalSafeId()] as $k => $mine) {
            if (in_array($k, $params, true) && (string) ($d[$k] ?? '') !== '' && $mine && (string) $d[$k] !== (string) $mine) {
                return false;
            }
        }
        if ($this->getTransactionId() !== null && (string) ($d['orderId'] ?? '') !== (string) $this->getTransactionId()) {
            return false;
        }

        return true;
    }
}
