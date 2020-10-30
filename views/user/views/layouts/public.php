<?php
use yii\helpers\Html;
use app\bundles\FormPageBundle;

/* @var $this \yii\web\View */
/* @var $content string */

FormPageBundle::register($this);

// Brand
$appName = Yii::$app->settings->get("app.name");
$brand = Html::tag("h1", $appName, ["class" => "app-name"]);
if ($logo = Yii::$app->settings->get("logo", "app", null)) {
    $brandLabel = Html::img(Yii::getAlias('@web/'.$logo), [
        'height' => '60px',
        'class' => 'app-name',
        'alt' => $appName,
        'title' => $appName,
    ]);
    $brand = Html::tag("div", $brandLabel, ["style" => "text-align: center"]);
}

?>
<?php $this->beginPage() ?>
    <!DOCTYPE html>
    <html lang="<?= Yii::$app->language ?>">
    <head>
        <meta charset="<?= Yii::$app->charset ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="shortcut icon" href="<?= Yii::$app->getHomeUrl() ?>favicon.ico" type="image/x-icon">
        <link rel="icon" href="<?= Yii::$app->getHomeUrl() ?>favicon_32.png" sizes="32x32">
        <link rel="icon" href="<?= Yii::$app->getHomeUrl() ?>favicon_48.png" sizes="48x48">
        <link rel="icon" href="<?= Yii::$app->getHomeUrl() ?>favicon_96.png" sizes="96x96">
        <link rel="icon" href="<?= Yii::$app->getHomeUrl() ?>favicon_144.png" sizes="144x144">
        <?= Html::csrfMetaTags() ?>
        <title><?= Html::encode($this->title) . ' | ' . $appName ?></title>
        <?php $this->head() ?>
    </head>
    <body class="form-page <?= $this->context->action->id ?>">

    <?php $this->beginBody() ?>

    <div class="container">
        <?= $brand ?>
        <?php // Alert::widget() ?>
        <?= $content ?>
    </div>

    <?php $this->endBody() ?>
    </body>
    </html>
<?php $this->endPage() ?>