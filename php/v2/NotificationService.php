<?php

namespace NW\WebService\References\Operations\Notification;

class NotificationService
{
    public function notifyClient(string $emailFrom, string $emailTo, int $resellerId, int $clientId, array $templateData, int $to): bool
    {
        if (!empty($emailFrom) && !empty($emailTo)) {
            MessagesClient::sendMessage([
                MessageTypes::EMAIL => [
                    'emailFrom' => $emailFrom,
                    'emailTo'   => $emailTo,
                    'subject'   => __('complaintClientEmailSubject', $templateData, $resellerId),
                    'message'   => __('complaintClientEmailBody', $templateData, $resellerId),
                ],
            ], $resellerId, $clientId, NotificationEvents::CHANGE_RETURN_STATUS, $to);
            return true;
        }
        return false;
    }

    public function notifyEmployees(string $emailFrom, int $resellerId, int $clientId, array $templateData): bool
    {
        $emails = getEmailsByPermit($resellerId, 'tsGoodsReturn');
        $result = false;
        if (!empty($emailFrom) && !empty($emails)) {
            foreach ($emails as $email) {
                MessagesClient::sendMessage([
                    MessageTypes::EMAIL => [
                        'emailFrom' => $emailFrom,
                        'emailTo'   => $email,
                        'subject'   => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                        'message'   => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, $clientId, NotificationEvents::CHANGE_RETURN_STATUS);
                $result = true;
            }
        }
        return $result;
    }
    // По хорошему нужен тут свой класс в большом проекте
    public function notifyMobile($resellerId, $clientId, $to, $templateData): array
    {
        $error = '';
        $res = NotificationManager::send($resellerId, $clientId, NotificationEvents::CHANGE_RETURN_STATUS, $to, $templateData, $error);
        // Представим что здесь по ссылке error должен передаваться
        $result['isSent'] = $res;
        $result['message'] = $error;

        return $result;
    }
}