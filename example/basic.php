<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use KraEtimsSdk\Services\AuthOClient;
use KraEtimsSdk\Services\EtimsOClient;
use KraEtimsSdk\Exceptions\ApiException;
use KraEtimsSdk\Exceptions\AuthenticationException;

/* ---------------------------- Helpers ---------------------------- */
function abort(string $msg): void {
    echo "\n❌ $msg\n";
    exit(1);
}

function headerLine(string $title): void {
    echo "\n" . str_repeat('=', 70) . "\n$title\n" . str_repeat('=', 70) . "\n";
}

function lastReqDt(string $modifier = '-7 days'): string {
    return (new DateTime($modifier))->format('YmdHis');
}

/* ---------------------------- Validate Env ---------------------------- */
foreach (['KRA_CONSUMER_KEY','KRA_CONSUMER_SECRET','KRA_TIN','DEVICE_SERIAL'] as $env) {
    if (!getenv($env)) abort("Missing environment variable: $env");
}

/* ---------------------------- Config ---------------------------- */
$config = [
    'env' => 'sbx', // 'prod' for production
    'cache_file' => sys_get_temp_dir() . '/kra_etims_token.json',
    'auth' => [
        'sbx' => [
            'consumer_key'    => getenv('KRA_CONSUMER_KEY'),
            'consumer_secret' => getenv('KRA_CONSUMER_SECRET'),
        ],
        'prod' => [
            'consumer_key'    => getenv('KRA_CONSUMER_KEY'),
            'consumer_secret' => getenv('KRA_CONSUMER_SECRET'),
        ],
    ],
    'http' => ['timeout' => 30],
    'oscu' => [
        'tin'           => getenv('KRA_TIN'),
        'bhf_id'        => getenv('KRA_BHF_ID') ?: '01',
        'device_serial' => getenv('DEVICE_SERIAL'),
        'cmc_key'       => getenv('CMC_KEY') ?: '',
    ],
];

/* ---------------------------- Bootstrap SDK ---------------------------- */
$auth  = new AuthOClient($config);
$etims = new EtimsOClient($config, $auth);

/* ---------------------------- STEP 1: AUTH ---------------------------- */
headerLine('STEP 1: AUTHENTICATION');
try {
    $auth->forgetToken();  // Clear cached token
    $token = $auth->token(true); // Force refresh
    echo "✅ Token OK: " . substr($token,0,25) . "...\n";
} catch (AuthenticationException $e) {
    abort("Auth failed: {$e->getMessage()}");
}

/* ---------------------------- STEP 2: OSCU INITIALIZATION ---------------------------- */
// $init = $etims->selectInitOsdcInfo([
//     'tin' => $config['oscu']['tin'],
//     'bhfId' => $config['oscu']['bhf_id'],
//     'dvcSrlNo' => $config['oscu']['device_serial'],
// ]);
// $config['oscu']['cmc_key'] = $init['cmcKey'] ?? abort($init['resultMsg']);

/* ---------------------------- STEP 3: CODE LIST SEARCH ---------------------------- */
headerLine('STEP 3: CODE LIST SEARCH');

try {
    $codes = $etims->selectCodeList(['lastReqDt' => lastReqDt()]);
    $clsList = $codes['clsList'] ?? [];

    echo "Code Classes found: " . count($clsList) . "\n";

    foreach ($clsList as $code) {
        echo "- Class: {$code['cdCls']} ({$code['cdClsNm']})\n";

        // Optional: loop over detail codes
        foreach ($code['dtlList'] ?? [] as $detail) {
            echo "    - Detail Code: {$detail['cd']} ({$detail['cdNm']})\n";
        }
    }

} catch (ApiException $e) {
    abort("Code List search failed: " . $e->getMessage());
}

/* ---------------------------- STEP 4: Customer SEARCH ---------------------------- */
headerLine('STEP 4: Customer SEARCH');

