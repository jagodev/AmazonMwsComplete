<?php

namespace CaponicaAmazonMwsComplete\ClientPack;

use CaponicaAmazonMwsComplete\ClientPool\MwsClientPoolConfig;
use CaponicaAmazonMwsComplete\AmazonClient\MwsOrderClient;
use CaponicaAmazonMwsComplete\Domain\Throttle\ThrottleAwareClientPackInterface;
use CaponicaAmazonMwsComplete\Domain\Throttle\ThrottledRequestManager;

class MwsOrderClientPack extends MwsOrderClient implements ThrottleAwareClientPackInterface {
    const PARAM_AMAZON_ORDER_IDS            = 'AmazonOrderId';
    const PARAM_CREATED_AFTER               = 'CreatedAfter';
    const PARAM_CREATED_BEFORE              = 'CreatedBefore';
    const PARAM_MARKETPLACE_ID              = 'MarketplaceId';
    const PARAM_MARKETPLACE_ID_LIST         = 'MarketplaceId.Id.1';
    const PARAM_MERCHANT                    = 'SellerId';
    const PARAM_NEXT_TOKEN                  = 'NextToken';
    const PARAM_LAST_UPDATED_AFTER          = 'LastUpdatedAfter';
    const PARAM_LAST_UPDATED_BEFORE         = 'LastUpdatedBefore';
    const PARAM_ORDER_STATUS                = 'OrderStatus';
    const PARAM_FULFILLMENT_CHANNEL         = 'FulfillmentChannel';
    const PARAM_PAYMENT_METHOD              = 'PaymentMethod';
    const PARAM_BUYER_EMAIL                 = 'BuyerEmail';
    const PARAM_SELLER_ORDER_ID             = 'SellerOrderId';
    const PARAM_MAX_RESULTS_PER_PAGE        = 'MaxResultsPerPage';
    const PARAM_TFM_SHIPMENT_STATUS         = 'TFMShipmentStatus';

    const METHOD_GET_ORDER                  = 'getOrder';
    const METHOD_LIST_ORDERS                = 'listOrders';
    const METHOD_LIST_ORDERS_BY_NEXT_TOKEN  = 'listOrdersByNextToken';
    
    const PARAM_ORDER_STATUS_PENDING_AVAILABILITY   = 'PendingAvailability';
    const PARAM_ORDER_STATUS_PENDING                = 'Pending';
    const PARAM_ORDER_STATUS_UNSHIPPED              = 'Unshipped';
    const PARAM_ORDER_STATUS_PARTIALLY_SHIPPED      = 'PartiallyShipped';
    const PARAM_ORDER_STATUS_SHIPPED                = 'Shipped';
    const PARAM_ORDER_STATUS_INVOICE_UNCONFIRMED    = 'InvoiceUnconfirmed';
    const PARAM_ORDER_STATUS_CANCELED               = 'Canceled';
    const PARAM_ORDER_STATUS_UNFULFILLABLE          = 'Unfulfillable';

    /** @var string $marketplaceId      The MWS MarketplaceID string used in API connections */
    protected $marketplaceId;
    /** @var string $sellerId           The MWS SellerID string used in API connections */
    protected $sellerId;

    public function __construct(MwsClientPoolConfig $poolConfig) {
        $this->marketplaceId    = $poolConfig->getMarketplaceId($poolConfig->getAmazonSite());
        $this->sellerId         = $poolConfig->getSellerId();

        $this->initThrottleManager();

        parent::__construct(
            $poolConfig->getAccessKey(),
            $poolConfig->getSecretKey(),
            $poolConfig->getApplicationName(),
            $poolConfig->getApplicationVersion(),
            $poolConfig->getConfigForOrder($this->getServiceUrlSuffix())
        );
    }

    private function getServiceUrlSuffix() {
        return '/Orders/' . self::SERVICE_VERSION;
    }

    // ##################################################
    // #      basic wrappers for API calls go here      #
    // ##################################################
    public function callGetOrder($amazonOrderIds) {
        if (is_string($amazonOrderIds)) {
            $amazonOrderIds = explode(',', $amazonOrderIds);
        }

        $requestArray = [
            self::PARAM_MERCHANT            => $this->sellerId,
            self::PARAM_MARKETPLACE_ID      => $this->marketplaceId,
            self::PARAM_AMAZON_ORDER_IDS    => $amazonOrderIds,
        ];

        return CaponicaClientPack::throttledCall($this, self::METHOD_GET_ORDER, $requestArray);
    }
    public function callListOrdersByCreateDate(\DateTime $dateFrom, \DateTime $dateTo) {
        $requestArray = [
            self::PARAM_MERCHANT            => $this->sellerId,
            self::PARAM_MARKETPLACE_ID      => $this->marketplaceId,
            self::PARAM_CREATED_AFTER       => $dateFrom->format('c'),
            self::PARAM_CREATED_BEFORE      => $dateTo->format('c'),
        ];

        return CaponicaClientPack::throttledCall($this, self::METHOD_LIST_ORDERS, $requestArray);
    }
    public function callListOrdersByNextToken($nextToken) {
        $requestArray = [
            self::PARAM_MERCHANT            => $this->sellerId,
            self::PARAM_MARKETPLACE_ID      => $this->marketplaceId,
            self::PARAM_NEXT_TOKEN          => $nextToken,
        ];

        return CaponicaClientPack::throttledCall($this, self::METHOD_LIST_ORDERS_BY_NEXT_TOKEN, $requestArray);
    }

    /**
     * Get orders within specified time frame with optional order-statuses
     * 
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     * @param array|null $orderStatus
     * 
     * @return \MarketplaceWebServiceOrders_Model_ListOrdersResponse
     */
    public function callListOrdersWithStatus(\DateTime $dateFrom, \DateTime $dateTo, $orderStatus = null)
    {
        $requestArray = [
            self::PARAM_MERCHANT            => $this->sellerId,
            self::PARAM_MARKETPLACE_ID      => $this->marketplaceId,
            self::PARAM_CREATED_AFTER       => $dateFrom->format('c'),
            self::PARAM_CREATED_BEFORE      => $dateTo->format('c'),
        ];
        
        if ($orderStatus) {
            $requestArray[self::PARAM_ORDER_STATUS] = $orderStatus;
        }

        return CaponicaClientPack::throttledCall($this, self::METHOD_LIST_ORDERS, $requestArray);
    }

    // ###################################################
    // # ThrottleAwareClientPackInterface implementation #
    // ###################################################
    private $throttleManager;

    public function initThrottleManager() {
        $this->throttleManager = new ThrottledRequestManager(
            [
                self::METHOD_GET_ORDER                  => [6, 0.015],
                self::METHOD_LIST_ORDERS                => [6, 0.015],
                self::METHOD_LIST_ORDERS_BY_NEXT_TOKEN  => [null, null, null, self::METHOD_LIST_ORDERS],
            ]
        );
    }

    public function getThrottleManager() {
        return $this->throttleManager;
    }
}
