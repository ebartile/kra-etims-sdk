<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use KraEtimsSdk\Services\AuthClient;
use KraEtimsSdk\Services\EtimsClient;
use KraEtimsSdk\Exceptions\ApiException;
use KraEtimsSdk\Exceptions\AuthenticationException;
use KraEtimsSdk\Exceptions\ValidationException;

// -------------------------------------------------
// 🔑 CRITICAL SETUP INSTRUCTIONS
// -------------------------------------------------
// BEFORE RUNNING THIS SCRIPT:
// 1. Obtain SANDBOX credentials from KRA:
//    • Email: timsupport@kra.go.ke
//    • Subject: "Request for OSCU Sandbox Test Credentials"
//    • Required: Approved device serial (dvcSrlNo), TIN, branch ID, APigee App ID
//
// 2. SET CREDENTIALS VIA ENVIRONMENT VARIABLES (SECURE):
//    export KRA_CONSUMER_KEY="your_key"
//    export KRA_CONSUMER_SECRET="your_secret"
//    export KRA_APIGEE_APP_ID="your_apigee_id"
//
// 3. ⚠️ DEVICE SERIAL MUST BE PRE-REGISTERED WITH KRA
//    • Sandbox test value (MAY work): dvcv1130
//    • NEVER use dynamic values like 'DEV2026...' → causes resultCd 901
// -------------------------------------------------

// -------------------------------------------------
// Configuration (EXACTLY MATCHES POSTMAN COLLECTION)
// -------------------------------------------------
$config = [
    // Environment MUST be 'sbx' (Postman uses {{url}} variable mapped to sbx/prod)
    'env' => 'sbx',

    'cache_file' => sys_get_temp_dir() . '/kra_etims_token.json',

    'auth' => [
        'sbx' => [
            // 🔑 TRIMMED URL (NO trailing spaces - critical fix!)
            'token_url'      => trim('https://sbx.kra.go.ke/v1/token/generate'),
            'consumer_key'   => 'YOUR_SANDBOX_CONSUMER_KEY',      // 🔑 REPLACE
            'consumer_secret'=> 'YOUR_SANDBOX_CONSUMER_SECRET',   // 🔑 REPLACE
        ],
        'prod' => [
            'token_url'      => trim('https://kra.go.ke/v1/token/generate'),
            'consumer_key'   => trim(getenv('KRA_PROD_CONSUMER_KEY') ?: 'YOUR_PROD_CONSUMER_KEY'),
            'consumer_secret'=> trim(getenv('KRA_PROD_CONSUMER_SECRET') ?: 'YOUR_PROD_CONSUMER_SECRET'),
        ],
    ],

    'api' => [
        'sbx' => [
            // 🔑 TRIMMED URL + APigee App ID (REQUIRED for ALL requests)
            'base_url'      => trim('https://sbx.kra.go.ke/etims-oscu/api/v1'),
            'apigee_app_id' => trim(getenv('KRA_APIGEE_APP_ID') ?: '3e18e00d-2f0e-4e93-9411-b698199683c5'),
        ],
        'prod' => [
            'base_url'      => trim('https://kra.go.ke/etims-oscu/api/v1'),
            'apigee_app_id' => trim(getenv('KRA_PROD_APIGEE_APP_ID') ?: 'YOUR_PROD_APIGEE_APP_ID'),
        ],
    ],

    'http' => [
        'timeout' => 30, // Increased for reliability
    ],

    'oscu' => [
        'tin'     => trim(getenv('KRA_TIN') ?: 'P051092286D'),    // Sandbox test TIN
        'bhf_id'  => trim(getenv('KRA_BHF_ID') ?: '00'),          // Sandbox test branch
        'cmc_key' => '',                                           // Set AFTER initialization
    ],

    // 🔑 ENDPOINT MAPPINGS EXACTLY AS IN POSTMAN COLLECTION
    'endpoints' => [
        // INITIALIZATION
        'initialize' => '/initialize',

        // BRANCH MANAGEMENT
        'branchInsuranceInfo'    => '/branchInsuranceInfo',
        'branchUserAccount'      => '/branchUserAccount',
        'branchSendCustomerInfo' => '/branchSendCustomerInfo',

        // DATA MANAGEMENT (EXACT Postman paths)
        'selectCodeList'     => '/selectCodeList',
        'selectItemClass'    => '/selectItemClass',
        'branchList'         => '/branchList',                    // ✅ NOT selectBhfList
        'customerPinInfo'    => '/customerPinInfo',
        'selectTaxpayerInfo' => '/selectTaxpayerInfo',
        'selectNoticeList'   => '/selectNoticeList',
        'selectCustomerList' => '/selectCustomerList',

        // IMPORTS
        'importedItemInfo'          => '/importedItemInfo',
        'importedItemConvertedInfo' => '/importedItemConvertedInfo',

        // ITEM MANAGEMENT
        'itemInfo'            => '/itemInfo',
        'saveItem'            => '/saveItem',
        'saveItemComposition' => '/saveItemComposition',

        // PURCHASE
        'getPurchaseTransactionInfo'  => '/getPurchaseTransactionInfo',
        'sendPurchaseTransactionInfo' => '/sendPurchaseTransactionInfo',

        // SALES (EXACT Postman paths)
        'sendSalesTransaction'   => '/sendSalesTransaction',
        'selectSalesTransactions'=> '/selectSalesTransactions',
        'selectInvoiceDetail'    => '/selectInvoiceDetail',

        // STOCK (NESTED PATHS - critical!)
        'insertStockIO'        => '/insert/stockIO',    // ✅ WITH SLASH
        'saveStockMaster'      => '/save/stockMaster',  // ✅ WITH SLASH
        'selectStockMoveLists' => '/selectStockMoveLists',
    ],
];

