<?php

namespace App\Trait;
use App\Models\Myob\Account; 

trait MyobAccountInfo
{
    public function getInvoicePrefix($warehouseID)
    {
        switch ($warehouseID) {
            case 1:
                return "DUB";
            case 3:
                return "RHINO";
            case 2:
                return "TAM";
            default:
                return "DUB";
        }
    }

    public function getIncomeAccount($warehouseID)
    {
        switch ($warehouseID) {
            case 1:
                return Account::where("DisplayID", "4-1230")->first();
            case 3:
                return Account::where("DisplayID", "4-2232")->first();
            case 2:
                return Account::where("DisplayID", "4-3100")->first();
            default:
                return Account::where("DisplayID", "4-1230")->first();
        }
    }

    public function getAssetAccount($warehouseID)
    {
        switch ($warehouseID) {
            case 1:
                return Account::where("DisplayID", "1-6500")->first();
            case 3:
                return Account::where("DisplayID", "1-6600")->first();
            case 2:
                return Account::where("DisplayID", "1-6700")->first();
            default:
                return Account::where("DisplayID", "1-6500")->first();
        }
    }

    public function getCogsAccount($warehouseID)
    {
        switch ($warehouseID) {
            case 1:
                return Account::where("DisplayID", '5-1275')->first();
            case 3:
                return Account::where("DisplayID", '5-2275')->first();
            case 2:
                return Account::where("DisplayID", '5-3175')->first();
            default:
                return Account::where("DisplayID", '5-1275')->first();
        }
    }

     public function getBankAccount($warehouseID, $paymentType = null)
    {
        switch ($warehouseID) {
            case 1: if($paymentType == 'TRANSFER'){
                        return Account::where("DisplayID", "1-1000")->first();
                    }else{
                        return Account::where("DisplayID", "1-1300")->first();
                    }
                //return Account::where("DisplayID", "1-1300")->first();
           
               
            case 2: if($paymentType == 'TRANSFER'){
                        return Account::where("DisplayID", "1-1050")->first();
                    }else{
                        return Account::where("DisplayID", "1-1301")->first();
                    }
                // return Account::where("DisplayID", "1-1301")->first();
            case 3:
            default:
                return Account::where("DisplayID", "1-1300")->first();
        }
    }
 
        public function getJobAccount($warehouseID)
    {
           switch ($warehouseID) {
            case 1:
                return "DU";
          
            
            case 2:
                return "TA";
          
            default:
                return "";
        }
    }
      public function getJobName($warehouseID)
    {
           switch ($warehouseID) {
            case 1:
                return "Dubbo";
           
              
            case 2:
                return "Tamworth";
              
            default:
                return "";
        }
    }
        public function getJobUid($warehouseID)
    {
           switch ($warehouseID) {
            case 1:
                return "8ae2cd32-ea3b-4ab8-bfa3-ccdabd1de056";
       
            case 2:
                return "d95c0fef-48c8-499f-89d2-83a404989c22";
            default:
                return "";
        }
    }
}
