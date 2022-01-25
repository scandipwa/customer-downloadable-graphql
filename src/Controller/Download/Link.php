<?php
/**
 * ScandiPWA CustomerDownloadableGraphQL
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CustomerDownloadableGraphQl
 * @author      Daniels Stabulis <info@scandiweb.com>
 * @copyright   Copyright (c) 2021 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CustomerDownloadableGraphQl\Controller\Download;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Downloadable\Controller\Download\Link as SourceLink;
use Magento\Downloadable\Helper\Data;
use Magento\Downloadable\Helper\Download as DownloadHelper;
use Magento\Downloadable\Helper\File;
use Magento\Downloadable\Model\Link\Purchased;
use Magento\Downloadable\Model\Link\Purchased\Item as PurchasedLink;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\UrlInterface;

/**
 * Class Link executes download link action.
 */
class Link extends SourceLink
{
    /**
     * Download link action
     *
     * @return void|ResponseInterface
     */
    public function execute()
    {
        $session = $this->_getCustomerSession();

        $id = $this->getRequest()->getParam('id', 0);
        /** @var PurchasedLink $linkPurchasedItem */
        $linkPurchasedItem = $this->_objectManager->create(
            PurchasedLink::class
        )->load(
            $id,
            'link_hash'
        );

        if (!$linkPurchasedItem->getId()) {
            $this->messageManager->addNotice(__("We can't find the link you requested."));
            return $this->_redirect('/my-account/my-downloadable');
        }
        if (!$this->_objectManager->get(Data::class)->getIsShareable($linkPurchasedItem)) {
            $customerId = $session->getCustomerId();
            if (!$customerId) {
                /** @var Product $product */
                $product = $this->_objectManager->create(
                    Product::class
                )->load(
                    $linkPurchasedItem->getProductId()
                );
                if ($product->getId()) {
                    $notice = __(
                        'Please sign in to download your product or purchase <a href="%1">%2</a>.',
                        $product->getProductUrl(),
                        $product->getName()
                    );
                } else {
                    $notice = __('Please sign in to download your product.');
                }
                $this->messageManager->addNotice($notice);
                $session->authenticate();
                $session->setBeforeAuthUrl(
                    $this->_objectManager->create(
                        UrlInterface::class
                    )->getUrl(
                        '/my-account/my-downloadable',
                        ['_secure' => true]
                    )
                );
                return;
            }
            /** @var Purchased $linkPurchased */
            $linkPurchased = $this->_objectManager->create(
                Purchased::class
            )->load(
                $linkPurchasedItem->getPurchasedId()
            );
            if ($linkPurchased->getCustomerId() !== $customerId) {
                $this->messageManager->addNotice(__("We can't find the link you requested."));
                return $this->_redirect('/my-account/my-downloadable');
            }
        }
        $downloadsLeft = $linkPurchasedItem->getNumberOfDownloadsBought() -
            $linkPurchasedItem->getNumberOfDownloadsUsed();

        $status = $linkPurchasedItem->getStatus();

        // can add $status === PurchasedLink::LINK_STATUS_AVAILABLE && for his 'if', if there are some errors
        if (($downloadsLeft || $linkPurchasedItem->getNumberOfDownloadsBought() == 0)) {
            $resource = '';
            $resourceType = '';
            if ($linkPurchasedItem->getLinkType() === DownloadHelper::LINK_TYPE_URL) {
                $resource = $linkPurchasedItem->getLinkUrl();
                $resourceType = DownloadHelper::LINK_TYPE_URL;
            } elseif ($linkPurchasedItem->getLinkType() === DownloadHelper::LINK_TYPE_FILE) {
                $resource = $this->_objectManager->get(
                    File::class
                )->getFilePath(
                    $this->_getLink()->getBasePath(),
                    $linkPurchasedItem->getLinkFile()
                );
                $resourceType = DownloadHelper::LINK_TYPE_FILE;
            }
            try {
                $this->_processDownload($resource, $resourceType);
                $linkPurchasedItem->setNumberOfDownloadsUsed($linkPurchasedItem->getNumberOfDownloadsUsed() + 1);

                if (!($downloadsLeft - 1) && $linkPurchasedItem->getNumberOfDownloadsBought() !== 0) {
                    $linkPurchasedItem->setStatus(PurchasedLink::LINK_STATUS_EXPIRED);
                }
                $linkPurchasedItem->save();
                // phpcs:ignore Magento2.Security.LanguageConstruct.ExitUsage
                exit(0);
            } catch (Exception $e) {
                $this->messageManager->addError(__('Something went wrong while getting the requested content.'));
            }
        } elseif ($status === PurchasedLink::LINK_STATUS_EXPIRED) {
            $this->messageManager->addNotice(__('The link has expired.'));
        } elseif ($status === PurchasedLink::LINK_STATUS_PENDING || $status === PurchasedLink::LINK_STATUS_PAYMENT_REVIEW
        ) {
            $this->messageManager->addNotice(__('The link is not available.'));
        } else {
            $this->messageManager->addError(__('Something went wrong while getting the requested content.'));
        }
        return $this->_redirect('/my-account/my-downloadable');
    }
}
