<?php
/**
 * ScandiPWA_CustomerDownloadableGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CustomerDownloadableGraphQl
 * @author      Daniels Stabulis <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */


declare(strict_types=1);

namespace ScandiPWA\CustomerDownloadableGraphQl\Model\Resolver;

use Magento\DownloadableGraphQl\Model\ResourceModel\GetPurchasedDownloadableProducts;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\UrlInterface;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Sales\Model\Order;

/**
 *
 * Returns available downloadable products for customer
 */
class CustomerDownloadableProducts implements ResolverInterface
{
    const DOWNLOADABLE_STATUES = [
        'available',
        Order::STATE_COMPLETE
    ];

    /**
     * @var GetPurchasedDownloadableProducts
     */
    private $getPurchasedDownloadableProducts;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @param GetPurchasedDownloadableProducts $getPurchasedDownloadableProducts
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        GetPurchasedDownloadableProducts $getPurchasedDownloadableProducts,
        UrlInterface $urlBuilder
    ) {
        $this->getPurchasedDownloadableProducts = $getPurchasedDownloadableProducts;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        /** @var ContextInterface $context */
        if (false === $context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        $purchasedProducts = $this->getPurchasedDownloadableProducts->execute($context->getUserId());
        $productsData = [];

        /* The fields names are hardcoded since there's no existing name reference in the code */
        foreach ($purchasedProducts as $purchasedProduct) {
            if ($purchasedProduct['number_of_downloads_bought']) {
                $remainingDownloads = $purchasedProduct['number_of_downloads_bought'] -
                    $purchasedProduct['number_of_downloads_used'];
            } else {
                $remainingDownloads = __('Unlimited');
            }

            /* Generates download url only if customer has any available downloads left or order is accepted */
            $downloadUrl = null;
            if ($remainingDownloads != '0' && in_array($purchasedProduct['status'], self::DOWNLOADABLE_STATUES)) {
                $downloadUrl = $this->urlBuilder->getUrl(
                    'downloadable/download/link',
                    ['id' => $purchasedProduct['link_hash'], '_secure' => true]
                );
            }

            $productsData[] = [
                'order_id' => $purchasedProduct['order_id'],
                'order_increment_id' => $purchasedProduct['order_increment_id'],
                'date' => explode(' ', $purchasedProduct['created_at'])[0],
                'status' => $purchasedProduct['status'],
                'title' => $purchasedProduct['product_name'],
                'link_title' => $purchasedProduct['link_title'],
                'download_url' => $downloadUrl,
                'remaining_downloads' => $remainingDownloads
            ];
        }

        return ['items' => $productsData];
    }
}
