<?php
namespace Modules\Myob\App\Http\Controllers\Auth;

use Modules\Myob\App\Trait\CurlTrait;
use Modules\Myob\App\Trait\ResponseTrait;

class AuthService
{
    //
    use CurlTrait;
    use ResponseTrait;
    public $myob;

    public function __construct(CompanyService $myob)
    {
        $this->myob = $myob;
    }

    public function sendGetRequest($url, $tokenFlag = false, $isDebug = 0)
    {

        info("Get Request Called");
        // if ($tokenFlag == true) {
        //     $token = $this->refreshToken();
        //     // info("Refresh Token Called ". $token);
        // } else {
        //     $token = $this->myob->client->myob_access_token;
        //     // info("Old Token Used".$token);
        // }
        $fullUrl = 'http://localhost:8080/AccountRight/2b0003ff-142f-4e59-9f72-04f4a040c04f' . '/' . $url;
        if ($isDebug == 1) {
            echo $fullUrl;
            die;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => [
                // 'x-myobapi-key: ' . $this->myob->client->myob_client_id,
                'x-myobapi-version: v2',
                'Accept-Encoding: gzip,deflate',
                'Authorization: Basic ' . base64_encode('Administrator:'),
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response, true);
        if (@$response == "The resource can't be found. It might have been deleted.") {
            return ["status" => @$response];
        }
        if (@$response['Errors']) {
            $code = $response['Errors'][0]['Name'];
            if ("$code" == 'OAuthTokenIsInvalid') {
                return $this->sendGetRequest($url, true);
            }
        }
        return $response;
    }

    /**
     * Send POST request to MYOB API
     */
    public function sendPostRequest($url, $data)
    {
        $fullUrl = 'http://localhost:8080/AccountRight/2b0003ff-142f-4e59-9f72-04f4a040c04f/' . $url;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                'x-myobapi-version: v2',
                'Accept-Encoding: gzip,deflate',
                'Authorization: Basic ' . base64_encode('Administrator:'),
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response, true);

        if (@$response == "The resource can't be found. It might have been deleted.") {
            return ["status" => @$response];
        }
        if (@$response['Errors']) {
            $code = $response['Errors'][0]['Name'];
            if ("$code" == 'OAuthTokenIsInvalid') {
                return $this->sendPostRequest($url, $data);
            }
        }
        return $response;
    }

    /**
     * Send PUT request to MYOB API
     */
    public function sendPostRequestForInventoryAdjustment($url, $data)
    {
        $fullUrl = 'http://localhost:8080/AccountRight/2b0003ff-142f-4e59-9f72-04f4a040c04f/Inventory/Adjustment';
        dump('fullUrl: ' . $fullUrl, 'data: ' . json_encode($data));
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                'x-myobapi-version: v2',
                'Accept-Encoding: gzip,deflate',
                'Authorization: Basic ' . base64_encode('Administrator:'),
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        dd($response);
        $response = json_decode($response, true);
        dump('response: ' . $response);
        if (@$response == "The resource can't be found. It might have been deleted.") {
            return ["status" => @$response];
        }
        if (@$response['Errors']) {
            $code = $response['Errors'][0]['Name'];
            if ("$code" == 'OAuthTokenIsInvalid') {
                return $this->sendPostRequest($url, $data);
            }
        }
        return $response;
    }

}
