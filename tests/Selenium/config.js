/**
 * Shop System Plugins:
 * - Terms of Use can be found under:
 * https://github.com/wirecard/magento-ee/blob/master/_TERMS_OF_USE
 * - License can be found under:
 * https://github.com/wirecard/magento-ee/blob/master/LICENSE
 */

exports.config = {
  url: process.env.TEST_URL || 'http://127.0.0.1:8000',
  payments: {
    creditCard: {
      label: 'Wirecard Credit Card',
      fields: {
        last_name: 'Lastname',
        account_number: '4012000300001003',
        card_security_code: '003'
      },
      expirationYear: 2023
    },
    creditCard3ds: {
      label: 'Wirecard Credit Card',
      fields: {
        last_name: 'Lastname',
        account_number: '4012000300001003',
        card_security_code: '003'
      },
      expirationYear: 2023,
      password: 'wirecard'
    },
    paypal: {
      label: 'Wirecard PayPal',
      fields: {
        email: 'paypal.magento1.buyer@wirecard.com',
        password: 'Wirecardbuyer'
      }
    },
    sepa: {
      label: 'Wirecard SEPA Direct Debit',
      fields: {
        'wirecardee-sepa--first-name': 'Firstname',
        'wirecardee-sepa--last-name': 'Lastname',
        'wirecardee-sepa--iban': 'DE42512308000000060004'
      }
    },
    sofort: {
      label: 'Wirecard Sofort.',
      fields: {
        bankCode: '00000',
        userId: '1234',
        password: 'passwd',
        tan: '12345'
      }
    },
    ideal: {
      label: 'Wirecard iDEAL',
      fields: {
        'wirecardee-ideal--bic': 'INGBNL2A'
      }
    },
    eps: {
      label: 'Wirecard eps-Überweisung',
      fields: {
        'wirecardee-eps--bic': 'BWFBATW1XXX'
      }
    },
    giropay: {
      label: 'Wirecard Giropay',
      fields: {
        'wirecardee-giropay--bic': 'GENODETT488'
      },
      simulatorFields: {
        sc: 10,
        extensionSc: 4000
      }
    },
    maestro: {
      label: 'Wirecard Maestro SecureCode',
      fields: {
        last_name: 'Lastname',
        account_number: '6799860300001000003',
        card_security_code: '003'
      },
      expirationYear: '2023',
      password: 'wirecard'
    },
    alipay: {
      label: 'Wirecard Alipay Cross-border',
      fields: {
        email: 'alipaytest20091@gmail.com',
        password: '111111',
        paymentPasswordDigit: '1'
      }
    },
    masterpass: {
      label: 'Wirecard Masterpass',
      fields: {
        email: 'masterpass@mailadresse.net',
        password: 'WirecardPass42'
      }
    },
    poi: {
      label: 'Wirecard Payment On Invoice'
    },
    pia: {
      label: 'Wirecard Payment In Advance'
    },
    payolution: {
      label: 'Wirecard Guaranteed Invoice by payolution',
      fields: {
        'wirecardee-payolution--dob-day': '27',
        'wirecardee-payolution--dob-month': '10',
        'wirecardee-payolution--dob-year': '1990'
      }
    },
    ratepay: {
      label: 'Wirecard Guaranteed Invoice by Wirecard',
      fields: {
        'wirecardee-ratepay--dob-day': '27',
        'wirecardee-ratepay--dob-month': '10',
        'wirecardee-ratepay--dob-year': '1990'
      }
    }
  }
};


/**
 * List of browsers to test against.
 * See https://www.browserstack.com/automate/capabilities
 */
const WINDOWS = {
  name: 'Windows',
  versions: {
    win10: '10',
    win8: '8',
    win7: '7'
  }
};

const OSX = {
  name: 'OS X',
  versions: {
    highSierra: 'High Sierra', // 10.13
    sierra: 'Sierra' // 10.12
  }
};

const CHROME = {
  name: 'Chrome',
  currentVersion: '72.0'
};

const FIREFOX = {
  name: 'Firefox',
  currentVersion: '62.0'
};

const OPERA = {
  name: 'Opera',
  currentVersion: '12.16'
};

const IE = {
  name: 'IE',
  versions: {
    ie8: '8.0',
    ie9: '9.0',
    ie10: '10.0',
    ie11: '11.0'
  }
};

const SAFARI = {
  name: 'Safari',
  versions: {
    v11_1: '11.1', // Current, only available for High Sierra
    v10_1: '10.1' // Only available for Sierra
  }
};

const ANDROID_7_DEVICE = {
  name: 'Samsung Galaxy S8',
  version: '7.0'
};

const ANDROID_8_DEVICE = {
  name: 'Samsung Galaxy S9',
  version: '8.0'
};

const IOS_10_DEVICE = {
  name: 'iPhone 7',
  version: '10.3'
};

const IOS_11_DEVICE = {
  name: 'iPhone 8',
  version: '11.0'
};

const DEFAULT_RESOLUTION = '1920x1080';

