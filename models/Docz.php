<?php

namespace app\models;

use Yii;
use yii\helpers\Url;
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

    public function doc_all_count(){
        $count = Docz::find()->select('id')->count();
        return $count;
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
    
    public function doc_cat_name($doc_id)
    {
        $models = DocCat::find()->where(['doc_id'=>$doc_id])->all();
        $name = '<span class="pull-right-container">';
        foreach($models as $model){            
            $name .= '<small class="label pull-right bg-yellow">'.$model->doc_cat_name().'</small>';
        }
        $name .= '</span>';
        return $name;
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
        // $token = 'zKsJKHnezJuHHCkClHcj8MfzZa8kWgL4Ss6HuIXgNXm';
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

    public function stamp_rub($id){
        $model = Docz::findOne($id);
        $mpdf = new \Mpdf\Mpdf();
        // $mpdf->SetImportUse(); // only with mPDF <8.0

        $completePath = Url::to('@webroot/'.$model->file);
        $pagecount = $mpdf->SetSourceFile($completePath);
        $mpdf->SetDocTemplate($completePath);
        // $mpdf->AddFont('THSarabun', '', 'THSarabun.php'); //ธรรมดา
        $mpdf->SetFont('garuda', '', 8);
        $mpdf->AddPage();
            $mpdf->SetXY(140, 5);
            $mpdf->SetDrawColor(0, 0, 255);
            $mpdf->setTextColor('0', '0', '255');
            $mpdf->Cell(60, 6, 'ศาลเยาวชนและครอบครัวจังหวัดประจวบคีรีขันธ์', 'LTR', 1, '');
            $mpdf->SetXY(140, 10);
            $mpdf->SetFont('garuda', '', 10);
            $mpdf->Cell(60, 6, 'รับที่  '.$model->r_number, 'LR', 1, '');
            $mpdf->SetXY(140, 15);
            $mpdf->Cell(60, 6, 'วันที่ '.$model->dateThaiTime($model->r_date), 'BLR', 1, '');
            // $mpdf->SetXY(140, 20);
            // $mpdf->Cell(60, 6, '', 'BLR', 1, '');
        
        for ($x = 2; $x <= $pagecount; $x++) {            
            $mpdf->AddPage();

        }
        
        $court_name = 'The Prachuapkhirikhan Juvenile and Family Court';
        // $mpdf->SetWatermarkText($court_name, 0.1);
        // $mpdf->showWatermarkText = true;
        $mpdf->SetTitle($model->name);
        $mpdf->SetAuthor($court_name);
        $mpdf->Output(Url::to('@webroot/'.$model->file), \Mpdf\Output\Destination::FILE);
        return true;
    } 

    public function stamp_end($id){
        $model = Docz::findOne($id);
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'default_font' => 'garuda'
        ]);

        $html = '<p>hi world สวัสดี</p>';
        $stylesheet = file_get_contents(Url::to('@webroot/css/pdf.css')); // external css
        $mpdf->WriteHTML($stylesheet,1);

        $completePath = Url::to('@webroot/'.$model->file);
        $pagecount = $mpdf->SetSourceFile($completePath);
        $mpdf->SetDocTemplate($completePath);
        // $mpdf->AddFont('THSarabun', '', 'THSarabun.php'); //ธรรมดา       
        
        for ($x = 1; $x <= $pagecount; $x++) {            
            $mpdf->AddPage();
        }
        $mpdf->SetFont('garuda', '', 8);
        $mpdf->AddPage();
            $mpdf->SetXY(140, 5);
            $mpdf->SetDrawColor(0, 0, 255);
            $mpdf->setTextColor('0', '0', '255');
            $mpdf->Cell(60, 6, 'ศาลเยาวชนและครอบครัวจังหวัดประจวบคีรีขันธ์', 'LTR', 1, '');
            $mpdf->SetXY(140, 10);
            $mpdf->SetFont('garuda', '', 10);
            $mpdf->Cell(60, 6, 'รับที่  '.$model->r_number, 'LR', 1, '');
            $mpdf->SetXY(140, 15);
            $mpdf->Cell(60, 6, 'วันที่ '.$model->dateThaiTime($model->r_date), 'BLR', 1, '');
            //หัวหน้าส่วน
            $x = 20;
            $y = 25;
            $mpdf->SetXY(20,5);
            $keyword = '';
            foreach($model->doc_manage_asc as $md){

                // $mpdf->SetXY(20,5);
                // $mpdf->SetFont('garuda', '', 8);
                $mpdf->WriteHTML($stylesheet,1);
                $mpdf->WriteHTML('<p id="hh">'.$md->ty.'</p>',2);
                // $mpdf->SetFont('garuda', '', 8);
                $mpdf->WriteHTML('<pre>'.$md->detail.'</pre>');
                $role_name = Role::find()->where(['user_id'=>$md->user_id,'role_name_id'=>$md->role_name_id])->one();
                // $mpdf->WriteHTML('<br>');
                if($role_name){
                    $dep_name = '<br>'.$role_name->name_dep1; 
                    $dep_name .= $role_name->name_dep2 ? '<br>'.$role_name->name_dep2 : '';
                    $dep_name .= $role_name->name_dep3 ? '<br>'.$role_name->name_dep3 : '';
                }else{
                    $dep_name = '';
                }                
                if($md->profile->sign_photo){
                    $sign_photo = '<img id="img" src="'.Url::to('@webroot/'.$md->profile->sign_photo).'" alt="sign_photo"><br>';
                }else{
                    $sign_photo ='<br>';
                }
                // $mpdf->WriteHTML($sign_photo,2);
                $mpdf->WriteHTML('<p>'.$sign_photo.'('.$md->username().')'.$dep_name.'<br>'.$model->dateThaiTime($md->updated).'</p>');
                $mpdf->WriteHTML('<p>--------------------------------------------------------------------------------</p>',2);
                // $y = $y+60;
                $keyword .= '['.$md->username().':'.$md->dep_name().'] ';
            }           
        $mpdf->SetTitle($model->name);
        $mpdf->SetAuthor('ศาลเยาวชนและครอบครัวจังหวัดประจวบคีรีขันธ์');        
        $mpdf->SetKeywords($keyword );
        $mpdf->Output(Url::to('@webroot/'.$model->file), \Mpdf\Output\Destination::FILE);
        // $mpdf->Output();
        return true;
    }
    
}
