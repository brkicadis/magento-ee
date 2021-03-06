/**
 * Shop System Plugins:
 * - Terms of Use can be found under:
 * https://github.com/wirecard/magento-ee/blob/master/_TERMS_OF_USE
 * - License can be found under:
 * https://github.com/wirecard/magento-ee/blob/master/LICENSE
 */

const {
  getDriver,
  placeOrder,
  checkConfirmationPage,
  choosePaymentMethod,
  fillOutGuestCheckout,
  addProductToCartAndGotoCheckout,
  chooseFlatRateShipping
} = require('../common');
let driver;

describe('default test', () => {
  before(async () => {
    driver = await getDriver('default');
  });

  it('should check the default checkout', async () => {
    await addProductToCartAndGotoCheckout(driver, '/accessories/eyewear/aviator-sunglasses.html');
    await fillOutGuestCheckout(driver);
    await chooseFlatRateShipping(driver);
    await choosePaymentMethod(driver,'p_method_cashondelivery', 'Cash On Delivery');
    await placeOrder(driver);
    await checkConfirmationPage(driver, 'Thank you for your purchase!');
  });

  after(async () => driver.quit());
});