try {
    $requestData = [
        'custmTin' => 'A123456789Z'
    ];

    $customers = $etims->selectCustomer($requestData);

    $custList = $customers['data']['custList'] ?? [];

    echo "Customers found: " . count($custList) . "\n";

    foreach ($custList as $customer) {
        echo "- TIN: {$customer['tin']}\n";
        echo "  Name: {$customer['taxprNm']}\n";
        echo "  Status: {$customer['taxprSttsCd']}\n";
        echo "  County: {$customer['prvncNm']}\n";
        echo "  Sub-County: {$customer['dstrtNm']}\n";
        echo "  Tax Locality: {$customer['sctrNm']}\n";
        echo "  Location Desc: {$customer['locDesc']}\n\n";
    }

} catch (ApiException $e) {
    abort("Customer search failed: " . $e->getMessage());
}

/* ---------------------------- STEP 5: NOTICE SEARCH ---------------------------- */
headerLine('STEP 5: NOTICE SEARCH');

try {
    // Prepare request data
    $requestData = [
        'lastReqDt' => lastReqDt('-30 days'),          // Notices updated in last 30 days
    ];

    // Call the API
    $notices = $etims->selectNoticeList($requestData);

    // Extract notice list
    $noticeList = $notices['data']['noticeList'] ?? [];

    echo "Notices found: " . count($noticeList) . "\n";

    // Print notice details
    foreach ($noticeList as $notice) {
        echo "- Notice No: {$notice['noticeNo']}\n";
        echo "  Title: {$notice['title']}\n";
        echo "  Contents: {$notice['cont']}\n";
        echo "  Detail URL: {$notice['dtlUrl']}\n";
        echo "  Registered by: {$notice['regrNm']}\n";
        echo "  Registration Date: {$notice['regDt']}\n\n";
    }

} catch (ApiException $e) {
    abort("Notice search failed: " . $e->getMessage());
}

/* ---------------------------- STEP 6: ITEM CLASS SEARCH ---------------------------- */
headerLine('STEP 6: ITEM CLASS SEARCH');

try {
    // Prepare request data
    $requestData = [
        'lastReqDt' => lastReqDt('-30 days'),          // Item classes updated in last 30 days
    ];

    // Call the API
    $itemClasses = $etims->selectItemClasses($requestData);

    // Extract item class list
    $itemClsList = $itemClasses['data']['itemClsList'] ?? [];

    echo "Item Classes found: " . count($itemClsList) . "\n";

    // Print item class details
    foreach ($itemClsList as $item) {
        echo "- Item Class Code: {$item['itemClsCd']}\n";
        echo "  Name: {$item['itemClsNm']}\n";
        echo "  Level: {$item['itemClsLvl']}\n";
        echo "  Tax Type Code: {$item['taxTyCd']}\n";
        echo "  Major Target: {$item['mjrTgYn']}\n";
        echo "  Use Status: {$item['useYn']}\n\n";
    }

} catch (ApiException $e) {
    abort("Item Class search failed: " . $e->getMessage());
}

/* ---------------------------- STEP 7: SAVE ITEM ---------------------------- */
headerLine('STEP 7: SAVE ITEM');

