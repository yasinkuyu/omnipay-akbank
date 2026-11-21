<?php

namespace Omnipay\Akbank\Message;

/** İade (1002): transactionId (sipariş no), amount (tam ya da kısmi) */
class RefundRequest extends AbstractApiRequest
{
    protected function body(): array
    {
        $this->validate('amount');

        return ['txnCode' => '1002', 'transaction' => ['amount' => $this->getAmount(), 'currencyCode' => (int) $this->getCurrencyNumeric()]];
    }
}
