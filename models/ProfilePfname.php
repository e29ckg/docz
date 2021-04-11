<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "profile_pfname".
 *
 * @property int $id
 * @property string|null $name
 * @property int|null $sort
 * @property string $created_at
 */
class ProfilePfname extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'profile_pfname';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['sort'], 'integer'],
            [['created_at'], 'safe'],
            [['name'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'sort' => 'Sort',
            'created_at' => 'Created At',
        ];
    }
}
