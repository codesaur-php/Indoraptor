<?php

namespace Indoraptor\Mail;

use Throwable;
use Exception;

use codesaur\Http\Client\Mail;

use Indoraptor\Localization\TranslationModel;

class EmailController extends \Indoraptor\IndoController
{
    
    public function send()
    {
        try {
            $payload = $this->getParsedBody();
            if (!isset($payload['to'])
                    || !isset($payload['subject'])
                    || !isset($payload['message'])
                    || filter_var($payload['to'], FILTER_VALIDATE_EMAIL) === false) {
                throw new Exception('Invalid Request');
            }
            
            $translation = new TranslationModel($this->pdo);
            $translation->setTable('dashboard');
            $text = $translation->retrieve($payload['code'] ?? 'en');
            
            if (!getenv('MAIL_SENDER', true)) {
                throw new Exception($text['emailer-not-set'] ?? 'Email sender not found!');
            }
            
            (new Mail())->send(getenv('MAIL_SENDER', true), $payload['to'], $payload['subject'], $payload['message']);
            $this->respond(array('success' => array('message' => $text['email-succesfully-sent'] ?? 'Email successfully sent to destination')));
        } catch (Throwable $th) {
            $this->error($th->getMessage(), $th->getCode());
        }
    }
    
    public function sendSMTP()
    {
        try {
            $payload = $this->getParsedBody();
            if (!isset($payload['to'])
                    || !isset($payload['subject'])
                    || !isset($payload['message'])
                    || filter_var($payload['to'], FILTER_VALIDATE_EMAIL) === false) {
                throw new Exception('Invalid Request');
            }
        
            $model = new MailerModel($this->pdo);
            $rows = $model->getRows();
            $record = end($rows);

            $translation = new TranslationModel($this->pdo);
            $translation->setTable('dashboard');
            $text = $translation->retrieve($payload['code'] ?? 'en');

            if (empty($record) || !isset($record['charset'])
                    || !isset($record['host']) || !isset($record['port'])
                    || !isset($record['email']) || !isset($record['name'])
                    || !isset($record['username']) || !isset($record['password'])
                    || !isset($record['is_smtp']) || !isset($record['smtp_auth']) || !isset($record['smtp_secure'])) {
                throw new Exception($text['emailer-not-set'] ?? 'Email carrier not found!');
            }
            
            (new Mail())->sendSMTP(
                    $record['email'], $record['name'], $payload['to'], $payload['name'] ?? '',
                    $payload['subject'], $payload['message'], $record['charset'],
                    $record['host'], $record['port'], $record['username'], $record['password'],
                    ((int)$record['is_smtp']) == 1, (bool)((int)$record['smtp_auth']), $record['smtp_secure']);
            
            $this->respond(array('success' => array('message' => $text['email-succesfully-sent'] ?? 'Email successfully sent to destination')));
        } catch (Throwable $th) {
            $this->error($th->getMessage(), $th->getCode());
        }
    }
}
