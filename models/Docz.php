<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "docz".
 *
 * @property int $id
 * @property int|null $r_number
 * @property string|null $r_date
 * @property string|null $doc_speed
 * @property string|null $doc_form_number
 * @property string|null $doc_date
 * @property string $doc_to
 * @property string $name
 * @property string|null $file
 * @property string|null $user_create
 * @property string $created
 */
class Docz extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'docz';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_create'], 'integer'],
            [['r_number','doc_to', 'name'], 'required'],
            [['r_date','doc_date','created'], 'safe'],
            [['r_number','doc_speed', 'doc_form_number','doc_form',  'doc_to'], 'string', 'max' => 255],
            [['name'], 'string', 'max' => 1000],
            ['r_number', 'unique', 'targetClass' => '\app\models\Docz', 'message' => 'เลขนี้มีในระบบแล้ว.'],
            [['file'], 'file','skipOnEmpty' => true, 'extensions' => 'pdf, PDF'],
            // [['r_number'],'my_required'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'r_number' => 'เลขลงรับ',
            'r_date' => 'ลงรับวันที่',
            'doc_speed' => 'ชั้นความเร็ว',
            'doc_form_number' => 'ที่ ศย ',
            'doc_date' => 'หนังสือลงวันที่',
            'doc_form' => 'หนังสือจาก',            
            'name' => 'เรื่อง',
            'doc_to' => 'เรียน',            
            'file' => 'File',
            'user_create' => 'ผู้นำเข้า',
            'st'=> 'สถานะ',
            'start'=> 'เริ่ม',
            'end'=> 'เสร็จสิ้น',
            'created' => 'Created',
        ];
    }    

    public function r_rub(){
        $name = $this->r_number;
        return $name;
    }

    public function name_doc(){
        $name = $this->doc_speed ? '<small class="label  bg-red">'.$this->doc_speed.'</small>':'';
        $name .=$this->doc_form_number ? 'ที่ '.$this->doc_form_number : '';
        $name .=$this->doc_date ? 'ลงวันที่ '.date("Y-m-d",strtotime($this->doc_date)) : '';
        $name .= $this->name ? 'เรื่อง '.$this->name : '';
        return $name;
    }

    public function getDoc_manage()
    {
        return $this->hasMany(DocManage::className(), ['doc_id'=>'id'])
                    ->orderBy(['sort' => SORT_DESC]);
    }

    public function getDoc_manage_asc()
    {
        return $this->hasMany(DocManage::className(), ['doc_id'=>'id'])
                    ->orderBy(['sort' => SORT_ASC]);
    }
    public function getDoc_user_read()
    {
        return $this->hasMany(DocUserRead::className(), ['doc_id'=>'id']);
                    // ->orderBy(['sort' => SORT_DESC]);
    }
    public function getUser_profile()
    {
        return $this->hasOne(UserProfile::className(), ['user_id'=>'user_create']);
    }

    public function getDoc_file()
    {
        return $this->hasMany(DocFile::className(), ['docz_id'=>'id'])
                    ->orderBy(['id' => SORT_ASC]);
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

    public function Line_send($token,$sms)
    {
        $sms =  trim($sms);
        date_default_timezone_set("Asia/Bangkok");
        // zKsJKHnezJuHHCkClHcj8MfzZa8kWgL4Ss6HuIXgNXm 
        // $model = new LineFormSend();
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

    
}