try {
    // Prepare request data
    $requestData = [
        'itemCd'      => 'KE1NTXU0000006',              // Item Code
        'itemClsCd'   => '5059690800',                  // Item Classification Code
        'itemTyCd'    => '1',                           // Item Type Code
        'itemNm'      => 'test material item3',        // Item Name
        'itemStdNm'   => null,                           // Optional standard name
        'orgnNatCd'   => 'KE',                           // Origin country code
        'pkgUnitCd'   => 'NT',                           // Packaging unit
        'qtyUnitCd'   => 'U',                            // Quantity unit
        'taxTyCd'     => 'B',                            // Tax type code
        'btchNo'      => null,                           // Optional batch number
        'bcd'         => null,                           // Optional barcode
        'dftPrc'      => 3500,                           // Default price
        'grpPrcL1'    => 3500,                           // Optional group prices
        'grpPrcL2'    => 3500,
        'grpPrcL3'    => 3500,
        'grpPrcL4'    => 3500,
        'grpPrcL5'    => null,
        'addInfo'     => null,                           // Optional additional info
        'sftyQty'     => null,                           // Optional safety quantity
        'isrcAplcbYn' => 'N',                            // Insurance applicable Y/N
        'useYn'       => 'Y',                             // Item active Y/N
        'regrId'      => 'Test',                          // Registration ID
        'regrNm'      => 'Test',                          // Registration Name
        'modrId'      => 'Test',                          // Modifier ID
        'modrNm'      => 'Test',                          // Modifier Name
    ];

    // Call the API
    $response = $etims->saveItem($requestData);

    // Print response
    echo "Item Save Response:\n";
    echo "Result Code: {$response['resultCd']}\n";
    echo "Result Message: {$response['resultMsg']}\n";
    echo "Result Date: {$response['resultDt']}\n";

} catch (ApiException $e) {
    abort("Save Item failed: " . $e->getMessage());
}

/* ---------------------------- STEP 8: ITEM SEARCH ---------------------------- */
headerLine('STEP 8: ITEM SEARCH');

try {
    // Prepare request data
    $requestData = [
        'lastReqDt' => lastReqDt('-30 days'),          // Items updated in last 30 days
    ];

    // Call the API
    $items = $etims->selectItems($requestData);

    // Extract item list
    $itemList = $items['data']['itemList'] ?? [];

    echo "Items found: " . count($itemList) . "\n";

    // Print item details
    foreach ($itemList as $item) {
        echo "- Item Code: {$item['itemCd']}\n";
        echo "  Name: {$item['itemNm']}\n";
        echo "  Classification Code: {$item['itemClsCd']}\n";
        echo "  Type Code: {$item['itemTyCd']}\n";
        echo "  Standard Name: {$item['itemStdNm']}\n";
        echo "  Origin: {$item['orgnNatCd']}\n";
        echo "  Packaging Unit: {$item['pkgUnitCd']}\n";
        echo "  Quantity Unit: {$item['qtyUnitCd']}\n";
        echo "  Tax Type: {$item['taxTyCd']}\n";
        echo "  Batch No: {$item['btchNo']}\n";
        echo "  Registered Branch: {$item['regBhfId']}\n";
        echo "  Barcode: {$item['bcd']}\n";
        echo "  Default Price: {$item['dftPrc']}\n";
        echo "  Group Prices: L1={$item['grpPrcL1']}, L2={$item['grpPrcL2']}, L3={$item['grpPrcL3']}, L4={$item['grpPrcL4']}, L5={$item['grpPrcL5']}\n";
        echo "  Additional Info: {$item['addInfo']}\n";
        echo "  Safety Quantity: {$item['sftyQty']}\n";
        echo "  Insurance Applicable: {$item['isrcAplcbYn']}\n";
        echo "  KRA Modify Flag: {$item['rraModYn']}\n";
        echo "  Use Status: {$item['useYn']}\n\n";
    }

} catch (ApiException $e) {
    abort("Item search failed: " . $e->getMessage());
}

/* ---------------------------- STEP 9: BRANCH SEARCH ---------------------------- */
headerLine('STEP 9: BRANCH SEARCH');