exports.browsers = [
  // WINDOWS
  {
    browserName: CHROME.name,
    browser_version: CHROME.currentVersion,
    os: WINDOWS.name,
    os_version: WINDOWS.versions.win10,
    resolution: DEFAULT_RESOLUTION
  }//,
  // {
  // browserName: FIREFOX.name,
  // browser_version: FIREFOX.currentVersion,
  // os: WINDOWS.name,
  // os_version: WINDOWS.versions.win8,
  // resolution: DEFAULT_RESOLUTION
  // },
  // {
  // browserName: OPERA.name,
  // browser_version: OPERA.currentVersion,
  // os: WINDOWS.name,
  // os_version: WINDOWS.versions.win8,
  // resolution: DEFAULT_RESOLUTION
  // },
  // {
  // browserName: IE.name,
  // browser_version: IE.versions.ie8,
  // os: WINDOWS.name,
  // os_version: WINDOWS.versions.win7,
  // resolution: DEFAULT_RESOLUTION
  // },
  // {
  // browserName: IE.name,
  // browser_version: IE.versions.ie9,
  // os: WINDOWS.name,
  // os_version: WINDOWS.versions.win7,
  // resolution: DEFAULT_RESOLUTION
  // },
  // {
  // browserName: IE.name,
  // browser_version: IE.versions.ie10,
  // os: WINDOWS.name,
  // os_version: WINDOWS.versions.win7,
  // resolution: DEFAULT_RESOLUTION
  // },
  // {
  // browserName: IE.name,
  // browser_version: IE.versions.ie11,
  // os: WINDOWS.name,
  // os_version: WINDOWS.versions.win7,
  // resolution: DEFAULT_RESOLUTION
  // },
  // // APPLE
  // {
  // browserName: CHROME.name,
  // browser_version: CHROME.currentVersion,
  // os: OSX.name,
  // os_version: OSX.versions.highSierra,
  // resolution: DEFAULT_RESOLUTION
  // },
  // {
  // browserName: CHROME.name,
  // browser_version: CHROME.currentVersion,
  // os: OSX.name,
  // os_version: OSX.versions.sierra,
  // resolution: DEFAULT_RESOLUTION
  // },
  // {
  // browserName: SAFARI.name,
  // browser_version: SAFARI.versions.v11_1,
  // os: OSX.name,
  // os_version: OSX.versions.highSierra,
  // resolution: DEFAULT_RESOLUTION
  // },
  // {
  // browserName: SAFARI.name,
  // browser_version: SAFARI.versions.v10_1,
  // os: OSX.name,
  // os_version: OSX.versions.sierra,
  // resolution: DEFAULT_RESOLUTION
  // },
  // // MOBILE: ANDROID
  // {
  // browserName: CHROME.name,
  // os: ANDROID_7_DEVICE.name,
  // os_version: ANDROID_7_DEVICE.version,
  // real_mobile: 'true'
  // },
  // {
  // browserName: CHROME.name,
  // os: ANDROID_8_DEVICE.name,
  // os_version: ANDROID_8_DEVICE.version,
  // real_mobile: 'true'
  // },
  // {
  // browserName: CHROME.name,
  // device: ANDROID_8_DEVICE.name,
  // os_version: ANDROID_8_DEVICE.version,
  // real_mobile: 'true',
  // deviceOrientation: 'landscape'
  // },
  // // MOBILE: iOS
  // {
  // browserName: SAFARI.name,
  // os: IOS_10_DEVICE.name,
  // os_version: IOS_10_DEVICE.version,
  // real_mobile: 'true'
  // },
  // {
  // browserName: SAFARI.name,
  // os: IOS_11_DEVICE.name,
  // os_version: IOS_11_DEVICE.version,
  // real_mobile: 'true'
  // },
  // {
  // browserName: SAFARI.name,
  // os: IOS_11_DEVICE.name,
  // os_version: IOS_11_DEVICE.version,
  // real_mobile: 'true',
  // deviceOrientation: 'landscape'
  // }
];

/**
 * List of tests to be executed. All tests must be located in `./Tests/Selenium`.
 */
exports.tests = [
  {
    file: 'Payments/CreditCardTest',
    timeout: 120000
  },
  {
    file: 'Payments/CreditCard3dsTest',
    timeout: 120000
  },
  {
    file: 'Payments/SepaTest',
    timeout: 90000
  },
  {
    file: 'Payments/SofortTest',
    timeout: 120000
  },
  {
    file: 'Payments/EpsTest',
    timeout: 60000,
  },
  {
    file: 'Payments/GiropayTest',
    timeout: 120000,
  },
  {
    file: 'Payments/IdealTest',
    timeout: 120000,
  },
  {
    file: 'Payments/MaestroTest',
    timeout: 120000,
  },
  {
    file: 'Payments/PoiTest',
    timeout: 120000,
  },
  {
    file: 'Payments/PiaTest',
    timeout: 120000,
  },
  {
    file: 'Payments/AlipayTest',
    timeout: 120000,
  },
  {
    file: 'Payments/PayolutionTest',
    timeout: 120000
  },
  {
    file: 'Payments/RatepayTest',
    timeout: 120000
  }
  //Add Payments/MasterpassTest and Payments/PayPalTest
];
