<?php

namespace Modules\Myob\App\Trait;
 

trait ResponseTrait
{

    public function successWithMessage($msg){
        return response()->json(['status'=>200, 'success' => true, 'message' => $msg]);
    }
    public function successWithMessageAndData($msg,$data){
        return response()->json(['status'=>200, 'success' => true, 'message' => $msg, 'records' => $data]);
    }

    public function errorWithMessage($msg){
        return response()->json(['status'=>400, 'success' => false, 'message' => $msg]);
    }

    public function errorWithMessageAndData($msg, $data){
        return response()->json(['status'=>400, 'success' => false, 'message' => $msg, 'records' => $data]);
    }


}