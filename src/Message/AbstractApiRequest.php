<?php

namespace Omnipay\Akbank\Message;

/**
 * JSON API: gövde JSON, "auth-hash" başlığı = base64(HMAC-SHA512(gövde, gizli anahtar)).
 * Bağlantı hatasında Omnipay'in NetworkException'ı fırlar: sonuç belirsizdir.
 */
abstract class AbstractApiRequest extends AbstractRequest
{
    abstract protected function body(): array;

    public function getData(): array
    {
        $this->validate('merchantSafeId', 'terminalSafeId', 'secretKey', 'transactionId');
        $data = ['terminal' => ['merchantSafeId' => (string) $this->getMerchantSafeId(), 'terminalSafeId' => (string) $this->getTerminalSafeId()]];
        if ($this->getSubMerchantId()) {
            $data['subMerchant'] = ['subMerchantId' => (string) $this->getSubMerchantId()];
        }

        return $data + ['version' => '1.00', 'requestDateTime' => self::requestDateTime(), 'randomNumber' => self::randomNumber()]
            + $this->body() + ['order' => ['orderId' => (string) $this->getTransactionId()]];
    }

    public function sendData($data): ApiResponse
    {
        $json = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $res = $this->httpClient->request('POST', $this->getTestMode() ? self::TEST_API : self::LIVE_API, [
            'Content-Type' => 'application/json',
            'auth-hash' => self::sign($json, (string) $this->getSecretKey()),
        ], $json);
        $out = json_decode((string) $res->getBody(), true);

        return $this->response = new ApiResponse($this, is_array($out) ? $out : null);
    }
}
