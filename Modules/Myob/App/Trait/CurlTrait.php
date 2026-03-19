<?php

namespace Modules\Myob\App\Trait;

use Modules\Myob\App\Http\Controllers\Auth\CompanyService;
use Modules\Myob\App\Models\Client;

trait CurlTrait
{


    public $myobAuth;
    public function __construct(CompanyService $service)
    {
        $this->service = $service;
    }


    public function curlGet($url)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->myobAuth->company->Uri."/",$this->myobAuth->company->myobID.'/'.$url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'x-myobapi-key: ' . $this->myobAuth->client->myob_client_id,
                'x-myobapi-version: v2',
                'Accept-Encoding: gzip,deflate',
                'Authorization: Bearer ' . $this->myobAuth->client->myob_access_token
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
    }

    public function curlPost($url, $data)
    {

        
    }

 
}
 