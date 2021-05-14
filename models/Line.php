<?php

namespace app\models;

use Yii;

class Line extends \yii\db\ActiveRecord
{
    
    public function Send($token,$sms)
    {
        $token = 'zKsJKHnezJuHHCkClHcj8MfzZa8kWgL4Ss6HuIXgNXm';
        $sms =  trim($sms);
        date_default_timezone_set("Asia/Bangkok");
        $json = null;
        // if($model->load(Yii::$app->request->post())){
            $api_url = 'https://notify-api.line.me/api/notify';
            $headers = [
                'Authorization: Bearer ' . $token
            ];
            $fields = [
                'message' => $sms
            ];
            
            try {
                $ch = curl_init();
            
                curl_setopt($ch, CURLOPT_URL, $api_url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, count($fields));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
                $res = curl_exec($ch);
                curl_close($ch);
                $json = json_decode($res);
                if($json->status == 200){
                    return true;
                }
            } catch (Exception $e) {
                throw new Exception($e->getMessage);
            }
        return false;
    }  
    
   



    public function dateThaiTime($strDate){
        $strYear = date("Y",strtotime($strDate))+543;
		$strMonth= date("n",strtotime($strDate));
		$strDay= date("j",strtotime($strDate));
		$strHour= date("H",strtotime($strDate));
		$strMinute= date("i",strtotime($strDate));
		$strSeconds= date("s",strtotime($strDate));
		$strMonthCut = Array("","ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค.");
		$strMonthThai=$strMonthCut[$strMonth];
		return "$strDay $strMonthThai $strYear, $strHour:$strMinute";
    }
}
