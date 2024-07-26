<?php

namespace MyFatoorah\Gateway\Model;

use Magento\Framework\Model\AbstractModel;
use MyFatoorah\Gateway\Model\ResourceModel\MyfatoorahInvoice as MFResourceModel;

class MyfatoorahInvoice extends AbstractModel
{

    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_init(MFResourceModel::class);
    }
}
