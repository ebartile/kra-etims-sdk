<?php

namespace KraEtimsSdk\Services;

class EtimsClient extends BaseClient
{
    protected Validator $validator;

    public function __construct(array $config, AuthClient $auth)
    {
        parent::__construct($config, $auth);
        $this->validator = new Validator();
    }

    protected function validate(array $data, string $schema): array
    {
        return $this->validator->validate($data, $schema);
    }

    // -----------------------------
    // INITIALIZATION (POSTMAN-COMPLIANT)
    // -----------------------------
    public function initialize(array $data): array
    {
        // ✅ REMOVE FORCED targetPath (NOT IN POSTMAN SPEC)
        // ✅ VALIDATE ONLY REQUIRED FIELDS (tin, bhfId, dvcSrlNo)
        $validated = $this->validate($data, 'initialization');
        
        // Uses BaseClient::post() which now handles:
        // - Correct headers (ONLY apigee_app_id + auth)
        // - Token refresh on 401
        // - KRA error unwrapping
        return $this->post('initialize', $validated);
    }

    // -----------------------------
    // BASIC DATA ENDPOINTS
    // -----------------------------
    public function selectCodeList(array $data): array
    {
        return $this->post('selectCodeList', $this->validate($data, 'codeList'));
    }

    public function selectItemClsList(array $data): array
    {
        return $this->post('selectItemClsList', $this->validate($data, 'itemClsList'));
    }

    public function selectBhfList(array $data): array
    {
        return $this->post('selectBhfList', $this->validate($data, 'bhfList'));
    }

    public function selectNoticeList(array $data): array
    {
        return $this->post('selectNoticeList', $this->validate($data, 'noticeList'));
    }

    public function selectTaxpayerInfo(array $data): array
    {
        return $this->post('selectTaxpayerInfo', $this->validate($data, 'taxpayerInfo'));
    }

    public function selectCustomerList(array $data): array
    {
        return $this->post('selectCustomerList', $this->validate($data, 'customerList'));
    }

    // -----------------------------
    // PURCHASE ENDPOINTS
    // -----------------------------
    public function selectPurchaseTrns(array $data): array
    {
        return $this->post('selectPurchaseTrns', $this->validate($data, 'purchaseTrns'));
    }

    // -----------------------------
    // SALES ENDPOINTS
    // -----------------------------
    public function sendSalesTrns(array $data): array
    {
        return $this->post('sendSalesTrns', $this->validate($data, 'salesTrns'));
    }

    public function selectSalesTrns(array $data): array
    {
        return $this->post('selectSalesTrns', $this->validate($data, 'selectSalesTrns'));
    }

    // -----------------------------
    // STOCK ENDPOINTS
    // -----------------------------
    public function selectMoveList(array $data): array
    {
        return $this->post('selectMoveList', $this->validate($data, 'moveList'));
    }

    public function saveStockMaster(array $data): array
    {
        return $this->post('saveStockMaster', $this->validate($data, 'stockMaster'));
    }

    // -----------------------------
    // ADD MISSING ENDPOINTS FROM POSTMAN
    // -----------------------------
    public function branchInsuranceInfo(array $data): array
    {
        return $this->post('branchInsuranceInfo', $this->validate($data, 'branchInsurance'));
    }

    public function branchUserAccount(array $data): array
    {
        return $this->post('branchUserAccount', $this->validate($data, 'branchUserAccount'));
    }

    public function branchSendCustomerInfo(array $data): array
    {
        return $this->post('branchSendCustomerInfo', $this->validate($data, 'customerInfo'));
    }

    public function sendPurchaseTransactionInfo(array $data): array
    {
        return $this->post('sendPurchaseTransactionInfo', $this->validate($data, 'purchaseTransaction'));
    }

    public function sendSalesTransaction(array $data): array
    {
        return $this->post('sendSalesTransaction', $this->validate($data, 'salesTransaction'));
    }

    public function saveItem(array $data): array
    {
        return $this->post('saveItem', $this->validate($data, 'item'));
    }

    public function insertStockIO(array $data): array
    {
        return $this->post('insertStockIO', $this->validate($data, 'stockIO'));
    }
}