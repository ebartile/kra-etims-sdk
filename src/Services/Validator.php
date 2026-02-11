<?php

namespace KraEtimsSdk\Services;

use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;
use KraEtimsSdk\Exceptions\ValidationException;


class Validator
{
    private array $schemas;

    public function __construct()
    {
        $tin = v::stringType()->notEmpty()->length(1, 20);
        $bhfId = v::stringType()->notEmpty()->length(1, 10);
        $lastReqDt = v::regex('/^\d{14}$/'); // YYYYMMDDHHMMSS

        $this->schemas = [

            /* -----------------------------
             * INITIALIZATION
             * ----------------------------- */
            'initialization' => v::key('tin', $tin)
                ->key('bhfId', $bhfId)
                ->key('dvcSrlNo', v::stringType()->notEmpty()),

            /* -----------------------------
             * COMMON
             * ----------------------------- */
            'lastReqOnly' => v::key('lastReqDt', $lastReqDt),

            'selectCustomer' => v::key('custmTin', $tin),

            /* -----------------------------
             * CUSTOMER / BRANCH
             * ----------------------------- */
            'saveBhfCustomer' => v::key('custNo', v::stringType()->notEmpty())
                ->key('custTin', $tin)
                ->key('custNm', v::stringType()->notEmpty())
                ->key('useYn', v::in(['Y', 'N']))
                ->key('regrId', v::stringType()->notEmpty())
                ->key('regrNm', v::stringType()->notEmpty())
                ->key('modrId', v::optional(v::stringType()))
                ->key('modrNm', v::optional(v::stringType())),

            'saveBhfUser' => v::key('userId', v::stringType()->notEmpty())
                ->key('userNm', v::stringType()->notEmpty())
                ->key('pwd', v::stringType()->notEmpty())
                ->key('useYn', v::in(['Y', 'N']))
                ->key('regrId', v::stringType()->notEmpty())
                ->key('regrNm', v::stringType()->notEmpty())
                ->key('modrId', v::optional(v::stringType()))
                ->key('modrNm', v::optional(v::stringType())),

            'saveBhfInsurance' => v::key('isrccCd', v::stringType()->notEmpty())
                ->key('isrccNm', v::stringType()->notEmpty())
                ->key('isrcRt', v::numericVal()->min(0))
                ->key('useYn', v::in(['Y', 'N']))
                ->key('regrId', v::stringType()->notEmpty())
                ->key('regrNm', v::stringType()->notEmpty())
                ->key('modrId', v::optional(v::stringType()))
                ->key('modrNm', v::optional(v::stringType())),

            /* -----------------------------
             * ITEM
             * ----------------------------- */
            'saveItem' => v::key('itemCd', v::stringType()->notEmpty())
                ->key('itemClsCd', v::stringType()->notEmpty())
                ->key('itemTyCd', v::stringType()->notEmpty())
                ->key('itemNm', v::stringType()->notEmpty())
                ->key('itemStdNm', v::optional(v::oneOf(v::stringType(), v::nullType())))
                ->key('orgnNatCd', v::stringType()->length(2, 5))
                ->key('pkgUnitCd', v::stringType()->notEmpty())
                ->key('qtyUnitCd', v::stringType()->notEmpty())
                ->key('taxTyCd', v::stringType()->notEmpty())
                ->key('dftPrc', v::numericVal()->min(0))
                ->key('grpPrcL1', v::optional(v::numericVal()))
                ->key('grpPrcL2', v::optional(v::numericVal()))
                ->key('grpPrcL3', v::optional(v::numericVal()))
                ->key('grpPrcL4', v::optional(v::numericVal()))
                ->key('grpPrcL5', v::optional(v::numericVal()))
                ->key('btchNo', v::optional(v::oneOf(v::stringType(), v::nullType())))
                ->key('bcd', v::optional(v::oneOf(v::stringType(), v::nullType())))
                ->key('addInfo', v::optional(v::oneOf(v::stringType(), v::nullType())))
                ->key('sftyQty', v::optional(v::numericVal()))
                ->key('isrcAplcbYn', v::in(['Y', 'N']))
                ->key('useYn', v::in(['Y', 'N']))
                ->key('regrId', v::stringType()->notEmpty())
                ->key('regrNm', v::stringType()->notEmpty())
                ->key('modrId', v::stringType()->notEmpty())
                ->key('modrNm', v::stringType()->notEmpty()),


            'saveItemComposition' => v::key('itemCd', v::stringType()->notEmpty())
                ->key('cpstItemCd', v::stringType()->notEmpty())
                ->key('cpstQty', v::numericVal()->min(0.001))
                ->key('regrId', v::stringType()->notEmpty())
                ->key('regrNm', v::stringType()->notEmpty())
                ->key('modrId', v::optional(v::stringType()))
                ->key('modrNm', v::optional(v::stringType())),

            'saveTrnsSalesOsdc' => v::key('trdInvcNo', v::oneOf(
                    v::stringType()->length(null, 50),
                    v::numericVal()
                ))

                ->key('invcNo', v::intType()->min(0))
                ->key('orgInvcNo', v::intType()->min(0))

                ->key('custTin', v::optional(v::oneOf(
                    v::stringType()->length(11, 11),
                    v::nullType()
                )))

                ->key('custNm', v::optional(v::oneOf(
                    v::stringType()->length(null, 60),
                    v::nullType()
                )))

                ->key('rcptTyCd', v::stringType()->notEmpty()->length(1, 5))
                ->key('pmtTyCd', v::optional(v::stringType()->length(1, 5)))
                ->key('salesSttsCd', v::stringType()->notEmpty()->length(1, 5))

                ->key('cfmDt', v::stringType()->regex('/^\d{14}$/'))   // yyyyMMddhhmmss
                ->key('salesDt', v::stringType()->regex('/^\d{8}$/'))  // yyyyMMdd

                ->key('stockRlsDt', v::optional(v::stringType()->regex('/^\d{14}$/')))
                ->key('cnclReqDt', v::optional(v::oneOf(v::stringType()->regex('/^\d{14}$/'), v::nullType())))
                ->key('cnclDt', v::optional(v::oneOf(v::stringType()->regex('/^\d{14}$/'), v::nullType())))
                ->key('rfdDt', v::optional(v::oneOf(v::stringType()->regex('/^\d{14}$/'), v::nullType())))
                ->key('rfdRsnCd', v::optional(v::oneOf(v::stringType()->length(1, 5), v::nullType())))

                ->key('totItemCnt', v::intType()->min(1))

                ->key('taxblAmtA', v::number())
                ->key('taxblAmtB', v::number())
                ->key('taxblAmtC', v::number())
                ->key('taxblAmtD', v::number())
                ->key('taxblAmtE', v::number())

                ->key('taxRtA', v::number())
                ->key('taxRtB', v::number())
                ->key('taxRtC', v::number())
                ->key('taxRtD', v::number())
                ->key('taxRtE', v::number())

                ->key('taxAmtA', v::number())
                ->key('taxAmtB', v::number())
                ->key('taxAmtC', v::number())
                ->key('taxAmtD', v::number())
                ->key('taxAmtE', v::number())

                ->key('totTaxblAmt', v::number())
                ->key('totTaxAmt', v::number())
                ->key('totAmt', v::number())

                ->key('prchrAcptcYn', v::in(['Y', 'N']))

                ->key('remark', v::optional(v::oneOf(
                    v::stringType()->length(null, 400),
                    v::nullType()
                )))

                ->key('regrId', v::stringType()->notEmpty()->length(null, 20))
                ->key('regrNm', v::stringType()->notEmpty()->length(null, 60))
                ->key('modrId', v::stringType()->notEmpty()->length(null, 20))
                ->key('modrNm', v::stringType()->notEmpty()->length(null, 60))

                ->key('receipt', v::key('rcptPbctDt', v::stringType()->regex('/^\d{14}$/'))
                    ->key('prchrAcptcYn', v::in(['Y', 'N']))
                    ->key('custTin', v::optional(v::oneOf(v::stringType()->length(11, 11), v::nullType())))
                    ->key('custMblNo', v::optional(v::oneOf(v::stringType()->length(null, 20), v::nullType())))
                    ->key('trdeNm', v::optional(v::oneOf(v::stringType()->length(null, 20), v::nullType())))
                    ->key('adrs', v::optional(v::oneOf(v::stringType()->length(null, 200), v::nullType())))
                    ->key('topMsg', v::optional(v::oneOf(v::stringType()->length(null, 20), v::nullType())))
                    ->key('btmMsg', v::optional(v::oneOf(v::stringType()->length(null, 20), v::nullType())))
                )

                ->key('itemList', v::arrayType()->notEmpty()->each(
                    v::key('itemSeq', v::intType()->min(1))
                        ->key('itemCd', v::stringType()->notEmpty()->length(null, 20))
                        ->key('itemNm', v::stringType()->notEmpty()->length(null, 200))
                        ->key('pkgUnitCd', v::stringType()->notEmpty()->length(null, 5))
                        ->key('pkg', v::number())
                        ->key('qtyUnitCd', v::stringType()->notEmpty()->length(null, 5))
                        ->key('qty', v::number())
                        ->key('prc', v::number())
                        ->key('splyAmt', v::number())
                        ->key('dcRt', v::number())
                        ->key('dcAmt', v::number())
                        ->key('taxTyCd', v::stringType()->notEmpty()->length(null, 5))
                        ->key('taxblAmt', v::number())
                        ->key('taxAmt', v::number())
                        ->key('totAmt', v::number())
                        ->key('itemClsCd', v::optional(v::stringType()->length(null, 10)))
                        ->key('bcd', v::optional(v::oneOf(v::stringType()->length(null, 20), v::nullType())))
                        ->key('isrccCd', v::optional(v::oneOf(v::stringType()->length(null, 10), v::nullType())))
                        ->key('isrccNm', v::optional(v::oneOf(v::stringType()->length(null, 100), v::nullType())))
                        ->key('isrcRt', v::optional(v::oneOf(v::number(), v::nullType())))
                        ->key('isrcAmt', v::optional(v::oneOf(v::number(), v::nullType())))
                )),

            /* -----------------------------
             * IMPORTED ITEM
             * ----------------------------- */
            'importItemUpdate' => v::key('taskCd', v::stringType()->notEmpty())
                ->key('dclDe', v::stringType()->length(8, 14))              // YYYYMMDD
                ->key('itemSeq', v::intVal()->min(1))                      // NUMBER
                ->key('hsCd', v::stringType()->notEmpty()->length(null,17))
                ->key('itemClsCd', v::stringType()->notEmpty()->length(null,10))
                ->key('itemCd', v::stringType()->notEmpty()->length(null,20))
                ->key('imptItemSttsCd', v::stringType()->notEmpty())
                ->key('modrId', v::stringType()->notEmpty())
                ->key('modrNm', v::stringType()->notEmpty())
                ->key('remark', v::optional(v::stringType())),

            /* -----------------------------
             * STOCK
             * ----------------------------- */
            'saveStockMaster' => v::key('itemCd', v::stringType()->notEmpty()->length(1, 20))
                ->key('rsdQty', v::numericVal()->min(0))          // Remaining quantity
                ->key('regrId', v::stringType()->notEmpty()->length(1, 20))
                ->key('regrNm', v::stringType()->notEmpty()->length(1, 60))
                ->key('modrId', v::stringType()->notEmpty()->length(1, 20))
                ->key('modrNm', v::stringType()->notEmpty()->length(1, 60)),
            
            'insertTrnsPurchase' => v::key('spplrTin', v::optional(v::stringType()->length(11, 11)))
                ->key('invcNo', v::intType()->min(0))
                ->key('orgInvcNo', v::intType()->min(0))
                ->key('spplrBhfId', v::optional(v::stringType()->length(2, 2)))
                ->key('spplrNm', v::optional(v::stringType()->length(null, 60)))
                ->key('spplrInvcNo', v::optional(v::intType()->min(0)))
                ->key('regTyCd', v::stringType()->notEmpty()->length(1, 5))
                ->key('pchsTyCd', v::stringType()->notEmpty()->length(1, 5))
                ->key('rcptTyCd', v::stringType()->notEmpty()->length(1, 5))
                ->key('pmtTyCd', v::stringType()->notEmpty()->length(1, 5))
                ->key('pchsSttsCd', v::stringType()->notEmpty()->length(1, 5))
                ->key('cfmDt', v::optional(v::stringType()->length(8, 14)))
                ->key('wrhsDt', v::optional(v::stringType()->length(8, 14)))
                ->key('cnclReqDt', v::optional(v::stringType()->length(8, 14)))
                ->key('cnclDt', v::optional(v::stringType()->length(8, 14)))
                ->key('rfdDt', v::optional(v::stringType()->length(8, 14)))                
                ->key('pchsDt', v::optional(v::stringType()->length(8, 14))) // YYYYMMDD
                ->key('totItemCnt', v::intType()->min(0))
                ->key('taxblAmtA', v::number())
                ->key('taxblAmtB', v::number())
                ->key('taxblAmtC', v::number())
                ->key('taxblAmtD', v::number())
                ->key('taxblAmtE', v::number())
                ->key('taxRtA', v::number())
                ->key('taxRtB', v::number())
                ->key('taxRtC', v::number())
                ->key('taxRtD', v::number())
                ->key('taxRtE', v::number())
                ->key('taxAmtA', v::number())
                ->key('taxAmtB', v::number())
                ->key('taxAmtC', v::number())
                ->key('taxAmtD', v::number())
                ->key('taxAmtE', v::number())
                ->key('totTaxblAmt', v::number())
                ->key('totTaxAmt', v::number())
                ->key('totAmt', v::number())
                ->key('remark', v::optional(v::stringType()->length(null, 400)))
                ->key('regrId', v::stringType()->notEmpty()->length(null, 20))
                ->key('regrNm', v::stringType()->notEmpty()->length(null, 60))
                ->key('modrId', v::stringType()->notEmpty()->length(null, 20))
                ->key('modrNm', v::stringType()->notEmpty()->length(null, 60))
                ->key('itemList', v::arrayType()->each(
                    v::key('itemSeq', v::intType()->min(1))
                    ->key('itemCd', v::stringType()->notEmpty()->length(null, 20))
                    ->key('itemClsCd', v::stringType()->notEmpty()->length(null, 10))
                    ->key('itemNm', v::stringType()->notEmpty()->length(null, 200))
                    ->key('bcd', v::optional(v::stringType()->length(null, 20)))
                    ->key('spplrItemClsCd', v::optional(v::stringType()->length(null, 10)))
                    ->key('spplrItemCd', v::optional(v::stringType()->length(null, 20)))
                    ->key('spplrItemNm', v::optional(v::stringType()->length(null, 200)))
                    ->key('pkgUnitCd', v::stringType()->length(null, 5))
                    ->key('pkg', v::number())
                    ->key('qtyUnitCd', v::stringType()->length(null, 5))
                    ->key('qty', v::number())
                    ->key('prc', v::number())
                    ->key('splyAmt', v::number())
                    ->key('dcRt', v::number())
                    ->key('dcAmt', v::number())
                    ->key('taxblAmt', v::number())
                    ->key('taxTyCd', v::stringType()->length(null, 5))
                    ->key('taxAmt', v::number())
                    ->key('totAmt', v::number())
                    ->key('itemExprDt', v::optional(v::stringType()->length(8, 14)))
                )),

            'insertStockIO' => v::key('tin', v::stringType()->notEmpty()->length(11, 11))
                ->key('bhfId', v::stringType()->notEmpty()->length(2, 2))
                ->key('sarNo', v::intType()->min(0))
                ->key('orgSarNo', v::intType()->min(0))
                ->key('regTyCd', v::stringType()->notEmpty()->length(1, 5))
                ->key('custTin', v::optional(v::stringType()->length(11, 11)))
                ->key('custNm', v::optional(v::stringType()->length(null, 100)))
                ->key('custBhfId', v::optional(v::stringType()->length(2, 2)))
                ->key('sarTyCd', v::stringType()->notEmpty()->length(1, 5))
                ->key('ocrnDt', v::stringType()->length(8, 8)) // YYYYMMDD
                ->key('totItemCnt', v::intType()->min(0))
                ->key('totTaxblAmt', v::number())
                ->key('totTaxAmt', v::number())
                ->key('totAmt', v::number())
                ->key('remark', v::optional(v::stringType()->length(null, 400)))
                ->key('regrId', v::stringType()->notEmpty()->length(null, 20))
                ->key('regrNm', v::stringType()->notEmpty()->length(null, 60))
                ->key('modrId', v::stringType()->notEmpty()->length(null, 20))
                ->key('modrNm', v::stringType()->notEmpty()->length(null, 60))
                ->key('itemList', v::arrayType()->each(
                    v::key('itemSeq', v::intType()->min(1))
                    ->key('itemCd', v::stringType()->notEmpty()->length(null, 20))
                    ->key('itemClsCd', v::stringType()->notEmpty()->length(null, 10))
                    ->key('itemNm', v::stringType()->notEmpty()->length(null, 200))
                    ->key('bcd', v::optional(v::stringType()->length(null, 20)))
                    ->key('pkgUnitCd', v::stringType()->length(null, 5))
                    ->key('pkg', v::number())
                    ->key('qtyUnitCd', v::stringType()->length(null, 5))
                    ->key('qty', v::number())
                    ->key('itemExprDt', v::optional(v::stringType()->length(8, 8)))
                    ->key('prc', v::number())
                    ->key('splyAmt', v::number())
                    ->key('totDcAmt', v::number())
                    ->key('taxblAmt', v::number())
                    ->key('taxTyCd', v::stringType()->length(null, 5))
                    ->key('taxAmt', v::number())
                    ->key('totAmt', v::number())
                )),

        ];
    }

    public function validate(array $data, string $schemaName): array
    {
        if (!isset($this->schemas[$schemaName])) {
            throw new \InvalidArgumentException("Schema '{$schemaName}' not defined");
        }

        try {
            $this->schemas[$schemaName]->assert($data);
            return $data;
        } catch (NestedValidationException $e) {
            // Collect field-specific messages
            $messages = [];
            foreach ($e->getIterator() as $exception) {
                // Full key path (e.g., itemList.0.qty)
                $field = $exception->getId() ?: '(unknown field)';
                $messages[$field] = $exception->getMessage();
            }

            // Optional: print errors to console/log
            foreach ($messages as $field => $msg) {
                echo "❌ Validation failed for field '{$field}': {$msg}\n";
            }

            throw new ValidationException('Validation failed', $messages);
        }
    }
}
