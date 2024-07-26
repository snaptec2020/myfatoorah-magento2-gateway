<?php

namespace MyFatoorah\Gateway\Plugin;

use Magento\Framework\Exception\LocalizedException as AMageExcept;

/**
 * Update stock items on sales.
 */
class StockManagementPlugin
{

    /**
     * @var \Magento\CatalogInventory\Api\StockConfigurationInterface
     */
    private $configStock;

    /**
     * @var \Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface
     */
    private $providerStockRegistry;

    /**
     * @var \Magento\CatalogInventory\Model\ResourceModel\Stock
     */
    private $resourceStock;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param \Magento\CatalogInventory\Model\ResourceModel\Stock                $resourceStock
     * @param \Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface $providerStockRegistry
     * @param \Magento\CatalogInventory\Api\StockConfigurationInterface          $configStock
     */
    public function __construct(
        \Magento\CatalogInventory\Model\ResourceModel\Stock $resourceStock,
        \Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface $providerStockRegistry,
        \Magento\CatalogInventory\Api\StockConfigurationInterface $configStock
    ) {
        $this->resourceStock         = $resourceStock;
        $this->providerStockRegistry = $providerStockRegistry;
        $this->configStock           = $configStock;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Check if is possible subtract value from item qty
     *
     * @param  \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem
     * @return bool
     */
    protected function _canSubtractQty(
        \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem
    ) {
        $result = $stockItem->getManageStock() && $this->configStock->canSubtractQty();
        return $result;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Update stock item on the stock and distribute qty by lots
     *
     * @param  \Magento\CatalogInventory\Model\StockManagement $subject
     * @param  \Closure                                        $proceed
     * @param  array                                           $items
     * @param  int                                             $websiteId is not used
     * @return \Magento\CatalogInventory\Api\Data\StockItemInterface[]
     * @throws AMageExcept
     */
    public function aroundRegisterProductsSale(
        \Magento\CatalogInventory\Model\StockManagement $subject,
        \Closure $proceed,
        array $items,
        $websiteId
    ) {
        /* This code is moved from original 'registerProductsSale' method. */
        /* replace websiteId by stockId */
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $stock         = $objectManager->create(\Magento\Store\Model\StoreManagerInterface::class);
        $stockId       = $stock->getStore()->getId();

        $lockedItems = $this->resourceStock->lockProductsStock(array_keys($items), $stockId);
        //$fullSaveItems   = $registeredItems = [];
        foreach ($lockedItems as $lockedItemRecord => $value) {
            $productId      = $lockedItemRecord;
            $orderedQty     = $items[$lockedItemRecord];
            /**
             * @var \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem
             */
            $stockItem      = $this->providerStockRegistry->getStockItem($productId, $stockId);
            $stockItemId    = $stockItem->getItemId();
            $canSubtractQty = $stockItemId && $this->_canSubtractQty($stockItem);
            if ($canSubtractQty && $this->configStock->isQty($value['type_id'])) {
                $StockState = $objectManager->get(\Magento\CatalogInventory\Api\StockStateInterface::class);
                $availQty   = $StockState->getStockQty($productId, $websiteId);
                $stockQty   = $availQty - $orderedQty;
                if ($stockQty < 0) {
                    $msg = __('MyFatoorah: Not all of your products are available in the requested quantity.');
                    throw new AMageExcept($msg);
                }
            }
        }
        return [];
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
