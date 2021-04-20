<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "doc_cat_profile".
 *
 * @property int $id
 * @property string|null $name
 */
class DocProfile extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'doc_profile';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name','code'], 'string', 'max' => 255],
            [['name','code'], 'required'],
            ['code', 'unique', 'targetClass' => '\app\models\DocProfile', 'message' => 'This CODE has already been taken.'],
            
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'ชื่อ',
            'code' => 'CODE'
        ];
    }
    public function getDocps()
    {
        return $this->hasMany(DocProfileSub::className(), ['doc_profile_id' => 'id'])
                    ->orderBy(['sort' => SORT_ASC]);
    }
}
