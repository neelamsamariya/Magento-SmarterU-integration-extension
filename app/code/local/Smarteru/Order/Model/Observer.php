<?php
class Smarteru_Order_Model_Observer
{
	public function notify_smarteru(Varien_Event_Observer $observer)
	{
		try {
			$invoice = $observer->getEvent()->getInvoice();
			$order = $invoice->getOrder();
			
			//Only work with a paid invoice
			if($invoice->getState() == Mage_Sales_Model_Order_Invoice::STATE_PAID) {
				$su_order = array();
				
				// Line Items
				$su_lineitems = array();
				foreach($invoice->getAllItems() as $item) {
					//Only care about virtual products
					$orderItem = $item->getOrderItem();
					if ($orderItem->getIsVirtual()) {
						if (is_null($item->getPriceInclTax())) {
							continue;
						}
						
						$su_lineitem = array();
						$su_lineitem['SKU'] = $item->getSku();
						$su_lineitem['description'] = $item->getName();
						$su_lineitem['quantity'] = $orderItem->getQtyInvoiced();
						$su_lineitem['unitPrice'] = $item->getPriceInclTax();
						$su_lineitem['subtotalPrice'] = $item->getPriceInclTax();
						$su_lineitem['totalTax'] = $item->getPriceInclTax();
						$su_lineitem['totalPrice'] = $item->getPriceInclTax();

						array_push($su_lineitems, $su_lineitem);
					}
				}
				
				if (!count($su_lineitems)) {
					return;
				}
				
				$su_order['lineItems'] = $su_lineitems;
				
				$su_order['orderID'] = $order->getId();
				$su_order['orderDate'] = $invoice->getCreatedAt();
				$su_order['orderNumber'] = $order->getId();
				$su_order['subtotalPrice'] = $order->getSubtotal();
				$su_order['totalPrice'] = $order->getGrandTotal();
				$su_order['totalTax'] = $order->getTaxAmount();
				
				if ($order->getBillingAddress()) {
					$billing = array();
					$billing_address = $order->getBillingAddress();
					$billing_names = explode(' ', strrev($billing_address->getName()), 2);
					
					$billing['address1'] = $billing_address->getStreetFull();
					$billing['city'] = $billing_address->getCity();
					$billing['company'] = $billing_address->getCompany();
					$billing['country'] = $billing_address->getCountryModel()->getName();
					$billing['firstName'] = strrev($billing_names[1]);
					$billing['lastName'] = strrev($billing_names[0]);
					$billing['phone'] = $billing_address->getTelephone();
					$billing['province'] = $billing_address->getRegion();

					$su_order['billingAddress'] = $billing;
				}
				
				$customer = array();
				$customer['email'] = $order->getCustomerEmail();
				$customer['firstName'] = $order->getCustomerFirstname();
				$customer['lastName'] = $order->getCustomerLastname();
				
				$su_order['customer'] = $customer;
				
				$post_data = json_encode($su_order, JSON_PRETTY_PRINT);
				Mage::log('post items: ' . $post_data, null, 'smarteru.log');
				$baseUrlDomain = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
				
				// POST to SmarterU
				$curl = new Varien_Http_Adapter_Curl();
				$curl->setConfig(array(
					'timeout'   => 10    //Timeout in seconds
				));
				
				$headers = array(
								'Content-Type: application/json',
								'X-Magento-Domain: '.$baseUrlDomain,
								'Content-Length: '.strlen($post_data));
				
				$post_url = "https://app.smarteru.com/eCommerce/Magento/";
				$curl->write(Zend_Http_Client::POST, $post_url, '1.0', $headers, $post_data);
				$data = $curl->read();
				$curl->close();
			}
		}
		catch (Exception $e) {
			Mage::log('Exception: '.$e->getMessage(), null, 'smarteru.log');
		}
	}
}