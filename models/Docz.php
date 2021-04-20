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
            [['r_number'], 'integer'],
            [['r_number','doc_to', 'name'], 'required'],
            [['created'], 'safe'],
            [['r_date', 'doc_speed', 'doc_form_number','doc_form', 'doc_date', 'doc_to', 'user_create'], 'string', 'max' => 255],
            [['name'], 'string', 'max' => 1000],
            ['r_number', 'unique', 'targetClass' => '\app\models\Docz', 'message' => 'เลขนี้มีในระบบแล้ว.'],
            [['file'], 'file','skipOnEmpty' => true, 'extensions' => 'pdf, PDF'],
            
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
            'created' => 'Created',
        ];
    }

    public function getDoc_manage()
    {
        return $this->hasMany(DocManage::className(), ['doc_id'=>'id'])
                    ->orderBy(['sort' => SORT_DESC]);
    }

    public function getDoc_file()
    {
        return $this->hasMany(DocFile::className(), ['docz_id'=>'id'])
                    ->orderBy(['id' => SORT_ASC]);
    }
}
