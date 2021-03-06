<?php

namespace Dotdigitalgroup\Email\Model;

class Abandoned extends \Magento\Framework\Model\AbstractModel
{
    /**
     * @var ResourceModel\Abandoned\Collection
     */
    public $abandonedCollectionFactory;
    /**
     * @var ResourceModel\Automation
     */
    private $automationResource;

    /**
     * @var \Magento\Framework\Stdlib\DateTime
     */
    private $dateTime;

    /**
     * @var \Dotdigitalgroup\Email\Helper\Data
     */
    private $helper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * Abandoned constructor.
     * @param ResourceModel\Abandoned\CollectionFactory $abandoned
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Stdlib\DateTime $dateTime
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Dotdigitalgroup\Email\Model\ResourceModel\Abandoned\CollectionFactory $abandoned,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Stdlib\DateTime $dateTime,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->abandonedCollectionFactory = $abandoned;
        $this->dateTime     = $dateTime;
        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Constructor.
     *
     * @return null
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init(\Dotdigitalgroup\Email\Model\ResourceModel\Abandoned::class);
    }

    /**
     * Prepare data to be saved to database.
     *
     * @return $this
     */
    public function beforeSave()
    {
        parent::beforeSave();
        if ($this->isObjectNew()) {
            $this->setCreatedAt($this->dateTime->formatDate(true));
        }
        $this->setUpdatedAt($this->dateTime->formatDate(true));

        return $this;
    }

    /**
     * @param $quoteId
     * @return mixed
     */
    public function loadByQuoteId($quoteId)
    {
        $collection = $this->abandonedCollectionFactory->create()
            ->addFieldToFilter('quote_id', $quoteId)
            ->setPageSize(1);

        return $collection->getFirstItem();
    }
}
