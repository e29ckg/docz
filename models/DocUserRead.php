<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "doc_user_read".
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $doc_id
 * @property int|null $check
 * @property string|null $ip
 * @property string|null $updated
 * @property string|null $created
 */
class DocUserRead extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'doc_user_read';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['doc_id', 'check'], 'integer'],
            [['user_id','updated', 'created'], 'safe'],
            [['ip'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'doc_id' => 'Doc ID',
            'check' => 'Check',
            'ip' => 'Ip',
            'updated' => 'Updated',
            'created' => 'Created',
        ];
    }
    public function getDocz()
    {
        return $this->hasOne(Docz::className(), ['id'=>'doc_id']);
    }
    public function getProfile()
    {
        return $this->hasOne(UserProfile::className(), ['user_id' => 'user_id']);
    }
    public function username()
    {
        return $this->profile->pfname.$this->profile->name.' '.$this->profile->sname;
    }
}
