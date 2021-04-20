<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "docz_bsdr".
 *
 * @property int $id
 * @property string $doc_date
 * @property string $name
 * @property string $file
 * @property string $created
 */
class DoczB extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'docz_b';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['doc_date', 'name'], 'required'],
            [['doc_date', 'created'], 'safe'],
            [['name'], 'string', 'max' => 255],
            [['file'], 'file', 'extensions' => 'pdf, PDF']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'doc_date' => 'วันที่',
            'name' => 'ชื่อเอกสาร',
            'file' => 'File',
            'created' => 'Created',
        ];
    }

    public function getDoc_manage()
    {
        return $this->hasMany(DocManage::className(), ['doc_form' => 'doc_form','doc_id'=>'id'])
                    ->orderBy(['sort' => SORT_ASC]);
    }
}
