<?php
/**
 * Copyright (C) Baluart.COM - All Rights Reserved
 *
 * @since 1.0
 * @author Balu
 * @copyright Copyright (c) 2015 - 2019 Baluart.COM
 * @license http://codecanyon.net/licenses/faq Envato marketplace licenses
 * @link http://easyforms.baluart.com/ Easy Forms
 */

namespace app\models;

use Yii;
use yii\swiftmailer\Mailer;
use yii\swiftmailer\Message;
use app\modules\user\models\UserToken;
use app\helpers\MailHelper;

/**
 * This is the model class for table "tbl_user".
 *
 * @property string $id
 * @property string $role_id
 * @property integer $status
 * @property string $email
 * @property string $username
 * @property string $password
 * @property string $auth_key
 * @property string $access_token
 * @property string $logged_in_ip
 * @property string $logged_in_at
 * @property string $created_ip
 * @property string $created_at
 * @property string $updated_at
 * @property string $banned_at
 * @property string $banned_reason
 * @property string $preferences
 *
 * @property Profile   $profile
 * @property Form[]    $forms
 * @property Theme[]   $themes
 * @property FormUser[] $userForms
 * @property Form[]     $assignedForms
 *
 * @property \app\modules\user\models\Role      $role
 * @property \app\modules\user\models\UserToken[] $userTokens
 * @property \app\modules\user\models\UserAuth[] $userAuths
 */
class User extends \app\modules\user\models\User
{

    /**
     * @inheritdoc
     */
    public static function primaryKey()
    {
        return ['id'];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        // set initial rules
        $rules = [
            // general email and username rules
            [['email', 'username'], 'string', 'max' => 255],
            [['email', 'username'], 'unique'],
            [['email', 'username'], 'filter', 'filter' => 'trim'],
            [['email'], 'email'],
            [['username'], 'match', 'pattern' => '/^[A-Za-z0-9_]+$/u', 'message' => Yii::t('app', '{attribute} can contain only letters, numbers, and "_"')],

            // password rules
            [['newPassword'], 'string', 'min' => 3],
            [['newPassword'], 'filter', 'filter' => 'trim'],
            [['newPassword'], 'required', 'on' => ['register', 'reset', 'update']],
            [['newPasswordConfirm'], 'required', 'on' => ['reset', 'update']],
            [['newPasswordConfirm'], 'compare', 'compareAttribute' => 'newPassword', 'message' => Yii::t('app', 'Passwords do not match')],

            // account page
            [['currentPassword'], 'validateCurrentPassword', 'on' => ['account', 'update']],

            // admin crud rules
            [['role_id', 'status'], 'required', 'on' => ['admin']],
            [['role_id', 'status'], 'integer', 'on' => ['admin']],
            [['banned_at'], 'integer', 'on' => ['admin']],
            [['banned_reason'], 'string', 'max' => 255, 'on' => 'admin'],

            // preferences
            [['preferences'], 'string'],
        ];

        // add required for currentPassword on account page
        // only if $this->password is set (might be null from a social login)
        if ($this->password) {
            $rules[] = [['currentPassword'], 'required', 'on' => ['account', 'update']];
        }

        // add required rules for email/username depending on module properties
        $requireFields = ["requireEmail", "requireUsername"];
        foreach ($requireFields as $requireField) {
            if ($this->module->$requireField) {
                $attribute = strtolower(substr($requireField, 7)); // "email" or "username"
                $rules[] = [$attribute, "required"];
            }
        }

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'role_id' => Yii::t('app', 'Role ID'),
            'status' => Yii::t('app', 'Status'),
            'email' => Yii::t('app', 'Email'),
            'username' => Yii::t('app', 'Username'),
            'password' => Yii::t('app', 'Password'),
            'preferences' => Yii::t('app', 'Preferences'),
            'auth_key' => Yii::t('app', 'Auth Key'),
            'access_token' => Yii::t('app', 'Access Token'),
            'logged_in_ip' => Yii::t('app', 'Logged In Ip'),
            'logged_in_at' => Yii::t('app', 'Logged In At'),
            'created_ip' => Yii::t('app', 'Created Ip'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
            'banned_at' => Yii::t('app', 'Banned At'),
            'banned_reason' => Yii::t('app', 'Banned Reason'),

            // virtual attributes set above
            'currentPassword' => Yii::t('app', 'Current Password'),
            'newPassword' => $this->isNewRecord ? Yii::t('app', 'Password') : Yii::t('app', 'New Password'),
            'newPasswordConfirm' => Yii::t('app', 'New Password Confirm'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getForms()
    {
        return $this->hasMany(Form::className(), ['created_by' => 'id'])->inverseOf('author');
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getThemes()
    {
        return $this->hasMany(Theme::className(), ['created_by' => 'id'])->inverseOf('author');
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserForms()
    {
        return $this->hasMany(FormUser::className(), ['user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAssignedForms()
    {
        return $this->hasMany(Form::className(), ['id' => 'user_id'])
            ->via('userForms');
    }

    /**
     * Validate current password (account page)
     */
    public function validateCurrentPassword()
    {
        if (!$this->validatePassword($this->currentPassword)) {
            $this->addError("currentPassword", Yii::t('app', 'Incorrect password.'));
        }
    }

    /**
     * Send email confirmation to user
     *
     * @param UserToken $userToken
     * @return int
     */
    public function sendEmailConfirmation($userToken)
    {
        /** @var Mailer $mailer */
        /** @var Message $message */

        // modify view path to module views
        $mailer           = Yii::$app->mailer;
        $oldViewPath      = $mailer->viewPath;
        $mailer->viewPath = Yii::$app->getModule("user")->emailViewPath;

        // send email
        $user    = $this;
        $profile = $user->profile;
        $email = $userToken->data ?: $user->email;
        $subject = Yii::$app->settings->get("app.name") . " - " . Yii::t("app", "Email Confirmation");
        $message  = $mailer->compose('confirmEmail', compact("subject", "user", "profile", "userToken"))
            ->setTo($email)
            ->setSubject($subject);

        // Sender by default: Support Email
        $fromEmail = MailHelper::from(Yii::$app->settings->get("app.supportEmail"));

        // Sender verification
        if (empty($fromEmail)) {
            return false;
        }

        $message->setFrom($fromEmail);

        $result = $message->send();

        // restore view path and return result
        $mailer->viewPath = $oldViewPath;
        return $result;
    }
}
