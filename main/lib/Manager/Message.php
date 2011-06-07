<?php
/**
 * MessageSystem Class
 *
 * Class to control the Messaging-System
 * @author Markus Schlegel <g42@gmx.net>
 * @copyright Copyright (C) 2007 Markus Schlegel
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package Ruins
 */

/**
 * Namespaces
 */
namespace Manager;
use Entities\Character,
    Manager\User,
    SessionStore;

/**
 * MessageSystem Class
 *
 * Class to control the Messaging-System
 * @package Ruins
 */
class Message
{
    const STATUS_UNREAD    = 0;
    const STATUS_READ      = 1;
    const STATUS_REPLIED   = 2;
    const STATUS_DELETED   = 3;

    /**
     * Write a normal Message
     * @param Character $sender ID of the Sender
     * @param mixed $receivers Receivers ID OR array of ID's OR "all" for all ID's
     * @param string $subject Subject of the Message
     * @param string $text Messagetext
     * @param int $status Messagestatus to set
     * @return int Number of Messages sent
     */
    public static function write(Character $sender, $receivers, $subject, $text, $status=self::STATUS_UNREAD)
    {
        global $em;

        // Add MessageData (happens only once)
        $messagedata          = new \Entities\MessageData;
        $messagedata->subject = $subject;
        $messagedata->text    = $text;
        $em->persist($messagedata);

        $receiverIDlist = array();

        if (is_array($receivers)) {
            $receiverIDlist = $receivers;
        } elseif (is_numeric($receivers)) {
            $receiverIDlist[] = $receivers;
        } elseif ($receivers instanceof Character) {
            $receiverIDlist[] = $receivers->id;
        } elseif (is_string($receivers) && $receivers != "all") {
            $receiverNameList = explode(",", $receivers);
            foreach ($receiverNameList as $receiver) {
                $receiverIDlist[] = User::getCharacterID(trim($receiver));
            }
        } elseif ($receivers == "all") {
            $receiverIDlist = User::getCharacterList("id");
        }

        // remove duplicates from List
        $receiverIDlist = array_unique($receiverIDlist);

        $nrSentMessages = 0;
        $batchSize = 20;
        $listSize = count($receiverIDlist);
        for ($i = 0; $i <= $listsize; $i++) {
            $message = new \Entities\Message;
            $message->sender = $sender;
            $message->receiver = $em->find("Entities\Character", $receiverIDlist[$i]);
            $message->data = $messagedata;
            $message->status = $status;
            if ($message->receiver) {
                // Receiver exists
                $em->persist($message);
                $nrSentMessages++;
            }

            if (($i % $batchSize) == 0 && $i > 0) {
                // Write to DB every $batchSize Inserts
                $em->flush();
            }
        }

        if ($nrSentMessages == 0) {
            // delete $messagedata
            $em->detach($messagedata);
        }

        return $nrSentMessages;

/*
        // Some Sanity-Checks
        if (!$subject) $subject=" ";
        if (!$text) $text=" ";

        $message = new Message;
        $message->create();

        // Fill in the Messagedata
        $message->sender = $sender;
        $message->subject = $subject;
        $message->text = $text;
        $message->date = date("Y-m-d H:i:s");
        $messageid = $message->save();

        // Add Messagerefs for the receivers
        if (is_array($receivers)) {
            // Array of ID's

            // Add DebugLogEntry
            if (isset ($user->char)) {
                $user->char->debuglog->add("Send Message to ids: ".implode(", ", $receivers), "veryverbose");
            }

            // Add Messageref
            $message->addReceiver($messageid, $receivers);
        } elseif (is_numeric($receivers)) {
            // Single ID

            // Add DebugLogEntry
            if (isset ($user->char)) {
                $user->char->debuglog->add("Send Message to $receivers", "veryverbose");
            }

            // Add Messageref
            $message->addReceiver($messageid, $receivers);
        } elseif (is_string($receivers) && strtolower($receivers) != "all") {
            // Charactername, separated by ;
            $characternames = explode(";", $receivers);

            // Add DebugLogEntry
            if (isset ($user->char)) {
                $user->char->debuglog->add("Send Message to ".implode(", ", $characternames), "veryverbose");
            }

            foreach ($characternames as $receivername) {
                // Add Messageref
                $message->addReceiver($messageid, Manager\User::getCharacterID(trim($receivername)));
            }
        } elseif (is_string($receivers) && strtolower($receivers) == "all") {
            // "all"
            // Get ID-List
            $dbqt = new QueryTool();
            $result = $dbqt	->select("id")
                            ->from("characters")
                            ->exec()
                            ->fetchCol("id");

            // Add DebugLogEntry
            if (isset ($user->char)) {
                $user->char->debuglog->add("Send Message to all Users!", "veryverbose");
            }

            // Add Messageref
            $message->addReceiver($messageid, $result);
        }

        return $messageid;
*/
    }

    /**
     * Delete a Message by setting the status to self::STATUS_DELETED
     * @param int|array $messageid
     */
    public static function delete($messageid)
    {
        self::updateMessageStatus($messageid, self::STATUS_DELETED);
/*
        $dbqt = new QueryTool();
        $result = $dbqt	->select("id")
                        ->from("messages_references")
                        ->where("messageid=".$messageid)
                        ->exec()
                        ->fetchCol("id");

        if (!is_array($result)) {
            throw new Error("Can't get Message_References List for Message ID " . $messageid . " (Messagesys->delete())");
        }

        // Delete the References
        foreach ($result as $ids) {
            self::updateMessageStatus($ids, self::STATUS_DELETED);
        }
*/
    }

