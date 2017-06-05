<?php

namespace Dotdigitalgroup\Email\Model\Newsletter;

use Magento\Framework\Exception\LocalizedException;

/**
 * Sync subscribers.
 */
class Subscriber
{
    const STATUS_SUBSCRIBED = 1;
    const STATUS_NOT_ACTIVE = 2;
    const STATUS_UNSUBSCRIBED = 3;
    const STATUS_UNCONFIRMED = 4;

    /**
     * @var
     */
    private $start;

    /**
     * Global number of subscriber updated.
     *
     * @var int
     */
    private $countSubscribers = 0;

    /**
     * @var \Dotdigitalgroup\Email\Helper\File
     */
    private $file;

    /**
     * @var \Dotdigitalgroup\Email\Helper\Data
     */
    private $helper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Dotdigitalgroup\Email\Model\ContactFactory
     */
    private $contactFactory;
    /**
     * @var \Magento\Newsletter\Model\SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * @var \Dotdigitalgroup\Email\Model\ImporterFactory
     */
    private $importerFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    private $orderCollection;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    private $timezone;
    /**
     * @var \Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory
     */
    private $subscribersCollection;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resource;

    /**
     * @var \Dotdigitalgroup\Email\Model\Apiconnector\SubscriberFactory
     */
    private $emailSubscriber;

    /**
     * @var \Dotdigitalgroup\Email\Model\ResourceModel\ContactFactory
     */
    private $emailContactResource;

    /**
     * Subscriber constructor.
     *
     * @param \Dotdigitalgroup\Email\Model\ContactFactory $contactFactory
     * @param \Dotdigitalgroup\Email\Helper\Data $helper
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollection
     * @param SubscriberExporter $subscriberExporter
     * @param SubscriberWithSalesExporter $subscriberWithSalesExporter
     * @param \Dotdigitalgroup\Email\Model\ResourceModel\ContactFactory $contactResourceFactory
     */
    public function __construct(
        \Dotdigitalgroup\Email\Model\ContactFactory $contactFactory,
        \Dotdigitalgroup\Email\Helper\Data $helper,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollection,
        \Dotdigitalgroup\Email\Model\Newsletter\SubscriberExporter $subscriberExporter,
        \Dotdigitalgroup\Email\Model\Newsletter\SubscriberWithSalesExporter $subscriberWithSalesExporter,
        \Dotdigitalgroup\Email\Model\ResourceModel\ContactFactory $contactResourceFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
    ) {
        $this->helper            = $helper;
        $this->contactFactory    = $contactFactory;
        $this->orderCollection   = $orderCollection;
        $this->subscriberExporter = $subscriberExporter;
        $this->subscriberWithSalesExporter = $subscriberWithSalesExporter;
        $this->emailContactResource = $contactResourceFactory;
        $this->timezone = $timezone;
    }

    /**
     * @return array
     */
    public function sync()
    {
        $response    = ['success' => true, 'message' => ''];
        $this->start = microtime(true);
        $websites    = $this->helper->getWebsites(true);

        foreach ($websites as $website) {
            //if subscriber is enabled and mapped
            $apiEnabled = $this->helper->isEnabled($website->getid());
            $subscriberEnabled
                = $this->helper->isSubscriberSyncEnabled($website->getid());
            $addressBook
                = $this->helper->getSubscriberAddressBook($website->getId());
            //enabled and mapped
            if ($apiEnabled && $addressBook && $subscriberEnabled) {
                //ready to start sync
                $numUpdated = $this->exportSubscribersPerWebsite($website);

                // show message for any number of customers
                if ($numUpdated) {
                    $response['message'] .= $website->getName() . ',  count = ' . $numUpdated;
                }
            }
        }
        //sync proccessed
        if ($this->countSubscribers) {
            $message = '----------- Subscribers sync ----------- : ' . gmdate('H:i:s', microtime(true) - $this->start) .
                ', updated = ' . $this->countSubscribers;
            $this->helper->log($message);
            $message .= $response['message'];
            $response['message'] = $message;
        }

        return $response;
    }

