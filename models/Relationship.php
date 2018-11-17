<?php

namespace humhub\modules\relationships\models;

use humhub\modules\relationships\activities\CreatedRelationship;
use humhub\modules\relationships\notifications\ApproveRelationship;
use humhub\modules\relationships\notifications\CreateRelationship;
use humhub\modules\relationships\notifications\DenyRelationship;
use humhub\modules\relationships\notifications\RemoveRelationship;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\models\Content;
use humhub\modules\user\models\User;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Query;

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

    /**
     * @return string
     */
    public function getContentName()
    {
        return "Relationship";
    }

    /**
     * @return string
     */
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

    /**
     * @throws \yii\base\InvalidConfigException
     * todo: Need to alter relationship so that the deny relationship notification can be sent out.
     */
    public function afterDeny()
    {
        DenyRelationship::instance()->from($this->otherUser)->about($this)->send($this->user);

        parent::afterDelete(); // TODO: Change the autogenerated stub
    }

    /**
     * todo: Same as above. RemoveRelationship notification is never sent out.
     * @throws \yii\base\InvalidConfigException
     */
    public function otherUserRemovedRelationship()
    {
        RemoveRelationship::instance()->from($this->otherUser)->about($this)->send($this->user);
    }

    /**
     * todo: Same as above.
     * @throws \yii\base\InvalidConfigException
     */
    public function userRemovedRelationship()
    {
        RemoveRelationship::instance()->from($this->user)->about($this)->send($this->otherUser);
    }

    public static function getAllRelationships()
    {
        $query = new Query();
        $query->select(['relationship.id as id', 'relationship.user_id AS send_user', 'relationship.other_user_id AS recv_user', 'relationship_type.type AS type',
            'send_user.username AS send_user_username', 'recv_user.username AS recv_user_username', 'relationship.approved AS approved' ])
            ->from('relationship')
            ->leftJoin('user AS send_user', 'send_user.id = relationship.user_id' )
            ->leftJoin('user AS recv_user', 'recv_user.id = relationship.other_user_id' )
            ->leftJoin('relationship_type', 'relationship_type.id = relationship.relationship_type')
            ->where(['relationship.user_id' => Yii::$app->user->id])->orWhere(['relationship.other_user_id' => Yii::$app->user->id])
            ->andWhere(['approved' => 1])->all();

        return $query;
    }

    public static function getAllPendingRelationshipsQuery(){

        $query = new Query();
        $query->select(['relationship.id as id', 'relationship.user_id AS send_user', 'relationship.other_user_id AS recv_user', 'relationship_type.type AS type',
            'send_user.username AS send_user_username', 'recv_user.username AS recv_user_username', 'relationship.approved AS approved' ])
            ->from('relationship')
            ->leftJoin('user AS send_user', 'send_user.id = relationship.user_id' )
            ->leftJoin('user AS recv_user', 'recv_user.id = relationship.other_user_id' )
            ->leftJoin('relationship_type', 'relationship_type.id = relationship.relationship_type')
            ->where(['relationship.user_id' => Yii::$app->user->id])->orWhere(['relationship.other_user_id' => Yii::$app->user->id])
            ->andWhere(['approved' => 0])->all();

        return $query;
    }


}