try {
    // Prepare request data
    $requestData = [
        'lastReqDt' => lastReqDt('-30 days'),          // Branch info updated in last 30 days
    ];

    // Call the API
    $branches = $etims->selectBranches($requestData);

    // Extract branch list
    $bhfList = $branches['data']['bhfList'] ?? [];

    echo "Branches found: " . count($bhfList) . "\n";

    // Print branch details
    foreach ($bhfList as $branch) {
        echo "- Branch ID: {$branch['bhfId']}\n";
        echo "  Name: {$branch['bhfNm']}\n";
        echo "  Status Code: {$branch['bhfSttsCd']}\n";
        echo "  County: {$branch['prvncNm']}\n";
        echo "  Sub-County: {$branch['dstrtNm']}\n";
        echo "  Locality: {$branch['sctrNm']}\n";
        echo "  Location: {$branch['locDesc']}\n";
        echo "  Manager Name: {$branch['mgrNm']}\n";
        echo "  Manager Phone: {$branch['mgrTelNo']}\n";
        echo "  Manager Email: {$branch['mgrEmail']}\n";
        echo "  Head Office: {$branch['hqYn']}\n\n";
    }

} catch (ApiException $e) {
    abort("Branch search failed: " . $e->getMessage());
}

/* ---------------------------- STEP 10: SAVE BRANCH CUSTOMER ---------------------------- */
headerLine('STEP 10: SAVE BRANCH CUSTOMER');

try {
    // Prepare request data
    $requestData = [
        'custNo'    => '999991113',                     // Customer Number
        'custTin'   => 'A123456789Z',                  // Customer PIN
        'custNm'    => 'Taxpayer1113',                 // Customer Name
        'adrs'      => null,                            // Optional address
        'telNo'     => null,                            // Optional contact
        'email'     => null,                            // Optional email
        'faxNo'     => null,                            // Optional fax
        'useYn'     => 'Y',                             // Active Y/N
        'remark'    => null,                            // Optional remark
        'regrId'    => 'Test',                          // Registration ID
        'regrNm'    => 'Test',                          // Registration Name
        'modrId'    => 'Test',                          // Modifier ID
        'modrNm'    => 'Test',                          // Modifier Name
    ];

    // Call the API
    $response = $etims->saveBranchCustomer($requestData);

    // Check response
    if (($response['resultCd'] ?? '') === '000') {
        echo "✅ Branch customer saved successfully\n";
    } else {
        abort("Failed to save branch customer: " . ($response['resultMsg'] ?? 'Unknown error'));
    }

} catch (ApiException $e) {
    abort("Branch customer save failed: " . $e->getMessage());
}

/* ---------------------------- STEP 11: SAVE BRANCH USER ---------------------------- */
headerLine('STEP 11: SAVE BRANCH USER');

try {
    // Prepare request data
    $requestData = [
        'tin'       => $config['oscu']['tin'],           // Taxpayer TIN
        'bhfId'     => $config['oscu']['bhf_id'],       // Branch ID, '00' for HQ
        'cmcKey'    => $config['oscu']['cmc_key'] ?? '', // Optional if OSCU used
        'userId'    => 'userId3',                        // User ID
        'userNm'    => 'User Name3',                     // User Name
        'pwd'       => '12341234',                       // Password
        'adrs'      => null,                             // Optional address
        'cntc'      => null,                             // Optional contact
        'authCd'    => null,                             // Optional authority code
        'remark'    => null,                             // Optional remark
        'useYn'     => 'Y',                              // Active Y/N
        'regrId'    => 'Test',                           // Registration ID
        'regrNm'    => 'Test',                           // Registration Name
        'modrId'    => 'Test',                           // Modifier ID
        'modrNm'    => 'Test',                           // Modifier Name
    ];

    // Call the API
    $response = $etims->saveBranchUser($requestData);

    // Check response
    if (($response['resultCd'] ?? '') === '000') {
        echo "✅ Branch user saved successfully\n";
    } else {
        abort("Failed to save branch user: " . ($response['resultMsg'] ?? 'Unknown error'));
    }

} catch (ApiException $e) {
    abort("Branch user save failed: " . $e->getMessage());
}

/* ---------------------------- STEP 12: SAVE BRANCH INSURANCE ---------------------------- */
headerLine('STEP 12: SAVE BRANCH INSURANCE');