// -------------------------------------------------
// 🔒 VALIDATE CRITICAL CREDENTIALS BEFORE EXECUTION
// -------------------------------------------------
function validateConfig(array $config): void
{
    $missing = [];
    $sbx = $config['auth']['sbx'];
    
    if (strpos($sbx['consumer_key'], 'YOUR_') !== false) {
        $missing[] = 'KRA_CONSUMER_KEY (set via env var or config)';
    }
    if (strpos($sbx['consumer_secret'], 'YOUR_') !== false) {
        $missing[] = 'KRA_CONSUMER_SECRET (set via env var or config)';
    }
    if (strpos($config['api']['sbx']['apigee_app_id'], 'YOUR_') !== false) {
        $missing[] = 'KRA_APIGEE_APP_ID (set via env var or config - REQUIRED FOR ALL REQUESTS)';
    }
    
    if ($missing) {
        echo "\n❌ MISSING CREDENTIALS:\n";
        foreach ($missing as $item) {
            echo "   • $item\n";
        }
        echo "\n💡 SET VIA ENVIRONMENT VARIABLES:\n";
        echo "   export KRA_CONSUMER_KEY='your_key'\n";
        echo "   export KRA_CONSUMER_SECRET='your_secret'\n";
        echo "   export KRA_APIGEE_APP_ID='your_apigee_id'\n";
        echo "\n⚠️  DEVICE SERIAL WARNING:\n";
        echo "   You MUST use a KRA-approved device serial number.\n";
        echo "   Common sandbox test value: 'dvcv1130' (may work if pre-provisioned)\n";
        echo "   Contact timsupport@kra.go.ke to get approved credentials.\n";
        exit(1);
    }
}

// -------------------------------------------------
// Helper functions
// -------------------------------------------------
function printHeader(string $title): void
{
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "  $title\n";
    echo str_repeat('=', 70) . "\n\n";
}

function printError(string $message): void
{
    echo "❌ $message\n";
}

function printSuccess(string $message): void
{
    echo "✅ $message\n";
}

function printWarning(string $message): void
{
    echo "⚠️  $message\n";
}

function formatDate(string $modifier = '-7 days'): string
{
    // KRA rejects future dates for lastReqDt
    $dt = new DateTime($modifier);
    return $dt->format('YmdHis');
}

// -------------------------------------------------
// MAIN TEST FLOW
// -------------------------------------------------
printHeader('🚀 KRA eTIMS OSCU SDK TEST (Postman-Aligned)');
validateConfig($config);

// Bootstrap clients
$auth  = new AuthClient($config);
$etims = new EtimsClient($config, $auth);

// -------------------------------------------------
// STEP 1: AUTHENTICATION
// -------------------------------------------------
printHeader('STEP 1: GET ACCESS TOKEN');
try {
    // Clear cache first to ensure fresh token
    $auth->forgetToken();
    
    echo "Requesting token from: {$config['auth']['sbx']['token_url']}\n";
    $token = $auth->token(true); // Force fresh token
    
    echo "✓ Token received (first 30 chars): " . substr($token, 0, 30) . "...\n";
    printSuccess('Authentication successful');
} catch (AuthenticationException $e) {
    printError("Authentication failed: " . $e->getMessage());
    echo "\n💡 CHECK:\n";
    echo "   • Consumer key/secret correct?\n";
    echo "   • Token URL has NO trailing spaces?\n";
    exit(1);
} catch (Throwable $e) {
    printError("Authentication failed: " . $e->getMessage());
    exit(1);
}

