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
    public function my_required($attribute_name, $params)

    {

        if (empty($this->file)

            && empty($this->message)

        ) {

            $this->addError($attribute_name, Yii::t('user', 'At least 1 of the field must be filled up properly'));


            return false;

        }


        return true;

    }

    public function getDoc_manage()
    {
        return $this->hasMany(DocManage::className(), ['doc_id'=>'id'])
                    ->orderBy(['sort' => SORT_DESC]);
    }

    // public function DM_2()
    // {
    //     return $this->hasMany(DocManage::className(), ['doc_id'=>'id'])
    //                 ->orderBy(['sort' => SORT_DESC]);
    // }

    public function getDoc_file()
    {
        return $this->hasMany(DocFile::className(), ['docz_id'=>'id'])
                    ->orderBy(['id' => SORT_ASC]);
    }
}
