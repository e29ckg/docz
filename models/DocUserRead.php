<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "doc_user_read".
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $doc_id
 * @property int|null $ckeck
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
            [['doc_id', 'ckeck'], 'integer'],
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
            'ckeck' => 'Ckeck',
            'ip' => 'Ip',
            'updated' => 'Updated',
            'created' => 'Created',
        ];
    }
    public function getDocz()
    {
        return $this->hasOne(Docz::className(), ['id'=>'doc_id']);
    }
}
