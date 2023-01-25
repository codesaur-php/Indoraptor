<?php

namespace Indoraptor\Mailer;

use Psr\Log\LogLevel;
use Psr\Http\Message\ResponseInterface;

use codesaur\Globals\Server;
use codesaur\Http\Client\Mail;

use Indoraptor\Logger\LoggerModel;
use Indoraptor\Localization\TextModel;

class MailerController extends \Indoraptor\IndoController
{
    public function send(): ResponseInterface
    {
        try {
            $context = ['origin' => (new Server())->getRemoteAddr()];
            
            $payload = $this->getParsedBody();
            if (!isset($payload['to'])
                || !isset($payload['subject'])
                || !isset($payload['message'])
                || filter_var($payload['to'], \FILTER_VALIDATE_EMAIL) === false
            ) {
                throw new \Exception('Invalid Request');
            } else {
                $context['to'] = $payload['to'];
                $context['subject'] = $payload['subject'];
            }
            
            $texts = new TextModel($this->pdo);
            $texts->setTable('dashboard', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
            $text = $texts->retrieve($payload['code'] ?? 'en');
            
            if (empty($_ENV['CODESAUR_MAIL_ADDRESS'])) {
                throw new \Exception($text['emailer-not-set'] ?? 'Email sender not found!');
            } else {
                $context['from'] = $_ENV['CODESAUR_MAIL_ADDRESS'];
            }
            
            (new Mail())->send($_ENV['CODESAUR_MAIL_ADDRESS'], $payload['to'], $payload['subject'], $payload['message']);
            
            $context['response'] = $text['email-succesfully-sent'] ?? 'Email successfully sent to destination';
            return $this->respond(['success' => ['message' => $context['response']]]);
        } catch (\Throwable $th) {
            $level = LogLevel::ERROR;
            $context['response'] = $th->getMessage();
            return $this->error($context['response'], $th->getCode());
        } finally {
            $logger = new LoggerModel($this->pdo);
            $logger->setTable('mailer', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
            $by_account = getenv('CODESAUR_ACCOUNT_ID', true);
            if ($by_account !== false && is_int($by_account)) {
                $logger->prepareCreatedBy((int) $by_account);
            }
            $to = $context['to'] ?? 'Unknown recipient';
            $subject = $context['subject'] ?? 'Unknown message';
            $logger->log($level ?? LogLevel::NOTICE, "[$to] - $subject", $context);
        }
    }
    
    public function sendSMTP(): ResponseInterface
    {
        try {
            $context = ['origin' => (new Server())->getRemoteAddr()];
            
            $payload = $this->getParsedBody();
            if (!isset($payload['to'])
                || !isset($payload['subject'])
                || !isset($payload['message'])
                || filter_var($payload['to'], \FILTER_VALIDATE_EMAIL) === false
            ) {
                throw new \Exception('Invalid Request');
            } else {
                $context['to'] = $payload['to'];
                if (!empty($payload['name'])) {
                    $context['to_name'] = $payload['name'];
                }
                $context['subject'] = $payload['subject'];
            }
        
            $model = new MailerModel($this->pdo);
            $rows = $model->getRows();
            $record = end($rows);

            $texts = new TextModel($this->pdo);
            $texts->setTable('dashboard', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
            $text = $texts->retrieve($payload['code'] ?? 'en');

            if (empty($record) || !isset($record['charset'])
                || !isset($record['host']) || !isset($record['port'])
                || !isset($record['email']) || !isset($record['name'])
                || !isset($record['username']) || !isset($record['password'])
                || !isset($record['is_smtp']) || !isset($record['smtp_auth']) || !isset($record['smtp_secure'])
            ) {
                throw new \Exception($text['emailer-not-set'] ?? 'Email carrier not found!');
            } else {
                $context['from'] = $record['email'];
                $context['host'] = $record['host'];
                $context['port'] = $record['port'];
                $context['username'] = $record['username'];
            }
            
            (new Mail())->sendSMTP(
                $record['email'], $record['name'], $payload['to'], $payload['name'] ?? '',
                $payload['subject'], $payload['message'], $record['charset'],
                $record['host'], $record['port'], $record['username'], $record['password'],
                ((int) $record['is_smtp']) == 1, (bool) ((int) $record['smtp_auth']), $record['smtp_secure']);
            
            $context['response'] = $text['email-succesfully-sent'] ?? 'Email successfully sent to destination';
            return $this->respond(['success' => ['message' => $context['response']]]);
        } catch (\Throwable $th) {
            $level = LogLevel::ERROR;
            $context['response'] = $th->getMessage();
            return $this->error($context['response'], $th->getCode());
        } finally {
            $logger = new LoggerModel($this->pdo);
            $logger->setTable('mailer', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
            $by_account = getenv('CODESAUR_ACCOUNT_ID', true);
            if ($by_account !== false && is_int($by_account)) {
                $logger->prepareCreatedBy((int) $by_account);
            }
            $to = $context['to'] ?? 'Unknown recipient';
            $subject = $context['subject'] ?? 'Unknown message';
            if (isset($context['to_name'])) {
                $message = "{$context['to_name']} - [$to] - $subject";
            } else {
                $message = "[$to] - $subject";
            }
            $logger->log($level ?? LogLevel::INFO, $message, $context);
        }
    }
}
