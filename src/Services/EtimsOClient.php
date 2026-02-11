<?php

namespace KraEtimsSdk\Services;

class EtimsOClient extends BaseOClient
{
    protected Validator $validator;

    public function __construct(array $config, AuthOClient $auth)
    {
        parent::__construct($config, $auth);
        $this->validator = new Validator();
    }

    protected function validate(array $data, string $schema): array
    {
        return $this->validator->validate($data, $schema);
    }

    /* -----------------------------
     * INITIALIZATION
     * ----------------------------- */
    public function selectInitOsdcInfo(array $data): array
    {
        return $this->post(
            'selectInitOsdcInfo',
            $this->validate($data, 'initialization')
        );
    }

    /* -----------------------------
     * CODE LISTS
     * ----------------------------- */
    public function selectCodeList(array $data): array
    {
        return $this->post(
            'selectCodeList',
            $this->validate($data, 'lastReqOnly')
        );
    }

    /* -----------------------------
     * CUSTOMER / BRANCH
     * ----------------------------- */
    public function selectCustomer(array $data): array
    {
        return $this->post(
            'selectCustomer',
            $this->validate($data, 'selectCustomer')
        );
    }

    public function selectBranches(array $data): array
    {
        return $this->post(
            'selectBhfList',
            $this->validate($data, 'lastReqOnly')
        );
    }

    public function saveBranchCustomer(array $data): array
    {
        return $this->post(
            'saveBhfCustomer',
            $this->validate($data, 'saveBhfCustomer')
        );
    }

    public function saveBranchUser(array $data): array
    {
        return $this->post(
            'saveBhfUser',
            $this->validate($data, 'saveBhfUser')
        );
    }

    public function saveBranchInsurance(array $data): array
    {
        return $this->post(
            'saveBhfInsurance',
            $this->validate($data, 'saveBhfInsurance')
        );
    }

    /* -----------------------------
     * ITEM
     * ----------------------------- */
    public function selectItemClasses(array $data): array
    {
        return $this->post(
            'selectItemClsList',
            $this->validate($data, 'lastReqOnly')
        );
    }

    public function selectItems(array $data): array
    {
        return $this->post(
            'selectItemList',
            $this->validate($data, 'lastReqOnly')
        );
    }

    public function saveItem(array $data): array
    {
        return $this->post(
            'saveItem',
            $this->validate($data, 'saveItem')
        );
    }

    public function saveItemComposition(array $data): array
    {
        return $this->post(
            'saveItemComposition',
            $this->validate($data, 'saveItemComposition')
        );
    }

    /* -----------------------------
     * IMPORTED ITEMS
     * ----------------------------- */
    public function selectImportedItems(array $data): array
    {
        return $this->post(
            'selectImportItemList',
            $this->validate($data, 'lastReqOnly')
        );
    }

    public function updateImportedItem(array $data): array
    {
        return $this->post(
            'updateImportItem',
            $this->validate($data, 'importItemUpdate')
        );
    }

    /* -----------------------------
     * PURCHASES
     * ----------------------------- */
    public function selectPurchases(array $data): array
    {
        return $this->post(
            'selectTrnsPurchaseSalesList',
            $this->validate($data, 'lastReqOnly')
        );
    }

    public function savePurchase(array $data): array
    {
        return $this->post(
            'insertTrnsPurchase',
            $this->validate($data, 'insertTrnsPurchase')
        );
    }

    public function saveSalesTransaction(array $data): array
    {
        return $this->post(
            'saveTrnsSalesOsdc',
            $this->validate($data, 'saveTrnsSalesOsdc')
        );
    }

    /* -----------------------------
     * STOCK
     * ----------------------------- */
    public function selectStockMovement(array $data): array
    {
        return $this->post(
            'selectStockMoveList',
            $this->validate($data, 'lastReqOnly')
        );
    }

    public function saveStockIO(array $data): array
    {
        return $this->post(
            'insertStockIO',
            $this->validate($data, 'insertStockIO')
        );
    }

    public function saveStockMaster(array $data): array
    {
        return $this->post(
            'saveStockMaster',
            $this->validate($data, 'saveStockMaster')
        );
    }

    /* -----------------------------
     * NOTICES
     * ----------------------------- */
    public function selectNoticeList(array $data): array
    {
        return $this->post(
            'selectNoticeList',
            $this->validate($data, 'lastReqOnly')
        );
    }
}
