<?php

namespace Omnipay\Akbank\Message;

/** Sipariş sorgusu (1010): transactionId (sipariş no). İşlemler ApiResponse::getTransactions() */
class FetchTransactionRequest extends AbstractApiRequest
{
    protected function body(): array
    {
        return ['txnCode' => '1010'];
    }
}
