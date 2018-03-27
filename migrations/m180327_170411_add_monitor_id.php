<?php

use yii\db\Schema;
use yii\db\Migration;
use jako\mailqueue\MailQueue;

/**
 * Add monitor_id index to table `mail_queue`
 */
class m180327_170411_add_monitor_id extends Migration
{
    public function up()
    {
        $table = Yii::$app->get(MailQueue::NAME)->table;
        $this->addColumn($table, 'monitor_id',  Schema::TYPE_BIGINT);
    }

    public function down()
    {
        $table = Yii::$app->get(MailQueue::NAME)->table;
        $this->dropColumn($table, 'monitor_id');
    }
}
