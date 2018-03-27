<?php

/**
 * MailQueue.php
 * @author Saranga Abeykoon http://nterms.com
 */

namespace jako\mailqueue;

use Yii;
use yii\swiftmailer\Mailer;
use jako\mailqueue\models\Queue;
use common\models\MailMonitor;

/**
 * MailQueue is a sub class of [yii\swiftmailer\Mailer](https://github.com/yiisoft/yii2-swiftmailer/blob/master/Mailer.php)
 * which intends to replace it.
 *
 * Configuration is the same as in `yii\swiftmailer\Mailer` with some additional properties to control the mail queue
 *
 * ~~~
 *    'components' => [
 *        ...
 *        'mailqueue' => [
 *            'class' => 'jako\mailqueue\MailQueue',
 *            'table' => '{{%mail_queue}}',
 *            'mailsPerRound' => 10,
 *            'maxAttempts' => 3,
 *            'transport' => [
 *                'class' => 'Swift_SmtpTransport',
 *                'host' => 'localhost',
 *                'username' => 'username',
 *                'password' => 'password',
 *                'port' => '587',
 *                'encryption' => 'tls',
 *            ],
 *        ],
 *        ...
 *    ],
 * ~~~
 *
 * @see http://www.yiiframework.com/doc-2.0/yii-swiftmailer-mailer.html
 * @see http://www.yiiframework.com/doc-2.0/ext-swiftmailer-index.html
 *
 * This extension replaces `yii\swiftmailer\Message` with `jako\mailqueue\Message'
 * to enable queuing right from the message.
 *
 */
class MailQueue extends Mailer
{
    const NAME = 'mailqueue';

    /**
     * @var string message default class name.
     */
    public $messageClass = 'jako\mailqueue\Message';

    /**
     * @var string the name of the database table to store the mail queue.
     */
    public $table = '{{%mail_queue}}';

    /**
     * @var integer the default value for the number of mails to be sent out per processing round.
     */
    public $mailsPerRound = 10;

    /**
     * @var integer maximum number of attempts to try sending an email out.
     */
    public $maxAttempts = 3;

    /**
     * @var boolean Purges messages from queue after sending
     */
    public $autoPurge = true;

    /**
     * Initializes the MailQueue component.
     * @throws yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();

        if (Yii::$app->db->getTableSchema($this->table) == null) {
            throw new yii\base\InvalidConfigException('""' . $this->table . '"" not found in database. Make sure the db migration is properly done and the table is created.');
        }
    }

    /**
     * Sends out the messages in email queue and update the database.
     *
     * @return boolean true if all messages are successfully sent out
     */
    public function process()
    {
        $success = true;

        $items = Queue::find()->where(['and', ['sent_time' => null], ['<', 'attempts', $this->maxAttempts], ['<=', 'time_to_send', date('Y-m-d H:i:s')]])->orderBy(['created_at' => SORT_ASC])->limit($this->mailsPerRound);
        foreach ($items->each() as $item) {
            /** @var Queue $item */
            if ($message = $item->toMessage()) {
                $attributes = ['attempts', 'last_attempt_time'];
                if ($this->send($message)) {
                    $item->sent_time = new \yii\db\Expression('NOW()');
                    $attributes[] = 'sent_time';

                    $monitor = MailMonitor::findOne($item->monitor_id);
                    if ($monitor) {
                        $monitor->sent_at = time();
                        $monitor->save();
                    }
                } else {
                    $success = false;
                }

                $item->attempts++;
                $item->last_attempt_time = new yii\db\Expression('NOW()');

                $item->updateAttributes($attributes);
            }
        }

        // Purge messages now?
        if ($this->autoPurge) {
            $this->purge();
        }

        return $success;
    }


    /**
     * Deletes sent messages from queue.
     *
     * @return int Number of rows deleted
     */

    public function purge()
    {
        return Queue::deleteAll('sent_time IS NOT NULL');
    }
}
