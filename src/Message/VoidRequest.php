<?php

namespace Omnipay\Akbank\Message;

/** İptal (1003, gün sonundan önce): transactionId (sipariş no) */
class VoidRequest extends AbstractApiRequest
{
    protected function body(): array
    {
        return ['txnCode' => '1003'];
    }
}
