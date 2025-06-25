<?php

namespace Kavenegar;

use Kavenegar\Enums\ApiLogs;
use Kavenegar\Exceptions\ApiException;
use Kavenegar\Exceptions\BaseRuntimeException;
use Kavenegar\Exceptions\HttpException;

class KavenegarApi
{
    const API_PATH = "%s://api.kavenegar.com/v1/%s/%s/%s.json/";
    const VERSION = "1.2.2";

    protected $apiKey;
    protected $insecure;

    /**
     * @param string $apiKey : Kavenegar API Key
     * @param bool $insecure : Set false if you want to use http instead of https
     */
    public function __construct($apiKey, $insecure = false)
    {
        if (!extension_loaded('curl')) {
            die('cURL library is not loaded');
        }
        if (is_null($apiKey)) {
            die('apiKey is empty');
        }
        $this->apiKey = trim($apiKey);
        $this->insecure = $insecure;
    }

    /**
     * Send a specific message to one or multiple receptors
     *
     * @param string $sender Sender's phone number
     * @param string|array $receptor Receptor(s) mobile number(s)
     * @param string $message Message text to be sent
     * @param int $date (Optional) UNIX timestamp of when the message should be sent. If null, message is sent immediately.
     * @param string $type (Optional) Message type
     * @param int|array|string $localId (Optional) ID in local database
     * @param int $hide (Optional) If set to 1, recipient's number won't be shown in list.
     * @param string $tag (Optional) Tag name
     * @return mixed
     */
    public function Send($sender, $receptor, $message, $date = null, $type = null, $localId = null, $hide = 0, $tag = null)
    {
        if (is_array($receptor)) {
            $receptor = implode(",", $receptor);
        }
        if (is_array($localId)) {
            $localId = implode(",", $localId);
        }
        $path = $this->get_path("send");
        $params = array(
            "receptor" => $receptor,
            "sender" => $sender,
            "message" => $message,
            "date" => $date,
            "type" => $type,
            "localid" => $localId,
            "hide" => $hide,
            "tag" => $tag
        );
        return $this->execute($path, $params);
    }

    protected function get_path($method, $base = 'sms')
    {
        return sprintf(self::API_PATH, $this->insecure ? "http" : "https", $this->apiKey, $base, $method);
    }

