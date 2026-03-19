<?php
namespace Modules\Myob\App\Http\Controllers\Auth;

use Modules\Myob\App\Models\CompanyFile;
use Modules\Myob\App\Trait\ResponseTrait;

class CompanyService
{
    use ResponseTrait;
    public $client;
    public $company;

    public function __construct(CompanyFile $cf)
    {
        // $this->client = $client;
        $this->company = $cf;
    }

    public function listCompanyFiles()
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => 'http://localhost:8080/AccountRight',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => [
                'x-myobapi-version: v2',
                'Accept-Encoding: gzip,deflate',
                'Authorization: Basic ' . base64_encode('Administrator:'),
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $res = json_decode($response, true);
        if (@$res['Errors']) {
            return $this->errorWithMessageAndData("Error", $res);
        }
        // dd($res);
        // print_r($res);
        // die;
        foreach ($res as $r) {
            CompanyFile::updateOrcreate(
                [
                    'myobID' => $r['Id'],
                ],
                [
                    'myobID'         => $r['Id'],
                    'Name'           => $r['Name'],
                    'LibraryPath'    => $r['LibraryPath'],
                    'ProductVersion' => $r['ProductVersion'],
                    'ProductLevel'   => ! empty($r['ProductLevel']) ? json_encode($r['ProductLevel'], 1) : '',
                    'Subscription'   => $r['Subscription'],
                    'CheckedOutDate' => $r['CheckedOutDate'],
                    'CheckedOutBy'   => $r['CheckedOutBy'],
                    'Uri'            => $r['Uri'],
                    'Country'        => $r['Country'],
                    'LauncherId'     => $r['LauncherId'],
                    'SerialNumber'   => $r['SerialNumber'],
                    'UIAccessFlags'  => @$r['UIAccessFlags'],
                ]
            );
        }

        return $this->successWithMessage("Company Files Saved Successfully.");

    }
}
