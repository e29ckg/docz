<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "doc_profile_sub".
 *
 * @property int $id
 * @property string $doc_cat_profile_id
 * @property int|null $role_name_id
 * @property int|null $sort
 */
class DocProfileSub extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'doc_profile_sub';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['doc_profile_id','role_name_id'], 'required'],
            [['role_name_id', 'sort'], 'integer'],
            [['doc_profile_id'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'doc_profile_id' => 'Doc Profile ID',
            'role_name_id' => 'ผู้เซ็นต์',
            'sort' => 'Sort',
        ];
    }
    public function getRolename()
    {
        return $this->hasOne(RoleName::className(), ['id' => 'role_name_id']);
    }
}