try {
    // Prepare request data
    $requestData = [
        'tin'       => $config['oscu']['tin'],           // Taxpayer TIN
        'bhfId'     => $config['oscu']['bhf_id'],       // Branch ID, '00' for HQ
        'cmcKey'    => $config['oscu']['cmc_key'] ?? '', // Optional if OSCU used
        'isrccCd'   => 'ISRCC01',                        // Insurance Code
        'isrccNm'   => 'ISRCC NAME',                     // Insurance Name
        'isrcRt'    => 20,                               // Premium Rate
        'useYn'     => 'Y',                              // Active Y/N
        'regrId'    => 'Test',                           // Registration ID
        'regrNm'    => 'Test',                           // Registration Name
        'modrId'    => 'Test',                           // Modifier ID
        'modrNm'    => 'Test',                           // Modifier Name
    ];

    // Call the API
    $response = $etims->saveBranchInsurance($requestData);

    // Check response
    if (($response['resultCd'] ?? '') === '000') {
        echo "✅ Branch insurance saved successfully\n";
    } else {
        abort("Failed to save branch insurance: " . ($response['resultMsg'] ?? 'Unknown error'));
    }

} catch (ApiException $e) {
    abort("Branch insurance save failed: " . $e->getMessage());
}

/* ---------------------------- STEP 13: IMPORT ITEM SEARCH ---------------------------- */
headerLine('STEP 13: IMPORT ITEM SEARCH');

// try {
//     // Prepare request data
//     $requestData = [
//         'lastReqDt' => lastReqDt('-30 days'),           // Fetch items modified in last 30 days
//     ];

//     // Call the API
//     $response = $etims->selectImportedItems($requestData);

//     // Check response
//     if (($response['resultCd'] ?? '') === '000') {
//         $itemList = $response['data']['itemList'] ?? [];
//         echo "Import items found: " . count($itemList) . "\n";

//         foreach ($itemList as $item) {
//             echo "- Task: {$item['taskCd']}, Declaration: {$item['dclNo']}, Item: {$item['itemNm']}, Qty: {$item['qty']}\n";
//         }
//     } else {
//         abort("Failed to fetch import items: " . ($response['resultMsg'] ?? 'Unknown error'));
//     }

// } catch (ApiException $e) {
//     abort("Import item search failed: " . $e->getMessage());
// }

/* ---------------------------- STEP 14: IMPORT ITEM UPDATE ---------------------------- */
headerLine('STEP 14: IMPORT ITEM UPDATE');

try {
    // Prepare request data
    $requestData = [
        'taskCd'          => '2231943',                       // Task code of import item
        'dclDe'           => '20191217',                      // Declaration date YYYYMMDD
        'itemSeq'         => 1,                                // Item sequence
        'hsCd'            => '1231531231',                     // HS Code
        'itemClsCd'       => '5022110801',                     // Item Classification Code
        'itemCd'          => 'KE1NTXU0000001',                 // Item Code
        'imptItemSttsCd'  => '1',                               // Import Item Status Code
        'remark'          => 'Updated remark',                 // Optional remark
        'modrId'          => 'Test',                            // Modifier ID
        'modrNm'          => 'Test',                            // Modifier Name
    ];

    // Call the API
    $response = $etims->updateImportedItem($requestData);

    // Check response
    if (($response['resultCd'] ?? '') === '000') {
        echo "✅ Import item updated successfully\n";
    } else {
        abort("Failed to update import item: " . ($response['resultMsg'] ?? 'Unknown error'));
    }

} catch (ApiException $e) {
    abort("Import item update failed: " . $e->getMessage());
}

/* ---------------------------- STEP 15: PURCHASE / SALES TRANSACTION QUERY ---------------------------- */
headerLine('STEP 15: PURCHASE / SALES TRANSACTION QUERY');

// try {
//     // Prepare request data
//     $requestData = [
//         'lastReqDt' => lastReqDt('-30 days'),           // Fetch items modified in last 30 days
//     ];

