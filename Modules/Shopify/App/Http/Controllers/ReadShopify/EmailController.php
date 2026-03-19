<?php

namespace Modules\Shopify\App\Http\Controllers\ReadShopify;

use App\Http\Controllers\Controller;
use Modules\Shopify\App\Models\Source\SourceProduct;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public function __construct() {}

    public function sendEmail()
    {
        dump('Sending Email');
        $bcc_email = 'rabi@hitechvalley.com.au';
        $to_email = 'suraj@hitechvalley.com.au';
        $message = 'This is test message';
//        Mail::to($to_email)->send(new SendEmail([
//            'message' => 'This is test message.',
//        ]));

        Mail::send('emails.sohEmail', ['message' => 'This is test message!!!'], function ($message) use ($to_email, $bcc_email) {
            $message
            ->bcc($bcc_email)
            ->to($to_email)
            ->subject("SOH Failed !!!");
        });
        dump('!!! Successfully Email Sent !!!');
    }

    public function sendEmailV1($result)
    {
        dump('Sending Email');
        $bcc_email = 'rabi@hitechvalley.com.au';
        $to_email_list = [
            "rajesh@hitechvalley.com.au",
            "suraj@hitechvalley.com.au",
            "zeus@retailcare.com.au",
            "rabi@hitechvalley.com.au"
        ];

        //        Mail::to($to_email)->send(new SendEmail([
        //            'message' => 'This is test message.',
        //        ]));

        Mail::send('emails.sohEmail', ['result' => $result], function ($res) use ($to_email_list, $bcc_email) {
            $res
            // ->bcc($bcc_email)
            ->to($to_email_list)
            ->subject("SOH Failed !!!");
        });
        dump('!!! Successfully Email Sent !!!');
    }

    public function getFailedSOH ()
    {
        $result = SourceProduct::where([
            'shopifyPendingProcess' => 0, 
            'shopifyVariationProcess' => 0, 
            'status' => 1, 
            'sohPendingProcess' => 4 // right after 3 tries 
        ])
        ->where('emailSentCount' ,'<=', 3)
        ->orderby('lastPushedDate', 'ASC')
        ->limit(100)
        ->get();

        if ($result->isNotEmpty()) {
            $dataArray = [];
            foreach ($result as $row) {
                $dataArray[] = [
                    'erply_id' => $row->stockId,
                    'handle' => $row->handle,
                    'title' => $row->title,
                    'shopify_product_id' => $row->shopifyProductId
                ];

                $count = $row->emailSentCount + 1;
                if ($count <= 3) {
                    $row->emailSentCount = $count;
                }
                $row->save();
            }

            $this->sendEmailV1($dataArray);

            // foreach ($result as $row) {
            //     $count = $row->emailSentCount + 1;
            //     $row->emailSentCount = $count;
            //     $row->save();
            //     $this->sendEmailV1([
            //         'erply_id' => $row->stockId,
            //         'handle' => $row->handle,
            //         'title' => $row->title,
            //         'shopify_product_id' => $row->shopifyProductId
            //     ]);
            // }
        }
    }
}
