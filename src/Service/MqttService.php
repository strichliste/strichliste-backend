<?php
/**
 * Created by PhpStorm.
 * User: flo
 * Date: 02.03.19
 * Time: 00:06
 */

namespace App\Service;


use App\Entity\Transaction;
use App\Serializer\TransactionSerializer;
use Bluerhinos\phpMQTT;

class MqttService
{

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var TransactionSerializer
     */
    private $transactionSerializer;

    /**
     * @var bool
     */
    private $enabled = false;

    /**
     * @var bool
     */
    private $connected = false;

    /**
     * @var phpMQTT
     */
    private $client = null;

    public function __construct(
        SettingsService $settingsService,
        TransactionSerializer $transactionSerializer
    ) {
        $this->settingsService = $settingsService;
        $this->transactionSerializer = $transactionSerializer;

        $this->enabled = $this->settingsService->getOrDefault('mqtt.enabled');
    }

    /**
     * @param Transaction $transaction
     */
    public function notify(Transaction $transaction)
    {
        if (!$this->enabled) {
            return;
        }

        $this->connect();

        $mqttClient = $this->getClient();

        $transactionValueTopic = $this->settingsService->getOrDefault('mqtt.topics.transactionValue');
        if (!empty($transactionValueTopic)) {
            $mqttClient->publish(
                $transactionValueTopic,
                $transaction->getAmount()
            );
        }

        $transactionInfoTopic = $this->settingsService->getOrDefault('mqtt.topics.transactionInfo');
        if (!empty($transactionInfoTopic)) {
            $mqttClient->publish(
                $transactionInfoTopic,
                $this->prepareTransactionInfo($transaction)
            );
        }

        $this->disconnect();
    }

    /**
     * @param \App\Entity\Transaction $transaction
     *
     * @return string
     */
    private function prepareTransactionInfo(Transaction $transaction)
    {
        $info = $this->transactionSerializer->serialize($transaction);

        if ($this->settingsService->getOrDefault('mqtt.flattenTransactionInfo')) {
            $info = $this->flattenArray($info);
        }

        return json_encode($info);
    }

    /**
     * @param $array
     *
     * @return array
     */
    private function flattenArray($array)
    {
        $out = [];
        foreach ($array as $key => $item) {
            if (is_array($item)) {
                foreach ($item as $subKey => $subItem) {
                    $out[$key . '_' . $subKey] = $subItem;
                }
            } else {
                $out[$key] = $item;
            }
        }

        return $out;
    }

    /**
     * @return \Bluerhinos\phpMQTT
     */
    public function getClient()
    {
        if (empty($this->client)) {
            $mqttClient = new phpMQTT(
                $this->settingsService->getOrDefault('mqtt.host'),
                $this->settingsService->getOrDefault('mqtt.port'),
                $this->settingsService->getOrDefault('mqtt.clientId')
            );

            $this->client = $mqttClient;
        }
        return $this->client;
    }

    public function connect()
    {
        if (!$this->connected) {
            $mqttClient = $this->getClient();

            $username = null;
            $password = null;

            if ($this->settingsService->getOrDefault('mqtt.authentication')) {
                $username = $this->settingsService->getOrDefault('mqtt.username');
                $password = $this->settingsService->getOrDefault('mqtt.password');
            }

            $connected = $mqttClient->connect(
                true,
                null,
                $username,
                $password
            );

            $this->connected = $connected;
        }
        return $this->connected;
    }

    public function disconnect()
    {
        if ($this->connected) {
            $mqttClient = $this->getClient();
            $mqttClient->disconnect();
            $this->connected = false;
        }

        return $this->connected;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
