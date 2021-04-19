<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "docz_bsdr".
 *
 * @property int $id
 * @property string $doc_date
 * @property string $name
 * @property string $bsdr_file
 * @property string $created
 */
class DoczBsdr extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'docz_bsdr';
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
            [['bsdr_file'], 'file', 'extensions' => 'pdf, PDF']
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
            'bsdr_file' => 'File',
            'created' => 'Created',
        ];
    }
}
