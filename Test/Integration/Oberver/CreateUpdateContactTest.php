<?php

namespace Dotdigitalgroup\Email\Observer\Customer;

use Dotdigitalgroup\Email\Helper\Data;

/**
 * @magentoDbIsolation enabled
 * @magentoAdminConfigFixture connector/api/endpoint https://r1-api.dotmailer.com
 * @magentoAdminConfigFixture connector_api_credentials/api/enabled 1
 * @magentoAdminConfigFixture connector_api_credentials/api/username dummyusername
 * @magentoAdminConfigFixture connector_api_credentials/api/password dummypassword
 */
class CreateUpdateContactTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\TestFramework\ObjectManager
     */
    public $objectManager;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    public $customerFactory;

    /**
     * @var \Dotdigitalgroup\Email\Model\ContactFactory
     */
    public $contactFactory;

    /**
     * @var string
     */
    public $email;

    /**
     * @var object
     */
    public $customerModel;

    /**
     * @var int
     */
    public $customerId ;

    /**
     * @var \Dotdigitalgroup\Email\Helper\Data
     */
    private $helper;


    public function setup()
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->customerFactory = $this->objectManager->create('\Magento\Customer\Model\CustomerFactory');
        $this->contactFactory = $this->objectManager->create('\Dotdigitalgroup\Email\Model\ContactFactory');
        $this->helper = $this->objectManager->create('\Dotdigitalgroup\Email\Helper\Data');
    }

    public function prepareCustomerData()
    {
        /** @var \Magento\Store\Model\StoreManagerInterface $storeManager */
        $storeManager = $this->objectManager->create('\Magento\Store\Model\StoreManagerInterface');
        $store = $storeManager->getStore();
        $website = $store->getWebsite();
        $num = rand(500, 5000);
        $this->email = 'dummy' . $num . 'new@dotmailer.com';

        //pass the helper is enabled
        $mockHelper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->objectManager->addSharedInstance($mockHelper, Data::class);
        $mockHelper->method('isEnabled')->willReturn(1);

        $customerModel = $this->customerFactory->create();
        $customerModel->setStore($store);
        $customerModel->setWebsiteId($website->getId());
        $customerModel->setEmail($this->email);
        $customerModel->setFirstname('Firstname');
        $customerModel->setLastname('Lastname');
        $customerModel->setPassword('dummypassword');
        $customerModel->save();

        $this->customerId = $customerModel->getId();
        $this->customerModel = $customerModel;
    }

    public function test_contact_created_and_updated_successfully()
    {
        $this->prepareCustomerData();
        //update the email and save contact
        $updatedEmail = str_replace('new', 'updated', $this->email);

        //save new customer which will trigger the observer and will create new contact
        $contact = $this->loadContactByCustomerId();
        $emailCreated = $contact->getEmail();

        $this->updateCustomerEmail($updatedEmail);
        $contact = $this->loadContactByCustomerId();
        $emailUpdated = $contact->getEmail();

        //check contact created after the customer was saved
        $this->assertEquals($this->email, $emailCreated, 'Contact was not found');

        $this->assertEquals($updatedEmail, $emailUpdated, 'Updated contact was not found');
    }

    private function updateCustomerEmail($updatedEmail)
    {
        return $this->customerModel
            ->setEmail($updatedEmail)
            ->save();
    }

    /**
     * @return mixed
     */
    public function loadContactByCustomerId()
    {
        $contact = $this->contactFactory->create()
            ->loadByCustomerId($this->customerId);
        return $contact;
    }
}