// -------------------------------------------------
// STEP 2: OSCU INITIALIZATION (CRITICAL - HEADER RESTRICTIONS)
// -------------------------------------------------
printHeader('STEP 2: OSCU INITIALIZATION');
printWarning('⚠️  DEVICE SERIAL MUST BE PRE-REGISTERED WITH KRA SANDBOX');
printWarning('⚠️  Using unregistered device = resultCd 901 "not valid device"');

// 🔑 USE STATIC, PRE-APPROVED DEVICE SERIAL (NOT DYNAMIC!)
// Common sandbox test value (MAY work if KRA pre-provisioned it):
$approvedDeviceSerial = 'dvcv1130'; // ⚠️ REPLACE WITH YOUR KRA-APPROVED SERIAL

try {
    echo "Initializing with:\n";
    echo "  TIN: {$config['oscu']['tin']}\n";
    echo "  Branch ID: {$config['oscu']['bhf_id']}\n";
    echo "  Device Serial: $approvedDeviceSerial\n";
    echo "  Headers: ONLY apigee_app_id + Bearer token (NO tin/bhfId/cmcKey)\n\n";

    $response = $etims->initialize([
        'tin'      => $config['oscu']['tin'],
        'bhfId'    => $config['oscu']['bhf_id'],
        'dvcSrlNo' => $approvedDeviceSerial, // MUST BE PRE-REGISTERED
    ]);

    echo "✓ Initialization response:\n";
    print_r($response);

    // 🔑 EXTRACT cmcKey (KRA sandbox returns at ROOT level)
    $cmcKey = $response['cmcKey'] ?? ($response['data']['cmcKey'] ?? null);
    
    if (!$cmcKey) {
        throw new RuntimeException(
            "cmcKey not found in response. Check:\n" .
            "  • Device serial is APPROVED by KRA\n" .
            "  • TIN/branch ID match registered device\n" .
            "  • Response structure: " . json_encode($response)
        );
    }

    // Update config with cmcKey for subsequent requests
    $config['oscu']['cmc_key'] = $cmcKey;
    $etims = new EtimsClient($config, $auth); // Recreate with updated config

    printSuccess("OSCU initialized successfully");
    printSuccess("cmcKey: " . substr($cmcKey, 0, 15) . '...');
} catch (ApiException $e) {
    $details = $e->getDetails() ?? [];
    $resultCd = $details['resultCd'] ?? 'UNKNOWN';
    $resultMsg = $details['resultMsg'] ?? $e->getMessage();
    
    if ($resultCd === '901') {
        printError('DEVICE NOT VALID (resultCd 901)');
        echo "\n💡 SOLUTION:\n";
        echo "   1. Device serial '$approvedDeviceSerial' is NOT registered with KRA sandbox\n";
        echo "   2. Contact KRA support: timsupport@kra.go.ke\n";
        echo "   3. Request APPROVED sandbox device serial number\n";
        echo "   4. Use ONLY pre-approved serial in initialization\n";
        echo "\n✅ Known sandbox test values (MAY work):\n";
        echo "   • dvcv1130\n";
        echo "   • KRACU013000001\n";
        echo "\n❗ NEVER generate dynamic device serials – KRA rejects all unregistered values\n";
    } else {
        printError("Initialization failed: $resultMsg (Code: $resultCd)");
        if ($details) {
            echo "Response details:\n";
            print_r($details);
        }
    }
    exit(1);
} catch (Throwable $e) {
    printError("Initialization failed: " . $e->getMessage());
    exit(1);
}

// -------------------------------------------------
// STEP 3: FETCH CODE LIST (POST-INITIALIZATION)
// -------------------------------------------------
printHeader('STEP 3: FETCH CODE LIST (Requires cmcKey)');
try {
    $response = $etims->selectCodeList([
        'tin'       => $config['oscu']['tin'],
        'bhfId'     => $config['oscu']['bhf_id'],
        'lastReqDt' => formatDate('-7 days'), // NOT future date
    ]);

    $itemCount = count($response['itemList'] ?? []);
    echo "✓ Retrieved $itemCount code list items\n";
    
    if ($itemCount > 0) {
        echo "Sample items:\n";
        print_r(array_slice($response['itemList'], 0, 2));
    }
    
    printSuccess('Code list fetched successfully');
} catch (ApiException $e) {
    $details = $e->getDetails() ?? [];
    $resultCd = $details['resultCd'] ?? 'UNKNOWN';
    
    if ($resultCd === '902') {
        printError('INVALID cmcKey (resultCd 902)');
        echo "💡 Did you update config['oscu']['cmc_key'] after initialization?\n";
    } else {
        printError("Code list fetch failed: " . ($details['resultMsg'] ?? $e->getMessage()));
    }
    exit(1);
} catch (Throwable $e) {
    printError("Code list fetch failed: " . $e->getMessage());
    exit(1);
}

