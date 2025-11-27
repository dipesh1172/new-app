<?php

use App\Models\BrandEztpvContract;
use App\Models\Product;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ClearviewContractDbRecordUpdates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // GreenValueAssurance12Plus - IL
        $product1 = Product::where(
            'name',
            'GreenValueAssurance12Plus - IL'
        )
        ->first();
        if (isset($product1)) {
            $bec1 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_il_GreenValueAssurance12Plus_sigpage_20200225.docx'
            )
            ->update([
                'product_id' => $product1->id
            ]);
        }

        // ========================================

        // GreenValueAssurance12Plus - IL - TBS
        $product2 = Product::where(
            'name',
            'GreenValueAssurance12Plus - IL - TBS'
        )
        ->first();
        if ($product2) {
            $bec2 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_il_GreenValueAssurance12Plus_sigpage_20200225.docx'
            )
            ->get();
            foreach ($bec2 as $contract) {
                $bec3 = $contract->replicate();
                $bec3->product_id = $product2->id;
                $bec3->save();
            }
        }

        // ========================================

        // GreenValueAssurance6Plus - IL
        $product3 = Product::where(
            'name',
            'GreenValueAssurance6Plus - IL'
        )
        ->first();
        if (isset($product3)) {
            $bec4 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_il_GreenValueAssurance6Plus_sigpage_20200225.docx'
            )
            ->update([
                'product_id' => $product3->id
            ]);
        }

        // ========================================

        // GreenValueAssurance6Plus - IL - TBS
        $product4 = Product::where(
            'name',
            'GreenValueAssurance6Plus - IL - TBS'
        )
        ->first();
        if (isset($product4)) {
            $bec5 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_il_GreenValueAssurance6Plus_sigpage_20200225.docx'
            )
            ->where(
                'channel_id',
                1
            )
            ->first();
            $bec6 = $bec5->replicate();
            $bec6->product_id = $product4->id;
            $bec6->save();
        }

        // ========================================

        // GreenValueAssurancePlus - IL
        $product5 = Product::where(
            'name',
            'GreenValueAssurancePlus - IL'
        )
        ->first();
        if (isset($product5)) {
            $bec7 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_il_GreenValueAssurancePlus_sigpage_20200225.docx'
            )
            ->update([
                'product_id' => $product5->id
            ]);
        }

        // ========================================

        // LoyaltyAssurance12Plus - IL
        $product6 = Product::where(
            'name',
            'LoyaltyAssurance12Plus - IL'
        )
        ->first();
        if (isset($product6)) {
            $bec8 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_il_LoyaltyAssurance12Plus_sigpage_20200225.docx'
            )
            ->update([
                'product_id' => $product6->id
            ]);
        }

        // ========================================

        // ClearGreenGuarantee12 - OH
        $product7 = Product::where(
            'name',
            'ClearGreenGuarantee12 - OH'
        )
        ->first();
        if (isset($product7)) {
            $bec9 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_oh_ClearGreenGuarantee12_sigpage_20200218.docx'
            )
            ->update([
                'product_id' => $product7->id
            ]);
        }

        // ========================================

        // ClearValue - OH
        $product8 = Product::where(
            'name',
            'ClearValue - OH'
        )
        ->first();
        if (isset($product8)) {
            $bec10 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_oh_ClearValue_sigpage_20200305.docx'
            )
            ->update([
                'product_id' => $product8->id
            ]);
        }

        // ========================================

        // GreenValueAssurance - OH
        $product9 = Product::where(
            'name',
            'GreenValueAssurance - OH'
        )
        ->first();
        if (isset($product9)) {
            $bec11 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_oh_GreenValueAssurance_sigpage_20200220.docx'
            )
            ->update([
                'product_id' => $product9->id
            ]);
        }

        // ========================================

        // GreenValueAssurance6 - OH
        $product10 = Product::where(
            'name',
            'GreenValueAssurance6 - OH'
        )
        ->first();
        if (isset($product10)) {
            $bec12 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_oh_GreenValueAssurance6_sigpage_20200221.docx'
            )
            ->update([
                'product_id' => $product10->id
            ]);
        }

        // ========================================

        // GreenValueAssurance6Plus - OH
        $product11 = Product::where(
            'name',
            'GreenValueAssurance6Plus - OH'
        )
        ->first();
        if (isset($product11)) {
            $bec13 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_oh_GreenValueAssurance6Plus_sigpage_20200221.docx'
            )
            ->update([
                'product_id' => $product11->id
            ]);
        }

        // ========================================

        // GreenValueAssurancePlus - OH
        $product12 = Product::where(
            'name',
            'GreenValueAssurancePlus - OH'
        )
        ->first();
        if (isset($product12)) {
            $bec14 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_oh_GreenValueAssurancePlus_sigpage_20200221.docx'
            )
            ->update([
                'product_id' => $product12->id
            ]);
        }

        // ========================================

        // LoyaltyAssurance12 - OH
        $product13 = Product::where(
            'name',
            'LoyaltyAssurance12 - OH'
        )
        ->first();
        if (isset($product13)) {
            $bec15 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_oh_LoyaltyAssurance12_sigpage_20200221.docx'
            )
            ->update([
                'product_id' => $product13->id
            ]);
        }

        // ========================================

        // LoyaltyAssurance12Plus - OH
        $product14 = Product::where(
            'name',
            'LoyaltyAssurance12Plus - OH'
        )
        ->first();
        if (isset($product14)) {
            $bec16 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_oh_LoyaltyAssurance12Plus_sigpage_20200221.docx'
            )
            ->update([
                'product_id' => $product14->id
            ]);
        }

        // ========================================

        // NaturalAssurance - OH
        $product15 = Product::where(
            'name',
            'NaturalAssurance - OH'
        )
        ->first();
        if (isset($product15)) {
            $bec17 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_oh_NaturalAssurance_sigpage_20200305.docx'
            )
            ->update([
                'product_id' => $product15->id
            ]);
        }

        // ========================================

        // NaturalAssurance12 -  OH
        $product16 = Product::where(
            'name',
            'NaturalAssurance12 -  OH'
        )
        ->first();
        if (isset($product16)) {
            $bec18 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_oh_NaturalAssurance12_sigpage_20200305.docx'
            )
            ->update([
                'product_id' => $product16->id
            ]);
        }

        // ========================================

        // NaturalAssurance6 - OH
        $product17 = Product::where(
            'name',
            'NaturalAssurance6 - OH'
        )
        ->first();
        if (isset($product17)) {
            $bec19 = BrandEztpvContract::where(
                'contract_pdf',
                'clearview_oh_NaturalAssurance6_sigpage_20200305.docx'
            )
            ->update([
                'product_id' => $product17->id
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // unnecessary
    }
}