    protected function execute($url, $data = null)
    {
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'charset: utf-8'
        );
        $fields_string = "";
        if (!is_null($data)) {
            $fields_string = http_build_query($data);
        }
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $fields_string);

        $response = curl_exec($handle);
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curl_errno = curl_errno($handle);
        $curl_error = curl_error($handle);
        if ($curl_errno) {
            throw new HttpException($curl_error, $curl_errno);
        }
        $json_response = json_decode($response, false);
        if ($code !== 200 && is_null($json_response)) {
            throw new HttpException("Request have errors", $code);
        }

        $json_return = $json_response->return;
        if ($json_return->status !== 200) {
            throw new ApiException($json_return->message, $json_return->status);
        }
        return $json_response->entries;
    }

    /**
     * Send multiple messages from multiple senders to multiple receptors
     *
     * @param string $sender Senders phone numbers
     * @param string|array $receptor Receptors mobile numbers
     * @param string $message Texts to be sent
     * @param int $date (Optional) UNIX timestamp of when the message should be sent. If null, message is sent immediately.
     * @param array $type Type of messages
     * @param array $localMessageId Message IDs in local database
     * @param int $hide (Optional) If set to 1, recipient's number won't be shown in list.
     * @param string $tag (Optional) Tag name
     * @return mixed
     */
    public function SendArray($sender, $receptor, $message, $date = null, $type = null, $localMessageId = null, $hide = 0, $tag = null)
    {
        if (!is_array($receptor)) {
            $receptor = (array)$receptor;
        }
        if (!is_array($sender)) {
            $sender = (array)$sender;
        }
        if (!is_array($message)) {
            $message = (array)$message;
        }
        $repeat = count($receptor);
        if (!is_null($type) && !is_array($type)) {
            $type = array_fill(0, $repeat, $type);
        }
        if (!is_null($localMessageId) && !is_array($localMessageId)) {
            $localMessageId = array_fill(0, $repeat, $localMessageId);
        }
        $path = $this->get_path("sendarray");
        $params = array(
            "receptor" => json_encode($receptor),
            "sender" => json_encode($sender),
            "message" => json_encode($message),
            "date" => $date,
            "type" => json_encode($type),
            "localmessageid" => json_encode($localMessageId),
            "hide" => $hide,
            "tag" => $tag
        );
        return $this->execute($path, $params);
    }

    /**
     * Fetch status of message(s) by Message ID
     *
     * @param int|array|string $messageId : Unique ID(s) of message(s)
     * @return mixed
     */
    public function Status($messageId)
    {
        $path = $this->get_path("status");
        $params = array(
            "messageid" => is_array($messageId) ? implode(",", $messageId) : $messageId
        );
        return $this->execute($path, $params);
    }

    /**
     * Fetch status of message(s) by Local ID
     *
     * @param int|array|string $localId Local ID(s) of message(s) in database
     * @return mixed
     */
    public function StatusLocalMessageId($localId)
    {
        $path = $this->get_path("statuslocalmessageid");
        $params = array(
            "localid" => is_array($localId) ? implode(",", $localId) : $localId
        );
        return $this->execute($path, $params);
    }

    /**
     * Recover data of sent message
     *
     * @param int|array|string $messageId : Unique ID(s) of message(s) to recover
     * @return mixed
     */
    public function Select($messageId)
    {
        $params = array(
            "messageid" => is_array($messageId) ? implode(",", $messageId) : $messageId
        );
        $path = $this->get_path("select");
        return $this->execute($path, $params);
    }

    /**
     * List of outbox messages within specific time range
     *
     * @param int $startDate : UNIX timestamp of start date
     * @param int $endDate : UNIX timestamp of end date
     * @param string $sender (Optional) Sender phone number to filter
     * @return mixed
     */
    public function SelectOutbox($startDate, $endDate, $sender = null)
    {
        $path = $this->get_path("selectoutbox");
        $params = array(
            "startdate" => $startDate,
            "enddate" => $endDate,
            "sender" => $sender
        );
        return $this->execute($path, $params);
    }

    /**
     * List of latest sent messages
     *
     * @param int $pageSize (Optional) Size of the required messages
     * @param string $sender (Optional) Sender phone number to filter
     * @return mixed
     */
    public function LatestOutbox($pageSize = null, $sender = null)
    {
        $path = $this->get_path("latestoutbox");
        $params = array(
            "pagesize" => $pageSize,
            "sender" => $sender
        );
        return $this->execute($path, $params);
    }

    /** Count of outbox messages within specific time range
     *
     *
     * @param int $startDate UNIX timestamp of start date
     * @param int $endDate UNIX timestamp of end date
     * @param int $status (Optional) Message status to filter
     * @return mixed
     */
    public function CountOutbox($startDate, $endDate, $status = 0)
    {
        $path = $this->get_path("countoutbox");
        $params = array(
            "startdate" => $startDate,
            "enddate" => $endDate,
            "status" => $status
        );
        return $this->execute($path, $params);
    }

    /**
     * Cancel the scheduled message
     *
     * @param int $messageId Unique ID of message
     * @return mixed
     */
    public function Cancel($messageId)
    {
        $path = $this->get_path("cancel");
        $params = array(
            "messageid" => is_array($messageId) ? implode(",", $messageId) : $messageId
        );
        return $this->execute($path, $params);

    }

    /**
     * List of received messages
     *
     * @param string $lineNumber Target mobile number
     * @param int $isRead Set 0 to get unread messages
     * @return mixed
     */
    public function Receive($lineNumber, $isRead = 0)
    {
        $path = $this->get_path("receive");
        $params = array(
            "linenumber" => $lineNumber,
            "isread" => $isRead
        );
        return $this->execute($path, $params);
    }

    /**
     * Count of inboxed messages on the all numbers or a specific number
     *
     * @param int $startDate UNIX timestamp of start date
     * @param int $endDate (Optional) UNIX timestamp of end date
     * @param string $lineNumber (Optional) Pass mobile number to filter the results
     * @param int $isRead Set 0 to count unread messages, 1 to read
     * @return mixed
     */
    public function CountInbox($startDate, $endDate = null, $lineNumber = null, $isRead = 0)
    {
        $path = $this->get_path("countinbox");
        $params = array(
            "startdate" => $startDate,
            "enddate" => $endDate,
            "linenumber" => $lineNumber,
            "isread" => $isRead
        );
        return $this->execute($path, $params);
    }

    public function CountPostalcode($postalCode)
    {
        throw new BaseRuntimeException("Method is removed");
    }

    public function SendbyPostalcode($sender, $postalCode, $message, $mciStartIndex, $mciCount, $mtnStartIndex, $mtnCount, $date)
    {
        throw new BaseRuntimeException("Method is removed");
    }

    /**
     * Get account info
     *
     * @return mixed
     */
    public function AccountInfo()
    {
        $path = $this->get_path("info", "account");
        return $this->execute($path);
    }

    /**
     * Update the account settings
     *
     * @param ApiLogs|string $apiLogs (Optional) Api log status
     * @param string $dailyReport (Optional) 'Set' enabled to enable the daily report.
     * @param string $debug (Optional) Set 'enabled' to enable the debug mode. In debug mode, your message won't be sent.
     * @param string $defaultSender (Optional) Set the default sender phone number
     * @param int $minCreditAlarm (Optional) Set the minimum credit to alert
     * @param string $resendFailed (Optional) set 'enabled' to resend the failed messages
     * @return mixed
     */
    // Todo => Update the doc
    // Todo: Enum for api logs
    // Todo: Enum for 'resend failed
    public function AccountConfig($apiLogs = 'justfaults', $dailyReport = 'disabled', $debug = 'disabled', $defaultSender = null, $minCreditAlarm = null, $resendFailed = null)
    {
        $path = $this->get_path("config", "account");
        $params = array(
            "apilogs" => $apiLogs,
            "dailyreport" => $dailyReport,
            "debug" => $debug,
            "defaultsender" => $defaultSender,
            "mincreditalarm" => $minCreditAlarm,
            "resendfailed" => $resendFailed
        );
        return $this->execute($path, $params);
    }

    /**
     * Verify users using OTP code or send necessary messages to user
     *
     * @param string $receptor Receptor phone number
     * @param string $token Token
     * @param string $token2 (Optional) Token2
     * @param string $token3 (Optional) Token3
     * @param string $template Defined template name
     * @param string $type (sms|call)
     * @param string $tag Tag name
     * @return mixed
     */

    // Todo => enum for type
    public function VerifyLookup($receptor, $token, $token2, $token3, $template, $type = null, $tag = null)
    {
        $path = $this->get_path("lookup", "verify");
        $params = array(
            "receptor" => $receptor,
            "token" => $token,
            "token2" => $token2,
            "token3" => $token3,
            "template" => $template,
            "type" => $type
        );
        if (func_num_args() > 5) {
            $arg_list = func_get_args();
            if (isset($arg_list[6])) {
                $params["token10"] = $arg_list[6];
            }
            if (isset($arg_list[7])) {
                $params["token20"] = $arg_list[7];
            }
        }
        return $this->execute($path, $params);
    }

    /**
     * @param string $receptor Receptor phone number
     * @param string $message Message text to be sent
     * @param int $date (Optional) UNIX timestamp of when the message should be sent. If null, message is sent immediately.
     * @param int $localId (Optional) ID in local database
     * @param string $tag Tag name
     * @return mixed
     */
    public function CallMakeTTS($receptor, $message, $date = null, $localId = null, $tag = null)
    {
        $path = $this->get_path("maketts", "call");
        $params = array(
            "receptor" => $receptor,
            "message" => $message,
            "date" => $date,
            "localid" => $localId,
            "tag" => $tag
        );
        return $this->execute($path, $params);
    }
}