//     // Call the API
//     $response = $etims->selectPurchases($requestData);

//     // Check response
//     if (($response['resultCd'] ?? '') === '000') {
//         echo "✅ Purchase/Sales transactions retrieved successfully\n";

//         $saleList = $response['data']['saleList'] ?? [];

//         foreach ($saleList as $sale) {
//             echo "----------------------------------------\n";
//             echo "Supplier PIN : {$sale['spplrTin']}\n";
//             echo "Supplier Name: {$sale['spplrNm']}\n";
//             echo "Invoice No   : {$sale['spplrInvcNo']}\n";
//             echo "Sales Date   : {$sale['salesDt']}\n";
//             echo "Total Amount : {$sale['totAmt']}\n";

//             // Items
//             foreach ($sale['itemList'] as $item) {
//                 echo "  - Item: {$item['itemNm']} | Qty: {$item['qty']} | Total: {$item['totAmt']}\n";
//             }
//         }

//     } else {
//         abort(
//             "Failed to retrieve purchase/sales transactions: "
//             . ($response['resultMsg'] ?? 'Unknown error')
//         );
//     }

// } catch (ApiException $e) {
//     abort("Purchase/Sales transaction query failed: " . $e->getMessage());
// }

/* ---------------------------- STEP 16: PURCHASE TRANSACTION SAVE ---------------------------- */
headerLine('STEP 16: PURCHASE TRANSACTION SAVE');

try {
    // Prepare request data
    $requestData = [
        // -------------------- Header --------------------
        'invcNo'      => 1,                    // Invoice Number
        'orgInvcNo'   => 0,                    // Original Invoice Number
        'spplrTin'    => 'A123456789Z',         // Supplier PIN
        'spplrBhfId'  => null,                 // Supplier Branch ID (optional)
        'spplrNm'     => null,                 // Supplier Name (optional)
        'spplrInvcNo' => null,                 // Supplier Invoice Number (optional)

        'regTyCd'   => 'M',                    // Registration Type
        'pchsTyCd'  => 'N',                    // Purchase Type
        'rcptTyCd'  => 'P',                    // Receipt Type
        'pmtTyCd'   => '01',                   // Payment Type
        'pchsSttsCd'=> '02',                   // Purchase Status

        'cfmDt'   => '20200127210300',          // Confirmed Date (YYYYMMDDhhmmss)
        'pchsDt'  => '20200127',                // Purchase Date (YYYYMMDD)
        'wrhsDt'  => null,                     // Warehousing Date
        'cnclReqDt' => null,
        'cnclDt'    => null,
        'rfdDt'     => null,

        // -------------------- Totals --------------------
        'totItemCnt' => 2,

        'taxblAmtA' => 0,
        'taxblAmtB' => 10500,
        'taxblAmtC' => 0,
        'taxblAmtD' => 0,
        'taxblAmtE' => 0,

        'taxRtA' => 0,
        'taxRtB' => 18,
        'taxRtC' => 0,
        'taxRtD' => 0,
        'taxRtE' => 0,

        'taxAmtA' => 0,
        'taxAmtB' => 1890,
        'taxAmtC' => 0,
        'taxAmtD' => 0,
        'taxAmtE' => 0,

        'totTaxblAmt' => 10500,
        'totTaxAmt'   => 1890,
        'totAmt'      => 10500,

        'remark' => null,

        // -------------------- Audit --------------------
        'regrId' => 'Test',
        'regrNm' => 'Test',
        'modrId' => 'Test',
        'modrNm' => 'Test',

        // -------------------- Items --------------------
        'itemList' => [
            [
                'itemSeq' => 1,
                'itemCd'  => 'KE1NTXU0000001',
                'itemClsCd' => '5059690800',
                'itemNm'  => 'test item 1',
                'bcd'     => null,

                'spplrItemClsCd' => null,
                'spplrItemCd'    => null,
                'spplrItemNm'    => null,

                'pkgUnitCd' => 'NT',
                'pkg'       => 2,
                'qtyUnitCd' => 'U',
                'qty'       => 2,
                'prc'       => 3500,
                'splyAmt'  => 7000,
                'dcRt'     => 0,
                'dcAmt'    => 0,

                'taxblAmt' => 7000,
                'taxTyCd'  => 'B',
                'taxAmt'   => 1260,
                'totAmt'   => 7000,

                'itemExprDt' => null,
            ],
            [
                'itemSeq' => 2,
                'itemCd'  => 'KE1NTXU0000002',
                'itemClsCd' => '5022110801',
                'itemNm'  => 'test item 2',
                'bcd'     => null,

                'spplrItemClsCd' => null,
                'spplrItemCd'    => null,
                'spplrItemNm'    => null,

                'pkgUnitCd' => 'NT',
                'pkg'       => 1,
                'qtyUnitCd' => 'U',
                'qty'       => 1,
                'prc'       => 3500,
                'splyAmt'  => 3500,
                'dcRt'     => 0,
                'dcAmt'    => 0,

                'taxblAmt' => 3500,
                'taxTyCd'  => 'B',
                'taxAmt'   => 630,
                'totAmt'   => 3500,

                'itemExprDt' => null,
            ],
        ],
    ];

    // Call the API
    $response = $etims->savePurchase($requestData);

    // Check response
    if (($response['resultCd'] ?? '') === '000') {
        echo "✅ Purchase transaction saved successfully\n";
    } else {
        abort(
            "Failed to save purchase transaction: "
            . ($response['resultMsg'] ?? 'Unknown error')
        );
    }

} catch (ApiException $e) {
    abort("Purchase transaction save failed: " . $e->getMessage());
}

