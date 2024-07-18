<?php

namespace NW\WebService\References\Operations\Notification;

use Exception;

class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW    = 1;
    public const TYPE_CHANGE = 2;


    private const defaultResult = [
        'notificationEmployeeByEmail' => false,
        'notificationClientByEmail'   => false,
        'notificationClientBySms'     => [
            'isSent'  => false,
            'message' => '',
        ],
    ];

    private NotificationService $notifications; // Ну тут типа интерфейс для него ещё можно было бы, если будут варианты

    public function __construct($registry) // Давайте дружно представим что тут di контейнер прокидывается
    {
        $this->notifications = new NotificationService(); // $registry->get('notificationService');
    }

    public function doOperation(): array
    {
        try {
            $data = $this->getRequest('data');
            $clientId = (int)$data['clientId'];
            $resellerId = (int)$data['resellerId'];
            $notificationType = (int)$data['notificationType'];
            $client = Contractor::getById($clientId);

            if ($client === null || $client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $resellerId) {
                throw new Exception('Client not found!');
            }

            $templateData = $this->getTemplate($this->getRequest('data'), $client);

            $emailFrom = getResellerEmailFrom($resellerId);

            $result['notificationEmployeeByEmail'] = $this->notifications->notifyEmployees($emailFrom, $resellerId, $client->id, $templateData);


            // Шлём клиентское уведомление, только если произошла смена статуса
            if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
                $result['notificationClientByEmail'] = $this->notifications->notifyClient($emailFrom, $client->email, $resellerId, $client->id, $templateData, (int)$data['differences']['to']);

                if (!empty($client->mobile)) {
                    $result['notificationClientBySms'] = $this->notifications->notifyMobile($resellerId, $client->id, $client->mobile, $templateData);
                }
            }

            return $result;
        }
        catch (Exception $e) {
            return $this->createError($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    private function getTemplate(array $data, $client): array
    {
        // Будем считать, что результат возвращать надо всегда.
        // Throwить ошибки когда не знаешь кто их обработает - плохая практика. Тем более что интерфейс не предполагает
        // (позже решил, что лучше локально обработать)
        if (empty($data['resellerId'])) {
            throw new Exception('Empty resellerId');
        }

        if (empty($data['notificationType'])) {
            throw new Exception('Empty notificationType');
        }

        $reseller = Seller::getById($data['resellerId']);
        // Разве getById возвращает null? Принимаю исходя из readme что править надо только этот файл, но тогда половина
        // проверок придется просто так убрать. Поправлю others.php

        if ($reseller === null) {
            throw new Exception('Seller not found!');
        }



        $clientName = $client->getFullName();

        $creator = Employee::getById($data['creatorId']);
        if ($creator === null) {
            throw new Exception('Creator not found!');
        }

        $expert = Employee::getById($data['expertId']);
        if ($expert === null) {
            throw new Exception('Expert not found!');
        }


        $differences = match ($data['notificationType']) {
            self::TYPE_NEW => __('NewPositionAdded', null, $data['resellerId']),
            self::TYPE_CHANGE => !empty($data['differences']) ? __('PositionStatusHasChanged', [
                'FROM' => Status::getName((int)$data['differences']['from']),
                'TO' => Status::getName((int)$data['differences']['to']),
            ], $data['resellerId']) : [],
            default => [],
        };


        $templateData = [
            'COMPLAINT_ID'       => (int)$data['complaintId'],
            'COMPLAINT_NUMBER'   => (string)$data['complaintNumber'],
            'CREATOR_ID'         => (int)$data['creatorId'],
            'CREATOR_NAME'       => $creator->getFullName(),
            'EXPERT_ID'          => (int)$data['expertId'],
            'EXPERT_NAME'        => $expert->getFullName(),
            'CLIENT_ID'          => (int)$data['clientId'],
            'CLIENT_NAME'        => $clientName,
            'CONSUMPTION_ID'     => (int)$data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER'   => (string)$data['agreementNumber'],
            'DATE'               => (string)$data['date'],
            'DIFFERENCES'        => $differences,
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new Exception("Template Data ({$key}) is empty!");
            }
        }

        return $templateData;
    }

    private function createError(string $message) : array
    {
        $default = self::defaultResult;
        $default['notificationClientBySms']['message'] = $message;
        return $default;
    }
    // По итогу, мне кажется дальше выносить в отдельные классы логику странно, возможно я не так понял и я должен был активно менять others.php тоже

}