// -------------------------------------------------
// STEP 4: FETCH BRANCH LIST (CORRECT ENDPOINT NAME)
// -------------------------------------------------
printHeader('STEP 4: FETCH BRANCH LIST');
try {
    // ✅ CORRECT METHOD NAME: branchList() NOT selectBhfList()
    $response = $etims->branchList([
        'lastReqDt' => formatDate('-7 days'),
    ]);

    $itemCount = count($response['itemList'] ?? []);
    echo "✓ Retrieved $itemCount branches\n";
    printSuccess('Branch list fetched successfully');
} catch (Throwable $e) {
    printError("Branch list fetch failed: " . $e->getMessage());
    // Continue to next step (non-critical)
}

// -------------------------------------------------
// STEP 5: SEND SALES TRANSACTION (FULL POSTMAN PAYLOAD)
// -------------------------------------------------
printHeader('STEP 5: SEND SALES TRANSACTION (Postman-Aligned Payload)');

// 🔑 CRITICAL: Invoice numbers MUST be UNIQUE integers (not strings with prefixes)
// Use sequential integers as shown in Postman examples
$invoiceNumber = 1; // Start with 1 for sandbox testing

try {
    // ✅ EXACT STRUCTURE FROM POSTMAN COLLECTION (field-by-field match)
    $salesPayload = [
        'invcNo'        => $invoiceNumber,          // INTEGER (not string!)
        'orgInvcNo'     => 0,                       // 0 for new invoices
        'custTin'       => 'A123456789Z',           // Test customer TIN
        'custNm'        => 'Test Customer',
        'salesTyCd'     => 'N',                     // N=Normal, R=Return
        'rcptTyCd'      => 'R',                     // R=Receipt, P=Proforma, C=Credit Note
        'pmtTyCd'       => '01',                    // 01=Cash
        'salesSttsCd'   => '01',                    // 01=Completed
        'cfmDt'         => date('YmdHis'),          // Confirmation date: YYYYMMDDHHmmss
        'salesDt'       => date('Ymd'),             // Sales date: YYYYMMDD (NO time)
        'stockRlsDt'    => date('YmdHis'),          // Stock release: YYYYMMDDHHmmss
        'cnclReqDt'     => null,                    // Nullable
        'cnclDt'        => null,                    // Nullable
        'rfdDt'         => null,                    // Nullable
        'rfdRsnCd'      => null,                    // Nullable
        'totItemCnt'    => 1,
        // TAX BREAKDOWN BY CATEGORY (A/B/C/D/E) - REQUIRED FIELDS
        'taxblAmtA'     => 0.00,
        'taxblAmtB'     => 0.00,
        'taxblAmtC'     => 81000.00,                // Taxable amount for category C
        'taxblAmtD'     => 0.00,
        'taxblAmtE'     => 0.00,
        'taxRtA'        => 0.00,
        'taxRtB'        => 0.00,
        'taxRtC'        => 0.00,                    // Rate for category C (0% for exempt)
        'taxRtD'        => 0.00,
        'taxRtE'        => 0.00,
        'taxAmtA'       => 0.00,
        'taxAmtB'       => 0.00,
        'taxAmtC'       => 0.00,                    // Tax amount for category C
        'taxAmtD'       => 0.00,
        'taxAmtE'       => 0.00,
        'totTaxblAmt'   => 81000.00,
        'totTaxAmt'     => 0.00,
        'totAmt'        => 81000.00,
        'prchrAcptcYn'  => 'N',                     // Purchaser acceptance: Y/N
        'remark'        => 'Test transaction from SDK',
        'regrId'        => 'Admin',
        'regrNm'        => 'Admin',
        'modrId'        => 'Admin',
        'modrNm'        => 'Admin',
        // RECEIPT OBJECT (REQUIRED)
        'receipt' => [
            'custTin'       => 'A123456789Z',
            'custMblNo'     => null,
            'rptNo'         => 1,
            'rcptPbctDt'    => date('YmdHis'),
            'trdeNm'        => '',
            'adrs'          => '',
            'topMsg'        => 'Shopwithus',
            'btmMsg'        => 'Welcome',
            'prchrAcptcYn'  => 'N',
        ],
        // ITEM LIST (EXACT Postman structure)
        'itemList' => [
            [
                'itemSeq'    => 1,
                'itemCd'     => 'KE2NTBA00000001',  // Must exist in KRA system
                'itemClsCd'  => '1000000000',
                'itemNm'     => 'Brand A',
                'barCd'      => '',                  // Nullable but REQUIRED field
                'pkgUnitCd'  => 'NT',
                'pkg'        => 1,                   // Package quantity
                'qtyUnitCd'  => 'BA',
                'qty'        => 90.0,
                'prc'        => 1000.00,
                'splyAmt'    => 81000.00,
                'dcRt'       => 10.0,                // Discount rate %
                'dcAmt'      => 9000.00,             // Discount amount
                'isrccCd'    => null,                // Insurance code (nullable)
                'isrccNm'    => null,
                'isrcRt'     => null,
                'isrcAmt'    => null,
                'taxTyCd'    => 'C',                 // C = Zero-rated/Exempt
                'taxblAmt'   => 81000.00,
                'taxAmt'     => 0.00,
                'totAmt'     => 81000.00,            // splyAmt - dcAmt + taxAmt
            ],
        ],
    ];

    echo "Sending sales transaction with invoice #{$salesPayload['invcNo']}...\n";
    $response = $etims->sendSalesTransaction($salesPayload);

    echo "✓ Response:\n";
    print_r($response);
    
    if (($response['resultCd'] ?? '') === '0000') {
        printSuccess("Sales transaction sent successfully (resultCd: 0000)");
    } else {
        printWarning("Transaction accepted but check resultCd: " . ($response['resultCd'] ?? 'UNKNOWN'));
    }
} catch (ValidationException $e) {
    printError("Validation failed:");
    foreach ($e->getErrors() as $error) {
        echo "  • $error\n";
    }
    echo "\n💡 FIX: Ensure payload matches Postman structure EXACTLY\n";
    exit(1);
} catch (ApiException $e) {
    $details = $e->getDetails() ?? [];
    $resultCd = $details['resultCd'] ?? 'UNKNOWN';
    $resultMsg = $details['resultMsg'] ?? $e->getMessage();
    
    printError("API Error: $resultMsg (Code: $resultCd)");
    
    if ($resultCd === '500') {
        echo "💡 Common causes:\n";
        echo "   • Missing required field (check Postman example)\n";
        echo "   • Invalid tax category code (must be A/B/C/D/E)\n";
        echo "   • Future date in salesDt/cfmDt\n";
        echo "   • Invoice number already used\n";
    }
    
    if ($details) {
        echo "Response details:\n";
        print_r($details);
    }
    exit(1);
} catch (Throwable $e) {
    printError("Sales transaction failed: " . $e->getMessage());
    exit(1);
}

