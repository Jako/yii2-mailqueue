<?php

/**
 * Message.php
 * @author Saranga Abeykoon http://nterms.com
 */

namespace jako\mailqueue;

use jako\mailqueue\models\Queue;

/**
 * Extends `yii\swiftmailer\Message` to enable queuing.
 *
 * @see http://www.yiiframework.com/doc-2.0/yii-swiftmailer-message.html
 */
class Message extends \yii\swiftmailer\Message
{
    /**
     * Enqueue the message storing it in database.
     *
     * @param int|string $time_to_send unix timestamp or 'now'
     * @param int $monitor_id
     * @return boolean true on success, false otherwise
     */
    public function queue($time_to_send = 'now', $monitor_id = 0)
    {
        if($time_to_send == 'now') {
            $time_to_send = time();
        }

        $item = new Queue();

        $item->subject = $this->getSubject();
        $item->attempts = 0;
        $item->swift_message = base64_encode(serialize($this));
        $item->time_to_send = date('Y-m-d H:i:s', $time_to_send);
        $item->monitor_id = $monitor_id;

        return $item->save();
    }
}
