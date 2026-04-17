<?php

namespace App\Http\Controllers\Auth;

use App\Trait\CurlTrait;
use App\Trait\ResponseTrait;


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
    public function getAccessCode()
    {
        $url = 'https://secure.myob.com/oauth2/account/authorize?client_id=' . $this->myob->client->myob_client_id . '&redirect_uri=http://127.0.0.1:8000/handleAccessCode&response_type=code&scope=myobFile';
        // dd($url);
        // dd($this->myob);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));
        $response = curl_exec($curl);

        $res = curl_getinfo($curl);
        $url = $res['url'];
        curl_close($curl);
        echo '<script>window.open ("' . $url . '")</script>';
    }

    public function getAccessToken()
    {

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://secure.myob.com/oauth2/v1/authorize/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'client_id=' . $this->myob->client->myob_client_id . '&client_secret=' . $this->myob->client->myob_secret . '&grant_type=authorization_code&code=' . urlencode($this->myob->client->myob_access_code) . '&redirect_uri=http%3A%2F%2F127.0.0.1%3A8000%2FhandleAccessCode',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $res = curl_exec($curl);

        curl_close($curl);
        $res = json_decode($res, true);

        if (@$res['error']) {
            return $this->errorWithMessage($res['error']);
        }
        info($res);
        $mc = $this->myob->client;
        $mc->myob_access_token = $res['access_token'];
        $mc->myob_refresh_token = $res['refresh_token'];
        $mc->myob_uid = $res['user']['uid'];
        $mc->myob_username = $res['user']['username'];
        $mc->save();
        // dd($res);
        return $this->successWithMessage("Authorization Successfull.");
    }

    public function refreshToken()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://secure.myob.com/oauth2/v1/authorize/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'client_id=' . $this->myob->client->myob_client_id . '&client_secret=' . $this->myob->client->myob_secret . '&grant_type=refresh_token&refresh_token=' . urlencode($this->myob->client->myob_refresh_token) . '',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $res = json_decode($response, true);
        if (@$res['error']) {
            return $this->errorWithMessage($res['error']);
        }

        $mc = $this->myob->client;
      
        $mc->myob_access_token = $res['access_token'];
        $mc->myob_refresh_token = $res['refresh_token'];
        $mc->save();

        return $res['access_token'];
    }

    public function sendGetRequest($url, $tokenFlag = false, $isDebug = 0)
    {

        info("Get Request Called");
        die;
        if ($tokenFlag == true) {
            $token = $this->refreshToken();
            // info("Refresh Token Called ". $token);
        } else {
            $token = $this->myob->client->myob_access_token;
            dd($token);
            // info("Old Token Used".$token);
        } 
        $fullUrl = $this->myob->company->Uri . "/" . $url;
        if($isDebug == 1){
            echo $fullUrl;
            die;
        }
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'x-myobapi-key: ' . $this->myob->client->myob_client_id,
                'x-myobapi-version: v2',
                'Accept-Encoding: gzip,deflate',
                'Authorization: Bearer ' . $token
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response, true); 
        if(@$response == "The resource can't be found. It might have been deleted."){
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

    public function sendPostRequest($url, $data, $tokenFlag = false)
    {
        $fullUrl = $this->myob->company->Uri . "/" . $url."?returnBody=true";
        $curl = curl_init();
        $token = $this->myob->client->myob_access_token;
        if ($tokenFlag == true) {
            $token = $this->refreshToken();
        }
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            // CURLINFO_HEADER_OUT => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>  $data,
            CURLOPT_HTTPHEADER => array(
                'x-myobapi-key: ' . $this->myob->client->myob_client_id,
                'x-myobapi-version: v2',
                'Accept-Encoding: gzip,deflate',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ),
        ));

        // curl_setopt_array($curl, array(
        //     CURLOPT_URL => $fullUrl,
        //     CURLOPT_RETURNTRANSFER => true,
        //     // CURLOPT_ENCODING => '',
        //     // CURLOPT_MAXREDIRS => 10,
        //     // CURLOPT_TIMEOUT => 0,
        //     // CURLOPT_FOLLOWLOCATION => true,
        //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        //     // CURLOPT_CUSTOMREQUEST => 'POST',
        //     CURLOPT_POST => true,
        //     CURLOPT_POSTFIELDS =>  $data,
        //     CURLOPT_HTTPHEADER => array(
        //         'x-myobapi-key: ' . $this->myob->client->myob_client_id,
        //         'x-myobapi-version: v2',
        //         'Accept-Encoding: gzip,deflate',
        //         'Content-Type: application/json',
        //         'Authorization: Bearer ' . $token, 
        //     )
        // ));

        $response = curl_exec($curl);
        $res = json_decode($response, true);
        // info($res);
        if (@$res['Errors']) {
            // print_r($res);
            $code = $res['Errors'][0]['ErrorCode'];
            if ("$code" == '31001 ') {
                return $this->sendPostRequest($url, $data, true);
            }
        }
        // $requestHeaders = curl_getinfo($curl, CURLINFO_HEADER_OUT);

        // Print the request headers
        // echo "Request Headers:\n";
        // echo $requestHeaders;

        curl_close($curl);
        return $res;
    }

    public function sendPutRequest($url, $data, $guid, $tokenFlag = false)
    {
        
        if ($tokenFlag == true) {
            $token = $this->refreshToken();
        }else{
            $token = $this->myob->client->myob_access_token;
        }

        $fullUrl = $this->myob->company->Uri . "/" . $url . "/" . $guid."?returnBody=true";//.urlencode("true");
         
        // $curl = curl_init();
        // curl_setopt($curl, CURLOPT_URL, $fullUrl);
        // curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        // curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        // curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($curl, CURLOPT_HEADER, array(
        //     'x-myobapi-key: ' . $this->myob->client->myob_client_id,
        //     'x-myobapi-version: v2',
        //     'Accept-Encoding: gzip,deflate',
        //     'Content-Type: application/json',
        //     'Authorization: Bearer ' . $token
        // )
        
        // );
        // $response = curl_exec($curl);
        // curl_close($curl);
        // $b64 = base64_encode($this->myob->client->myob_username.":87DJF%#1541sg");
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS =>  $data,
            CURLOPT_HTTPHEADER => array(
                'x-myobapi-key: ' . $this->myob->client->myob_client_id,
                'x-myobapi-version: v2',
                'Accept-Encoding: gzip,deflate',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response, true);
        if (@$response['Errors']) {
            // print_r($res);
            $code = $response['Errors'][0]['ErrorCode'];
            if ("$code" == '31001') {
                return $this->sendPutRequest($url, $data, $guid, true);
            }
        }
        // print_r($response);
        return $response;
    }

    public function sendDeleteRequest($url, $data, $guid, $tokenFlag = false, $isDebug = 0)
    {

        $token = $this->myob->client->myob_access_token;
        if ($tokenFlag == true) {
            $token = $this->refreshToken();
        }
        $fullUrl = $this->myob->company->Uri . "/" . $url . "/" . $guid."?returnBody=true";

        if($isDebug == 1){
            dd($fullUrl);
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_POSTFIELDS =>  $data,
            CURLOPT_HTTPHEADER => array(
                'x-myobapi-key: ' . $this->myob->client->myob_client_id,
                'x-myobapi-version: v2',
                'Accept-Encoding: gzip,deflate',
                'Authorization: Bearer ' . $token
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $response = json_decode($response, true);
        info($response);
        if (is_array($response) == true && @$response['Errors']) {
            // print_r($res);
            $code = $response['Errors'][0]['ErrorCode'];
            if ("$code" == '31001 ') {
                return $this->sendPutRequest($url, $data, $guid, true);
            }
        }
        // print_r($response);
        return $response;
    }

    public function sendGetPDFRequest($url, $tokenFlag = false)
    {

        info("Get Request Called");
        if ($tokenFlag == true) {
            $token = $this->refreshToken();
            // info("Refresh Token Called ". $token);
        } else {
            $token = $this->myob->client->myob_access_token;
            // info("Old Token Used".$token);
        }
        // info($token);
        $fullUrl = $this->myob->company->Uri . "/" . $url."?".urlencode("format=pdf&templatename=Pre-Printed Invoice");
        // echo $fullUrl;
        // die;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'x-myobapi-key: ' . $this->myob->client->myob_client_id,
                'x-myobapi-version: v2',
                'Accept: Application/PDF',
                'Accept-Encoding: gzip,deflate',
                'Authorization: Bearer ' . $token
            ),
        ));

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        // curl_close($curl);
        
        
        // print_r($response);
        // die;
        if($http_code != 200){
            //now checking if 
            $res = json_decode($response, true);
            if(@$res['Errors'][0]['ErrorCode'] == 31001){
                return $this->sendGetPDFRequest($url, $tokenFlag = true);
            }
            return "failed";
        }
        // die;
        // if (@$response['Errors']) {
        //     // print_r($res);
        //     $code = $response['Errors'][0]['Name'];
        //     if ("$code" == 'OAuthTokenIsInvalid') {
        //         // info("Invalid Token Matched");
        //         return $this->sendGetRequest($url, true);
        //     }
        // }
        // if($response)
        // print_r($response);
        // die;

        return $response;
        
    }


    public function sendCurlGetPostRequest($url, $data = '', $isPost = false)
    {
        // info("Hello Im Here");
        $ch = curl_init();

        // Set the URL and other curl options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($isPost == true) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        // Execute the request
        $response = curl_exec($ch);
        curl_close($ch);
        // print_r($response);
        // die;
        return json_decode($response, true);
    }
}