// -------------------------------------------------
// TEST SUMMARY
// -------------------------------------------------
printHeader('✅ TEST SUITE COMPLETED SUCCESSFULLY');

echo "\n";
echo "NEXT STEPS:\n";
echo "  1. ✅ Replace placeholder credentials with KRA-approved values\n";
echo "  2. ✅ Use ONLY pre-registered device serial (contact KRA for approval)\n";
echo "  3. ✅ Keep cmcKey secure (required for all post-initialization requests)\n";
echo "  4. ✅ Use SEQUENTIAL INTEGER invoice numbers (not strings with prefixes)\n";
echo "  5. ✅ Ensure lastReqDt is NEVER in the future\n";
echo "\n";
echo "CRITICAL REMINDERS:\n";
echo "  • apigee_app_id header is REQUIRED for ALL requests\n";
echo "  • Initialization uses ONLY apigee_app_id header (NO tin/bhfId/cmcKey)\n";
echo "  • All other endpoints require FULL headers (tin, bhfId, cmcKey, apigee_app_id)\n";
echo "  • Device serial registration is MANDATORY (infrastructure-level check)\n";
echo "  • Invoice numbers must be UNIQUE per branch\n";
echo "  • Date formats: YYYYMMDD (salesDt) / YYYYMMDDHHmmss (cfmDt, lastReqDt)\n";
echo "\n";
echo "SUPPORT:\n";
echo "  • KRA Sandbox Support: timsupport@kra.go.ke\n";
echo "  • API Technical Issues: apisupport@kra.go.ke\n";
echo "\n";