    /**
     * Export subscribers per website.
     *
     * @param $website
     *
     * @return int
     *
     * @throws LocalizedException
     */
    public function exportSubscribersPerWebsite($website)
    {
        $updated = 0;
        $limit = $this->helper->getSyncLimit($website->getId());
        //subscriber collection to import
        $emailContactModel = $this->contactFactory->create();
        //Customer Subscribers
        $subscribersAreCustomers = $emailContactModel->getSubscribersToImport($website, $limit);
        //Guest Subscribers
        $subscribersAreGuest = $emailContactModel->getSubscribersToImport($website, $limit, false);
        $subscribersGuestEmails = $subscribersAreGuest->getColumnValues('email');
        $existInSales = [];
        if (! empty($subscribersGuestEmails)) {
            $existInSales = $this->checkInSales($subscribersGuestEmails);
        }
        $emailsNotInSales = array_diff($subscribersGuestEmails, $existInSales);
        $customerSubscribers = $subscribersAreCustomers->getColumnValues('email');
        $emailsWithNoSaleData = array_merge($emailsNotInSales, $customerSubscribers);
        //subscriber that are customer or/and the one that do not exist in sales order table.
        $subscribersWithNoSaleData = [];
        if (! empty($emailsWithNoSaleData)) {
            $subscribersWithNoSaleData = $emailContactModel
                ->getSubscribersToImportFromEmails($emailsWithNoSaleData);
        }
        if (! empty($subscribersWithNoSaleData)) {
            $updated += $this->subscriberExporter->exportSubscribers(
                $website,
                $subscribersWithNoSaleData
            );
            //add updated number for the website
            $this->countSubscribers += $updated;
        }
        //subscriber that are guest and also exist in sales order table.
        $subscribersWithSaleData = [];
        if (! empty($existInSales)) {
            $subscribersWithSaleData = $emailContactModel->getSubscribersToImportFromEmails($existInSales);
        }

        if (! empty($subscribersWithSaleData)) {
            $updated += $this->subscriberWithSalesExporter->exportSubscribersWithSales($website, $subscribersWithSaleData);
            //add updated number for the website
            $this->countSubscribers += $updated;
        }
        return $updated;
    }

    /**
     * Check emails exist in sales order table
     *
     * @param $emails
     * @return array
     */
    public function checkInSales($emails)
    {
        $collection = $this->orderCollection->create()
            ->addFieldToFilter('customer_email', ['in' => $emails]);
        return $collection->getColumnValues('customer_email');
    }

    /**
     * Un-subscribe suppressed contacts.
     * @return mixed
     */
    public function unsubscribe()
    {
        $limit = 5;
        $maxToSelect = 1000;
        $result['customers'] = 0;
        $date = $this->timezone->date()->sub(\DateInterval::createFromDateString('24 hours'));
        $suppressedEmails = [];

        // Datetime format string
        $dateString = $date->format(\DateTime::W3C);

        /**
         * Sync all suppressed for each store
         */
        $websites = $this->helper->getWebsites(true);
        foreach ($websites as $website) {
            $client = $this->helper->getWebsiteApiClient($website);
            $skip = $i = 0;
            $contacts = [];

            // Not enabled and valid credentials
            if (! $client) {
                continue;
            }

            //there is a maximum of request we need to loop to get more suppressed contacts
            for ($i=0; $i<= $limit;$i++) {
                $apiContacts = $client->getContactsSuppressedSinceDate($dateString, $maxToSelect , $skip);

                // skip no more contacts or the api request failed
                if(empty($apiContacts) || isset($apiContacts->message)) {
                    break;
                }
                $contacts = array_merge($contacts, $apiContacts);
                $skip += 1000;
            }

            // Contacts to un-subscribe
            foreach ($contacts as $apiContact) {
                if (isset($apiContact->suppressedContact)) {
                    $suppressedContact = $apiContact->suppressedContact;
                    $suppressedEmails[] = $suppressedContact->email;
                }
            }
        }
        //Mark suppressed contacts
        if (! empty($suppressedEmails)) {
            $this->emailContactResource->create()->unsubscribe($suppressedEmails);
        }
        return $result;
    }
}
