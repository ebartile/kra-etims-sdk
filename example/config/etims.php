<?php

// -------------------------------------------------
// KRA eTIMS OSCU Configuration
// -------------------------------------------------
// ⚠️ IMPORTANT: Replace ALL placeholder values with your actual credentials
return [
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
