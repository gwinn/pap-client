<?php

namespace Pap\Helpers;

use IntaroCrm\Exception\ApiException;
use IntaroCrm\Exception\CurlException;
use IntaroCrm\RestApi;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ApiHelper {
    private $dir, $fileDate, $errDir;
    protected $intaroApi, $log, $params;

    protected function initLogger() {
        $this->log = new Logger('pap');
        $this->log->pushHandler(new StreamHandler($this->dir . 'log/pap.log', Logger::INFO));
    }

    public function __construct() {
        $this->dir = __DIR__ . '/../../../';
        $this->fileDate = $this->dir . 'log/historyDate.log';
        $this->errDir = $this->dir . 'log/json';
        $this->params = parse_ini_file($this->dir . 'config/parameters.ini', true);

        $this->intaroApi = new RestApi(
            $this->params['intarocrm_api']['url'],
            $this->params['intarocrm_api']['key']
        );

        $this->initLogger();
    }

    public function orderCreate($order) {
        if (!isset($order['customFields'])) {
            $order['customFields'] = array();
        }
        $order['customFields'] = array_merge($order['customFields'], $this->getAdditionalParameters());

        if(isset($order['customer']['fio'])) {
            $contactNameArr = $this->explodeFIO($order['customer']['fio']);

            // parse fio
            if(count($contactNameArr) == 1) {
                $order['firstName']              = $contactNameArr[0];
                $order['customer']['firstName']  = $contactNameArr[0];
            } else {
                $order['lastName']               = $contactNameArr[0];
                $order['customer']['lastName']   = $contactNameArr[0];
                $order['firstName']              = $contactNameArr[1];
                $order['customer']['firstName']  = $contactNameArr[1];
                $order['patronymic']             = $contactNameArr[2];
                $order['customer']['patronymic'] = $contactNameArr[2];
            }
        }

        if(isset($order['customer']['phone'][0]) && $order['customer']['phone'][0])
            $order['phone'] = $order['customer']['phone'][0];

        try {
            $customers = $this->intaroApi->customers(
                isset($order['phone']) ? $order['phone'] : null,
                null, $order['customer']['fio'], 200, 0);

        } catch (ApiException $e) {
            $this->log->addError('RestApi::customers:' . $e->getMessage());
            $this->log->addError('RestApi::customers:' . json_encode($order));

            $this->sendMail(
                'Error: IntaroCRM - PAP',
                '<p> RestApi::customers:' . $e->getMessage() . '</p>' .
                '<p> RestApi::customers:' . json_encode($order) . '</p>'
            );
        } catch (CurlException $e) {
            $this->log->addError('RestApi::customers::Curl:' . $e->getMessage());

            $this->sendMail(
                'Error: IntaroCRM - PAP',
                '<p> RestApi::customers::Curl:' . $e->getMessage() . '</p>' .
                '<p> RestApi::customers::Curl:' . json_encode($order) . '</p>'
            );
        }

        if(count($customers) > 0) {
            $customer = current($customers);
            $order['customerId'] = $customer['externalId'];
        }
        else
            $order['customerId'] = (microtime(true) * 10000) . mt_rand(1, 1000);

        $order['customer']['externalId'] = $order['customerId'];

        try {
            $this->intaroApi->customerEdit($order['customer']);
            unset($order['customer']);

            $this->intaroApi->orderCreate($order);
        } catch (ApiException $e) {
            $this->log->addError('RestApi::orderCreate:' . $e->getMessage());
            $this->log->addError('RestApi::orderCreate:' . json_encode($order));

            $this->sendMail(
                'Error: IntaroCRM - PAP',
                '<p> RestApi::orderCreate:' . $e->getMessage() . '</p>' .
                '<p> RestApi::orderCreate:' . json_encode($order) . '</p>'
            );
        } catch (CurlException $e) {
            $this->log->addError('RestApi::orderCreate::Curl:' . $e->getMessage());

            $this->sendMail(
                'Error: IntaroCRM - PAP',
                '<p> RestApi::orderCreate::Curl:' . $e->getMessage() . '</p>' .
                '<p> RestApi::orderCreate::Curl:' . json_encode($order) . '</p>'
            );
        }

    }

    public function orderHistory() {
        //$this->sendErrorJson();

//        try {
//            $statuses = $this->intaroApi->orderStatusGroupsList();
//        } catch (ApiException $e) {
//            $this->log->addError('RestApi::orderStatusGroupsList:' . $e->getMessage());
//
//            $this->sendMail(
//                'Error: IntaroCRM - PAP',
//                '<p> RestApi::RestApi::orderStatusGroupsList:' . $e->getMessage() . '</p>'
//            );
//
//            return false;
//        } catch (CurlException $e) {
//            $this->log->addError('RestApi::orderStatusGroupsList::Curl:' . $e->getMessage());
//
//            $this->sendMail(
//                'Error: IntaroCRM - PAP',
//                '<p> RestApi::orderStatusGroupsList::Curl:' . $e->getMessage() . '</p>'
//            );
//
//            return false;
//        }

        try {
            $orders = $this->intaroApi->orderHistory($this->getDate());
            $this->saveDate($this->intaroApi->getGeneratedAt()->format('Y-m-d H:i:s'));
        } catch (ApiException $e) {
            $this->log->addError('RestApi::orderHistory:' . $e->getMessage());
            $this->log->addError('RestApi::orderHistory:' . json_encode($orders));

            $this->sendMail(
                'Error: IntaroCRM - PAP',
                '<p> RestApi::orderHistory:' . $e->getMessage() . '</p>' .
                '<p> RestApi::orderHistory:' . json_encode($orders) . '</p>'
            );

            return false;
        } catch (CurlException $e) {
            $this->log->addError('RestApi::orderHistory::Curl:' . $e->getMessage());

            $this->sendMail(
                'Error: IntaroCRM - PAP',
                '<p> RestApi::orderHistory::Curl:' . $e->getMessage() . '</p>' .
                '<p> RestApi::orderHistory::Curl:' . json_encode($orders) . '</p>'
            );

            return false;
        }

        foreach($orders as $order) {
            try {
                $o = $this->intaroApi->orderGet($order['id'], 'id');
            } catch (ApiException $e) {
                $this->log->addError('RestApi::orderGet:' . $e->getMessage());
                $this->log->addError('RestApi::orderGet:' . json_encode($order));

                $this->sendMail(
                    'Error: IntaroCRM - PAP',
                    '<p> RestApi::orderGet:' . $e->getMessage() . '</p>' .
                    '<p> RestApi::orderGet:' . json_encode($order) . '</p>'
                );

                return false;
            } catch (CurlException $e) {
                $this->log->addError('RestApi::orderGet::Curl:' . $e->getMessage());

                $this->sendMail(
                    'Error: IntaroCRM - PAP',
                    '<p> RestApi::orderGet::Curl:' . $e->getMessage() . '</p>' .
                    '<p> RestApi::orderGet::Curl:' . json_encode($order) . '</p>'
                );

                return false;
            }

            if(isset($o['customFields']) &&
                isset($o['customFields']['transaction_id']) &&
                $o['customFields']['transaction_id'] &&
                isset($o['orderMethod']) &&
                $o['orderMethod'] == $this->params['intarocrm_api']['orderMethod']
            ) {
                $this->sendPAP($o['status'], $o['customFields']['transaction_id']);
            }
        }

        return true;
    }

    private function saveDate($date) {
        file_put_contents($this->fileDate, $date, LOCK_EX);
    }

    private function getDate() {
        $result = file_get_contents($this->fileDate);
        if(!$result) {
            $result = new \DateTime();
            return $result->format('Y-m-d H:i:s');
        } else return $result;
    }

    public function sendPAP($status, $transaction) {

        include_once(__DIR__ . '/../../../../../pap/api/PapApi.class.php');

        if(!$status || !$transaction) {
            return false;
        }

        $session = new \Gpf_Api_Session($this->params['pap']['url']);

        if(!$session->login($this->params['pap']['login'],$this->params['pap']['password'])) {
            $this->log->addError('PAP auth:' . json_encode($session->getMessage()));
            return false;
        }

        $sale = new \Pap_Api_Transaction($session);
        $sale->setTransId($transaction);

        if (!($sale->load())) {
            $this->log->addError('PAP load transaction:' . json_encode($sale->getMessage()));
            return false;
        }

        if ($status == 'new') {
            $sale->setStatus('P');
        } elseif ($status == 'sent') {
            $sale->setStatus('A');
        } else {
            $sale->setStatus('D');
        }

        if(!$sale->save()) {
            $this->log->addError('Pap transaction update: ' . json_encode($sale->geetMessage()));
            return false;
        }

        return true;

    }

    public function setAdditionalParameters($query)
    {
        if(!$query) return;

        $params = array();
        parse_str($query, $params);
        $params = array_merge($this->getAdditionalParameters(), $params);

        foreach ($params as $key => $param) {
            if (empty($param)) {
                unset($params[$key]);
            }
        }

        setcookie($this->params['cookie_name'], serialize($params), time() + 60 * 60 * 24 * 365, '/');
    }

    public function getAdditionalParameters()
    {
        if (!isset($_COOKIE[$this->params['cookie_name']])) {
            return array();
        }

        return unserialize($_COOKIE[$this->params['cookie_name']]);
    }

    protected function sendMail($subject, $body) {
        if(!$subject || !$body || !$this->params['mail']['from'] || !$this->params['mail']['to'])
            return false;

        mail($this->params['mail']['to'], $subject, $body, 'From:'.$this->params['mail']['from']);
    }

    private function explodeFIO($str) {
        if(!$str)
            return array();

        $array = explode(" ", $str, 3);
        $newArray = array();

        foreach($array as $ar) {
            if(!$ar)
                continue;

            $newArray[] = $ar;
        }

        return $newArray;
    }

    private function isInGroup($status, $statuses) {
        foreach($statuses as $s)
            if($s == $status) return true;

        return false;
    }

    private function sendErrorJson() {
        foreach($this->getErrorFiles() as $fileName) {
            $result = $this->getErrorJson($fileName);
            unlink($fileName);
            if(isset($result['status']) && $result['status'] && isset($result['transaction_id'])
                && $result['transaction_id'] && isset($result['times_sent'])
                && $result['times_sent'] && $result['times_sent'] < 4)
                $this->sendPAP($result['status'], $result['transaction_id'], ++$result['times_sent']);
        }
    }

    private function getErrorFiles() {
        return glob($this->errDir . '/err_*.json', GLOB_BRACE);
    }

    private function getErrorJson($fileName) {
        $result = file_get_contents($fileName);
        if(!$result) return array();
        $result = json_decode($result, true);
        if(is_array($result)) return $result;
        else return array();
    }

    private function writeErrorJson(array $data) {
        file_put_contents($this->getErrFileName(), json_encode($data), LOCK_EX);
    }

    private function getErrFileName() {
        return $this->errDir . '/err_' . (microtime(true) * 10000) . mt_rand(1, 1000) .'.json';
    }
}