/* ---------------------------- STEP 17: STOCK MOVEMENT ---------------------------- */
headerLine('STEP 17: STOCK MOVEMENT');

// try {
//     // Prepare request data
//     $requestData = [
//         'lastReqDt' => lastReqDt('-30 days'),           // Fetch items modified in last 30 days
//     ];

//     // Call the API
//     $response = $etims->selectStockMovement($requestData);

//     // Check response
//     if (($response['resultCd'] ?? '') === '000') {
//         echo "✅ Stock movement fetched successfully\n";

//         // Print summary of stock movements
//         foreach ($response['data']['stockList'] ?? [] as $stock) {
//             echo "- Customer: {$stock['custTin']} | Branch: {$stock['custBhfId']} | SAR No: {$stock['sarNo']} | Date: {$stock['ocrnDt']}\n";
//             foreach ($stock['itemList'] as $item) {
//                 echo "  • Item: {$item['itemCd']} ({$item['itemNm']}) | Qty: {$item['qty']} | Total: {$item['totAmt']}\n";
//             }
//         }
//     } else {
//         abort("Failed to fetch stock movements: " . ($response['resultMsg'] ?? 'Unknown error'));
//     }

// } catch (ApiException $e) {
//     abort("Stock movement request failed: " . $e->getMessage());
// } catch (ValidationException $e) {
//     echo "❌ Validation failed:\n";
//     foreach ($e->getErrors() as $field => $msg) {
//         echo "- Field '{$field}': {$msg}\n";
//     }
// }

/* ---------------------------- STEP 18: STOCK IN/OUT SAVE ---------------------------- */
headerLine('STEP 18: STOCK IN/OUT SAVE');

