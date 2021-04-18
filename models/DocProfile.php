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
            [['name'], 'string', 'max' => 255],
            [['name'], 'required'],
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
        ];
    }
    public function getDoccps()
    {
        return $this->hasMany(DocProfileSub::className(), ['doc_profile_id' => 'id'])
                    ->orderBy(['sort' => SORT_ASC]);
    }
}
