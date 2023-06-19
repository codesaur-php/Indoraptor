<?php

namespace Indoraptor\Mailer;

use Psr\Log\LogLevel;
use Psr\Http\Message\ResponseInterface;

use codesaur\Http\Client\Mail;

use Indoraptor\Logger\LoggerModel;
use Indoraptor\Localization\TextModel;

class MailerController extends \Indoraptor\IndoController
{
    public function send(): ResponseInterface
    {
        try {
            $context = ['origin' => $this->getRemoteAddr()];
            
            $payload = $this->getParsedBody();
            if (!isset($payload['to'])
                || !isset($payload['subject'])
                || !isset($payload['message'])
                || \filter_var($payload['to'], \FILTER_VALIDATE_EMAIL) === false
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
        } catch (\Throwable $e) {
            $level = LogLevel::ERROR;
            $context['response'] = $e->getMessage();
            return $this->error($context['response'], $e->getCode());
        } finally {
            $logger = new LoggerModel($this->pdo);
            $logger->setTable('mailer', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
            $to = $context['to'] ?? 'Unknown recipient';
            $subject = $context['subject'] ?? 'Unknown message';
            $logger->log($level ?? LogLevel::NOTICE, "[$to] - $subject", $context);
        }
    }
    
    public function sendSMTP(): ResponseInterface
    {
        try {
            $context = ['origin' => $this->getRemoteAddr()];
            
            $payload = $this->getParsedBody();
            if (!isset($payload['to'])
                || !isset($payload['subject'])
                || !isset($payload['message'])
                || \filter_var($payload['to'], \FILTER_VALIDATE_EMAIL) === false
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
            $record = \end($rows);
            
            $lang_code = $payload['code'] ?? 'en';
            $texts = new TextModel($this->pdo);
            $texts->setTable('dashboard', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
            $text = $texts->retrieve($lang_code);

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
                ((int) $record['is_smtp']) == 1, (bool) ((int) $record['smtp_auth']), $record['smtp_secure'], null,
                $lang_code
            );
            
            $context['response'] = $text['email-succesfully-sent'] ?? 'Email successfully sent to destination';
            return $this->respond(['success' => ['message' => $context['response']]]);
        } catch (\Throwable $e) {
            $level = LogLevel::ERROR;
            $context['response'] = $e->getMessage();
            return $this->error($context['response'], $e->getCode());
        } finally {
            $logger = new LoggerModel($this->pdo);
            $logger->setTable('mailer', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
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
    
    private function isValidIP(string $ip): bool
    {
        $real = \ip2long($ip);
        if (empty($ip) || $real == -1 || $real === false) {
            return false;
        }

        $private_ips = [
            ['0.0.0.0', '2.255.255.255'],
            ['10.0.0.0', '10.255.255.255'],
            ['127.0.0.0', '127.255.255.255'],
            ['169.254.0.0', '169.254.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.0.2.0', '192.0.2.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['255.255.255.0', '255.255.255.255']
        ];
        foreach ($private_ips as $r) {
            $min = \ip2long($r[0]);
            $max = \ip2long($r[1]);
            if ($real >= $min && $real <= $max) {
                return false;
            }
        }

        return true;
    }

    private function getRemoteAddr(): string
    {
        $server = $this->getServerParams();
        if (!empty($server['HTTP_X_FORWARDED_FOR'])) {
            if (!empty($server['HTTP_CLIENT_IP'])
                && $this->isValidIP($server['HTTP_CLIENT_IP'])
            ) {
                return $server['HTTP_CLIENT_IP'];
            }
            foreach (\explode(',', $server['HTTP_X_FORWARDED_FOR']) as $ip) {
                if ($this->isValidIP(\trim($ip))) {
                    return $ip;
                }
            }
        }

        if (!empty($server['HTTP_X_FORWARDED'])
            && $this->isValidIP($server['HTTP_X_FORWARDED'])
        ) {
            return $server['HTTP_X_FORWARDED'];
        } elseif (!empty($server['HTTP_X_CLUSTER_CLIENT_IP'])
            && $this->isValidIP($server['HTTP_X_CLUSTER_CLIENT_IP'])
        ) {
            return $server['HTTP_X_CLUSTER_CLIENT_IP'];
        } elseif (!empty($server['HTTP_FORWARDED_FOR'])
            && $this->isValidIP($server['HTTP_FORWARDED_FOR'])
        ) {
            return $server['HTTP_FORWARDED_FOR'];
        } elseif (!empty($server['HTTP_FORWARDED'])
            && $this->isValidIP($server['HTTP_FORWARDED'])
        ) {
            return $server['HTTP_FORWARDED'];
        }

        return $server['REMOTE_ADDR'] ?? '';
    }
}
