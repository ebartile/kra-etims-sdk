<p align="center">
  <a href="https://paybill.ke" target="_blank">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://paybill.ke/logo-wordmark--dark.png">
      <img src="https://paybill.ke/logo-wordmark--light.png" width="180" alt="Paybill Kenya Logo">
    </picture>
  </a>
</p>

# KRA eTIMS OSCU API Integration SDK (PHP)

![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)
![License](https://img.shields.io/badge/License-MIT-green)
![KRA eTIMS](https://img.shields.io/badge/KRA-eTIMS_OSCU_API-0066CC)
![Postman Compliant](https://img.shields.io/badge/Postman-Compliant-FF6C37?logo=postman)
![PHPUnit Tested](https://img.shields.io/badge/Tests-PHPUnit-3776AB?logo=php)

A production-ready **PHP SDK** for integrating with the Kenya Revenue Authority (KRA) **OSCU eTIMS API**. Built to match the official Postman collection specifications with strict payload compliance, token management, and comprehensive validation.

> ⚠️ **Critical Clarification**: Use this SDK ONLY for OSCU integrations

## Author
**Bartile Emmanuel**  
📧 ebartile@gmail.com | 📱 +254757807150  
*Lead Developer, Paybill Kenya*

---

## Table of Contents
- [Critical Requirements](#critical-requirements)
- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage Guide](#usage-guide)
- [API Reference](#api-reference)
- [Field Validation Rules](#field-validation-rules)
- [Error Handling](#error-handling)
- [Troubleshooting](#troubleshooting)
- [Support](#support)
- [License](#license)

---

## Critical Requirements

### 1. Environment Variables (Required)
```env
KRA_CONSUMER_KEY=your_sandbox_key
KRA_CONSUMER_SECRET=your_sandbox_secret
KRA_TIN=A000000000X
KRA_BHF_ID=01          # Branch ID (default: '01')
DEVICE_SERIAL=dvc12345 # Any value accepted in sandbox
CMC_KEY=               # Optional - populated after selectInitOsdcInfo
```

### 2. Invoice Numbering Rules
- **MUST be sequential integers** (`1`, `2`, `3...`) – **NOT strings** (`INV001`)
- Must be unique per branch office (`bhfId`)
- Cannot be reused even after cancellation
- KRA rejects non-integer invoice numbers with `resultCd: 500`

### 3. Date Format Specifications
| Field | Format | Example | Validation Rule |
|-------|--------|---------|-----------------|
| `salesDt`, `pchsDt`, `ocrnDt` | `YYYYMMDD` | `20260131` | Cannot be future date |
| `cfmDt`, `stockRlsDt` | `YYYYMMDDHHmmss` | `20260131143022` | Must be current/past |
| `lastReqDt` | `YYYYMMDDHHmmss` | `20260130143022` | Cannot be future date; max 7 days old |

---

## Features

✅ **Postman Collection Compliance**  
- 100% payload alignment with official KRA Postman collection for OSCU API  
- Flat endpoint paths (`/saveStockIO`, not `/insert/stockIO`)  
- All 10 functional categories implemented (see [API Reference](#api-reference))

✅ **Token Lifecycle Management**  
- Automatic token caching with 60-second buffer  
- Transparent refresh on 401 errors  
- File-based cache (`/tmp/kra_etims_token.json` by default)

✅ **Comprehensive Validation**  
- Respect\Validation schemas matching KRA specifications  
- Field-level validation with human-readable errors  
- Date format enforcement (`YYYYMMDDHHmmss`)  
- Tax category validation (A/B/C/D/E)

✅ **Production Ready**  
- SSL verification enabled by default  
- Timeout configuration (default: 30s)  
- Environment-aware (sandbox/production)  
- Detailed error diagnostics with KRA fault strings

---

## Installation

```bash
composer require paybilldev/kra-etims-sdk
```

### Requirements
- PHP 8.1+
- cURL extension (with SSL support)
- JSON extension
- Respect\Validation (`composer require respect/validation`)

---

## Configuration (Exact Match to Example Script)

```php
<?php
return [
    'env' => 'sbx', // 'sbx' for sandbox, 'prod' for production

    'cache_file' => sys_get_temp_dir() . '/kra_etims_token.json',

    'auth' => [
        'sbx' => [
            'token_url'       => 'https://sbx.kra.go.ke/v1/token/generate', // NO trailing spaces!
            'consumer_key'    => getenv('KRA_CONSUMER_KEY'),
            'consumer_secret' => getenv('KRA_CONSUMER_SECRET'),
        ],
        'prod' => [
            'token_url'       => 'https://kra.go.ke/v1/token/generate',
            'consumer_key'    => getenv('KRA_PROD_CONSUMER_KEY'),
            'consumer_secret' => getenv('KRA_PROD_CONSUMER_SECRET'),
        ],
    ],

    'api' => [
        'sbx' => [
            'base_url' => 'https://etims-api-sbx.kra.go.ke/etims-api', // NO trailing spaces!
        ],
        'prod' => [
            'base_url' => 'https://etims-api.kra.go.ke/etims-api',
        ],
    ],

    'http' => [
        'timeout' => 30,
    ],

    'oscu' => [
        'tin'           => getenv('KRA_TIN'),
        'bhf_id'        => getenv('KRA_BHF_ID') ?: '01',
        'device_serial' => getenv('DEVICE_SERIAL'),
        'cmc_key'       => getenv('CMC_KEY') ?: '', // Populated AFTER selectInitOsdcInfo
    ],
];
```

> ⚠️ **Critical Fix**: URLs **MUST NOT** have trailing spaces (common copy-paste error causing `cURL error 3`)

---

## Usage Guide (Matches Example Script Exactly)

### Step 1: Bootstrap SDK
```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use KraEtimsSdk\Services\AuthClient;
use KraEtimsSdk\Services\EtimsClient;

$config = require __DIR__ . '/config.php';

$auth  = new AuthClient($config);
$etims = new EtimsClient($config, $auth);
```

### Step 2: Authenticate
```php
// Clear cache and force fresh token
$auth->forgetToken();
$token = $auth->token(true);
echo "✅ Token OK: " . substr($token,0,25) . "...\n";
```

### Step 3: Get Branch Communication Key (Required for Branch Operations)
```php
// ONLY required for Steps 10-12 (saveBranchUser, saveBranchInsurance, etc.)
$init = $etims->selectInitOsdcInfo([
    'tin'      => $config['oscu']['tin'],
    'bhfId'    => $config['oscu']['bhf_id'],
    'dvcSrlNo' => $config['oscu']['device_serial'],
]);
$config['oscu']['cmc_key'] = $init['cmcKey'] ?? abort($init['resultMsg']);

// Recreate client with updated cmc_key
$etims = new EtimsClient($config, $auth);
```

### Step 4: Business Operations (All Working Endpoints from Example)
```php
// Code List Search
$codes = $etims->selectCodeList(['lastReqDt' => '20260130000000']);

// Customer Search
$customers = $etims->selectCustomer(['custmTin' => 'A123456789Z']);

// Save Item
$etims->saveItem([
    'itemCd'    => 'KE1NTXU0000006',
    'itemClsCd' => '5059690800',
    'itemNm'    => 'test material item3',
    'orgnNatCd' => 'KE',
    'pkgUnitCd' => 'NT',
    'qtyUnitCd' => 'U',
    'taxTyCd'   => 'B',
    'dftPrc'    => 3500,
    'useYn'     => 'Y',
    'regrId'    => 'Test',
    'regrNm'    => 'Test',
    'modrId'    => 'Test',
    'modrNm'    => 'Test',
]);

// Save Purchase Transaction (FULL tax breakdown required)
$etims->savePurchase([
    'invcNo'      => 1,
    'spplrTin'    => 'A123456789Z',
    'regTyCd'     => 'M',
    'pchsTyCd'    => 'N',
    'rcptTyCd'    => 'P',
    'pmtTyCd'     => '01',
    'pchsSttsCd'  => '02',
    'cfmDt'       => '20200127210300',
    'pchsDt'      => '20200127',
    'totItemCnt'  => 2,
    // ALL 15 TAX FIELDS REQUIRED
    'taxblAmtA' => 0, 'taxblAmtB' => 10500, 'taxblAmtC' => 0, 'taxblAmtD' => 0, 'taxblAmtE' => 0,
    'taxRtA' => 0, 'taxRtB' => 18, 'taxRtC' => 0, 'taxRtD' => 0, 'taxRtE' => 0,
    'taxAmtA' => 0, 'taxAmtB' => 1890, 'taxAmtC' => 0, 'taxAmtD' => 0, 'taxAmtE' => 0,
    'totTaxblAmt' => 10500,
    'totTaxAmt'   => 1890,
    'totAmt'      => 10500,
    'regrId' => 'Test', 'regrNm' => 'Test', 'modrId' => 'Test', 'modrNm' => 'Test',
    'itemList' => [ /* ... items array ... */ ],
]);

// Stock Operations
$etims->saveStockIO([...]);      // Stock in/out
$etims->saveStockMaster([...]);  // Stock master update
```

> 💡 **Complete working example**: See [`example`](example/basic.php) in repository root

---

## API Reference (OSCU Endpoints)

| Category | Endpoints (Method Names) |
|----------|--------------------------|
| **Authentication** | `token()` |
| **Initialization** | `selectInitOsdcInfo()` |
| **Data Management** | `selectCodeList()`, `selectCustomer()`, `selectNoticeList()`, `selectItemClasses()`, `selectBranches()` |
| **Item Management** | `saveItem()`, `selectItems()` |
| **Branch Management** | `saveBranchCustomer()`, `saveBranchUser()`, `saveBranchInsurance()` |
| **Purchase Management** | `savePurchase()`, `selectPurchases()` |
| **Stock Management** | `saveStockIO()`, `saveStockMaster()` |
| **Imports Management** | `selectImportedItems()`, `updateImportedItem()` |

> 🔑 **All methods use flat endpoint paths** (e.g., `/saveStockIO` not `/insert/stockIO`)

---

## Field Validation Rules

| Field | Validation Rule | Error if Violated |
|-------|-----------------|-------------------|
| `lastReqDt` | Cannot be future date; max 7 days old | `resultCd: 500` "Check request body" |
| `salesDt`/`pchsDt` | Must be `YYYYMMDD` format | `resultCd: 500` |
| `cfmDt` | Must be `YYYYMMDDHHmmss` format | `resultCd: 500` |
| `invcNo` | Must be sequential integer (not string) | `resultCd: 500` |
| `taxTyCd` | Must be A/B/C/D/E | `resultCd: 500` |
| `itemCd` | Must follow KRA format (`KE[0-9A-Z]{12}`) | `resultCd: 500` |
| `pkg` | Must be ≥ 1 | `resultCd: 500` |
| `qty` | Must be > 0.001 | `resultCd: 500` |

---

## Error Handling

### Exception Types
| Exception | When Thrown | Example |
|-----------|-------------|---------|
| `AuthenticationException` | Token generation fails | Invalid consumer key/secret |
| `ApiException` | KRA business error (`resultCd !== '000'`) | `resultCd: 500` (invalid payload) |
| `ValidationException` | Payload fails schema validation | Missing required field |

### Handling Pattern
```php
try {
    $response = $etims->savePurchase($payload);
    
    if ($response['resultCd'] === '000') {
        echo "✅ Success: {$response['resultMsg']}\n";
    } else {
        echo "❌ Business error ({$response['resultCd']}): {$response['resultMsg']}\n";
    }
    
} catch (AuthenticationException $e) {
    echo "❌ Auth failed: {$e->getMessage()}\n";
    
} catch (ApiException $e) {
    echo "❌ API error ({$e->getErrorCode()}): {$e->getMessage()}\n";
    
    // Get full KRA response
    $details = $e->getDetails();
    if (isset($details['resultMsg'])) {
        echo "KRA Message: {$details['resultMsg']}\n";
    }
    
} catch (ValidationException $e) {
    echo "❌ Validation failed:\n";
    foreach ($e->getErrors() as $field => $msg) {
        echo "  • {$field}: {$msg}\n";
    }
}
```

### Common KRA Error Codes
| Code | Meaning | Solution |
|------|---------|----------|
| `000` | Success | ✅ Operation completed |
| `500` | "Check request body" | Validate payload against Postman schema |
| `501` | "Mandatory information missing" | Check required fields |
| `502` | "Invalid format" | Fix date formats / data types |
| `503` | "Data not found" | Verify TIN/branch/item exists |
| `504` | "Duplicate data" | Use unique invoice number |
| `401` | "Unauthorized" | Check token validity |

---

## Troubleshooting

### ❌ "cURL error 3: URL using bad/illegal format"
**Cause**: Trailing spaces in base URLs (common copy-paste error)  
**Fix**: Verify NO trailing spaces in config URLs:
```php
// WRONG (trailing spaces):
'base_url' => 'https://etims-api-sbx.kra.go.ke/etims-api  '

// CORRECT:
'base_url' => 'https://etims-api-sbx.kra.go.ke/etims-api'
```

### ❌ "cmcKey not found" in branch operations
**Cause**: Skipping `selectInitOsdcInfo()` before `saveBranchUser`/`saveBranchInsurance`  
**Fix**: 
```php
// MUST execute BEFORE branch operations
$init = $etims->selectInitOsdcInfo([...]);
$config['oscu']['cmc_key'] = $init['cmcKey'];
$etims = new EtimsClient($config, $auth); // Recreate client
```

### ❌ Invoice number rejected (`resultCd: 500`)
**Cause**: Using string prefix (`INV001`) instead of integer  
**Fix**: Use sequential integers:
```php
'invcNo' => 1,  // ✅ Correct
// NOT 'INV001' ❌
```

### ❌ "Device serial number invalid" during initialization
**Cause**: Sandbox accepts ANY serial value (no pre-registration required for OSCU API)  
**Fix**: Use any alphanumeric value:
```php
'dvcSrlNo' => 'dvc12345', // Any value works in sandbox
```

---

## Support

### KRA Official Support Channels
| Purpose | Contact | Expected Response |
|---------|---------|-------------------|
| OSCU API technical issues | `apisupport@kra.go.ke` | 24-48 hours |
| Sandbox credentials | `timsupport@kra.go.ke` | 1-3 business days |
| Developer Portal access | `apisupport@kra.go.ke` | 24 hours |

### SDK Support
- **GitHub Issues**: [github.com/paybillke/kra-etims-php-sdk/issues](https://github.com/paybillke/kra-etims-php-sdk/issues)
- **Email**: ebartile@gmail.com (integration guidance)
- **Emergency Hotline**: +254757807150 (business hours only)

> ℹ️ **Disclaimer**: This SDK is not officially endorsed by Kenya Revenue Authority. Always verify integration requirements with KRA before production deployment. KRA may update API specifications without notice – monitor [GavaConnect Portal](https://developer.go.ke) for updates.

---

## License

MIT License

Copyright © 2024-2026 Bartile Emmanuel / Paybill Kenya

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

---

## Attribution

This SDK was developed by **Bartile Emmanuel** for Paybill Kenya to simplify KRA eTIMS OSCU API integration for Kenyan businesses. Special thanks to KRA for providing comprehensive API documentation and Postman collections.

> 🇰🇪 **Proudly Made in Kenya** – Supporting digital tax compliance for East Africa's largest economy.  
> *Tested on KRA Sandbox • Built with PHP 8.1+ • Production Ready*
