<?php

namespace Raptor\Mail;

use GuzzleHttp\Client;
use Brevo\Client\Configuration;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Model\SendSmtpEmail;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LogLevel;

use Raptor\Log\Logger;

class Mailer extends \codesaur\Http\Client\Mail
{
    use \codesaur\DataObject\PDOTrait;
    
    public function __construct(
        \PDO $pdo,
        ?string $from = null, ?string $fromName = null,
        ?string $replyTo = null, ?string $replyToName = null
    ) {
        $this->setInstance($pdo);
        
        $this->setFrom(
            $from ?? $_ENV['INDO_MAIL_FROM'] ?? '',
            $fromName ?? $_ENV['INDO_MAIL_FROM_NAME'] ?? ''
        );
        if (!empty($replyTo ?? $_ENV['INDO_MAIL_REPLY_TO'] ?? '')) {
            $this->setReplyTo(
                $replyTo ?? $_ENV['INDO_MAIL_REPLY_TO'],
                $replyToName ?? $_ENV['INDO_MAIL_REPLY_TO_NAME'] ?? ''
            );
        }
    }
    
    public function mail(
        string $to,
        ?string $toName,
        string $subject,
        string $message,
        ?array $attachments = null
    ): Mailer {
        $this->setSubject($subject);
        $this->setMessage($message);
        $this->targetTo($to, $toName ?? '');
        
        if (\is_array($attachments)) {
            foreach ($attachments as $attachment) {
                $this->addAttachment($attachment);
            }
        }
        
        return $this;
    }
    
    public function send(): bool
    {
        try {
            $context = [];
            
            if (empty($_ENV['INDO_MAIL_BREVO_APIKEY'] ?? '')) {
                throw new \Exception('Mailer BREVO API KEY not found from environment variables!');
            }

            $context['brevo-result'] = $this->sendBrevoTransactional($_ENV['INDO_MAIL_BREVO_APIKEY']);
            if (empty($context['brevo-result'])) {
                throw new \RuntimeException('Email sending failed!');
            }

            $level = LogLevel::NOTICE;
            $context['status'] = 'success';
            $context['message'] = 'Email successfully sent to destination';
            return true;
        } catch (\Throwable $e) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($e->getMessage());
            }

            $level = LogLevel::ERROR;
            $context['status'] = 'error';
            $context['code'] = $e->getCode();
            $context['message'] = $e->getMessage();
            return false;
        } finally {
            $logger = new Logger($this->pdo);
            $logger->setTable('mailer');
            $context['To'] = $this->getRecipients('To');
            $cc = $this->getRecipients('Cc');
            if (!empty($cc)) {
                $context['Cc'] = $cc;
            }
            $bcc = $this->getRecipients('Bcc');
            if (!empty($bcc)) {
                $context['Bcc'] = $bcc;
            }
            $toEmail = \reset($context['To'])['email'] ?? '';            
            $mailSubject = $this->subject ?? '';
            $logger->log(
                $level,
                "[$toEmail] - $mailSubject",
                $context + ['remote_addr' => $_SERVER['REMOTE_ADDR']]
            );
        }
    }
    
    protected function sendSMTP(
        $host, // yourhost
        $port, // 465 or 587 or 25
        $username, //username@yourhost.com
        $password,
        $smtp_secure = 'ssl',
        $smtp_options = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]
    ): bool {   
        $this->assertValues();

        $phpMailer = new PHPMailer(CODESAUR_DEVELOPMENT ? true : null);
        $phpMailer->isSMTP();
        $phpMailer->CharSet = 'UTF-8';
        $phpMailer->SMTPAuth = true;
        $phpMailer->SMTPSecure = $smtp_secure;
        $phpMailer->Host = $host;
        $phpMailer->Port = $port;
        $phpMailer->Username = $username;
        $phpMailer->Password = $password;
        $phpMailer->setFrom($this->from, $this->fromName);
        if (empty($this->replyTo)) {
            $replyTo = $this->from;
            $replyToName = $this->fromName;
        } else {
            $replyTo = $this->replyTo;
            $replyToName = $this->replyToName;
        }
        $phpMailer->addReplyTo($replyTo, $replyToName);
        $phpMailer->SMTPOptions = $smtp_options;
        $phpMailer->msgHTML($this->message);
        $phpMailer->Subject = $this->subject;
        foreach ($this->getRecipients('To') as $to) {
            $phpMailer->addAddress($to['email'], $to['name'] ?? '');
        }
        foreach ($this->getRecipients('Cc') as $cc) {
            $phpMailer->addCC($cc['email'], $cc['name'] ?? '');
        }
        foreach ($this->getRecipients('Bcc') as $bcc) {
            $phpMailer->addBCC($bcc['email'], $bcc['name'] ?? '');
        }

        foreach ($this->getAttachments() as $attachment) {
            if (isset($attachment['path'])) {
                $phpMailer->addAttachment($attachment['path'], $attachment['name']);
            } elseif (isset($attachment['url'])) {
                throw new \Exception('Explicitly *does not* support passing URLs; PHPMailer is not an HTTP client.');
            } elseif (isset($attachment['content'])) {
                $phpMailer->addStringAttachment($attachment['content'], $attachment['name']);
            }
        }

        return $phpMailer->send();
    }
    
    protected function sendBrevoTransactional($apiKey): array
    {
        $this->assertValues();
        
        $credentials = Configuration::getDefaultConfiguration()->setApiKey(
            'api-key', $apiKey // your Brevo API key
        );
        $apiInstance = new TransactionalEmailsApi(new Client(), $credentials);
        $options = [
            'subject' => $this->subject,
            'htmlContent' => $this->message,
            'to' => $this->getRecipients('To')
        ];
        $cc = $this->getRecipients('Cc');
        if (!empty($cc)) {
            $options['cc'] = $cc;
        }
        $bcc = $this->getRecipients('Bcc');
        if (!empty($bcc)) {
            $options['bcc'] = $bcc;
        }        
        if (!empty($this->fromName)) {
            $options['sender'] = ['name' => $this->fromName, 'email' => $this->from];
        } else {
            $options['sender'] = ['email' => $this->from];
        }
        if (!empty($this->replyTo)) {
            if (!empty($this->replyToName)) {
                $options['replyTo'] = ['name' => $this->replyToName, 'email' => $this->replyTo];
            } else {
                $options['replyTo'] = ['email' => $this->replyTo];
            }
        }
        
        $attachments = [];
        foreach ($this->getAttachments() as $attachment) {
            if (isset($attachment['path'])) {
                throw new \Exception("Brevo's SendSmtpEmail doesn't support local file!");
            } elseif (isset($attachment['url'])) {
                $attachments[] = ['url' => $attachment['url'], 'name' => $attachment['name']];
            } elseif (isset($attachment['content'])) {
                $attachments[] = ['content' => $attachment['content'], 'name' => $attachment['name']];
            }
        }
        if (!empty($attachments)) {
            $options['attachment'] = $attachments;
        }
        
        $sendSmtpEmail = new SendSmtpEmail($options);
        return (array) $apiInstance->sendTransacEmail($sendSmtpEmail);
    }
}
