<?php
/* @var $this \yii\web\View */
/* @var $content string */

use bedezign\yii2\audit\web\JSLoggingAsset;
use yii\bootstrap\Nav;
use yii\bootstrap\NavBar;
use yii\helpers\Html;
use yii\widgets\Breadcrumbs;

yii\debug\DebugAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php JSLoggingAsset::register($this); ?>
    <?php $this->registerCss('body{padding-top: 60px;}'); ?>
    <?php $this->head() ?>
</head>
<body>
<?php $this->beginBody() ?>

<?php
NavBar::begin([
    'brandLabel' => Yii::t('audit', 'Audit'),
    'brandUrl' => ['default/index'],
    'options' => ['class' => 'navbar-default navbar-fixed-top navbar-fluid'],
    'innerContainerOptions' => ['class' => 'container-fluid'],
]);
echo Nav::widget([
    'items' => [
        ['label' => Yii::t('audit', 'Entries'), 'url' => ['entry/index']],
        ['label' => Yii::t('audit', 'Trails'), 'url' => ['trail/index']],
        ['label' => Yii::t('audit', 'JS'), 'url' => ['javascript/index']],
        ['label' => Yii::t('audit', 'Errors'), 'url' => ['error/index']],
    ],
    'options' => ['class' => 'navbar-nav'],
]);
echo Nav::widget([
    'items' => [
        ['label' => Yii::$app->name, 'url' => Yii::$app->getHomeUrl()],
    ],
    'options' => ['class' => 'navbar-nav navbar-right'],
]);
NavBar::end();
?>

<div class="container-fluid">
    <?php if (isset($this->params['breadcrumbs'])) { ?>
        <div class="breadcrumbs">
            <?= Breadcrumbs::widget([
                'links' => $this->params['breadcrumbs'],
            ]) ?>
        </div>
    <?php } ?>
    <?= $content ?>
</div>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
