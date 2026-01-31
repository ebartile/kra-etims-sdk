<?php

namespace KraEtimsSdk\Services;

use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;
use KraEtimsSdk\Exceptions\ValidationException;

class Validator
{
    private array $schemas = [];

    public function __construct()
    {
        // CORE FIELDS (used across schemas)
        $tinValidator = v::stringType()->notEmpty()->length(1, 20)->setName('TIN');
        $bhfIdValidator = v::stringType()->notEmpty()->length(1, 10)->setName('Branch ID');
        $lastReqDtValidator = v::stringType()->notEmpty()->regex('/^\d{14}$/')->setName('Last Request Date (YYYYMMDDHHmmss)');

        $this->schemas = [
            // INITIALIZATION (Postman: ONLY these 3 fields)
            'initialization' => v::key('tin', $tinValidator)
                ->key('bhfId', $bhfIdValidator)
                ->key('dvcSrlNo', v::stringType()->notEmpty()->length(1, 50)->setName('Device Serial Number')),

            // DATA MANAGEMENT ENDPOINTS
            'codeList' => v::key('tin', $tinValidator)
                ->key('bhfId', $bhfIdValidator)
                ->key('lastReqDt', $lastReqDtValidator),
            
            'itemClsList' => v::key('tin', $tinValidator)
                ->key('bhfId', $bhfIdValidator)
                ->key('lastReqDt', $lastReqDtValidator),
            
            'bhfList' => v::key('lastReqDt', $lastReqDtValidator),
            
            'noticeList' => v::key('tin', $tinValidator)
                ->key('bhfId', $bhfIdValidator)
                ->key('lastReqDt', $lastReqDtValidator),
            
            'taxpayerInfo' => v::key('tin', $tinValidator)
                ->key('bhfId', $bhfIdValidator)
                ->key('lastReqDt', $lastReqDtValidator),
            
            'customerList' => v::key('tin', $tinValidator)
                ->key('bhfId', $bhfIdValidator)
                ->key('lastReqDt', $lastReqDtValidator),

            // BRANCH MANAGEMENT (NEW)
            'branchInsurance' => v::key('isrccCd', v::stringType()->notEmpty())
                ->key('isrccNm', v::stringType()->notEmpty())
                ->key('isrcRt', v::numericVal()->min(0))
                ->key('useYn', v::in(['Y', 'N']))
                ->key('regrNm', v::stringType()->notEmpty())
                ->key('regrId', v::stringType()->notEmpty()),
            
            'branchUserAccount' => v::key('userId', v::stringType()->notEmpty())
                ->key('userNm', v::stringType()->notEmpty())
                ->key('pwd', v::stringType()->notEmpty())
                ->key('useYn', v::in(['Y', 'N']))
                ->key('regrNm', v::stringType()->notEmpty())
                ->key('regrId', v::stringType()->notEmpty()),
            
            'customerInfo' => v::key('custNo', v::stringType()->notEmpty())
                ->key('custTin', v::stringType()->notEmpty())
                ->key('custNm', v::stringType()->notEmpty())
                ->key('useYn', v::in(['Y', 'N']))
                ->key('regrNm', v::stringType()->notEmpty())
                ->key('regrId', v::stringType()->notEmpty()),

            // SALES (Aligned with Postman example)
            'salesTransaction' => v::key('invcNo', v::intVal()->min(1))
                ->key('custTin', v::stringType()->notEmpty())
                ->key('custNm', v::stringType()->notEmpty())
                ->key('salesTyCd', v::in(['N', 'R']))
                ->key('rcptTyCd', v::in(['R', 'P', 'C'])) // Critical: Must match KRA codes
                ->key('pmtTyCd', v::regex('/^\d{2}$/'))
                ->key('salesSttsCd', v::in(['01', '02', '03']))
                ->key('cfmDt', v::stringType()->regex('/^\d{14}$/'))
                ->key('salesDt', v::stringType()->regex('/^\d{8}$/'))
                ->key('totItemCnt', v::intVal()->min(1))
                ->key('totTaxblAmt', v::numericVal()->min(0))
                ->key('totTaxAmt', v::numericVal()->min(0))
                ->key('totAmt', v::numericVal()->min(0))
                ->key('regrId', v::stringType()->notEmpty())
                ->key('regrNm', v::stringType()->notEmpty())
                ->key('itemList', v::arrayType()->notEmpty()->each(
                    v::key('itemSeq', v::intVal()->min(1))
                        ->key('itemCd', v::stringType()->notEmpty())
                        ->key('itemClsCd', v::stringType()->notEmpty())
                        ->key('itemNm', v::stringType()->notEmpty())
                        ->key('qty', v::numericVal()->min(0.001))
                        ->key('prc', v::numericVal()->min(0))
                        ->key('splyAmt', v::numericVal()->min(0))
                        ->key('taxTyCd', v::in(['A', 'B', 'C', 'D', 'E']))
                        ->key('taxblAmt', v::numericVal()->min(0))
                        ->key('taxAmt', v::numericVal()->min(0))
                        ->key('totAmt', v::numericVal()->min(0))
                )),
            
            // ADD OTHER SCHEMAS (purchaseTransaction, item, stockIO, etc.)
            // ... (implement following same pattern using Postman body examples)
        ];
    }

    public function validate(array $data, string $schemaName): array
    {
        if (!isset($this->schemas[$schemaName])) {
            throw new \InvalidArgumentException("Validation schema '$schemaName' not defined");
        }

        try {
            $this->schemas[$schemaName]->assert($data);
            return $data;
        } catch (NestedValidationException $e) {
            throw new ValidationException('Validation failed', $e->getMessages());
        }
    }
}