    /**
     * Update Message Status
     * @param int|array $messageid ID of the Message to alter
     * @param int $status New Status
     */
    public static function updateMessageStatus($messageid, $status)
    {
        $qb = getQueryBuilder();

        $qb ->update("Entities\Message", "message")
            ->set("message.status", $status)
            ->where("message.id = ?1");

        if (is_array($messageid)) {
            foreach ($messageid as $id) {
                $qb->getQuery()->execute(array(1 => $id));
            }
        } else {
            $qb->getQuery()->execute(array(1 => $messageid));
        }
/*
        global $dbconnect;
        $dbqt = new QueryTool;

        $tablename 	= "messages_references";
        $values		= array ( "status" => $status );

        $dbqt	->update($tablename)
                ->set($values)
                ->where("messageid=".$messageid);

        if (is_numeric($receiverid) && $receiverid > 0) {
            $dbqt->where("receiver=".$receiverid);
        }

        $dbqt->exec();
*/
    }

    /**
     * Get a specific Message
     * @param int $messageid ID of the Message to get
     * @param mixed String or Array of Fields to retrieve
     */
    public static function getMessage($messageid, $fields=false)
    {
        global $em;

        return $em->find("Entities\Message", $messageid);

/*
        if (!$result = SessionStore::readCache("message_".$messageid."_".serialize($fields))) {

            $dbqt = new QueryTool();

            if (is_array($fields)) {
                $dbqt->select(implode(",", $fields));
            } else {
                $dbqt->select("*");;
            }

            $dbqt	->from("messages")
                    ->where("id=".$messageid);

            $result = $dbqt->exec()->fetchRow();

           SessionStore::writeCache("message_".$messageid."_".serialize($fields), $result);
        }

        return $result;
*/
    }

    /**
     * Get Message Inbox for a specific Character
     * @param Character $character Character Object
     * @param int $limit Number of Messages to get
     * @param bool $ascending Ascending sorting
     * @param int $status The Status of the Messages
     * @return array Array of Messages
     */
    public static function getInbox(Character $character, $limit=false, $ascending=true, $status=false)
    {
        $qb = getQueryBuilder();

        $qb  ->select("message")
             ->from("Entities\Message", "message")
             ->where("message.receiver = ?1")->setParameter(1, $character->id);


        if ($status) {
            $qb->andWhere("message.status = ?2")->setParameter(2, $status);
        } else {
            $qb->andWhere("message.status != ?2")->setParameter(2, self::STATUS_DELETED);
        }

        if ($limit) $qb->setMaxResults($limit);

        if ($ascending) {
            $qb->orderBy("message.date", "ASC");
        } else {
            $qb->orderBy("message.date", "DESC");
        }

        return $qb->getQuery()->getResult();

/*
        $dbqt = new QueryTool();

        if (is_array($fields)) {
            $dbqt->select(implode(", ", $fields));
        } else {
            $dbqt->select("messages.id, sender, receiver, subject, text, date, status");
        }

        $dbqt	->from("messages_references")
                ->join("messages", "messages.id = messages_references.messageid")
                ->where("receiver=".$character->id);

        if ($status && is_numeric($status)) {
            $dbqt->where("status=".$status);
        } else {
            $dbqt->where("status!=3"); // Message is not deleted
        }

        $dbqt	->order("date", $ascending)
                ->order("id", $ascending);

        if (is_numeric($limit)) {
            $dbqt->limit($limit, 0);
        }

        $result = $dbqt->exec()->fetchAll();

        return $result;
*/
    }

    /**
     * Get Message Outbox for a specific Character
     * @param Character $character Character Object
     * @param int $limit Number of Messages to get
     * @param bool $ascending Ascending sorting
     * @param int $status The Status of the Messages
     * @return array Array of Messages
     */
    public static function getOutbox(Character $character, $limit=false, $ascending=true, $status=false)
    {
        $qb = getQueryBuilder();

        $qb ->select("message")
            ->from("Entities\Message", "message")
            ->where("message.sender = ?1")->setParameter(1, $character);

        if ($limit) $qb->setMaxResults($limit);

        if ($ascending) {
            $qb->orderBy("message.date", "ASC");
        } else {
            $qb->orderBy("message.date", "DESC");
        }

        return $qb->getQuery()->getResult();


/*
        $dbqt = new Querytool;

        if (is_array($fields)) {
            $dbqt->select(implode(", ", $fields));
        } else {
            $dbqt->select("messages.id, sender, receiver, subject, text, date, status");
        }

        $dbqt	->from("messages_references")
                ->join("messages", "messages.id = messages_references.messageid")
                ->where("sender=".$character->id);

        if ($status && is_numeric($status)) {
            $dbqt->where("status=".$status);
        }

        $dbqt	->order("date", $ascending)
                ->order("id", $ascending);

        if (is_numeric($limit)) {
            $dbqt->limit($limit, 0);
        }

        $result = $dbqt->exec()->fetchAll();

        return $result;
*/
    }
}
?>