try {
    // Prepare request data
    $requestData = [
        'tin'       => 'A123456789Z',        // Your PIN
        'bhfId'     => '00',                  // Branch ID
        'sarNo'     => 2,                     // Stored and released number
        'orgSarNo'  => 2,                     // Original stored/released number
        'regTyCd'   => 'M',                   // Registration type
        'custTin'   => 'A123456789Z',         // Customer PIN (optional)
        'custNm'    => null,                  // Customer Name (optional)
        'custBhfId' => null,                  // Customer Branch ID (optional)
        'sarTyCd'   => '11',                  // Stock In/Out Type
        'ocrnDt'    => '20260106',            // Occurred date YYYYMMDD
        'totItemCnt'=> 2,                     // Total items
        'totTaxblAmt'=> 70000,
        'totTaxAmt' => 10677.96,
        'totAmt'    => 70000,
        'remark'    => null,
        'regrId'    => 'Test',
        'regrNm'    => 'Test',
        'modrId'    => 'Test',
        'modrNm'    => 'Test',

        // -------------------- Items --------------------
        'itemList' => [
            [
                'itemSeq'     => 1,
                'itemCd'      => 'KE1NTXU0000001',
                'itemClsCd'   => '5059690800',
                'itemNm'      => 'test item1',
                'bcd'         => null,
                'pkgUnitCd'   => 'NT',
                'pkg'         => 10,
                'qtyUnitCd'   => 'U',
                'qty'         => 10,
                'itemExprDt'  => null,
                'prc'         => 3500,
                'splyAmt'     => 35000,
                'totDcAmt'    => 0,
                'taxblAmt'    => 35000,
                'taxTyCd'     => 'B',
                'taxAmt'      => 5338.98,
                'totAmt'      => 35000,
            ],
            [
                'itemSeq'     => 2,
                'itemCd'      => 'KE1NTXU0000002',
                'itemClsCd'   => '5059690800',
                'itemNm'      => 'test item2',
                'bcd'         => null,
                'pkgUnitCd'   => 'BL',
                'pkg'         => 10,
                'qtyUnitCd'   => 'U',
                'qty'         => 10,
                'itemExprDt'  => null,
                'prc'         => 3500,
                'splyAmt'     => 35000,
                'totDcAmt'    => 0,
                'taxblAmt'    => 35000,
                'taxTyCd'     => 'B',
                'taxAmt'      => 5338.98,
                'totAmt'      => 35000,
            ],
        ],
    ];

    // Call the API
    $response = $etims->saveStockIO($requestData);

    // Check response
    if (($response['resultCd'] ?? '') === '000') {
        echo "✅ Stock In/Out saved successfully\n";
    } else {
        abort("Failed to save Stock In/Out: " . ($response['resultMsg'] ?? 'Unknown error'));
    }

} catch (ApiException $e) {
    abort("Stock In/Out request failed: " . $e->getMessage());
} catch (ValidationException $e) {
    echo "❌ Validation failed:\n";
    foreach ($e->getErrors() as $field => $msg) {
        echo "- Field '{$field}': {$msg}\n";
    }
}

/* ---------------------------- STEP 20: SAVE STOCK MASTER ---------------------------- */
headerLine('STEP 19: SAVE STOCK MASTER');

try {
    // Prepare request data
    $requestData = [
        'tin'     => 'A123456789Z',         // Your PIN
        'bhfId'   => '00',                  // Branch ID
        'itemCd'  => 'KE1NTXU0000002',     // Item Code
        'rsdQty'  => 10,                    // Remaining quantity
        'regrId'  => 'Test',                // Registration ID
        'regrNm'  => 'Test',                // Registration Name
        'modrId'  => 'Test',                // Modifier ID
        'modrNm'  => 'Test',                // Modifier Name
    ];

    // Call the API
    $response = $etims->saveStockMaster($requestData);

    // Check response
    if (($response['resultCd'] ?? '') === '000') {
        echo "✅ Stock Master saved successfully\n";
    } else {
        abort("Failed to save Stock Master: " . ($response['resultMsg'] ?? 'Unknown error'));
    }

} catch (ApiException $e) {
    abort("Stock Master save failed: " . $e->getMessage());
} catch (ValidationException $e) {
    echo "❌ Validation failed:\n";
    foreach ($e->getErrors() as $field => $msg) {
        echo "- Field '{$field}': {$msg}\n";
    }
}
