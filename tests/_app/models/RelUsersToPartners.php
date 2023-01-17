<?php
declare(strict_types = 1);

namespace app\models\users\active_record\relations;

use app\models\Partners;
use app\models\Users;
use pozitronik\relations\traits\RelationsTrait;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Связка пользователей с партнёрами.
 *
 * @property int $id Идентификатор
 * @property int $partner_id Идентификатор партнёра
 * @property int $user_id Идентификатор пользователя
 *
 * @property Partners $partner
 * @property Users $user
 */
class RelUsersToPartners extends ActiveRecord {
	use RelationsTrait;

	/**
	 * {@inheritdoc}
	 */
	public static function tableName():string {
		return 'rel_users_to_partners';
	}

	/**
	 * {@inheritdoc}
	 * Note: this rules have unusual order, but relation should work anyway
	 */
	public function rules():array {
		return [
			[['partner_id', 'user_id'], 'required'],
			[['partner_id', 'user_id'], 'default', 'value' => null],
			[['partner_id', 'user_id'], 'integer'],
			[['user_id', 'partner_id'], 'unique', 'targetAttribute' => ['user_id', 'partner_id']],
			[
				['partner_id'],
				'exist',
				'skipOnError' => true,
				'targetClass' => Partners::class,
				'targetAttribute' => ['partner_id' => 'id']
			],
			[
				['user_id'],
				'exist',
				'skipOnError' => true,
				'targetClass' => Users::class,
				'targetAttribute' => ['user_id' => 'id']
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels():array {
		return [
			'id' => 'Идентификатор',
			'partner_id' => 'Идентификатор партнёра',
			'user_id' => 'Идентификатор пользователя',
		];
	}

	/**
	 * @return ActiveQuery
	 */
	public function getRelatedPartner():ActiveQuery {
		return $this->hasOne(Partners::class, ['id' => 'partner_id']);
	}

	/**
	 * @return ActiveQuery
	 */
	public function getRelatedUser():ActiveQuery {
		return $this->hasOne(Users::class, ['id' => 'user_id']);
	}
}
