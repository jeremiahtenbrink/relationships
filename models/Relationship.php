<?php

namespace conerd\humhub\modules\relationships\models;

use conerd\humhub\modules\relationships\activities\CreatedRelationship;
use conerd\humhub\modules\relationships\notifications\ApproveRelationship;
use conerd\humhub\modules\relationships\notifications\CreateRelationship;
use conerd\humhub\modules\relationships\notifications\DenyRelationship;
use conerd\humhub\modules\relationships\notifications\RemoveRelationship;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\models\Content;
use humhub\modules\user\models\User;
use Yii;

/**
 * This is the model class for table "relationship".
 *
 * @property int $id
 * @property int $user_id
 * @property int $other_user_id
 * @property int $relationship_type
 * @property int $approved
 *
 * @property User $otherUser
 * @property RelationshipType $relationshipType
 * @property User $user
 */
class Relationship extends ContentActiveRecord
{

    protected $moduleId = 'relationships';

    protected $canMove = false;


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'relationship';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'other_user_id', 'relationship_type'], 'required'],
            [['user_id', 'other_user_id', 'relationship_type', 'approved'], 'integer'],
            [['other_user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['other_user_id' => 'id']],
            [['relationship_type'], 'exist', 'skipOnError' => true, 'targetClass' => RelationshipType::className(), 'targetAttribute' => ['relationship_type' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],
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
            'other_user_id' => 'Other User ID',
            'relationship_type' => 'Relationship Type',
            'approved' => 'Approved',
        ];
    }

    public function getContentName()
    {
        return "Relationship";
    }

    public function getContentDescription()
    {
        return "Gives users the ability to create different relationship types with each other.";
    }


    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOtherUser()
    {
        return $this->hasOne(User::className(), ['id' => 'other_user_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRelationshipType()
    {
        return $this->hasOne(RelationshipType::className(), ['id' => 'relationship_type']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    /**
     * @param bool $insert
     * @param array $changedAttributes
     * @throws \yii\base\InvalidConfigException
     */
    public function afterSave($insert, $changedAttributes)
    {
        if ($this->approved)
        {
            ApproveRelationship::instance()->from($this->otherUser)->about($this)->send($this->user);
            CreatedRelationship::instance()->from($this->user)->container($this->user)->about($this)->create();

        }else
        {
            CreateRelationship::instance()->from($this->user)->about($this)->send($this->otherUser);
        }

        return parent::afterSave($insert, $changedAttributes); // TODO: Change the autogenerated stub

    }

    public function afterDeny()
    {
        DenyRelationship::instance()->from($this->otherUser)->about($this)->send($this->user);

        parent::afterDelete(); // TODO: Change the autogenerated stub
    }

    public function otherUserRemovedRelationship()
    {
        RemoveRelationship::instance()->from($this->otherUser)->about($this)->send($this->user);
    }

    public function userRemovedRelationship()
    {
        RemoveRelationship::instance()->from($this->user)->about($this)->send($this->otherUser);
    }


}
