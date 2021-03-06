<?php
class Gaiterjones_GDPR_Model_AccountDeletion
{
    /**
     * Remove customer details from the address
     *
     * @param Mage_Sales_Model_Order_Address|Mage_Sales_Model_Quote_Address $address
     */
    protected function anonymiseSaleAddress(&$address) {
        $address->setFirstname($this->getRandom());
        $address->setMiddlename($this->getRandom());
        $address->setLastname($this->getRandom());
        $address->setCompany($this->getRandom());
        $address->setEmail($this->getRandom('email'));
        $address->setRegion($this->getRandom());
        $address->setStreet($this->getRandom());
        $address->setCity($this->getRandom());
        $address->setPostcode($this->getRandom());
        $address->setTelephone($this->getRandom());
        $address->setFax($this->getRandom());
    }

    /**
     * Remove customer details from a quote or order
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $obj
     */
    protected function anonymiseSaleData(&$obj) {
        $obj->setCustomerFirstname($this->getRandom());
        $obj->setCustomerMiddlename($this->getRandom());
        $obj->setCustomerLastname($this->getRandom());
        $obj->setCustomerEmail($this->getRandom('email'));
        $this->anonymiseSaleAddress($obj->getBillingAddress());
        $this->anonymiseSaleAddress($obj->getShippingAddress());
        $obj->save();
    }

    /**
     * Remove customer details from Magento
     *
     * @param Mage_Customer_Model_Customer $customer
     */
    public function anonymiseCustomer($customer) {

        if (!Mage::helper('gaiterjones_gdpr')->debugMode())
        {
            // Anonymise sales order details
            $orders = Mage::getResourceModel('sales/order_collection')
                ->addFieldToSelect('*')
                ->addFieldToFilter('customer_id', $customer->getId());
            foreach ($orders as $order) {
                $this->anonymiseSaleData($order);
            }

            // Anonymise sales quote details
            $quotes = Mage::getModel('sales/quote')->getCollection()
                ->addFieldToSelect('*')
                ->addFieldToFilter('customer_id', $customer->getId());
            foreach($quotes as $quote) {
                $this->anonymiseSaleData($quote);
            }

            // Delete customer newsletter subscription
            /** @var Mage_Newsletter_Model_Subscriber $subscriber */
            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($customer->getEmail());
            if ($subscriber->getId()) {
                $subscriber->unsubscribe();
                $subscriber->delete();
            }

            // Finally, delete the customer
            $customer->delete();
        }

        // Send confirmation to customer
        /** @var Mage_Core_Model_Email_Template $email */
        $email = Mage::getModel('core/email_template')->loadDefault('gaiterjones_gdpr_success',Mage::getStoreConfig('general/locale/code', $customer->getData('store_id')));
        if ($email->getId()) {
            $email->sendTransactional($email->getId(), 'sales', $customer->getEmail(), $customer->getName(), array(
                'email' => $customer->getEmail(),
                'name' => $customer->getName(),
                'store' => Mage::app()->getStore()->getFrontendName()
            ), 0);
        }

        // send notification email
        //
        $this->sendMailAction($customer);
    }

    /**
     * @param string $type
     * @return null|string
     */
    public function getRandom($type = 'str') {
        $rand = substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', mt_rand(1,10))),1,10);
        if ($type == 'str') {
            return $rand;
        } else if ($type == 'email') {
            return $rand.'@'.$rand.'.com';
        }
        return null;
    }

    public function sendMailAction($customer){

        $html=$customer->getName(). ' account deleted!';
        $supportemail=Mage::getStoreConfig('trans_email/ident_support/email');

        $mail = Mage::getModel('core/email');
        $mail->setToName('GDPR Module');
        $mail->setToEmail($supportemail);
        $mail->setBody($html);
        $mail->setSubject('Account Deletion');
        $mail->setFromEmail($supportemail);
        $mail->setFromName("GDPR Module");
        $mail->setType('html');// YOu can use Html or text as Mail format

        try {

            $mail->send();
        }
        catch (Exception $e) {

        }
    }
}
