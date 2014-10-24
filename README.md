osCommerce 2.3.4 Bitcoin-Coinbase Module
------------------------------

<strong>©2014 BRIAN BALOGH</strong>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

Bitcoin osCommerce payment module using the coinbase.com service.

Installation
------------
1. Copy the coinbase-php directory into your osCommerce catalog directory
2. Copy coinbase_callback.php into your osCommerce catalog directory
3. Copy includes/modules/payment/bitcoin_coinbase.php into catalog/includes/modules/payment/
4. Copy includes/languages/english/modules/payment/bitcoin_coinbase.php into catalog/includes/languages/english/modules/payment/

Configuration
-------------
1. Create an API key at coinbase.com under Settings > API ACCESS
2. In your osCommerce admin panel under Modules > Payment, install the "Bitcoin via Coinbase" module
3. Fill out all of the configuration information:
	- Verify that the module is enabled.
	- Copy/Paste the API key you created in step 1 into the API Key field
	- Copy/Paste the API secret key you created in step 1 into the API Secret Key field
	- Choose a status for unpaid and paid orders, and those with payment errors (or leave the default values as defined).
	- Verify that the currencies displayed corresponds to what you want and to those accepted by coinbase.com (the defaults are what Coinbase accepts as of this writing).
	- Choose a sort order for displaying this payment option to visitors.  Lowest is displayed first.
	- Choose whether you want Coinbase's icons to be used in the checkout process 

Usage
-----
When a user chooses the "Bitcoin via Coinbase" payment method, they will be presented with an order summary as the next step (prices are shown in whatever currency they've selected for shopping).  Upon confirming their order, a payment window is displayed by coinbase.com.  Once payment is received, the order is submitted as normal.

In your Admin control panel, you can see the orders made via Bitcoins just as you could see for any other payment mode.  The status you selected in the configuration steps above will indicate whether the order has been paid for or an error has occurred.

Note: This extension does not provide a means of automatically pulling a current BTC exchange rate for presenting BTC prices to shoppers.

Version
-------
Version 1.0
- Tested and validated against osCommerce 2.3.4
