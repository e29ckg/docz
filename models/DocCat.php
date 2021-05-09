<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "doc_cat".
 *
 * @property int $id
 * @property int|null $doc_id
 * @property int|null $doc_cat_name_id
 * @property string|null $note
 */
class DocCat extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'doc_cat';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['doc_id', 'doc_cat_name_id'], 'integer'],
            [['note'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'doc_id' => 'Doc ID',
            'doc_cat_name_id' => 'Doc Cat Name ID',
            'note' => 'Note',
        ];
    }

    public function getDocz()
    {
        return $this->hasOne(DocZ::className(), ['id'=>'doc_id']);
    }

    public function doc_out_count()
    {
        $models = Docz::find()->select('id')->where(['st'=>4])->all();
        $count = 0;
        foreach($models as $model){
            $doc_count = DocCat::find()->select('id')->where(['doc_id'=>$model->id])->count();
            if($doc_count == 0){                
                $count++;
            }
        }
        return $count ? $count : '';
    }

    public function doc_cat_count($doc_cat_name_id){       
        
        return DocCat::find()->select('id')->where(['doc_cat_name_id'=>$doc_cat_name_id])->count();
    }
}
